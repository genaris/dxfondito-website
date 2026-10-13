<?php

declare(strict_types=1);

namespace DxFondito\Templates;

/**
 * The texts of the fields of a certificate (FR-CER-2): the call sign and the full name of the participant.
 */
final class Certificate
{
    /** The names of the certificate levels (R-CER-1). The browser has the same names (web/src/program.ts). */
    private const LEVEL_NAMES = [5 => 'Bronce', 10 => 'Plata', 15 => 'Oro'];

    /**
     * @param string $name The full name of the participant, or an empty text.
     * @return array<string, string>
     */
    public static function values(string $baseCallSign, string $name): array
    {
        return ['call_sign' => $baseCallSign, 'name' => $name];
    }

    /**
     * The example data of the sample image.
     *
     * @return array<string, string>
     */
    public static function sample(): array
    {
        return self::values('LU1ABC', 'Juana Pérez');
    }

    /**
     * The name of a level, such as "Bronce", or "20 puntos" for a level without a name.
     */
    public static function levelName(int $points): string
    {
        return self::LEVEL_NAMES[$points] ?? $points . ' puntos';
    }
}
