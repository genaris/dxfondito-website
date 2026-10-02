<?php

declare(strict_types=1);

namespace DxFondito\Activities;

use Closure;
use PDO;

final class PdoActivityStore implements ActivityStore
{
    private const SELECT = 'SELECT a.id AS activity_id, a.season, a.start_date, a.end_date, a.description AS activity_description,
            r.id, r.number, r.name, r.description, s.id AS series_id, s.code AS series_code, s.name AS series_name
        FROM activities a
        JOIN refs r ON r.id = a.reference_id
        JOIN series s ON s.id = r.series_id';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function seasons(): array
    {
        return array_map(
            'intval',
            ($this->pdo)()->query('SELECT DISTINCT season FROM activities ORDER BY season DESC')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function bySeason(int $season): array
    {
        $statement = ($this->pdo)()->prepare(
            self::SELECT . ' WHERE a.season = ? ORDER BY a.start_date DESC, s.code, r.number'
        );
        $statement->execute([$season]);

        return array_map(self::fromRow(...), $statement->fetchAll());
    }

    public function find(int $id): ?Activity
    {
        return $this->findOne(self::SELECT . ' WHERE a.id = ?', [$id]);
    }

    public function findByStart(int $referenceId, string $startDate): ?Activity
    {
        return $this->findOne(self::SELECT . ' WHERE a.reference_id = ? AND a.start_date = ?', [$referenceId, $startDate]);
    }

    public function create(int $referenceId, int $season, string $startDate, string $endDate, ?string $description): int
    {
        $pdo = ($this->pdo)();
        $pdo->prepare(
            'INSERT INTO activities (reference_id, season, start_date, end_date, description) VALUES (?, ?, ?, ?, ?)'
        )->execute([$referenceId, $season, $startDate, $endDate, $description]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, int $referenceId, int $season, string $startDate, string $endDate, ?string $description): void
    {
        ($this->pdo)()->prepare(
            'UPDATE activities SET reference_id = ?, season = ?, start_date = ?, end_date = ?, description = ? WHERE id = ?'
        )->execute([$referenceId, $season, $startDate, $endDate, $description, $id]);
    }

    public function delete(int $id): void
    {
        ($this->pdo)()->prepare('DELETE FROM activities WHERE id = ?')->execute([$id]);
    }

    public function hasLogs(int $id): bool
    {
        $statement = ($this->pdo)()->prepare('SELECT 1 FROM logs WHERE activity_id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param list<mixed> $params
     */
    private function findOne(string $sql, array $params): ?Activity
    {
        $statement = ($this->pdo)()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): Activity
    {
        return new Activity(
            id: (int) $row['activity_id'],
            reference: PdoReferenceStore::fromRow($row),
            season: (int) $row['season'],
            startDate: $row['start_date'],
            endDate: $row['end_date'],
            description: $row['activity_description'],
        );
    }
}
