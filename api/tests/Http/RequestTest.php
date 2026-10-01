<?php

declare(strict_types=1);

namespace DxFondito\Tests\Http;

use DxFondito\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    #[DataProvider('paths')]
    public function testNormalizesThePath(string $input, string $expected): void
    {
        self::assertSame($expected, Request::normalizePath($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function paths(): array
    {
        return [
            'empty path' => ['', '/'],
            'root' => ['/', '/'],
            'no first slash' => ['health', '/health'],
            'last slash' => ['/seasons/2026/', '/seasons/2026'],
            'two slashes' => ['/seasons//2026', '/seasons/2026'],
        ];
    }
}
