<?php

declare(strict_types=1);

namespace DxFondito\Live;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use DxFondito\Activities\Activity;
use DxFondito\Activities\ActivityStore;
use DxFondito\Activities\Reference;
use DxFondito\Audit\AuditLog;
use DxFondito\Audit\Changes;
use DxFondito\Auth\User;
use DxFondito\Auth\UserStore;
use DxFondito\Http\HttpException;
use DxFondito\Logs\Log;
use DxFondito\Logs\LogSummary;
use DxFondito\Logs\RecordReader;
use DxFondito\Ranking\RankingStore;

/**
 * The live page: the contacts that the operators enter during an activity, from one or more browsers
 * (FR-LIVE-1 to FR-LIVE-15, system design, section 5.3). The browser keeps each change in a queue and sends the queue to `sync`.
 */
final class LiveService
{
    public const OK = 'ok';
    public const CONFLICT = 'conflict';
    public const ERROR = 'error';

    /** The warning of a contact out of the hours of the activity. A date out of the dates gives LogSummary::DATE_OUT_OF_ACTIVITY. */
    public const OUT_OF_HOURS = 'hours';

    /** The voice modes of the program. */
    public const MODES = ['SSB', 'FM'];

    // A browser sends some tens of changes after a break of the connection.
    private const MAX_CHANGES = 200;
    private const MAX_NOTES = 500;

    // The answer gives the changes until now less these seconds. Thus the next request gets again the changes of a
    // transaction that started before the answer and ended after it. The browser ignores a change that it has.
    private const OVERLAP_SECONDS = 10;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly LiveStore $live,
        private readonly ActivityStore $activities,
        private readonly UserStore $users,
        private readonly RankingStore $ranking,
        private readonly AuditLog $audit,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * The data of the live page. With `$since`, only the changes since that time.
     *
     * @return array<string, mixed>
     */
    public function state(int $activityId, ?string $since): array
    {
        $activity = $this->activity($activityId);
        $now = $this->utcNow();
        if ($since !== null) {
            return $this->changes($activity, self::since($since), $now);
        }

        return [
            'activity' => $activity->publicData(),
            'operators' => $this->operators($activity),
            'contacts' => array_map(static fn (LiveContact $contact): array => $contact->data(), $this->live->contacts($activity->id, null)),
            'uploaded' => $this->live->uploadedContacts($activity->id),
        ] + self::times($now);
    }

    /**
     * Applies the changes of a browser in their order. A change with an error does not stop the others.
     *
     * @param mixed $changes A list of `{clientId, op: "save" | "delete", baseVersion, contact}`.
     * @return array<string, mixed> The result of each change, and the changes of all browsers since `$since`.
     */
    public function sync(User $actor, int $activityId, mixed $changes, ?string $since): array
    {
        $activity = $this->activity($activityId);
        if (!is_array($changes) || !array_is_list($changes)) {
            throw new HttpException(422, 'The changes must be a list');
        }
        if (count($changes) > self::MAX_CHANGES) {
            throw new HttpException(422, 'Send ' . self::MAX_CHANGES . ' or fewer changes in one request');
        }
        $sinceTime = $since === null ? null : self::since($since);
        $now = $this->utcNow();

        $results = array_map(fn (mixed $change): array => $this->apply($actor, $activity, $change, $now), $changes);

        return ['results' => $results] + ($sinceTime === null ? self::times($now) : $this->changes($activity, $sinceTime, $now));
    }

    /**
     * The table of the help of the live page (FR-LIVE-7): the names, and the references of
     * each participant in the season of the activity. The browser looks up each call sign without a request.
     *
     * @return array<string, mixed>
     */
    public function lookup(int $activityId): array
    {
        $activity = $this->activity($activityId);
        $references = [];
        foreach ($this->ranking->seasonReferences($activity->season) as $row) {
            $references[$row['callSign']][] = Reference::code($row['seriesCode'], $row['number']);
        }
        $names = $this->live->names();

        return [
            'reference' => $activity->reference->referenceCode(),
            'season' => $activity->season,
            // An empty PHP array gives [] in JSON. The browser needs an object.
            'names' => $names === [] ? (object) [] : $names,
            'references' => $references === [] ? (object) [] : $references,
        ];
    }

    /**
     * The ADIF file of the contacts of an operator in an activity, from live logs and uploaded logs (FR-LIVE-13).
     * For the operator and the administrators, as the original file of a log (FR-LOG-18).
     *
     * @param string|null $station Only the contacts of this station call sign, such as LU2AOG/A.
     * @return array{name: string, content: string}
     */
    public function adif(User $actor, int $activityId, int $operatorId, ?string $station): array
    {
        $activity = $this->activity($activityId);
        if (!$actor->isAdministrator() && $actor->id !== $operatorId) {
            throw new HttpException(403, 'Only the operator or an administrator can do this');
        }
        $operator = $this->users->findById($operatorId) ?? throw new HttpException(404, 'The operator does not exist');
        $records = $this->live->operatorRecords($activity->id, $operator->id);
        if ($station !== null) {
            $records = array_values(array_filter($records, static fn (AdifRecord $record): bool => $record->station === $station));
        }
        if ($records === []) {
            throw new HttpException(404, 'The operator has no contacts in the activity');
        }

        $name = sprintf(
            '%s_%s_%s.adi',
            $activity->reference->referenceCode(),
            str_replace('/', '-', $station ?? $operator->callSign),
            str_replace('-', '', $activity->startDate),
        );

        return ['name' => $name, 'content' => AdifWriter::write($records, $this->utcNow()->format('Y-m-d H:i:s'))];
    }

    /**
     * The file of a live log: the log has no stored file. The caller checks the user.
     *
     * @return array{name: string, content: string}
     */
    public function logFile(Log $log): array
    {
        return ['name' => $log->fileName, 'content' => AdifWriter::write($this->live->logRecords($log->id), $this->utcNow()->format('Y-m-d H:i:s'))];
    }

    /**
     * The station call sign of an operator in an activity: with /A in a DPS activity, as in the logs of 2026.
     */
    public static function station(User $operator, Activity $activity): string
    {
        return $activity->reference->series->code === 'DPS' ? $operator->callSign . '/A' : $operator->callSign;
    }

    /**
     * @return array<string, mixed>
     */
    private function apply(User $actor, Activity $activity, mixed $change, DateTimeImmutable $now): array
    {
        $clientId = is_array($change) && is_string($change['clientId'] ?? null) ? $change['clientId'] : null;
        if ($clientId === null || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $clientId) !== 1) {
            return ['clientId' => $clientId, 'status' => self::ERROR, 'error' => 'The identifier of the contact is not valid'];
        }
        $existing = $this->live->find($clientId);
        if ($existing !== null && $existing->activityId !== $activity->id) {
            return ['clientId' => $clientId, 'status' => self::ERROR, 'error' => 'The contact is of a different activity'];
        }

        return match ($change['op'] ?? null) {
            'save' => $this->save($actor, $activity, $clientId, $change, $existing, $now),
            'delete' => $this->delete($actor, $activity, $clientId, $existing, $now),
            default => ['clientId' => $clientId, 'status' => self::ERROR, 'error' => 'The operation must be save or delete'],
        };
    }

    /**
     * @param array<mixed> $change
     * @return array<string, mixed>
     */
    private function save(User $actor, Activity $activity, string $clientId, array $change, ?LiveContact $existing, DateTimeImmutable $now): array
    {
        $contact = $this->build($actor, $activity, $clientId, $change['contact'] ?? null, $now);
        if (is_string($contact)) {
            return ['clientId' => $clientId, 'status' => self::ERROR, 'error' => $contact];
        }
        if (is_array($contact)) {
            return ['clientId' => $clientId, 'status' => self::ERROR, 'error' => 'The contact is not valid', 'problems' => $contact];
        }

        // A second sending of the same values: the answer to the first sending did not arrive.
        if ($existing !== null && $existing->values() === $contact->values()) {
            return $this->saved($existing, $activity);
        }
        $baseVersion = is_int($change['baseVersion'] ?? null) ? $change['baseVersion'] : null;
        if ($existing === null && $baseVersion !== null) {
            // A different browser deleted the contact. The user decides if it comes back.
            return ['clientId' => $clientId, 'status' => self::CONFLICT, 'server' => null];
        }
        if ($existing !== null && $baseVersion !== $existing->version) {
            return ['clientId' => $clientId, 'status' => self::CONFLICT, 'server' => $existing->data()];
        }

        $contact = $contact->withVersion($existing === null ? 1 : $existing->version + 1);
        $result = $this->live->save($contact, $existing?->version, self::fileName($activity, $contact->stationCallSign));
        if (!$result['saved']) {
            $current = $this->live->find($clientId);
            if ($current !== null && $current->values() === $contact->values()) {
                return $this->saved($current, $activity);
            }

            return ['clientId' => $clientId, 'status' => self::CONFLICT, 'server' => $current?->data()];
        }

        if ($result['logCreated']) {
            $this->audit->record($actor->id, AuditLog::LIVE_LOG_CREATE, $result['logId'], [
                'label' => $activity->label(),
                'operator' => $contact->stationCallSign,
            ]);
        }
        if ($existing !== null) {
            $this->audit->record($actor->id, AuditLog::LIVE_CONTACT_UPDATE, $result['logId'], [
                'label' => $activity->label(),
                'callSign' => $existing->callSign,
                'qsoAt' => $existing->qsoAt,
                'changes' => Changes::between($existing->values(), $contact->values()),
            ]);
        }

        return $this->saved($contact, $activity);
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(User $actor, Activity $activity, string $clientId, ?LiveContact $existing, DateTimeImmutable $now): array
    {
        // A contact that does not exist is already deleted: a second sending of the same deletion.
        if ($existing !== null) {
            $this->live->delete($existing, $now->format('Y-m-d H:i:s'));
            $this->audit->record($actor->id, AuditLog::LIVE_CONTACT_DELETE, null, [
                'label' => $activity->label(),
                'operator' => $existing->stationCallSign,
                'callSign' => $existing->callSign,
                'qsoAt' => $existing->qsoAt,
            ]);
        }

        return ['clientId' => $clientId, 'status' => self::OK];
    }

    /**
     * @return array<string, mixed>
     */
    private function saved(LiveContact $contact, Activity $activity): array
    {
        return ['clientId' => $contact->clientId, 'status' => self::OK, 'contact' => $contact->data(), 'warnings' => self::warnings($contact, $activity)];
    }

    /**
     * The contact from the values of the browser, with the rules of the ADIF reader (FR-LOG-6 to FR-LOG-9).
     *
     * @return LiveContact|string|list<array{field: string, problem: string}> The contact (version 0), the error,
     *     or the problems of the fields.
     */
    private function build(User $actor, Activity $activity, string $clientId, mixed $values, DateTimeImmutable $now): LiveContact|string|array
    {
        if (!is_array($values)) {
            return 'The contact is necessary';
        }
        $text = static fn (string $key): string => is_string($values[$key] ?? null) || is_int($values[$key] ?? null) || is_float($values[$key] ?? null)
            ? trim((string) $values[$key])
            : '';

        $operatorId = is_int($values['operatorId'] ?? null) ? $values['operatorId'] : null;
        $operator = $operatorId === null ? null : $this->users->findById($operatorId);
        if ($operator === null || !$operator->active) {
            return 'The operator does not exist';
        }

        $date = '';
        $time = '';
        if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2})(?::([0-9]{2}))?(?:\.[0-9]+)?Z$/', $text('qsoAt'), $parts) === 1) {
            $date = $parts[1] . $parts[2] . $parts[3];
            $time = $parts[4] . $parts[5] . ($parts[6] ?? '00');
        }
        $frequency = $text('frequency');
        $mode = strtoupper($text('mode'));
        $result = RecordReader::read(0, [
            'CALL' => $text('callSign'),
            'QSO_DATE' => $date,
            'TIME_ON' => $time,
            'MODE' => $mode,
            'FREQ' => $frequency,
            'NAME' => $text('name'),
            'RST_SENT' => $text('rstSent'),
            'RST_RCVD' => $text('rstRcvd'),
        ]);
        $problems = is_array($result) ? $result : [];
        if ($date === '') {
            // The page sends one time, not a date and a time as ADIF.
            $problems = array_values(array_filter($problems, static fn (array $problem): bool => !in_array($problem['field'], ['QSO_DATE', 'TIME_ON'], true)));
            $problems[] = ['field' => 'QSO_DATE', 'problem' => $text('qsoAt') === '' ? RecordReader::MISSING : RecordReader::INVALID];
        }
        // The page needs the frequency for the band and the mode. An ADIF record can have only the band.
        if ($frequency === '' && !in_array('FREQ', array_column($problems, 'field'), true)) {
            $problems[] = ['field' => 'FREQ', 'problem' => RecordReader::MISSING];
        }
        if ($mode !== '' && !in_array($mode, self::MODES, true) && !in_array('MODE', array_column($problems, 'field'), true)) {
            $problems[] = ['field' => 'MODE', 'problem' => RecordReader::INVALID];
        }
        if ($problems !== [] || is_array($result) || $result->frequency === null) {
            return $problems;
        }

        $notes = mb_substr($text('notes'), 0, self::MAX_NOTES);
        $station = self::station($operator, $activity);

        return new LiveContact(
            clientId: $clientId,
            activityId: $activity->id,
            operatorId: $operator->id,
            operatorCallSign: $operator->callSign,
            stationCallSign: $station,
            callSign: $result->callSign,
            baseCallSign: $result->baseCallSign,
            name: $result->name,
            qsoAt: $result->qsoAt,
            frequency: $result->frequency,
            band: Band::of($result->frequency),
            mode: $result->mode,
            rstSent: $result->rstSent,
            rstRcvd: $result->rstRcvd,
            notes: $notes === '' ? null : $notes,
            enteredById: $actor->id,
            enteredByCallSign: $actor->callSign,
            updatedAt: $now->format('Y-m-d H:i:s'),
            version: 0,
        );
    }

    /**
     * A contact out of the dates or the hours of the activity gives a warning only (D-7).
     *
     * @return list<array{kind: string, value: string}>
     */
    private static function warnings(LiveContact $contact, Activity $activity): array
    {
        $date = substr($contact->qsoAt, 0, 10);
        if ($date < $activity->startDate || $date > $activity->endDate) {
            return [['kind' => LogSummary::DATE_OUT_OF_ACTIVITY, 'value' => $date]];
        }
        $minute = substr($contact->qsoAt, 0, 16);
        if (($activity->startTime !== null && $minute < $activity->startDate . ' ' . $activity->startTime)
            || ($activity->endTime !== null && $minute > $activity->endDate . ' ' . $activity->endTime)) {
            return [['kind' => self::OUT_OF_HOURS, 'value' => substr($contact->qsoAt, 11, 5)]];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function changes(Activity $activity, string $since, DateTimeImmutable $now): array
    {
        return [
            'contacts' => array_map(static fn (LiveContact $contact): array => $contact->data(), $this->live->contacts($activity->id, $since)),
            'deleted' => $this->live->deletions($activity->id, $since),
        ] + self::times($now);
    }

    /**
     * @return list<array{id: int, callSign: string, name: string, station: string}> The active accounts.
     */
    private function operators(Activity $activity): array
    {
        $operators = [];
        foreach ($this->users->all() as $user) {
            if ($user->active) {
                $operators[] = ['id' => $user->id, 'callSign' => $user->callSign, 'name' => $user->name, 'station' => self::station($user, $activity)];
            }
        }

        return $operators;
    }

    /**
     * `cursor` is the `since` value of the next request. `serverTime` lets the browser check its clock.
     *
     * @return array{cursor: string, serverTime: string}
     */
    private static function times(DateTimeImmutable $now): array
    {
        return [
            'cursor' => $now->sub(new DateInterval('PT' . self::OVERLAP_SECONDS . 'S'))->format('Y-m-d\TH:i:s\Z'),
            'serverTime' => $now->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }

    /**
     * @throws HttpException 422 for a value that is not a time of a cursor.
     */
    private static function since(string $value): string
    {
        if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})T([0-9]{2}:[0-9]{2}:[0-9]{2})Z$/', $value, $parts) !== 1) {
            throw new HttpException(422, 'The since value is not valid');
        }

        return $parts[1] . ' ' . $parts[2];
    }

    private static function fileName(Activity $activity, string $station): string
    {
        return sprintf('%s_%s.adi', $activity->reference->referenceCode(), str_replace('/', '-', $station));
    }

    private function utcNow(): DateTimeImmutable
    {
        return ($this->now)()->setTimezone(new DateTimeZone('UTC'));
    }

    private function activity(int $id): Activity
    {
        return $this->activities->find($id) ?? throw new HttpException(404, 'The activity does not exist');
    }
}
