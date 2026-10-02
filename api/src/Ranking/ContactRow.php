<?php

declare(strict_types=1);

namespace DxFondito\Ranking;

/**
 * A contact with the data of its activity, its reference and its log, for the calculations of section 4.
 */
final class ContactRow
{
    /**
     * @param string $qsoAt YYYY-MM-DD HH:MM:SS, UTC.
     * @param string $startDate YYYY-MM-DD, the start date of the activity.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $baseCallSign,
        public readonly string $callSign,
        public readonly ?string $name,
        public readonly string $qsoAt,
        public readonly ?string $frequency,
        public readonly ?string $band,
        public readonly string $mode,
        public readonly int $activityId,
        public readonly string $startDate,
        public readonly int $season,
        public readonly int $referenceId,
        public readonly string $seriesCode,
        public readonly string $referenceCode,
        public readonly string $referenceName,
        public readonly string $operatorCallSign,
    ) {
    }

    /**
     * True if this contact is earlier than the other: the lower time, then the lower id (section 4.3).
     */
    public function isBefore(self $other): bool
    {
        return [$this->qsoAt, $this->id] < [$other->qsoAt, $other->id];
    }
}
