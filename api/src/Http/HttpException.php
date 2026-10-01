<?php

declare(strict_types=1);

namespace DxFondito\Http;

use RuntimeException;

/**
 * An error that the API sends to the browser with its status code and its message.
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
