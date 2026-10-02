<?php

declare(strict_types=1);

namespace DxFondito\Auth;

use DateTimeImmutable;

/**
 * An account of an operator or an administrator.
 */
final class User
{
    public const OPERATOR = 'operator';
    public const ADMINISTRATOR = 'administrator';

    public function __construct(
        public readonly int $id,
        public readonly string $callSign,
        public readonly string $name,
        public readonly ?string $email,
        public readonly string $role,
        public readonly string $passwordHash,
        public readonly bool $mustChangePassword,
        public readonly bool $active,
        public readonly int $failedAttempts,
        public readonly ?DateTimeImmutable $lockedUntil,
    ) {
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ADMINISTRATOR;
    }

    /**
     * The data that the browser gets. It has no password data.
     *
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'callSign' => $this->callSign,
            'name' => $this->name,
            'role' => $this->role,
            'mustChangePassword' => $this->mustChangePassword,
        ];
    }
}
