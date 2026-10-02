<?php

declare(strict_types=1);

namespace DxFondito\Templates;

/**
 * The certificate template of one level in one season (FR-CER-1).
 */
final class CertificateTemplate
{
    /**
     * @param int $points The level, such as 5.
     * @param array<string, array{x: int, y: int, width: int, height: int, colour: string, align: string, font: string}> $fields
     */
    public function __construct(
        public readonly int $season,
        public readonly int $points,
        public readonly string $storedName,
        public readonly array $fields,
    ) {
    }
}
