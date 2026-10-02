<?php

declare(strict_types=1);

namespace DxFondito\Tests\Activities;

use DxFondito\Activities\Reference;
use DxFondito\Activities\ReferenceStore;
use DxFondito\Activities\Series;

final class MemoryReferenceStore implements ReferenceStore
{
    /** @var array<int, Series> */
    public array $series;

    /** @var array<int, Reference> */
    public array $references = [];

    /** @var array<int, true> The references with activities. */
    public array $withActivities = [];

    private int $nextId = 1;

    public function __construct()
    {
        $this->series = [1 => new Series(1, 'DPS', 'Puestos de Salud'), 2 => new Series(2, 'EFE', 'Efemérides')];
    }

    public function allSeries(): array
    {
        return array_values($this->series);
    }

    public function findSeries(int $id): ?Series
    {
        return $this->series[$id] ?? null;
    }

    public function all(): array
    {
        $references = array_values($this->references);
        usort($references, static fn (Reference $a, Reference $b): int => [$a->series->code, $a->number] <=> [$b->series->code, $b->number]);

        return $references;
    }

    public function find(int $id): ?Reference
    {
        return $this->references[$id] ?? null;
    }

    public function findByNumber(int $seriesId, int $number): ?Reference
    {
        foreach ($this->references as $reference) {
            if ($reference->series->id === $seriesId && $reference->number === $number) {
                return $reference;
            }
        }

        return null;
    }

    public function highestNumber(int $seriesId): int
    {
        $numbers = array_map(
            static fn (Reference $reference): int => $reference->number,
            array_filter($this->references, static fn (Reference $reference): bool => $reference->series->id === $seriesId),
        );

        return $numbers === [] ? 0 : max($numbers);
    }

    public function create(int $seriesId, int $number, string $name, ?string $description): int
    {
        $id = $this->nextId++;
        $this->references[$id] = new Reference($id, $this->series[$seriesId], $number, $name, $description);

        return $id;
    }

    public function update(int $id, int $seriesId, int $number, string $name, ?string $description): void
    {
        $this->references[$id] = new Reference($id, $this->series[$seriesId], $number, $name, $description);
    }

    public function delete(int $id): void
    {
        unset($this->references[$id]);
    }

    public function hasActivities(int $id): bool
    {
        return isset($this->withActivities[$id]);
    }
}
