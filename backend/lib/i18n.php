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
        ['^Wszystkie progi i ceny - (.+)$', 'All tiers and prices - $1', 'Alle Stufen und Preise - $1'],
        // Poradniki (components/poradnik.php, poradniki/index.php)
        ['^Aktualizacja: ([\d.]+) · (.+)$', 'Updated: $1 · $2', 'Aktualisiert: $1 · $2'],
        ['^Hosting (Minecraft|Project Zomboid|Vintage Story) od (.+)/mies\.$', '$1 hosting from $2/mies.', '$1-Hosting ab $2/mies.'],
        // Status (status.php)
        ['^Odczyt: (.+) · odśwież stronę, aby wykonać nowy test\.$', 'Checked: $1 · refresh the page to run a new check.', 'Geprüft: $1 · lade die Seite neu für eine neue Prüfung.'],
        ['^Odczytano konfigurację (\d+) węzła/węzłów\.$', 'Read the configuration of $1 node(s).', 'Konfiguration von $1 Node(s) gelesen.'],
        // Dobierz serwer (assets/js/wybierz-serwer.js) - kolejność ma znaczenie, pierwszy pasujący wygrywa
        ['^(\d+) (?:osoba|osoby|osób)$', '$1 players', '$1 Spieler'],
        ['^Dla (\d+) (?:osoba|osoby|osób) i trybu mody / modpack celujemy w co najmniej (\d+) GB RAM\.$', 'For $1 players with mods / a modpack we aim for at least $2 GB RAM.', 'Für $1 Spieler mit Mods / Modpack planen wir mindestens $2 GB RAM ein.'],
        ['^Dla (\d+) (?:osoba|osoby|osób) i trybu pluginy celujemy w co najmniej (\d+) GB RAM\.$', 'For $1 players with plugins we aim for at least $2 GB RAM.', 'Für $1 Spieler mit Plugins planen wir mindestens $2 GB RAM ein.'],
        ['^Dla (\d+) (?:osoba|osoby|osób) i trybu vanilla celujemy w co najmniej (\d+) GB RAM\.$', 'For $1 players on vanilla we aim for at least $2 GB RAM.', 'Für $1 Spieler mit Vanilla planen wir mindestens $2 GB RAM ein.'],
        ['^Wybraliśmy pierwszy dostępny próg, który mieści (\d+) (?:osoba|osoby|osób) jednocześnie\.$', 'We picked the first available tier that fits $1 players at once.', 'Wir haben die erste verfügbare Stufe gewählt, die $1 Spieler gleichzeitig fasst.'],
        ['^(\d+) slotów · (.+) SSD · od (.+) / mies\.$', '$1 slots · $2 SSD · from $3 / mo', '$1 Slots · $2 SSD · ab $3 / Monat'],
        ['^(.+) SSD · od (.+) / mies\.$', '$1 SSD · from $2 / mo', '$1 SSD · ab $2 / Monat'],
        // Komunikaty API sklejane z liczbami/nazwami (http.php json_tlumacz)
        ['^Kolejny kod możesz wysłać za (\d+) s\.$', 'You can send another code in $1 s.', 'Einen neuen Code kannst du in $1 s senden.'],
        ['^Kod musi składać się z (\d+) cyfr\.$', 'The code must be $1 digits.', 'Der Code muss aus $1 Ziffern bestehen.'],
        ['^Nieprawidłowy kod\. Pozostałe próby: (\d+)\.$', 'Wrong code. Attempts left: $1.', 'Falscher Code. Verbleibende Versuche: $1.'],
        ['^Nazwa użytkownika musi mieć od (\d+) do (\d+) znaków\.$', 'The username must be $1 to $2 characters.', 'Der Benutzername muss $1 bis $2 Zeichen lang sein.'],
        ['^Hasło może mieć maksymalnie (\d+) znaków\.$', 'The password can be at most $1 characters.', 'Das Passwort darf höchstens $1 Zeichen haben.'],
        ['^Maksymalna długość to (\d+) znaków\.$', 'The maximum length is $1 characters.', 'Die maximale Länge beträgt $1 Zeichen.'],
        ['^Czy chodziło o @(\S+)\? Domena @(\S+) nie przyjmuje poczty\.$', 'Did you mean @$1? The domain @$2 doesn\'t accept email.', 'Meintest du @$1? Die Domain @$2 nimmt keine E-Mails an.'],
        ['^Domena @(\S+) nie przyjmuje poczty\. Sprawdź, czy nie ma literówki\.$', 'The domain @$1 doesn\'t accept email. Check for a typo.', 'Die Domain @$1 nimmt keine E-Mails an. Prüf auf Tippfehler.'],
        ['^Pakiet zmieniony na (.+)\. Niewykorzystana różnica wróciła jako (\d+) dni\.$', 'Plan changed to $1. The unused difference came back as $2 days.', 'Paket geändert auf $1. Die ungenutzte Differenz kam als $2 Tage zurück.'],
        ['^Pakiet zmieniony na (.+)\.$', 'Plan changed to $1.', 'Paket geändert auf $1.'],
        ['^Masz już pakiet (.+)\. Przedłuż go albo napisz do nas, jeśli chcesz zmienić pakiet\.$', 'You already have the $1 plan. Renew it or write to us if you want to change plans.', 'Du hast schon das Paket $1. Verlängere es oder schreib uns, wenn du wechseln willst.'],
        ['^(.+) Numer sprawy: (.+)\.$', '$1 Case number: $2.', '$1 Fallnummer: $2.'],
        // Rejestracja (auth/register/register.js)
        ['^Postęp rejestracji: krok (\d) z 3$', 'Sign-up progress: step $1 of 3', 'Registrierungsfortschritt: Schritt $1 von 3'],
        ['^Wpisz pełny (\d+)-cyfrowy kod\.$', 'Enter the full $1-digit code.', 'Gib den vollständigen $1-stelligen Code ein.'],
        ['^Wyślij ponownie za (\d+) s$', 'Resend in $1 s', 'Erneut senden in $1 s'],
        ['^Kod wygaśnie za (\d+:\d\d)$', 'The code expires in $1', 'Der Code läuft ab in $1'],
        ['^Złamanie: ([\d\s.,]+) (?:sekunda|sekundy|sekund)$', 'Time to crack: $1 s', 'Zeit zum Knacken: $1 s'],
        ['^Złamanie: ([\d\s.,]+) (?:minuta|minuty|minut)$', 'Time to crack: $1 min', 'Zeit zum Knacken: $1 min'],
        ['^Złamanie: ([\d\s.,]+) (?:godzina|godziny|godzin)$', 'Time to crack: $1 h', 'Zeit zum Knacken: $1 Std.'],
        ['^Złamanie: ([\d\s.,]+) (?:dzień|dni)$', 'Time to crack: $1 days', 'Zeit zum Knacken: $1 Tage'],
        ['^Złamanie: ([\d\s.,]+) (?:miesiąc|miesiące|miesięcy)$', 'Time to crack: $1 months', 'Zeit zum Knacken: $1 Monate'],
        ['^Złamanie: ([\d\s.,]+) (?:rok|lata|lat)$', 'Time to crack: $1 years', 'Zeit zum Knacken: $1 Jahre'],
        ['^Złamanie: ([\d\s.,]+) tys\. lat$', 'Time to crack: $1 thousand years', 'Zeit zum Knacken: $1 Tausend Jahre'],
        ['^Złamanie: ([\d\s.,]+) mln lat$', 'Time to crack: $1 million years', 'Zeit zum Knacken: $1 Millionen Jahre'],
        ['^Złamanie: ([\d\s.,]+) mld lat$', 'Time to crack: $1 billion years', 'Zeit zum Knacken: $1 Milliarden Jahre'],
        ['^Złamanie: ([\d\s.,]+) bln lat$', 'Time to crack: $1 trillion years', 'Zeit zum Knacken: $1 Billionen Jahre'],
        // Historia opłat (oplaty.php, oplaty_okres)
        ['^(\d+) lata$', '$1 years', '$1 Jahre'],
        ['^(\d+) miesiące$', '$1 months', '$1 Monate'],
        ['^(\d+) tygodnie$', '$1 weeks', '$1 Wochen'],
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
                    '~\b(title|aria-label|placeholder|alt|content|data-label)="([^"]*[A-Za-ząćęłńóśźż][^"]*)"~u',
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
    // /auth/ to przekierowania (logowanie, callback), z wyjątkiem kreatora
    // rejestracji - to zwykła strona HTML, której nowy klient musi rozumieć.
    $authStrona = str_starts_with($skrypt, '/auth/') && $skrypt !== '/auth/register/register.php';
    if (str_starts_with($skrypt, '/api/') || $authStrona || basename($skrypt) === 'pobierz.php') {
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

/**
 * Dokumenty prawne w EN/DE (regulamin, polityka prywatności, umowa
 * powierzenia): pełne tłumaczenie wnętrza bloku .legal leży w
 * backend/data/prawne-i18n/<slug>.php - jak poradniki, całe akapity, nie
 * słownik. Wiąże wersja polska; tłumaczenie dostaje na górze dopisek, że
 * jest informacyjne. Brak pliku albo języka = dokument zostaje po polsku.
 */
function i18n_dokument(string $slug, string $html, ?string &$tytul = null, ?string &$opis = null): string
{
    $lang = i18n_lang();
    $plik = dirname(__DIR__) . '/data/prawne-i18n/' . basename($slug) . '.php';
    if ($lang === 'pl' || !is_file($plik)) {
        return $html;
    }
    $tl = (require $plik)[$lang] ?? null;
    if (!is_array($tl) || empty($tl['tresc'])
        || !preg_match('~^(.*?<div class="legal[^"]*"[^>]*>)(.*)(</div>\s*)$~s', $html, $m)) {
        return $html;
    }
    $tytul = $tl['title'] ?? $tytul;
    $opis  = $tl['opis'] ?? $opis;
    $dopisek = $lang === 'de'
        ? 'Diese Übersetzung dient nur zur Information. Rechtlich verbindlich ist ausschließlich die <a href="?lang=pl">polnische Fassung</a>.'
        : 'This translation is provided for information only. Only the <a href="?lang=pl">Polish version</a> is legally binding.';
    return $m[1] . "\n    <p class=\"legal-tl-note\">" . $dopisek . "</p>\n" . $tl['tresc'] . $m[3];
}
