<?php
/**
 * Wersja językowa strony: PL (domyślnie), EN i DE.
 *
 * Jak to działa (12.10.2026):
 *   - Język wybiera się linkiem ?lang=en|de|pl (przełącznik w navbarze);
 *     wybór zostaje w ciasteczku dnct_lang na rok. Bez wyboru język
 *     zgaduje i18n_wykryj() z kraju i przeglądarki.
 *   - Strony NIE są przepisywane na klucze. Po zbudowaniu całej strony
 *     filtr i18n_filtr() podmienia teksty według słownika
 *     backend/data/i18n-slownik.php (polski tekst -> [en, de]). Tekst bez
 *     wpisu w słowniku zostaje po polsku - dlatego regulamin, konto itd.
 *     świadomie zostają polskie (zakres: strona główna, cennik, strony gier).
 *   - Teksty dopisywane przez JavaScript (konfigurator, czat w paśmie
 *     Discorda, makieta panelu) tłumaczy assets/js/i18n.js tym samym
 *     słownikiem, wstrzykniętym do strony jako JSON.
 *   - Ceny: TYLKO WYŚWIETLANIE w euro, po stałym kursie I18N_KURS_EUR,
 *     zaokrąglone w górę do ,99. Płatność w Stripe dalej idzie w złotych -
 *     zamówienie mówi to wprost (wpis "Ceny brutto w złotych..." w słowniku).
 *
 * Bufor z filtrem startuje tylko dla stron HTML w języku innym niż PL, więc
 * polska wersja, API i pobieranie plików (/pobierz) nie płacą za nic.
 */

declare(strict_types=1);

const I18N_JEZYKI = ['pl', 'en', 'de'];
/** Kurs do wyświetlania cen w euro. Zmiana kursu = zmiana tej liczby. */
const I18N_KURS_EUR = 4.30;

function i18n_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $lang = 'pl';
    $z = strtolower((string)($_GET['lang'] ?? ''));
    if (in_array($z, I18N_JEZYKI, true)) {
        $lang = $z;
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie('dnct_lang', $lang, [
                'expires'  => time() + 365 * 86400,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        return $lang;
    }
    $c = (string)($_COOKIE['dnct_lang'] ?? '');
    if (in_array($c, I18N_JEZYKI, true)) {
        $lang = $c;
        return $lang;
    }
    $lang = i18n_wykryj();
    return $lang;
}

/**
 * Pierwsze wejście bez wyboru (13.10.2026): język z kraju odwiedzającego
 * (CF-IPCountry z Cloudflare, ten sam nagłówek co w lib/odwiedziny.php)
 * i języka przeglądarki. Niczego nie zapisuje - ręczny wybór w navbarze
 * ustawia ciasteczko i ono zawsze wygrywa.
 *
 *   - roboty -> PL: Google indeksuje adresy bez ?lang i ma widzieć polską
 *     stronę, a crawluje głównie z USA (dostałby angielską);
 *   - przeglądarka po polsku -> PL (Polak za granicą);
 *   - Niemcy, Austria, Szwajcaria, Liechtenstein, Luksemburg albo
 *     przeglądarka po niemiecku -> DE;
 *   - Polska albo brak nagłówka kraju (LAN, wejście z pominięciem
 *     Cloudflare'a) -> PL;
 *   - każdy inny kraj -> EN.
 */
function i18n_wykryj(): string
{
    if (PHP_SAPI === 'cli') {
        return 'pl';
    }
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (function_exists('odwiedziny_ua') ? odwiedziny_ua($ua)[3] : ($ua === '' || preg_match('/bot|crawl|spider/i', $ua))) {
        return 'pl';
    }
    $przegladarka = strtolower(substr(trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')), 0, 2));
    $kraj = strtoupper((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if ($przegladarka === 'pl') {
        return 'pl';
    }
    if (in_array($kraj, ['DE', 'AT', 'CH', 'LI', 'LU'], true) || $przegladarka === 'de') {
        return 'de';
    }
    if ($kraj === '' || $kraj === 'PL') {
        return 'pl';
    }
    return 'en';
}

/** @return array<string, array{0:string,1:string}> */
function i18n_slownik(): array
{
    static $s = null;
    if ($s === null) {
        $s = require dirname(__DIR__) . '/data/i18n-slownik.php';
    }
    return $s;
}

/**
 * Wzorce dla tekstów z liczbami/nazwami ("35 slotów", "Zostały 3 wolne...").
 * Wspólne z JS (wstrzykiwane razem ze słownikiem). $1, $2 = grupy.
 *
 * @return list<array{0:string,1:string,2:string}>  [regex bez ogranicznika, en, de]
 */
function i18n_wzorce(): array
{
    return [
        ['^(\d+) slotów$', '$1 slots', '$1 Slots'],
        ['^Zostały (\d+) wolne miejsca na węźle$', 'Only $1 spots left on this node', 'Nur noch $1 Plätze auf diesem Node'],
        ['^Zostały (\d+) wolne miejsca na węźle dla tej liczby slotów\.$', 'Only $1 spots left on the node for this slot count.', 'Nur noch $1 Plätze auf dem Node für diese Slotanzahl.'],
        ['^za (tydzień|rok) · (.+)/mies\.$', 'per $1 · $2/mo', 'pro $1 · $2/Monat'],
        ['^Oszczędzasz (.+)$', 'You save $1', 'Du sparst $1'],
        ['^Zamawiam: (.+), (.+) miesięcznie$', 'Order: $1, $2 per month', 'Bestellen: $1, $2 pro Monat'],
        ['^(Minecraft|Project Zomboid|Vintage Story) · od (.+)$', '$1 · from $2', '$1 · ab $2'],
        ['^Najczęstsze pytania o (.+)$', 'FAQ: $1', 'Häufige Fragen zu $1'],
        ['^(\d+) opinii w Google →$', '$1 reviews on Google →', '$1 Bewertungen auf Google →'],
        ['^(\d+) opinie w Google →$', '$1 reviews on Google →', '$1 Bewertungen auf Google →'],
        ['^Dziś o (.+)$', 'Today at $1', 'Heute um $1'],
        ['^Cześć, (.+)!$', 'Hi, $1!', 'Hallo, $1!'],
        ['^Hosting (Minecraft|Project Zomboid|Vintage Story) - serwer od (.+)/mies\. \| DNCT$', '$1 server hosting from $2/mo | DNCT', '$1 Server-Hosting ab $2/Monat | DNCT'],
        ['^\+(\d+) więcej$', '+$1 more', '+$1 weitere'],
        ['^Pomoc: (Minecraft|Project Zomboid|Vintage Story)$', 'Help: $1', 'Hilfe: $1'],
        // Poradniki (components/poradnik.php, poradniki/index.php)
        ['^Aktualizacja: ([\d.]+) · (.+)$', 'Updated: $1 · $2', 'Aktualisiert: $1 · $2'],
        ['^Hosting (Minecraft|Project Zomboid|Vintage Story) od (.+)/mies\.$', '$1 hosting from $2/mies.', '$1-Hosting ab $2/mies.'],
        // Twoje serwery (twoje-serwery.php, servers.js)
        ['^Zamówienie #(\d+) · kupione ([\d.]+)( ·)?$', 'Order #$1 · bought $2$3', 'Bestellung #$1 · gekauft am $2$3'],
        ['^Dodany bezpośrednio w panelu · utworzony ([\d.]+)( ·)?$', 'Added directly in the panel · created $1$2', 'Direkt im Panel angelegt · erstellt am $1$2'],
        ['^(\d+) dni po terminie$', '$1 days overdue', '$1 Tage überfällig'],
        ['^(\d+) dni$', '$1 days', '$1 Tage'],
        ['^\+(\d+) dni$', '+$1 days', '+$1 Tage'],
        ['^bez dopłaty · \+(\d+) dni$', 'no extra cost · +$1 days', 'ohne Aufpreis · +$1 Tage'],
        ['^dopłata (.+)$', 'top-up $1', 'Aufpreis $1'],
        ['^(.+) zł/mies\. dalej$', '$1 zł/mies. afterwards', '$1 zł/mies. danach'],
        ['^(\d\d\.\d\d\.\d{4}) o (\d\d:\d\d)$', '$1 at $2', '$1 um $2'],
        ['^do (.+), potem odnawia się sam$', 'until $1, then renews automatically', 'bis $1, danach verlängert er sich selbst'],
        ['^serwer będzie działał do (.+)$', 'the server will run until $1', 'der Server läuft bis $1'],
        ['^ostatnia: (.+)$', 'last: $1', 'letztes: $1'],
        ['^Nie udało się pobrać opłaty\. Serwer działa do (.+) - popraw dane karty albo przedłuż jednorazowo\.$', 'We couldn\'t charge your card. The server runs until $1 - update your card details or renew with a one-off payment.', 'Die Abbuchung ist fehlgeschlagen. Der Server läuft bis $1 - aktualisiere deine Kartendaten oder verlängere einmalig.'],
        ['^(\d+)% mniej$', '$1% less', '$1% weniger'],
        ['^Poleciłeś: (\d+) · zdobyte dni: (\d+)$', 'Referred: $1 · days earned: $2', 'Eingeladen: $1 · erhaltene Tage: $2'],
    ];
}

/** Złotówki (np. "64,99") -> euro, zaokrąglone w górę do ,99. */
function i18n_eur(string $pln, string $lang): string
{
    $v = (float)str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $pln);
    $e = $v <= 0 ? 0.0 : max(0.99, ceil($v / I18N_KURS_EUR) - 0.01);
    return $lang === 'de' ? number_format($e, 2, ',', '.') : number_format($e, 2, '.', ',');
}

/** Tłumaczenie jednego tekstu (już po normalizacji białych znaków). */
function i18n_tekst(string $t, string $lang): ?string
{
    $s = i18n_slownik();
    $i = $lang === 'de' ? 1 : 0;
    if (isset($s[$t])) {
        return $s[$t][$i];
    }
    foreach (i18n_wzorce() as $w) {
        $re = '~' . $w[0] . '~u';
        if (preg_match($re, $t)) {
            $wynik = (string)preg_replace($re, $w[1 + $i], $t);
            // grupa może sama być tekstem do tłumaczenia ("rok" -> "year")
            return strtr($wynik, $lang === 'de'
                ? ['pro tydzień' => 'pro Woche', 'pro rok' => 'pro Jahr']
                : ['per tydzień' => 'per week', 'per rok' => 'per year']);
        }
    }
    return null;
}

/** Ceny w tekście: "6,99 zł" -> "1.99 €", "zł/mies." -> "€/mo". */
function i18n_ceny(string $t, string $lang): string
{
    $mies = $lang === 'de' ? '/Monat' : '/mo';
    $t = (string)preg_replace_callback(
        '~(\d{1,3}(?:[ \x{00A0}]?\d{3})*[.,]\d{2})(\s*)zł(/mies\.)?~u',
        static fn(array $m): string => i18n_eur($m[1], $lang) . $m[2] . '€' . (isset($m[3]) && $m[3] !== '' ? $mies : ''),
        $t
    );
    return $t;
}

/** Filtr całej strony HTML (wywołuje go ob_start). */
function i18n_filtr(string $html): string
{
    $lang = i18n_lang();
    if ($lang === 'pl' || stripos($html, '<html') === false) {
        return $html;
    }
    foreach (headers_list() as $h) {
        if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) {
            return $html;
        }
    }

    $czesci = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<!--.*?-->|<[^>]+>)~si', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($czesci === false) {
        return $html;
    }
    $kontekst = null;   // klucz z data-i18n="@..." dla najbliższego tekstu
    foreach ($czesci as $k => $c) {
        if ($c === '') {
            continue;
        }
        if ($c[0] === '<') {
            // Ten sam polski tekst w dwóch znaczeniach ("Akceptuję" w banerze
            // cookies i w zgodzie na regulamin) - element dostaje data-i18n
            // z własnym kluczem słownika i jego tekst tłumaczy się po kluczu.
            if (preg_match('~\bdata-i18n="(@[^"]+)"~', $c, $km)) {
                $kontekst = $km[1];
            }
            // atrybuty z tekstem dla ludzi
            if (!preg_match('~^<(script|style|!--)~i', $c)) {
                $czesci[$k] = (string)preg_replace_callback(
                    '~\b(title|aria-label|placeholder|alt|content)="([^"]*[A-Za-ząćęłńóśźż][^"]*)"~u',
                    static function (array $m) use ($lang): string {
                        $t = trim(preg_replace('~\s+~u', ' ', html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                        $p = i18n_tekst($t, $lang);
                        $p = i18n_ceny($p ?? $t, $lang);
                        return $p === $t ? $m[0] : $m[1] . '="' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '"';
                    },
                    $c
                );
            }
            continue;
        }
        if (trim($c) === '') {
            continue;
        }
        $t = trim(preg_replace('~\s+~u', ' ', html_entity_decode($c, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $p = $kontekst !== null ? i18n_tekst($kontekst, $lang) : i18n_tekst($t, $lang);
        $kontekst = null;
        $p = i18n_ceny($p ?? $t, $lang);
        if ($p !== $t) {
            preg_match('~^\s*~u', $c, $l);
            preg_match('~\s*$~u', $c, $r);
            $czesci[$k] = $l[0] . htmlspecialchars($p, ENT_NOQUOTES, 'UTF-8') . $r[0];
        }
    }
    $html = implode('', $czesci);

    $html = (string)preg_replace('~<html lang="pl"~', '<html lang="' . $lang . '"', $html, 1);

    // Słownik i wzorce dla tekstów z JS + przeliczanie cen rozbitych na
    // dwa elementy ("6,99" i osobno "zł"). Skrypt na końcu body, przed
    // pierwszym malowaniem, więc nie widać mignięcia polskich cen.
    $cfg = [
        'lang'    => $lang,
        'kurs'    => I18N_KURS_EUR,
        'slownik' => array_map(static fn(array $p): string => $p[$lang === 'de' ? 1 : 0], i18n_slownik()),
        'wzorce'  => array_map(static fn(array $w): array => [$w[0], $w[$lang === 'de' ? 2 : 1]], i18n_wzorce()),
    ];
    $js = '<script>window.DNCT_I18N=' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
        . '<script src="' . asset('/assets/js/i18n.js') . '"></script>';
    $poz = strripos($html, '</body>');
    return $poz === false ? $html . $js : substr($html, 0, $poz) . $js . substr($html, $poz);
}

/** Start bufora - tylko strony HTML w innym języku niż polski. */
function i18n_start(): void
{
    if (PHP_SAPI === 'cli' || i18n_lang() === 'pl') {
        return;
    }
    $skrypt = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($skrypt, '/api/') || str_starts_with($skrypt, '/auth/') || basename($skrypt) === 'pobierz.php') {
        return;
    }
    ob_start('i18n_filtr');
}

/** Link przełącznika języka: ten sam adres z ?lang=xx. */
function i18n_link(string $lang): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $sciezka = (string)parse_url($uri, PHP_URL_PATH);
    parse_str((string)parse_url($uri, PHP_URL_QUERY), $q);
    $q['lang'] = $lang;
    return $sciezka . '?' . http_build_query($q);
}
