<?php

declare(strict_types=1);

// Fills the station call sign of the contacts of the logs that the system had before migration 0002.
// The source is the STATION_CALLSIGN field of the stored ADIF files. The step can run again.

use DxFondito\Logs\LocalFileStore;
use DxFondito\Logs\StationBackfill;

return static function (PDO $pdo, string $storageDir): void {
    (new StationBackfill($pdo, new LocalFileStore($storageDir . '/logs')))->run();
};
