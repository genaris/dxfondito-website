<?php

declare(strict_types=1);

namespace DxFondito\Tests\Auth;

use DateTimeImmutable;
use DxFondito\Auth\AccountService;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use PHPUnit\Framework\TestCase;

final class AccountServiceTest extends TestCase
{
    private MemoryUserStore $users;
    private AccountService $accounts;
    private int $adminId;

    protected function setUp(): void
    {
        $this->users = new MemoryUserStore();
        $this->accounts = new AccountService($this->users);
        $this->adminId = $this->users->create('LU1ADM', 'Admin', null, User::ADMINISTRATOR, 'hash', false);
    }

    public function testCreatesAnAccountWithAnInitialPassword(): void
    {
        $user = $this->accounts->create(' lu2abc ', ' Ana ', 'ana@example.com', User::OPERATOR, 'initial password');

        self::assertSame('LU2ABC', $user->callSign);
        self::assertSame('Ana', $user->name);
        self::assertSame('ana@example.com', $user->email);
        self::assertTrue($user->mustChangePassword);
        self::assertTrue($user->active);
        self::assertTrue(password_verify('initial password', $user->passwordHash));
    }

    public function testTheEmailAddressIsOptional(): void
    {
        $user = $this->accounts->create('LU2ABC', 'Ana', '  ', User::OPERATOR, 'initial password');

        self::assertNull($user->email);
    }

    public function testRefusesACallSignThatHasAnAccount(): void
    {
        $this->assertStatus(409, fn () => $this->accounts->create('lu1adm', 'Ana', null, User::OPERATOR, 'initial password'));
    }

    public function testRefusesIncorrectValues(): void
    {
        $this->assertStatus(422, fn () => $this->accounts->create('L!', 'Ana', null, User::OPERATOR, 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create('LU2ABC', ' ', null, User::OPERATOR, 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create('LU2ABC', 'Ana', 'not an address', User::OPERATOR, 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create('LU2ABC', 'Ana', null, 'guest', 'initial password'));
        $this->assertStatus(422, fn () => $this->accounts->create('LU2ABC', 'Ana', null, User::OPERATOR, 'short'));
    }

    public function testChangesTheNameTheRoleAndTheEmailAddress(): void
    {
        $id = $this->accounts->create('LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        $user = $this->accounts->update($id, 'Ana María', 'ana@example.com', User::ADMINISTRATOR, true);

        self::assertSame('Ana María', $user->name);
        self::assertSame('ana@example.com', $user->email);
        self::assertTrue($user->isAdministrator());
    }

    public function testDeactivatesAnAccountAndActivatesItAgain(): void
    {
        $id = $this->accounts->create('LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;

        self::assertFalse($this->accounts->update($id, 'Ana', null, User::OPERATOR, false)->active);
        self::assertTrue($this->accounts->update($id, 'Ana', null, User::OPERATOR, true)->active);
    }

    public function testKeepsOneActiveAdministrator(): void
    {
        $this->assertStatus(409, fn () => $this->accounts->update($this->adminId, 'Admin', null, User::ADMINISTRATOR, false));
        $this->assertStatus(409, fn () => $this->accounts->update($this->adminId, 'Admin', null, User::OPERATOR, true));
    }

    public function testDeactivatesAnAdministratorWhenAnotherIsActive(): void
    {
        $this->accounts->create('LU2ABC', 'Ana', null, User::ADMINISTRATOR, 'initial password');

        $user = $this->accounts->update($this->adminId, 'Admin', null, User::ADMINISTRATOR, false);

        self::assertFalse($user->active);
    }

    public function testADeactivatedAdministratorDoesNotCount(): void
    {
        $id = $this->accounts->create('LU2ABC', 'Ana', null, User::ADMINISTRATOR, 'initial password')->id;
        $this->accounts->update($id, 'Ana', null, User::ADMINISTRATOR, false);

        $this->assertStatus(409, fn () => $this->accounts->update($this->adminId, 'Admin', null, User::OPERATOR, true));
    }

    public function testANewInitialPasswordOpensALockedAccount(): void
    {
        $id = $this->accounts->create('LU2ABC', 'Ana', null, User::OPERATOR, 'initial password')->id;
        $this->users->setPassword($id, 'hash', false);
        $this->users->recordFailedAttempts($id, 3, new DateTimeImmutable('+10 minutes'));

        $user = $this->accounts->setInitialPassword($id, 'other password');

        self::assertTrue($user->mustChangePassword);
        self::assertTrue(password_verify('other password', $user->passwordHash));
        self::assertSame(0, $user->failedAttempts);
        self::assertNull($user->lockedUntil);
    }

    public function testRefusesAnUnknownAccount(): void
    {
        $this->assertStatus(404, fn () => $this->accounts->update(99, 'Ana', null, User::OPERATOR, true));
        $this->assertStatus(404, fn () => $this->accounts->setInitialPassword(99, 'other password'));
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
