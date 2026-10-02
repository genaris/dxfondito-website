<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Logs\FileStore;

final class MemoryFileStore implements FileStore
{
    /** @var array<string, string> */
    public array $files = [];

    public function save(string $content, string $extension = 'adi'): string
    {
        $name = 'file' . (count($this->files) + 1) . '.' . $extension;
        $this->files[$name] = $content;

        return $name;
    }

    public function read(string $storedName): ?string
    {
        return $this->files[$storedName] ?? null;
    }

    public function delete(string $storedName): void
    {
        unset($this->files[$storedName]);
    }
}
