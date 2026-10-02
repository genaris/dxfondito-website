<?php

declare(strict_types=1);

namespace DxFondito\Ranking;

use DxFondito\Activities\Reference;

/**
 * The calculations of section 4 of the system design. The API calculates the points, the positions
 * and the certificates from the contacts for each request (section 3.2). Nothing is stored.
 */
final class Calculator
{
    /**
     * The first contact of each participant with each reference in each season (R-OPR-3, section 4.3).
     * The contacts of all activities of the reference in the season count.
     *
     * @param iterable<ContactRow> $contacts
     * @return list<ContactRow> In the order of the base call sign, the season and the reference.
     */
    public static function firstContacts(iterable $contacts): array
    {
        $first = [];
        foreach ($contacts as $contact) {
            $key = $contact->baseCallSign . '|' . $contact->season . '|' . $contact->referenceId;
            if (!isset($first[$key]) || $contact->isBefore($first[$key])) {
                $first[$key] = $contact;
            }
        }
        ksort($first, SORT_STRING);

        return array_values($first);
    }

    /**
     * The ranking of one season (FR-PUB-2 to FR-PUB-5, R-PTS-1 to R-PTS-3).
     * One point for each reference with a contact. Equal points give the same position (1, 1, 3).
     * Each line has the codes of the references of the participant (D-25).
     *
     * @param iterable<array{callSign: string, referenceId: int, seriesCode: string, number: int}> $references
     *   The references of each participant in the season.
     * @param list<int> $levels The certificate levels, such as [5, 10, 15].
     * @return list<array{position: int, callSign: string, points: int, references: list<string>, levels: list<int>}>
     */
    public static function ranking(iterable $references, array $levels): array
    {
        $participants = [];
        foreach ($references as $item) {
            // The key removes a second activity of the same reference (R-PTS-2a).
            $participants[$item['callSign']][$item['referenceId']] = [$item['seriesCode'], $item['number']];
        }

        $rows = [];
        foreach ($participants as $callSign => $codes) {
            sort($codes);
            $rows[] = [
                'callSign' => (string) $callSign,
                'points' => count($codes),
                'references' => array_map(static fn (array $code): string => Reference::code($code[0], $code[1]), $codes),
            ];
        }

        // The order of the points, from high to low. Equal points: alphabetical order (FR-PUB-5).
        usort($rows, static fn (array $a, array $b): int => [$b['points'], $a['callSign']] <=> [$a['points'], $b['callSign']]);

        $position = 0;
        $previousPoints = null;
        foreach ($rows as $index => $row) {
            if ($row['points'] !== $previousPoints) {
                $position = $index + 1;
                $previousPoints = $row['points'];
            }
            $rows[$index] = ['position' => $position] + $row + [
                'levels' => array_values(array_filter($levels, static fn (int $level): bool => $row['points'] >= $level)),
            ];
        }

        return $rows;
    }

    /**
     * The certificates of one participant in one season (R-CER-1 to R-CER-5, section 4.4).
     *
     * @param list<ContactRow> $firstContacts The first contacts of the participant in the season.
     * @param list<int> $levels
     * @return list<array{points: int, date: string}> The available certificates, each with its date.
     */
    public static function certificates(array $firstContacts, array $levels): array
    {
        // The activity of each first contact gave a point. The list is in the order of the start dates.
        $dates = array_map(static fn (ContactRow $contact): string => $contact->startDate, $firstContacts);
        sort($dates, SORT_STRING);

        $certificates = [];
        foreach ($levels as $level) {
            if (count($dates) >= $level) {
                // The date of the activity that gave the last necessary point (R-CER-5).
                $certificates[] = ['points' => $level, 'date' => $dates[$level - 1]];
            }
        }

        return $certificates;
    }

    /**
     * The points necessary for the next level, or null after the highest level (FR-PUB-8b).
     *
     * @param list<int> $levels
     */
    public static function pointsToNextLevel(int $points, array $levels): ?int
    {
        foreach ($levels as $level) {
            if ($level > $points) {
                return $level - $points;
            }
        }

        return null;
    }

    /**
     * All contacts of one activity, in the order of time (FR-PUB-15). A participant with more than one contact,
     * with the same operator or with different operators, has a line for each contact.
     *
     * @param iterable<ContactRow> $contacts The contacts of the activity.
     * @return list<ContactRow>
     */
    public static function activityContacts(iterable $contacts): array
    {
        $list = [...$contacts];
        usort($list, static fn (ContactRow $a, ContactRow $b): int => [$a->qsoAt, $a->id] <=> [$b->qsoAt, $b->id]);

        return $list;
    }

    /**
     * The number of participants: the different base call signs (FR-PUB-14a).
     *
     * @param list<ContactRow> $contacts
     */
    public static function participantCount(array $contacts): int
    {
        return count(array_unique(array_map(static fn (ContactRow $contact): string => $contact->baseCallSign, $contacts)));
    }
}
