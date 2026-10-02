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

    // A text that needs a size below this part of the median size is an exception: it does not make the
    // other fields smaller (sharedSizes).
    public const OUTLIER = 0.75;

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
     * One size for all fields of a QSL card (system design, section 6.1): the smallest size that fits.
     * A field that needs much less than the median size, such as a long name, keeps its own smaller size.
     * Thus a long name does not make the whole card small.
     *
     * @param array<string, float> $fits The size at which the text of each field fits its box.
     * @return array<string, float> The size of each field.
     */
    public static function sharedSizes(array $fits): array
    {
        if ($fits === []) {
            return [];
        }
        $sorted = array_values($fits);
        sort($sorted);
        $median = $sorted[intdiv(count($sorted), 2)];
        $common = min(array_filter($fits, static fn (float $size): bool => $size >= self::OUTLIER * $median));

        return array_map(static fn (float $size): float => min($size, $common), $fits);
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
