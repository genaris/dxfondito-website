<?php

declare(strict_types=1);

namespace DxFondito\Audit;

final class Changes
{
    /**
     * The values that changed, each with the old and the new value.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function between(array $old, array $new): array
    {
        $changes = [];
        foreach ($new as $key => $value) {
            if ($old[$key] !== $value) {
                $changes[$key] = [$old[$key], $value];
            }
        }

        return $changes;
    }
}
