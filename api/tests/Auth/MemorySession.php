<?php

declare(strict_types=1);

namespace DxFondito\Tests\Auth;

use DxFondito\Auth\Session;

final class MemorySession implements Session
{
    /** @var array<string, mixed> */
    public array $data = [];
    public int $regenerations = 0;
    public bool $destroyed = false;

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
    }
}
