<?php

declare(strict_types=1);

namespace DxFondito\Logs;

/**
 * One uploaded ADIF file of one operator for one activity.
 */
final class Log
{
    /**
     * @param string $uploadedAt YYYY-MM-DD HH:MM:SS, UTC.
     */
    public function __construct(
        public readonly int $id,
        public readonly int $activityId,
        public readonly int $operatorId,
        public readonly string $operatorCallSign,
        public readonly int $uploadedById,
        public readonly string $uploadedByCallSign,
        public readonly string $fileName,
        public readonly string $storedName,
        public readonly int $contactCount,
        public readonly string $uploadedAt,
    ) {
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
            'uploadedAt' => str_replace(' ', 'T', $this->uploadedAt) . 'Z',
        ];
    }
}
