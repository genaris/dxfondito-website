<?php

declare(strict_types=1);

namespace DxFondito\Activities;

interface ActivityStore
{
    /**
     * @return list<int> The seasons that have activities.
     */
    public function seasons(): array;

    /**
     * @return list<Activity> The most recent activity first (FR-PUB-13).
     */
    public function bySeason(int $season): array;

    public function find(int $id): ?Activity;

    public function findByStart(int $referenceId, string $startDate): ?Activity;

    public function create(int $referenceId, int $season, string $startDate, string $endDate, ?string $description): int;

    public function update(int $id, int $referenceId, int $season, string $startDate, string $endDate, ?string $description): void;

    public function delete(int $id): void;

    public function hasLogs(int $id): bool;
}
