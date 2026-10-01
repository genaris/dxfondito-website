<?php

declare(strict_types=1);

// Entry file of the API.
// On the host, the build adds private-path.php. That file gives the location of the private folder.
// In the local environment, the private folder is the api/ folder of the repository.
$privatePathFile = __DIR__ . '/private-path.php';
$privateDir = is_file($privatePathFile) ? require $privatePathFile : dirname(__DIR__);

require $privateDir . '/src/bootstrap.php';
