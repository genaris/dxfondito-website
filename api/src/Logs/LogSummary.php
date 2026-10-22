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

    // A record of the file is the same contact as a contact of the live log with the same base call sign and a time
    // with this difference or less: the clock of the logger and the clock of the browser can differ a little.
    private const SAME_CONTACT_SECONDS = 120;

    /**
     * @param list<Contact> $contacts The valid records.
     * @param list<array{record: int, callSign: ?string, problems: list<array{field: string, problem: string}>}> $invalid
     * @param list<array{record: int, callSign: string, kind: string, value: string}> $warnings
     * @param list<int> $repeated The valid records that are already in the live log of the operator.
     */
    private function __construct(
        public readonly array $contacts,
        public readonly array $invalid,
        public readonly array $warnings,
        public readonly array $repeated = [],
    ) {
    }

    /**
     * @param list<array{baseCallSign: string, qsoAt: string}> $live The contacts of the live log of the operator.
     */
    public static function read(string $content, Activity $activity, User $operator, array $live = []): self
    {
        $contacts = [];
        $invalid = [];
        $warnings = [];
        $repeated = [];
        $operatorBase = CallSign::base($operator->callSign);
        $liveTimes = [];
        foreach ($live as $contact) {
            $liveTimes[$contact['baseCallSign']][] = strtotime($contact['qsoAt'] . ' UTC');
        }

        foreach (AdifReader::read($content) as $index => $fields) {
            $record = $index + 1;
            $result = RecordReader::read($record, $fields);
            if (is_array($result)) {
                $call = CallSign::normalize($fields['CALL'] ?? '');
                $invalid[] = ['record' => $record, 'callSign' => $call === '' ? null : $call, 'problems' => $result];
                continue;
            }

            $contacts[] = $result;
            $time = strtotime($result->qsoAt . ' UTC');
            foreach ($liveTimes[$result->baseCallSign] ?? [] as $liveTime) {
                if (abs($time - $liveTime) <= self::SAME_CONTACT_SECONDS) {
                    $repeated[] = $record;
                    break;
                }
            }
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

        return new self($contacts, $invalid, $warnings, $repeated);
    }

    /**
     * The valid records without the records that are already in the live log.
     *
     * @return list<Contact>
     */
    public function withoutRepeated(): array
    {
        return array_values(array_filter($this->contacts, fn (Contact $contact): bool => !in_array($contact->record, $this->repeated, true)));
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
            'repeated' => array_values(array_map(
                static fn (Contact $contact): array => ['record' => $contact->record, 'callSign' => $contact->callSign, 'qsoAt' => str_replace(' ', 'T', $contact->qsoAt) . 'Z'],
                array_filter($this->contacts, fn (Contact $contact): bool => in_array($contact->record, $this->repeated, true)),
            )),
        ];
    }
}
