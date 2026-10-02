<?php

declare(strict_types=1);

namespace DxFondito\Http;

final class Request
{
    /** @var array<string, string> */
    public readonly array $headers;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param bool $secure True if the request came through HTTPS.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        array $headers = [],
        public readonly bool $secure = false,
    ) {
        // The names of the headers are not case-sensitive.
        $this->headers = array_change_key_case($headers, CASE_LOWER);
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
            headers: self::readHeaders(),
            secure: self::isSecure(),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * A string value of the body, or an empty string.
     */
    public function string(string $key): string
    {
        $value = $this->body[$key] ?? '';

        return is_string($value) ? $value : '';
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

    /**
     * @return array<string, string>
     */
    private static function readHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }

        return $headers;
    }

    private static function isSecure(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        // Some hosts end HTTPS at a proxy in front of PHP.
        return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
