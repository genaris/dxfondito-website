<?php

declare(strict_types=1);

namespace DxFondito;

use RuntimeException;

final class Config
{
    public function __construct(
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $migrationSecret,
        public readonly string $storageDir,
    ) {
    }

    public static function load(string $root): self
    {
        $file = $root . '/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('The configuration file is absent: ' . $file);
        }

        return self::fromArray(require $file);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $db = $values['db'] ?? [];
        foreach (['host', 'name', 'user', 'password'] as $key) {
            if (!isset($db[$key]) || !is_string($db[$key])) {
                throw new RuntimeException('The configuration has no value for db.' . $key);
            }
        }
        if (!isset($values['storage_dir']) || !is_string($values['storage_dir'])) {
            throw new RuntimeException('The configuration has no value for storage_dir');
        }

        return new self(
            dbHost: $db['host'],
            dbPort: (int) ($db['port'] ?? 3306),
            dbName: $db['name'],
            dbUser: $db['user'],
            dbPassword: $db['password'],
            migrationSecret: (string) ($values['migration_secret'] ?? ''),
            storageDir: $values['storage_dir'],
        );
    }
}
