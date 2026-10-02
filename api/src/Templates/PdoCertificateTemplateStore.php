<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use Closure;
use PDO;

final class PdoCertificateTemplateStore implements CertificateTemplateStore
{
    private const SELECT = 'SELECT t.season, l.points, t.stored_name, t.fields
        FROM certificate_templates t JOIN certificate_levels l ON l.id = t.level_id';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function find(int $season, int $points): ?CertificateTemplate
    {
        $statement = ($this->pdo)()->prepare(self::SELECT . ' WHERE t.season = ? AND l.points = ?');
        $statement->execute([$season, $points]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    public function all(): array
    {
        $statement = ($this->pdo)()->query(self::SELECT . ' ORDER BY t.season DESC, l.points');

        return array_map(self::fromRow(...), $statement->fetchAll());
    }

    public function save(int $season, int $points, string $storedName, array $fields): bool
    {
        $statement = ($this->pdo)()->prepare(
            'INSERT INTO certificate_templates (season, level_id, stored_name, fields)
             SELECT ?, id, ?, ? FROM certificate_levels WHERE points = ?
             ON DUPLICATE KEY UPDATE stored_name = VALUES(stored_name), fields = VALUES(fields)'
        );
        $statement->execute([$season, $storedName, json_encode($fields, JSON_THROW_ON_ERROR), $points]);

        return $this->find($season, $points) !== null;
    }

    public function delete(int $season, int $points): void
    {
        ($this->pdo)()->prepare(
            'DELETE t FROM certificate_templates t JOIN certificate_levels l ON l.id = t.level_id
             WHERE t.season = ? AND l.points = ?'
        )->execute([$season, $points]);
    }

    public function levels(int $season): array
    {
        $statement = ($this->pdo)()->prepare(
            'SELECT l.points FROM certificate_templates t JOIN certificate_levels l ON l.id = t.level_id
             WHERE t.season = ? ORDER BY l.points'
        );
        $statement->execute([$season]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): CertificateTemplate
    {
        return new CertificateTemplate(
            season: (int) $row['season'],
            points: (int) $row['points'],
            storedName: $row['stored_name'],
            fields: json_decode($row['fields'], true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
