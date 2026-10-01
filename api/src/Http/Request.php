<?php

declare(strict_types=1);

namespace DxFondito\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $query = $_GET;
        // The `r` parameter gives the path of the request. Thus the API needs no rewrite rules.
        $route = is_string($query['r'] ?? null) ? $query['r'] : '/';
        unset($query['r']);

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: self::normalizePath($route),
            query: $query,
            body: self::readBody(),
        );
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return preg_replace('#/+#', '/', $path);
    }

    /**
     * @return array<string, mixed>
     */
    private static function readBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_starts_with($contentType, 'application/json')) {
            $data = json_decode((string) file_get_contents('php://input'), true);

            return is_array($data) ? $data : [];
        }

        return $_POST;
    }
}
