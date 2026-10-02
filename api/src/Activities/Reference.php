<?php

declare(strict_types=1);

namespace DxFondito\Activities;

/**
 * A permanent reference, such as DPS-01. It stays the same in all seasons.
 */
final class Reference
{
    public const MAX_NUMBER = 999;

    public function __construct(
        public readonly int $id,
        public readonly Series $series,
        public readonly int $number,
        public readonly string $name,
        public readonly ?string $description,
    ) {
    }

    /**
     * The series, a hyphen and the number with two or more digits (FR-REF-5).
     */
    public static function code(string $seriesCode, int $number): string
    {
        return sprintf('%s-%02d', $seriesCode, $number);
    }

    public function referenceCode(): string
    {
        return self::code($this->series->code, $this->number);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->referenceCode(),
            'seriesId' => $this->series->id,
            'series' => $this->series->code,
            'number' => $this->number,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
