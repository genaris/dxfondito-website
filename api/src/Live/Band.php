<?php

declare(strict_types=1);

namespace DxFondito\Live;

/**
 * The ADIF band of a frequency. `web/src/live.ts` has the same table, and the mode of the program for each band.
 */
final class Band
{
    /** @var list<array{0: float, 1: float, 2: string}> Lower and upper limit in MHz, and the band. */
    private const BANDS = [
        [1.8, 2.0, '160m'],
        [3.5, 4.0, '80m'],
        [5.06, 5.45, '60m'],
        [7.0, 7.3, '40m'],
        [10.1, 10.15, '30m'],
        [14.0, 14.35, '20m'],
        [18.068, 18.168, '17m'],
        [21.0, 21.45, '15m'],
        [24.89, 24.99, '12m'],
        [28.0, 29.7, '10m'],
        [50.0, 54.0, '6m'],
        [144.0, 148.0, '2m'],
        [222.0, 225.0, '1.25m'],
        [420.0, 450.0, '70cm'],
    ];

    /**
     * The band of a frequency in MHz, such as 40m for 7.130, or null out of the bands.
     */
    public static function of(string $frequency): ?string
    {
        $mhz = (float) $frequency;
        foreach (self::BANDS as [$lower, $upper, $band]) {
            if ($mhz >= $lower && $mhz <= $upper) {
                return $band;
            }
        }

        return null;
    }
}
