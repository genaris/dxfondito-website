<?php

declare(strict_types=1);

namespace DxFondito\Logs;

use PDO;

/**
 * Fills the e-mail address of the contacts that have none, from the stored ADIF file of their log
 * (migration 0007). The call sign and the time of a record find its contact, as in StationBackfill.
 */
final class EmailBackfill
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
             WHERE c.email IS NULL ORDER BY l.id'
        )->fetchAll();
        $update = $this->pdo->prepare('UPDATE contacts SET email = ? WHERE id = ?');
        $select = $this->pdo->prepare('SELECT id, call_sign, qso_at FROM contacts WHERE log_id = ? AND email IS NULL');

        foreach ($logs as $log) {
            $content = $this->files->read($log['stored_name']);
            if ($content === null) {
                continue;
            }
            $emails = self::emails($content);
            $select->execute([$log['id']]);
            foreach ($select->fetchAll() as $contact) {
                $email = $emails[$contact['call_sign'] . '|' . $contact['qso_at']] ?? null;
                if ($email !== null) {
                    $update->execute([$email, $contact['id']]);
                    $changed++;
                }
            }
        }

        return $changed;
    }

    /**
     * The address of each valid record of an ADIF file, by call sign and time.
     *
     * @return array<string, string>
     */
    public static function emails(string $content): array
    {
        $emails = [];
        foreach (AdifReader::read($content) as $index => $fields) {
            $contact = RecordReader::read($index + 1, $fields);
            if ($contact instanceof Contact && $contact->email !== null) {
                $emails[$contact->callSign . '|' . $contact->qsoAt] = $contact->email;
            }
        }

        return $emails;
    }
}
