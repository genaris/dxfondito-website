<?php

declare(strict_types=1);

namespace DxFondito\Templates;

/**
 * The TrueType fonts of the fonts/ folder. They have an open licence (fonts/LICENSE-DejaVu.txt).
 */
final class Fonts
{
    public const NAMES = ['sans', 'sans-bold', 'serif', 'serif-bold', 'mono-bold'];

    public function __construct(private readonly string $dir)
    {
    }

    public static function exists(string $name): bool
    {
        return in_array($name, self::NAMES, true);
    }

    public function path(string $name): string
    {
        return $this->dir . '/' . (self::exists($name) ? $name : 'sans') . '.ttf';
    }
}
