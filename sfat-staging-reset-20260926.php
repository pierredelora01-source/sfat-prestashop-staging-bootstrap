<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

const EXPECTED_ROOT = '8891o0oywz';
const EXPECTED_HOST = 'dev.sfat-industrie.fr';
const RUN_TOKEN = 'c68eaf63dbfd4fa183c349e6d969f53d2708e14e4fe26e141f71328bd8b96b9d';

$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$token = (string)($_GET['token'] ?? '');

if (basename(__DIR__) !== EXPECTED_ROOT || $host !== EXPECTED_HOST) {
    http_response_code(403);
    exit("Refused: unexpected staging target.\n");
}
if (!hash_equals(RUN_TOKEN, $token)) {
    http_response_code(403);
    exit("Refused: invalid token.\n");
}

$parent = dirname(__DIR__);
$suffix = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
$backupName = EXPECTED_ROOT . '-backup-' . $suffix;
$backup = $parent . DIRECTORY_SEPARATOR . $backupName;

if (!is_writable($parent) || file_exists($backup) || !mkdir($backup, 0750)) {
    http_response_code(500);
    exit("Backup directory could not be created.\n");
}

$items = array_values(array_filter(scandir(__DIR__) ?: [], static fn(string $item): bool => $item !== '.' && $item !== '..'));
$self = basename(__FILE__);
usort($items, static fn(string $a, string $b): int => ($a === $self ? 1 : 0) <=> ($b === $self ? 1 : 0));

$moved = [];
foreach ($items as $item) {
    $from = __DIR__ . DIRECTORY_SEPARATOR . $item;
    $to = $backup . DIRECTORY_SEPARATOR . $item;
    if (!rename($from, $to)) {
        foreach (array_reverse($moved) as $done) {
            @rename($backup . DIRECTORY_SEPARATOR . $done, __DIR__ . DIRECTORY_SEPARATOR . $done);
        }
        @rmdir($backup);
        http_response_code(500);
        exit("Move failed on: {$item}. Previous files were restored.\n");
    }
    $moved[] = $item;
}

echo "OK\n";
echo "Backup: {$backupName}\n";
echo "Moved entries: " . count($moved) . "\n";
