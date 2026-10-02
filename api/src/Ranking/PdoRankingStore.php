<?php

declare(strict_types=1);

namespace DxFondito\Ranking;

use Closure;
use DxFondito\Activities\Reference;
use PDO;

final class PdoRankingStore implements RankingStore
{
    private const SELECT = 'SELECT c.id, c.base_call_sign, c.call_sign, c.name, c.qso_at, c.frequency, c.band, c.mode, c.rst_sent,
            a.id AS activity_id, a.start_date, a.season, r.id AS reference_id, r.number, r.name AS reference_name,
            s.code AS series_code, u.id AS operator_id, u.call_sign AS operator_call_sign
        FROM contacts c
        JOIN activities a ON a.id = c.activity_id
        JOIN refs r ON r.id = a.reference_id
        JOIN series s ON s.id = r.series_id
        JOIN logs l ON l.id = c.log_id
        JOIN users u ON u.id = l.operator_id';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function levels(): array
    {
        return array_map('intval', ($this->pdo)()->query('SELECT points FROM certificate_levels ORDER BY points')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function seasonReferences(int $season): array
    {
        // The index activities_season gives the activities of the season. The index contacts_activity_call
        // gives the base call signs of each activity without a read of the contact lines.
        $statement = ($this->pdo)()->prepare(
            'SELECT DISTINCT c.base_call_sign, a.reference_id, s.code, r.number
             FROM activities a
             JOIN contacts c ON c.activity_id = a.id
             JOIN refs r ON r.id = a.reference_id
             JOIN series s ON s.id = r.series_id
             WHERE a.season = ?'
        );
        $statement->execute([$season]);

        return array_map(
            static fn (array $row): array => [
                'callSign' => $row['base_call_sign'],
                'referenceId' => (int) $row['reference_id'],
                'seriesCode' => $row['code'],
                'number' => (int) $row['number'],
            ],
            $statement->fetchAll(),
        );
    }

    public function byParticipant(string $baseCallSign): array
    {
        return $this->rows(self::SELECT . ' WHERE c.base_call_sign = ?', [$baseCallSign]);
    }

    public function byActivity(int $activityId): array
    {
        return $this->rows(self::SELECT . ' WHERE c.activity_id = ?', [$activityId]);
    }

    /**
     * @param list<mixed> $params
     * @return list<ContactRow>
     */
    private function rows(string $sql, array $params): array
    {
        $statement = ($this->pdo)()->prepare($sql);
        $statement->execute($params);

        return array_map(
            static fn (array $row): ContactRow => new ContactRow(
                id: (int) $row['id'],
                baseCallSign: $row['base_call_sign'],
                callSign: $row['call_sign'],
                name: $row['name'],
                qsoAt: $row['qso_at'],
                frequency: $row['frequency'] === null ? null : rtrim(rtrim($row['frequency'], '0'), '.'),
                band: $row['band'],
                mode: $row['mode'],
                activityId: (int) $row['activity_id'],
                startDate: $row['start_date'],
                season: (int) $row['season'],
                referenceId: (int) $row['reference_id'],
                seriesCode: $row['series_code'],
                referenceCode: Reference::code($row['series_code'], (int) $row['number']),
                referenceName: $row['reference_name'],
                operatorCallSign: $row['operator_call_sign'],
                operatorId: (int) $row['operator_id'],
                rstSent: $row['rst_sent'],
            ),
            $statement->fetchAll(),
        );
    }
}
