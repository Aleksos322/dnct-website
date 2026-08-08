<?php
/**
 * Przerzutka do panelu po zalogowaniu.
 *
 * Istnieje, bo adres powrotu z logowania jest celowo ograniczony do ścieżek
 * lokalnych (patrz oidc_safe_return_to() - inaczej dałoby się użyć naszego
 * logowania jako trampoliny na obcą stronę). Ta strona jest lokalna, więc
 * przechodzi walidację, a dopiero stąd kierujemy do panelu.
 *
 * Używa jej przycisk demo, ale nadaje się do każdego "zaloguj i wpuść do panelu".
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';

if (!isset($_SESSION['authentik_username'])) {
    header('Location: /login?return_to=' . rawurlencode('/panel'));
    exit();
}

header('Location: ' . env('PANEL_SSO_URL', 'https://panel.dnct.pl/extensions/vikingsso/auth/auto'));
exit();
