<?php

declare(strict_types=1);

namespace DxFondito\Audit;

use DateTimeImmutable;

/**
 * One entry of the record of actions (FR-AUD-3).
 */
final class AuditEntry
{
    /**
     * @param array<string, mixed>|null $detail
     */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $userCallSign,
        public readonly string $action,
        public readonly ?int $entityId,
        public readonly ?array $detail,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'user' => ['id' => $this->userId, 'callSign' => $this->userCallSign],
            'action' => $this->action,
            'entityId' => $this->entityId,
            'detail' => $this->detail,
            // UTC, as all times of the system.
            'createdAt' => $this->createdAt->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
