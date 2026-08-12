<?php
/**
 * Zawieszanie serwerów po terminie.
 *
 *     php backend/tools/suspend-expired.php            # podgląd
 *     php backend/tools/suspend-expired.php --apply    # zawiesza
 *
 * PO CO. Potwierdzenie zamówienia podaje klientowi DOKŁADNĄ datę zawieszenia.
 * Bez tego procesu ta data byłaby obietnicą, której nic nie pilnuje — serwery
 * działałyby w nieskończoność, a wiadomość mówiłaby nieprawdę. To gorsze niż
 * brak terminu, bo uczy klientów, że nasze terminy nic nie znaczą.
 *
 * ZAWIESZAMY, NIE KASUJEMY. Świat, pliki i konfiguracja zostają — klient może
 * wrócić i dopłacić. Kasowanie serwera automatem jest nieodwracalne i nie
 * powinno dziać się bez decyzji człowieka.
 *
 * KARENCJA. Przy abonamencie liczy się `grace_until` (ustawiane po nieudanym
 * obciążeniu), a nie samo `expires_at`: Stripe ponawia płatność przez kilka
 * dni i przez ten czas serwer ma działać.
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

$apply = in_array('--apply', $argv, true);
$dnct  = dnct_db();

echo $apply ? "TRYB: zawieszanie\n\n" : "TRYB: podgląd (dodaj --apply)\n\n";

/* Termin, po którym serwer ma zniknąć z sieci:
   - jest karencja  -> liczy się karencja (nieudana płatność cykliczna),
   - nie ma         -> liczy się data wygaśnięcia.
   Bierzemy tylko zamówienia, które MAJĄ serwer i nie są już zawieszone. */
$rows = $dnct->query(
    'SELECT * FROM orders
      WHERE ptero_server_id IS NOT NULL
        AND status = "provisioned"
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
       z osobnej flagi, której ktoś kiedyś zapomni ustawić. */
    notify_user(
        $dnct,
        (string)$o['user_name'],
        'error',
        'Serwer został zawieszony',
        'Minął opłacony okres. Dane są nietknięte — po przedłużeniu serwer wraca w tym samym stanie.'
    );

    echo "      -> zawieszony\n";
    $done++;
}

printf("\nPodsumowanie: %d zawieszonych, %d z błędem\n", $done, $failed);
