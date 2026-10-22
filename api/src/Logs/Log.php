<?php

declare(strict_types=1);

namespace DxFondito\Logs;

/**
 * One uploaded ADIF file of one operator for one activity, or the live log of the operator (FR-LIVE-3).
 */
final class Log
{
    public const ADIF = 'adif';
    public const LIVE = 'live';

    /**
     * @param string|null $storedName Null for a live log: its file comes from its contacts.
     * @param string $uploadedAt YYYY-MM-DD HH:MM:SS, UTC. For a live log, the time of its first contact.
     * @param int $skippedCount The records of the file that were already in the live log of the operator.
     */
    public function __construct(
        public readonly int $id,
        public readonly int $activityId,
        public readonly int $operatorId,
        public readonly string $operatorCallSign,
        public readonly int $uploadedById,
        public readonly string $uploadedByCallSign,
        public readonly string $fileName,
        public readonly ?string $storedName,
        public readonly int $contactCount,
        public readonly string $uploadedAt,
        public readonly string $source = self::ADIF,
        public readonly int $skippedCount = 0,
    ) {
    }

    public function isLive(): bool
    {
        return $this->source === self::LIVE;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'activityId' => $this->activityId,
            'operator' => ['id' => $this->operatorId, 'callSign' => $this->operatorCallSign],
            'uploadedBy' => ['id' => $this->uploadedById, 'callSign' => $this->uploadedByCallSign],
            'fileName' => $this->fileName,
            'contactCount' => $this->contactCount,
            'skippedCount' => $this->skippedCount,
            'source' => $this->source,
            'uploadedAt' => str_replace(' ', 'T', $this->uploadedAt) . 'Z',
        ];
    }
}
