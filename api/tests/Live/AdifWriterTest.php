<?php

declare(strict_types=1);

namespace DxFondito\Tests\Live;

use DxFondito\Live\AdifRecord;
use DxFondito\Live\AdifWriter;
use DxFondito\Live\Band;
use DxFondito\Logs\AdifReader;
use PHPUnit\Framework\TestCase;

final class AdifWriterTest extends TestCase
{
    public function testTheReaderOfTheSiteReadsTheFile(): void
    {
        $content = AdifWriter::write([
            new AdifRecord('LU1ABC/P', '2026-10-04 14:29:05', '7.130', '40m', 'SSB', '59', '57', 'Juana Pérez', 'LU2AOG/A', 'LU2AOG', 'QRP'),
            new AdifRecord('CX2AB', '2026-10-04 14:31:00', null, '2m', 'FM', null, null, null, 'LU2AOG/A', 'LU2AOG', null),
        ], '2026-10-04 20:00:00');

        $records = AdifReader::read($content);

        self::assertCount(2, $records);
        self::assertSame([
            'CALL' => 'LU1ABC/P',
            'QSO_DATE' => '20261004',
            'TIME_ON' => '142905',
            'BAND' => '40m',
            'FREQ' => '7.130',
            'MODE' => 'SSB',
            'RST_SENT' => '59',
            'RST_RCVD' => '57',
            'NAME' => 'Juana Pérez',
            'STATION_CALLSIGN' => 'LU2AOG/A',
            'OPERATOR' => 'LU2AOG',
            'COMMENT' => 'QRP',
        ], $records[0]);
        // The length is a number of bytes: "é" has two.
        self::assertStringContainsString('<NAME:12>Juana Pérez ', $content);
        self::assertArrayNotHasKey('FREQ', $records[1]);
        self::assertStringContainsString('<CREATED_TIMESTAMP:15>20261004 200000 ', $content);
    }

    public function testTheBandOfAFrequency(): void
    {
        self::assertSame('80m', Band::of('3.650'));
        self::assertSame('40m', Band::of('7.130'));
        self::assertSame('2m', Band::of('145.750'));
        self::assertSame('2m', Band::of('144.000'));
        self::assertNull(Band::of('7.350'));
    }
}
