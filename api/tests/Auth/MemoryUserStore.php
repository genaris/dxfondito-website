<?php

declare(strict_types=1);

namespace DxFondito\Tests\Auth;

use DateTimeImmutable;
use DxFondito\Auth\User;
use DxFondito\Auth\UserStore;

final class MemoryUserStore implements UserStore
{
    /** @var array<int, User> */
    public array $users = [];

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByCallSign(string $callSign): ?User
    {
        foreach ($this->users as $user) {
            if ($user->callSign === $callSign) {
                return $user;
            }
        }

        return null;
    }

    public function all(): array
    {
        $users = array_values($this->users);
        usort($users, static fn (User $a, User $b): int => strcmp($a->callSign, $b->callSign));

        return $users;
    }

    public function count(): int
    {
        return count($this->users);
    }

    public function countActiveAdministrators(): int
    {
        return count(array_filter($this->users, static fn (User $user): bool => $user->isAdministrator() && $user->active));
    }

    public function create(string $callSign, string $name, ?string $email, string $role, string $passwordHash, bool $mustChangePassword): int
    {
        $id = count($this->users) + 1;
        $this->users[$id] = new User($id, $callSign, $name, $email, $role, $passwordHash, $mustChangePassword, true, 0, null);

        return $id;
    }

    public function update(int $id, string $name, ?string $email, string $role, bool $active): void
    {
        $this->replace($id, ['name' => $name, 'email' => $email, 'role' => $role, 'active' => $active]);
    }

    public function setPassword(int $id, string $passwordHash, bool $mustChangePassword): void
    {
        $this->replace($id, ['passwordHash' => $passwordHash, 'mustChangePassword' => $mustChangePassword]);
    }

    public function recordFailedAttempts(int $id, int $failedAttempts, ?DateTimeImmutable $lockedUntil): void
    {
        $this->replace($id, ['failedAttempts' => $failedAttempts, 'lockedUntil' => $lockedUntil]);
    }

    public function deactivate(int $id): void
    {
        $this->replace($id, ['active' => false]);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function replace(int $id, array $changes): void
    {
        $values = get_object_vars($this->users[$id]);
        $this->users[$id] = new User(...array_merge($values, $changes));
    }
}
