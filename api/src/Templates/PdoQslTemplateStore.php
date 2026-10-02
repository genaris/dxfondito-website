<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use Closure;
use PDO;

final class PdoQslTemplateStore implements QslTemplateStore
{
    private const SELECT = 'SELECT t.activity_id, t.operator_id, u.call_sign, t.stored_name, t.fields
        FROM qsl_templates t JOIN users u ON u.id = t.operator_id';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function find(int $activityId, int $operatorId): ?QslTemplate
    {
        $statement = ($this->pdo)()->prepare(self::SELECT . ' WHERE t.activity_id = ? AND t.operator_id = ?');
        $statement->execute([$activityId, $operatorId]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    public function byActivity(int $activityId): array
    {
        $statement = ($this->pdo)()->prepare(self::SELECT . ' WHERE t.activity_id = ? ORDER BY u.call_sign');
        $statement->execute([$activityId]);

        return array_map(self::fromRow(...), $statement->fetchAll());
    }

    public function save(int $activityId, int $operatorId, string $storedName, array $fields): void
    {
        ($this->pdo)()->prepare(
            'INSERT INTO qsl_templates (activity_id, operator_id, stored_name, fields) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE stored_name = VALUES(stored_name), fields = VALUES(fields)'
        )->execute([$activityId, $operatorId, $storedName, json_encode($fields, JSON_THROW_ON_ERROR)]);
    }

    public function delete(int $activityId, int $operatorId): void
    {
        ($this->pdo)()->prepare('DELETE FROM qsl_templates WHERE activity_id = ? AND operator_id = ?')
            ->execute([$activityId, $operatorId]);
    }

    public function keys(array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($activityIds), '?'));
        $statement = ($this->pdo)()->prepare(
            'SELECT activity_id, operator_id FROM qsl_templates WHERE activity_id IN (' . $marks . ')'
        );
        $statement->execute(array_values($activityIds));

        return array_map(
            static fn (array $row): string => $row['activity_id'] . ':' . $row['operator_id'],
            $statement->fetchAll(),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): QslTemplate
    {
        return new QslTemplate(
            activityId: (int) $row['activity_id'],
            operatorId: (int) $row['operator_id'],
            operatorCallSign: $row['call_sign'],
            storedName: $row['stored_name'],
            fields: json_decode($row['fields'], true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
