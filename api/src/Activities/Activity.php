<?php

declare(strict_types=1);

namespace DxFondito\Activities;

/**
 * One operation of the group on the air for one reference. The dates are in UTC (FR-ACT-3).
 */
final class Activity
{
    /**
     * @param string $startDate YYYY-MM-DD.
     * @param string $endDate YYYY-MM-DD.
     */
    public function __construct(
        public readonly int $id,
        public readonly Reference $reference,
        public readonly int $season,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $description,
    ) {
    }

    /**
     * The identification of the activity, such as "DPS-01 (2026-05-10)" (FR-ACT-5).
     */
    public function label(): string
    {
        return $this->reference->referenceCode() . ' (' . $this->startDate . ')';
    }

    /**
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'reference' => $this->reference->publicData(),
            'season' => $this->season,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'description' => $this->description,
        ];
    }
}
