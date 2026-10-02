<?php

declare(strict_types=1);

namespace DxFondito\Activities;

use DateTimeImmutable;
use DxFondito\Audit\AuditLog;
use DxFondito\Audit\Changes;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;

/**
 * The administration of the activities (FR-ACT-1 to FR-ACT-8).
 */
final class ActivityService
{
    public function __construct(
        private readonly ActivityStore $activities,
        private readonly ReferenceStore $references,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * The season of an activity is the year of its start date (R-SEA-2a).
     */
    public static function seasonOf(string $startDate): int
    {
        return (int) substr($startDate, 0, 4);
    }

    /**
     * @throws HttpException 422 for an incorrect value, 409 for a second activity of the reference
     *   with the same start date (FR-ACT-5a).
     */
    public function create(
        User $actor,
        int $referenceId,
        string $startDate,
        string $startTime,
        string $endDate,
        string $endTime,
        ?string $description,
    ): Activity {
        $reference = $this->reference($referenceId);
        [$startDate, $endDate] = self::checkDates($startDate, $endDate);
        [$startTime, $endTime] = self::checkTimes($startDate, $startTime, $endDate, $endTime);
        $description = TextInput::description($description);
        $this->requireFreeStart($referenceId, $startDate, null);

        $id = $this->activities->create($referenceId, self::seasonOf($startDate), $startDate, $startTime, $endDate, $endTime, $description);
        $activity = $this->find($id);
        $this->audit->record($actor->id, AuditLog::ACTIVITY_CREATE, $id, [
            'label' => $activity->label(),
            'name' => $reference->name,
            'startTime' => $startTime,
            'endDate' => $endDate,
            'endTime' => $endTime,
            'description' => $description,
        ]);

        return $activity;
    }

    /**
     * FR-ACT-7.
     *
     * @throws HttpException 404, 422 or 409.
     */
    public function update(
        User $actor,
        int $id,
        int $referenceId,
        string $startDate,
        string $startTime,
        string $endDate,
        string $endTime,
        ?string $description,
    ): Activity {
        $activity = $this->find($id);
        $reference = $this->reference($referenceId);
        [$startDate, $endDate] = self::checkDates($startDate, $endDate);
        [$startTime, $endTime] = self::checkTimes($startDate, $startTime, $endDate, $endTime);
        $description = TextInput::description($description);
        $this->requireFreeStart($referenceId, $startDate, $id);

        $changes = Changes::between(
            [
                'reference' => $activity->reference->referenceCode(),
                'startDate' => $activity->startDate,
                'startTime' => $activity->startTime,
                'endDate' => $activity->endDate,
                'endTime' => $activity->endTime,
                'description' => $activity->description,
            ],
            [
                'reference' => $reference->referenceCode(),
                'startDate' => $startDate,
                'startTime' => $startTime,
                'endDate' => $endDate,
                'endTime' => $endTime,
                'description' => $description,
            ],
        );
        if ($changes === []) {
            return $activity;
        }

        $this->activities->update($id, $referenceId, self::seasonOf($startDate), $startDate, $startTime, $endDate, $endTime, $description);
        $this->audit->record($actor->id, AuditLog::ACTIVITY_UPDATE, $id, [
            'label' => $activity->label(),
            'changes' => $changes,
        ]);

        return $this->find($id);
    }

    /**
     * FR-ACT-8. An activity with QSL card templates also stays.
     *
     * @throws HttpException 404, or 409 if the activity has logs or QSL card templates.
     */
    public function delete(User $actor, int $id): void
    {
        $activity = $this->find($id);
        if ($this->activities->hasLogs($id)) {
            throw new HttpException(409, 'The activity has logs');
        }
        if ($this->activities->hasQslTemplates($id)) {
            throw new HttpException(409, 'The activity has QSL card templates');
        }

        $this->activities->delete($id);
        $this->audit->record($actor->id, AuditLog::ACTIVITY_DELETE, $id, [
            'label' => $activity->label(),
            'name' => $activity->reference->name,
        ]);
    }

    public function find(int $id): Activity
    {
        return $this->activities->find($id) ?? throw new HttpException(404, 'The activity does not exist');
    }

    private function reference(int $id): Reference
    {
        return $this->references->find($id) ?? throw new HttpException(422, 'The reference does not exist');
    }

    private function requireFreeStart(int $referenceId, string $startDate, ?int $ownId): void
    {
        $other = $this->activities->findByStart($referenceId, $startDate);
        if ($other !== null && $other->id !== $ownId) {
            throw new HttpException(409, 'The reference has an activity with this start date');
        }
    }

    /**
     * @return array{0: string, 1: string}
     * @throws HttpException 422 for an incorrect date, or an end date before the start date.
     */
    private static function checkDates(string $startDate, string $endDate): array
    {
        $start = self::date($startDate);
        $end = self::date($endDate);
        if ($end < $start) {
            throw new HttpException(422, 'The end date must be on or after the start date');
        }

        return [$start, $end];
    }

    /**
     * The hours of an activity (FR-ACT-3b): HH:MM in UTC. The end is after the start.
     *
     * @return array{0: string, 1: string}
     * @throws HttpException 422 for an absent or incorrect time, or an end before the start.
     */
    private static function checkTimes(string $startDate, string $startTime, string $endDate, string $endTime): array
    {
        foreach ([$startTime, $endTime] as $time) {
            if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time) !== 1) {
                throw new HttpException(422, 'A time must have the form HH:MM');
            }
        }
        if ($startDate . ' ' . $startTime >= $endDate . ' ' . $endTime) {
            throw new HttpException(422, 'The end must be after the start');
        }

        return [$startTime, $endTime];
    }

    private static function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < 2000) {
            throw new HttpException(422, 'A date must have the form YYYY-MM-DD');
        }

        return $value;
    }
}
