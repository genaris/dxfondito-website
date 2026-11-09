<?php

declare(strict_types=1);

namespace DxFondito\Live;

/**
 * One contact of an ADIF file for QRZ.com or LdA.
 */
final class AdifRecord
{
    /**
     * @param string $qsoAt YYYY-MM-DD HH:MM:SS, UTC.
     * @param string $station The station call sign, such as LU2AOG/A.
     * @param string $operator The call sign of the account of the operator.
     */
    public function __construct(
        public readonly string $callSign,
        public readonly string $qsoAt,
        public readonly ?string $frequency,
        public readonly ?string $band,
        public readonly string $mode,
        public readonly ?string $rstSent,
        public readonly ?string $rstRcvd,
        public readonly ?string $name,
        public readonly string $station,
        public readonly string $operator,
        public readonly ?string $notes,
    ) {
    }

    /**
     * The record with the suffix on the station call sign. A station with a suffix, such as LU2AOG/A, stays the same.
     */
    public function withSuffix(string $suffix): self
    {
        if ($this->station !== $this->operator) {
            return $this;
        }

        return new self(
            $this->callSign,
            $this->qsoAt,
            $this->frequency,
            $this->band,
            $this->mode,
            $this->rstSent,
            $this->rstRcvd,
            $this->name,
            $this->station . '/' . $suffix,
            $this->operator,
            $this->notes,
        );
    }
}
