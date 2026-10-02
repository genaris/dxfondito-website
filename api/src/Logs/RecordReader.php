<?php

declare(strict_types=1);

namespace DxFondito\Logs;

use DateTimeImmutable;
use DxFondito\CallSign;

/**
 * Makes a contact from an ADIF record, or gives the problems of the record (FR-LOG-6 to FR-LOG-9).
 */
final class RecordReader
{
    public const MISSING = 'missing';
    public const INVALID = 'invalid';

    // The sizes of the columns of the contacts table.
    private const MAX_NAME = 100;
    private const MAX_MODE = 20;
    private const MAX_RST = 10;

    /**
     * @param array<string, string> $fields
     * @return Contact|list<array{field: string, problem: string}> The contact, or the problems of the record.
     */
    public static function read(int $record, array $fields): Contact|array
    {
        $problems = [];
        $value = static fn (string $field): string => trim($fields[$field] ?? '');

        $callSign = CallSign::normalize($value('CALL'));
        if ($callSign === '') {
            $problems[] = ['field' => 'CALL', 'problem' => self::MISSING];
        } elseif (!CallSign::isValid($callSign) || CallSign::base($callSign) === '') {
            $problems[] = ['field' => 'CALL', 'problem' => self::INVALID];
        }

        $date = $value('QSO_DATE');
        $time = $value('TIME_ON');
        $qsoAt = null;
        if ($date === '') {
            $problems[] = ['field' => 'QSO_DATE', 'problem' => self::MISSING];
        } elseif (!self::isDate($date)) {
            $problems[] = ['field' => 'QSO_DATE', 'problem' => self::INVALID];
        }
        if ($time === '') {
            $problems[] = ['field' => 'TIME_ON', 'problem' => self::MISSING];
        } elseif (!self::isTime($time)) {
            $problems[] = ['field' => 'TIME_ON', 'problem' => self::INVALID];
        }
        if (self::isDate($date) && self::isTime($time)) {
            $qsoAt = sprintf(
                '%s-%s-%s %s:%s:%s',
                substr($date, 0, 4),
                substr($date, 4, 2),
                substr($date, 6, 2),
                substr($time, 0, 2),
                substr($time, 2, 2),
                strlen($time) === 6 ? substr($time, 4, 2) : '00',
            );
        }

        $mode = strtoupper($value('MODE'));
        if ($mode === '') {
            $problems[] = ['field' => 'MODE', 'problem' => self::MISSING];
        } elseif (preg_match('/^[A-Z0-9\-\/]{1,' . self::MAX_MODE . '}$/', $mode) !== 1) {
            $problems[] = ['field' => 'MODE', 'problem' => self::INVALID];
        }

        // FR-LOG-7: a FREQ field or a BAND field.
        $frequency = $value('FREQ');
        $band = strtolower($value('BAND'));
        if ($frequency === '' && $band === '') {
            $problems[] = ['field' => 'FREQ', 'problem' => self::MISSING];
        }
        if ($frequency !== '') {
            $frequency = self::frequency($frequency);
            if ($frequency === null) {
                $problems[] = ['field' => 'FREQ', 'problem' => self::INVALID];
            }
        }
        if ($band !== '' && preg_match('/^[0-9]+(\.[0-9]+)?(mm|cm|m)$/', $band) !== 1) {
            $problems[] = ['field' => 'BAND', 'problem' => self::INVALID];
        }

        if ($problems !== [] || $qsoAt === null) {
            return $problems;
        }

        $station = CallSign::normalize($value('STATION_CALLSIGN'));

        return new Contact(
            record: $record,
            callSign: $callSign,
            baseCallSign: CallSign::base($callSign),
            name: self::optional($value('NAME'), self::MAX_NAME),
            qsoAt: $qsoAt,
            frequency: $frequency === '' ? null : $frequency,
            band: $band === '' ? null : $band,
            mode: $mode,
            rstSent: self::optional($value('RST_SENT'), self::MAX_RST),
            rstRcvd: self::optional($value('RST_RCVD'), self::MAX_RST),
            stationCallSign: $station === '' ? null : $station,
        );
    }

    private static function isDate(string $date): bool
    {
        if (preg_match('/^[0-9]{8}$/', $date) !== 1) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Ymd', $date);

        return $parsed !== false && $parsed->format('Ymd') === $date && (int) substr($date, 0, 4) >= 1930;
    }

    private static function isTime(string $time): bool
    {
        if (preg_match('/^([0-9]{2})([0-9]{2})([0-9]{2})?$/', $time, $parts) !== 1) {
            return false;
        }

        return (int) $parts[1] < 24 && (int) $parts[2] < 60 && (int) ($parts[3] ?? 0) < 60;
    }

    /**
     * The frequency in MHz with six or fewer decimals, or null for an incorrect value.
     */
    private static function frequency(string $value): ?string
    {
        if (preg_match('/^[0-9]{1,4}(\.[0-9]+)?$/', $value) !== 1 || (float) $value <= 0) {
            return null;
        }
        $rounded = rtrim(rtrim(number_format(round((float) $value, 6), 6, '.', ''), '0'), '.');

        return $rounded === '0' ? null : $rounded;
    }

    /**
     * An optional text: null if empty, cut to the size of its column.
     */
    private static function optional(string $value, int $max): ?string
    {
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
