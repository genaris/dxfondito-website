<?php

declare(strict_types=1);

namespace DxFondito\Tests\Activities;

use DxFondito\Activities\ActivityService;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Tests\Audit\MemoryAuditLog;
use PHPUnit\Framework\TestCase;

final class ActivityServiceTest extends TestCase
{
    private MemoryReferenceStore $references;
    private MemoryActivityStore $store;
    private MemoryAuditLog $audit;
    private ActivityService $activities;
    private User $admin;
    private int $dps01;
    private int $dps02;

    protected function setUp(): void
    {
        $this->references = new MemoryReferenceStore();
        $this->store = new MemoryActivityStore($this->references);
        $this->audit = new MemoryAuditLog();
        $this->activities = new ActivityService($this->store, $this->references, $this->audit);
        $this->admin = new User(1, 'LU1ADM', 'Admin', null, User::ADMINISTRATOR, 'hash', false, true, 0, null);
        $this->dps01 = $this->references->create(1, 1, 'Hospital', null);
        $this->dps02 = $this->references->create(1, 2, 'Sala', null);
    }

    public function testTheSeasonIsTheYearOfTheStartDate(): void
    {
        self::assertSame(2026, ActivityService::seasonOf('2026-12-31'));
    }

    public function testCreatesAnActivity(): void
    {
        $activity = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-11', ' Note ');

        self::assertSame(2026, $activity->season);
        self::assertSame('DPS-01 (2026-05-10)', $activity->label());
        self::assertSame('Note', $activity->description);
        self::assertSame(['activity.create'], $this->audit->actions());
    }

    public function testTheSeasonComesFromTheStartDateOnly(): void
    {
        $activity = $this->activities->create($this->admin, $this->dps01, '2026-12-31', '2027-01-01', null);

        self::assertSame(2026, $activity->season);
    }

    public function testAcceptsAnActivityOfOneDay(): void
    {
        $activity = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null);

        self::assertSame('2026-05-10', $activity->endDate);
    }

    public function testAcceptsTwoActivitiesOfAReferenceInOneSeason(): void
    {
        $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null);
        $this->activities->create($this->admin, $this->dps01, '2026-08-01', '2026-08-02', null);

        self::assertCount(2, $this->store->bySeason(2026));
    }

    public function testRefusesTwoActivitiesWithTheSameReferenceAndStartDate(): void
    {
        $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null);

        $this->assertStatus(409, fn () => $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-12', null));
        // A different reference can start on the same date.
        $this->activities->create($this->admin, $this->dps02, '2026-05-10', '2026-05-10', null);
    }

    public function testRefusesIncorrectDates(): void
    {
        $this->assertStatus(422, fn () => $this->activities->create($this->admin, $this->dps01, '2026-02-30', '2026-03-01', null));
        $this->assertStatus(422, fn () => $this->activities->create($this->admin, $this->dps01, '10/05/2026', '2026-05-10', null));
        $this->assertStatus(422, fn () => $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-09', null));
        $this->assertStatus(422, fn () => $this->activities->create($this->admin, $this->dps01, '', '', null));
    }

    public function testRefusesAnUnknownReference(): void
    {
        $this->assertStatus(422, fn () => $this->activities->create($this->admin, 99, '2026-05-10', '2026-05-10', null));
    }

    public function testChangesAnActivityAndItsSeason(): void
    {
        $id = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null)->id;

        $activity = $this->activities->update($this->admin, $id, $this->dps02, '2027-01-05', '2027-01-06', null);

        self::assertSame(2027, $activity->season);
        self::assertSame('DPS-02 (2027-01-05)', $activity->label());
        self::assertSame(
            [
                'label' => 'DPS-01 (2026-05-10)',
                'changes' => [
                    'reference' => ['DPS-01', 'DPS-02'],
                    'startDate' => ['2026-05-10', '2027-01-05'],
                    'endDate' => ['2026-05-10', '2027-01-06'],
                ],
            ],
            $this->audit->entries[1]['detail'],
        );
    }

    public function testAnActivityKeepsItsOwnStartDate(): void
    {
        $id = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null)->id;

        $activity = $this->activities->update($this->admin, $id, $this->dps01, '2026-05-10', '2026-05-11', null);

        self::assertSame('2026-05-11', $activity->endDate);
    }

    public function testRecordsNothingWithoutAChange(): void
    {
        $id = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null)->id;

        $this->activities->update($this->admin, $id, $this->dps01, '2026-05-10', '2026-05-10', '');

        self::assertSame(['activity.create'], $this->audit->actions());
    }

    public function testDeletesAnActivityWithoutLogs(): void
    {
        $id = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null)->id;

        $this->activities->delete($this->admin, $id);

        self::assertNull($this->store->find($id));
        self::assertSame(['label' => 'DPS-01 (2026-05-10)', 'name' => 'Hospital'], $this->audit->entries[1]['detail']);
    }

    public function testRefusesToDeleteAnActivityWithLogs(): void
    {
        $id = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null)->id;
        $this->store->withLogs[$id] = true;

        $this->assertStatus(409, fn () => $this->activities->delete($this->admin, $id));
    }

    public function testRefusesToDeleteAnActivityWithQslCardTemplates(): void
    {
        $id = $this->activities->create($this->admin, $this->dps01, '2026-05-10', '2026-05-10', null)->id;
        $this->store->withTemplates[$id] = true;

        $this->assertStatus(409, fn () => $this->activities->delete($this->admin, $id));
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
