<?php

declare(strict_types=1);

namespace DxFondito\Tests\Live;

use DxFondito\Live\AdifRecord;
use DxFondito\Live\LiveContact;
use DxFondito\Live\LiveStore;

final class MemoryLiveStore implements LiveStore
{
    /** @var array<string, LiveContact> By client identifier. */
    public array $contacts = [];

    /** @var array<string, int> The log of each contact. */
    public array $logOf = [];

    /** @var array<string, int> The live log of each "<activity>:<operator>". */
    public array $logs = [];

    /** @var array<string, array{activityId: int, deletedAt: string}> */
    public array $deletions = [];

    /** @var list<array{callSign: string, qsoAt: string, station: string, band: ?string}> */
    public array $uploaded = [];

    /** @var array<string, string> */
    public array $names = [];

    /** @var array<string, list<AdifRecord>> The contacts of the uploaded logs of each "<activity>:<operator>". */
    public array $uploadedRecords = [];

    /** A contact that a different request saves first, before the next save. */
    public ?LiveContact $raceNext = null;

    private int $nextLogId = 100;

    public function find(string $clientId): ?LiveContact
    {
        return $this->contacts[$clientId] ?? null;
    }

    public function save(LiveContact $contact, ?int $expectedVersion, string $fileName): array
    {
        if ($this->raceNext !== null) {
            $this->contacts[$this->raceNext->clientId] = $this->raceNext;
            $this->raceNext = null;
        }
        $current = $this->contacts[$contact->clientId] ?? null;
        if ($expectedVersion === null ? $current !== null : $current?->version !== $expectedVersion) {
            return ['saved' => false, 'logId' => 0, 'logCreated' => false];
        }

        $key = $contact->activityId . ':' . $contact->operatorId;
        $logCreated = !isset($this->logs[$key]);
        $this->logs[$key] ??= $this->nextLogId++;
        $this->contacts[$contact->clientId] = $contact;
        $this->logOf[$contact->clientId] = $this->logs[$key];
        unset($this->deletions[$contact->clientId]);
        $this->removeEmptyLogs();

        return ['saved' => true, 'logId' => $this->logs[$key], 'logCreated' => $logCreated];
    }

    public function delete(LiveContact $contact, string $now): void
    {
        unset($this->contacts[$contact->clientId], $this->logOf[$contact->clientId]);
        $this->deletions[$contact->clientId] = ['activityId' => $contact->activityId, 'deletedAt' => $now];
        $this->removeEmptyLogs();
    }

    public function contacts(int $activityId, ?string $since): array
    {
        $contacts = array_values(array_filter(
            $this->contacts,
            static fn (LiveContact $contact): bool => $contact->activityId === $activityId && ($since === null || $contact->updatedAt >= $since),
        ));
        usort($contacts, static fn (LiveContact $a, LiveContact $b): int => strcmp($a->qsoAt, $b->qsoAt));

        return $contacts;
    }

    public function deletions(int $activityId, string $since): array
    {
        return array_keys(array_filter(
            $this->deletions,
            static fn (array $deletion): bool => $deletion['activityId'] === $activityId && $deletion['deletedAt'] >= $since,
        ));
    }

    public function uploadedContacts(int $activityId): array
    {
        return $this->uploaded;
    }

    public function names(): array
    {
        return $this->names;
    }

    public function logRecords(int $logId): array
    {
        return array_map(
            self::record(...),
            array_values(array_filter($this->contacts, fn (LiveContact $contact): bool => $this->logOf[$contact->clientId] === $logId)),
        );
    }

    public function operatorRecords(int $activityId, int $operatorId): array
    {
        $records = [
            ...($this->uploadedRecords[$activityId . ':' . $operatorId] ?? []),
            ...array_map(self::record(...), array_values(array_filter(
                $this->contacts,
                static fn (LiveContact $contact): bool => $contact->activityId === $activityId && $contact->operatorId === $operatorId,
            ))),
        ];
        usort($records, static fn (AdifRecord $a, AdifRecord $b): int => strcmp($a->qsoAt, $b->qsoAt));

        return $records;
    }

    private function removeEmptyLogs(): void
    {
        $this->logs = array_filter($this->logs, fn (int $logId): bool => in_array($logId, $this->logOf, true));
    }

    private static function record(LiveContact $contact): AdifRecord
    {
        return new AdifRecord(
            $contact->callSign,
            $contact->qsoAt,
            $contact->frequency,
            $contact->band,
            $contact->mode,
            $contact->rstSent,
            $contact->rstRcvd,
            $contact->name,
            $contact->stationCallSign,
            $contact->operatorCallSign,
            $contact->notes,
        );
    }
}
