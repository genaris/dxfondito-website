<?php

declare(strict_types=1);

namespace DxFondito\Http;

final class PathId
{
    /**
     * The `{id}` parameter of the path as a number.
     *
     * @param array<string, string> $params
     * @throws HttpException 404 if the value is not an identifier.
     */
    public static function from(array $params, string $notFound): int
    {
        $id = $params['id'] ?? '';
        if (preg_match('/^[1-9][0-9]{0,9}$/', $id) !== 1) {
            throw new HttpException(404, $notFound);
        }

        return (int) $id;
    }
}
