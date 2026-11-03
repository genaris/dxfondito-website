<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Ranking\ContactRow;
use DxFondito\Ranking\RankingStore;

final class MemoryRankingStore implements RankingStore
{
    /** @var list<ContactRow> */
    public array $contacts = [];

    /** @var list<array{callSign: string, referenceId: int, seriesCode: string, number: int}> */
    public array $seasonReferences = [];

    public function levels(): array
    {
        return [5, 10, 15];
    }

    public function seasonReferences(int $season): array
    {
        return $this->seasonReferences;
    }

    public function seasonFirstQsos(int $season): array
    {
        $first = [];
        foreach ($this->bySeason($season) as $row) {
            $key = $row->baseCallSign . '|' . $row->activityId;
            if (!isset($first[$key]) || $row->qsoAt < $first[$key]['qsoAt']) {
                $first[$key] = ['callSign' => $row->baseCallSign, 'activityId' => $row->activityId, 'qsoAt' => $row->qsoAt];
            }
        }

        return array_values($first);
    }

    public function byParticipant(string $baseCallSign): array
    {
        return array_values(array_filter($this->contacts, static fn (ContactRow $row): bool => $row->baseCallSign === $baseCallSign));
    }

    public function bySeason(int $season): array
    {
        return array_values(array_filter($this->contacts, static fn (ContactRow $row): bool => $row->season === $season));
    }

    public function byActivity(int $activityId): array
    {
        return array_values(array_filter($this->contacts, static fn (ContactRow $row): bool => $row->activityId === $activityId));
    }
}
