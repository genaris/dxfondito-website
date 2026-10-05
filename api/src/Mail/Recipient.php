<?php

declare(strict_types=1);

namespace DxFondito\Mail;

/**
 * The address for the messages of a base call sign (FR-MAIL-4, notes of the design section 6.5).
 */
final class Recipient
{
    public const BOOK = 'book';
    public const ADIF = 'adif';

    /**
     * @param string|null $email The address, or null without a usable address.
     * @param string|null $source "book" or "adif".
     * @param list<string> $others The other usable addresses that the logs gave.
     * @param string|null $changedFrom The address of the last message, if it is different from the address of now.
     * @param bool $noMail True if the address book says: no messages for this call sign.
     */
    public function __construct(
        public readonly ?string $email,
        public readonly ?string $source,
        public readonly array $others,
        public readonly ?string $changedFrom,
        public readonly bool $noMail,
    ) {
    }

    /**
     * The address of the address book, if it is usable. Otherwise the address of the most recent contact.
     * An address that bounced is never usable.
     *
     * @param list<array{email: string, lastQsoAt: string}> $known The addresses of the logs, the most recent contact first.
     * @param array<string, true> $invalid The addresses that bounced.
     * @param string|null $lastRecipient The address of the last sent message to this call sign.
     */
    public static function resolve(?AddressBookEntry $entry, array $known, array $invalid, ?string $lastRecipient): self
    {
        $usable = array_values(array_filter(
            array_map(static fn (array $item): string => $item['email'], $known),
            static fn (string $email): bool => !isset($invalid[$email]),
        ));
        $email = null;
        $source = null;
        if ($entry?->email !== null && !isset($invalid[$entry->email])) {
            $email = $entry->email;
            $source = self::BOOK;
        } elseif ($usable !== []) {
            $email = $usable[0];
            $source = self::ADIF;
        }

        return new self(
            email: $email,
            source: $source,
            others: array_values(array_filter($usable, static fn (string $other): bool => $other !== $email)),
            changedFrom: $lastRecipient !== null && $email !== null && $lastRecipient !== $email ? $lastRecipient : null,
            noMail: $entry?->noMail ?? false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'email' => $this->email,
            'source' => $this->source,
            'others' => $this->others,
            'changedFrom' => $this->changedFrom,
            'noMail' => $this->noMail,
        ];
    }
}
