<?php

declare(strict_types=1);

namespace DxFondito\Mail;

/**
 * Dates in Spanish words for the messages, such as "domingo 4 de octubre de 2026".
 */
final class SpanishDate
{
    private const MONTHS = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    private const WEEKDAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /**
     * @param string $date YYYY-MM-DD.
     */
    public static function long(string $date): string
    {
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return sprintf('%d de %s de %d', $day, self::MONTHS[$month - 1], $year);
    }

    /**
     * @param string $date YYYY-MM-DD.
     */
    public static function withWeekday(string $date): string
    {
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        $weekday = (int) gmdate('w', gmmktime(0, 0, 0, $month, $day, $year));

        return self::WEEKDAYS[$weekday] . ' ' . self::long($date);
    }

    /**
     * The dates of an activity: one day, or "del viernes 2 al domingo 4 de octubre de 2026".
     */
    public static function range(string $start, string $end): string
    {
        return $start === $end ? self::withWeekday($start) : 'del ' . self::withWeekday($start) . ' al ' . self::withWeekday($end);
    }
}
