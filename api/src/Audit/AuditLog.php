<?php

declare(strict_types=1);

namespace DxFondito\Audit;

/**
 * The record of actions (FR-AUD-1 to FR-AUD-4).
 */
interface AuditLog
{
    public const USER_CREATE = 'user.create';
    public const USER_UPDATE = 'user.update';
    public const USER_PASSWORD_RESET = 'user.password.reset';
    public const USER_PASSWORD_CHANGE = 'user.password.change';
    public const REFERENCE_CREATE = 'reference.create';
    public const REFERENCE_UPDATE = 'reference.update';
    public const REFERENCE_DELETE = 'reference.delete';
    public const ACTIVITY_CREATE = 'activity.create';
    public const ACTIVITY_UPDATE = 'activity.update';
    public const ACTIVITY_DELETE = 'activity.delete';

    /**
     * @param int $userId The user who did the action.
     * @param array<string, mixed>|null $detail Data that the record shows with the action. No passwords.
     */
    public function record(int $userId, string $action, ?int $entityId, ?array $detail = null): void;

    /**
     * @return list<AuditEntry> The newest entries first.
     */
    public function list(int $offset, int $limit): array;

    public function count(): int;
}
