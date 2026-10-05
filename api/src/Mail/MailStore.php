<?php

declare(strict_types=1);

namespace DxFondito\Mail;

interface MailStore
{
    /**
     * The addresses that the logs gave for each base call sign, the most recent contact first (FR-MAIL-2).
     *
     * @param list<string> $callSigns
     * @return array<string, list<array{email: string, lastQsoAt: string}>>
     */
    public function knownEmails(array $callSigns): array;

    /**
     * @return array<string, true> The addresses that bounced.
     */
    public function invalidEmails(): array;

    public function setInvalid(string $email, bool $invalid, int $userId): void;

    /**
     * @return list<AddressBookEntry> In the order of the call signs.
     */
    public function addressBook(): array;

    /**
     * @param list<string> $callSigns
     * @return array<string, AddressBookEntry>
     */
    public function entries(array $callSigns): array;

    public function saveEntry(AddressBookEntry $entry, int $userId): void;

    public function deleteEntry(string $callSign): void;

    /**
     * @return array{subject: string, body: string}|null
     */
    public function template(string $kind, string $scope): ?array;

    public function saveTemplate(string $kind, string $scope, string $subject, string $body, int $userId): void;

    public function deleteTemplate(string $kind, string $scope): void;

    /**
     * @return list<Delivery> The QSL messages and marks of an activity, the oldest first.
     */
    public function activityDeliveries(int $activityId): array;

    /**
     * @return list<Delivery> The certificate messages and marks of a season, the oldest first.
     */
    public function certificateDeliveries(int $season): array;

    public function record(Delivery $delivery): void;

    /**
     * Deletes the manual marks of a QSL message (with the activity) or of a certificate (with the season and the points).
     *
     * @return int The number of deleted marks.
     */
    public function deleteManualMarks(string $kind, string $callSign, ?int $activityId, ?int $season, ?int $points): int;

    /**
     * The messages that the mailer tried after a time, for the limit of each hour.
     *
     * @param string $since YYYY-MM-DD HH:MM:SS, UTC.
     * @return array{count: int, oldest: string|null}
     */
    public function emailsSince(string $since): array;

    /**
     * The address of the last sent message to each call sign, of any kind.
     *
     * @param list<string> $callSigns
     * @return array<string, string>
     */
    public function lastRecipients(array $callSigns): array;
}
