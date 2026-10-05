<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use DxFondito\Mail\SpanishDate;

/**
 * The texts of the fields of a certificate (FR-CER-2): the call sign and the certificate date.
 */
final class Certificate
{
    /** The names of the certificate levels (R-CER-1). The browser has the same names (web/src/program.ts). */
    private const LEVEL_NAMES = [5 => 'Bronce', 10 => 'Plata', 15 => 'Oro'];

    /**
     * @param string $date YYYY-MM-DD, the date of the certificate (R-CER-5).
     * @return array<string, string>
     */
    public static function values(string $baseCallSign, string $date): array
    {
        return ['call_sign' => $baseCallSign, 'date' => self::longDate($date)];
    }

    /**
     * The example data of the sample image.
     *
     * @return array<string, string>
     */
    public static function sample(): array
    {
        return self::values('LU1ABC', '2026-10-04');
    }

    /**
     * A date in Spanish words, such as "4 de octubre de 2026".
     */
    public static function longDate(string $date): string
    {
        return SpanishDate::long($date);
    }

    /**
     * The name of a level, such as "Bronce", or "20 puntos" for a level without a name.
     */
    public static function levelName(int $points): string
    {
        return self::LEVEL_NAMES[$points] ?? $points . ' puntos';
    }
}
