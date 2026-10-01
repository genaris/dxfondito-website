<?php

declare(strict_types=1);

namespace DxFondito\Tests\Database;

use DxFondito\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testDividesTheTextIntoStatements(): void
    {
        $sql = "CREATE TABLE a (\n    id INT\n);\n\nINSERT INTO a VALUES (1);\n";

        self::assertSame(
            ["CREATE TABLE a (\n    id INT\n)", 'INSERT INTO a VALUES (1)'],
            Migrator::splitStatements($sql),
        );
    }

    public function testIgnoresCommentLines(): void
    {
        $sql = "-- A comment; with a semicolon;\nINSERT INTO a VALUES (1);\n  -- A second comment\n";

        self::assertSame(['INSERT INTO a VALUES (1)'], Migrator::splitStatements($sql));
    }

    public function testKeepsASemicolonThatIsNotAtTheEndOfALine(): void
    {
        $sql = "INSERT INTO a VALUES ('x; y');\r\nINSERT INTO a VALUES (2)";

        self::assertSame(
            ["INSERT INTO a VALUES ('x; y')", 'INSERT INTO a VALUES (2)'],
            Migrator::splitStatements($sql),
        );
    }

    public function testTheInitialSchemaHasStatements(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/0001_initial_schema.sql');

        $statements = Migrator::splitStatements($sql);

        self::assertCount(12, $statements);
        self::assertStringStartsWith('CREATE TABLE users', $statements[0]);
    }
}
