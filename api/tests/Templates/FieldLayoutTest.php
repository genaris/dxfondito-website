<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Http\HttpException;
use DxFondito\Templates\FieldLayout;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class FieldLayoutTest extends TestCase
{
    public static function layout(): array
    {
        $fields = [];
        foreach (FieldLayout::QSL_FIELDS as $index => $name) {
            $fields[$name] = ['x' => 100, 'y' => 20 + 60 * $index, 'width' => 300, 'height' => 40, 'colour' => '#1a1a1a', 'align' => 'left', 'font' => 'sans'];
        }

        return $fields;
    }

    public function testAcceptsTheFieldsAndUsesUppercaseColours(): void
    {
        $fields = FieldLayout::check(self::layout(), FieldLayout::QSL_FIELDS, 800, 500);

        self::assertSame(FieldLayout::QSL_FIELDS, array_keys($fields));
        self::assertSame('#1A1A1A', $fields['call_sign']['colour']);
    }

    public function testIgnoresUnknownFields(): void
    {
        $fields = FieldLayout::check(self::layout() + ['other' => []], FieldLayout::QSL_FIELDS, 800, 500);

        self::assertArrayNotHasKey('other', $fields);
    }

    public function testRefusesAnAbsentField(): void
    {
        $layout = self::layout();
        unset($layout['rst']);

        $this->expectException(HttpException::class);
        FieldLayout::check($layout, FieldLayout::QSL_FIELDS, 800, 500);
    }

    #[TestWith(['x', 501])]
    #[TestWith(['y', -1])]
    #[TestWith(['x', '100'])]
    #[TestWith(['width', 9])]
    #[TestWith(['height', 5])]
    #[TestWith(['height', 501])]
    #[TestWith(['colour', 'red'])]
    #[TestWith(['colour', '#12345'])]
    #[TestWith(['align', 'justify'])]
    #[TestWith(['font', 'comic'])]
    public function testRefusesAnIncorrectValue(string $key, mixed $value): void
    {
        $layout = self::layout();
        $layout['date'][$key] = $value;

        try {
            FieldLayout::check($layout, FieldLayout::QSL_FIELDS, 800, 500);
            self::fail('The check accepted ' . $key);
        } catch (HttpException $e) {
            self::assertSame(422, $e->status);
            self::assertStringContainsString('date', $e->getMessage());
        }
    }

    public function testTheBoxMustBeInsideTheImage(): void
    {
        $layout = self::layout();
        // 100 + 300 is in the image of 800 pixels, 600 + 300 is not.
        $layout['date']['x'] = 600;

        $this->expectException(HttpException::class);
        FieldLayout::check($layout, FieldLayout::QSL_FIELDS, 800, 500);
    }

    public function testRefusesTextThatIsNotJson(): void
    {
        $this->expectException(HttpException::class);
        FieldLayout::decode('{x');
    }
}
