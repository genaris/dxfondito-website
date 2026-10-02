<?php

declare(strict_types=1);

namespace DxFondito\Logs;

/**
 * Reads the records of an ADIF file (system design, section 5.1).
 *
 * The reader gives each record as an array of field values with the field names in uppercase letters.
 * It does not check the values. RecordReader does that.
 */
final class AdifReader
{
    /**
     * @return list<array<string, string>> The records in the order of the file.
     */
    public static function read(string $content): array
    {
        // The reader ignores all text before <EOH>, if the file has a header.
        $position = 0;
        if (preg_match('/<eoh>/i', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $position = $match[0][1] + strlen($match[0][0]);
        }

        $records = [];
        $fields = [];
        $length = strlen($content);
        while ($position < $length) {
            $open = strpos($content, '<', $position);
            if ($open === false) {
                break;
            }
            $close = strpos($content, '>', $open);
            if ($close === false) {
                break;
            }
            $position = $close + 1;

            // <NAME:length> or <NAME:length:type>. The length is a number of bytes.
            $parts = explode(':', substr($content, $open + 1, $close - $open - 1));
            $name = strtoupper(trim($parts[0]));

            if ($name === 'EOR') {
                if ($fields !== []) {
                    $records[] = $fields;
                }
                $fields = [];
                continue;
            }
            if (!isset($parts[1]) || !ctype_digit(trim($parts[1])) || $name === '') {
                continue;
            }

            $data = (string) substr($content, $position, (int) trim($parts[1]));
            $position += strlen($data);
            $fields[$name] = self::toUtf8($data);
        }

        // A last record without <EOR> is also a record. Thus the summary shows it.
        if ($fields !== []) {
            $records[] = $fields;
        }

        return $records;
    }

    /**
     * Data that is not valid UTF-8 is ISO-8859-1. Thus names with accents stay correct.
     */
    private static function toUtf8(string $data): string
    {
        return mb_check_encoding($data, 'UTF-8') ? $data : mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
    }
}
