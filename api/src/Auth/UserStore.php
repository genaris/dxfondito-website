<?php

declare(strict_types=1);

namespace DxFondito\Auth;

use DateTimeImmutable;

/**
 * Reads and writes the accounts.
 */
interface UserStore
{
    public function findById(int $id): ?User;

    public function findByCallSign(string $callSign): ?User;

    public function count(): int;

    /**
     * @return int The identifier of the new account.
     */
    public function create(string $callSign, string $name, string $role, string $passwordHash, bool $mustChangePassword): int;

    public function setPassword(int $id, string $passwordHash, bool $mustChangePassword): void;

    public function recordFailedAttempts(int $id, int $failedAttempts, ?DateTimeImmutable $lockedUntil): void;
}
