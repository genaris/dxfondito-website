<?php

declare(strict_types=1);

namespace DxFondito\Auth;

use DxFondito\Http\HttpException;

final class PasswordRules
{
    public const MIN_LENGTH = 8;

    // password_hash() uses only the first 72 bytes.
    public const MAX_BYTES = 72;

    /**
     * @throws HttpException 422 if the password is not acceptable.
     */
    public static function check(string $password): void
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            throw new HttpException(422, 'The password must have ' . self::MIN_LENGTH . ' or more characters');
        }
        if (strlen($password) > self::MAX_BYTES) {
            throw new HttpException(422, 'The password is too long');
        }
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
