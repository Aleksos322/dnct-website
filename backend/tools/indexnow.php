<?php
/**
 * IndexNow: powiadamia Binga (i Yandex, Seznam, Naver) o adresach z sitemap.xml.
 * Bez tego Bing odkrywa nowe strony sam, po tygodniach. Klucz jest jawny
 * z założenia - leży w pliku {klucz}.txt w katalogu strony, tak każe protokół.
 *
 * Użycie po wdrożeniu nowej albo zmienionej strony:
 *   php backend/tools/indexnow.php            (wszystkie adresy z sitemap.xml)
 *   php backend/tools/indexnow.php /cennik    (tylko wybrane ścieżki)
 */

declare(strict_types=1);

const INDEXNOW_KLUCZ = '59c49932255c8ebca555453fc1a4e695';
const INDEXNOW_HOST  = 'game.dnct.pl';

$adresy = array_map(static fn(string $s): string => 'https://' . INDEXNOW_HOST . '/' . ltrim($s, '/'), array_slice($argv, 1));
if (!$adresy) {
    $xml = simplexml_load_file(dirname(__DIR__, 2) . '/sitemap.xml');
    foreach ($xml->url as $u) {
        $adresy[] = (string)$u->loc;
    }
}

$c = curl_init('https://api.indexnow.org/indexnow');
curl_setopt_array($c, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
    CURLOPT_POSTFIELDS     => json_encode([
        'host'        => INDEXNOW_HOST,
        'key'         => INDEXNOW_KLUCZ,
        'keyLocation' => 'https://' . INDEXNOW_HOST . '/' . INDEXNOW_KLUCZ . '.txt',
        'urlList'     => $adresy,
    ], JSON_UNESCAPED_SLASHES),
]);
$odp = curl_exec($c);
$kod = curl_getinfo($c, CURLINFO_HTTP_CODE);
echo 'IndexNow: HTTP ', $kod, ' (200/202 = przyjęte), adresów: ', count($adresy), PHP_EOL;
if ($kod >= 400) {
    echo $odp, PHP_EOL;
    exit(1);
}
