<?php

declare(strict_types=1);

namespace DxFondito\Logs;

use Closure;
use PDO;
use Throwable;

final class PdoLogStore implements LogStore
{
    // Each INSERT statement has this number of contacts or fewer.
    private const BATCH = 500;

    private const SELECT = 'SELECT l.id, l.activity_id, l.operator_id, o.call_sign AS operator_call_sign,
            l.uploaded_by, b.call_sign AS uploaded_by_call_sign, l.file_name, l.stored_name, l.contact_count, l.uploaded_at
        FROM logs l
        JOIN users o ON o.id = l.operator_id
        JOIN users b ON b.id = l.uploaded_by';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function create(
        int $activityId,
        int $operatorId,
        int $uploadedBy,
        string $fileName,
        string $storedName,
        array $contacts,
        string $uploadedAt,
    ): int {
        $pdo = ($this->pdo)();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO logs (activity_id, operator_id, uploaded_by, file_name, stored_name, contact_count, uploaded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$activityId, $operatorId, $uploadedBy, $fileName, $storedName, count($contacts), $uploadedAt]);
            $logId = (int) $pdo->lastInsertId();

            foreach (array_chunk($contacts, self::BATCH) as $batch) {
                $values = [];
                foreach ($batch as $contact) {
                    array_push(
                        $values,
                        $logId,
                        $activityId,
                        $contact->callSign,
                        $contact->baseCallSign,
                        $contact->name,
                        $contact->qsoAt,
                        $contact->frequency,
                        $contact->band,
                        $contact->mode,
                        $contact->rstSent,
                        $contact->rstRcvd,
                    );
                }
                $rows = implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
                $pdo->prepare(
                    'INSERT INTO contacts (log_id, activity_id, call_sign, base_call_sign, name, qso_at, frequency, band, mode, rst_sent, rst_rcvd)
                     VALUES ' . $rows
                )->execute($values);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $logId;
    }

    public function find(int $id): ?Log
    {
        $statement = ($this->pdo)()->prepare(self::SELECT . ' WHERE l.id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    public function byActivity(int $activityId): array
    {
        $statement = ($this->pdo)()->prepare(self::SELECT . ' WHERE l.activity_id = ? ORDER BY l.uploaded_at DESC, l.id DESC');
        $statement->execute([$activityId]);

        return array_map(self::fromRow(...), $statement->fetchAll());
    }

    public function contacts(int $logId): array
    {
        $statement = ($this->pdo)()->prepare(
            'SELECT call_sign, base_call_sign, name, qso_at, frequency, band, mode, rst_sent, rst_rcvd
             FROM contacts WHERE log_id = ? ORDER BY qso_at, id'
        );
        $statement->execute([$logId]);

        return array_map(
            static fn (array $row): Contact => new Contact(
                record: 0,
                callSign: $row['call_sign'],
                baseCallSign: $row['base_call_sign'],
                name: $row['name'],
                qsoAt: $row['qso_at'],
                // DECIMAL(10, 6) gives zeros at the end, such as 7.074000.
                frequency: $row['frequency'] === null ? null : rtrim(rtrim($row['frequency'], '0'), '.'),
                band: $row['band'],
                mode: $row['mode'],
                rstSent: $row['rst_sent'],
                rstRcvd: $row['rst_rcvd'],
                stationCallSign: null,
            ),
            $statement->fetchAll(),
        );
    }

    public function delete(int $id): void
    {
        ($this->pdo)()->prepare('DELETE FROM logs WHERE id = ?')->execute([$id]);
    }

    public function operators(int $activityId): array
    {
        $statement = ($this->pdo)()->prepare(
            'SELECT DISTINCT u.id, u.call_sign FROM logs l JOIN users u ON u.id = l.operator_id
             WHERE l.activity_id = ? ORDER BY u.call_sign'
        );
        $statement->execute([$activityId]);

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'callSign' => $row['call_sign']],
            $statement->fetchAll(),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): Log
    {
        return new Log(
            id: (int) $row['id'],
            activityId: (int) $row['activity_id'],
            operatorId: (int) $row['operator_id'],
            operatorCallSign: $row['operator_call_sign'],
            uploadedById: (int) $row['uploaded_by'],
            uploadedByCallSign: $row['uploaded_by_call_sign'],
            fileName: $row['file_name'],
            storedName: $row['stored_name'],
            contactCount: (int) $row['contact_count'],
            uploadedAt: $row['uploaded_at'],
        );
    }
}
