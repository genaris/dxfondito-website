<?php

declare(strict_types=1);

namespace DxFondito\Logs;

/**
 * The text of a frequency in MHz (FR-LOG-7a).
 */
final class Frequency
{
    /** Kilohertz: the usual resolution of a frequency on a QSL card, such as 7.130 MHz. */
    private const MIN_DECIMALS = 3;

    /**
     * The frequency with three decimals or more, such as 7.130 for 7.13 or 7.130000.
     * The decimals after the third stay only if they are not zero, such as 7.1305.
     *
     * @param string $value A decimal number in MHz, such as the value of the DECIMAL column.
     */
    public static function text(string $value): string
    {
        [$whole, $decimals] = explode('.', $value . '.');
        $decimals = rtrim($decimals, '0');

        return $whole . '.' . str_pad($decimals, self::MIN_DECIMALS, '0');
    }
}
