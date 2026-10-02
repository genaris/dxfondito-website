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

    public function testAllFieldsShareTheSizeOfTheTightestField(): void
    {
        // The same values as qsl.test.ts.
        $sizes = TextBox::sharedSizes(['call_sign' => 35.0, 'date' => 29.6, 'time' => 35.0, 'frequency' => 28.5]);

        self::assertSame(['call_sign' => 28.5, 'date' => 28.5, 'time' => 28.5, 'frequency' => 28.5], $sizes);
    }

    public function testAVeryLongTextKeepsItsOwnSmallerSize(): void
    {
        // The median is 35. The name needs less than 0.75 × 35: it does not make the other fields smaller.
        $sizes = TextBox::sharedSizes(['call_sign' => 35.0, 'name' => 18.3, 'time' => 35.0, 'mode' => 35.0, 'frequency' => 28.5]);

        self::assertSame(['call_sign' => 28.5, 'name' => 18.3, 'time' => 28.5, 'mode' => 28.5, 'frequency' => 28.5], $sizes);
    }

    public function testNoFieldsGiveNoSizes(): void
    {
        self::assertSame([], TextBox::sharedSizes([]));
    }
}
