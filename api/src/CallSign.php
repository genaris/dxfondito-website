<?php

declare(strict_types=1);

namespace DxFondito;

final class CallSign
{
    /**
     * Uppercase letters and no spaces (R-CALL-1).
     */
    public static function normalize(string $callSign): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $callSign));
    }

    /**
     * True for a normalized call sign of an account: 3 to 20 letters, digits or `/` characters.
     */
    public static function isValid(string $callSign): bool
    {
        return preg_match('#^[A-Z0-9/]{3,20}$#', $callSign) === 1;
    }
}
