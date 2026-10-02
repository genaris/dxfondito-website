<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Activities\Activity;
use DxFondito\Activities\Reference;
use DxFondito\Activities\Series;
use DxFondito\Auth\User;
use DxFondito\Logs\LogSummary;
use PHPUnit\Framework\TestCase;

final class LogSummaryTest extends TestCase
{
    public function testCountsTheValidRecordsAndGivesTheInvalidRecords(): void
    {
        $summary = $this->summary(
            $this->record('LU2ABC', '20260510') . '<QSO_DATE:8>20260510<MODE:3>SSB<BAND:3>40m<EOR>' . $this->record('CX1A', '20260510'),
        );

        self::assertCount(2, $summary->contacts);
        self::assertSame(
            [['record' => 2, 'callSign' => null, 'problems' => [['field' => 'CALL', 'problem' => 'missing'], ['field' => 'TIME_ON', 'problem' => 'missing']]]],
            $summary->invalid,
        );
    }

    public function testWarnsAboutADateOutOfTheActivity(): void
    {
        $summary = $this->summary($this->record('LU2ABC', '20260509') . $this->record('CX1A', '20260511') . $this->record('PY1A', '20260512'));

        self::assertSame(
            [
                ['record' => 1, 'callSign' => 'LU2ABC', 'kind' => 'date', 'value' => '2026-05-09'],
                ['record' => 3, 'callSign' => 'PY1A', 'kind' => 'date', 'value' => '2026-05-12'],
            ],
            $summary->warnings,
        );
        // A warning does not make the record invalid (D-7).
        self::assertCount(3, $summary->contacts);
    }

    public function testWarnsAboutADifferentStation(): void
    {
        $summary = $this->summary(
            $this->record('LU2ABC', '20260510', 'LU1ABC/P') . $this->record('CX1A', '20260510', 'LU9XYZ'),
        );

        self::assertSame([['record' => 2, 'callSign' => 'CX1A', 'kind' => 'station', 'value' => 'LU9XYZ']], $summary->warnings);
    }

    private function summary(string $adif): LogSummary
    {
        $reference = new Reference(1, new Series(1, 'DPS', 'Puestos de Salud'), 1, 'Hospital', null);
        $activity = new Activity(1, $reference, 2026, '2026-05-10', '2026-05-11', null);
        $operator = new User(1, 'LU1ABC', 'Ana', null, User::OPERATOR, 'hash', false, true, 0, null);

        return LogSummary::read("<EOH>\n" . $adif, $activity, $operator);
    }

    private function record(string $call, string $date, ?string $station = null): string
    {
        $stationField = $station === null ? '' : '<STATION_CALLSIGN:' . strlen($station) . '>' . $station;

        return '<CALL:' . strlen($call) . '>' . $call . '<QSO_DATE:8>' . $date . '<TIME_ON:4>1200<MODE:2>CW<FREQ:5>7.030'
            . $stationField . "<EOR>\n";
    }
}
