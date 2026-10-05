<?php

declare(strict_types=1);

// Fills the e-mail address of the contacts of the logs that the system had before migration 0006.
// The source is the EMAIL field of the stored ADIF files. The step can run again.

use DxFondito\Logs\EmailBackfill;
use DxFondito\Logs\LocalFileStore;

return static function (PDO $pdo, string $storageDir): void {
    (new EmailBackfill($pdo, new LocalFileStore($storageDir . '/logs')))->run();
};
