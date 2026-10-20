<?php
declare(strict_types=1);
require_once __DIR__.'/messaging_security.php';

/**
 * Niezależne prymitywy cyklu życia wiadomości supportu i prywatnych mediów.
 *
 * Ten plik nie autoryzuje dostępu do ticketu. Endpoint musi najpierw sprawdzić,
 * czy bieżący użytkownik może czytać lub zmieniać daną rozmowę, a dopiero potem
 * wywołać poniższe funkcje. Załączniki są trzymane pod backend/storage/, którego
 * backend/.htaccess nie wystawia przez HTTP; pobranie ma iść wyłącznie przez
 * endpoint używający support_attachment_stream_path().
 */

const SUPPORT_MESSAGE_MEDIA_MAX_FILES = 4;
const SUPPORT_MESSAGE_IMAGE_MAX_BYTES = 10 * 1024 * 1024;
const SUPPORT_MESSAGE_VIDEO_MAX_BYTES = 50 * 1024 * 1024;
const SUPPORT_MESSAGE_FEATURES_PAGE_MAX = 100;
const SUPPORT_MESSAGE_WITHDRAWN_TEXT = 'Wiadomość została cofnięta.';

/**
 * Buduje stabilny, nieodwracalny klucz widza klienta.
 *
 * $customerIdentity powinno być identyfikatorem stabilnym w obrębie ticketu,
 * np. "account:<nazwa>" dla zalogowanego klienta lub "guest:<access-hash>".
 */
function support_message_viewer_key_customer(string $customerIdentity): string
{
    return support_message_viewer_key('customer', $customerIdentity);
}

/** Buduje stabilny, nieodwracalny klucz widza administratora. */
function support_message_viewer_key_admin(string $adminUsername): string
{
    return support_message_viewer_key('admin', $adminUsername);
}

/**
 * @throws InvalidArgumentException Gdy rola albo identyfikator są nieprawidłowe.
 */
function support_message_viewer_key(string $role, string $identity): string
{
    $role = support_message_participant_role($role);
    $identity = trim($identity);
    if ($identity === '') {
        throw new InvalidArgumentException('Brakuje identyfikatora uczestnika rozmowy.');
    }

    $normalizedIdentity = function_exists('mb_strtolower')
        ? mb_strtolower($identity, 'UTF-8')
        : strtolower($identity);

    return hash('sha256', "dnct-support-message-viewer-v1\0{$role}\0{$normalizedIdentity}");
}

/**
 * Zapisuje „cofnięcie dla mnie”. Nie usuwa wiadomości ani jej załączników.
 *
 * @throws InvalidArgumentException Gdy identyfikator wiadomości lub widza jest błędny.
 */
function support_message_hide_for_viewer(
    PDO $db,
    int $messageId,
    string $viewerRole,
    string $viewerKey
): void {
    if ($messageId < 1) {
        throw new InvalidArgumentException('Nieprawidłowy identyfikator wiadomości.');
    }

    $viewerRole = support_message_participant_role($viewerRole);
    support_message_assert_viewer_key($viewerKey);

    $statement = $db->prepare(
        'INSERT INTO support_message_hides(message_id, participant_role, participant_key)
         VALUES(:message, :role, :viewer)
         ON DUPLICATE KEY UPDATE hidden_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        ':message' => $messageId,
        ':role' => $viewerRole,
        ':viewer' => $viewerKey,
    ]);
}

/** Przywraca wiadomość ukrytą wcześniej tylko dla danego uczestnika. */
function support_message_restore_for_viewer(
    PDO $db,
    int $messageId,
    string $viewerRole,
    string $viewerKey
): void {
    if ($messageId < 1) {
        throw new InvalidArgumentException('Nieprawidłowy identyfikator wiadomości.');
    }

    $viewerRole = support_message_participant_role($viewerRole);
    support_message_assert_viewer_key($viewerKey);

    $statement = $db->prepare(
        'DELETE FROM support_message_hides
         WHERE message_id = :message AND participant_role = :role AND participant_key = :viewer'
    );
    $statement->execute([
        ':message' => $messageId,
        ':role' => $viewerRole,
        ':viewer' => $viewerKey,
    ]);
}

/**
 * Zwraca stronę rozmowy bez wiadomości ukrytych przez tego konkretnego widza.
 * Treść wycofanych wiadomości, cytatów i załączniki nigdy nie zawiera oryginału.
 *
 * $mediaUrlBase to autoryzowany adres endpointu pobierającego załącznik,
 * np. "/api/support/media.php". Funkcja doda parametr attachment_id.
 *
 * @return array{
 *   messages:list<array<string,mixed>>,
 *   has_more:bool,
 *   next_before_id:?int
 * }
 */
function support_messages_for_viewer(
    PDO $db,
    int $ticketId,
    string $viewerRole,
    string $viewerKey,
    string $mediaUrlBase,
    int $beforeId = 0,
    int $limit = 50
): array {
    if ($ticketId < 1) {
        throw new InvalidArgumentException('Nieprawidłowy identyfikator ticketu.');
    }

    $viewerRole = support_message_participant_role($viewerRole);
    support_message_assert_viewer_key($viewerKey);
    $limit = max(1, min(SUPPORT_MESSAGE_FEATURES_PAGE_MAX, $limit));

    $sql = 'SELECT
            m.id, m.ticket_id, m.author_role, m.author_name, m.body,
            m.client_message_key, m.reply_to_message_id, m.edited_at,
            m.withdrawn_at, m.withdrawn_by_role, m.created_at, UNIX_TIMESTAMP(m.created_at)+300 AS edit_expires_at,
            parent.id AS reply_id,
            parent.author_role AS reply_author_role,
            parent.author_name AS reply_author_name,
            parent.body AS reply_body,
            parent.withdrawn_at AS reply_withdrawn_at,
            parent_hide.message_id AS reply_hidden_message_id
        FROM support_messages AS m
        LEFT JOIN support_message_hides AS own_hide
            ON own_hide.message_id = m.id
           AND own_hide.participant_role = :own_viewer_role
           AND own_hide.participant_key = :own_viewer_key
        LEFT JOIN support_messages AS parent
            ON parent.id = m.reply_to_message_id
        LEFT JOIN support_message_hides AS parent_hide
            ON parent_hide.message_id = parent.id
           AND parent_hide.participant_role = :parent_viewer_role
           AND parent_hide.participant_key = :parent_viewer_key
        WHERE m.ticket_id = :ticket
          AND own_hide.message_id IS NULL';

    if ($beforeId > 0) {
        $sql .= ' AND m.id < :before_id';
    }

    // $limit jest zaciśnięty powyżej, więc nie interpoluje danych użytkownika.
    $sql .= ' ORDER BY m.id DESC LIMIT ' . ($limit + 1);

    $statement = $db->prepare($sql);
    $statement->bindValue(':own_viewer_role', $viewerRole, PDO::PARAM_STR);
    $statement->bindValue(':own_viewer_key', $viewerKey, PDO::PARAM_STR);
    $statement->bindValue(':parent_viewer_role', $viewerRole, PDO::PARAM_STR);
    $statement->bindValue(':parent_viewer_key', $viewerKey, PDO::PARAM_STR);
    $statement->bindValue(':ticket', $ticketId, PDO::PARAM_INT);
    if ($beforeId > 0) {
        $statement->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
    }
    $statement->execute();

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        array_pop($rows);
    }
    $rows = array_reverse($rows);

    /** @var array<int,list<array<string,mixed>>> $attachmentsByMessage */
    $attachmentsByMessage = [];
    $visibleMessageIds = [];
    foreach ($rows as $row) {
        if (($row['withdrawn_at'] ?? null) === null) {
            $visibleMessageIds[] = (int) $row['id'];
        }
    }
    foreach (support_message_attachment_rows_for_messages($db, $visibleMessageIds, $mediaUrlBase) as $attachment) {
        $attachmentsByMessage[(int) $attachment['message_id']][] = $attachment;
    }

    $messages = [];
    foreach ($rows as $row) {
        $withdrawn = ($row['withdrawn_at'] ?? null) !== null;
        $messageId = (int) $row['id'];
        $messages[] = [
            'id' => $messageId,
            'ticket_id' => (int) $row['ticket_id'],
            'author_role' => (string) $row['author_role'],
            'author_name' => (string) $row['author_name'],
            // Nie zwracamy oryginału po cofnięciu dla wszystkich.
            'body' => $withdrawn ? SUPPORT_MESSAGE_WITHDRAWN_TEXT : (string) $row['body'],
            'display_body' => $withdrawn ? SUPPORT_MESSAGE_WITHDRAWN_TEXT : (string) $row['body'],
            'client_message_key' => $row['client_message_key'] !== null
                ? (string) $row['client_message_key']
                : null,
            'reply_to_message_id' => $row['reply_to_message_id'] !== null
                ? (int) $row['reply_to_message_id']
                : null,
            'reply' => support_message_reply_metadata($row),
            'edit_expires_at' => (int)$row['edit_expires_at'],
            'edited_at' => $row['edited_at'] !== null ? (string) $row['edited_at'] : null,
            'withdrawn_at' => $row['withdrawn_at'] !== null ? (string) $row['withdrawn_at'] : null,
            'withdrawn_by_role' => $row['withdrawn_by_role'] !== null
                ? (string) $row['withdrawn_by_role']
                : null,
            'is_withdrawn' => $withdrawn,
            'attachments' => $withdrawn ? [] : ($attachmentsByMessage[$messageId] ?? []),
            'created_at' => (string) $row['created_at'],
        ];
    }

    $oldestId = $messages[0]['id'] ?? null;
    return [
        'messages' => $messages,
        'has_more' => $hasMore,
        'next_before_id' => $hasMore && is_int($oldestId) ? $oldestId : null,
    ];
}

/**
 * Waliduje i zapisuje maksymalnie cztery przesłane pliki dla jednej wiadomości.
 *
 * Przyjmuje standardową strukturę $_FILES['attachments'] albo listę pojedynczych
 * wpisów z kluczami name, tmp_name, error i size. Endpoint powinien wywołać tę
 * funkcję w tej samej transakcji co utworzenie wiadomości.
 *
 * @return list<array<string,mixed>> Publiczne metadane bez storage_key.
 *
 * @throws InvalidArgumentException Gdy upload nie spełnia polityki mediów.
 * @throws RuntimeException Gdy prywatny zapis na dysku się nie powiedzie.
 */
function support_store_message_attachments(
    PDO $db,
    int $messageId,
    array $uploadedFiles,
    string $mediaUrlBase = ''
): array {
    if ($messageId < 1) {
        throw new InvalidArgumentException('Nieprawidłowy identyfikator wiadomości.');
    }

    $files = support_message_validate_uploaded_media($uploadedFiles);
    if ($files === []) {
        return [];
    }

    $ownsTransaction = !$db->inTransaction();
    $savepoint = 'support_message_media_' . bin2hex(random_bytes(6));
    $storedPaths = [];

    try {
        if ($ownsTransaction) {
            $db->beginTransaction();
        } else {
            $db->exec('SAVEPOINT ' . $savepoint);
        }

        // Blokada wiersza wiadomości serializuje równoległe uploady i limit 4 plików.
        $message = $db->prepare('SELECT id FROM support_messages WHERE id = :message FOR UPDATE');
        $message->execute([':message' => $messageId]);
        if (!$message->fetchColumn()) {
            throw new OutOfBoundsException('Nie znaleziono wiadomości dla załączników.');
        }

        $count = $db->prepare('SELECT COUNT(*) FROM support_message_attachments WHERE message_id = :message');
        $count->execute([':message' => $messageId]);
        if ((int) $count->fetchColumn() + count($files) > SUPPORT_MESSAGE_MEDIA_MAX_FILES) {
            throw new InvalidArgumentException('Do jednej wiadomości można dodać maksymalnie 4 pliki.');
        }

        msg_upload_charge($db,$files,'support');
        $root = support_message_media_storage_root();
        support_message_ensure_media_directory($root);

        $insert = $db->prepare(
            'INSERT INTO support_message_attachments
                (message_id, storage_key, original_name, mime_type, media_type, byte_size)
             VALUES
                (:message, :storage, :name, :mime, :type, :size)'
        );
        $attachmentIds = [];

        foreach ($files as $file) {
            $storageKey = bin2hex(random_bytes(32));
            $destination = support_message_attachment_disk_path($storageKey);
            if ($destination === null) {
                throw new RuntimeException('Nie udało się przygotować bezpiecznej ścieżki pliku.');
            }

            support_message_ensure_media_directory(dirname($destination));
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('Nie udało się zapisać przesłanego pliku.');
            }
            @chmod($destination, 0640);
            $storedPaths[] = $destination;

            $insert->execute([
                ':message' => $messageId,
                ':storage' => $storageKey,
                ':name' => $file['original_name'],
                ':mime' => $file['mime_type'],
                ':type' => $file['kind'],
                ':size' => $file['byte_size'],
            ]);
            $attachmentIds[] = (int) $db->lastInsertId();
            msg_media_register($db,'support',$storageKey,$file['byte_size']);
        }

        if ($ownsTransaction) {
            $db->commit();
        } else {
            $db->exec('RELEASE SAVEPOINT ' . $savepoint);
        }

        return support_message_attachment_rows($db, $attachmentIds, $mediaUrlBase);
    } catch (Throwable $exception) {
        foreach ($storedPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        } elseif (!$ownsTransaction && $db->inTransaction()) {
            $db->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        }

        throw $exception;
    }
}

/**
 * Zwraca wyłącznie sprawdzoną ścieżkę do strumieniowania przez autoryzowany
 * endpoint. Nie zwraca storage_key i nie wykonuje kontroli dostępu do ticketu.
 *
 * @return array{
 *   path:string,
 *   attachment_id:int,
 *   message_id:int,
 *   original_name:string,
 *   mime_type:string,
 *   media_type:string,
 *   byte_size:int
 * }|null
 */
function support_attachment_stream_path(PDO $db, int $attachmentId): ?array
{
    msg_security_bind($db);
    if ($attachmentId < 1) {
        return null;
    }

    $statement = $db->prepare(
        'SELECT id, message_id, storage_key, original_name, mime_type, media_type, byte_size
         FROM support_message_attachments WHERE id = :id'
    );
    $statement->execute([':id' => $attachmentId]);
    $attachment = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($attachment)) {
        return null;
    }

    $path = support_message_attachment_disk_path((string) $attachment['storage_key']);
    if ($path === null || !is_file($path)) {
        return null;
    }

    $root = realpath(support_message_media_storage_root());
    $realPath = realpath($path);
    if ($root === false || $realPath === false || !str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return [
        'path' => $realPath,
        'attachment_id' => (int) $attachment['id'],
        'message_id' => (int) $attachment['message_id'],
        'original_name' => (string) $attachment['original_name'],
        'mime_type' => (string) $attachment['mime_type'],
        'media_type' => (string) $attachment['media_type'],
        'byte_size' => (int) $attachment['byte_size'],
    ];
}

/**
 * @return list<array{
 *   tmp_name:string,
 *   original_name:string,
 *   mime_type:string,
 *   kind:string,
 *   byte_size:int
 * }>
 */
function support_message_validate_uploaded_media(array $uploadedFiles): array
{
    $normalized = support_message_normalize_uploaded_media($uploadedFiles);
    if (count($normalized) > SUPPORT_MESSAGE_MEDIA_MAX_FILES) {
        throw new InvalidArgumentException('Można przesłać maksymalnie 4 pliki naraz.');
    }

    $allowed = support_message_allowed_media_types();
    $validated = [];

    foreach ($normalized as $file) {
        $error = (int) $file['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(support_message_upload_error_text($error));
        }

        $tmpName = (string) $file['tmp_name'];
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Przesłany plik nie został poprawnie odebrany.');
        }

        $byteSize = filesize($tmpName);
        if (!is_int($byteSize) || $byteSize < 1) {
            throw new InvalidArgumentException('Przesłany plik jest pusty lub nieczytelny.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);
        if (!is_string($mimeType) || !isset($allowed[$mimeType])) {
            throw new InvalidArgumentException('Dozwolone są wyłącznie obrazy JPEG, PNG, WEBP, GIF oraz filmy MP4, WEBM i MOV.');
        }

        if (!defined('WSPARCIE_ZRODLO') || WSPARCIE_ZRODLO === 'beta') {
            msg_validate_media_file($tmpName, (string)$file['name'], $byteSize);
        }
        $definition = $allowed[$mimeType];
        if ($byteSize > $definition['max_bytes']) {
            $kindLabel = $definition['kind'] === 'image' ? 'Obraz' : 'Film';
            $maxMegabytes = (int) ($definition['max_bytes'] / 1024 / 1024);
            throw new InvalidArgumentException("{$kindLabel} może mieć maksymalnie {$maxMegabytes} MB.");
        }

        $validated[] = [
            'tmp_name' => $tmpName,
            'original_name' => support_message_safe_original_name((string) $file['name']),
            'mime_type' => $mimeType,
            'kind' => $definition['kind'],
            'byte_size' => $byteSize,
        ];
    }

    return $validated;
}

/**
 * Tworzy bezpieczny adres publicznego endpointu, nigdy adres pliku na dysku.
 * Pusty base celowo daje null — przydatne dla kodu administracyjnego/audytu.
 */
function support_attachment_url(string $mediaUrlBase, int $attachmentId): ?string
{
    $mediaUrlBase = trim($mediaUrlBase);
    if ($mediaUrlBase === '' || $attachmentId < 1) {
        return null;
    }

    $separator = str_contains($mediaUrlBase, '?')
        ? ((str_ends_with($mediaUrlBase, '?') || str_ends_with($mediaUrlBase, '&')) ? '' : '&')
        : '?';

    return $mediaUrlBase . $separator . 'attachment_id=' . rawurlencode((string) $attachmentId);
}

/** @return array<string,array{kind:string,max_bytes:int}> */
function support_message_allowed_media_types(): array
{
    return [
        'image/jpeg' => ['kind' => 'image', 'max_bytes' => SUPPORT_MESSAGE_IMAGE_MAX_BYTES],
        'image/png' => ['kind' => 'image', 'max_bytes' => SUPPORT_MESSAGE_IMAGE_MAX_BYTES],
        'image/webp' => ['kind' => 'image', 'max_bytes' => SUPPORT_MESSAGE_IMAGE_MAX_BYTES],
        'image/gif' => ['kind' => 'image', 'max_bytes' => SUPPORT_MESSAGE_IMAGE_MAX_BYTES],
        'image/avif' => ['kind' => 'image', 'max_bytes' => SUPPORT_MESSAGE_IMAGE_MAX_BYTES],
        'video/mp4' => ['kind' => 'video', 'max_bytes' => SUPPORT_MESSAGE_VIDEO_MAX_BYTES],
        'video/webm' => ['kind' => 'video', 'max_bytes' => SUPPORT_MESSAGE_VIDEO_MAX_BYTES],
        'video/quicktime' => ['kind' => 'video', 'max_bytes' => SUPPORT_MESSAGE_VIDEO_MAX_BYTES],
    ];
}

/**
 * @return list<array{name:mixed,tmp_name:mixed,error:mixed,size:mixed}>
 *
 * Obsługuje zarówno standardowe $_FILES['attachments'], jak i już rozpakowaną
 * listę plików. Pola MIME od przeglądarki celowo są ignorowane.
 */
function support_message_normalize_uploaded_media(array $uploadedFiles): array
{
    if ($uploadedFiles === []) {
        return [];
    }

    if (array_key_exists('name', $uploadedFiles)) {
        $names = $uploadedFiles['name'];
        if (!is_array($names)) {
            return [[
                'name' => $names,
                'tmp_name' => $uploadedFiles['tmp_name'] ?? '',
                'error' => $uploadedFiles['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $uploadedFiles['size'] ?? 0,
            ]];
        }

        foreach (['tmp_name', 'error', 'size'] as $field) {
            if (!isset($uploadedFiles[$field]) || !is_array($uploadedFiles[$field])
                || count($uploadedFiles[$field]) !== count($names)) {
                throw new InvalidArgumentException('Nieprawidłowa struktura przesłanych plików.');
            }
        }

        $result = [];
        foreach (array_keys($names) as $index) {
            $result[] = [
                'name' => $names[$index],
                'tmp_name' => $uploadedFiles['tmp_name'][$index],
                'error' => $uploadedFiles['error'][$index],
                'size' => $uploadedFiles['size'][$index],
            ];
        }
        return $result;
    }

    $result = [];
    foreach ($uploadedFiles as $file) {
        if (!is_array($file)) {
            throw new InvalidArgumentException('Nieprawidłowa struktura przesłanych plików.');
        }
        $result[] = [
            'name' => $file['name'] ?? '',
            'tmp_name' => $file['tmp_name'] ?? '',
            'error' => $file['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $file['size'] ?? 0,
        ];
    }

    return $result;
}

/**
 * @return list<array{
 *   id:int,
 *   message_id:int,
 *   kind:string,
 *   media_type:string,
 *   mime_type:string,
 *   original_name:string,
 *   byte_size:int,
 *   created_at:string,
 *   url:?string
 * }>
 */
function support_message_attachment_rows(PDO $db, array $attachmentIds, string $mediaUrlBase): array
{
    $ids = support_message_unique_positive_ids($attachmentIds);
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $db->prepare(
        'SELECT id, message_id, original_name, mime_type, media_type, byte_size, created_at
         FROM support_message_attachments
         WHERE id IN (' . $placeholders . ')
         ORDER BY id ASC'
    );
    $statement->execute($ids);

    $attachments = [];
    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $attachments[] = support_message_attachment_public_payload($row, $mediaUrlBase);
    }

    return $attachments;
}

/**
 * @return list<array{
 *   id:int,
 *   message_id:int,
 *   kind:string,
 *   media_type:string,
 *   mime_type:string,
 *   original_name:string,
 *   byte_size:int,
 *   created_at:string,
 *   url:?string
 * }>
 */
function support_message_attachment_rows_for_messages(PDO $db, array $messageIds, string $mediaUrlBase): array
{
    $ids = support_message_unique_positive_ids($messageIds);
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $db->prepare(
        'SELECT id, message_id, original_name, mime_type, media_type, byte_size, created_at
         FROM support_message_attachments
         WHERE message_id IN (' . $placeholders . ')
         ORDER BY message_id ASC, id ASC'
    );
    $statement->execute($ids);

    $attachments = [];
    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $attachments[] = support_message_attachment_public_payload($row, $mediaUrlBase);
    }

    return $attachments;
}

/** @return array<string,mixed>|null */
function support_message_reply_metadata(array $row): ?array
{
    if (($row['reply_id'] ?? null) === null || ($row['reply_hidden_message_id'] ?? null) !== null) {
        return null;
    }

    $withdrawn = ($row['reply_withdrawn_at'] ?? null) !== null;
    return [
        'id' => (int) $row['reply_id'],
        'author_role' => (string) $row['reply_author_role'],
        'author_name' => (string) $row['reply_author_name'],
        'body' => $withdrawn ? SUPPORT_MESSAGE_WITHDRAWN_TEXT : (string) $row['reply_body'],
        'display_body' => $withdrawn ? SUPPORT_MESSAGE_WITHDRAWN_TEXT : (string) $row['reply_body'],
        'is_withdrawn' => $withdrawn,
    ];
}

/**
 * Dostarcza tylko publiczne metadane załącznika. storage_key nie opuszcza PHP.
 *
 * @return array{
 *   id:int,
 *   message_id:int,
 *   kind:string,
 *   media_type:string,
 *   mime_type:string,
 *   original_name:string,
 *   byte_size:int,
 *   created_at:string,
 *   url:?string
 * }
 */
function support_message_attachment_public_payload(array $row, string $mediaUrlBase): array
{
    $id = (int) $row['id'];
    $kind = (string) $row['media_type'];

    return [
        'id' => $id,
        'message_id' => (int) $row['message_id'],
        // kind jest krótszym kontraktem dla frontendu; media_type zostaje jawne.
        'kind' => $kind,
        'media_type' => $kind,
        'mime_type' => (string) $row['mime_type'],
        'original_name' => (string) $row['original_name'],
        'byte_size' => (int) $row['byte_size'],
        'created_at' => (string) $row['created_at'],
        'url' => support_attachment_url($mediaUrlBase, $id),
    ];
}

/** Zwraca katalog storage prywatnych mediów pod chronionym backend/. */
function support_message_media_storage_root(): string
{
    if ((!defined('WSPARCIE_ZRODLO') || WSPARCIE_ZRODLO === 'beta') && (!defined('SUPPORT_MESSAGE_MEDIA_STORAGE_ROOT') || str_contains(SUPPORT_MESSAGE_MEDIA_STORAGE_ROOT,'/WEB/beta.dnct.pl/backend/storage/'))) return msg_media_root('support');
    if (defined('SUPPORT_MESSAGE_MEDIA_STORAGE_ROOT')) {
        return rtrim((string) SUPPORT_MESSAGE_MEDIA_STORAGE_ROOT, DIRECTORY_SEPARATOR);
    }
    $backendPath = defined('DNCT_BACKEND_PATH') ? DNCT_BACKEND_PATH : dirname(__DIR__);
    return rtrim($backendPath, DIRECTORY_SEPARATOR) . '/storage/support-message-media';
}

/**
 * Buduje ścieżkę wyłącznie z losowego klucza o stałym formacie.
 * Zwraca null zamiast ryzykować użycie danych ścieżkowych pochodzących z bazy.
 */
function support_message_attachment_disk_path(string $storageKey): ?string
{
    if (preg_match('/\A[a-f0-9]{64}\z/D', $storageKey) !== 1) {
        return null;
    }

    return support_message_media_storage_root()
        . DIRECTORY_SEPARATOR
        . substr($storageKey, 0, 2)
        . DIRECTORY_SEPARATOR
        . $storageKey
        . '.blob';
}

/** @throws RuntimeException */
function support_message_ensure_media_directory(string $directory): void
{
    if (!is_dir($directory)) {
        if (!mkdir($directory, 02770, true) && !is_dir($directory)) throw new RuntimeException('Nie udało się utworzyć prywatnego katalogu mediów.');
        // Web requests and maintenance jobs share the www-data group.
        chmod($directory, 02770);
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Prywatny katalog mediów nie jest zapisywalny.');
    }
}

/** @throws InvalidArgumentException */
function support_message_participant_role(string $role): string
{
    $role = strtolower(trim($role));
    if (!in_array($role, ['customer', 'admin'], true)) {
        throw new InvalidArgumentException('Nieprawidłowa rola uczestnika rozmowy.');
    }
    return $role;
}

/** @throws InvalidArgumentException */
function support_message_assert_viewer_key(string $viewerKey): void
{
    if (preg_match('/\A[a-f0-9]{64}\z/D', $viewerKey) !== 1) {
        throw new InvalidArgumentException('Nieprawidłowy klucz widza rozmowy.');
    }
}

/** @return list<int> */
function support_message_unique_positive_ids(array $ids): array
{
    $result = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $result[$id] = $id;
        }
    }
    return array_values($result);
}

function support_message_safe_original_name(string $name): string
{
    $name = basename(str_replace('\\', '/', str_replace(["\0", "\r", "\n"], '', $name)));
    $name = trim($name);
    if ($name === '') {
        return 'plik';
    }

    return function_exists('mb_substr')
        ? mb_substr($name, 0, 180, 'UTF-8')
        : substr($name, 0, 180);
}

function support_message_upload_error_text(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Przesłany plik przekracza dopuszczalny rozmiar.',
        UPLOAD_ERR_PARTIAL => 'Przesłany plik dotarł tylko częściowo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Brakuje katalogu tymczasowego do obsługi pliku.',
        UPLOAD_ERR_CANT_WRITE => 'Nie udało się zapisać przesłanego pliku.',
        UPLOAD_ERR_EXTENSION => 'Rozszerzenie PHP zatrzymało przesyłanie pliku.',
        default => 'Nie udało się przesłać pliku.',
    };
}
