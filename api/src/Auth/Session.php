<?php

declare(strict_types=1);

namespace DxFondito\Auth;

/**
 * The data of the session of the browser.
 */
interface Session
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    /**
     * Gives the session a new identifier and keeps its data.
     */
    public function regenerate(): void;

    /**
     * Removes all data and the session.
     */
    public function destroy(): void;
}
