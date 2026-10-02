<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Templates\CertificateTemplate;
use DxFondito\Templates\CertificateTemplateStore;

final class MemoryCertificateTemplateStore implements CertificateTemplateStore
{
    /** @var array<string, CertificateTemplate> */
    public array $templates = [];

    public function find(int $season, int $points): ?CertificateTemplate
    {
        return $this->templates[$season . ':' . $points] ?? null;
    }

    public function all(): array
    {
        $all = array_values($this->templates);
        usort($all, static fn (CertificateTemplate $a, CertificateTemplate $b): int => [$b->season, $a->points] <=> [$a->season, $b->points]);

        return $all;
    }

    public function save(int $season, int $points, string $storedName, array $fields): bool
    {
        $this->templates[$season . ':' . $points] = new CertificateTemplate($season, $points, $storedName, $fields);

        return true;
    }

    public function delete(int $season, int $points): void
    {
        unset($this->templates[$season . ':' . $points]);
    }

    public function levels(int $season): array
    {
        $levels = array_map(
            static fn (CertificateTemplate $template): int => $template->points,
            array_filter($this->templates, static fn (CertificateTemplate $template): bool => $template->season === $season),
        );
        sort($levels);

        return array_values($levels);
    }
}
