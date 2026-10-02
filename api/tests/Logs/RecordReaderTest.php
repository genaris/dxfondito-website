<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DxFondito\Logs\Contact;
use DxFondito\Logs\RecordReader;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class RecordReaderTest extends TestCase
{
    private const RECORD = [
        'CALL' => 'lu2abc/p',
        'QSO_DATE' => '20260510',
        'TIME_ON' => '1432',
        'MODE' => 'ssb',
        'FREQ' => '7.0740000',
        'NAME' => ' José ',
        'RST_SENT' => '59',
        'RST_RCVD' => '57',
        'STATION_CALLSIGN' => 'lu1abc',
    ];

    public function testMakesAContact(): void
    {
        $contact = RecordReader::read(3, self::RECORD);

        self::assertInstanceOf(Contact::class, $contact);
        self::assertSame(3, $contact->record);
        self::assertSame('LU2ABC/P', $contact->callSign);
        self::assertSame('LU2ABC', $contact->baseCallSign);
        self::assertSame('2026-05-10 14:32:00', $contact->qsoAt);
        self::assertSame('7.074', $contact->frequency);
        self::assertNull($contact->band);
        self::assertSame('SSB', $contact->mode);
        self::assertSame('José', $contact->name);
        self::assertSame('59', $contact->rstSent);
        self::assertSame('57', $contact->rstRcvd);
        self::assertSame('LU1ABC', $contact->stationCallSign);
    }

    public function testReadsTheSecondsOfTheTime(): void
    {
        self::assertSame('2026-05-10 14:32:05', $this->contact(['TIME_ON' => '143205'])->qsoAt);
    }

    public function testABandIsSufficientWithoutAFrequency(): void
    {
        $contact = $this->contact(['FREQ' => null, 'BAND' => '40M']);

        self::assertNull($contact->frequency);
        self::assertSame('40m', $contact->band);
    }

    public function testTheOptionalFieldsCanBeAbsent(): void
    {
        $contact = $this->contact(['NAME' => null, 'RST_SENT' => null, 'RST_RCVD' => '', 'STATION_CALLSIGN' => null]);

        self::assertNull($contact->name);
        self::assertNull($contact->rstSent);
        self::assertNull($contact->rstRcvd);
        self::assertNull($contact->stationCallSign);
    }

    public function testCutsALongName(): void
    {
        self::assertSame(100, mb_strlen($this->contact(['NAME' => str_repeat('ñ', 150)])->name));
    }

    #[TestWith(['CALL'])]
    #[TestWith(['QSO_DATE'])]
    #[TestWith(['TIME_ON'])]
    #[TestWith(['MODE'])]
    public function testRefusesARecordWithoutAMandatoryField(string $field): void
    {
        self::assertSame([['field' => $field, 'problem' => 'missing']], $this->problems([$field => null]));
    }

    public function testRefusesARecordWithoutFrequencyAndBand(): void
    {
        self::assertSame([['field' => 'FREQ', 'problem' => 'missing']], $this->problems(['FREQ' => null]));
    }

    #[TestWith(['CALL', 'LU 2 ABC!'])]
    #[TestWith(['CALL', 'L'])]
    #[TestWith(['QSO_DATE', '2026-05-10'])]
    #[TestWith(['QSO_DATE', '20260230'])]
    #[TestWith(['TIME_ON', '14:32'])]
    #[TestWith(['TIME_ON', '2460'])]
    #[TestWith(['TIME_ON', '143'])]
    #[TestWith(['MODE', 'S S B'])]
    #[TestWith(['FREQ', 'abc'])]
    #[TestWith(['FREQ', '0'])]
    #[TestWith(['FREQ', '12345'])]
    #[TestWith(['BAND', '40'])]
    public function testRefusesAnIncorrectValue(string $field, string $value): void
    {
        self::assertSame([['field' => $field, 'problem' => 'invalid']], $this->problems([$field => $value]));
    }

    public function testGivesAllProblemsOfARecord(): void
    {
        $problems = $this->problems(['CALL' => null, 'QSO_DATE' => '2026', 'MODE' => null]);

        self::assertSame(['CALL', 'QSO_DATE', 'MODE'], array_column($problems, 'field'));
    }

    /**
     * @param array<string, ?string> $changes Null removes the field.
     */
    private function contact(array $changes): Contact
    {
        $result = RecordReader::read(1, $this->record($changes));
        self::assertInstanceOf(Contact::class, $result, json_encode($result));

        return $result;
    }

    /**
     * @param array<string, ?string> $changes
     * @return list<array{field: string, problem: string}>
     */
    private function problems(array $changes): array
    {
        $result = RecordReader::read(1, $this->record($changes));
        self::assertIsArray($result);

        return $result;
    }

    /**
     * @param array<string, ?string> $changes
     * @return array<string, string>
     */
    private function record(array $changes): array
    {
        return array_filter(array_merge(self::RECORD, $changes), static fn (?string $value): bool => $value !== null);
    }
}
