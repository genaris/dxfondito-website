<?php

declare(strict_types=1);

namespace DxFondito\Logs;

use RuntimeException;

/**
 * Keeps the files in a folder that the web server does not supply (storage/logs/).
 */
final class LocalFileStore implements FileStore
{
    public function __construct(private readonly string $dir)
    {
    }

    public function save(string $content): string
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Cannot make the folder: ' . $this->dir);
        }
        $name = bin2hex(random_bytes(16)) . '.adi';
        if (file_put_contents($this->dir . '/' . $name, $content, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write the file: ' . $name);
        }

        return $name;
    }

    public function read(string $storedName): ?string
    {
        $path = $this->path($storedName);
        $content = is_file($path) ? file_get_contents($path) : false;

        return $content === false ? null : $content;
    }

    public function delete(string $storedName): void
    {
        $path = $this->path($storedName);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $storedName): string
    {
        // The API made the name. This check is a second protection.
        if (preg_match('/^[0-9a-f]{32}\.adi$/', $storedName) !== 1) {
            throw new RuntimeException('Incorrect stored name: ' . $storedName);
        }

        return $this->dir . '/' . $storedName;
    }
}
