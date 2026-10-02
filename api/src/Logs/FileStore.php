<?php

declare(strict_types=1);

namespace DxFondito\Logs;

/**
 * A store of uploaded files: the original log files (FR-LOG-18) and the template images.
 */
interface FileStore
{
    /**
     * @param string $extension `adi`, `jpg` or `png`.
     * @return string The stored name. The store makes it. It never uses a name from a user.
     */
    public function save(string $content, string $extension = 'adi'): string;

    public function read(string $storedName): ?string;

    public function delete(string $storedName): void;
}
