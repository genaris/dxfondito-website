<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Logs\Contact;
use DxFondito\Logs\Log;
use DxFondito\Logs\LogStore;
use DxFondito\Tests\Auth\MemoryUserStore;
use RuntimeException;

final class MemoryLogStore implements LogStore
{
    /** @var array<int, Log> */
    public array $logs = [];

    /** @var array<int, list<Contact>> */
    public array $contacts = [];

    public bool $failNextCreate = false;

    private int $nextId = 1;

    public function __construct(private readonly MemoryUserStore $users)
    {
    }

    public function create(int $activityId, int $operatorId, int $uploadedBy, string $fileName, string $storedName, array $contacts, string $uploadedAt): int
    {
        if ($this->failNextCreate) {
            throw new RuntimeException('The database failed');
        }
        $id = $this->nextId++;
        $this->logs[$id] = new Log(
            $id,
            $activityId,
            $operatorId,
            $this->users->findById($operatorId)->callSign,
            $uploadedBy,
            $this->users->findById($uploadedBy)->callSign,
            $fileName,
            $storedName,
            count($contacts),
            $uploadedAt,
        );
        $this->contacts[$id] = $contacts;

        return $id;
    }

    public function find(int $id): ?Log
    {
        return $this->logs[$id] ?? null;
    }

    public function byActivity(int $activityId): array
    {
        return array_values(array_reverse(array_filter($this->logs, static fn (Log $log): bool => $log->activityId === $activityId)));
    }

    public function contacts(int $logId): array
    {
        return $this->contacts[$logId] ?? [];
    }

    public function delete(int $id): void
    {
        unset($this->logs[$id], $this->contacts[$id]);
    }

    public function operators(int $activityId): array
    {
        $operators = [];
        foreach ($this->byActivity($activityId) as $log) {
            $operators[$log->operatorId] = ['id' => $log->operatorId, 'callSign' => $log->operatorCallSign];
        }

        return array_values($operators);
    }
}
