<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');

$path = __DIR__ . '/install/classes/controllerHttp.php';
if (!is_file($path) || !is_writable($path)) {
    http_response_code(500);
    exit("ERROR: installer controller is missing or not writable\n");
}

$source = file_get_contents($path);
if ($source === false) {
    http_response_code(500);
    exit("ERROR: cannot read installer controller\n");
}

$needle = "        die(json_encode([";
$patched = "        // OVH may preserve an earlier 500 status although the installer step succeeded.\n"
         . "        // Force the transport status to 200; the JSON success flag remains authoritative.\n"
         . "        http_response_code(200);\n\n"
         . $needle;

if (strpos($source, 'OVH may preserve an earlier 500 status') !== false) {
    echo "OK: patch already present\n";
    @unlink(__FILE__);
    exit;
}

if (substr_count($source, $needle) !== 1) {
    http_response_code(500);
    exit("ERROR: expected installer code was not found uniquely\n");
}

$newSource = str_replace($needle, $patched, $source);
$tmp = $path . '.sfat-tmp';
if (file_put_contents($tmp, $newSource, LOCK_EX) === false || !rename($tmp, $path)) {
    @unlink($tmp);
    http_response_code(500);
    exit("ERROR: cannot update installer controller\n");
}

echo "OK: installer HTTP status patch applied\n";
@unlink(__FILE__);
