<?php

declare(strict_types=1);

namespace DxFondito\Mail;

/**
 * The data that an administrator enters for a base call sign (FR-MAIL-3).
 */
final class AddressBookEntry
{
    public function __construct(
        public readonly string $callSign,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly bool $noMail,
        public readonly ?string $notes,
        public readonly string $updatedAt = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'callSign' => $this->callSign,
            'name' => $this->name,
            'email' => $this->email,
            'noMail' => $this->noMail,
            'notes' => $this->notes,
            'updatedAt' => $this->updatedAt === '' ? null : str_replace(' ', 'T', $this->updatedAt) . 'Z',
        ];
    }
}
