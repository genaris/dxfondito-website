<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Logs\Frequency;
use PHPUnit\Framework\TestCase;

final class FrequencyTest extends TestCase
{
    public function testKeepsThreeDecimals(): void
    {
        self::assertSame('7.130', Frequency::text('7.130000'));
        self::assertSame('7.130', Frequency::text('7.13'));
        self::assertSame('146.520', Frequency::text('146.520000'));
        self::assertSame('14.000', Frequency::text('14'));
    }

    public function testKeepsTheDecimalsAfterTheThird(): void
    {
        self::assertSame('7.1305', Frequency::text('7.130500'));
        self::assertSame('7.074123', Frequency::text('7.074123'));
    }
}
