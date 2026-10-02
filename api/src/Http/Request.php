<?php

declare(strict_types=1);

namespace DxFondito\Http;

final class Request
{
    /** @var array<string, string> */
    public readonly array $headers;

    /** @var array<string, UploadedFile> */
    public readonly array $files;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param bool $secure True if the request came through HTTPS.
     * @param array<string, UploadedFile> $files
     * @param bool $bodyTooLarge True if PHP refused the body because of its size (post_max_size).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        array $headers = [],
        public readonly bool $secure = false,
        array $files = [],
        public readonly bool $bodyTooLarge = false,
    ) {
        $this->files = $files;
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
            files: self::readFiles(),
            // PHP gives no body and no files when the body is larger than post_max_size.
            bodyTooLarge: $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
                && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data'),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * An integer value of the body, or null.
     */
    public function int(string $key): ?int
    {
        $value = $this->body[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^-?[0-9]{1,9}$/', $value) === 1 ? (int) $value : null;
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

    /**
     * @return array<string, UploadedFile> The files of the request. The reader ignores lists of files.
     */
    private static function readFiles(): array
    {
        $files = [];
        foreach ($_FILES as $key => $file) {
            if (is_array($file) && is_string($file['name'] ?? null) && is_string($file['tmp_name'] ?? null)) {
                $files[$key] = new UploadedFile($file['name'], $file['tmp_name'], (int) $file['size'], (int) $file['error']);
            }
        }

        return $files;
    }
}
