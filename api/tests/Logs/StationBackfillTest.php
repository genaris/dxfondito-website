<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Logs\StationBackfill;
use PHPUnit\Framework\TestCase;

final class StationBackfillTest extends TestCase
{
    public function testGivesTheStationOfEachRecordByCallSignAndTime(): void
    {
        $adif = "<EOH>\n"
            . "<CALL:6>LU1ABC <QSO_DATE:8>20261004 <TIME_ON:4>1200 <FREQ:4>7.13 <MODE:3>SSB <STATION_CALLSIGN:8>lu2aog/a <EOR>\n"
            . "<CALL:6>LU1ABC <QSO_DATE:8>20261005 <TIME_ON:6>090510 <FREQ:4>7.13 <MODE:3>SSB <STATION_CALLSIGN:6>LU2AOG <EOR>\n"
            . "<CALL:6>LU3XYZ <QSO_DATE:8>20261004 <TIME_ON:4>1300 <FREQ:4>7.13 <MODE:3>SSB <EOR>\n"
            . "<CALL:3>BAD <EOR>\n";

        self::assertSame(
            ['LU1ABC|2026-10-04 12:00:00' => 'LU2AOG/A', 'LU1ABC|2026-10-05 09:05:10' => 'LU2AOG'],
            StationBackfill::stations($adif),
        );
    }

    public function testTheMigrationGivesAFunction(): void
    {
        $step = require dirname(__DIR__, 2) . '/migrations/0003_station_call_sign_backfill.php';

        self::assertIsCallable($step);
    }
}
