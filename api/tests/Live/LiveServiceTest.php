<?php

declare(strict_types=1);

namespace DxFondito\Tests\Live;

use DateTimeImmutable;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Live\AdifRecord;
use DxFondito\Live\LiveService;
use DxFondito\Tests\Activities\MemoryActivityStore;
use DxFondito\Tests\Activities\MemoryReferenceStore;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use DxFondito\Tests\Templates\MemoryRankingStore;
use PHPUnit\Framework\TestCase;

final class LiveServiceTest extends TestCase
{
    private const ID_1 = '0b6f1c2e-4a51-4c0e-9d1b-2f3a4b5c6d01';
    private const ID_2 = '0b6f1c2e-4a51-4c0e-9d1b-2f3a4b5c6d02';

    private MemoryUserStore $users;
    private MemoryLiveStore $live;
    private MemoryAuditLog $audit;
    private MemoryRankingStore $ranking;
    private LiveService $service;
    private User $operator;
    private User $otherOperator;
    private User $admin;
    private int $activityId;
    private int $efeActivityId;
    private string $now = '2026-10-04 14:30:00';

    protected function setUp(): void
    {
        $this->users = new MemoryUserStore();
        $this->operator = $this->user('LU2AOG', User::OPERATOR);
        $this->otherOperator = $this->user('LU3EBQ', User::OPERATOR);
        $this->admin = $this->user('LU1ADM', User::ADMINISTRATOR);

        $references = new MemoryReferenceStore();
        $activities = new MemoryActivityStore($references);
        $dps = $references->create(1, 5, 'Parque Rivadavia', null);
        $efe = $references->create(2, 2, 'Día del Radioaficionado', null);
        $this->activityId = $activities->create($dps, 2026, '2026-10-04', '13:00', '2026-10-04', '18:00', null);
        $this->efeActivityId = $activities->create($efe, 2026, '2026-10-11', '13:00', '2026-10-11', '18:00', null);

        $this->live = new MemoryLiveStore();
        $this->audit = new MemoryAuditLog();
        $this->ranking = new MemoryRankingStore();
        $this->service = new LiveService(
            $this->live,
            $activities,
            $this->users,
            $this->ranking,
            $this->audit,
            fn (): DateTimeImmutable => new DateTimeImmutable($this->now . ' UTC'),
        );
    }

    public function testSavesANewContactInTheLiveLogOfTheOperator(): void
    {
        $result = $this->sync([$this->save(self::ID_1)]);

        self::assertSame('ok', $result['results'][0]['status']);
        $contact = $result['results'][0]['contact'];
        self::assertSame(1, $contact['version']);
        self::assertSame('LU1ABC/P', $contact['callSign']);
        self::assertSame('LU2AOG/A', $contact['station']);
        self::assertSame('2026-10-04T14:29:00Z', $contact['qsoAt']);
        self::assertSame('7.130', $contact['frequency']);
        self::assertSame('40m', $contact['band']);
        self::assertSame('LU2AOG', $contact['enteredBy']);
        self::assertSame([], $result['results'][0]['warnings']);
        self::assertSame('LU1ABC', $this->live->find(self::ID_1)?->baseCallSign);
        self::assertSame(['live-log.create'], $this->audit->actions());
        self::assertSame(['label' => 'DPS-05 (2026-10-04)', 'operator' => 'LU2AOG/A'], $this->audit->entries[0]['detail']);
    }

    public function testASecondSendingOfTheSameContactChangesNothing(): void
    {
        $this->sync([$this->save(self::ID_1)]);
        $result = $this->sync([$this->save(self::ID_1)]);

        self::assertSame('ok', $result['results'][0]['status']);
        self::assertSame(1, $result['results'][0]['contact']['version']);
        self::assertCount(1, $this->live->contacts);
        self::assertSame(['live-log.create'], $this->audit->actions());
    }

    public function testACorrectionGivesANewVersionAndAnEntryOfTheRecord(): void
    {
        $this->sync([$this->save(self::ID_1)]);
        $result = $this->sync([$this->save(self::ID_1, ['rstRcvd' => '57', 'notes' => 'QRP, 5 W'], 1)]);

        self::assertSame('ok', $result['results'][0]['status']);
        self::assertSame(2, $result['results'][0]['contact']['version']);
        self::assertSame('57', $result['results'][0]['contact']['rstRcvd']);
        self::assertSame(['live-log.create', 'live-contact.update'], $this->audit->actions());
        self::assertSame(['rstRcvd' => ['59', '57'], 'notes' => [null, 'QRP, 5 W']], $this->audit->entries[1]['detail']['changes']);
    }

    public function testACorrectionOfAnOlderVersionIsAConflict(): void
    {
        $this->sync([$this->save(self::ID_1)]);
        $this->sync([$this->save(self::ID_1, ['rstRcvd' => '57'], 1)]);

        // A second browser corrects the first version.
        $result = $this->sync([$this->save(self::ID_1, ['rstRcvd' => '58'], 1)]);

        self::assertSame('conflict', $result['results'][0]['status']);
        self::assertSame('57', $result['results'][0]['server']['rstRcvd']);
        self::assertSame('57', $this->live->find(self::ID_1)?->rstRcvd);
    }

    public function testANewContactThatADifferentRequestMadeFirstIsAConflict(): void
    {
        $this->sync([$this->save(self::ID_1)]);
        $this->live->raceNext = $this->live->find(self::ID_1)?->withVersion(2);
        unset($this->live->contacts[self::ID_1]);

        $result = $this->sync([$this->save(self::ID_1, ['rstRcvd' => '55'])]);

        self::assertSame('conflict', $result['results'][0]['status']);
        self::assertSame(2, $result['results'][0]['server']['version']);
    }

    public function testACorrectionOfADeletedContactIsAConflict(): void
    {
        $this->sync([$this->save(self::ID_1)]);
        $this->sync([['clientId' => self::ID_1, 'op' => 'delete']]);

        $result = $this->sync([$this->save(self::ID_1, ['rstRcvd' => '57'], 1)]);

        self::assertSame('conflict', $result['results'][0]['status']);
        self::assertNull($result['results'][0]['server']);
    }

    public function testDeletesAContactAndKeepsTheDeletionForTheOtherBrowsers(): void
    {
        $this->sync([$this->save(self::ID_1)]);
        $cursor = $this->sync([])['cursor'];
        $this->now = '2026-10-04 14:31:00';

        $result = $this->sync([['clientId' => self::ID_1, 'op' => 'delete']]);
        $again = $this->sync([['clientId' => self::ID_1, 'op' => 'delete']]);

        self::assertSame('ok', $result['results'][0]['status']);
        self::assertSame('ok', $again['results'][0]['status']);
        self::assertSame([], $this->live->contacts);
        self::assertSame([], $this->live->logs);
        self::assertSame([self::ID_1], $this->service->state($this->activityId, $cursor)['deleted']);
        self::assertSame(['live-log.create', 'live-contact.delete'], $this->audit->actions());
    }

    public function testADifferentOperatorMovesTheContactToItsLiveLog(): void
    {
        $this->sync([$this->save(self::ID_1)]);
        $firstLog = $this->live->logOf[self::ID_1];

        $result = $this->sync([$this->save(self::ID_1, ['operatorId' => $this->otherOperator->id], 1)]);

        self::assertSame('LU3EBQ/A', $result['results'][0]['contact']['station']);
        self::assertNotSame($firstLog, $this->live->logOf[self::ID_1]);
        self::assertSame([$this->activityId . ':' . $this->otherOperator->id], array_keys($this->live->logs));
    }

    public function testAnOperatorEntersTheContactsOfADifferentOperator(): void
    {
        $result = $this->sync([$this->save(self::ID_1, ['operatorId' => $this->otherOperator->id])]);

        self::assertSame('LU3EBQ/A', $result['results'][0]['contact']['station']);
        self::assertSame('LU2AOG', $result['results'][0]['contact']['enteredBy']);
    }

    public function testTheStationOfAnEfeActivityHasNoSuffix(): void
    {
        $result = $this->service->sync($this->operator, $this->efeActivityId, [$this->save(self::ID_1, ['qsoAt' => '2026-10-11T14:00:00Z'])], null);

        self::assertSame('LU2AOG', $result['results'][0]['contact']['station']);
    }

    public function testGivesTheProblemsOfAContactWithoutStoppingTheOthers(): void
    {
        $result = $this->sync([
            $this->save(self::ID_1, ['callSign' => '', 'frequency' => '', 'mode' => 'CW', 'qsoAt' => 'ayer']),
            $this->save(self::ID_2),
        ]);

        self::assertSame('error', $result['results'][0]['status']);
        self::assertEqualsCanonicalizing(
            [
                ['field' => 'CALL', 'problem' => 'missing'],
                ['field' => 'QSO_DATE', 'problem' => 'invalid'],
                ['field' => 'FREQ', 'problem' => 'missing'],
                ['field' => 'MODE', 'problem' => 'invalid'],
            ],
            $result['results'][0]['problems'],
        );
        self::assertSame('ok', $result['results'][1]['status']);
    }

    public function testRefusesAnUnknownOperatorAndAnIncorrectIdentifier(): void
    {
        $result = $this->sync([
            $this->save(self::ID_1, ['operatorId' => 99]),
            ['clientId' => 'abc', 'op' => 'save'],
            ['clientId' => self::ID_2, 'op' => 'move'],
        ]);

        self::assertSame(['error', 'error', 'error'], array_column($result['results'], 'status'));
        self::assertSame('The operator does not exist', $result['results'][0]['error']);
    }

    public function testAContactOutOfTheHoursOfTheActivityGivesAWarning(): void
    {
        $late = $this->sync([$this->save(self::ID_1, ['qsoAt' => '2026-10-04T18:05:00Z'])]);
        $otherDay = $this->sync([$this->save(self::ID_2, ['qsoAt' => '2026-10-05T14:00:00Z'])]);

        self::assertSame([['kind' => 'hours', 'value' => '18:05']], $late['results'][0]['warnings']);
        self::assertSame([['kind' => 'date', 'value' => '2026-10-05']], $otherDay['results'][0]['warnings']);
    }

    public function testTheStateGivesTheOperatorsAndTheChangesSinceTheCursor(): void
    {
        $this->now = '2026-10-04 14:20:00';
        $this->sync([$this->save(self::ID_1)]);
        $this->now = '2026-10-04 14:30:00';
        $state = $this->service->state($this->activityId, null);

        self::assertSame(['LU1ADM/A', 'LU2AOG/A', 'LU3EBQ/A'], array_column($state['operators'], 'station'));
        self::assertSame([self::ID_1], array_column($state['contacts'], 'clientId'));
        // The cursor gives the changes of the last seconds again.
        self::assertSame('2026-10-04T14:29:50Z', $state['cursor']);
        self::assertSame('2026-10-04T14:30:00.000Z', $state['serverTime']);

        $this->now = '2026-10-04 14:45:00';
        $this->sync([$this->save(self::ID_2, ['callSign' => 'CX2AB'])]);
        $changes = $this->service->state($this->activityId, $state['cursor']);

        self::assertSame([self::ID_2], array_column($changes['contacts'], 'clientId'));
        self::assertSame([], $changes['deleted']);
    }

    public function testRefusesAnIncorrectCursorAndTooManyChanges(): void
    {
        $this->assertStatus(422, fn () => $this->service->state($this->activityId, '2026-10-04 14:00'));
        $this->assertStatus(422, fn () => $this->service->sync($this->operator, $this->activityId, 'changes', null));
        $this->assertStatus(422, fn () => $this->service->sync($this->operator, $this->activityId, array_fill(0, 201, []), null));
        $this->assertStatus(404, fn () => $this->service->state(99, null));
    }

    public function testTheLookupGivesTheNamesAndTheReferencesOfTheSeason(): void
    {
        $this->live->names = ['LU1ABC' => 'Juana Pérez'];
        $this->ranking->seasonReferences = [
            ['callSign' => 'LU1ABC', 'referenceId' => 1, 'seriesCode' => 'DPS', 'number' => 5],
            ['callSign' => 'LU1ABC', 'referenceId' => 7, 'seriesCode' => 'EFE', 'number' => 2],
        ];

        $lookup = $this->service->lookup($this->activityId);

        self::assertSame('DPS-05', $lookup['reference']);
        self::assertSame(['LU1ABC' => 'Juana Pérez'], $lookup['names']);
        self::assertSame(['LU1ABC' => ['DPS-05', 'EFE-02']], $lookup['references']);
    }

    public function testTheAdifFileHasTheContactsOfTheOperatorForTheOperatorAndTheAdministrators(): void
    {
        $this->sync([$this->save(self::ID_1, ['notes' => "QRP\n5 W"])]);
        $this->live->uploadedRecords[$this->activityId . ':' . $this->operator->id] = [
            new AdifRecord('CX2AB', '2026-10-04 14:00:00', '144.200', '2m', 'SSB', '59', '59', null, 'LU2AOG/A', 'LU2AOG', null),
        ];

        $file = $this->service->adif($this->operator, $this->activityId, $this->operator->id, null);

        self::assertSame('DPS-05_LU2AOG_20261004.adi', $file['name']);
        self::assertStringContainsString('<CALL:5>CX2AB ', $file['content']);
        self::assertStringContainsString('<COMMENT:7>QRP 5 W ', $file['content']);
        self::assertLessThan(strpos($file['content'], 'LU1ABC/P'), strpos($file['content'], 'CX2AB'));
        self::assertSame('DPS-05_LU2AOG-A_20261004.adi', $this->service->adif($this->admin, $this->activityId, $this->operator->id, 'LU2AOG/A')['name']);
        $this->assertStatus(403, fn () => $this->service->adif($this->otherOperator, $this->activityId, $this->operator->id, null));
        $this->assertStatus(404, fn () => $this->service->adif($this->admin, $this->activityId, $this->operator->id, 'LU2AOG/M'));
    }

    public function testTheSuffixOfTheAdifFileGoesOnlyOnTheContactsWithoutASuffix(): void
    {
        $this->live->uploadedRecords[$this->activityId . ':' . $this->operator->id] = [
            new AdifRecord('CX2AB', '2026-10-04 14:00:00', '7.130', '40m', 'SSB', '59', '59', null, 'LU2AOG', 'LU2AOG', null),
            new AdifRecord('CX3CD', '2026-10-04 14:10:00', '7.130', '40m', 'SSB', '59', '59', null, 'LU2AOG/A', 'LU2AOG', null),
        ];

        $file = $this->service->adif($this->operator, $this->activityId, $this->operator->id, null, '/d');

        self::assertSame('DPS-05_LU2AOG-D_20261004.adi', $file['name']);
        self::assertSame(1, substr_count($file['content'], '<STATION_CALLSIGN:8>LU2AOG/D '));
        self::assertSame(1, substr_count($file['content'], '<STATION_CALLSIGN:8>LU2AOG/A '));
        self::assertSame(2, substr_count($file['content'], '<OPERATOR:6>LU2AOG '));
        $this->assertStatus(422, fn () => $this->service->adif($this->operator, $this->activityId, $this->operator->id, null, 'D-1'));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function save(string $clientId, array $values = [], ?int $baseVersion = null): array
    {
        return [
            'clientId' => $clientId,
            'op' => 'save',
            'baseVersion' => $baseVersion,
            'contact' => $values + [
                'operatorId' => $this->operator->id,
                'callSign' => 'lu1abc/p',
                'name' => 'Juana Pérez',
                'qsoAt' => '2026-10-04T14:29:00Z',
                'frequency' => '7.13',
                'mode' => 'SSB',
                'rstSent' => '59',
                'rstRcvd' => '59',
                'notes' => '',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $changes
     * @return array<string, mixed>
     */
    private function sync(array $changes): array
    {
        return $this->service->sync($this->operator, $this->activityId, $changes, null);
    }

    private function user(string $callSign, string $role): User
    {
        return $this->users->findById($this->users->create($callSign, $callSign, null, $role, 'hash', false));
    }

    private function assertStatus(int $status, callable $action): void
    {
        try {
            $action();
            self::fail('The action did not fail.');
        } catch (HttpException $e) {
            self::assertSame($status, $e->status, $e->getMessage());
        }
    }
}
