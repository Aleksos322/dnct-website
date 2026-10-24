<?php
/**
 * Jedno konto DNCT -> konta we wszystkich usługach.
 *
 * Jedno miejsce, które wie, jakie usługi dostają konto przy rejestracji.
 * Każda usługa to jedno zadanie w provision_jobs (migracja 010):
 *  - rejestracja wpisuje zadania i od razu je wykonuje,
 *  - nieudane zostają "pending" i cykl życia (etap `konta`) ponawia je
 *    z rosnącym odstępem, a po ACCOUNT_JOB_MAX_PROB prób woła admina.
 *
 * Nowa usługa = nowa wartość w ENUM `service` + gałąź w account_job_run().
 */

declare(strict_types=1);

require_once __DIR__ . '/user_profiles.php';
require_once __DIR__ . '/jellyfin.php';
require_once __DIR__ . '/jellyfin_mail.php';
require_once __DIR__ . '/notifications.php';

// Jellyfin celowo wyłączony z rejestracji (2026-09-29, decyzja: zajmiemy się nim osobno).
// Gałąź account_job_jellyfin() zostaje - wystarczy dopisać 'jellyfin' z powrotem.
const ACCOUNT_SERVICES = ['pterodactyl', 'hosting'];
const ACCOUNT_JOB_MAX_PROB = 10;

/**
 * Zakłada konta we wszystkich usługach dla świeżo utworzonego konta DNCT.
 * @param array{sub:string,username:string,email:string,first_name:string,last_name:string} $k
 * @return array<string,bool> usługa => czy konto już jest
 */
function account_provision_all(PDO $pdo, array $k): array
{
    $wstaw = $pdo->prepare(
        'INSERT IGNORE INTO provision_jobs (authentik_sub, username, email, first_name, last_name, service, next_try_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    foreach (ACCOUNT_SERVICES as $s) {
        $wstaw->execute([$k['sub'], $k['username'], $k['email'], $k['first_name'], $k['last_name'], $s]);
    }
    $zadania = $pdo->prepare('SELECT * FROM provision_jobs WHERE authentik_sub = ? AND status = "pending"');
    $zadania->execute([$k['sub']]);
    $wynik = array_fill_keys(ACCOUNT_SERVICES, true);
    foreach ($zadania->fetchAll(PDO::FETCH_ASSOC) as $job) {
        $wynik[$job['service']] = account_job_run($pdo, $job);
    }
    return $wynik;
}

/** Wykonuje jedno zadanie i zapisuje wynik. true = konto jest. */
function account_job_run(PDO $pdo, array $job): bool
{
    try {
        $blad = match ($job['service']) {
            'pterodactyl' => account_job_pterodactyl($pdo, $job),
            'hosting'     => account_job_hosting($pdo, $job),
            'jellyfin'    => account_job_jellyfin($pdo, $job),
            default       => 'nieznana usługa',
        };
    } catch (Throwable $e) {
        $blad = $e->getMessage();
    }

    if ($blad === null) {
        $pdo->prepare('UPDATE provision_jobs SET status = "done", done_at = NOW(), last_error = NULL, attempts = attempts + 1 WHERE id = ?')
            ->execute([$job['id']]);
        return true;
    }

    $proby = (int)$job['attempts'] + 1;
    // 10 min, 20, 40, ... najwyżej 12 h między próbami.
    $odstep = min(10 * (2 ** ($proby - 1)), 720);
    $koniec = $proby >= ACCOUNT_JOB_MAX_PROB;
    $pdo->prepare('UPDATE provision_jobs SET attempts = ?, last_error = ?, status = ?, next_try_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
        ->execute([$proby, mb_substr($blad, 0, 500), $koniec ? 'failed' : 'pending', $odstep, $job['id']]);
    error_log('Konta: ' . $job['service'] . ' dla ' . $job['username'] . ' nieudane (próba ' . $proby . '): ' . $blad);
    if ($koniec) {
        notify_admins($pdo, 'error', 'Nie udało się założyć konta w usłudze',
            $job['service'] . ' dla ' . $job['username'] . ' (' . $job['email'] . ') po ' . $proby . ' próbach: ' . $blad);
    }
    return false;
}

/** @return string|null null = sukces, tekst = powód porażki */
function account_job_pterodactyl(PDO $pdo, array $job): ?string
{
    $u = pterodactyl_ensure_user($job['email'], $job['username'], $job['first_name'] ?: 'User', $job['last_name'] ?: 'Account');
    if ($u === null) {
        return 'panel gier nie odpowiedział';
    }
    $pdo->prepare('UPDATE user_profiles SET pterodactyl_user_id = ? WHERE authentik_sub = ? AND pterodactyl_user_id IS NULL')
        ->execute([(int)$u['id'], $job['authentik_sub']]);
    return null;
}

function account_job_hosting(PDO $pdo, array $job): ?string
{
    if (!hosting_enabled()) {
        return null; // środowisko bez panelu hostingu - konto powstanie przy pierwszym logowaniu tam
    }
    $nazwa = trim($job['first_name'] . ' ' . $job['last_name']) ?: $job['username'];
    $h = hosting_ensure_user($job['email'], $nazwa, $job['authentik_sub']);
    if ($h === null) {
        return 'panel hostingu nie odpowiedział';
    }
    $pdo->prepare('UPDATE user_profiles SET hosting_user = ? WHERE authentik_sub = ?')->execute([$h['user'], $job['authentik_sub']]);
    return null;
}

function account_job_jellyfin(PDO $pdo, array $job): ?string
{
    // Jellyfin nie ma wyszukiwania po e-mailu, więc "już jest" wiemy tylko z profilu.
    $st = $pdo->prepare('SELECT jellyfin_user_id FROM user_profiles WHERE authentik_sub = ?');
    $st->execute([$job['authentik_sub']]);
    if ($st->fetchColumn()) {
        return null;
    }
    $konto = jellyfin_create_user(jellyfin_username_from_authentik($job['username']));
    if ($konto === null) {
        return 'Jellyfin nie odpowiedział';
    }
    $pdo->prepare('UPDATE user_profiles SET jellyfin_user_id = ?, jellyfin_linked_at = NOW(), jellyfin_link_source = "created" WHERE authentik_sub = ?')
        ->execute([$konto['id'], $job['authentik_sub']]);
    $mail = send_jellyfin_credentials_mail($job['email'], $job['first_name'], $konto);
    if (empty($mail['ok'])) {
        error_log('Konta: mail z danymi Jellyfina nie dotarł do ' . $job['email'] . ': ' . ($mail['error'] ?? '?'));
    }
    return null;
}

/** Etap `konta` cyklu życia - ponawia zadania, którym minął odstęp. */
function account_provision_retry(PDO $pdo, bool $apply): void
{
    echo "\n-- konta w usługach --\n";
    $rows = $pdo->query('SELECT * FROM provision_jobs WHERE status = "pending" AND next_try_at <= NOW() ORDER BY id LIMIT 50')
                ->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "  nic\n";
        return;
    }
    foreach ($rows as $job) {
        printf("  #%-4d %-10s %-24s próba %d\n", $job['id'], $job['service'], $job['username'], (int)$job['attempts'] + 1);
        if ($apply) {
            echo '      ' . (account_job_run($pdo, $job) ? 'OK' : 'nie wyszło, ponowię później') . "\n";
        }
    }
}
