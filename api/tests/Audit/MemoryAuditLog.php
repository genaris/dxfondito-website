<?php

declare(strict_types=1);

namespace DxFondito\Tests\Audit;

use DateTimeImmutable;
use DxFondito\Audit\AuditEntry;
use DxFondito\Audit\AuditLog;

final class MemoryAuditLog implements AuditLog
{
    /** @var list<array{userId: int, action: string, entityId: ?int, detail: ?array<string, mixed>}> */
    public array $entries = [];

    public function record(int $userId, string $action, ?int $entityId, ?array $detail = null): void
    {
        $this->entries[] = ['userId' => $userId, 'action' => $action, 'entityId' => $entityId, 'detail' => $detail];
    }

    public function list(int $offset, int $limit): array
    {
        $entries = [];
        foreach (array_reverse($this->entries, true) as $index => $entry) {
            $entries[] = new AuditEntry(
                $index + 1,
                $entry['userId'],
                'USER' . $entry['userId'],
                $entry['action'],
                $entry['entityId'],
                $entry['detail'],
                new DateTimeImmutable('2026-10-01 12:00:00'),
            );
        }

        return array_slice($entries, $offset, $limit);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return array_column($this->entries, 'action');
    }
}
