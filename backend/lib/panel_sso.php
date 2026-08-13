<?php
/**
 * backend/lib/panel_sso.php
 *
 * Jednorazowy bilet wstępu do panelu Pterodactyla, Z POMINIĘCIEM Authentika.
 *
 * Panel wystawia własny endpoint `/auth/sso?token=...`, który wymienia wpis
 * z tabeli `sso_tokens` na sesję konkretnego konta (dopasowanie po adresie
 * e-mail). Bilet żyje 2 minuty i jest jednorazowy - kontroler oznacza go jako
 * zużyty, ZANIM kogokolwiek zaloguje, więc podwójne kliknięcie w link nic nie
 * daje.
 *
 * ---------------------------------------------------------------------------
 * Po co osobna droga, skoro klienci wchodzą do panelu przez Authentika?
 * ---------------------------------------------------------------------------
 * Bo konto demo NIE MOŻE dostać sesji Authentika.
 *
 * Sesja SSO jest wspólna dla całej przeglądarki i dla wszystkich aplikacji
 * podpiętych do Authentika. Gdyby demo logowało się normalnie, w przeglądarce
 * zostawałoby ciasteczko mówiące "ten człowiek to demo", a wtedy:
 *
 *   - kliknięcie "Zaloguj" wpuszczałoby z powrotem na demo, bez pytania
 *     o cokolwiek - Authentik nie ma powodu pytać, skoro sesja jest ważna;
 *   - dnct.pl też uważałoby odwiedzającego za zalogowanego, więc "Twoje
 *     serwery" i - co gorsza - zamówienie trafiałyby na konto demo.
 *
 * Bilet zostawia w przeglądarce wyłącznie ciasteczko panelu. Ani Authentik,
 * ani dnct.pl nie dowiadują się o niczym, więc logowanie na własne konto
 * działa potem dokładnie tak, jakby demo nigdy nie było otwarte.
 *
 * BEZPIECZEŃSTWO: adres konta bierzemy z konfiguracji (DEMO_PANEL_EMAIL), nigdy
 * z żądania. Gdyby e-mail dało się podać w adresie, ten plik byłby gotowym
 * logowaniem na dowolne konto w panelu.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

/** Tyle samo, ile odlicza kontroler w panelu (120 s od created_at). */
const PANEL_SSO_TTL_SECONDS = 120;

/**
 * Wystawia bilet i zwraca pełny adres, pod który należy odesłać przeglądarkę.
 *
 * @throws RuntimeException gdy nie wiadomo, dla kogo bilet wystawić.
 */
function panel_sso_ticket(string $email, string $username = ''): string
{
    $email = trim($email);
    if ($email === '') {
        throw new RuntimeException('Brak adresu konta, dla którego mam wystawić bilet do panelu.');
    }

    $base  = rtrim((string)env('PANEL_SSO_TOKEN_URL', 'https://panel.dnct.pl/auth/sso'), '/');
    $token = bin2hex(random_bytes(32));

    $pdo = panel_db();

    /* Przy okazji sprzątamy stare wpisy. Panel kasuje tylko te, na które sam
       się natknie przy wygaśnięciu, więc bez tego tabela rośnie w nieskończoność
       o bilety, których nikt nigdy nie kliknął. Godzina zamiast dwóch minut,
       żeby przypadkiem nie usunąć czegoś, co właśnie jest w drodze. */
    $pdo->exec('DELETE FROM sso_tokens WHERE created_at < (NOW() - INTERVAL 1 HOUR)');

    $stmt = $pdo->prepare(
        'INSERT INTO sso_tokens (token, email, username, expires_at, created_at, updated_at)
         VALUES (?, ?, ?, (NOW() + INTERVAL ' . PANEL_SSO_TTL_SECONDS . ' SECOND), NOW(), NOW())'
    );
    $stmt->execute([$token, $email, $username !== '' ? $username : null]);

    return $base . '?token=' . rawurlencode($token);
}

/**
 * Adres konta poglądowego albo pusty łańcuch, gdy demo nie jest skonfigurowane.
 * Pusty wynik jest normalnym stanem - wtedy po prostu nie pokazujemy przycisku.
 */
function panel_demo_email(): string
{
    return trim((string)env('DEMO_PANEL_EMAIL', ''));
}
