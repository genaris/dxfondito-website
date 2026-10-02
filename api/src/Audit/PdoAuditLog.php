<?php

declare(strict_types=1);

namespace DxFondito\Audit;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class PdoAuditLog implements AuditLog
{
    /**
     * @param Closure(): PDO $pdo
     * @param (Closure(): DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly Closure $pdo,
        private readonly ?Closure $now = null,
    ) {
    }

    public function record(int $userId, string $action, ?int $entityId, ?array $detail = null): void
    {
        $now = $this->now === null ? new DateTimeImmutable() : ($this->now)();
        ($this->pdo)()->prepare(
            'INSERT INTO audit_entries (user_id, action, entity_id, detail, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $action,
            $entityId,
            $detail === null ? null : json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }

    public function list(int $offset, int $limit): array
    {
        $statement = ($this->pdo)()->prepare(
            'SELECT a.id, a.user_id, u.call_sign, a.action, a.entity_id, a.detail, a.created_at
             FROM audit_entries a JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ? OFFSET ?'
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->bindValue(2, $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): AuditEntry => new AuditEntry(
                id: (int) $row['id'],
                userId: (int) $row['user_id'],
                userCallSign: $row['call_sign'],
                action: $row['action'],
                entityId: $row['entity_id'] === null ? null : (int) $row['entity_id'],
                detail: $row['detail'] === null ? null : json_decode($row['detail'], true, flags: JSON_THROW_ON_ERROR),
                createdAt: new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')),
            ),
            $statement->fetchAll(),
        );
    }

    public function count(): int
    {
        return (int) ($this->pdo)()->query('SELECT COUNT(*) FROM audit_entries')->fetchColumn();
    }
}
