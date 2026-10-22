<?php

declare(strict_types=1);

namespace DxFondito\Live;

use Closure;
use DxFondito\Logs\Frequency;
use DxFondito\Registry\LicenseeName;
use PDO;
use PDOException;
use Throwable;

final class PdoLiveStore implements LiveStore
{
    private const SELECT = 'SELECT c.client_id, c.activity_id, l.operator_id, o.call_sign AS operator_call_sign,
            c.station_call_sign, c.call_sign, c.base_call_sign, c.name, c.qso_at, c.frequency, c.band, c.mode,
            c.rst_sent, c.rst_rcvd, c.notes, c.entered_by, e.call_sign AS entered_by_call_sign, c.updated_at, c.version
        FROM contacts c
        JOIN logs l ON l.id = c.log_id
        JOIN users o ON o.id = l.operator_id
        JOIN users e ON e.id = c.entered_by';

    // The contacts of an ADIF file. The station is the station call sign of the contact, or the call sign of the account.
    private const RECORDS = 'SELECT c.call_sign, c.qso_at, c.frequency, c.band, c.mode, c.rst_sent, c.rst_rcvd, c.name,
            COALESCE(c.station_call_sign, o.call_sign) AS station, o.call_sign AS operator, c.notes
        FROM contacts c
        JOIN logs l ON l.id = c.log_id
        JOIN users o ON o.id = l.operator_id';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function find(string $clientId): ?LiveContact
    {
        $statement = ($this->pdo)()->prepare(self::SELECT . ' WHERE c.client_id = ?');
        $statement->execute([$clientId]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    public function save(LiveContact $contact, ?int $expectedVersion, string $fileName): array
    {
        $pdo = ($this->pdo)();
        $pdo->beginTransaction();
        try {
            $liveKey = $contact->activityId . ':' . $contact->operatorId;
            // No FOR UPDATE: two locks of a line that does not exist yet stop each other (a deadlock) at the INSERT.
            $existing = $pdo->prepare('SELECT id FROM logs WHERE live_key = ?');
            $existing->execute([$liveKey]);
            $logId = $existing->fetchColumn();
            $logCreated = false;
            if ($logId === false) {
                // The unique key of live_key makes one log, also with two requests at the same time.
                // LAST_INSERT_ID(id) gives the identifier of the log of the other request.
                // An INSERT uses a number of AUTO_INCREMENT also for a line that exists. Thus the SELECT comes first.
                $log = $pdo->prepare(
                    "INSERT INTO logs (activity_id, operator_id, uploaded_by, source, live_key, file_name, stored_name, contact_count, uploaded_at)
                     VALUES (?, ?, ?, 'live', ?, ?, NULL, 0, ?)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
                );
                $log->execute([$contact->activityId, $contact->operatorId, $contact->enteredById, $liveKey, $fileName, $contact->updatedAt]);
                $logId = $pdo->lastInsertId();
                // MySQL gives 1 for a new line, and 0 for a line that exists and did not change.
                $logCreated = $log->rowCount() === 1;
            }
            $logId = (int) $logId;

            $values = [
                $logId,
                $contact->callSign,
                $contact->baseCallSign,
                $contact->stationCallSign,
                $contact->name,
                $contact->qsoAt,
                $contact->frequency,
                $contact->band,
                $contact->mode,
                $contact->rstSent,
                $contact->rstRcvd,
                $contact->notes,
                $contact->enteredById,
                $contact->updatedAt,
                $contact->version,
            ];
            $oldLogId = false;
            if ($expectedVersion === null) {
                try {
                    $pdo->prepare(
                        'INSERT INTO contacts (log_id, call_sign, base_call_sign, station_call_sign, name, qso_at, frequency, band,
                            mode, rst_sent, rst_rcvd, notes, entered_by, updated_at, version, client_id, activity_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([...$values, $contact->clientId, $contact->activityId]);
                    $saved = true;
                } catch (PDOException $e) {
                    // 1062: a different request made the contact first.
                    if (($e->errorInfo[1] ?? null) !== 1062) {
                        throw $e;
                    }
                    $saved = false;
                }
            } else {
                $old = $pdo->prepare('SELECT log_id FROM contacts WHERE client_id = ? FOR UPDATE');
                $old->execute([$contact->clientId]);
                $oldLogId = $old->fetchColumn();
                $update = $pdo->prepare(
                    'UPDATE contacts SET log_id = ?, call_sign = ?, base_call_sign = ?, station_call_sign = ?, name = ?, qso_at = ?,
                        frequency = ?, band = ?, mode = ?, rst_sent = ?, rst_rcvd = ?, notes = ?, entered_by = ?, updated_at = ?, version = ?
                     WHERE client_id = ? AND version = ?'
                );
                $update->execute([...$values, $contact->clientId, $expectedVersion]);
                $saved = $update->rowCount() === 1;
            }
            if (!$saved) {
                $pdo->rollBack();

                return ['saved' => false, 'logId' => $logId, 'logCreated' => false];
            }

            $pdo->prepare('DELETE FROM live_deletions WHERE client_id = ?')->execute([$contact->clientId]);
            $this->recount($pdo, $logId);
            if ($oldLogId !== false && (int) $oldLogId !== $logId) {
                $this->recount($pdo, (int) $oldLogId);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['saved' => true, 'logId' => $logId, 'logCreated' => $logCreated];
    }

    public function delete(LiveContact $contact, string $now): void
    {
        $pdo = ($this->pdo)();
        $pdo->beginTransaction();
        try {
            $old = $pdo->prepare('SELECT log_id FROM contacts WHERE client_id = ? FOR UPDATE');
            $old->execute([$contact->clientId]);
            $logId = $old->fetchColumn();
            $pdo->prepare('DELETE FROM contacts WHERE client_id = ?')->execute([$contact->clientId]);
            $pdo->prepare(
                'INSERT INTO live_deletions (client_id, activity_id, deleted_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE deleted_at = VALUES(deleted_at)'
            )->execute([$contact->clientId, $contact->activityId, $now]);
            if ($logId !== false) {
                $this->recount($pdo, (int) $logId);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function contacts(int $activityId, ?string $since): array
    {
        $sql = self::SELECT . ' WHERE c.activity_id = ? AND c.client_id IS NOT NULL';
        $params = [$activityId];
        if ($since !== null) {
            $sql .= ' AND c.updated_at >= ?';
            $params[] = $since;
        }
        $statement = ($this->pdo)()->prepare($sql . ' ORDER BY c.qso_at, c.id');
        $statement->execute($params);

        return array_map(self::fromRow(...), $statement->fetchAll());
    }

    public function deletions(int $activityId, string $since): array
    {
        $statement = ($this->pdo)()->prepare('SELECT client_id FROM live_deletions WHERE activity_id = ? AND deleted_at >= ?');
        $statement->execute([$activityId, $since]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function uploadedContacts(int $activityId): array
    {
        $statement = ($this->pdo)()->prepare(
            "SELECT c.call_sign, c.qso_at, COALESCE(c.station_call_sign, o.call_sign) AS station, c.band
             FROM contacts c JOIN logs l ON l.id = c.log_id JOIN users o ON o.id = l.operator_id
             WHERE c.activity_id = ? AND l.source = 'adif'
             ORDER BY c.qso_at, c.id"
        );
        $statement->execute([$activityId]);

        return array_map(
            static fn (array $row): array => [
                'callSign' => $row['call_sign'],
                'qsoAt' => str_replace(' ', 'T', $row['qso_at']) . 'Z',
                'station' => $row['station'],
                'band' => $row['band'],
            ],
            $statement->fetchAll(),
        );
    }

    public function names(): array
    {
        $names = [];
        foreach (($this->pdo)()->query('SELECT call_sign, name FROM licensees')->fetchAll() as $row) {
            $names[$row['call_sign']] = LicenseeName::format($row['name']);
        }
        foreach (($this->pdo)()->query("SELECT call_sign, name FROM address_book WHERE name IS NOT NULL AND name <> ''")->fetchAll() as $row) {
            $names[$row['call_sign']] = $row['name'];
        }

        return $names;
    }

    public function logRecords(int $logId): array
    {
        $statement = ($this->pdo)()->prepare(self::RECORDS . ' WHERE c.log_id = ? ORDER BY c.qso_at, c.id');
        $statement->execute([$logId]);

        return array_map(self::record(...), $statement->fetchAll());
    }

    public function operatorRecords(int $activityId, int $operatorId): array
    {
        $statement = ($this->pdo)()->prepare(self::RECORDS . ' WHERE c.activity_id = ? AND l.operator_id = ? ORDER BY c.qso_at, c.id');
        $statement->execute([$activityId, $operatorId]);

        return array_map(self::record(...), $statement->fetchAll());
    }

    /**
     * The number of contacts of a live log. A live log without contacts is deleted.
     */
    private function recount(PDO $pdo, int $logId): void
    {
        $pdo->prepare('UPDATE logs SET contact_count = (SELECT COUNT(*) FROM contacts WHERE log_id = ?) WHERE id = ?')
            ->execute([$logId, $logId]);
        $pdo->prepare("DELETE FROM logs WHERE id = ? AND source = 'live' AND contact_count = 0")->execute([$logId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): LiveContact
    {
        return new LiveContact(
            clientId: $row['client_id'],
            activityId: (int) $row['activity_id'],
            operatorId: (int) $row['operator_id'],
            operatorCallSign: $row['operator_call_sign'],
            stationCallSign: $row['station_call_sign'],
            callSign: $row['call_sign'],
            baseCallSign: $row['base_call_sign'],
            name: $row['name'],
            qsoAt: $row['qso_at'],
            // DECIMAL(10, 6) gives zeros at the end, such as 7.130000.
            frequency: Frequency::text($row['frequency']),
            band: $row['band'],
            mode: $row['mode'],
            rstSent: $row['rst_sent'],
            rstRcvd: $row['rst_rcvd'],
            notes: $row['notes'],
            enteredById: (int) $row['entered_by'],
            enteredByCallSign: $row['entered_by_call_sign'],
            updatedAt: $row['updated_at'],
            version: (int) $row['version'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function record(array $row): AdifRecord
    {
        return new AdifRecord(
            callSign: $row['call_sign'],
            qsoAt: $row['qso_at'],
            frequency: $row['frequency'] === null ? null : Frequency::text($row['frequency']),
            band: $row['band'],
            mode: $row['mode'],
            rstSent: $row['rst_sent'],
            rstRcvd: $row['rst_rcvd'],
            name: $row['name'],
            station: $row['station'],
            operator: $row['operator'],
            notes: $row['notes'],
        );
    }
}
