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

    /**
     * The number of contacts of each activity of a season with contacts (FR-PUB-13b).
     *
     * @return array<int, int> The number of contacts by activity id.
     */
    public function contactCounts(int $season): array;

    public function findByStart(int $referenceId, string $startDate): ?Activity;

    public function create(int $referenceId, int $season, string $startDate, ?string $startTime, string $endDate, ?string $endTime, ?string $description): int;

    public function update(int $id, int $referenceId, int $season, string $startDate, ?string $startTime, string $endDate, ?string $endTime, ?string $description): void;

    public function delete(int $id): void;

    public function hasLogs(int $id): bool;

    public function hasQslTemplates(int $id): bool;
}
