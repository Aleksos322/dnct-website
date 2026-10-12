<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/capacity.php';

$pageTitle = 'Status usług – DNCT Hosting';
$metaDescription = 'Aktualny stan strony, panelu i danych węzła DNCT Hosting. Status oparty na bieżącym odczycie, bez sztucznie wyliczanego uptime.';
$canonicalUrl = 'https://beta.dnct.pl/status';
$centerContent = false;
$checkedAt = date('d.m.Y, H:i');
$checks = [['name' => 'Strona DNCT', 'ok' => true, 'detail' => 'Ta strona odpowiada poprawnie.']];
$capacity = null;

try {
    $panel = panel_db();
    $nodes = (int)$panel->query('SELECT COUNT(*) FROM nodes')->fetchColumn();
    $capacity = node_capacity($panel);
    $checks[] = ['name' => 'Dane panelu', 'ok' => $nodes > 0, 'detail' => $nodes > 0 ? 'Odczytano konfigurację ' . $nodes . ' węzła/węzłów.' : 'Panel nie zwrócił żadnego węzła.'];
} catch (Throwable $e) {
    error_log('status: panel db - ' . $e->getMessage());
    $checks[] = ['name' => 'Dane panelu', 'ok' => false, 'detail' => 'Nie udało się teraz odczytać danych panelu.'];
}

try {
    $api = pterodactyl_api_request('GET', '/api/application/nodes?per_page=1');
    $checks[] = ['name' => 'API panelu', 'ok' => $api['http_code'] === 200, 'detail' => $api['http_code'] === 200 ? 'Panel API odpowiada poprawnie.' : 'Panel API nie odpowiedział poprawnie — sprawdzamy to.'];
} catch (Throwable $e) {
    error_log('status: panel api - ' . $e->getMessage());
    $checks[] = ['name' => 'API panelu', 'ok' => false, 'detail' => 'Nie udało się teraz połączyć z API panelu.'];
}

$allOk = !array_filter($checks, static fn(array $check): bool => !$check['ok']);
ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/status.css'); ?>">
<div class="status-page">
    <header class="status-hero"><p class="status-kicker">DNCT Hosting · bieżący odczyt</p><h1>Status usług</h1><p>Stan jest sprawdzany przy otwarciu strony. Nie publikujemy sztucznego procentu uptime ani historii, której nie mierzyliśmy automatycznie.</p></header>
    <section class="status-overview glass <?php echo $allOk ? 'is-ok' : 'is-warn'; ?>"><span class="status-dot" aria-hidden="true"></span><div><strong><?php echo $allOk ? 'Wszystkie sprawdzone usługi działają' : 'Część usług wymaga sprawdzenia'; ?></strong><span>Odczyt: <?php echo htmlspecialchars($checkedAt); ?> · odśwież stronę, aby wykonać nowy test.</span></div></section>
    <section class="status-grid" aria-label="Składniki usługi">
        <?php foreach ($checks as $check): ?><article class="status-card glass"><span class="status-state <?php echo $check['ok'] ? 'is-ok' : 'is-warn'; ?>"><?php echo $check['ok'] ? 'Działa' : 'Sprawdzamy'; ?></span><h2><?php echo htmlspecialchars($check['name']); ?></h2><p><?php echo htmlspecialchars($check['detail']); ?></p></article><?php endforeach; ?>
    </section>
    <section class="status-capacity glass"><div><p class="status-kicker">Pojemność sprzedażowa</p><h2><?php echo $capacity ? htmlspecialchars($capacity['name']) : 'Brak odczytu węzła'; ?></h2><p><?php echo $capacity ? 'To przydzielone zasoby serwerów, nie chwilowe zużycie maszyn.' : 'Spróbuj odświeżyć stronę za chwilę.'; ?></p></div>
        <?php if ($capacity): ?><div class="status-metrics"><div><span>Serwery</span><strong><?php echo (int)$capacity['servers']; ?></strong></div><div><span>Wolny RAM</span><strong><?php echo number_format($capacity['free_memory'] / 1024, 1, ',', ' '); ?> GB</strong></div><div><span>Wolny dysk</span><strong><?php echo number_format($capacity['free_disk'] / 1024, 1, ',', ' '); ?> GB</strong></div></div><?php endif; ?>
    </section>
    <section class="status-history"><h2>Historia incydentów</h2><p>Automatyczny monitoring okresowy nie jest jeszcze podpięty, dlatego nie udajemy historycznego uptime. Gdy go wdrożymy, tutaj pojawią się potwierdzone incydenty i ich rozwiązania.</p><a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener">Zgłoś problem na Discordzie →</a></section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
