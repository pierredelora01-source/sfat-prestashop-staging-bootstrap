<?php
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Content-Type: text/plain; charset=UTF-8');
set_time_limit(0);

const RELEASE_URL = 'https://github.com/PrestaShop/PrestaShop/releases/download/8.2.8/prestashop_8.2.8.zip';
const RELEASE_SHA256 = '0d6931ed9ecb2636ae8024c5660bb72dc3c5f835738b79d3c1bda984e353598d';

$root = __DIR__;
$archive = $root . '/.prestashop_8.2.8.zip';
$lockHandle = fopen($root . '/.bootstrap.lock', 'c');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    http_response_code(503);
    exit("Installation déjà en cours. Réessaie dans quelques instants.\n");
}

try {
    if (is_file($root . '/prestashop.zip') || is_dir($root . '/install')) {
        header('Location: /index.php');
        exit;
    }

    if (!extension_loaded('curl') || !class_exists('ZipArchive')) {
        throw new RuntimeException('Les extensions PHP cURL et ZIP sont requises.');
    }

    $destination = fopen($archive, 'wb');
    if ($destination === false) {
        throw new RuntimeException('Impossible de créer le fichier temporaire.');
    }

    $curl = curl_init(RELEASE_URL);
    curl_setopt_array($curl, [
        CURLOPT_FILE => $destination,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FAILONERROR => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'SFAT staging bootstrap',
    ]);

    $downloaded = curl_exec($curl);
    $curlError = curl_error($curl);
    curl_close($curl);
    fclose($destination);

    if ($downloaded !== true) {
        throw new RuntimeException('Téléchargement impossible : ' . $curlError);
    }

    if (!hash_equals(RELEASE_SHA256, hash_file('sha256', $archive))) {
        throw new RuntimeException('Empreinte SHA-256 incorrecte : archive refusée.');
    }

    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) {
        throw new RuntimeException('Impossible d’ouvrir l’archive officielle.');
    }

    if (!$zip->extractTo($root)) {
        $zip->close();
        throw new RuntimeException('Impossible d’extraire l’archive officielle.');
    }

    $zip->close();
    @unlink($archive);
    header('Location: /index.php');
    exit;
} catch (Throwable $error) {
    @unlink($archive);
    http_response_code(500);
    echo "Le bootstrap PrestaShop a échoué.\n";
    echo $error->getMessage() . "\n";
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
