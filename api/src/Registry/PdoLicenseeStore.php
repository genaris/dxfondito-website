<?php

declare(strict_types=1);

namespace DxFondito\Registry;

use Closure;
use PDO;
use Throwable;

final class PdoLicenseeStore implements LicenseeStore
{
    private const BATCH = 500;

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function replace(string $country, array $licensees, string $sourceUrl): void
    {
        $pdo = ($this->pdo)();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM licensees WHERE country = ?')->execute([$country]);
            foreach (array_chunk($licensees, self::BATCH, true) as $batch) {
                $values = [];
                foreach ($batch as $callSign => $name) {
                    array_push($values, (string) $callSign, $country, $name);
                }
                // A call sign of a different country stays with the newest list.
                $pdo->prepare(
                    'INSERT INTO licensees (call_sign, country, name) VALUES '
                    . implode(', ', array_fill(0, count($batch), '(?, ?, ?)'))
                    . ' ON DUPLICATE KEY UPDATE country = VALUES(country), name = VALUES(name)'
                )->execute($values);
            }
            $pdo->prepare(
                'INSERT INTO licensee_updates (country, licensee_count, source_url, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE licensee_count = VALUES(licensee_count), source_url = VALUES(source_url),
                 updated_at = VALUES(updated_at)'
            )->execute([$country, count($licensees), $sourceUrl]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function name(string $callSign): ?string
    {
        $statement = ($this->pdo)()->prepare('SELECT name FROM licensees WHERE call_sign = ?');
        $statement->execute([$callSign]);
        $name = $statement->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function updates(): array
    {
        return array_map(
            static fn (array $row): array => [
                'country' => $row['country'],
                'count' => (int) $row['licensee_count'],
                'sourceUrl' => $row['source_url'],
                'updatedAt' => str_replace(' ', 'T', $row['updated_at']) . 'Z',
            ],
            ($this->pdo)()->query('SELECT country, licensee_count, source_url, updated_at FROM licensee_updates ORDER BY country')->fetchAll(),
        );
    }
}
