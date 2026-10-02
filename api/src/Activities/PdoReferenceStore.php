<?php

declare(strict_types=1);

namespace DxFondito\Activities;

use Closure;
use PDO;

final class PdoReferenceStore implements ReferenceStore
{
    private const SELECT = 'SELECT r.id, r.number, r.name, r.description, s.id AS series_id, s.code AS series_code, s.name AS series_name
        FROM refs r JOIN series s ON s.id = r.series_id';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function allSeries(): array
    {
        return array_map(
            static fn (array $row): Series => new Series((int) $row['id'], $row['code'], $row['name']),
            ($this->pdo)()->query('SELECT id, code, name FROM series ORDER BY code')->fetchAll(),
        );
    }

    public function findSeries(int $id): ?Series
    {
        $statement = ($this->pdo)()->prepare('SELECT id, code, name FROM series WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : new Series((int) $row['id'], $row['code'], $row['name']);
    }

    public function all(): array
    {
        return array_map(
            self::fromRow(...),
            ($this->pdo)()->query(self::SELECT . ' ORDER BY s.code, r.number')->fetchAll(),
        );
    }

    public function find(int $id): ?Reference
    {
        return $this->findOne(self::SELECT . ' WHERE r.id = ?', [$id]);
    }

    public function findByNumber(int $seriesId, int $number): ?Reference
    {
        return $this->findOne(self::SELECT . ' WHERE r.series_id = ? AND r.number = ?', [$seriesId, $number]);
    }

    public function highestNumber(int $seriesId): int
    {
        $statement = ($this->pdo)()->prepare('SELECT COALESCE(MAX(number), 0) FROM refs WHERE series_id = ?');
        $statement->execute([$seriesId]);

        return (int) $statement->fetchColumn();
    }

    public function create(int $seriesId, int $number, string $name, ?string $description): int
    {
        $pdo = ($this->pdo)();
        $pdo->prepare('INSERT INTO refs (series_id, number, name, description) VALUES (?, ?, ?, ?)')
            ->execute([$seriesId, $number, $name, $description]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, int $seriesId, int $number, string $name, ?string $description): void
    {
        ($this->pdo)()->prepare('UPDATE refs SET series_id = ?, number = ?, name = ?, description = ? WHERE id = ?')
            ->execute([$seriesId, $number, $name, $description, $id]);
    }

    public function delete(int $id): void
    {
        ($this->pdo)()->prepare('DELETE FROM refs WHERE id = ?')->execute([$id]);
    }

    public function hasActivities(int $id): bool
    {
        $statement = ($this->pdo)()->prepare('SELECT 1 FROM activities WHERE reference_id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param list<mixed> $params
     */
    private function findOne(string $sql, array $params): ?Reference
    {
        $statement = ($this->pdo)()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): Reference
    {
        return new Reference(
            id: (int) $row['id'],
            series: new Series((int) $row['series_id'], $row['series_code'], $row['series_name']),
            number: (int) $row['number'],
            name: $row['name'],
            description: $row['description'],
        );
    }
}
