<?php

declare(strict_types=1);

namespace DxFondito\Templates;

/**
 * The texts of the fields of a certificate (FR-CER-2): the call sign and the certificate date.
 */
final class Certificate
{
    private const MONTHS = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

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
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return sprintf('%d de %s de %d', $day, self::MONTHS[$month - 1], $year);
    }
}
