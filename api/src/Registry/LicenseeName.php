<?php

declare(strict_types=1);

namespace DxFondito\Registry;

/**
 * The name of a licensee for the QSL card (FR-QSL-3a). The registries give the full name in uppercase letters,
 * such as "JUANA ISABEL EJEMPLO". The QSL card shows "Juana Isabel Ejemplo".
 */
final class LicenseeName
{
    /** Words that stay in lowercase letters inside a name, such as "de" in "Maria de los Angeles". */
    private const PARTICLES = ['da', 'das', 'de', 'del', 'di', 'do', 'dos', 'e', 'la', 'las', 'los', 'van', 'von', 'y'];

    public static function format(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $result = [];
        foreach ($words as $index => $word) {
            $lower = mb_strtolower($word, 'UTF-8');
            if ($index > 0 && in_array($lower, self::PARTICLES, true)) {
                $result[] = $lower;
                continue;
            }
            // A capital letter after an apostrophe or a hyphen: D'Angelo, Perez-Garcia.
            $result[] = preg_replace_callback(
                "/(^|['\\-])(\\p{L})/u",
                static fn (array $match): string => $match[1] . mb_strtoupper($match[2], 'UTF-8'),
                $lower,
            );
        }

        return implode(' ', $result);
    }
}
