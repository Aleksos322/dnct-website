<?php
/**
 * backend/lib/db.php
 *
 * Jeśli masz już gdzieś w projekcie wspólne połączenie PDO (np. w bootstrap.php),
 * NIE dołączaj tego pliku — użyj istniejącego.
 *
 * Zakłada, że backend/lib/env.php udostępnia funkcję env(string $key, $default = null),
 * czytającą zmienne w stylu .env: MY_DB_HOST, MY_DB_DATABASE, MY_DB_USERNAME,
 * MY_DB_PASSWORD, MY_DB_PORT.
 */

require_once __DIR__ . '/env.php';

function dnct_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = env('MY_DB_HOST', '127.0.0.1');
        $port = env('MY_DB_PORT', '3306');
        $name = env('MY_DB_DATABASE');
        $user = env('MY_DB_USERNAME');
        $pass = env('MY_DB_PASSWORD');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}