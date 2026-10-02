<?php

declare(strict_types=1);

namespace DxFondito\Auth;

use RuntimeException;

/**
 * A PHP session. The session starts only when a request needs it.
 * Thus the public pages make no session files and send no cookie.
 */
final class PhpSession implements Session
{
    private const NAME = 'dxfondito_session';

    private bool $started = false;

    /**
     * @param string $saveDir The folder for the session files. The host can delete the files of
     *   the shared folder of PHP before the end of the session. Thus the API uses its own folder.
     */
    public function __construct(
        private readonly string $saveDir,
        private readonly bool $secure,
    ) {
    }

    public function get(string $key): mixed
    {
        // Without a cookie, there is no session to read.
        if (!$this->started && !isset($_COOKIE[self::NAME])) {
            return null;
        }
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        if (!$this->started && !isset($_COOKIE[self::NAME])) {
            return;
        }
        $this->start();
        $_SESSION = [];
        session_destroy();
        setcookie(self::NAME, '', $this->cookieOptions() + ['expires' => 1]);
        $this->started = false;
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }
        if (!is_dir($this->saveDir) && !mkdir($this->saveDir, 0700, true) && !is_dir($this->saveDir)) {
            throw new RuntimeException('Cannot make the session folder: ' . $this->saveDir);
        }

        session_name(self::NAME);
        session_save_path($this->saveDir);
        session_set_cookie_params($this->cookieOptions() + ['lifetime' => 0]);
        session_start([
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            // The Authenticator ends a session after two hours without activity.
            // The files stay a little longer.
            'gc_maxlifetime' => 3 * 3600,
        ]);
        $this->started = true;
    }

    /**
     * @return array{path: string, secure: bool, httponly: bool, samesite: string}
     */
    private function cookieOptions(): array
    {
        return [
            'path' => '/',
            // The local environment has no HTTPS. The host has it.
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
