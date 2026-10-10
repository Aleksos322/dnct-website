<?php
/**
 * Opinie z Wizytówki Google (Google Places API "New") na stronę główną.
 *
 * Dwie zmienne w .env:
 *   GOOGLE_PLACES_API_KEY - klucz z Google Cloud, ograniczony do "Places API (New)"
 *   GOOGLE_PLACE_ID       - ID miejsca DNCT Hosting (narzędzie: backend/tools/google-place-id.php)
 * Bez którejkolwiek sekcja opinii po prostu się nie pokazuje.
 *
 * Nowe opinie wchodzą same: odpowiedź Google jest cache'owana na dysku
 * GOOGLE_REVIEWS_TTL sekund (wzorzec widgetu Discorda w index.php), więc
 * dzisiejsza opinia jest na stronie najpóźniej po tym czasie, a API woła się
 * kilka razy na dobę zamiast przy każdym wejściu.
 *
 * Ograniczenie Google: API zwraca najwyżej 5 opinii ("najtrafniejsze", nie
 * najnowsze). Przy 5 opiniach to wszystkie; przy większej liczbie Google sam
 * wybiera piątkę. Całej listy bez zgody Google na Business Profile API nie ma.
 */

declare(strict_types=1);

const GOOGLE_REVIEWS_TTL = 6 * 3600;
/** Na stronie tylko opinie z treścią i co najmniej tyloma gwiazdkami. Średnia i liczba - zawsze ze wszystkich. */
const GOOGLE_REVIEWS_MIN_GWIAZDEK = 4;

/**
 * @return array{rating:float, count:int, url:string, reviews:list<array{autor:string, zdjecie:string, profil:string, gwiazdki:int, tekst:string, kiedy:string}>}|null
 */
function google_reviews(): ?array
{
    $key = (string)env('GOOGLE_PLACES_API_KEY', '');
    $placeId = (string)env('GOOGLE_PLACE_ID', '');
    if ($key === '' || $placeId === '') {
        return null;
    }

    $cacheFile = sys_get_temp_dir() . '/dnct_google_reviews_' . md5($placeId) . '.json';
    $dane = null;
    if (is_readable($cacheFile) && (time() - (int)@filemtime($cacheFile)) < GOOGLE_REVIEWS_TTL) {
        $dane = json_decode((string)@file_get_contents($cacheFile), true);
    }

    if (!is_array($dane)) {
        $ch = curl_init('https://places.googleapis.com/v1/places/' . rawurlencode($placeId) . '?languageCode=pl&regionCode=PL');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER     => [
                'X-Goog-Api-Key: ' . $key,
                'X-Goog-FieldMask: rating,userRatingCount,googleMapsUri,reviews',
            ],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $swieze = $code === 200 ? json_decode((string)$raw, true) : null;
        if (is_array($swieze)) {
            $dane = $swieze;
            @file_put_contents($cacheFile, json_encode($swieze, JSON_UNESCAPED_UNICODE));
        } else {
            error_log('google_reviews: HTTP ' . $code . ' ' . substr((string)$raw, 0, 300));
            // Google chwilowo nie odpowiada albo klucz padł - lepszy przeterminowany cache niż pusta sekcja.
            $dane = is_readable($cacheFile) ? json_decode((string)@file_get_contents($cacheFile), true) : null;
            // Dotknięcie pliku: kolejna próba dopiero po TTL, nie przy każdym wejściu.
            if (is_array($dane)) {
                @touch($cacheFile);
            }
        }
    }

    if (!is_array($dane)) {
        return null;
    }

    $opinie = [];
    foreach ($dane['reviews'] ?? [] as $r) {
        $tekst = trim((string)($r['text']['text'] ?? $r['originalText']['text'] ?? ''));
        $gwiazdki = (int)($r['rating'] ?? 0);
        if ($tekst === '' || $gwiazdki < GOOGLE_REVIEWS_MIN_GWIAZDEK) {
            continue;
        }
        $autor = $r['authorAttribution'] ?? [];
        $opinie[] = [
            'autor'    => (string)($autor['displayName'] ?? 'Klient Google'),
            'zdjecie'  => (string)($autor['photoUri'] ?? ''),
            'profil'   => (string)($autor['uri'] ?? ''),
            'gwiazdki' => $gwiazdki,
            'tekst'    => $tekst,
            'kiedy'    => (string)($r['relativePublishTimeDescription'] ?? ''),
        ];
    }

    // Google nie oddaje treści opinii przez API (świeża wizytówka) - wtedy
    // teksty przepisane ręcznie z wizytówki (backend/data/opinie-reczne.php).
    // Ocena i liczba opinii dalej idą z API.
    if ($opinie === []) {
        $reczne = @include dirname(__DIR__) . '/data/opinie-reczne.php';
        foreach (is_array($reczne) ? $reczne : [] as $r) {
            $opinie[] = [
                'autor'    => (string)$r['autor'],
                'zdjecie'  => '',
                'profil'   => '',
                'gwiazdki' => (int)($r['gwiazdki'] ?? 5),
                'tekst'    => trim((string)$r['tekst']),
                'kiedy'    => (string)($r['kiedy'] ?? ''),
            ];
        }
    }

    if ($opinie === []) {
        return null;
    }

    return [
        'rating'  => (float)($dane['rating'] ?? 0),
        'count'   => (int)($dane['userRatingCount'] ?? count($opinie)),
        'url'     => (string)($dane['googleMapsUri'] ?? ''),
        'reviews' => $opinie,
    ];
}
