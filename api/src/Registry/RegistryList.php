<?php

declare(strict_types=1);

namespace DxFondito\Registry;

use RuntimeException;

/**
 * The parsers of the official lists of licensees (system design, section 6.4).
 */
final class RegistryList
{
    /**
     * The list of ENACOM: an HTML table with the columns "Radioaficionado" (the name) and "Señal Distintiva".
     *
     * @return array<string, string> The name of each call sign.
     * @throws RuntimeException if the page does not have these columns.
     */
    public static function enacom(string $html): array
    {
        $headers = array_map(self::text(...), self::matches('#<th[^>]*>(.*?)</th>#s', $html));
        $nameColumn = array_search('Radioaficionado', $headers, true);
        $callColumn = array_search('Señal Distintiva', $headers, true);
        if ($nameColumn === false || $callColumn === false) {
            throw new RuntimeException('The list of ENACOM does not have the expected columns');
        }

        $licensees = [];
        foreach (self::matches('#<tr[^>]*>(.*?)</tr>#s', $html) as $row) {
            $cells = array_map(self::text(...), self::matches('#<td[^>]*>(.*?)</td>#s', $row));
            self::add($licensees, $cells[$callColumn] ?? '', $cells[$nameColumn] ?? '');
        }

        return $licensees;
    }

    /**
     * The list of URSEC: an ODS spreadsheet with the columns "Distintivo de Llamada", "Apellidos/Razón Social"
     * and "Nombres". The name is the given names and then the surnames.
     *
     * @return array<string, string>
     * @throws RuntimeException if the file is not correct or does not have these columns.
     */
    public static function ursec(string $ods): array
    {
        $xml = ZipReader::read($ods, 'content.xml');
        $licensees = [];
        $columns = null;
        foreach (self::matches('#<table:table-row[^>]*>(.*?)</table:table-row>#s', $xml) as $row) {
            $cells = self::odsCells($row);
            if ($columns === null) {
                $call = array_search('Distintivo de Llamada', $cells, true);
                $surnames = array_search('Apellidos/Razón Social', $cells, true);
                $names = array_search('Nombres', $cells, true);
                if ($call !== false && $surnames !== false && $names !== false) {
                    $columns = [$call, $surnames, $names];
                }
                continue;
            }
            [$call, $surnames, $names] = $columns;
            self::add($licensees, $cells[$call] ?? '', trim(($cells[$names] ?? '') . ' ' . ($cells[$surnames] ?? '')));
        }
        if ($columns === null) {
            throw new RuntimeException('The list of URSEC does not have the expected columns');
        }

        return $licensees;
    }

    /**
     * The address of the newest list of current licensees on the page of URSEC, such as
     * ".../files/2026-07/Nomina CX Vigentes Julio 2026.ods".
     *
     * @throws RuntimeException if the page has no such file.
     */
    public static function ursecLink(string $html, string $base): string
    {
        $links = array_filter(
            self::matches('#href=["\']([^"\']+\.ods)["\']#i', $html),
            static fn (string $link): bool => stripos(rawurldecode($link), 'vigentes') !== false,
        );
        if ($links === []) {
            throw new RuntimeException('The page of URSEC has no list of current licensees');
        }
        // The newest file has the highest folder of the year and the month.
        usort($links, static fn (string $a, string $b): int => self::month($b) <=> self::month($a));
        $link = html_entity_decode($links[0]);

        return str_starts_with($link, 'http') ? $link : rtrim($base, '/') . '/' . ltrim($link, '/');
    }

    /**
     * @param array<string, string> $licensees
     */
    private static function add(array &$licensees, string $callSign, string $name): void
    {
        $callSign = strtoupper(trim($callSign));
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if (preg_match('/^[A-Z0-9]{3,10}$/', $callSign) === 1 && $name !== '') {
            $licensees[$callSign] = mb_substr($name, 0, 150);
        }
    }

    /**
     * The text of each cell of an ODS row. A cell can repeat: table:number-columns-repeated.
     *
     * @return list<string>
     */
    private static function odsCells(string $row): array
    {
        preg_match_all('#<table:(?:covered-)?table-cell(\s[^>]*)?(?:/>|>(.*?)</table:(?:covered-)?table-cell>)#s', $row, $matches, PREG_SET_ORDER);
        $cells = [];
        foreach ($matches as $match) {
            $repeat = preg_match('/table:number-columns-repeated="(\d+)"/', $match[1] ?? '', $count) === 1 ? (int) $count[1] : 1;
            $text = self::text($match[2] ?? '');
            // Only a few repetitions matter: the empty cells at the end of a row can repeat a thousand times.
            for ($i = 0; $i < min($repeat, 20); $i++) {
                $cells[] = $text;
            }
        }

        return $cells;
    }

    private static function month(string $link): string
    {
        return preg_match('#/(\d{4}-\d{2})/#', $link, $match) === 1 ? $match[1] : '';
    }

    /**
     * @return list<string>
     */
    private static function matches(string $pattern, string $text): array
    {
        preg_match_all($pattern, $text, $matches);

        return $matches[1];
    }

    private static function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags(str_replace('</text:p>', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
