<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use DxFondito\Audit\AuditLog;
use DxFondito\Audit\Changes;
use DxFondito\Auth\User;
use DxFondito\CallSign;
use DxFondito\Http\HttpException;
use DxFondito\Logs\RecordReader;
use DxFondito\Registry\LicenseeName;
use DxFondito\Registry\LicenseeStore;

/**
 * The address book of the administrators (FR-MAIL-3 to FR-MAIL-5): an own address, the mark "no messages",
 * and notes for a base call sign. The addresses that bounced.
 */
final class AddressBookService
{
    private const MAX_NAME = 150;
    private const MAX_NOTES = 500;

    public function __construct(
        private readonly MailStore $store,
        private readonly LicenseeStore $licensees,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $entries = $this->store->addressBook();
        $known = $this->store->knownEmails(array_map(static fn (AddressBookEntry $entry): string => $entry->callSign, $entries));
        $invalid = $this->store->invalidEmails();

        return array_map(
            fn (AddressBookEntry $entry): array => $entry->data() + [
                'recipient' => Recipient::resolve($entry, $known[$entry->callSign] ?? [], $invalid, null)->data(),
                'emailInvalid' => $entry->email !== null && isset($invalid[$entry->email]),
            ],
            $entries,
        );
    }

    /**
     * The data of a call sign: its entry, the addresses of the logs, and the name of the official lists.
     *
     * @return array<string, mixed>
     */
    public function show(string $callSign): array
    {
        $callSign = self::callSign($callSign);
        $entry = $this->store->entries([$callSign])[$callSign] ?? null;
        $known = $this->store->knownEmails([$callSign])[$callSign] ?? [];
        $invalid = $this->store->invalidEmails();
        $name = $this->licensees->name($callSign);

        return [
            'callSign' => $callSign,
            'entry' => $entry?->data(),
            'officialName' => $name === null ? null : LicenseeName::format($name),
            'known' => array_map(
                static fn (array $item): array => [
                    'email' => $item['email'],
                    'lastQsoAt' => str_replace(' ', 'T', $item['lastQsoAt']) . 'Z',
                    'invalid' => isset($invalid[$item['email']]),
                ],
                $known,
            ),
            'recipient' => Recipient::resolve($entry, $known, $invalid, null)->data(),
        ];
    }

    /**
     * Saves the entry of a call sign. An entry without data is deleted.
     *
     * @throws HttpException 422 for an incorrect call sign, address or text.
     */
    public function save(User $actor, string $callSign, string $name, string $email, bool $noMail, string $notes): void
    {
        $callSign = self::callSign($callSign);
        $email = trim($email);
        $address = $email === '' ? null : RecordReader::email($email);
        if ($email !== '' && $address === null) {
            throw new HttpException(422, 'The e-mail address is not correct');
        }
        $name = trim($name);
        $notes = trim($notes);
        if (mb_strlen($name) > self::MAX_NAME || mb_strlen($notes) > self::MAX_NOTES) {
            throw new HttpException(422, 'The name or the notes are too long');
        }
        $old = $this->store->entries([$callSign])[$callSign] ?? null;
        $entry = new AddressBookEntry($callSign, $name === '' ? null : $name, $address, $noMail, $notes === '' ? null : $notes);

        if ($entry->name === null && $entry->email === null && !$entry->noMail && $entry->notes === null) {
            if ($old !== null) {
                $this->delete($actor, $callSign);
            }

            return;
        }
        $changes = Changes::between(self::values($old), self::values($entry));
        if ($changes === []) {
            return;
        }
        $this->store->saveEntry($entry, $actor->id);
        $this->audit->record($actor->id, AuditLog::ADDRESS_BOOK_SAVE, null, ['callSign' => $callSign, 'changes' => $changes]);
    }

    public function delete(User $actor, string $callSign): void
    {
        $callSign = self::callSign($callSign);
        $this->store->deleteEntry($callSign);
        $this->audit->record($actor->id, AuditLog::ADDRESS_BOOK_DELETE, null, ['callSign' => $callSign]);
    }

    /**
     * Marks an address as bounced, or as correct again (FR-MAIL-5). A bounced address is not used.
     *
     * @throws HttpException 422 for an incorrect address.
     */
    public function setInvalid(User $actor, string $email, bool $invalid): void
    {
        $address = RecordReader::email($email) ?? throw new HttpException(422, 'The e-mail address is not correct');
        $this->store->setInvalid($address, $invalid, $actor->id);
        $this->audit->record($actor->id, $invalid ? AuditLog::EMAIL_INVALID : AuditLog::EMAIL_VALID, null, ['email' => $address]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(?AddressBookEntry $entry): array
    {
        return [
            'name' => $entry?->name,
            'email' => $entry?->email,
            'noMail' => $entry?->noMail ?? false,
            'notes' => $entry?->notes,
        ];
    }

    /**
     * @throws HttpException 422 for an incorrect call sign.
     */
    private static function callSign(string $callSign): string
    {
        $base = CallSign::base($callSign);
        if (!CallSign::isValid($base)) {
            throw new HttpException(422, 'The call sign is not correct');
        }

        return $base;
    }
}
