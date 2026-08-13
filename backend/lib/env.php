<?php
/**
 * Wczytywanie konfiguracji z pliku .env.
 *
 * Plik .env leży POZA DocumentRootem, dzięki czemu nie da się go pobrać przez
 * HTTP nawet przy błędnej konfiguracji Apache'a. Jego docelowe miejsce to
 * /var/www/dnct.pl/public_html/.env - jeden plik dla całego serwisu, wspólny
 * także ze starym `beta/` (tamten czyta go przez BASE_PATH w config.php).
 *
 * Kandydaci są liczone od tego pliku w GÓRĘ, więc zależą od tego, jak głęboko
 * siedzi katalog strony. Stąd dwie różne głębokości:
 *
 *   public_html/WEB/-dnct.pl/backend/lib/env.php  -> 4 poziomy do public_html
 *   public_html/private/backend/lib/env.php       -> 3 poziomy do public_html
 *
 * Obie są tu wymienione celowo: dzięki temu ten sam kod działa i przed
 * przeprowadzką do WEB/, i po niej, a przenoszenie katalogu nie wymaga
 * jednoczesnej zmiany w PHP. Bez tej pierwszej pozycji strona po przeprowadzce
 * szukałaby .env w public_html/WEB/ - nie znalazłaby go i wstałaby bez ŻADNEJ
 * konfiguracji: bez bazy, bez Authentika, bez Pterodactyla.
 */

declare(strict_types=1);

/**
 * Ścieżki sprawdzane po kolei - pierwsza istniejąca wygrywa.
 *
 * PIERWSZEŃSTWO ma jawne wskazanie z vhosta:
 *
 *     SetEnv DNCT_ENV_FILE /var/www/dnct.pl/config/beta.env
 *
 * Bez tego środowisko testowe czytałoby produkcyjny .env, bo oba katalogi
 * (WEB/-dnct.pl i WEB/beta.dnct.pl) leżą tak samo głęboko i wypadają na tę samą
 * ścieżkę. Skutek byłby taki, że "zabawa na becie" pisałaby do produkcyjnej bazy
 * i strzelała prawdziwymi kluczami Stripe - czyli dokładnie to, przed czym
 * osobne środowisko ma chronić.
 */
function env_candidate_paths(): array
{
    $explicit = $_SERVER['DNCT_ENV_FILE'] ?? getenv('DNCT_ENV_FILE') ?: null;

    return [
        ...($explicit ? [(string)$explicit] : []),
        dirname(__DIR__, 4) . '/.env', // public_html/.env  (układ WEB/-dnct.pl - docelowy)
        dirname(__DIR__, 3) . '/.env', // public_html/.env  (układ private/  - przed przeprowadzką)
        dirname(__DIR__, 2) . '/.env', // .env w katalogu strony (zaszłość - awaryjnie)
    ];
}

/**
 * Parsuje .env do tablicy. Wynik jest pamiętany w statycznej zmiennej,
 * więc plik czytamy raz na żądanie HTTP.
 *
 * @return array<string,string>
 */
function env_all(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }

    $values = [];

    /* WSKAZANY PLIK, KTÓREGO NIE DA SIĘ ODCZYTAĆ, TO AWARIA - NIE POWÓD DO
       CICHEGO SIĘGNIĘCIA PO NASTĘPNY.

       Kosztowało to już raz: `beta.env` stracił grupę `www-data` po edycji,
       PHP-FPM przestał go czytać i beta po cichu zjechała na produkcyjny
       `.env` - czyli na PRAWDZIWE klucze Stripe. Nic tego nie zgłosiło,
       bo pętla niżej po prostu brała kolejny czytelny plik z listy.

       Ścieżki domyślne mogą nie istnieć i to normalne. Ale jeśli ktoś podał
       DNCT_ENV_FILE, to znaczy, że chce DOKŁADNIE tego środowiska. */
    $explicit = $_SERVER['DNCT_ENV_FILE'] ?? getenv('DNCT_ENV_FILE') ?: null;
    if ($explicit !== null && !is_readable((string)$explicit)) {
        error_log(
            'env: KRYTYCZNE - wskazany plik ' . $explicit . ' jest nieczytelny dla '
            . (function_exists('posix_geteuid') ? 'uid ' . posix_geteuid() : 'tego procesu')
            . '. Sięgam po plik domyślny, co oznacza CUDZE ustawienia (klucze płatności, adresy baz). '
            . 'Napraw uprawnienia: chown deployer:www-data ' . $explicit
        );
    }

    $path = null;
    foreach (env_candidate_paths() as $candidate) {
        if (is_readable($candidate)) {
            $path = $candidate;
            break;
        }
    }

    if ($path === null) {
        error_log('env: nie znaleziono pliku .env w żadnej ze ścieżek: ' . implode(', ', env_candidate_paths()));
        return $values;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log('env: nie udało się odczytać ' . $path);
        return $values;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Zdejmujemy cudzysłowy, jeśli wartość jest w nie ujęta.
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key !== '') {
            $values[$key] = $value;
        }
    }

    return $values;
}

/** Pojedyncza wartość z .env (z fallbackiem na zmienne środowiskowe procesu). */
function env(string $key, ?string $default = null): ?string
{
    $values = env_all();
    if (array_key_exists($key, $values) && $values[$key] !== '') {
        return $values[$key];
    }

    $fromProcess = getenv($key);
    if (is_string($fromProcess) && $fromProcess !== '') {
        return $fromProcess;
    }

    return $default;
}

/** Wartość wymagana - brak oznacza błąd konfiguracji, nie cichą awarię. */
function env_required(string $key): string
{
    $value = env($key);
    if ($value === null || $value === '') {
        throw new RuntimeException('Brak wymaganej zmiennej konfiguracyjnej: ' . $key);
    }
    return $value;
}

function env_int(string $key, int $default): int
{
    $value = env($key);
    return $value === null || !is_numeric($value) ? $default : (int)$value;
}
