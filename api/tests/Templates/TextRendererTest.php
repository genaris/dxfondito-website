<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Templates\Fonts;
use DxFondito\Templates\TextRenderer;
use GdImage;
use PHPUnit\Framework\TestCase;

final class TextRendererTest extends TestCase
{
    public function testWritesTheTextAndGivesAJpegImage(): void
    {
        $jpeg = $this->render('left', 20, 360);

        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($jpeg)[2]);
        $image = imagecreatefromstring($jpeg);
        self::assertGreaterThan(50, $this->darkPixels($image, 0, 400));
    }

    public function testTheAlignmentIsInTheBox(): void
    {
        // The box is from x = 100 to x = 350. The text is narrower than the box.
        $left = imagecreatefromstring($this->render('left', 100, 250));
        $right = imagecreatefromstring($this->render('right', 100, 250));

        self::assertSame(0, $this->darkPixels($left, 0, 98));
        self::assertSame(0, $this->darkPixels($left, 320, 400));
        self::assertSame(0, $this->darkPixels($right, 0, 130));
        self::assertSame(0, $this->darkPixels($right, 352, 400));
    }

    public function testALongTextBecomesSmallerAndStaysInTheBox(): void
    {
        $image = imagecreatetruecolor(400, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        $field = ['x' => 50, 'y' => 20, 'width' => 120, 'height' => 60, 'colour' => '#000000', 'align' => 'left', 'font' => 'sans-bold'];

        $jpeg = $this->renderer()->render($png, ['name' => $field], ['name' => 'María de los Ángeles Fernández']);

        $result = imagecreatefromstring($jpeg);
        self::assertGreaterThan(20, $this->darkPixels($result, 50, 170));
        self::assertSame(0, $this->darkPixels($result, 0, 48));
        self::assertSame(0, $this->darkPixels($result, 173, 400));
    }

    public function testAcceptsAPalettePng(): void
    {
        $image = imagecreate(400, 100);
        imagecolorallocate($image, 255, 255, 255);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $jpeg = $this->renderer()->render($png, ['call_sign' => $this->field('left', 20, 360)], ['call_sign' => 'LU1ABC']);

        self::assertGreaterThan(50, $this->darkPixels(imagecreatefromstring($jpeg), 0, 400));
    }

    private function render(string $align, int $x, int $width): string
    {
        $image = imagecreatetruecolor(400, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        return $this->renderer()->render($png, ['call_sign' => $this->field($align, $x, $width)], ['call_sign' => 'LU1ABC']);
    }

    /**
     * @return array{x: int, y: int, width: int, height: int, colour: string, align: string, font: string}
     */
    private function field(string $align, int $x, int $width): array
    {
        return ['x' => $x, 'y' => 30, 'width' => $width, 'height' => 40, 'colour' => '#000000', 'align' => $align, 'font' => 'sans-bold'];
    }

    private function renderer(): TextRenderer
    {
        return new TextRenderer(new Fonts(dirname(__DIR__, 2) . '/fonts'));
    }

    private function darkPixels(GdImage $image, int $fromX, int $toX): int
    {
        $count = 0;
        for ($x = $fromX; $x < min($toX, imagesx($image)); $x++) {
            for ($y = 0; $y < imagesy($image); $y++) {
                if ((imagecolorat($image, $x, $y) & 0xFF) < 100) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
