<?php
/**
 * Reguły walidacji wspólne dla całego procesu rejestracji.
 *
 * Te same reguły są zdublowane w register.js (dla natychmiastowego feedbacku),
 * ale to TA implementacja jest wiążąca - front może zostać ominięty.
 */

declare(strict_types=1);

const USERNAME_MIN_LENGTH = 3;
const USERNAME_MAX_LENGTH = 32;
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 200;
const NAME_MAX_LENGTH     = 60;
const EMAIL_MAX_LENGTH    = 190;

/** Zwraca komunikat błędu albo null, gdy wartość jest poprawna. */
function validate_username_value(string $username): ?string
{
    if ($username === '') {
        return 'Podaj nazwę użytkownika.';
    }
    if (!preg_match('/^[A-Za-z0-9]+$/', $username)) {
        return 'Nazwa użytkownika może zawierać wyłącznie litery i cyfry (A-Z, 0-9) - bez spacji i znaków specjalnych.';
    }
    $length = strlen($username);
    if ($length < USERNAME_MIN_LENGTH || $length > USERNAME_MAX_LENGTH) {
        return 'Nazwa użytkownika musi mieć od ' . USERNAME_MIN_LENGTH . ' do ' . USERNAME_MAX_LENGTH . ' znaków.';
    }
    return null;
}

function validate_email_value(string $email): ?string
{
    if ($email === '') {
        return 'Podaj adres e-mail.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > EMAIL_MAX_LENGTH) {
        return 'Podaj poprawny adres e-mail (np. twoj@email.pl).';
    }
    $domena = strtolower(substr(strrchr($email, '@'), 1));
    // Najczęstsze literówki - podpowiadamy, zanim kod pójdzie w próżnię
    // (proxmox.aosi@gmail.pl: domena nie istnieje, kod wracał jako niedoręczalny,
    // a klient czekał na maila, który nigdy nie przyjdzie).
    $literowki = ['gmail.pl' => 'gmail.com', 'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmail.co' => 'gmail.com',
                  'gamil.com' => 'gmail.com', 'gmail.con' => 'gmail.com', 'wp.com' => 'wp.pl', 'onet.com' => 'onet.pl',
                  'interia.com' => 'interia.pl', 'outlok.com' => 'outlook.com', 'hotmial.com' => 'hotmail.com'];
    if (isset($literowki[$domena])) {
        return 'Czy chodziło o @' . $literowki[$domena] . '? Domena @' . $domena . ' nie przyjmuje poczty.';
    }
    // Domena bez MX i bez adresu IP nie odbierze żadnego maila.
    if (!checkdnsrr($domena, 'MX') && !checkdnsrr($domena, 'A')) {
        return 'Domena @' . $domena . ' nie przyjmuje poczty. Sprawdź, czy nie ma literówki.';
    }
    return null;
}

function validate_password_value(string $password): ?string
{
    $missing = [];

    if (strlen($password) > PASSWORD_MAX_LENGTH) {
        return 'Hasło może mieć maksymalnie ' . PASSWORD_MAX_LENGTH . ' znaków.';
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $missing[] = 'min. ' . PASSWORD_MIN_LENGTH . ' znaków';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $missing[] = 'wielką literę';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $missing[] = 'cyfrę';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $missing[] = 'znak specjalny';
    }

    return $missing ? 'Hasło musi zawierać: ' . implode(', ', $missing) . '.' : null;
}

/** Imię / nazwisko - wymagane i o rozsądnej długości. */
function validate_person_name(string $value, string $emptyMessage): ?string
{
    if ($value === '') {
        return $emptyMessage;
    }
    if (mb_strlen($value, 'UTF-8') > NAME_MAX_LENGTH) {
        return 'Maksymalna długość to ' . NAME_MAX_LENGTH . ' znaków.';
    }
    return null;
}

/**
 * Komplet walidacji kroku 1 (dane identyfikacyjne).
 *
 * @param array<string,string> $data
 * @return array<string,string> mapa pole => komunikat błędu (pusta = OK)
 */
function validate_identity_step(array $data): array
{
    $errors = [];

    if (($error = validate_person_name($data['first_name'] ?? '', 'Podaj swoje imię.')) !== null) {
        $errors['first_name'] = $error;
    }
    if (($error = validate_person_name($data['last_name'] ?? '', 'Podaj swoje nazwisko.')) !== null) {
        $errors['last_name'] = $error;
    }
    if (($error = validate_username_value($data['username'] ?? '')) !== null) {
        $errors['username'] = $error;
    }
    if (($error = validate_email_value($data['email'] ?? '')) !== null) {
        $errors['email'] = $error;
    }

    return $errors;
}
