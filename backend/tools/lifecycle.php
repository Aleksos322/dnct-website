<?php
/**
 * Cykl życia serwera po wygaśnięciu opłaty.
 *
 *     php backend/tools/lifecycle.php            # podgląd, niczego nie zmienia
 *     php backend/tools/lifecycle.php --apply    # wykonuje
 *     php backend/tools/lifecycle.php --apply --only=suspend
 *
 * Do crona, raz na godzinę, JAKO www-data (paczki muszą być czytelne dla
 * serwera WWW, który je potem wydaje):
 *
 *     15 * * * * www-data php /var/www/dnct.pl/public_html/WEB/beta.dnct.pl/backend/tools/lifecycle.php --apply >> /var/log/dnct-lifecycle.log 2>&1
 *
 * ---------------------------------------------------------------------------
 * Dwa scenariusze, jeden przebieg
 * ---------------------------------------------------------------------------
 * A. Płatność jednorazowa - klient sam pilnuje terminu, więc dostaje
 *    ostrzeżenia na 7 dni i na 24 h przed końcem. Po terminie: zawieszenie,
 *    a 24 h później usunięcie.
 *
 * B. Abonament - żadnych ostrzeżeń, bo płatność idzie sama i straszenie
 *    człowieka czymś, czego nie musi robić, to zwykły spam. Dopiero nieudane
 *    obciążenie uruchamia zegar: 24 h karencji (ustawia webhook), potem
 *    zawieszenie, a 48 h później usunięcie.
 *
 * ---------------------------------------------------------------------------
 * Czego ten proces NIE dotyka
 * ---------------------------------------------------------------------------
 * Chodzi WYŁĄCZNIE po tabeli `orders`. Serwery założone ręcznie, sprzed
 * systemu płatności, nie mają tam wiersza i dlatego są poza automatem - i tak
 * ma zostać. Nie ma dla nich żadnej daty, więc każda decyzja o ich kasowaniu
 * byłaby zgadywaniem.
 *
 * ---------------------------------------------------------------------------
 * Zabezpieczenia etapu kasowania
 * ---------------------------------------------------------------------------
 * 1. Kasuje tylko serwery, które TEN proces zawiesił (`suspended_at`).
 * 2. Tylko gdy paczka z plikami ma status `ready` i niezerowy rozmiar.
 * 3. Najpierw anuluje subskrypcję w Stripie - inaczej Stripe obciążałby kartę
 *    za serwer, którego już nie ma.
 * 4. Limit sztuk na przebieg: awaria dat nie skasuje całego hostingu w godzinę.
 * 5. Domyślnie podgląd; bez --apply nie dzieje się nic.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/provisioning.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../lib/server_archive.php';
require_once __DIR__ . '/../lib/lifecycle_mail.php';
require_once __DIR__ . '/../lib/stripe.php';

/** Ile serwerów najwyżej skasujemy w jednym przebiegu. */
const LIFECYCLE_DELETE_LIMIT = 5;

/**
 * Ile godzin czekamy na spakowanie paczki, zanim zawiesimy serwer mimo braku.
 * Bez tego limitu psująca się archiwizacja oznaczałaby, że nieopłacony serwer
 * działa w nieskończoność. Kasowanie i tak pozostaje zablokowane.
 */
const LIFECYCLE_ARCHIVE_WAIT_HOURS = 6;

$apply = in_array('--apply', $argv, true);
$only  = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, 7);
    }
}

$dnct = dnct_db();

printf("[%s] %s%s\n", date('Y-m-d H:i:s'), $apply ? 'WYKONUJĘ' : 'PODGLĄD (dodaj --apply)',
    $only !== null ? ' - tylko etap: ' . $only : '');

$stage = static fn(string $name): bool => $only === null || $only === $name;

/* `reconcile` idzie PRZED wszystkim innym - jeśli ktoś skasował serwer
   RĘCZNIE w Pterodactylu (z pominięciem tego skryptu), `orders.ptero_server_id`
   zostaje martwym wskaźnikiem, a status - dalej "provisioned". Bez tego etapu
   `warn` wysłałby ostrzeżenie o wygasającym serwerze, którego już nie ma,
   a `suspend`/`delete` próbowałyby zawiesić/skasować coś nieistniejącego
   w kółko, co godzinę, bez końca. */
if ($stage('reconcile')) { lifecycle_reconcile_deleted($dnct, $apply); }

/* KOLEJNOŚĆ MA ZNACZENIE. `archive` idzie PRZED `suspend`, bo zawieszony
   serwer nie pozwala zrobić backupu - panel odpowiada wtedy 409
   ServerStateConflictException. Paczka musi więc powstać, dopóki maszyna
   jeszcze działa, a zawieszenie następuje dopiero po niej. */
if ($stage('warn'))    { lifecycle_warn($dnct, $apply); }
if ($stage('archive')) { lifecycle_archive($dnct, $apply); }
if ($stage('suspend')) { lifecycle_suspend($dnct, $apply); }
if ($stage('delete'))  { lifecycle_delete($dnct, $apply); }
if ($stage('purge'))   { lifecycle_purge($dnct, $apply); }

echo "Gotowe.\n";

// ===========================================================================
// Etap 0: pojednanie - serwery skasowane RĘCZNIE, z pominięciem tego skryptu
// ===========================================================================

/**
 * Wyłapuje zamówienia "provisioned", których serwer nie istnieje już
 * w Pterodactylu (skasowany ręcznie w panelu, nie przez ten skrypt).
 *
 * CELOWO BEZ notify_user() - klient (albo admin, który to skasował) już wie,
 * że serwera nie ma; kolejny mail typu "Twój serwer został usunięty" byłby
 * myloną, spóźnioną wiadomością o czymś, co już się stało. `notify_admins()`
 * zostaje, bo to sygnał dla nas: ręczne skasowanie ominęło cykl życia
 * (subskrypcja mogła NIE zostać anulowana w Stripie - to różni się od
 * `lifecycle_delete()`, które zawsze anuluje najpierw).
 *
 * Ten sam efekt co `lifecycle_delete()` na końcu (status="cancelled",
 * deleted_at=NOW()) - żeby WSZYSTKIE dalsze etapy (warn/suspend/delete),
 * których warunki filtrują `deleted_at IS NULL`, przestały to zamówienie
 * w ogóle widzieć.
 */
function lifecycle_reconcile_deleted(PDO $dnct, bool $apply): void
{
    echo "\n-- pojednanie (serwery skasowane ręcznie) --\n";

    $rows = $dnct->query(
        'SELECT * FROM orders
          WHERE status = "provisioned"
            AND ptero_server_id IS NOT NULL
            AND deleted_at IS NULL
          ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "  nic do sprawdzenia\n";
        return;
    }

    $panel = panel_db();
    $found = 0;

    foreach ($rows as $o) {
        if (pterodactyl_server_exists($panel, (int)$o['ptero_server_id'])) {
            continue;
        }

        $found++;
        printf("  #%-4d serwer %-5d %-24s -> serwer nie istnieje, zamykam zamówienie po cichu\n",
            $o['id'], $o['ptero_server_id'], $o['user_name']);

        if (!$apply) {
            continue;
        }

        // Subskrypcja mogła zostać żywa - kto kasował serwer ręcznie w panelu
        // Pterodactyla, nie miał jak dotknąć Stripe'a. Bez tego klient płaciłby
        // dalej za coś, czego już nie ma.
        if (!lifecycle_cancel_subscription($o)) {
            echo "      UWAGA: nie udało się potwierdzić anulowania subskrypcji Stripe - sprawdź ręcznie\n";
        }

        $dnct->prepare('UPDATE orders SET deleted_at = NOW(), status = "cancelled" WHERE id = ?')
             ->execute([(int)$o['id']]);

        notify_admins($dnct, 'warning', 'Serwer zniknął z panelu poza cyklem życia',
            'Zamówienie #' . (int)$o['id'] . ' (' . (string)$o['user_email'] . '), serwer '
            . (int)$o['ptero_server_id'] . ' - ktoś skasował go ręcznie w Pterodactylu. '
            . 'Zamówienie oznaczone jako zakończone, klient NIE dostał o tym maila.');
    }

    if ($found === 0) {
        echo "  wszystkie istnieją\n";
    }
}

// ===========================================================================
// Etap 1: ostrzeżenia (tylko płatność jednorazowa)
// ===========================================================================

function lifecycle_warn(PDO $dnct, bool $apply): void
{
    echo "\n-- ostrzeżenia --\n";

    /* Dwa progi w jednym zapytaniu, bo warunek jest ten sam poza odstępem
       i kolumną znacznika. Znacznik decyduje o wysłaniu, nie sama data:
       inaczej cron chodzący co godzinę wysłałby to samo 24 razy dziennie. */
    $progi = [
        ['kolumna' => 'warn_7d_at',  'interval' => '7 DAY',   'final' => false, 'opis' => 'tydzień'],
        ['kolumna' => 'warn_24h_at', 'interval' => '24 HOUR', 'final' => true,  'opis' => '24 h'],
    ];

    foreach ($progi as $p) {
        $rows = $dnct->query(
            'SELECT * FROM orders
              WHERE status = "provisioned"
                AND billing_mode = "payment"
                AND ptero_server_id IS NOT NULL
                AND suspended_at IS NULL
                AND deleted_at IS NULL
                AND expires_at IS NOT NULL
                AND expires_at > NOW()
                AND expires_at <= DATE_ADD(NOW(), INTERVAL ' . $p['interval'] . ')
                AND ' . $p['kolumna'] . ' IS NULL
              ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $o) {
            printf("  #%-4d %-28s wygasa %s  -> ostrzeżenie (%s)\n",
                $o['id'], $o['user_name'], (string)$o['expires_at'], $p['opis']);

            if (!$apply) {
                continue;
            }

            /* Znacznik ustawiamy PRZED wysyłką. Gdyby poczta zawiesiła się na
               minutę i cron dostał drugie uruchomienie, klient dostałby dwa
               takie same ostrzeżenia - a to gorsze niż jedno niewysłane. */
            $dnct->prepare('UPDATE orders SET ' . $p['kolumna'] . ' = NOW() WHERE id = ?')
                 ->execute([(int)$o['id']]);

            notify_user(
                $dnct,
                (string)$o['user_name'],
                'warning',
                $p['final'] ? 'Serwer wygasa jutro' : 'Serwer wygasa za tydzień',
                'Opłacony okres kończy się ' . lifecycle_when((string)$o['expires_at'])
                . '. To płatność jednorazowa - serwer nie odnowi się sam.'
            );

            lifecycle_send(fn() => send_expiry_warning_mail($o, $p['final']), (int)$o['id'], 'ostrzeżenie');
        }
    }
}

// ===========================================================================
// Etap 2: zawieszenie
// ===========================================================================

function lifecycle_suspend(PDO $dnct, bool $apply): void
{
    echo "\n-- zawieszanie --\n";

    /* Termin: karencja, jeśli jest (nieudana płatność cykliczna), inaczej data
       wygaśnięcia. Ta sama reguła co w mailu potwierdzającym - klient dostał
       dokładnie tę datę i to jej pilnujemy. */
    $rows = $dnct->query(
        'SELECT * FROM orders
          WHERE status = "provisioned"
            AND ptero_server_id IS NOT NULL
            AND suspended_at IS NULL
            AND deleted_at IS NULL
            AND COALESCE(grace_until, expires_at) IS NOT NULL
            AND COALESCE(grace_until, expires_at) < NOW()
          ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "  nic\n";
        return;
    }

    foreach ($rows as $o) {
        $archive = archive_latest_for_order($dnct, (int)$o['id']);

        /* KROK 1: paczka, dopóki serwer jeszcze działa.
           Zawieszona maszyna odmawia backupu (409), więc kolejność jest
           odwrotna, niż podpowiada intuicja: najpierw pakujemy, potem gasimy.
           Serwer stoi wtedy najwyżej godzinę dłużej - a to znacznie tańsze
           niż skasowanie go bez kopii. */
        if ($archive === null) {
            printf("  #%-4d serwer %-5d %-24s termin minął %s -> zamawiam paczkę\n",
                $o['id'], $o['ptero_server_id'], $o['user_name'],
                (string)($o['grace_until'] ?? $o['expires_at']));

            if ($apply) {
                $res = archive_start($dnct, $o);
                echo $res['ok']
                    ? "      paczka zamówiona, zawieszenie po jej spakowaniu\n"
                    : "      BŁĄD paczki: " . $res['error'] . "\n";
            }
            continue;
        }

        /* KROK 2: czekamy na spakowanie, ale nie w nieskończoność. Gdyby
           archiwizacja psuła się w kółko, serwer po terminie działałby dalej
           za darmo. Po LIFECYCLE_ARCHIVE_WAIT_HOURS zawieszamy mimo wszystko -
           ale skasować i tak nie pozwoli brak gotowej paczki. */
        $waited = (time() - strtotime((string)$archive['created_at'])) / 3600;

        if ($archive['status'] === 'requested' && $waited < LIFECYCLE_ARCHIVE_WAIT_HOURS) {
            printf("  #%-4d paczka jeszcze się pakuje (%.1f h) - czekam\n", $o['id'], $waited);
            continue;
        }

        $deleteAt = lifecycle_delete_deadline($o, date('Y-m-d H:i:s'));

        printf("  #%-4d serwer %-5d %-24s paczka: %-9s -> ZAWIESZAM, usunięcie %s\n",
            $o['id'], $o['ptero_server_id'], $o['user_name'], (string)$archive['status'], $deleteAt);

        if (!$apply) {
            continue;
        }

        if (!provision_suspend((int)$o['ptero_server_id'])) {
            echo "      BŁĄD: panel nie zawiesił serwera - zostawiam na kolejny przebieg\n";
            continue;
        }

        $dnct->prepare('UPDATE orders SET suspended_at = NOW() WHERE id = ?')->execute([(int)$o['id']]);

        if ($archive['status'] !== 'ready') {
            echo "      UWAGA: zawieszony bez paczki - kasowanie się nie odbędzie\n";
            notify_admins($dnct, 'error', 'Zawieszenie bez paczki',
                'Zamówienie #' . (int)$o['id'] . ': archiwum ma status "' . (string)$archive['status']
                . '". Serwer NIE zostanie skasowany, dopóki paczki nie ma.');
            $archive = null;
        }

        notify_user(
            $dnct,
            (string)$o['user_name'],
            'error',
            'Serwer został zawieszony',
            'Opłacony okres minął. Dane są nietknięte - przedłużenie do '
            . lifecycle_when($deleteAt) . ' przywraca serwer w tym samym stanie.'
            . ($archive !== null ? ' Link do paczki z plikami wysłaliśmy na e-mail.' : '')
        );

        lifecycle_send(fn() => send_suspended_mail($o, $deleteAt, $archive), (int)$o['id'], 'zawieszenie');
    }
}

// ===========================================================================
// Etap 3: dokończenie paczek
// ===========================================================================

function lifecycle_archive(PDO $dnct, bool $apply): void
{
    echo "\n-- paczki z plikami --\n";

    $rows = $dnct->query(
        'SELECT * FROM server_archives WHERE status = "requested" ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "  nic\n";
        return;
    }

    foreach ($rows as $a) {
        printf("  archiwum #%-3d zamówienie #%-4d serwer %-5d\n",
            $a['id'], $a['order_id'], $a['ptero_server_id']);

        if (!$apply) {
            continue;
        }

        $res = archive_advance($dnct, $a);

        if ($res['status'] === 'requested') {
            echo "      jeszcze się pakuje" . ($res['error'] ? ' (' . $res['error'] . ')' : '') . "\n";
            continue;
        }

        if ($res['status'] === 'failed') {
            echo "      NIEUDANE: " . $res['error'] . "\n";
            notify_admins($dnct, 'error', 'Nieudana paczka serwera',
                'Zamówienie #' . (int)$a['order_id'] . ': ' . $res['error']
                . ' Serwer NIE zostanie skasowany, dopóki paczki nie ma.');
            continue;
        }

        /* Bez wiadomości do klienta - link do paczki niesie dopiero mail
           o zawieszeniu, żeby nie rozbijać jednej sprawy na dwie przesyłki
           przychodzące w losowej kolejności. */
        $fresh = $dnct->prepare('SELECT bytes FROM server_archives WHERE id = ?');
        $fresh->execute([(int)$a['id']]);

        printf("      gotowa: %s\n", archive_human_size((int)$fresh->fetchColumn()));
    }
}

// ===========================================================================
// Etap 4: usunięcie
// ===========================================================================

function lifecycle_delete(PDO $dnct, bool $apply): void
{
    echo "\n-- usuwanie --\n";

    $rows = $dnct->query(
        'SELECT * FROM orders
          WHERE ptero_server_id IS NOT NULL
            AND suspended_at IS NOT NULL
            AND deleted_at IS NULL
          ORDER BY id
          LIMIT 50'
    )->fetchAll(PDO::FETCH_ASSOC);

    $done = 0;

    foreach ($rows as $o) {
        $deadline = lifecycle_delete_deadline($o, (string)$o['suspended_at']);

        if (strtotime($deadline) > time()) {
            continue; // jeszcze ma czas na przedłużenie
        }

        $archive = archive_ready_for_order($dnct, (int)$o['id']);
        $path    = $archive !== null ? archive_path($archive) : null;

        /* WARUNEK BEZWZGLĘDNY. Bez paczki nie kasujemy - nigdy. Serwer stojący
           dzień dłużej nie kosztuje nikogo nic; skasowany bez kopii to dane
           klienta, których nikt już nie odzyska. */
        if ($archive === null || $path === null || !is_file($path) || filesize($path) <= 0) {
            printf("  #%-4d POMIJAM - brak gotowej paczki (termin minął %s)\n", $o['id'], $deadline);
            continue;
        }

        if ($done >= LIFECYCLE_DELETE_LIMIT) {
            printf("  #%-4d wstrzymane - limit %d na przebieg\n", $o['id'], LIFECYCLE_DELETE_LIMIT);
            continue;
        }

        printf("  #%-4d serwer %-5d %-24s termin usunięcia %s -> KASUJĘ\n",
            $o['id'], $o['ptero_server_id'], $o['user_name'], $deadline);

        if (!$apply) {
            $done++;
            continue;
        }

        /* Najpierw Stripe. Kolejność jest istotna: gdyby skasowanie serwera się
           udało, a anulowanie subskrypcji nie, klient płaciłby co miesiąc za
           nieistniejącą maszynę. Odwrotnie jest bezpiecznie - anulowana
           subskrypcja przy działającym serwerze to tylko utrata odnowienia. */
        if (!lifecycle_cancel_subscription($o)) {
            echo "      BŁĄD: nie udało się anulować subskrypcji - NIE kasuję serwera\n";
            notify_admins($dnct, 'error', 'Nie anulowano subskrypcji',
                'Zamówienie #' . (int)$o['id'] . ': subskrypcja Stripe nadal aktywna, serwer zostaje.');
            continue;
        }

        if (!provision_delete((int)$o['ptero_server_id'])) {
            echo "      BŁĄD: panel nie skasował serwera\n";
            notify_admins($dnct, 'error', 'Nie udało się skasować serwera',
                'Zamówienie #' . (int)$o['id'] . ', serwer ' . (int)$o['ptero_server_id'] . '.');
            continue;
        }

        $dnct->prepare('UPDATE orders SET deleted_at = NOW(), status = "cancelled" WHERE id = ?')
             ->execute([(int)$o['id']]);

        notify_user(
            $dnct,
            (string)$o['user_name'],
            'info',
            'Serwer został usunięty',
            'Zgodnie z podanym terminem serwer zniknął z panelu. Paczka z plikami jest do pobrania '
            . 'do ' . lifecycle_when((string)$archive['expires_at']) . ' - link masz na e-mailu.'
        );

        lifecycle_send(fn() => send_deleted_mail($o, $archive), (int)$o['id'], 'usunięcie');

        notify_admins($dnct, 'warning', 'Serwer skasowany automatem',
            'Zamówienie #' . (int)$o['id'] . ' (' . (string)$o['user_email'] . '), serwer '
            . (int)$o['ptero_server_id'] . '. Paczka: ' . archive_human_size((int)$archive['bytes']) . '.');

        echo "      skasowany\n";
        $done++;
    }

    if ($done === 0) {
        echo "  nic\n";
    }
}

// ===========================================================================
// Etap 5: sprzątanie paczek po terminie
// ===========================================================================

function lifecycle_purge(PDO $dnct, bool $apply): void
{
    echo "\n-- sprzątanie paczek --\n";

    if (!$apply) {
        $n = (int)$dnct->query(
            'SELECT COUNT(*) FROM server_archives WHERE status = "ready" AND expires_at < NOW()'
        )->fetchColumn();
        echo "  do skasowania: {$n}\n";
        return;
    }

    printf("  skasowano plików: %d\n", archive_purge_expired($dnct));
}

// ===========================================================================
// Pomocnicze
// ===========================================================================

/** Kiedy serwer ma zniknąć, licząc od podanej chwili zawieszenia. */
function lifecycle_delete_deadline(array $order, string $suspendedAt): string
{
    $hours = PROVISION_DELETE_AFTER_HOURS[(string)$order['billing_mode']] ?? 24;

    return (new DateTimeImmutable($suspendedAt))
        ->modify('+' . $hours . ' hours')
        ->format('Y-m-d H:i:s');
}

/**
 * Anuluje subskrypcję w Stripie. Zwraca true także wtedy, gdy nie ma czego
 * anulować - brak subskrypcji to stan docelowy, nie błąd.
 */
function lifecycle_cancel_subscription(array $order): bool
{
    $subId = trim((string)($order['stripe_subscription_id'] ?? ''));
    if ($subId === '') {
        return true;
    }

    try {
        stripe_init();
        $sub = \Stripe\Subscription::retrieve($subId);

        if (in_array($sub->status, ['canceled', 'incomplete_expired'], true)) {
            return true;
        }

        $sub->cancel();
        return true;
    } catch (Throwable $e) {
        error_log('Cykl życia: nie udalo sie anulowac subskrypcji ' . $subId . ': ' . $e->getMessage());
        return false;
    }
}

/** Wysyłka, która nie może wywrócić całego przebiegu. */
function lifecycle_send(callable $send, int $orderId, string $co): void
{
    try {
        $res = $send();
        if (empty($res['ok'])) {
            echo "      poczta ({$co}) nieudana: " . ($res['error'] ?? 'nieznany błąd') . "\n";
            error_log('Cykl życia: nieudana wysyłka (' . $co . ') dla zamówienia #' . $orderId);
        }
    } catch (Throwable $e) {
        echo "      poczta ({$co}) wyjątek: " . $e->getMessage() . "\n";
        error_log('Cykl życia: wyjatek przy wysylce (' . $co . ') #' . $orderId . ': ' . $e->getMessage());
    }
}
