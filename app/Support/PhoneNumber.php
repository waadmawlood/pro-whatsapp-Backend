<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return $digits === '' ? null : $digits;
    }
}
