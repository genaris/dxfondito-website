<?php

declare(strict_types=1);

namespace DxFondito\Tests\Registry;

use DxFondito\Registry\LicenseeStore;

final class MemoryLicenseeStore implements LicenseeStore
{
    /** @var array<string, array{country: string, name: string}> */
    public array $licensees = [];

    /** @var array<string, array{country: string, count: int, sourceUrl: string, updatedAt: string}> */
    public array $updates = [];

    public function replace(string $country, array $licensees, string $sourceUrl): void
    {
        $this->licensees = array_filter($this->licensees, static fn (array $item): bool => $item['country'] !== $country);
        foreach ($licensees as $callSign => $name) {
            $this->licensees[(string) $callSign] = ['country' => $country, 'name' => $name];
        }
        $this->updates[$country] = ['country' => $country, 'count' => count($licensees), 'sourceUrl' => $sourceUrl, 'updatedAt' => '2026-10-02T12:00:00Z'];
    }

    public function name(string $callSign): ?string
    {
        return $this->licensees[$callSign]['name'] ?? null;
    }

    public function updates(): array
    {
        return array_values($this->updates);
    }
}
