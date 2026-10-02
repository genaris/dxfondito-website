<?php

declare(strict_types=1);

namespace DxFondito\Templates;

/**
 * The QSL card template of one operator in one activity (FR-QSL-1).
 */
final class QslTemplate
{
    /**
     * @param array<string, array{x: int, y: int, width: int, height: int, colour: string, align: string, font: string}> $fields
     */
    public function __construct(
        public readonly int $activityId,
        public readonly int $operatorId,
        public readonly string $operatorCallSign,
        public readonly string $storedName,
        public readonly array $fields,
    ) {
    }
}
