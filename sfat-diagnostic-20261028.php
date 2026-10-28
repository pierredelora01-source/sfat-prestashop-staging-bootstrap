<?php
declare(strict_types=1);
// Incident diagnostic: CLI only, no application or database modifications.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = __DIR__;
if (!is_file($root . '/config/config.inc.php')) { exit("SFAT_DIAG wrong root\n"); }
$lockPath = sys_get_temp_dir() . '/sfat-diag-20261028-' . sha1($root) . '.done';
$lock = @fopen($lockPath, 'x');
if ($lock === false) { exit; }
fclose($lock);
echo 'SFAT_DIAG START ' . date(DATE_ATOM) . "\n";
echo 'PHP=' . PHP_VERSION . ' memory_limit=' . ini_get('memory_limit') . "\n";
foreach (['var/cache', 'var/cache/prod', 'var/logs', 'var/log', 'app/logs'] as $dir) {
    echo $dir . ': exists=' . (int)is_dir($root . '/' . $dir) . ' writable=' . (int)is_writable($root . '/' . $dir) . "\n";
}
$files = [];
foreach (['var/logs/*.log', 'var/log/*.log', 'app/logs/*.log'] as $pattern) {
    foreach (glob($root . '/' . $pattern) ?: [] as $file) { if (is_file($file)) { $files[$file] = filemtime($file); } }
}
arsort($files);
foreach (array_slice(array_keys($files), 0, 3) as $file) {
    echo 'LOG ' . basename($file) . ' modified=' . date(DATE_ATOM, $files[$file]) . "\n";
    $fp = @fopen($file, 'rb');
    if (!$fp) { echo "unreadable\n"; continue; }
    fseek($fp, max(0, filesize($file) - 1048576));
    $tail = stream_get_contents($fp); fclose($fp);
    $lines = preg_split('/\r?\n/', $tail ?: '');
    $errors = [];
    foreach ($lines as $line) {
        if (!preg_match('/\.(CRITICAL|ERROR|EMERGENCY|ALERT):/', $line)) { continue; }
        // Exclude structured request, SQL, cookie and credential contexts.
        $line = preg_split('/\s+\{/', $line, 2)[0];
        $line = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $line);
        $line = preg_replace('/(?:https?:\/\/|mysql:\/\/|pgsql:\/\/)[^\s"<>]+/i', '[url]', $line);
        $line = preg_replace('/(?i)(password|passwd|secret|token|cookie|authorization)(\s*[=:]\s*)([^\s,;]+)/', '$1$2[redacted]', $line);
        $errors[] = substr($line, 0, 1200);
    }
    foreach (array_slice(array_values(array_unique($errors)), -8) as $error) { echo $error . "\n"; }
}
echo "SFAT_DIAG END\n";
