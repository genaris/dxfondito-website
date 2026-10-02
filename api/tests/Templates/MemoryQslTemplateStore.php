<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Templates\QslTemplate;
use DxFondito\Templates\QslTemplateStore;

final class MemoryQslTemplateStore implements QslTemplateStore
{
    /** @var array<string, QslTemplate> */
    public array $templates = [];

    public function find(int $activityId, int $operatorId): ?QslTemplate
    {
        return $this->templates[$activityId . ':' . $operatorId] ?? null;
    }

    public function byActivity(int $activityId): array
    {
        return array_values(array_filter($this->templates, static fn (QslTemplate $template): bool => $template->activityId === $activityId));
    }

    public function save(int $activityId, int $operatorId, string $storedName, array $fields): void
    {
        $this->templates[$activityId . ':' . $operatorId] = new QslTemplate($activityId, $operatorId, 'OP' . $operatorId, $storedName, $fields);
    }

    public function delete(int $activityId, int $operatorId): void
    {
        unset($this->templates[$activityId . ':' . $operatorId]);
    }

    public function keys(array $activityIds): array
    {
        return array_values(array_filter(
            array_keys($this->templates),
            static fn (string $key): bool => in_array((int) explode(':', $key)[0], $activityIds, true),
        ));
    }
}
