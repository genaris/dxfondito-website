<?php

declare(strict_types=1);

namespace DxFondito\Tests\Activities;

use DxFondito\Activities\Reference;
use DxFondito\Activities\ReferenceService;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Tests\Audit\MemoryAuditLog;
use PHPUnit\Framework\TestCase;

final class ReferenceServiceTest extends TestCase
{
    private const DPS = 1;
    private const EFE = 2;

    private MemoryReferenceStore $store;
    private MemoryAuditLog $audit;
    private ReferenceService $references;
    private User $admin;

    protected function setUp(): void
    {
        $this->store = new MemoryReferenceStore();
        $this->audit = new MemoryAuditLog();
        $this->references = new ReferenceService($this->store, $this->audit);
        $this->admin = new User(1, 'LU1ADM', 'Admin', null, User::ADMINISTRATOR, 'hash', false, true, 0, null);
    }

    public function testTheCodeHasTwoOrMoreDigits(): void
    {
        self::assertSame('DPS-01', Reference::code('DPS', 1));
        self::assertSame('EFE-12', Reference::code('EFE', 12));
        self::assertSame('DPS-123', Reference::code('DPS', 123));
    }

    public function testCreatesAReference(): void
    {
        $reference = $this->references->create($this->admin, self::DPS, 3, ' Hospital ', ' A description ');

        self::assertSame('DPS-03', $reference->referenceCode());
        self::assertSame('Hospital', $reference->name);
        self::assertSame('A description', $reference->description);
        self::assertSame(['reference.create'], $this->audit->actions());
    }

    public function testTheDescriptionIsOptional(): void
    {
        self::assertNull($this->references->create($this->admin, self::DPS, 1, 'Hospital', '  ')->description);
    }

    public function testProposesTheNextNumberOfTheSeries(): void
    {
        self::assertSame(1, $this->references->nextNumber(self::DPS));

        $this->references->create($this->admin, self::DPS, 1, 'A', null);
        $this->references->create($this->admin, self::DPS, 5, 'B', null);

        self::assertSame(6, $this->references->nextNumber(self::DPS));
        self::assertSame(1, $this->references->nextNumber(self::EFE));
    }

    public function testUsesTheNextNumberWithoutANumber(): void
    {
        $this->references->create($this->admin, self::EFE, 7, 'A', null);

        self::assertSame(8, $this->references->create($this->admin, self::EFE, null, 'B', null)->number);
    }

    public function testRefusesACodeThatExists(): void
    {
        $this->references->create($this->admin, self::DPS, 1, 'A', null);

        $this->assertStatus(409, fn () => $this->references->create($this->admin, self::DPS, 1, 'B', null));
        // The same number in a different series is a different code.
        self::assertSame('EFE-01', $this->references->create($this->admin, self::EFE, 1, 'C', null)->referenceCode());
    }

    public function testRefusesIncorrectValues(): void
    {
        $this->assertStatus(422, fn () => $this->references->create($this->admin, 9, 1, 'A', null));
        $this->assertStatus(422, fn () => $this->references->create($this->admin, self::DPS, 0, 'A', null));
        $this->assertStatus(422, fn () => $this->references->create($this->admin, self::DPS, 1000, 'A', null));
        $this->assertStatus(422, fn () => $this->references->create($this->admin, self::DPS, 1, ' ', null));
        $this->assertStatus(422, fn () => $this->references->create($this->admin, self::DPS, 1, 'A', str_repeat('x', 2001)));
    }

    public function testChangesAReferenceAndRecordsTheChanges(): void
    {
        $id = $this->references->create($this->admin, self::DPS, 1, 'Hospital', null)->id;

        $reference = $this->references->update($this->admin, $id, self::DPS, 2, 'Hospital Central', null);

        self::assertSame('DPS-02', $reference->referenceCode());
        self::assertSame(
            ['code' => 'DPS-01', 'changes' => ['code' => ['DPS-01', 'DPS-02'], 'name' => ['Hospital', 'Hospital Central']]],
            $this->audit->entries[1]['detail'],
        );
    }

    public function testAReferenceKeepsItsOwnCode(): void
    {
        $id = $this->references->create($this->admin, self::DPS, 1, 'Hospital', null)->id;

        $reference = $this->references->update($this->admin, $id, self::DPS, 1, 'Hospital', 'New description');

        self::assertSame('New description', $reference->description);
    }

    public function testRefusesAChangeToACodeThatExists(): void
    {
        $this->references->create($this->admin, self::DPS, 1, 'A', null);
        $id = $this->references->create($this->admin, self::DPS, 2, 'B', null)->id;

        $this->assertStatus(409, fn () => $this->references->update($this->admin, $id, self::DPS, 1, 'B', null));
    }

    public function testDeletesAReferenceWithoutActivities(): void
    {
        $id = $this->references->create($this->admin, self::DPS, 1, 'A', null)->id;

        $this->references->delete($this->admin, $id);

        self::assertNull($this->store->find($id));
        self::assertSame(['reference.create', 'reference.delete'], $this->audit->actions());
    }

    public function testRefusesToDeleteAReferenceWithActivities(): void
    {
        $id = $this->references->create($this->admin, self::DPS, 1, 'A', null)->id;
        $this->store->withActivities[$id] = true;

        $this->assertStatus(409, fn () => $this->references->delete($this->admin, $id));
        self::assertNotNull($this->store->find($id));
    }

    public function testRefusesAnUnknownReference(): void
    {
        $this->assertStatus(404, fn () => $this->references->update($this->admin, 99, self::DPS, 1, 'A', null));
        $this->assertStatus(404, fn () => $this->references->delete($this->admin, 99));
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
