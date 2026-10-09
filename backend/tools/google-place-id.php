<?php
/**
 * Szuka ID miejsca w Google (do GOOGLE_PLACE_ID w .env) - jednorazowo, po
 * dodaniu GOOGLE_PLACES_API_KEY.
 *
 *   php backend/tools/google-place-id.php                 # szuka "DNCT Hosting"
 *   php backend/tools/google-place-id.php "inna nazwa"
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';

$key = (string)env('GOOGLE_PLACES_API_KEY', '');
if ($key === '') {
    exit("Brak GOOGLE_PLACES_API_KEY w .env.\n");
}

$ch = curl_init('https://places.googleapis.com/v1/places:searchText');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['textQuery' => $argv[1] ?? 'DNCT Hosting', 'languageCode' => 'pl', 'regionCode' => 'PL']),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . $key,
        'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount',
    ],
]);
$raw = (string)curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    exit("Google odpowiedział HTTP {$code}:\n{$raw}\n");
}
foreach (json_decode($raw, true)['places'] ?? [] as $p) {
    printf("%s  %s  (%s)  %s gw., %d opinii\n", $p['id'], $p['displayName']['text'] ?? '?',
        $p['formattedAddress'] ?? 'bez adresu', $p['rating'] ?? '-', $p['userRatingCount'] ?? 0);
}
