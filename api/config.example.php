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

    // The SMTP server of the QSL mailer. Without a host, the mailer is off.
    // In the local environment, the Mailpit container catches all messages: http://localhost:8025.
    // On the host (DonWeb): host '<user>.ferozo.com', port 465, encryption 'ssl', and the mailbox of the group.
    'mail' => [
        'host' => getenv('MAIL_HOST') ?: 'mailpit',
        'port' => (int) (getenv('MAIL_PORT') ?: 1025),
        'encryption' => getenv('MAIL_ENCRYPTION') ?: '',
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'from_address' => getenv('MAIL_FROM') ?: 'qsl@dxfondito.com.ar',
        'from_name' => 'Grupo DX Fondito',
        // DonWeb accepts 100 messages for each hour and mailbox. The default leaves a margin.
        'hourly_limit' => 90,
        // The address of the site, for the {sitio} variable of the messages.
        'site_url' => getenv('SITE_URL') ?: 'http://localhost:5173/',
    ],
];
