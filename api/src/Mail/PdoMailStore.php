<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use Closure;
use PDO;

final class PdoMailStore implements MailStore
{
    private const DELIVERY = 'SELECT id, kind, base_call_sign, activity_id, season, points, certificate_date, method, status,
            recipient, subject, items, error, created_at, user_id FROM mail_deliveries';

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function knownEmails(array $callSigns): array
    {
        if ($callSigns === []) {
            return [];
        }
        $statement = ($this->pdo)()->prepare(
            'SELECT base_call_sign, email, MAX(qso_at) AS last_qso_at FROM contacts
             WHERE email IS NOT NULL AND base_call_sign IN (' . self::marks($callSigns) . ')
             GROUP BY base_call_sign, email ORDER BY base_call_sign, last_qso_at DESC'
        );
        $statement->execute(array_values($callSigns));
        $known = [];
        foreach ($statement->fetchAll() as $row) {
            $known[$row['base_call_sign']][] = ['email' => $row['email'], 'lastQsoAt' => $row['last_qso_at']];
        }

        return $known;
    }

    public function invalidEmails(): array
    {
        return array_fill_keys(($this->pdo)()->query('SELECT email FROM invalid_emails')->fetchAll(PDO::FETCH_COLUMN), true);
    }

    public function setInvalid(string $email, bool $invalid, int $userId): void
    {
        if ($invalid) {
            ($this->pdo)()->prepare(
                'INSERT INTO invalid_emails (email, marked_at, marked_by) VALUES (?, UTC_TIMESTAMP(), ?)
                 ON DUPLICATE KEY UPDATE marked_at = VALUES(marked_at), marked_by = VALUES(marked_by)'
            )->execute([$email, $userId]);
        } else {
            ($this->pdo)()->prepare('DELETE FROM invalid_emails WHERE email = ?')->execute([$email]);
        }
    }

    public function addressBook(): array
    {
        return array_map(
            self::entry(...),
            ($this->pdo)()->query('SELECT * FROM address_book ORDER BY call_sign')->fetchAll(),
        );
    }

    public function entries(array $callSigns): array
    {
        if ($callSigns === []) {
            return [];
        }
        $statement = ($this->pdo)()->prepare('SELECT * FROM address_book WHERE call_sign IN (' . self::marks($callSigns) . ')');
        $statement->execute(array_values($callSigns));
        $entries = [];
        foreach ($statement->fetchAll() as $row) {
            $entries[$row['call_sign']] = self::entry($row);
        }

        return $entries;
    }

    public function saveEntry(AddressBookEntry $entry, int $userId): void
    {
        ($this->pdo)()->prepare(
            'INSERT INTO address_book (call_sign, name, email, no_mail, notes, updated_at, updated_by)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), no_mail = VALUES(no_mail),
             notes = VALUES(notes), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)'
        )->execute([$entry->callSign, $entry->name, $entry->email, $entry->noMail ? 1 : 0, $entry->notes, $userId]);
    }

    public function deleteEntry(string $callSign): void
    {
        ($this->pdo)()->prepare('DELETE FROM address_book WHERE call_sign = ?')->execute([$callSign]);
    }

    public function template(string $kind, string $scope): ?array
    {
        $statement = ($this->pdo)()->prepare('SELECT subject, body FROM mail_templates WHERE kind = ? AND scope = ?');
        $statement->execute([$kind, $scope]);
        $row = $statement->fetch();

        return $row === false ? null : ['subject' => $row['subject'], 'body' => $row['body']];
    }

    public function saveTemplate(string $kind, string $scope, string $subject, string $body, int $userId): void
    {
        ($this->pdo)()->prepare(
            'INSERT INTO mail_templates (kind, scope, subject, body, updated_at, updated_by) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), updated_at = VALUES(updated_at),
             updated_by = VALUES(updated_by)'
        )->execute([$kind, $scope, $subject, $body, $userId]);
    }

    public function deleteTemplate(string $kind, string $scope): void
    {
        ($this->pdo)()->prepare('DELETE FROM mail_templates WHERE kind = ? AND scope = ?')->execute([$kind, $scope]);
    }

    public function activityDeliveries(int $activityId): array
    {
        $statement = ($this->pdo)()->prepare(self::DELIVERY . " WHERE kind = 'qsl' AND activity_id = ? ORDER BY id");
        $statement->execute([$activityId]);

        return array_map(self::delivery(...), $statement->fetchAll());
    }

    public function certificateDeliveries(int $season): array
    {
        $statement = ($this->pdo)()->prepare(self::DELIVERY . " WHERE kind = 'certificate' AND season = ? ORDER BY id");
        $statement->execute([$season]);

        return array_map(self::delivery(...), $statement->fetchAll());
    }

    public function record(Delivery $delivery): void
    {
        ($this->pdo)()->prepare(
            'INSERT INTO mail_deliveries (kind, base_call_sign, activity_id, season, points, certificate_date, method, status,
             recipient, subject, items, error, created_at, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)'
        )->execute([
            $delivery->kind,
            $delivery->baseCallSign,
            $delivery->activityId,
            $delivery->season,
            $delivery->points,
            $delivery->certificateDate,
            $delivery->method,
            $delivery->status,
            $delivery->recipient,
            $delivery->subject === null ? null : mb_substr($delivery->subject, 0, 255),
            $delivery->items === [] ? null : json_encode($delivery->items, JSON_THROW_ON_ERROR),
            $delivery->error === null ? null : mb_substr($delivery->error, 0, 500),
            $delivery->userId,
        ]);
    }

    public function deleteManualMarks(string $kind, string $callSign, ?int $activityId, ?int $season, ?int $points): int
    {
        $statement = ($this->pdo)()->prepare(
            "DELETE FROM mail_deliveries WHERE method = 'manual' AND kind = ? AND base_call_sign = ?
             AND activity_id <=> ? AND season <=> ? AND points <=> ?"
        );
        $statement->execute([$kind, $callSign, $activityId, $season, $points]);

        return $statement->rowCount();
    }

    public function emailsSince(string $since): array
    {
        $statement = ($this->pdo)()->prepare(
            "SELECT COUNT(*) AS count, MIN(created_at) AS oldest FROM mail_deliveries WHERE method = 'email' AND created_at > ?"
        );
        $statement->execute([$since]);
        $row = $statement->fetch();

        return ['count' => (int) $row['count'], 'oldest' => $row['oldest']];
    }

    public function lastRecipients(array $callSigns): array
    {
        if ($callSigns === []) {
            return [];
        }
        $statement = ($this->pdo)()->prepare(
            "SELECT base_call_sign, recipient FROM mail_deliveries
             WHERE method = 'email' AND status = 'sent' AND base_call_sign IN (" . self::marks($callSigns) . ')
             ORDER BY id'
        );
        $statement->execute(array_values($callSigns));
        $last = [];
        foreach ($statement->fetchAll() as $row) {
            $last[$row['base_call_sign']] = $row['recipient'];
        }

        return $last;
    }

    /**
     * @param list<string> $values
     */
    private static function marks(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function entry(array $row): AddressBookEntry
    {
        return new AddressBookEntry(
            callSign: $row['call_sign'],
            name: $row['name'],
            email: $row['email'],
            noMail: (bool) $row['no_mail'],
            notes: $row['notes'],
            updatedAt: $row['updated_at'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function delivery(array $row): Delivery
    {
        return new Delivery(
            kind: $row['kind'],
            baseCallSign: $row['base_call_sign'],
            method: $row['method'],
            status: $row['status'],
            userId: (int) $row['user_id'],
            activityId: $row['activity_id'] === null ? null : (int) $row['activity_id'],
            season: $row['season'] === null ? null : (int) $row['season'],
            points: $row['points'] === null ? null : (int) $row['points'],
            certificateDate: $row['certificate_date'],
            recipient: $row['recipient'],
            subject: $row['subject'],
            items: $row['items'] === null ? [] : json_decode($row['items'], true, flags: JSON_THROW_ON_ERROR),
            error: $row['error'],
            createdAt: $row['created_at'],
            id: (int) $row['id'],
        );
    }
}
