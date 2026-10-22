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
}
