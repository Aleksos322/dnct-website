<?php
/**
 * Publiczny kanał zgłaszania potencjalnie nielegalnych treści.
 *
 * Nie wymaga konta DNCT. Zgłoszenie trafia do zespołu Abuse przez skrzynkę
 * support@dnct.pl, a nadawca otrzymuje potwierdzenie, gdy poda adres e-mail.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/mail/mail.php';

require_method('POST');
csrf_guard();
rate_limit_guard('abuse_report', [5, 3600], 'Zbyt wiele zgłoszeń z tego adresu. Spróbuj ponownie za godzinę.');

$categories = [
    'copyright'    => 'Naruszenie praw autorskich lub znaku towarowego',
    'fraud'        => 'Oszustwo, phishing lub podszywanie się',
    'malware'      => 'Złośliwe oprogramowanie lub atak',
    'child_safety' => 'Bezpieczeństwo dzieci',
    'violence_hate'=> 'Przemoc, groźby lub mowa nienawiści',
    'privacy'      => 'Naruszenie prywatności lub danych osobowych',
    'other'        => 'Inne potencjalne naruszenie prawa',
];

$location   = request_string('location');
$category   = request_string('category');
$explanation = request_string('explanation');
$legalBasis = request_string('legal_basis');
$name       = request_string('reporter_name');
$email      = request_string('reporter_email');
$goodFaith  = request_bool('good_faith');
$errors     = [];

if ($location === '' || mb_strlen($location) > 1000) {
    $errors['location'] = 'Podaj dokładny adres lub identyfikator usługi (maks. 1000 znaków).';
}
if (!array_key_exists($category, $categories)) {
    $errors['category'] = 'Wybierz kategorię zgłoszenia.';
}
if (mb_strlen($explanation) < 20 || mb_strlen($explanation) > 8000) {
    $errors['explanation'] = 'Opisz zgłoszenie w 20–8000 znakach.';
}
if (mb_strlen($legalBasis) < 5 || mb_strlen($legalBasis) > 2000) {
    $errors['legal_basis'] = 'Wskaż podstawę lub powód, dla którego treść może być nielegalna.';
}
if (!$goodFaith) {
    $errors['good_faith'] = 'Potwierdź, że działasz w dobrej wierze.';
}

// DSA dopuszcza anonimowe zawiadomienie dotyczące materiałów przedstawiających
// seksualne wykorzystywanie dzieci. W pozostałych sprawach potrzebujemy kanału
// kontaktowego, aby przekazać decyzję i umożliwić doprecyzowanie zgłoszenia.
if ($category !== 'child_safety') {
    if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
        $errors['reporter_name'] = 'Podaj imię i nazwisko albo nazwę podmiotu.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
        $errors['reporter_email'] = 'Podaj prawidłowy adres e-mail.';
    }
} elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254)) {
    $errors['reporter_email'] = 'Podaj prawidłowy adres e-mail albo pozostaw pole puste.';
}

if ($errors !== []) {
    json_error('Popraw zaznaczone pola.', 422, $errors);
}

$reportId = 'ABU-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$submittedAt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')))->format('Y-m-d H:i:s T');

$text = "Nowe zgłoszenie nielegalnej treści: {$reportId}\n\n"
    . "Data: {$submittedAt}\n"
    . "Kategoria: {$categories[$category]}\n"
    . "Lokalizacja: {$location}\n\n"
    . "Opis:\n{$explanation}\n\n"
    . "Podstawa/powód:\n{$legalBasis}\n\n"
    . "Zgłaszający: " . ($name !== '' ? $name : '(nie podano)') . "\n"
    . "E-mail: " . ($email !== '' ? $email : '(nie podano)') . "\n"
    . "Oświadczenie dobrej wiary: tak\n";

$html = '<h2>Nowe zgłoszenie nielegalnej treści: ' . $h($reportId) . '</h2>'
    . '<p><strong>Data:</strong> ' . $h($submittedAt) . '<br>'
    . '<strong>Kategoria:</strong> ' . $h($categories[$category]) . '<br>'
    . '<strong>Lokalizacja:</strong> ' . nl2br($h($location)) . '</p>'
    . '<h3>Opis</h3><p>' . nl2br($h($explanation)) . '</p>'
    . '<h3>Podstawa lub powód</h3><p>' . nl2br($h($legalBasis)) . '</p>'
    . '<p><strong>Zgłaszający:</strong> ' . $h($name !== '' ? $name : '(nie podano)') . '<br>'
    . '<strong>E-mail:</strong> ' . $h($email !== '' ? $email : '(nie podano)') . '<br>'
    . '<strong>Oświadczenie dobrej wiary:</strong> tak</p>';

$delivery = send_mail('support@dnct.pl', "[Abuse {$reportId}] {$categories[$category]}", $html, $text, 'info');
if (!$delivery['ok']) {
    error_log("Abuse {$reportId}: nie udało się doręczyć zgłoszenia do zespołu Abuse.");
    json_error('Nie udało się przyjąć zgłoszenia. Spróbuj ponownie później lub napisz na support@dnct.pl.', 503);
}

if ($email !== '') {
    $confirmationText = "Potwierdzamy otrzymanie zgłoszenia {$reportId}.\n\n"
        . "Sprawę oceni zespół DNCT Hosting. Możemy skontaktować się z Tobą, jeśli będzie potrzebne doprecyzowanie. "
        . "Odpowiedź otrzymasz na ten adres e-mail.\n\nNumer sprawy: {$reportId}";
    $confirmationHtml = '<p>Potwierdzamy otrzymanie zgłoszenia <strong>' . $h($reportId) . '</strong>.</p>'
        . '<p>Sprawę oceni zespół DNCT Hosting. Możemy skontaktować się z Tobą, jeśli będzie potrzebne doprecyzowanie. '
        . 'Odpowiedź otrzymasz na ten adres e-mail.</p><p><strong>Numer sprawy:</strong> ' . $h($reportId) . '</p>';
    $confirmation = send_mail($email, "Potwierdzenie zgłoszenia {$reportId} - DNCT Hosting", $confirmationHtml, $confirmationText, 'info');
    if (!$confirmation['ok']) {
        error_log("Abuse {$reportId}: zgłoszenie przyjęte, ale potwierdzenie dla zgłaszającego nie zostało wysłane.");
    }
}

json_ok([
    'message' => 'Zgłoszenie zostało przyjęte. ' . ($email !== '' ? 'Potwierdzenie wysłaliśmy e-mailem.' : 'Nie podano e-maila, więc nie możemy wysłać potwierdzenia.'),
    'report_id' => $reportId,
]);
