<?php

declare(strict_types=1);

namespace DxFondito\Logs;

use PDO;

/**
 * Fills the station call sign of the contacts that have none, from the stored ADIF file of their log
 * (migration 0003). The file gives the station of each record. The call sign and the time of a record
 * find its contact. A contact without a station in the file stays without a station.
 */
final class StationBackfill
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly FileStore $files,
    ) {
    }

    /**
     * @return int The number of changed contacts.
     */
    public function run(): int
    {
        $changed = 0;
        $logs = $this->pdo->query(
            'SELECT DISTINCT l.id, l.stored_name FROM logs l JOIN contacts c ON c.log_id = l.id
             WHERE c.station_call_sign IS NULL ORDER BY l.id'
        )->fetchAll();
        $update = $this->pdo->prepare('UPDATE contacts SET station_call_sign = ? WHERE id = ?');
        $select = $this->pdo->prepare(
            'SELECT id, call_sign, qso_at FROM contacts WHERE log_id = ? AND station_call_sign IS NULL'
        );

        foreach ($logs as $log) {
            $content = $this->files->read($log['stored_name']);
            if ($content === null) {
                continue;
            }
            $stations = self::stations($content);
            $select->execute([$log['id']]);
            foreach ($select->fetchAll() as $contact) {
                $station = $stations[$contact['call_sign'] . '|' . $contact['qso_at']] ?? null;
                if ($station !== null) {
                    $update->execute([$station, $contact['id']]);
                    $changed++;
                }
            }
        }

        return $changed;
    }

    /**
     * The station of each valid record of an ADIF file, by call sign and time.
     *
     * @return array<string, string>
     */
    public static function stations(string $content): array
    {
        $stations = [];
        foreach (AdifReader::read($content) as $index => $fields) {
            $contact = RecordReader::read($index + 1, $fields);
            if ($contact instanceof Contact && $contact->stationCallSign !== null) {
                $stations[$contact->callSign . '|' . $contact->qsoAt] = $contact->stationCallSign;
            }
        }

        return $stations;
    }
}
