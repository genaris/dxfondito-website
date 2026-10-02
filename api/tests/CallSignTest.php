<?php

declare(strict_types=1);

namespace DxFondito\Tests;

use DxFondito\CallSign;
use PHPUnit\Framework\TestCase;

final class CallSignTest extends TestCase
{
    public function testUsesUppercaseLettersAndRemovesTheSpaces(): void
    {
        self::assertSame('LU1ABC/P', CallSign::normalize(" lu1 abc/p\t"));
    }

    /**
     * The examples of section 4.1 of the system design.
     */
    #[\PHPUnit\Framework\Attributes\TestWith(['LU1ABC', 'LU1ABC'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['LU1ABC/P', 'LU1ABC'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['LU1ABC/QRP', 'LU1ABC'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CX/LU1ABC', 'LU1ABC'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['lu1abc/m', 'LU1ABC'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['LU1AB/LU2CD', 'LU1AB'])]
    public function testGivesTheBaseCallSign(string $callSign, string $base): void
    {
        self::assertSame($base, CallSign::base($callSign));
    }
}
