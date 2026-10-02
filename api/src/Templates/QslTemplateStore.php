<?php

declare(strict_types=1);

namespace DxFondito\Templates;

interface QslTemplateStore
{
    public function find(int $activityId, int $operatorId): ?QslTemplate;

    /**
     * @return list<QslTemplate>
     */
    public function byActivity(int $activityId): array;

    /**
     * Saves a new template, or changes the template of the operator in the activity.
     *
     * @param array<string, array<string, mixed>> $fields
     */
    public function save(int $activityId, int $operatorId, string $storedName, array $fields): void;

    public function delete(int $activityId, int $operatorId): void;

    /**
     * The activities and operators with a template, as "activityId:operatorId" keys.
     *
     * @param list<int> $activityIds
     * @return list<string>
     */
    public function keys(array $activityIds): array;
}
