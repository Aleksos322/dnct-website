<?php
/**
 * backend/lib/user_profiles.php
 *
 * Tabela user_profiles - łącznik między tożsamością Authentika a kontami
 * w Pterodactylu i Jellyfinie, plus dane profilowe klienta. Patrz nagłówek
 * migracji `backend/migrations/005_konta_klienta.sql` po uzasadnienie,
 * dlaczego kluczem jest authentik_username, nie authentik_sub.
 *
 * Wiersz powstaje w `user_profile_create()`, wołane RAZ z rejestracji
 * (api/auth/register.php). `user_profile_ensure()` to fallback dla kont
 * założonych przed wdrożeniem tego systemu - żeby account.dnct.pl nie
 * wywaliło się na starym koncie bez wiersza.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Pola profilowe edytowalne przez klienta - jedyna lista dozwolona w update_fields(). */
const USER_PROFILE_EDITABLE_FIELDS = ['birth_date', 'phone', 'minecraft_nick', 'discord_username', 'steam_id'];

function user_profile_by_username(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM user_profiles WHERE authentik_username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * Zakłada wiersz profilu. Wołane raz, z rejestracji - w tym momencie
 * Pterodactyl i Jellyfin są już (albo nie są, jeśli JIT zawiodło) założone,
 * więc od razu zapisujemy oba linki.
 */
function user_profile_create(PDO $pdo, array $fields): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO user_profiles
            (authentik_username, authentik_email, pterodactyl_user_id,
             jellyfin_user_id, jellyfin_linked_at, jellyfin_link_source)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $fields['authentik_username'],
        $fields['authentik_email'] ?? null,
        $fields['pterodactyl_user_id'] ?? null,
        $fields['jellyfin_user_id'] ?? null,
        $fields['jellyfin_linked_at'] ?? null,
        $fields['jellyfin_link_source'] ?? null,
    ]);

    return user_profile_by_username($pdo, $fields['authentik_username']);
}

/**
 * Find-or-create - fallback dla kont sprzed tego systemu (zalogowanych
 * przed rejestracją, która teraz zakłada wiersz sama). NIE tworzy kont
 * w Pterodactylu/Jellyfinie - tylko pusty wiersz-łącznik do wypełnienia
 * później przez ekran "połącz konta".
 */
function user_profile_ensure(PDO $pdo, string $username, ?string $email): array
{
    $existing = user_profile_by_username($pdo, $username);
    if ($existing !== null) {
        return $existing;
    }

    return user_profile_create($pdo, [
        'authentik_username' => $username,
        'authentik_email'    => $email,
    ]);
}

/** Zapisuje dane profilowe (urodziny, telefon, Minecraft, Discord, Steam). */
function user_profile_update_fields(PDO $pdo, string $username, array $fields): void
{
    $set = [];
    $values = [];

    foreach (USER_PROFILE_EDITABLE_FIELDS as $key) {
        if (array_key_exists($key, $fields)) {
            $set[] = "{$key} = ?";
            $values[] = $fields[$key] !== '' ? $fields[$key] : null;
        }
    }

    if ($set === []) {
        return;
    }

    $values[] = $username;
    $pdo->prepare('UPDATE user_profiles SET ' . implode(', ', $set) . ' WHERE authentik_username = ?')
        ->execute($values);
}

function user_profile_set_jellyfin_link(PDO $pdo, string $username, string $jellyfinUserId, string $source): void
{
    $pdo->prepare(
        'UPDATE user_profiles
            SET jellyfin_user_id = ?, jellyfin_linked_at = NOW(), jellyfin_link_source = ?
          WHERE authentik_username = ?'
    )->execute([$jellyfinUserId, $source, $username]);
}

/** Zeruje link do Jellyfina - np. przed podmianą na inne konto przy ręcznym linkowaniu. */
function user_profile_clear_jellyfin_link(PDO $pdo, string $username): void
{
    $pdo->prepare(
        'UPDATE user_profiles
            SET jellyfin_user_id = NULL, jellyfin_linked_at = NULL, jellyfin_link_source = NULL
          WHERE authentik_username = ?'
    )->execute([$username]);
}

function user_profile_set_pterodactyl_id(PDO $pdo, string $username, int $pterodactylUserId): void
{
    $pdo->prepare('UPDATE user_profiles SET pterodactyl_user_id = ? WHERE authentik_username = ?')
        ->execute([$pterodactylUserId, $username]);
}
