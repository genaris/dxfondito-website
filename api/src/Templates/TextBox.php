<?php

declare(strict_types=1);

namespace DxFondito\Templates;

/**
 * The place of a text in the box of a field (system design, section 6.1).
 * The editor of the browser uses the same rules (web/src/qsl.ts).
 */
final class TextBox
{
    // The text fills the height of the box: the font size in pixels is the height divided by this value.
    public const HEIGHT_TO_SIZE = 1.2;

    // The capital letters of the DejaVu fonts are 0.73 of the font size high.
    public const CAP_HEIGHT = 0.72;

    // A text that is wider than the box becomes smaller to this part of the box width.
    // GD measures small texts some pixels narrower than their ink. The margin keeps the ink in the box.
    public const FIT = 0.97;

    /**
     * The font size in pixels for a text with this width at the full size.
     */
    public static function size(int $boxWidth, int $boxHeight, float $fullTextWidth): float
    {
        $size = $boxHeight / self::HEIGHT_TO_SIZE;
        if ($fullTextWidth > $boxWidth && $fullTextWidth > 0) {
            // A text wider than the box becomes smaller.
            $size *= $boxWidth * self::FIT / $fullTextWidth;
        }

        return $size;
    }

    /**
     * The base line that puts the capital letters in the vertical centre of the box.
     */
    public static function baseline(int $y, int $boxHeight, float $size): int
    {
        return (int) round($y + ($boxHeight + self::CAP_HEIGHT * $size) / 2);
    }

    /**
     * The left end of the text, after the alignment in the box.
     */
    public static function left(int $x, int $boxWidth, string $align, float $textWidth): int
    {
        return (int) round(match ($align) {
            'center' => $x + ($boxWidth - $textWidth) / 2,
            'right' => $x + $boxWidth - $textWidth,
            default => $x,
        });
    }
}
