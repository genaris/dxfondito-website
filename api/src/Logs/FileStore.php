<?php

declare(strict_types=1);

namespace DxFondito\Logs;

/**
 * The store of the original log files (FR-LOG-18).
 */
interface FileStore
{
    /**
     * @return string The stored name. The store makes it. It never uses a name from a user.
     */
    public function save(string $content): string;

    public function read(string $storedName): ?string;

    public function delete(string $storedName): void;
}
