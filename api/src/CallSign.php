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

    /**
     * The base call sign (system design, section 4.1): the longest part between the `/` characters.
     * If two parts have the same length, the first part. LU1ABC/P and CX/LU1ABC give LU1ABC.
     */
    public static function base(string $callSign): string
    {
        $base = '';
        foreach (explode('/', self::normalize($callSign)) as $part) {
            if (strlen($part) > strlen($base)) {
                $base = $part;
            }
        }

        return $base;
    }
}
