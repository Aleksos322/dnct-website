<?php
/**
 * admin/notifications/send.php
 * Formularz do ręcznego wysyłania powiadomień. Tylko dla adminów.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../backend/lib/notifications.php';

if (!isset($_SESSION['authentik_username']) || !dnct_is_admin()) {
    http_response_code(403);
    exit('Brak dostępu.');
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $_POST['type'] ?? 'info';
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target  = $_POST['target'] ?? 'all';
    $targetUser = trim($_POST['target_user'] ?? '');

    if (!in_array($type, ['info', 'success', 'warning', 'error'], true)) {
        $errors[] = 'Nieprawidłowy typ powiadomienia.';
    }
    if ($title === '') {
        $errors[] = 'Tytuł jest wymagany.';
    }
    if ($message === '') {
        $errors[] = 'Treść jest wymagana.';
    }
    if ($target === 'user' && $targetUser === '') {
        $errors[] = 'Podaj nazwę użytkownika docelowego.';
    }

    if (!$errors) {
        $pdo = dnct_db();
        switch ($target) {
            case 'admins':
                notify_admins($pdo, $type, $title, $message);
                break;
            case 'user':
                notify_user($pdo, $targetUser, $type, $title, $message);
                break;
            case 'all':
            default:
                notify_all($pdo, $type, $title, $message);
                break;
        }
        $success = true;
    }
}

$pageTitle = 'Wyślij powiadomienie - Admin';

ob_start();
?>
<div class="bg-gray-900/80 p-6 rounded-xl border border-gray-800 backdrop-blur-md max-w-lg w-full shadow-2xl text-left">
    <h1 class="text-2xl font-bold mb-4 text-center">Wyślij powiadomienie</h1>

    <?php if ($success): ?>
        <div class="bg-green-600/20 border border-green-600 text-green-300 rounded-lg px-4 py-2 mb-4 text-sm">
            Powiadomienie zostało wysłane.
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $err): ?>
        <div class="bg-red-600/20 border border-red-600 text-red-300 rounded-lg px-4 py-2 mb-4 text-sm">
            <?php echo htmlspecialchars($err); ?>
        </div>
    <?php endforeach; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm text-gray-300 mb-1">Typ</label>
            <select name="type" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white">
                <option value="info">Informacja</option>
                <option value="success">Sukces</option>
                <option value="warning">Ostrzeżenie</option>
                <option value="error">Błąd</option>
            </select>
        </div>

        <div>
            <label class="block text-sm text-gray-300 mb-1">Odbiorca</label>
            <select name="target" id="target" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white" onchange="document.getElementById('target_user_wrap').classList.toggle('hidden', this.value !== 'user')">
                <option value="all">Wszyscy użytkownicy</option>
                <option value="admins">Tylko admini</option>
                <option value="user">Konkretny użytkownik</option>
            </select>
        </div>

        <div id="target_user_wrap" class="hidden">
            <label class="block text-sm text-gray-300 mb-1">Nazwa użytkownika</label>
            <input type="text" name="target_user" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white" placeholder="np. jan_kowalski">
        </div>

        <div>
            <label class="block text-sm text-gray-300 mb-1">Tytuł</label>
            <input type="text" name="title" required maxlength="150" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white">
        </div>

        <div>
            <label class="block text-sm text-gray-300 mb-1">Treść</label>
            <textarea name="message" required rows="4" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white"></textarea>
        </div>

        <button type="submit" class="w-full bg-gradient-main hover:scale-[1.02] text-white font-semibold rounded-lg px-4 py-2 shadow-lg shadow-purple-500/20 transition-transform">
            Wyślij
        </button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../assets/clear/clear.php';