<?php
/**
 * Twoje serwery - /twoje-serwery
 *
 * Lista usług klienta: co ma, do kiedy, i co może z tym zrobić.
 * Zastępuje beta/przedluzenie.php (84 KB w jednym pliku, z formularzami,
 * zapytaniami i widokiem wymieszanymi razem).
 *
 * WSZYSTKIE KWOTY I DATY LICZY SERWER. Strona dostaje gotowe warianty
 * w atrybutach `data-*`, a skrypt tylko je pokazuje. To ta sama zasada, co
 * w formularzu zamówienia: drugi wzór po stronie przeglądarki prędzej czy
 * później rozjedzie się z tym w backend/lib/pricing.php, a różnicę zauważy
 * dopiero klient, porównując ekran z rachunkiem.
 *
 * Strona jest podsumowaniem usługi, nie drugim panelem Pterodactyla. Pokazuje
 * stan handlowy oraz lekki odczyt realnego stanu maszyny; konsola, pliki,
 * tworzenie i przywracanie backupów nadal są wyłącznie w panelu gry.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/stripe.php';
require_once __DIR__ . '/backend/lib/order_mail.php';
require_once __DIR__ . '/backend/lib/plan_change.php';

$pageTitle       = 'Twoje serwery - DNCT Hosting';
$metaDescription = 'Twoje serwery, terminy ważności, przedłużanie i zmiana pakietu w DNCT Hosting.';
$centerContent   = false;

$isLoggedIn = isset($_SESSION['authentik_email'], $_SESSION['authentik_username']);
$salesOn    = stripe_sales_enabled();

$cards = [];

if ($isLoggedIn) {
    $dnct = dnct_db();

    /* Pokazujemy tylko zamówienia, które coś uruchomiły. Porzucone sesje
       płatności zostają w bazie jako ślad do diagnostyki, ale na liście klienta
       byłyby wyłącznie zamieszaniem - nikt nie chce oglądać „zamówienia",
       którego nie dokończył. Przedłużenia i zmiany pakietu też pomijamy: to
       zdarzenia w historii usługi, nie osobne usługi. */
    $stmt = $dnct->prepare(
        'SELECT * FROM orders
          WHERE user_email = ?
            AND kind = "new"
            AND status IN ("provisioned", "failed", "cancelled", "paid")
          ORDER BY id DESC'
    );
    $stmt->execute([(string)$_SESSION['authentik_email']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $panel = null;
    try {
        $panel = new PDO(
            'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
            (string)env('DB_USERNAME'),
            (string)env('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        error_log('twoje-serwery: baza panelu niedostępna - ' . $e->getMessage());
    }

    // Kod "Poleć znajomemu" - powstaje przy pierwszym wejściu klienta z opłaconym zakupem.
    $polecenie = null;
    try {
        require_once __DIR__ . '/backend/lib/polecenia.php';
        if ($kodPolecenia = polecenie_kod($dnct, (string)$_SESSION['authentik_email'], (string)$_SESSION['authentik_username'])) {
            $polecenie = ['kod' => (string)$kodPolecenia['code']] + polecenie_statystyki($dnct, (string)$_SESSION['authentik_email']);
        }
    } catch (Throwable $e) {
        error_log('twoje-serwery: polecenia - ' . $e->getMessage());
    }

    foreach ($orders as $order) {
        $card = srv_build_card($order, $panel, $salesOn);
        if ($card !== null) {
            $cards[] = $card;
        }
    }

    /* Serwer może należeć do konta w panelu, choć nie powstał przez obecny
       checkout (np. został dodany ręcznie). Nie chowamy go wtedy przed jego
       właścicielem, ale nie wymyślamy mu płatności ani terminu ważności. */
    if ($panel !== null) {
        $knownServerIds = [];
        foreach ($orders as $order) {
            if (!empty($order['ptero_server_id'])) {
                $knownServerIds[(int)$order['ptero_server_id']] = true;
            }
        }

        try {
            $owner = $dnct->prepare(
                'SELECT pterodactyl_user_id FROM user_profiles WHERE authentik_username = ? LIMIT 1'
            );
            $owner->execute([(string)$_SESSION['authentik_username']]);
            $panelUserId = (int)$owner->fetchColumn();

            if ($panelUserId > 0) {
                $servers = $panel->prepare(
                    'SELECT s.id, s.name, s.status, s.memory, s.cpu, s.disk, s.backup_limit, s.created_at,
                            n.name AS nest_name, e.name AS egg_name,
                            COALESCE(a.ip_alias, a.ip) AS host, a.port
                       FROM servers s
                  LEFT JOIN nests n ON n.id = s.nest_id
                  LEFT JOIN eggs e ON e.id = s.egg_id
                  LEFT JOIN allocations a ON a.id = s.allocation_id
                      WHERE s.owner_id = ?
                   ORDER BY s.created_at DESC, s.id DESC'
                );
                $servers->execute([$panelUserId]);

                foreach ($servers->fetchAll(PDO::FETCH_ASSOC) as $server) {
                    if (!isset($knownServerIds[(int)$server['id']])) {
                        $cards[] = srv_build_panel_only_card($server);
                    }
                }
            }
        } catch (Throwable $e) {
            // Zakupione usługi zostają widoczne nawet przy awarii odczytu panelu.
            error_log('twoje-serwery: serwery dodane ręcznie - ' . $e->getMessage());
        }
    }
}

/**
 * Wszystko, czego karta potrzebuje do wyświetlenia i obsługi.
 *
 * Zbieramy to w jednym miejscu, żeby widok niżej był samym HTML-em. Mieszanie
 * zapytań z markupem jest dokładnie tym, przez co stary plik urósł do 84 KB.
 *
 * @return array<string,mixed>|null null, gdy serwer zniknął z Pterodactyla
 *                                   poza naszym cyklem życia - karta się nie pokazuje
 */
function srv_build_card(array $order, ?PDO $panel, bool $salesOn): ?array
{
    $card = ['order' => $order];

    // --- stan czasu -------------------------------------------------------
    $expiresTs = $order['expires_at'] !== null ? strtotime((string)$order['expires_at']) : null;
    $daysLeft  = $expiresTs !== null ? (int)floor(($expiresTs - time()) / 86400) : null;

    $card['days_left'] = $daysLeft;
    $card['deadlines'] = order_deadlines($order);

    $isSub   = $order['billing_mode'] === 'subscription';
    $renews  = $isSub && $order['status'] !== 'cancelled' && !empty($order['stripe_subscription_id']);
    $inGrace = !empty($order['grace_until']);

    $card['is_subscription'] = $isSub;
    $card['renews']          = $renews;
    $card['in_grace']        = $inGrace;

    /* JEDEN STAN NA KARTĘ, w kolejności pilności. Klient ma zobaczyć
       najważniejszą rzecz, a nie wszystkie naraz. */
    if ($order['status'] === 'failed') {
        [$card['tone'], $card['state']] = ['bad', 'Wymaga naszej reakcji'];
    } elseif ($daysLeft !== null && $daysLeft < 0) {
        [$card['tone'], $card['state']] = ['bad', 'Wygasł'];
    } elseif ($inGrace) {
        [$card['tone'], $card['state']] = ['warn', 'Nieopłacona płatność'];
    } elseif ($order['status'] === 'cancelled') {
        [$card['tone'], $card['state']] = ['warn', 'Bez odnawiania'];
    } elseif ($daysLeft !== null && $daysLeft <= 3) {
        [$card['tone'], $card['state']] = ['warn', 'Kończy się'];
    } else {
        [$card['tone'], $card['state']] = ['ok', $isSub ? 'Abonament aktywny' : 'Aktywny'];
    }

    // --- szczegóły z panelu gry ------------------------------------------
    $card['address']   = null;
    $card['suspended'] = false;
    $card['plan_name'] = null;
    $card['game_icon'] = null;
    $card['periods']   = [];
    $card['plans']     = [];

    if ($panel === null) {
        return $card;
    }

    /* Serwer zniknął z panelu poza naszym cyklem życia (np. ręczne skasowanie
       przez admina w Pterodactylu, z pominięciem backend/tools/lifecycle.php).
       `orders.ptero_server_id` nie znika automatycznie w takiej sytuacji -
       bez tej kontroli klient widziałby tu usługę, której realnie już nie ma.
       Dotyczy tylko "provisioned" - zamówienie "failed"/"cancelled" i tak nie
       ma za czym tęsknić, a "paid" jeszcze nie zdążyło dostać serwera. */
    if ($order['status'] === 'provisioned'
        && !empty($order['ptero_server_id'])
        && !pterodactyl_server_exists($panel, (int)$order['ptero_server_id'])
    ) {
        return null;
    }

    if (!empty($order['ptero_server_id'])) {
        /* Adres serwera to pierwsza rzecz, której klient tu szuka - rozdaje go
           znajomym i nie chce po niego wchodzić do panelu. Bierzemy alokację
           domyślną, bo to ona jest adresem gry. */
        /* Zawieszenie trzyma kolumna `status` ('suspended' albo NULL), a nie
           osobna flaga - starsze wersje Pterodactyla miały `suspended`
           i stąd łatwo o pomyłkę. */
        $q = $panel->prepare(
            'SELECT a.ip, a.ip_alias, a.port, s.status
               FROM allocations a
               JOIN servers s ON s.id = a.server_id
              WHERE a.server_id = ?
              ORDER BY a.id ASC LIMIT 1'
        );
        try {
            $q->execute([(int)$order['ptero_server_id']]);
            if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                /* Klientowi podajemy adres PUBLICZNY (`ip_alias`), nie adres
                   wewnętrzny węzła. Wpisanie 192.168.50.5 w grze nie połączy
                   nikogo spoza naszej sieci. */
                $host = !empty($row['ip_alias']) ? $row['ip_alias'] : $row['ip'];
                $card['address']   = $host . ':' . $row['port'];
                $card['suspended'] = ($row['status'] ?? '') === 'suspended';
            }
        } catch (Throwable $e) {
            // Brak adresu nie może wywrócić całej listy usług.
            error_log('twoje-serwery: adres serwera - ' . $e->getMessage());
        }
    }

    if (!empty($order['plan_id'])) {
        $q = $panel->prepare('SELECT name FROM hosting_plans WHERE id = ?');
        $q->execute([(int)$order['plan_id']]);
        $card['plan_name'] = $q->fetchColumn() ?: null;
    }

    $q = $panel->prepare('SELECT icon_img FROM hosting_games WHERE type = ?');
    $q->execute([(string)$order['game_type']]);
    if ($icon = $q->fetchColumn()) {
        $card['game_icon'] = '/assets/media/Server_card/icons/' . basename((string)$icon);
    }

    if (!$salesOn || $order['status'] === 'failed') {
        return $card;
    }

    /* WARIANTY PRZEDŁUŻENIA policzone po stronie serwera - każdy okres razy
       każdy dopuszczalny model rozliczenia. Front wybiera z gotowej listy. */
    $base = $expiresTs !== null ? max($expiresTs, time()) : time();

    foreach ($panel->query('SELECT days, label, badge FROM hosting_periods WHERE is_active = 1 ORDER BY sort_order') as $p) {
        $days  = (int)$p['days'];
        $entry = [
            'days'     => $days,
            'label'    => (string)$p['label'],
            'badge'    => $p['badge'] !== null ? (string)$p['badge'] : null,
            'until'    => czas_fmt_ts($base + $days * 86400, 'd.m.Y'),
            'prices'   => [],
        ];

        foreach (['payment', 'subscription'] as $mode) {
            // Abonament ma sens tylko wtedy, gdy serwer jeszcze go nie ma -
            // druga subskrypcja obok działającej to dwie płatności za ten sam czas.
            if ($mode === 'subscription' && $renews) {
                continue;
            }
            try {
                $q = pricing_quote($panel, [
                    'game_type'    => (string)$order['game_type'],
                    'plan_id'      => (int)($order['plan_id'] ?? 0),
                    'slots'        => (int)($order['slots'] ?? 0),
                    'period_days'  => $days,
                    'billing_mode' => $mode,
                ]);
                $entry['prices'][$mode] = pricing_format((int)$q['amount_gross']);
            } catch (RuntimeException $e) {
                continue;   // np. brak odpowiednika interwału w Stripe
            }
        }

        if ($entry['prices']) {
            $card['periods'][] = $entry;
        }
    }

    // Zmiana pakietu dotyczy wyłącznie gier z gotowymi planami; sloty mają
    // własny konfigurator i inny sposób doboru zasobów.
    if ($order['slots'] === null && $order['status'] === 'provisioned') {
        $card['plans'] = plan_change_options($panel, $order);
    }

    return $card;
}

/** @param array<string,mixed> $server @return array<string,mixed> */
function srv_build_panel_only_card(array $server): array
{
    $host = trim((string)($server['host'] ?? ''));
    $port = (int)($server['port'] ?? 0);
    $game = trim((string)($server['nest_name'] ?? ''));
    $egg = trim((string)($server['egg_name'] ?? ''));

    return [
        'panel_only' => true,
        'server_name' => (string)$server['name'],
        'game_name' => $game !== '' ? $game : 'Serwer gry',
        'egg_name' => $egg !== '' ? $egg : '—',
        'created_at' => (string)$server['created_at'],
        'address' => $host !== '' && $port > 0 ? $host . ':' . $port : null,
        'suspended' => ($server['status'] ?? '') === 'suspended',
        'tone' => ($server['status'] ?? '') === 'suspended' ? 'warn' : 'ok',
        'state' => ($server['status'] ?? '') === 'suspended' ? 'Serwer zawieszony' : 'Aktywny w panelu',
        'ram_mb' => (int)$server['memory'],
        'cpu_cores' => ((int)$server['cpu']) / 100,
        'ssd_mb' => (int)$server['disk'],
        'backups_limit' => (int)$server['backup_limit'],
        // Puste celowo: ten serwer nie ma powiązanego zamówienia do rozliczeń.
        'periods' => [], 'plans' => [], 'renews' => false,
        'is_subscription' => false, 'in_grace' => false,
    ];
}

/** Megabajty na czytelny zapis. */
function srv_size(int $mb): string
{
    if ($mb >= 1024) {
        $gb = $mb / 1024;
        return rtrim(rtrim(number_format($gb, 1, '.', ''), '0'), '.') . ' GB';
    }
    return $mb . ' MB';
}

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/order.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/servers.css'); ?>">

<div class="cennik-page">
<div class="cnk-wrap">

<section class="ord-head">
    <span class="cnk-eyebrow">Twoje konto</span>
    <h1 class="cnk-h1">Twoje serwery</h1>
    <?php /* Historia opłat to osobna strona, bo odpowiada na inne pytanie:
             ta lista mówi "co mam", tamta "za co zapłaciłem". Odnośnik stoi
             tutaj, bo właśnie tu klient przychodzi ze sprawami rozliczeń. */ ?>
    <?php if ($isLoggedIn): ?>
        <p class="srv-lead-link"><a href="/oplaty">Historia opłat →</a></p>
    <?php endif; ?>
</section>

<?php if (!$isLoggedIn): ?>
    <div class="cennik-info glass">
        <h2>Zaloguj się, żeby zobaczyć swoje serwery</h2>
        <p>Usługi przypisujemy do konta - bez logowania nie mamy czego pokazać.</p>
        <p><a class="ord-back" href="/login">Przejdź do logowania</a></p>
    </div>

<?php elseif (!$cards): ?>
    <div class="cennik-info glass">
        <h2>Nie masz jeszcze żadnego serwera</h2>
        <p>Gdy zamówisz pierwszy, pojawi się tutaj razem z terminem ważności, przedłużaniem i zmianą pakietu.</p>
        <p><a class="ord-back" href="/cennik">Zobacz cennik</a></p>
    </div>

<?php else: ?>
    <div class="srv-list">
        <?php foreach ($cards as $c): $isPanelOnly = !empty($c['panel_only']); $o = $c['order'] ?? null; ?>
            <article class="srv glass is-<?php echo $c['tone']; ?>"
                     <?php if (!$isPanelOnly): ?>data-order="<?php echo (int)$o['id']; ?>"<?php endif; ?>
                     data-periods="<?php echo htmlspecialchars(json_encode($c['periods'], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>"
                     data-plans="<?php echo htmlspecialchars(json_encode($c['plans'], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>">

                <header class="srv-head">
                    <div class="srv-ident">
                        <?php if (!$isPanelOnly && $c['game_icon']): ?>
                            <img class="srv-icon" src="<?php echo htmlspecialchars($c['game_icon']); ?>"
                                 alt="" width="44" height="44" loading="lazy">
                        <?php endif; ?>
                        <div>
                            <h2 class="srv-name">
                                <?php echo htmlspecialchars($isPanelOnly ? $c['server_name'] : ucfirst((string)$o['game_type'])); ?>
                                <span class="srv-spec">
                                    <?php echo htmlspecialchars($isPanelOnly ? $c['game_name'] :
                                        $c['plan_name'] ?? ($o['slots'] !== null ? (int)$o['slots'] . ' slotów' : 'pakiet własny')
                                    ); ?>
                                </span>
                            </h2>
                            <p class="srv-sub">
                                <?php if ($isPanelOnly): ?>
                                    Dodany bezpośrednio w panelu
                                    · utworzony <?php echo htmlspecialchars(czas_fmt((string)$c['created_at'], 'd.m.Y')); ?>
                                <?php else: ?>
                                    Zamówienie #<?php echo (int)$o['id']; ?>
                                    · kupione <?php echo htmlspecialchars(czas_fmt((string)$o['created_at'], 'd.m.Y')); ?>
                                <?php endif; ?>
                                <?php if ($c['suspended']): ?>
                                    · <strong class="srv-flag">serwer zawieszony</strong>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <span class="srv-state"><?php echo htmlspecialchars($c['state']); ?></span>
                </header>

                <?php /* Adres to pierwsza rzecz, po którą klient tu wchodzi -
                         rozdaje go znajomym i nie chce szukać w panelu. */ ?>
                <?php if ($c['address']): ?>
                    <div class="srv-address">
                        <span class="srv-address-label">Adres serwera</span>
                        <code data-role="addr"><?php echo htmlspecialchars($c['address']); ?></code>
                        <button type="button" class="srv-copy" data-role="copy"
                                data-value="<?php echo htmlspecialchars($c['address'], ENT_QUOTES); ?>">Kopiuj</button>
                    </div>
                <?php endif; ?>

                <?php if (!$isPanelOnly && !empty($o['ptero_server_id']) && $o['status'] === 'provisioned'): ?>
                    <section class="srv-live" data-role="live" aria-live="polite" aria-busy="true">
                        <div class="srv-live-head">
                            <div><span>Stan serwera</span><strong>Ładuję aktualne dane…</strong></div>
                            <button type="button" class="srv-live-refresh" data-role="refreshLive" disabled>Odśwież</button>
                        </div>
                        <div class="srv-live-grid">
                            <div><span>Stan</span><strong data-live="state"><i class="srv-loading"></i> sprawdzam</strong></div>
                            <div><span>RAM teraz</span><strong data-live="memory"><i class="srv-loading"></i></strong></div>
                            <div><span>Procesor teraz</span><strong data-live="cpu"><i class="srv-loading"></i></strong></div>
                            <div><span>Dysk teraz</span><strong data-live="disk"><i class="srv-loading"></i></strong></div>
                            <div><span>Backupy</span><strong data-live="backups"><i class="srv-loading"></i></strong><small data-live="backupLast">sprawdzam ostatnią kopię</small></div>
                            <div><span>Czas działania</span><strong data-live="uptime"><i class="srv-loading"></i></strong></div>
                        </div>
                        <p class="srv-live-note" data-role="liveNote">Dane odświeżają się niezależnie od reszty strony.</p>
                    </section>
                <?php endif; ?>

                <div class="srv-specs">
                    <div><span>RAM</span><strong><?php echo srv_size((int)($isPanelOnly ? $c['ram_mb'] : $o['ram_mb'])); ?></strong></div>
                    <div><span>Procesor</span><strong><?php echo rtrim(rtrim(number_format((float)($isPanelOnly ? $c['cpu_cores'] : $o['cpu_cores']), 1, '.', ''), '0'), '.'); ?> vCore</strong></div>
                    <div><span>Dysk SSD</span><strong><?php echo srv_size((int)($isPanelOnly ? $c['ssd_mb'] : $o['ssd_mb'])); ?></strong></div>
                    <div><span>Backupy</span><strong><?php echo (int)($isPanelOnly ? $c['backups_limit'] : $o['backups_limit']); ?></strong></div>
                </div>

                <?php if ($isPanelOnly): ?>
                <div class="srv-facts">
                    <div><span>Silnik</span><strong><?php echo htmlspecialchars($c['egg_name']); ?></strong></div>
                    <div><span>Rozliczenie</span><strong>Poza zamówieniami DNCT</strong></div>
                    <div><span>Stan</span><strong><?php echo htmlspecialchars($c['state']); ?></strong></div>
                </div>
                <?php else: ?>
                <div class="srv-facts">
                    <div>
                        <span>Opłacone do</span>
                        <strong><?php echo $o['expires_at'] !== null
                            ? htmlspecialchars(czas_fmt((string)$o['expires_at'], 'd.m.Y, H:i'))
                            : '-'; ?></strong>
                    </div>
                    <div>
                        <span>Pozostało</span>
                        <strong><?php
                            $d = $c['days_left'];
                            if ($d === null)   { echo '-'; }
                            elseif ($d < 0)    { echo abs($d), ' dni po terminie'; }
                            elseif ($d === 0)  { echo 'ostatni dzień'; }
                            else               { echo $d, ' dni'; }
                        ?></strong>
                    </div>
                    <div>
                        <span>Zawieszenie</span>
                        <strong class="srv-suspend"><?php echo htmlspecialchars($c['deadlines']['suspends']); ?></strong>
                    </div>
                    <div>
                        <span>Rozliczenie</span>
                        <strong><?php
                            echo $c['renews'] ? 'abonament' : ($c['is_subscription'] ? 'abonament (bez odnawiania)' : 'jednorazowo');
                        ?></strong>
                    </div>
                    <div>
                        <span>Ostatnia płatność</span>
                        <strong><?php echo pricing_format((int)$o['amount_gross']); ?> zł</strong>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$isPanelOnly && $o['status'] === 'failed'): ?>
                    <p class="srv-alert">
                        Płatność doszła, ale serwer nie został utworzony automatycznie.
                        Wiemy o tym i zajmiemy się tym ręcznie - napisz na Discordzie, jeśli chcesz przyspieszyć.
                    </p>
                <?php elseif (!$isPanelOnly && $c['in_grace']): ?>
                    <p class="srv-alert">
                        Nie udało się pobrać opłaty. Serwer działa do
                        <?php echo htmlspecialchars(czas_fmt((string)$o['grace_until'], 'd.m.Y, H:i')); ?> -
                        popraw dane karty albo przedłuż jednorazowo.
                    </p>
                <?php elseif (!$isPanelOnly && $c['renews']): ?>
                    <p class="srv-note">Ten serwer odnawia się sam. Kolejny okres pobierze się automatycznie.</p>
                <?php elseif (!$isPanelOnly && $c['is_subscription']): ?>
                    <p class="srv-note">Abonament wyłączony - serwer działa do końca opłaconego okresu.</p>
                <?php endif; ?>

                <?php /* --- PASEK AKCJI --- */ ?>
                <div class="srv-actions">
                    <?php if ($salesOn && $c['periods']): ?>
                        <button type="button" class="srv-btn is-primary" data-role="toggleExtend">Przedłuż serwer</button>
                    <?php endif; ?>

                    <?php if ($salesOn && $c['plans']): ?>
                        <button type="button" class="srv-btn" data-role="togglePlans">Zmień pakiet</button>
                    <?php endif; ?>

                    <?php if ($c['renews']): ?>
                        <button type="button" class="srv-btn is-quiet" data-role="cancelSub">Zrezygnuj z odnawiania</button>
                    <?php endif; ?>

                    <a class="srv-btn is-link" href="https://panel.dnct.pl" target="_blank" rel="noopener">Otwórz w panelu</a>
                </div>

                <p class="srv-error" data-role="error" hidden></p>

                <?php /* --- PRZEDŁUŻANIE --- */ ?>
                <?php if ($salesOn && $c['periods']): ?>
                    <div class="srv-panel" data-role="extendPanel" hidden>
                        <h3 class="srv-panel-h">Na jak długo?</h3>
                        <div class="srv-opts" data-role="periods">
                            <?php foreach ($c['periods'] as $i => $p): ?>
                                <button type="button" class="srv-opt<?php echo $i === 0 ? ' is-active' : ''; ?>"
                                        data-days="<?php echo (int)$p['days']; ?>">
                                    <span class="srv-opt-label"><?php echo htmlspecialchars($p['label']); ?></span>
                                    <span class="srv-opt-sub">+<?php echo (int)$p['days']; ?> dni</span>
                                    <?php if ($p['badge']): ?>
                                        <span class="srv-opt-badge"><?php echo htmlspecialchars($p['badge']); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <h3 class="srv-panel-h">Jak rozliczamy?</h3>
                        <div class="srv-opts srv-opts-modes" data-role="modes">
                            <button type="button" class="srv-opt is-active" data-mode="payment">
                                <span class="srv-opt-label">Jednorazowo</span>
                                <span class="srv-opt-sub">Płacisz raz, bez zobowiązania.</span>
                            </button>
                            <?php if (!$c['renews']): ?>
                                <button type="button" class="srv-opt" data-mode="subscription">
                                    <span class="srv-opt-label">Abonament</span>
                                    <span class="srv-opt-sub">Odnawia się sam. Rezygnujesz kiedy chcesz.</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php /* Podsumowanie mówi WPROST, ile dni dochodzi i do
                                 kiedy serwer będzie działał. Sama kwota nie
                                 odpowiada na pytanie, które klient ma w głowie. */ ?>
                        <div class="srv-summary">
                            <div class="srv-summary-text">
                                <strong data-role="sumDays"></strong>
                                <span data-role="sumUntil"></span>
                            </div>
                            <div class="srv-summary-price">
                                <span data-role="sumPrice">-</span>
                                <span class="srv-summary-cur">zł</span>
                            </div>
                            <button type="button" class="srv-btn is-primary" data-role="payExtend">Przejdź do płatności</button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php /* --- ZMIANA PAKIETU --- */ ?>
                <?php if ($salesOn && $c['plans']): ?>
                    <div class="srv-panel" data-role="plansPanel" hidden>
                        <h3 class="srv-panel-h">Dostępne pakiety</h3>
                        <p class="srv-panel-note">
                            Wyżej dopłacasz tylko za czas, który Ci został - data ważności zostaje bez zmian.
                            Niżej nie płacisz nic, a niewykorzystana różnica wraca jako dodatkowe dni.
                        </p>

                        <div class="srv-plans">
                            <?php foreach ($c['plans'] as $p): ?>
                                <button type="button" class="srv-plan is-<?php echo htmlspecialchars($p['direction']); ?>"
                                        data-plan="<?php echo (int)$p['plan_id']; ?>">
                                    <span class="srv-plan-name"><?php echo htmlspecialchars($p['name']); ?></span>
                                    <span class="srv-plan-spec">
                                        <?php echo srv_size((int)$p['ram_mb']); ?> RAM ·
                                        <?php echo rtrim(rtrim(number_format((float)$p['cpu_cores'], 1, '.', ''), '0'), '.'); ?> vCore ·
                                        <?php echo srv_size((int)$p['ssd_mb']); ?>
                                    </span>
                                    <span class="srv-plan-cost">
                                        <?php if ($p['direction'] === 'up'): ?>
                                            dopłata <?php echo htmlspecialchars(number_format((float)$p['price'], 2, ',', ' ')); ?> zł
                                        <?php elseif ((int)$p['bonus_days'] > 0): ?>
                                            bez dopłaty · +<?php echo (int)$p['bonus_days']; ?> dni
                                        <?php else: ?>
                                            bez dopłaty
                                        <?php endif; ?>
                                    </span>
                                    <span class="srv-plan-monthly"><?php echo htmlspecialchars(number_format((float)$p['monthly'], 2, ',', ' ')); ?> zł/mies. dalej</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($polecenie !== null): $linkPolecenia = 'https://game.dnct.pl/?kod=' . rawurlencode($polecenie['kod']); ?>
        <section class="srv-polec glass" aria-labelledby="srv-polec-h">
            <div>
                <h2 id="srv-polec-h">Poleć znajomemu</h2>
                <p>Znajomy płaci <strong><?php echo POLECENIE_RABAT; ?>% mniej</strong> za pierwszy serwer, a Ty dostajesz <strong>+<?php echo POLECENIE_DNI; ?> dni</strong> do swojego, gdy zapłaci.</p>
                <?php if ($polecenie['ile'] > 0): ?>
                    <p class="srv-polec-wynik">Poleciłeś: <?php echo (int)$polecenie['ile']; ?> · zdobyte dni: <?php echo (int)$polecenie['dni']; ?></p>
                <?php endif; ?>
            </div>
            <div class="srv-polec-kod">
                <code><?php echo htmlspecialchars($polecenie['kod']); ?></code>
                <button type="button" class="ord-cta srv-polec-btn" data-kopiuj="<?php echo htmlspecialchars($linkPolecenia); ?>">Kopiuj link</button>
            </div>
        </section>
        <script>
        document.querySelector('.srv-polec-btn')?.addEventListener('click', function () {
            var b = this;
            navigator.clipboard.writeText(b.dataset.kopiuj).then(function () {
                b.textContent = 'Skopiowano';
                setTimeout(function () { b.textContent = 'Kopiuj link'; }, 2000);
            }, function () { window.prompt('Skopiuj link:', b.dataset.kopiuj); });
        });
        </script>
    <?php endif; ?>

    <?php if (!$salesOn): ?>
        <p class="ord-hint">Przedłużanie i zmiana pakietu są chwilowo wyłączone - trwa przerwa techniczna.</p>
    <?php endif; ?>

    <script id="srv-data" type="application/json"><?php
        echo json_encode(['csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?></script>
    <script src="<?php echo asset('/assets/js/servers.js'); ?>" defer></script>
<?php endif; ?>

</div><!-- /.cnk-wrap -->
</div><!-- /.cennik-page -->
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
