<?php
/**
 * Proxy avatara (menu strony: assets/navibar/navibar.php).
 *
 * Beta ma własną bazę testową, w której nazwa pliku avatara może być starsza
 * od aktualnej. Źródłem prawdy jest account.dnct.pl, a obraz podajemy dalej
 * z tej samej domeny, aby przeglądarka nie napotkała ograniczeń cross-origin.
 */

declare(strict_types=1);

$username = (string)($_GET['u'] ?? '');
if (!preg_match('/^[A-Za-z0-9_.+-]{3,150}$/', $username)) {
    http_response_code(404);
    exit;
}

$request = curl_init('https://account.dnct.pl/avatar/' . rawurlencode($username));
curl_setopt_array($request, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 2,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT        => 4,
    CURLOPT_USERAGENT      => 'DNCT-Avatar/1.0',
]);
$image = curl_exec($request);
$status = (int)curl_getinfo($request, CURLINFO_RESPONSE_CODE);
$type = (string)curl_getinfo($request, CURLINFO_CONTENT_TYPE);
curl_close($request);

if ($image === false || $status !== 200 || !str_starts_with(strtolower($type), 'image/webp')) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/webp');
header('Content-Length: ' . strlen($image));
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
echo $image;
