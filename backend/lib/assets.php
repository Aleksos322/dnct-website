<?php
/**
 * Adresy plików statycznych z odciskiem wersji.
 *
 * PROBLEM: przeglądarka trzyma CSS i JS w cache. Po wgraniu poprawki część
 * odwiedzających dostaje nowy HTML ze starym arkuszem - układ rozjeżdża się
 * dokładnie u tych osób, u których nie da się tego powtórzyć, bo autor ma już
 * świeże pliki. Kosztowało to raz pół godziny szukania błędu w CSS-ie, który
 * był poprawny; przeglądarka po prostu go nie pobrała.
 *
 * ROZWIĄZANIE: do adresu dopisujemy czas modyfikacji pliku:
 *
 *     /assets/css/cennik.css?v=1786516451
 *
 * Zmiana pliku zmienia adres, więc cache unieważnia się sam. Adres jest stały,
 * dopóki plik się nie zmienia, więc powracający klienci nadal pobierają arkusz
 * raz. Nie ma tu żadnego kroku budowania do wykonania - wystarczy wgrać plik.
 *
 * Parametr zapytania jest tu wystarczający, bo pliki serwuje Apache prosto
 * z dysku. Gdyby przed nim stanął cache pomijający query string, trzeba by
 * przejść na nazwy z odciskiem (`cennik.1786516451.css`) i regułę w .htaccess.
 */

declare(strict_types=1);

/**
 * @param string $path Ścieżka absolutna od korzenia strony, np. "/assets/css/cennik.css"
 */
function asset(string $path): string
{
    // Znacznik czasu liczymy raz na plik - strona wywołuje asset() kilka razy
    // na żądanie, a stat() na dysku nie jest darmowy.
    static $stamps = [];

    // Bez bootstrapu nie wiemy, gdzie leży katalog strony. Oddajemy wtedy adres
    // bez znacznika - strona ma się wyświetlić, choćby ze starym arkuszem.
    // Wersjonowanie jest wygodą, a nie warunkiem działania widoku.
    if (!defined('DNCT_BASE_PATH')) {
        return $path;
    }

    if (!array_key_exists($path, $stamps)) {
        $file = DNCT_BASE_PATH . $path;
        // Brak pliku zostawiamy bez znacznika zamiast wywalać stronę: literówka
        // w ścieżce ma dać brakujący styl, a nie biały ekran.
        $stamps[$path] = is_file($file) ? (string)filemtime($file) : null;
    }

    return $stamps[$path] === null ? $path : $path . '?v=' . $stamps[$path];
}
