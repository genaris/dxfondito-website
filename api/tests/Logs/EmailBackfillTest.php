<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Logs\EmailBackfill;
use PHPUnit\Framework\TestCase;

final class EmailBackfillTest extends TestCase
{
    public function testGivesTheAddressOfEachRecordByCallSignAndTime(): void
    {
        $adif = "<EOH>\n"
            . "<CALL:6>LU9ZZA <QSO_DATE:8>20261004 <TIME_ON:4>1200 <FREQ:4>7.13 <MODE:3>SSB <EMAIL:17>Juana@Example.com <EOR>\n"
            . "<CALL:6>LU9ZZB <QSO_DATE:8>20261004 <TIME_ON:4>1300 <FREQ:4>7.13 <MODE:3>SSB <EOR>\n";

        self::assertSame(['LU9ZZA|2026-10-04 12:00:00' => 'juana@example.com'], EmailBackfill::emails($adif));
    }

    public function testTheMigrationGivesAFunction(): void
    {
        self::assertIsCallable(require dirname(__DIR__, 2) . '/migrations/0007_contact_email_backfill.php');
    }
}
