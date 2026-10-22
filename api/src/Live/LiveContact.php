<?php

declare(strict_types=1);

namespace DxFondito\Live;

/**
 * A contact of a live log. The browser makes its identifier (`clientId`).
 */
final class LiveContact
{
    /**
     * @param string $qsoAt YYYY-MM-DD HH:MM:SS, UTC.
     * @param string $frequency In MHz, with three to six decimals.
     * @param string $updatedAt YYYY-MM-DD HH:MM:SS, UTC.
     */
    public function __construct(
        public readonly string $clientId,
        public readonly int $activityId,
        public readonly int $operatorId,
        public readonly string $operatorCallSign,
        public readonly string $stationCallSign,
        public readonly string $callSign,
        public readonly string $baseCallSign,
        public readonly ?string $name,
        public readonly string $qsoAt,
        public readonly string $frequency,
        public readonly ?string $band,
        public readonly string $mode,
        public readonly ?string $rstSent,
        public readonly ?string $rstRcvd,
        public readonly ?string $notes,
        public readonly int $enteredById,
        public readonly string $enteredByCallSign,
        public readonly string $updatedAt,
        public readonly int $version,
    ) {
    }

    /**
     * The values that a user enters. Two sendings with the same values are the same correction.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return [
            'operatorId' => $this->operatorId,
            'callSign' => $this->callSign,
            'name' => $this->name,
            'qsoAt' => $this->qsoAt,
            'frequency' => $this->frequency,
            'mode' => $this->mode,
            'rstSent' => $this->rstSent,
            'rstRcvd' => $this->rstRcvd,
            'notes' => $this->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'clientId' => $this->clientId,
            'version' => $this->version,
            'operatorId' => $this->operatorId,
            'station' => $this->stationCallSign,
            'callSign' => $this->callSign,
            'name' => $this->name,
            'qsoAt' => str_replace(' ', 'T', $this->qsoAt) . 'Z',
            'frequency' => $this->frequency,
            'band' => $this->band,
            'mode' => $this->mode,
            'rstSent' => $this->rstSent,
            'rstRcvd' => $this->rstRcvd,
            'notes' => $this->notes,
            'enteredBy' => $this->enteredByCallSign,
            'updatedAt' => str_replace(' ', 'T', $this->updatedAt) . 'Z',
        ];
    }

    public function withVersion(int $version): self
    {
        return new self(
            $this->clientId,
            $this->activityId,
            $this->operatorId,
            $this->operatorCallSign,
            $this->stationCallSign,
            $this->callSign,
            $this->baseCallSign,
            $this->name,
            $this->qsoAt,
            $this->frequency,
            $this->band,
            $this->mode,
            $this->rstSent,
            $this->rstRcvd,
            $this->notes,
            $this->enteredById,
            $this->enteredByCallSign,
            $this->updatedAt,
            $version,
        );
    }
}
