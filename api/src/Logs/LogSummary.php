<?php

declare(strict_types=1);

namespace DxFondito\Logs;

use DxFondito\Activities\Activity;
use DxFondito\Auth\User;
use DxFondito\CallSign;

/**
 * The result of the reading of a log file (FR-LOG-10 to FR-LOG-13).
 */
final class LogSummary
{
    public const DATE_OUT_OF_ACTIVITY = 'date';
    public const OTHER_STATION = 'station';

    /**
     * @param list<Contact> $contacts The valid records.
     * @param list<array{record: int, callSign: ?string, problems: list<array{field: string, problem: string}>}> $invalid
     * @param list<array{record: int, callSign: string, kind: string, value: string}> $warnings
     */
    private function __construct(
        public readonly array $contacts,
        public readonly array $invalid,
        public readonly array $warnings,
    ) {
    }

    public static function read(string $content, Activity $activity, User $operator): self
    {
        $contacts = [];
        $invalid = [];
        $warnings = [];
        $operatorBase = CallSign::base($operator->callSign);

        foreach (AdifReader::read($content) as $index => $fields) {
            $record = $index + 1;
            $result = RecordReader::read($record, $fields);
            if (is_array($result)) {
                $call = CallSign::normalize($fields['CALL'] ?? '');
                $invalid[] = ['record' => $record, 'callSign' => $call === '' ? null : $call, 'problems' => $result];
                continue;
            }

            $contacts[] = $result;
            // D-7: a date out of the activity dates gives a warning only.
            $date = substr($result->qsoAt, 0, 10);
            if ($date < $activity->startDate || $date > $activity->endDate) {
                $warnings[] = ['record' => $record, 'callSign' => $result->callSign, 'kind' => self::DATE_OUT_OF_ACTIVITY, 'value' => $date];
            }
            // LU1ABC/P is the station of LU1ABC. Thus the comparison uses the base call signs.
            if ($result->stationCallSign !== null && CallSign::base($result->stationCallSign) !== $operatorBase) {
                $warnings[] = ['record' => $record, 'callSign' => $result->callSign, 'kind' => self::OTHER_STATION, 'value' => $result->stationCallSign];
            }
        }

        return new self($contacts, $invalid, $warnings);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'validCount' => count($this->contacts),
            'invalid' => $this->invalid,
            'warnings' => $this->warnings,
        ];
    }
}
