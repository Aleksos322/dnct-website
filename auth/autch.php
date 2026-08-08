<?php
/**
 * Stary endpoint logowania - dziś już tylko przekierowuje.
 *
 * Logowanie login+hasło przez Authentik Flow Executor API zostało usunięte.
 * Powód: hasło szło do Authentika Z SERWERA (cURL), a ciasteczko sesji
 * Authentika lądowało w pliku tymczasowym i było kasowane. Przeglądarka nigdy
 * go nie dostawała, więc dla Authentika użytkownik pozostawał niezalogowany
 * i panel Pterodactyla i tak żądał osobnego logowania.
 * Zastąpione przekierowaniem OIDC - patrz backend/lib/oidc.php i auth/callback.php.
 *
 * Rejestracja żyje w REST-owym API w /api/auth/ (kreator 3-krokowy):
 *   POST /api/auth/send-verification   - krok 1: wysyłka kodu
 *   POST /api/auth/verify-code         - krok 2: weryfikacja kodu
 *   POST /api/auth/register            - krok 3: założenie konta
 *   POST /api/auth/check-availability  - podpowiedzi inline w kroku 1
 *
 * Widoki (register.php) robią require_once tego pliku wyłącznie po to, żeby
 * dostać csrf_token() - routing poniżej uruchamia się tylko wtedy, gdy plik
 * został wywołany bezpośrednio.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';

const LOGIN_PAGE_URL    = '/login';
const REGISTER_PAGE_URL = '/register';

// ============================================================
// ROUTING - tylko przy bezpośrednim wywołaniu pliku
// ============================================================
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'login') {
        // Stare formularze mogą jeszcze tu POST-ować - kierujemy na OIDC.
        header('Location: ' . LOGIN_PAGE_URL);
        exit();
    }

    if ($action === 'register') {
        header('Location: ' . REGISTER_PAGE_URL);
        exit();
    }

    header('Location: /');
    exit();
}
