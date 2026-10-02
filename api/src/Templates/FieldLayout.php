<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use DxFondito\Http\HttpException;

/**
 * The box, the colour, the alignment and the font of each field of a template (system design, section 6.1).
 * The box is in pixels of the image. The text fills the height of the box and becomes smaller if it is
 * wider than the box (TextBox).
 */
final class FieldLayout
{
    public const QSL_FIELDS = ['call_sign', 'name', 'date', 'time', 'frequency', 'mode', 'rst'];
    public const ALIGNMENTS = ['left', 'center', 'right'];
    public const MIN_WIDTH = 10;
    public const MIN_HEIGHT = 6;
    public const MAX_HEIGHT = 500;

    /**
     * Checks the fields that the browser sends.
     *
     * @param mixed $input The decoded JSON value.
     * @param list<string> $names The necessary fields.
     * @return array<string, array{x: int, y: int, width: int, height: int, colour: string, align: string, font: string}>
     * @throws HttpException 422 for an absent field or an incorrect value.
     */
    public static function check(mixed $input, array $names, int $width, int $height): array
    {
        if (!is_array($input)) {
            throw new HttpException(422, 'The fields are not correct');
        }

        $fields = [];
        foreach ($names as $name) {
            $field = $input[$name] ?? null;
            if (!is_array($field)) {
                throw new HttpException(422, 'The field ' . $name . ' is absent');
            }
            $x = $field['x'] ?? null;
            $y = $field['y'] ?? null;
            $boxWidth = $field['width'] ?? null;
            $boxHeight = $field['height'] ?? null;
            $colour = $field['colour'] ?? null;
            $align = $field['align'] ?? null;
            $font = $field['font'] ?? null;
            if (
                !is_int($x) || $x < 0
                || !is_int($y) || $y < 0
                || !is_int($boxWidth) || $boxWidth < self::MIN_WIDTH || $x + $boxWidth > $width
                || !is_int($boxHeight) || $boxHeight < self::MIN_HEIGHT || $boxHeight > self::MAX_HEIGHT || $y + $boxHeight > $height
                || !is_string($colour) || preg_match('/^#[0-9a-fA-F]{6}$/', $colour) !== 1
                || !in_array($align, self::ALIGNMENTS, true)
                || !is_string($font) || !Fonts::exists($font)
            ) {
                throw new HttpException(422, 'The field ' . $name . ' has an incorrect value');
            }
            $fields[$name] = [
                'x' => $x,
                'y' => $y,
                'width' => $boxWidth,
                'height' => $boxHeight,
                'colour' => strtoupper($colour),
                'align' => $align,
                'font' => $font,
            ];
        }

        return $fields;
    }

    /**
     * Decodes the JSON text of the fields.
     *
     * @throws HttpException 422 for text that is not JSON.
     */
    public static function decode(string $json): mixed
    {
        $value = json_decode($json, true);
        if ($value === null && trim($json) !== 'null') {
            throw new HttpException(422, 'The fields are not correct');
        }

        return $value;
    }
}
