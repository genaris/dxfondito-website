<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Ranking\ContactRow;
use DxFondito\Ranking\RankingStore;

final class MemoryRankingStore implements RankingStore
{
    /** @var list<ContactRow> */
    public array $contacts = [];

    public function levels(): array
    {
        return [5, 10, 15];
    }

    public function seasonReferences(int $season): array
    {
        return [];
    }

    public function byParticipant(string $baseCallSign): array
    {
        return array_values(array_filter($this->contacts, static fn (ContactRow $row): bool => $row->baseCallSign === $baseCallSign));
    }

    public function byActivity(int $activityId): array
    {
        return array_values(array_filter($this->contacts, static fn (ContactRow $row): bool => $row->activityId === $activityId));
    }
}
