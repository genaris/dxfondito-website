<?php

declare(strict_types=1);

namespace DxFondito\Logs;

/**
 * One valid record of a log.
 */
final class Contact
{
    /**
     * @param string $qsoAt YYYY-MM-DD HH:MM:SS, UTC.
     * @param string|null $frequency In MHz, with six or fewer decimals.
     */
    public function __construct(
        public readonly int $record,
        public readonly string $callSign,
        public readonly string $baseCallSign,
        public readonly ?string $name,
        public readonly string $qsoAt,
        public readonly ?string $frequency,
        public readonly ?string $band,
        public readonly string $mode,
        public readonly ?string $rstSent,
        public readonly ?string $rstRcvd,
        public readonly ?string $stationCallSign,
        public readonly ?string $email = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function publicData(): array
    {
        return [
            'callSign' => $this->callSign,
            'name' => $this->name,
            'qsoAt' => str_replace(' ', 'T', $this->qsoAt) . 'Z',
            'frequency' => $this->frequency,
            'band' => $this->band,
            'mode' => $this->mode,
            'rstSent' => $this->rstSent,
            'rstRcvd' => $this->rstRcvd,
        ];
    }
}
