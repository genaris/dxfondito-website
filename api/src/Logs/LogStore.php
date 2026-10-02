<?php

declare(strict_types=1);

namespace DxFondito\Logs;

interface LogStore
{
    /**
     * Saves the log and its contacts in one transaction.
     *
     * @param list<Contact> $contacts
     * @return int The identifier of the new log.
     */
    public function create(
        int $activityId,
        int $operatorId,
        int $uploadedBy,
        string $fileName,
        string $storedName,
        array $contacts,
        string $uploadedAt,
    ): int;

    public function find(int $id): ?Log;

    /**
     * @return list<Log> The newest log first.
     */
    public function byActivity(int $activityId): array;

    /**
     * @return list<Contact> In the order of their time.
     */
    public function contacts(int $logId): array;

    /**
     * Deletes the log. The database deletes its contacts (FR-LOG-25).
     */
    public function delete(int $id): void;

    /**
     * The operators with a log in the activity (FR-ACT-2a).
     *
     * @return list<array{id: int, callSign: string}>
     */
    public function operators(int $activityId): array;
}
