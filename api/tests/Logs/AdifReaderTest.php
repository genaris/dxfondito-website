<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Logs\AdifReader;
use PHPUnit\Framework\TestCase;

final class AdifReaderTest extends TestCase
{
    public function testReadsTheRecordsAfterTheHeader(): void
    {
        $adif = "Log of LU1ABC <CALL:6>LU9ZZZ\n<ADIF_VER:5>3.1.4 <EOH>\n"
            . "<CALL:6>LU2ABC <QSO_DATE:8>20260510 <EOR>\n"
            . "<CALL:4>CX1A <QSO_DATE:8>20260511 <EOR>\n";

        self::assertSame(
            [['CALL' => 'LU2ABC', 'QSO_DATE' => '20260510'], ['CALL' => 'CX1A', 'QSO_DATE' => '20260511']],
            AdifReader::read($adif),
        );
    }

    public function testReadsAFileWithoutHeader(): void
    {
        self::assertSame([['CALL' => 'LU2ABC']], AdifReader::read('<CALL:6>LU2ABC<EOR>'));
    }

    public function testTheFieldNamesAreNotCaseSensitive(): void
    {
        self::assertSame([['CALL' => 'LU2ABC', 'MODE' => 'SSB']], AdifReader::read('<eoh><call:6>LU2ABC<Mode:3>SSB<eor>'));
    }

    public function testUsesTheLengthAndIgnoresTheType(): void
    {
        // The length gives the end of the data, also if the data has a < character.
        self::assertSame([['NAME' => 'A<b>c', 'FREQ' => '7.074']], AdifReader::read('<NAME:5>A<b>c<FREQ:5:N>7.074<EOR>'));
    }

    public function testTheLengthIsANumberOfBytes(): void
    {
        self::assertSame([['NAME' => 'José', 'CALL' => 'LU2ABC']], AdifReader::read('<NAME:5>José<CALL:6>LU2ABC<EOR>'));
    }

    public function testChangesIso88591ToUtf8(): void
    {
        $adif = "<NAME:4>Jos\xE9<EOR>";

        self::assertSame([['NAME' => 'José']], AdifReader::read($adif));
    }

    public function testKeepsALastRecordWithoutEor(): void
    {
        self::assertSame([['CALL' => 'LU2ABC'], ['CALL' => 'CX1A']], AdifReader::read('<CALL:6>LU2ABC<EOR><CALL:4>CX1A'));
    }

    public function testIgnoresEmptyRecordsAndTagsWithoutLength(): void
    {
        self::assertSame([['CALL' => 'LU2ABC']], AdifReader::read('<EOR><APP_X>junk<CALL:6>LU2ABC<EOR><EOR>'));
    }

    public function testAcceptsAShortLastValue(): void
    {
        self::assertSame([['CALL' => 'LU2']], AdifReader::read('<CALL:6>LU2'));
    }

    public function testGivesNoRecordsForAnEmptyFile(): void
    {
        self::assertSame([], AdifReader::read(''));
        self::assertSame([], AdifReader::read('Only a header <EOH>'));
    }
}
