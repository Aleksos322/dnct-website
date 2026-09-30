<?php
/**
 * Lekki odczyt stanu bieżącej sesji dla synchronizacji kart dnct.pl i panelu.
 *
 * Dane identyfikujące użytkownika są udostępniane przez CORS wyłącznie panelowi.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$isPanelOrigin = $origin === 'https://panel.dnct.pl';

if ($isPanelOrigin) {
    header('Access-Control-Allow-Origin: https://panel.dnct.pl');
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, X-Requested-With');
    http_response_code(204);
    exit;
}

$response = [
    'authenticated' => isset($_SESSION['authentik_username']),
];

if ($isPanelOrigin && $response['authenticated']) {
    $response['email'] = (string)($_SESSION['authentik_email'] ?? '');
}

echo json_encode($response, JSON_UNESCAPED_SLASHES);
