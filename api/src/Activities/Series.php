<?php

declare(strict_types=1);

namespace DxFondito\Activities;

/**
 * A series of references: DPS or EFE (FR-REF-8).
 */
final class Series
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
    ) {
    }
}
