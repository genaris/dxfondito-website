<?php

declare(strict_types=1);

namespace DxFondito\Live;

interface LiveStore
{
    public function find(string $clientId): ?LiveContact;

    /**
     * Saves a new contact, or a correction if `$expectedVersion` is the version in the database, in one transaction.
     * The contact goes to the live log of its operator, which the store makes if necessary: one for each operator
     * and activity, also with two requests at the same time. A correction with a different operator moves the contact,
     * and a live log without contacts after the move is deleted.
     * The store changes the number of contacts of the logs, and removes a deletion of the same contact.
     *
     * @param int|null $expectedVersion Null for a new contact.
     * @param string $fileName The name of the ADIF file of a new live log.
     * @return array{saved: bool, logId: int, logCreated: bool} `saved` is false if a different request changed
     *     or made the contact first. Then the store changed nothing.
     */
    public function save(LiveContact $contact, ?int $expectedVersion, string $fileName): array;

    /**
     * Deletes a live contact and keeps the deletion for the other browsers. A log without contacts is deleted.
     */
    public function delete(LiveContact $contact, string $now): void;

    /**
     * @param string|null $since YYYY-MM-DD HH:MM:SS, UTC: only the contacts changed at that time or later.
     * @return list<LiveContact> The live contacts of the activity, in the order of time.
     */
    public function contacts(int $activityId, ?string $since): array;

    /**
     * @return list<string> The identifiers of the live contacts deleted at `$since` or later.
     */
    public function deletions(int $activityId, string $since): array;

    /**
     * The contacts of the uploaded logs of the activity, for the warning of a second contact.
     *
     * @return list<array{callSign: string, qsoAt: string, station: string, band: ?string}>
     */
    public function uploadedContacts(int $activityId): array;

    /**
     * The names for the live page: the address book, else the official lists.
     *
     * @return array<string, string> The name of each call sign.
     */
    public function names(): array;

    /**
     * @return list<AdifRecord> The contacts of one log, in the order of time.
     */
    public function logRecords(int $logId): array;

    /**
     * @return list<AdifRecord> The contacts of all logs of one operator in one activity, in the order of time.
     */
    public function operatorRecords(int $activityId, int $operatorId): array;
}
