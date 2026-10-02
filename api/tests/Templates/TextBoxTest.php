<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Templates\TextBox;
use PHPUnit\Framework\TestCase;

/**
 * The same values are in web/src/qsl.test.ts. Thus the editor and the API agree.
 */
final class TextBoxTest extends TestCase
{
    public function testTheTextFillsTheHeightOfTheBox(): void
    {
        self::assertEqualsWithDelta(50.0, TextBox::size(400, 60, 200.0), 0.001);
    }

    public function testAWideTextBecomesSmaller(): void
    {
        // At 50 pixels the text is 500 pixels wide. The box is 250 pixels wide: half the size, with the margin.
        self::assertEqualsWithDelta(25.0 * 0.97, TextBox::size(250, 60, 500.0), 0.001);
    }

    public function testTheCapitalLettersAreInTheVerticalCentre(): void
    {
        // 100 + (60 + 0.72 × 50) / 2 = 148
        self::assertSame(148, TextBox::baseline(100, 60, 50.0));
    }

    public function testTheAlignmentInTheBox(): void
    {
        self::assertSame(10, TextBox::left(10, 200, 'left', 80.0));
        self::assertSame(70, TextBox::left(10, 200, 'center', 80.0));
        self::assertSame(130, TextBox::left(10, 200, 'right', 80.0));
    }
}
