<?php
/**
 * POST /api/internal/ptero-server-deleted.php
 *
 * Wywoływane PRZEZ Pterodactyla (Services/Servers/ServerDeletionService.php),
 * zaraz po tym, jak admin skasuje serwer w panelu ("Safely Delete This
 * Server") albo przez Application API. Cel: zamówienie w `orders` ma
 * przestać istnieć DLA KLIENTA (dashboard, panel admina dnct) w tej samej
 * chwili co sam serwer, a nie dopiero przy najbliższym przebiegu
 * backend/tools/lifecycle.php (do godziny później).
 *
 * BEZ CSRF - to nie jest żądanie z przeglądarki z sesją, tylko serwer-do-
 * -serwera (Pterodactyl -> dnct.pl), więc uwierzytelnienie idzie SEKRETEM
 * w nagłówku, tym samym wzorcem co Stripe-Signature w api/stripe/webhook.php,
 * tylko prościej (brak biblioteki od podpisów po stronie Pterodactyla -
 * stały sekret porównywany stałoczasowo przez hash_equals()).
 *
 * DWA ŚRODOWISKA, JEDEN PTERODACTYL: ten sam realny serwer mógł powstać
 * z zamówienia na PRODUKCJI albo na becie (obie sprzedają, patrz
 * dnct.pl/public_html/WEB/-dnct.pl/CLAUDE.md). Pterodactyl nie wie, które -
 * wywołuje ten endpoint na OBU środowiskach, a każde po prostu nie znajduje
 * nic do zamknięcia, jeśli zamówienie nie jest jego. `found:false` nie jest
 * błędem.
 *
 * IDEMPOTENTNE: server_forget_by_ptero_id() filtruje `deleted_at IS NULL`,
 * więc powtórne doręczenie (Pterodactyl ponawia po timeoutcie/5xx) już nic
 * nie zmieni - drugie wywołanie po prostu nie znajdzie zamówienia do zamknięcia.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/orders.php';

require_method('POST');

$secret = (string)env('PTERO_DELETE_WEBHOOK_SECRET', '');
if ($secret === '') {
    error_log('ptero-server-deleted: brak PTERO_DELETE_WEBHOOK_SECRET w konfiguracji - endpoint zablokowany.');
    json_error('Endpoint nieskonfigurowany.', 500);
}

$given = (string)($_SERVER['HTTP_X_INTERNAL_SECRET'] ?? '');
if ($given === '' || !hash_equals($secret, $given)) {
    json_error('Brak dostępu.', 403);
}

$payload = request_payload();
$pteroServerId = (int)($payload['ptero_server_id'] ?? 0);
if ($pteroServerId <= 0) {
    json_error('Brak lub błędne ptero_server_id.', 422);
}

$result = server_forget_by_ptero_id(dnct_db(), $pteroServerId);

json_ok([
    'found'                  => $result['found'],
    'order_id'                => $result['order_id'],
    'subscription_cancelled'  => $result['subscription_cancelled'],
]);
