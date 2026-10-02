<?php

declare(strict_types=1);

namespace DxFondito\Templates;

interface CertificateTemplateStore
{
    public function find(int $season, int $points): ?CertificateTemplate;

    /**
     * @return list<CertificateTemplate> The newest season first, then the order of the points.
     */
    public function all(): array;

    /**
     * Saves a new template, or changes the template of the level in the season.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return bool False if the level does not exist.
     */
    public function save(int $season, int $points, string $storedName, array $fields): bool;

    public function delete(int $season, int $points): void;

    /**
     * The levels with a template in a season, such as [5, 10].
     *
     * @return list<int>
     */
    public function levels(int $season): array;
}
