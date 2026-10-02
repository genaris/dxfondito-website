<?php

declare(strict_types=1);

namespace DxFondito\Tests\Ranking;

use DxFondito\Ranking\Calculator;
use DxFondito\Ranking\ContactRow;
use PHPUnit\Framework\TestCase;

final class CalculatorTest extends TestCase
{
    private const LEVELS = [5, 10, 15];

    private int $nextId = 1;

    public function testOnePointForEachReferenceAndTheListOfTheReferences(): void
    {
        $ranking = Calculator::ranking([
            $this->reference('LU2ABC', 'EFE-01'),
            $this->reference('LU2ABC', 'DPS-10'),
            $this->reference('LU2ABC', 'DPS-02'),
        ], self::LEVELS);

        self::assertSame(3, $ranking[0]['points']);
        // In the order of the series and the number. DPS and EFE add to the same total (R-PTS-3).
        self::assertSame(['DPS-02', 'DPS-10', 'EFE-01'], $ranking[0]['references']);
    }

    public function testASecondActivityOfAReferenceGivesNoSecondPoint(): void
    {
        // The query of the store is DISTINCT for each reference. The calculation also removes duplicates.
        $ranking = Calculator::ranking([
            $this->reference('LU2ABC', 'DPS-01'),
            $this->reference('LU2ABC', 'DPS-01'),
        ], self::LEVELS);

        self::assertSame(1, $ranking[0]['points']);
        self::assertSame(['DPS-01'], $ranking[0]['references']);
    }

    public function testEqualPointsGiveTheSamePositionInAlphabeticalOrder(): void
    {
        $ranking = Calculator::ranking([
            $this->reference('PY1A', 'DPS-01'),
            $this->reference('CX1A', 'DPS-01'),
            $this->reference('LU2ABC', 'DPS-01'),
            $this->reference('LU2ABC', 'DPS-02'),
            $this->reference('ZZ9Z', 'DPS-01'),
        ], self::LEVELS);

        self::assertSame(
            [[1, 'LU2ABC', 2], [2, 'CX1A', 1], [2, 'PY1A', 1], [2, 'ZZ9Z', 1]],
            array_map(static fn (array $row): array => [$row['position'], $row['callSign'], $row['points']], $ranking),
        );
    }

    public function testThePositionAfterATieSkipsTheSharedPositions(): void
    {
        $ranking = Calculator::ranking([
            $this->reference('AA1A', 'DPS-01'),
            $this->reference('AA1A', 'DPS-02'),
            $this->reference('BB1B', 'DPS-01'),
            $this->reference('BB1B', 'DPS-02'),
            $this->reference('CC1C', 'DPS-01'),
        ], self::LEVELS);

        self::assertSame([1, 1, 3], array_column($ranking, 'position'));
    }

    public function testGivesTheReachedLevels(): void
    {
        $references = [];
        for ($number = 1; $number <= 11; $number++) {
            $references[] = $this->reference('LU2ABC', sprintf('DPS-%02d', $number));
        }

        self::assertSame([5, 10], Calculator::ranking($references, self::LEVELS)[0]['levels']);
    }

    public function testTheFirstContactIsTheEarliestOfAllActivitiesOfTheReference(): void
    {
        $first = Calculator::firstContacts([
            $this->contact('LU2ABC', 'DPS-01', qsoAt: '2026-08-01 10:00:00', activityId: 2, operator: 'LU3OP'),
            $this->contact('LU2ABC', 'DPS-01', qsoAt: '2026-05-10 12:00:00', activityId: 1, operator: 'LU1OP'),
            $this->contact('LU2ABC', 'DPS-01', qsoAt: '2026-05-10 12:30:00', activityId: 1, operator: 'LU2OP'),
        ]);

        self::assertCount(1, $first);
        // R-OPR-4 and R-OPR-5a: the operator of the first contact.
        self::assertSame('LU1OP', $first[0]->operatorCallSign);
    }

    public function testTheLowerIdDecidesBetweenContactsAtTheSameTime(): void
    {
        $earlier = $this->contact('LU2ABC', 'DPS-01', operator: 'LU1OP');
        $later = $this->contact('LU2ABC', 'DPS-01', operator: 'LU2OP');

        self::assertSame('LU1OP', Calculator::firstContacts([$later, $earlier])[0]->operatorCallSign);
    }

    public function testEachSeasonHasItsOwnFirstContacts(): void
    {
        $first = Calculator::firstContacts([
            $this->contact('LU2ABC', 'DPS-01', season: 2026),
            $this->contact('LU2ABC', 'DPS-01', season: 2027),
        ]);

        self::assertCount(2, $first);
    }

    public function testTheCertificateDateIsTheStartDateOfTheActivityOfTheLastNecessaryPoint(): void
    {
        $first = [];
        $dates = ['2026-03-01', '2026-01-10', '2026-02-01', '2026-05-01', '2026-04-01', '2026-06-01'];
        foreach ($dates as $index => $date) {
            $first[] = $this->contact('LU2ABC', sprintf('DPS-%02d', $index + 1), startDate: $date);
        }

        // The fifth activity in the order of the start dates starts on 2026-05-01.
        self::assertSame([['points' => 5, 'date' => '2026-05-01']], Calculator::certificates($first, self::LEVELS));
    }

    public function testNoCertificateBelowTheFirstLevel(): void
    {
        self::assertSame([], Calculator::certificates([$this->contact('LU2ABC', 'DPS-01')], self::LEVELS));
    }

    public function testGivesThePointsForTheNextLevel(): void
    {
        self::assertSame(5, Calculator::pointsToNextLevel(0, self::LEVELS));
        self::assertSame(1, Calculator::pointsToNextLevel(9, self::LEVELS));
        self::assertSame(5, Calculator::pointsToNextLevel(10, self::LEVELS));
        self::assertNull(Calculator::pointsToNextLevel(15, self::LEVELS));
    }

    public function testTheParticipantsOfAnActivityHaveTheOperatorOfTheEarliestContact(): void
    {
        $participants = Calculator::activityParticipants([
            $this->contact('PY1A', 'DPS-01', qsoAt: '2026-05-10 13:00:00', operator: 'LU2OP'),
            $this->contact('PY1A', 'DPS-01', qsoAt: '2026-05-10 12:00:00', operator: 'LU1OP'),
            $this->contact('CX1A', 'DPS-01', operator: 'LU2OP'),
        ]);

        self::assertSame(['CX1A', 'PY1A'], array_map(static fn (ContactRow $row): string => $row->baseCallSign, $participants));
        self::assertSame('LU1OP', $participants[1]->operatorCallSign);
    }

    /**
     * @return array{callSign: string, referenceId: int, seriesCode: string, number: int}
     */
    private function reference(string $callSign, string $code): array
    {
        [$series, $number] = explode('-', $code);

        return ['callSign' => $callSign, 'referenceId' => crc32($code), 'seriesCode' => $series, 'number' => (int) $number];
    }

    private function contact(
        string $base,
        string $reference,
        string $qsoAt = '2026-05-10 12:00:00',
        int $activityId = 1,
        int $season = 2026,
        string $startDate = '2026-05-10',
        string $operator = 'LU1OP',
        ?string $callSign = null,
    ): ContactRow {
        [$series] = explode('-', $reference);

        return new ContactRow(
            id: $this->nextId++,
            baseCallSign: $base,
            callSign: $callSign ?? $base,
            name: null,
            qsoAt: $qsoAt,
            frequency: '7.074',
            band: null,
            mode: 'SSB',
            activityId: $activityId,
            startDate: $startDate,
            season: $season,
            referenceId: crc32($reference),
            seriesCode: $series,
            referenceCode: $reference,
            referenceName: 'Name of ' . $reference,
            operatorCallSign: $operator,
        );
    }
}
