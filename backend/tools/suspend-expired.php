<?php
/**
 * Zawieszanie serwerów po terminie.
 *
 *     php backend/tools/suspend-expired.php            # podgląd
 *     php backend/tools/suspend-expired.php --apply    # zawiesza
 *
 * PO CO. Potwierdzenie zamówienia podaje klientowi DOKŁADNĄ datę zawieszenia.
 * Bez tego procesu ta data byłaby obietnicą, której nic nie pilnuje - serwery
 * działałyby w nieskończoność, a wiadomość mówiłaby nieprawdę. To gorsze niż
 * brak terminu, bo uczy klientów, że nasze terminy nic nie znaczą.
 *
 * ZAWIESZAMY, NIE KASUJEMY. Świat, pliki i konfiguracja zostają - klient może
 * wrócić i dopłacić. Kasowanie serwera automatem jest nieodwracalne i nie
 * powinno dziać się bez decyzji człowieka.
 *
 * KARENCJA. Przy abonamencie liczy się `grace_until` (ustawiane po nieudanym
 * obciążeniu), a nie samo `expires_at`: Stripe ponawia płatność przez kilka
 * dni i przez ten czas serwer ma działać.
 *
 * PACZKA PRZED ZAWIESZENIEM - TAK SAMO JAK W lifecycle.php, CELOWO ZDUBLOWANE.
 * Ten skrypt to węższy, awaryjny odpowiednik etapu `suspend` z lifecycle.php
 * (patrz CLAUDE.md) - ma działać SAMODZIELNIE, bez zależności od stanu innego
 * procesu, więc powiela tu tę samą logikę zamiast ją importować (lifecycle.php
 * to wykonywalny skrypt, nie biblioteka - require'owanie go stąd odpaliłoby
 * cały cykl życia).
 *
 * WCZEŚNIEJSZA WERSJA zawieszała od razu, bez paczki, i nigdy nie ustawiała
 * `suspended_at`. Skutek: serwer zawieszony TĄ drogą był niewidoczny dla
 * `suspended_at IS NOT NULL` w lifecycle_delete() (czyli nigdy nie mógł zostać
 * skasowany, nawet z gotową paczką), a jednocześnie kolejny przebieg
 * lifecycle_suspend() wciąż go widział (bo u niego `suspended_at IS NULL`
 * jest warunkiem wejścia) i próbował zamówić na nim paczkę - na serwerze,
 * który już odmawia backupu, bo jest zawieszony. 409 w nieskończoność,
 * zamówienie na zawsze zawieszone, bez kopii, bez możliwości skasowania.
 *
 * ZMIANA TUTAJ WYMAGA TEJ SAMEJ ZMIANY W lifecycle_suspend() (lifecycle.php)
 * - i odwrotnie. Dwie kopie tego samego wzorca rozjadą się przy pierwszej
 * poprawce w jednej z nich, jeśli nikt nie pamięta o drugiej.
 *
 * Do crona, raz na godzinę:
 *     0 * * * * php /var/www/dnct.pl/public_html/WEB/beta.dnct.pl/backend/tools/suspend-expired.php --apply
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

/** Tak samo jak LIFECYCLE_ARCHIVE_WAIT_HOURS w lifecycle.php - musi się z nią zgadzać. */
const SUSPEND_EXPIRED_ARCHIVE_WAIT_HOURS = 6;

$apply = in_array('--apply', $argv, true);
$dnct  = dnct_db();

echo $apply ? "TRYB: zawieszanie\n\n" : "TRYB: podgląd (dodaj --apply)\n\n";

/* Termin, po którym serwer ma zniknąć z sieci:
   - jest karencja  -> liczy się karencja (nieudana płatność cykliczna),
   - nie ma         -> liczy się data wygaśnięcia.
   Bierzemy tylko zamówienia, które MAJĄ serwer, nie są już zawieszone i nie
   są zamknięte - bez `suspended_at IS NULL` ten skrypt próbowałby zawiesić
   (i wysłać drugi mail o zawieszeniu) serwer, który zawiesił już normalny
   lifecycle.php. */
$rows = $dnct->query(
    'SELECT * FROM orders
      WHERE ptero_server_id IS NOT NULL
        AND status = "provisioned"
        AND suspended_at IS NULL
        AND deleted_at IS NULL
        AND COALESCE(grace_until, expires_at) IS NOT NULL
        AND COALESCE(grace_until, expires_at) < NOW()
      ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "Nic do zawieszenia.\n";
    exit(0);
}

$done = $failed = 0;

foreach ($rows as $o) {
    $deadline = $o['grace_until'] ?? $o['expires_at'];

    /* KROK 1: paczka, dopóki serwer jeszcze działa - zawieszona maszyna
       odmawia backupu (409). Ten sam powód, ta sama kolejność co
       w lifecycle_suspend(). */
    $archive = archive_latest_for_order($dnct, (int)$o['id']);

    if ($archive === null) {
        printf(
            "  zamówienie #%-4d serwer %-5d %-22s termin minął %s -> zamawiam paczkę\n",
            $o['id'],
            $o['ptero_server_id'],
            $o['user_name'],
            (string)$deadline
        );

        if ($apply) {
            $res = archive_start($dnct, $o);
            echo $res['ok']
                ? "      paczka zamówiona, zawieszenie po jej spakowaniu\n"
                : "      BŁĄD paczki: " . $res['error'] . "\n";
        }
        continue;
    }

    /* KROK 2: czekamy na spakowanie, ale nie w nieskończoność - tak samo jak
       w lifecycle_suspend(). Po limicie zawieszamy mimo wszystko; skasować
       i tak nie pozwoli brak gotowej paczki (lifecycle_delete()). */
    $waited = (time() - strtotime((string)$archive['created_at'])) / 3600;

    if ($archive['status'] === 'requested' && $waited < SUSPEND_EXPIRED_ARCHIVE_WAIT_HOURS) {
        printf("  zamówienie #%-4d paczka jeszcze się pakuje (%.1f h) - czekam\n", $o['id'], $waited);
        continue;
    }

    printf(
        "  zamówienie #%-4d serwer %-5d %-22s termin minął %s\n",
        $o['id'],
        $o['ptero_server_id'],
        $o['user_name'],
        (string)$deadline
    );

    if (!$apply) {
        continue;
    }

    if (!provision_suspend((int)$o['ptero_server_id'])) {
        echo "      -> BŁĄD: Pterodactyl nie zawiesił serwera\n";
        $failed++;
        continue;
    }

    /* Zamówienie zostaje w stanie `provisioned`, a nie `cancelled`: usługa
       nadal istnieje, tylko jest wstrzymana. Ponowne uruchomienie robi
       przedłużenie (webhook zdejmuje zawieszenie). Stan wynika z dat, nie
       z osobnej flagi, której ktoś kiedyś zapomni ustawić.
       `suspended_at` TU JEST WARUNKIEM KONIECZNYM: to jedyny sygnał, po
       którym lifecycle_delete() w ogóle rozważa skasowanie tego zamówienia
       (patrz komentarz na górze pliku). */
    $dnct->prepare('UPDATE orders SET suspended_at = NOW() WHERE id = ?')->execute([(int)$o['id']]);

    if ($archive['status'] !== 'ready') {
        echo "      UWAGA: zawieszony bez gotowej paczki - kasowanie się nie odbędzie, dopóki jej nie ma\n";
        notify_admins($dnct, 'error', 'Zawieszenie bez paczki',
            'Zamówienie #' . (int)$o['id'] . ': archiwum ma status "' . (string)$archive['status']
            . '". Serwer NIE zostanie skasowany, dopóki paczki nie ma.');
    }

    notify_user(
        $dnct,
        (string)$o['user_name'],
        'error',
        'Serwer został zawieszony',
        'Minął opłacony okres. Dane są nietknięte - po przedłużeniu serwer wraca w tym samym stanie.'
    );

    echo "      -> zawieszony\n";
    $done++;
}

printf("\nPodsumowanie: %d zawieszonych, %d z błędem\n", $done, $failed);
