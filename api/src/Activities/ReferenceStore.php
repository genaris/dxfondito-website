<?php

declare(strict_types=1);

namespace DxFondito\Activities;

interface ReferenceStore
{
    /**
     * @return list<Series>
     */
    public function allSeries(): array;

    public function findSeries(int $id): ?Series;

    /**
     * @return list<Reference> In the order of the series code and the number.
     */
    public function all(): array;

    public function find(int $id): ?Reference;

    public function findByNumber(int $seriesId, int $number): ?Reference;

    /**
     * The highest number of the series, or 0.
     */
    public function highestNumber(int $seriesId): int;

    public function create(int $seriesId, int $number, string $name, ?string $description): int;

    public function update(int $id, int $seriesId, int $number, string $name, ?string $description): void;

    public function delete(int $id): void;

    public function hasActivities(int $id): bool;
}
