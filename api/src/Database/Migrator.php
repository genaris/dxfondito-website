<?php

declare(strict_types=1);

namespace DxFondito\Database;

use PDO;
use PDOException;

/**
 * Applies the numbered files of the migrations folder in the order of their names.
 * The schema_migrations table keeps the names of the applied files.
 *
 * A `.sql` file has SQL statements. A `.php` file returns a function that changes data, such as a step
 * that reads the stored files: function (PDO $pdo, string $storageDir): void.
 */
final class Migrator
{
    private const TABLE = 'schema_migrations';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $dir,
        private readonly string $storageDir = '',
    ) {
    }

    /**
     * Applies all pending files.
     *
     * @return list<string> The names of the files that this call applied.
     */
    public function migrate(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                name VARCHAR(100) NOT NULL,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $done = [];
        foreach ($this->pending() as $name) {
            $this->apply($name);
            $done[] = $name;
        }

        return $done;
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->applied()));
    }

    /**
     * @return list<string>
     */
    public function applied(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        return $this->pdo
            ->query('SELECT name FROM ' . self::TABLE . ' ORDER BY name')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function latest(): ?string
    {
        $applied = $this->applied();

        return $applied === [] ? null : end($applied);
    }

    /**
     * Divides the text of a migration file into statements.
     * A statement ends with a semicolon at the end of a line.
     * A line that starts with `--` is a comment.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $lines = preg_split('/\r?\n/', $sql);
        $lines = array_filter($lines, static fn (string $line): bool => !str_starts_with(ltrim($line), '--'));
        $parts = preg_split('/;[ \t]*(\n|$)/', implode("\n", $lines));

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<string>
     */
    private function available(): array
    {
        $names = array_map(
            static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
            [...glob($this->dir . '/*.sql') ?: [], ...glob($this->dir . '/*.php') ?: []],
        );
        sort($names, SORT_STRING);

        return $names;
    }

    private function apply(string $name): void
    {
        $script = $this->dir . '/' . $name . '.php';
        if (is_file($script)) {
            try {
                (require $script)($this->pdo, $this->storageDir);
            } catch (\Throwable $e) {
                throw new MigrationException(sprintf('Migration %s failed: %s', $name, $e->getMessage()), previous: $e);
            }
            $this->record($name);

            return;
        }

        $statements = self::splitStatements((string) file_get_contents($this->dir . '/' . $name . '.sql'));

        // MySQL cannot undo a CREATE TABLE or an ALTER TABLE statement.
        // Thus a failure can leave a part of the file applied. The message gives the statement number.
        foreach ($statements as $index => $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                throw new MigrationException(
                    sprintf('Migration %s failed at statement %d: %s', $name, $index + 1, $e->getMessage()),
                    previous: $e,
                );
            }
        }

        $this->record($name);
    }

    private function record(string $name): void
    {
        $insert = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (name, applied_at) VALUES (?, UTC_TIMESTAMP())');
        $insert->execute([$name]);
    }

    private function tableExists(): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $statement->execute([self::TABLE]);

        return $statement->fetchColumn() !== false;
    }
}
