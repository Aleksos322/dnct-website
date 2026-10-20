<?php
declare(strict_types=1);

/** Accept exactly one emoji grapheme, including flags, modifiers and ZWJ families. */
function chat_valid_emoji(string $emoji): bool
{
    if ($emoji === '' || mb_strlen($emoji) > 32) return false;
    $part = '\p{Extended_Pictographic}\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?';
    return preg_match('/^(?:\p{Regional_Indicator}{2}|[0-9#*]\x{FE0F}?\x{20E3}|'.$part.'(?:\x{200D}'.$part.')*(?:[\x{E0020}-\x{E007E}]+\x{E007F})?)$/uD', $emoji) === 1;
}
