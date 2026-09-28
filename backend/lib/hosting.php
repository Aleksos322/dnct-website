<?php
/**
 * Konto hostingu WWW (panel nad HestiaCP) - odpowiednik pterodactyl_ensure_user().
 *
 * Jedno konto DNCT na wszystkie produkty: rejestracja i każde logowanie
 * zakładają (find-or-create po e-mailu) konto w panelu hostingu, tak samo
 * jak w panelu gier. Nowe konto dostaje pakiet bez zasobów - strony, poczta
 * i bazy odblokowują się dopiero po zakupie.
 *
 * Strona NIE ma klucza administratora Hestii. Woła jeden endpoint panelu
 * (/internal/konto), który przepuszcza tylko adres z listy i ten token,
 * i umie wyłącznie: załóż / znajdź / pokaż / zawieś / odwieś / zmień pakiet / usuń.
 *
 * Bez HOSTING_PANEL_URL w .env integracja jest wyłączona (produkcja, dopóki
 * panel hostingu stoi na maszynie testowej) - funkcje wracają null bez błędu.
 */

declare(strict_types=1);

/** Krótko: logowanie nie może czekać na panel hostingu. */
const HOSTING_TIMEOUT = 4;

function hosting_enabled(): bool
{
    return env('HOSTING_PANEL_URL', '') !== '' && env('HOSTING_INTERNAL_TOKEN', '') !== '';
}

/** Adres panelu hostingu dla klienta (przycisk "Otwórz panel hostingu"). */
function hosting_public_url(): string
{
    $url = env('HOSTING_PANEL_PUBLIC_URL', '');
    return $url !== '' ? $url : env('HOSTING_PANEL_URL', '');
}

/**
 * @return array<string,mixed>|null  odpowiedź panelu albo null przy błędzie
 */
function hosting_call(string $action, array $fields = []): ?array
{
    if (!hosting_enabled()) {
        return null;
    }

    $ch = curl_init(rtrim(env('HOSTING_PANEL_URL', ''), '/') . '/internal/konto');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['action' => $action] + $fields),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . env('HOSTING_INTERNAL_TOKEN', ''),
        ],
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => HOSTING_TIMEOUT,
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $data = is_string($raw) ? json_decode($raw, true) : null;
    if ($http !== 200 || !is_array($data) || empty($data['ok'])) {
        error_log('Hosting: ' . $action . ' nieudane (HTTP ' . $http . ($err !== '' ? ', ' . $err : '')
            . '): ' . (is_array($data) ? (string)($data['error'] ?? '') : substr((string)$raw, 0, 200)));
        return null;
    }
    return $data;
}

/**
 * Find-or-create konta hostingu po e-mailu. Istniejącego konta nie zmienia
 * (nie obniża płatnego pakietu).
 *
 * @return array{user:string,created:bool}|null
 */
function hosting_ensure_user(string $email, string $displayName = ''): ?array
{
    $res = hosting_call('ensure', ['email' => $email, 'name' => $displayName]);
    if ($res === null || empty($res['user'])) {
        return null;
    }
    if (!empty($res['created'])) {
        error_log('Hosting JIT: założono konto ' . $res['user'] . ' dla ' . $email);
    }
    return ['user' => (string)$res['user'], 'created' => !empty($res['created'])];
}

/**
 * Stan konta hostingu do kafelka na account.dnct.pl.
 *
 * @return array{user:string,package:string,paid:bool,suspended:bool,sites:int,disk_mb:int}|null
 */
function hosting_account_info(string $email): ?array
{
    $found = hosting_call('find', ['email' => $email]);
    if ($found === null || empty($found['user'])) {
        return null;
    }
    $info = hosting_call('info', ['user' => (string)$found['user']]);
    if ($info === null) {
        return null;
    }
    return [
        'user'      => (string)$info['user'],
        'package'   => (string)($info['package'] ?? ''),
        'paid'      => !empty($info['paid']),
        'suspended' => !empty($info['suspended']),
        'sites'     => (int)($info['sites'] ?? 0),
        'disk_mb'   => (int)($info['disk_mb'] ?? 0),
    ];
}
