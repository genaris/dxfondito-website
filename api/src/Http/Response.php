<?php

declare(strict_types=1);

namespace DxFondito\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            $status,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * A file that the browser saves with its name.
     */
    public static function download(string $content, string $fileName): self
    {
        // The plain name is for old browsers. filename* keeps the characters that are not ASCII.
        $plain = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);

        return new self(200, $content, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => sprintf("attachment; filename=\"%s\"; filename*=UTF-8''%s", $plain, rawurlencode($fileName)),
        ]);
    }

    /**
     * An image or a font that the browser shows or uses.
     *
     * @param string|null $fileName The name for a download, or null.
     */
    public static function file(string $content, string $type, ?string $fileName = null, string $cache = 'no-store'): self
    {
        $headers = ['Content-Type' => $type, 'Cache-Control' => $cache];
        if ($fileName !== null) {
            $headers['Content-Disposition'] = sprintf('inline; filename="%s"', preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName));
        }

        return new self(200, $content, $headers);
    }

    public static function error(int $status, string $message): self
    {
        return self::json(['error' => $message], $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (!isset($this->headers['Cache-Control'])) {
            header('Cache-Control: no-store');
        }
        header('X-Content-Type-Options: nosniff');
        echo $this->body;
    }
}
