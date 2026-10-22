<?php

declare(strict_types=1);

namespace DxFondito\Live;

/**
 * Makes an ADIF file from contacts, for QRZ.com and LdA (FR-LIVE-13).
 */
final class AdifWriter
{
    /**
     * @param list<AdifRecord> $records
     */
    public static function write(array $records, string $now): string
    {
        $text = "ADIF export of the DX Fondito site\n"
            . self::field('ADIF_VER', '3.1.4')
            . self::field('CREATED_TIMESTAMP', str_replace(['-', ':'], '', $now))
            . self::field('PROGRAMID', 'DX Fondito')
            . "<EOH>\n";

        foreach ($records as $record) {
            $text .= self::field('CALL', $record->callSign)
                . self::field('QSO_DATE', str_replace('-', '', substr($record->qsoAt, 0, 10)))
                . self::field('TIME_ON', str_replace(':', '', substr($record->qsoAt, 11, 8)))
                . self::field('BAND', $record->band)
                . self::field('FREQ', $record->frequency)
                . self::field('MODE', $record->mode)
                . self::field('RST_SENT', $record->rstSent)
                . self::field('RST_RCVD', $record->rstRcvd)
                . self::field('NAME', $record->name)
                . self::field('STATION_CALLSIGN', $record->station)
                . self::field('OPERATOR', $record->operator)
                . self::field('COMMENT', $record->notes === null ? null : self::oneLine($record->notes))
                . "<EOR>\n";
        }

        return $text;
    }

    /**
     * A field with its length in bytes, as the ADIF reader reads it. No field for an empty value.
     */
    private static function field(string $name, ?string $value): string
    {
        return $value === null || $value === '' ? '' : sprintf('<%s:%d>%s ', $name, strlen($value), $value);
    }

    /**
     * COMMENT has one line.
     */
    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
