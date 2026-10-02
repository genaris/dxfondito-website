<?php

declare(strict_types=1);

namespace DxFondito\Registry;

interface LicenseeStore
{
    /**
     * Replaces the licensees of one country.
     *
     * @param array<string, string> $licensees The name of each call sign.
     */
    public function replace(string $country, array $licensees, string $sourceUrl): void;

    /**
     * The name of a call sign in the registries, as the registry gives it, or null.
     */
    public function name(string $callSign): ?string;

    /**
     * The last update of each registry.
     *
     * @return list<array{country: string, count: int, sourceUrl: string, updatedAt: string}>
     */
    public function updates(): array;
}
