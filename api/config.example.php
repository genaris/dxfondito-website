<?php

// Copy this file to config.php and change the values.
// The default values are for the local environment (compose.yaml).
// Do not add config.php to the Git repository.

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'dxfondito',
        'user' => getenv('DB_USER') ?: 'dxfondito',
        'password' => getenv('DB_PASSWORD') ?: 'dxfondito',
    ],

    // The migration page is off when this value is empty.
    // On the host, use a long random value.
    'migration_secret' => getenv('MIGRATION_SECRET') ?: '',

    // The folder for the uploaded files. The web server must not supply this folder.
    'storage_dir' => __DIR__ . '/storage',
];
