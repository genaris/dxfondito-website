<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use RuntimeException;

/**
 * Writes the fields on a template image with GD and gives a JPEG image (system design, section 6.2).
 */
final class TextRenderer
{
    private const JPEG_QUALITY = 90;

    // The sizes are in pixels, as in the browser. GD uses points at 96 dots for each inch.
    private const POINTS_PER_PIXEL = 0.75;

    public function __construct(private readonly Fonts $fonts)
    {
    }

    /**
     * @param array<string, array{x: int, y: int, width: int, height: int, colour: string, align: string, font: string}> $fields
     * @param array<string, string> $values The text of each field.
     * @param bool $sharedSize True for one size for all fields (TextBox::sharedSizes): the QSL cards.
     */
    public function render(string $templateContent, array $fields, array $values, bool $sharedSize = false): string
    {
        $image = imagecreatefromstring($templateContent);
        if ($image === false) {
            throw new RuntimeException('GD cannot read the template');
        }
        // A palette PNG gives wrong colours for the text. A true colour image gives the exact colours.
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $fits = [];
        foreach ($fields as $name => $field) {
            $text = $values[$name] ?? '';
            if ($text !== '') {
                $fits[$name] = $this->fit($field, $this->fonts->path($field['font']), $text);
            }
        }
        $sizes = $sharedSize ? TextBox::sharedSizes($fits) : $fits;

        foreach ($sizes as $name => $size) {
            $field = $fields[$name];
            $text = $values[$name];
            $font = $this->fonts->path($field['font']);
            [$textWidth, $offset] = $this->measure($size, $font, $text);
            [$red, $green, $blue] = sscanf($field['colour'], '#%02x%02x%02x');
            imagettftext(
                $image,
                $size * self::POINTS_PER_PIXEL,
                0,
                // The first letter can start some pixels after the origin. The offset puts the ink in the box.
                TextBox::left($field['x'], $field['width'], $field['align'], $textWidth) - $offset,
                TextBox::baseline($field['y'], $field['height'], $size),
                imagecolorallocate($image, $red, $green, $blue),
                $font,
                $text,
            );
        }

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);

        return (string) ob_get_clean();
    }

    /**
     * The size at which the text fits the box of the field: the full size, or smaller for a wide text.
     *
     * @param array{width: int, height: int} $field
     */
    private function fit(array $field, string $font, string $text): float
    {
        [$fullWidth] = $this->measure($field['height'] / TextBox::HEIGHT_TO_SIZE, $font, $text);
        $size = TextBox::size($field['width'], $field['height'], $fullWidth);
        [$textWidth] = $this->measure($size, $font, $text);
        // GD does not scale the width of small texts in proportion. Thus a second measure can be necessary.
        for ($step = 0; $step < 8 && $textWidth > $field['width']; $step++) {
            $size *= $field['width'] * TextBox::FIT / $textWidth;
            [$textWidth] = $this->measure($size, $font, $text);
        }

        return $size;
    }

    /**
     * The width in pixels of a text at a font size in pixels, and the distance from the origin to its first ink.
     *
     * @return array{0: float, 1: int}
     */
    private function measure(float $size, string $font, string $text): array
    {
        $box = imagettfbbox($size * self::POINTS_PER_PIXEL, 0, $font, $text);

        return $box === false ? [0.0, 0] : [(float) ($box[2] - $box[0]), $box[0]];
    }
}
