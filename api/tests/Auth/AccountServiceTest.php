<?php

declare(strict_types=1);

namespace DxFondito\Tests\Auth;

use DateTimeImmutable;
use DxFondito\Auth\AccountService;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Tests\Audit\MemoryAuditLog;
use PHPUnit\Framework\TestCase;

final class AccountServiceTest extends TestCase
{
    private MemoryUserStore $users;
    private MemoryAuditLog $audit;
    private AccountService $accounts;
    private int $adminId;
    private User $admin;

    protected function setUp(): void
    {
        $this->users = new MemoryUserStore();
        $this->audit = new MemoryAuditLog();
        $this->accounts = new AccountService($this->users, $this->audit);
        $this->adminId = $this->users->create('LU1ADM', 'Admin', null, User::ADMINISTRATOR, 'hash', false);
        $this->admin = $this->users->findById($this->adminId);
    }

    public function testCreatesAnAccountWithAnInitialPassword(): void
    {
        $user = $this->accounts->create($this->admin, ' lu2abc ', ' Ana ', 'ana@example.com', User::OPERATOR, 'initial password');

        self::assertSame('LU2ABC', $user->callSign);
        self::assertSame('Ana', $user->name);
        self::assertSame('ana@example.com', $user->email);
        self::assertTrue($user->mustChangePassword);
        self::assertTrue($user->active);
        self::assertTrue(password_verify('initial password', $user->passwordHash));
    }

    public function testTheEmailAddressIsOptional(): void
    {
        $user = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', '  ', User::OPERATOR, 'initial password');

        self::assertNull($user->email);
    }

    public function testRefusesACallSignThatHasAnAccount(): void
    {
        $this->assertStatus(409, fn () => $this->accounts->create($this->admin, 'lu1adm', 'Ana', null, User::OPERATOR, 'initial password'));
    }

    public function testRefusesIncorrectValues(): void
    {
        $this->assertStatus(422, fn () => $this->accounts->create($this->admin, 'L!', 'Ana', null, User::OPERATOR, 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create($this->admin, 'LU2ABC', ' ', null, User::OPERATOR, 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create($this->admin, 'LU2ABC', 'Ana', 'not an address', User::OPERATOR, 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, 'guest', 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'short'));
    }

    public function testChangesTheNameTheRoleAndTheEmailAddress(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        $user = $this->accounts->update($this->admin, $id, 'Ana María', 'ana@example.com', User::ADMINISTRATOR, true);

        self::assertSame('Ana María', $user->name);
        self::assertSame('ana@example.com', $user->email);
        self::assertTrue($user->isAdministrator());
    }

    public function testDeactivatesAnAccountAndActivatesItAgain(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        self::assertFalse($this->accounts->update($this->admin, $id, 'Ana', null, User::OPERATOR, false)->active);
        self::assertTrue($this->accounts->update($this->admin, $id, 'Ana', null, User::OPERATOR, true)->active);
    }

    public function testKeepsOneActiveAdministrator(): void
    {
        $this->assertStatus(409, fn () => $this->accounts->update($this->admin, $this->adminId, 'Admin', null, User::ADMINISTRATOR, false));
        $this->assertStatus(409, fn () => $this->accounts->update($this->admin, $this->adminId, 'Admin', null, User::OPERATOR, true));
    }

    public function testDeactivatesAnAdministratorWhenAnotherIsActive(): void
    {
        $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::ADMINISTRATOR, 'initial password');

        $user = $this->accounts->update($this->admin, $this->adminId, 'Admin', null, User::ADMINISTRATOR, false);

        self::assertFalse($user->active);
    }

    public function testADeactivatedAdministratorDoesNotCount(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::ADMINISTRATOR, 'initial password')->id;
        $this->accounts->update($this->admin, $id, 'Ana', null, User::ADMINISTRATOR, false);

        $this->assertStatus(409, fn () => $this->accounts->update($this->admin, $this->adminId, 'Admin', null, User::OPERATOR, true));
    }

    public function testANewInitialPasswordOpensALockedAccount(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;
        $this->users->setPassword($id, 'hash', false);
        $this->users->recordFailedAttempts($id, 3, new DateTimeImmutable('+10 minutes'));

        $user = $this->accounts->setInitialPassword($this->admin, $id, 'other password');

        self::assertTrue($user->mustChangePassword);
        self::assertTrue(password_verify('other password', $user->passwordHash));
        self::assertSame(0, $user->failedAttempts);
        self::assertNull($user->lockedUntil);
    }

    public function testRefusesAnUnknownAccount(): void
    {
        $this->assertStatus(404, fn () => $this->accounts->update($this->admin, 99, 'Ana', null, User::OPERATOR, true));
        $this->assertStatus(404, fn () => $this->accounts->setInitialPassword($this->admin, 99, 'other password'));
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

    public function testRecordsTheCreationWithoutThePassword(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        self::assertSame([[
            'userId' => $this->adminId,
            'action' => 'user.create',
            'entityId' => $id,
            'detail' => ['callSign' => 'LU2ABC', 'name' => 'Ana', 'email' => null, 'role' => User::OPERATOR],
        ]], $this->audit->entries);
    }

    public function testRecordsOnlyTheValuesThatChanged(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        $this->accounts->update($this->admin, $id, 'Ana', null, User::OPERATOR, false);

        self::assertSame(
            ['callSign' => 'LU2ABC', 'changes' => ['active' => [true, false]]],
            $this->audit->entries[1]['detail'],
        );
    }

    public function testRecordsNothingWithoutAChange(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        $this->accounts->update($this->admin, $id, ' Ana ', '', User::OPERATOR, true);

        self::assertSame(['user.create'], $this->audit->actions());
    }

    public function testRecordsANewInitialPassword(): void
    {
        $id = $this->accounts->create($this->admin, 'LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        $this->accounts->setInitialPassword($this->admin, $id, 'other password');

        self::assertSame(['user.create', 'user.password.reset'], $this->audit->actions());
        self::assertSame(['callSign' => 'LU2ABC'], $this->audit->entries[1]['detail']);
    }

    public function testRecordsNothingForARefusedChange(): void
    {
        $this->expectException(HttpException::class);
        try {
            $this->accounts->update($this->admin, $this->adminId, 'Admin', null, User::OPERATOR, true);
        } finally {
            self::assertSame([], $this->audit->entries);
        }
    }
}
