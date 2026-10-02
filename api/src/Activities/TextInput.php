<?php

declare(strict_types=1);

namespace DxFondito\Activities;

use DxFondito\Http\HttpException;

/**
 * Checks of the text values of references and activities.
 */
final class TextInput
{
    public const MAX_DESCRIPTION = 2000;

    /**
     * @throws HttpException 422 for an empty or too long name.
     */
    public static function name(string $name, int $maxLength): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > $maxLength) {
            throw new HttpException(422, 'The name must have 1 to ' . $maxLength . ' characters');
        }

        return $name;
    }

    /**
     * An optional description. An empty text gives null.
     *
     * @throws HttpException 422 for a too long description.
     */
    public static function description(?string $description): ?string
    {
        $description = trim((string) $description);
        if ($description === '') {
            return null;
        }
        if (mb_strlen($description) > self::MAX_DESCRIPTION) {
            throw new HttpException(422, 'The description must have ' . self::MAX_DESCRIPTION . ' or fewer characters');
        }

        return $description;
    }
}
