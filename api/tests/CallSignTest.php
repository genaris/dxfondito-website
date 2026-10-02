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
}
