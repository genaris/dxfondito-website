<?php

declare(strict_types=1);

namespace DxFondito\Auth;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class PdoUserStore implements UserStore
{
    private const COLUMNS = 'id, call_sign, name, email, role, password_hash, must_change_password, active, failed_attempts, locked_until';

    /**
     * @param Closure(): PDO $pdo The store opens the database connection only when it needs it.
     */
    public function __construct(private readonly Closure $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        return $this->findOne('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]);
    }

    public function findByCallSign(string $callSign): ?User
    {
        return $this->findOne('SELECT ' . self::COLUMNS . ' FROM users WHERE call_sign = ?', [$callSign]);
    }

    public function all(): array
    {
        $rows = ($this->pdo)()->query('SELECT ' . self::COLUMNS . ' FROM users ORDER BY call_sign')->fetchAll();

        return array_map(self::fromRow(...), $rows);
    }

    public function count(): int
    {
        return (int) ($this->pdo)()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function contactCounts(): array
    {
        $rows = ($this->pdo)()
            ->query('SELECT operator_id, SUM(contact_count) FROM logs GROUP BY operator_id')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map('intval', $rows);
    }

    public function countActiveAdministrators(): int
    {
        return (int) ($this->pdo)()
            ->query("SELECT COUNT(*) FROM users WHERE role = 'administrator' AND active = 1")
            ->fetchColumn();
    }

    public function create(string $callSign, string $name, ?string $email, string $role, string $passwordHash, bool $mustChangePassword): int
    {
        $pdo = ($this->pdo)();
        $pdo->prepare(
            'INSERT INTO users (call_sign, name, email, role, password_hash, must_change_password) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$callSign, $name, $email, $role, $passwordHash, (int) $mustChangePassword]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $name, ?string $email, string $role, bool $active): void
    {
        ($this->pdo)()->prepare(
            'UPDATE users SET name = ?, email = ?, role = ?, active = ? WHERE id = ?'
        )->execute([$name, $email, $role, (int) $active, $id]);
    }

    public function setPassword(int $id, string $passwordHash, bool $mustChangePassword): void
    {
        ($this->pdo)()->prepare(
            'UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?'
        )->execute([$passwordHash, (int) $mustChangePassword, $id]);
    }

    public function recordFailedAttempts(int $id, int $failedAttempts, ?DateTimeImmutable $lockedUntil): void
    {
        ($this->pdo)()->prepare(
            'UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?'
        )->execute([$failedAttempts, $lockedUntil?->format('Y-m-d H:i:s'), $id]);
    }

    /**
     * @param list<mixed> $params
     */
    private function findOne(string $sql, array $params): ?User
    {
        $statement = ($this->pdo)()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            callSign: $row['call_sign'],
            name: $row['name'],
            email: $row['email'],
            role: $row['role'],
            passwordHash: $row['password_hash'],
            mustChangePassword: (bool) $row['must_change_password'],
            active: (bool) $row['active'],
            failedAttempts: (int) $row['failed_attempts'],
            // The database connection uses UTC.
            lockedUntil: $row['locked_until'] === null
                ? null
                : new DateTimeImmutable($row['locked_until'], new DateTimeZone('UTC')),
        );
    }
}
