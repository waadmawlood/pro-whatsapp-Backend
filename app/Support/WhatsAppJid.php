<?php

namespace App\Support;

use App\Enums\CustomerChatType;

class WhatsAppJid
{
    public static function chatTypeFromJid(?string $jid): CustomerChatType
    {
        if ($jid && self::isGroupJid($jid)) {
            return CustomerChatType::Group;
        }

        return CustomerChatType::Direct;
    }

    public static function isGroupJid(?string $jid): bool
    {
        return is_string($jid) && str_ends_with($jid, '@g.us');
    }

    public static function isSupportedChatJid(?string $jid): bool
    {
        if (! is_string($jid) || $jid === '') {
            return false;
        }

        return str_ends_with($jid, '@s.whatsapp.net')
            || str_ends_with($jid, '@lid')
            || str_ends_with($jid, '@g.us');
    }

    public static function groupJidFromNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return $digits.'@g.us';
    }

    /**
     * Guess a WhatsApp JID when only the numeric part was stored (legacy inbound parsing).
     */
    public static function inferFromStoredNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 15) {
            return $digits.'@g.us';
        }

        if (self::isLikelyLid($digits)) {
            return $digits.'@lid';
        }

        return null;
    }

    public static function isLikelyLid(string $digits): bool
    {
        if (strlen($digits) < 12 || strlen($digits) > 15) {
            return false;
        }

        // 969 is not a valid ITU country code; inbound LIDs are often stored as 969… digits.
        if (str_starts_with($digits, '969')) {
            return true;
        }

        return false;
    }

    public static function digitsFromJid(?string $jid): ?string
    {
        if (! $jid || ! str_contains($jid, '@')) {
            return null;
        }

        $user = explode('@', $jid, 2)[0] ?? '';

        return preg_replace('/\D/', '', explode(':', $user)[0] ?? '') ?: null;
    }
}
