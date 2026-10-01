<?php

declare(strict_types=1);

// Applies the pending migration files from the command line.
// Use: php bin/migrate.php

use DxFondito\Config;
use DxFondito\Database\Connection;
use DxFondito\Database\Migrator;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

date_default_timezone_set('UTC');

try {
    $migrator = new Migrator(Connection::open(Config::load($root)), $root . '/migrations');
    $done = $migrator->migrate();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

if ($done === []) {
    echo 'No pending migrations.' . PHP_EOL;
} else {
    foreach ($done as $name) {
        echo 'Applied: ' . $name . PHP_EOL;
    }
}
