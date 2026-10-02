<?php

declare(strict_types=1);

namespace DxFondito\Tests\Activities;

use DxFondito\Activities\Activity;
use DxFondito\Activities\ActivityStore;

final class MemoryActivityStore implements ActivityStore
{
    /** @var array<int, array{referenceId: int, season: int, startDate: string, startTime: ?string, endDate: string, endTime: ?string, description: ?string}> */
    public array $rows = [];

    /** @var array<int, true> The activities with logs. */
    public array $withLogs = [];

    /** @var array<int, true> The activities with QSL card templates. */
    public array $withTemplates = [];

    private int $nextId = 1;

    public function __construct(private readonly MemoryReferenceStore $references)
    {
    }

    public function seasons(): array
    {
        $seasons = array_values(array_unique(array_column($this->rows, 'season')));
        rsort($seasons);

        return $seasons;
    }

    public function bySeason(int $season): array
    {
        $activities = array_values(array_filter(
            array_map($this->find(...), array_keys($this->rows)),
            static fn (?Activity $activity): bool => $activity?->season === $season,
        ));
        usort($activities, static fn (Activity $a, Activity $b): int => strcmp($b->startDate, $a->startDate));

        return $activities;
    }

    public function find(int $id): ?Activity
    {
        $row = $this->rows[$id] ?? null;
        if ($row === null) {
            return null;
        }

        return new Activity(
            $id,
            $this->references->find($row['referenceId']),
            $row['season'],
            $row['startDate'],
            $row['endDate'],
            $row['description'],
            $row['startTime'],
            $row['endTime'],
        );
    }

    public function findByStart(int $referenceId, string $startDate): ?Activity
    {
        foreach ($this->rows as $id => $row) {
            if ($row['referenceId'] === $referenceId && $row['startDate'] === $startDate) {
                return $this->find($id);
            }
        }

        return null;
    }

    public function create(int $referenceId, int $season, string $startDate, ?string $startTime, string $endDate, ?string $endTime, ?string $description): int
    {
        $id = $this->nextId++;
        $this->update($id, $referenceId, $season, $startDate, $startTime, $endDate, $endTime, $description);

        return $id;
    }

    public function update(int $id, int $referenceId, int $season, string $startDate, ?string $startTime, string $endDate, ?string $endTime, ?string $description): void
    {
        $this->rows[$id] = compact('referenceId', 'season', 'startDate', 'startTime', 'endDate', 'endTime', 'description');
    }

    public function delete(int $id): void
    {
        unset($this->rows[$id]);
    }

    public function hasLogs(int $id): bool
    {
        return isset($this->withLogs[$id]);
    }

    public function hasQslTemplates(int $id): bool
    {
        return isset($this->withTemplates[$id]);
    }
}
