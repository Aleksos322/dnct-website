<?php
/**
 * assets/clear/notifications.php
 *
 * Dwie warstwy powiadomień:
 *  1. Flash (sesja) - jednorazowe komunikaty po akcji. Bez zmian względem
 *     poprzedniej wersji, patrz $_SESSION['flash'][] = [...]
 *  2. Trwałe (baza danych) - dzwoneczek w prawym górnym rogu + dropdown +
 *     toast dla nowych. Wysyłane przez backend/lib/notifications.php
 *     (notify_user / notify_admins / notify_all).
 *
 * Cztery typy (success/error/warning/info) mają OSOBNY kolor, OSOBNĄ ikonę
 * i OSOBNĄ tekstową etykietę - nie tylko kolor, bo poleganie wyłącznie na
 * barwie zawodzi część odbiorców (patrz DESIGN.md, kolory semantyczne).
 * Paleta trzyma się tokenów DESIGN.md; "info" ponownie używa Głębokiego
 * Indygo (#6366f1) - koloru już zdefiniowanego w systemie jako partner CTA,
 * zamiast wprowadzać piąty kolor spoza palety.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$flashMessages = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$isLoggedInForBell = isset($_SESSION['authentik_username']);

/** Ikona + etykieta PL dla typu powiadomienia - współdzielone przez flash i JS niżej. */
function dnct_notif_type_meta(string $type): array
{
    return match ($type) {
        'success' => [
            'label' => 'Sukces',
            'icon'  => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>',
        ],
        'error' => [
            'label' => 'Błąd',
            'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5M14.5 9.5l-5 5"/>',
        ],
        'warning' => [
            'label' => 'Uwaga',
            'icon'  => '<path d="M12 3.5 21 19H3L12 3.5Z"/><path d="M12 9.5v4"/><path d="M12 16.5h.01"/>',
        ],
        default => [
            'label' => 'Info',
            'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
        ],
    };
}
?>


<?php if (!empty($flashMessages)): ?>
<div id="dnct-notifications" class="fixed right-4 z-[9999] flex flex-col gap-2 w-[90%] max-w-sm" style="bottom: calc(8.5rem + env(safe-area-inset-bottom));">
    <?php foreach ($flashMessages as $i => $flash):
        $type = $flash['type'] ?? 'info';
        $type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
        $message = $flash['message'] ?? '';
        $meta    = dnct_notif_type_meta($type);
    ?>
    <div
        class="dnct-toast dnct-toast--<?php echo $type; ?> opacity-0 translate-x-4 transition-all duration-300 ease-out"
        data-toast-id="flash-<?php echo $i; ?>"
        data-type="<?php echo $type; ?>"
    >
        <span class="dnct-toast-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $meta['icon']; ?></svg>
        </span>
        <span class="dnct-toast-body">
            <span class="dnct-toast-label"><?php echo htmlspecialchars($meta['label']); ?></span>
            <span class="dnct-toast-msg"><?php echo htmlspecialchars($message); ?></span>
        </span>
        <button type="button" class="dnct-toast-close" aria-label="Zamknij">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
