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
}
