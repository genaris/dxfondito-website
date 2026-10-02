<?php

declare(strict_types=1);

namespace DxFondito\Tests\Auth;

use DateTimeImmutable;
use DxFondito\Auth\Authenticator;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\Request;
use PHPUnit\Framework\TestCase;

final class AuthenticatorTest extends TestCase
{
    private const PASSWORD = 'correct horse';

    private MemoryUserStore $users;
    private MemorySession $session;
    private DateTimeImmutable $now;
    private int $userId;

    protected function setUp(): void
    {
        $this->users = new MemoryUserStore();
        $this->session = new MemorySession();
        $this->now = new DateTimeImmutable('2026-10-01 12:00:00');
        // A low cost keeps the tests fast.
        $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->userId = $this->users->create('LU1ABC', 'Ana', null, User::OPERATOR, $hash, false);
    }

    public function testSignsInWithTheCallSignAndThePassword(): void
    {
        $user = $this->auth()->signIn('LU1ABC', self::PASSWORD);

        self::assertSame($this->userId, $user->id);
        self::assertSame($this->userId, $this->session->get('user_id'));
        self::assertSame(1, $this->session->regenerations);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $this->session->get('token'));
    }

    public function testAcceptsTheCallSignInLowercaseLettersAndWithSpaces(): void
    {
        $user = $this->auth()->signIn(' lu1 abc ', self::PASSWORD);

        self::assertSame($this->userId, $user->id);
    }

    public function testRefusesAnIncorrectPassword(): void
    {
        $this->assertStatus(401, fn () => $this->auth()->signIn('LU1ABC', 'incorrect'));

        self::assertSame(1, $this->users->findById($this->userId)->failedAttempts);
        self::assertNull($this->session->get('user_id'));
    }

    public function testRefusesAnUnknownCallSign(): void
    {
        $this->assertStatus(401, fn () => $this->auth()->signIn('LU9ZZZ', self::PASSWORD));
    }

    public function testLocksTheAccountAfterFiveIncorrectPasswords(): void
    {
        for ($i = 1; $i < Authenticator::MAX_FAILED_ATTEMPTS; $i++) {
            $this->assertStatus(401, fn () => $this->auth()->signIn('LU1ABC', 'incorrect'));
        }
        $this->assertStatus(423, fn () => $this->auth()->signIn('LU1ABC', 'incorrect'));

        // The correct password does not open a locked account.
        $this->assertStatus(423, fn () => $this->auth()->signIn('LU1ABC', self::PASSWORD));
    }

    public function testOpensTheAccountAfterFifteenMinutes(): void
    {
        for ($i = 0; $i < Authenticator::MAX_FAILED_ATTEMPTS; $i++) {
            $this->expectFailure(fn () => $this->auth()->signIn('LU1ABC', 'incorrect'));
        }

        $this->now = $this->now->modify('+15 minutes +1 second');
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $user = $this->users->findById($this->userId);
        self::assertSame(0, $user->failedAttempts);
        self::assertNull($user->lockedUntil);
    }

    public function testACorrectPasswordSetsTheCountOfIncorrectPasswordsToZero(): void
    {
        $this->expectFailure(fn () => $this->auth()->signIn('LU1ABC', 'incorrect'));

        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        self::assertSame(0, $this->users->findById($this->userId)->failedAttempts);
    }

    public function testRefusesADeactivatedAccount(): void
    {
        $this->users->deactivate($this->userId);

        $this->assertStatus(403, fn () => $this->auth()->signIn('LU1ABC', self::PASSWORD));
    }

    public function testGivesTheSignedInUser(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        self::assertSame($this->userId, $this->auth()->current()?->id);
    }

    public function testEndsTheSessionAfterTwoHoursWithoutActivity(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $this->now = $this->now->modify('+2 hours +1 second');

        self::assertNull($this->auth()->current());
        self::assertTrue($this->session->destroyed);
    }

    public function testEachRequestExtendsTheSession(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $this->now = $this->now->modify('+90 minutes');
        self::assertNotNull($this->auth()->current());
        $this->now = $this->now->modify('+90 minutes');

        self::assertNotNull($this->auth()->current());
    }

    public function testEndsTheSessionOfADeactivatedAccount(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $this->users->deactivate($this->userId);

        self::assertNull($this->auth()->current());
    }

    public function testSignOutRemovesTheSession(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $this->auth()->signOut();

        self::assertTrue($this->session->destroyed);
        self::assertNull($this->auth()->current());
    }

    public function testARequestThatChangesDataMustHaveTheToken(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);
        $token = (string) $this->session->get('token');

        $user = $this->auth()->requireUser($this->request('POST', $token));

        self::assertSame($this->userId, $user->id);
        $this->assertStatus(403, fn () => $this->auth()->requireUser($this->request('POST', null)));
        $this->assertStatus(403, fn () => $this->auth()->requireUser($this->request('POST', 'incorrect')));
    }

    public function testARequestThatReadsDataNeedsNoToken(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $user = $this->auth()->requireUser($this->request('GET', null));

        self::assertSame($this->userId, $user->id);
    }

    public function testRefusesARequestWithoutASession(): void
    {
        $this->assertStatus(401, fn () => $this->auth()->requireUser($this->request('GET', null)));
    }

    public function testAUserWithAnInitialPasswordCanOnlyChangeIt(): void
    {
        $this->users->setPassword($this->userId, $this->users->findById($this->userId)->passwordHash, true);
        $this->auth()->signIn('LU1ABC', self::PASSWORD);
        $request = $this->request('PUT', (string) $this->session->get('token'));

        $this->assertStatus(403, fn () => $this->auth()->requireUser($request));
        self::assertSame($this->userId, $this->auth()->requireUser($request, allowInitialPassword: true)->id);
    }

    public function testOnlyAnAdministratorPassesTheAdministratorCheck(): void
    {
        $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $this->assertStatus(403, fn () => $this->auth()->requireAdministrator($this->request('GET', null)));
    }

    public function testChangesThePassword(): void
    {
        $this->users->setPassword($this->userId, $this->users->findById($this->userId)->passwordHash, true);
        $auth = $this->auth();
        $user = $auth->signIn('LU1ABC', self::PASSWORD);

        $auth->changePassword($user, self::PASSWORD, 'a new password');

        $changed = $this->users->findById($this->userId);
        self::assertTrue(password_verify('a new password', $changed->passwordHash));
        self::assertFalse($changed->mustChangePassword);
        self::assertFalse($auth->current()?->mustChangePassword);
        self::assertSame(2, $this->session->regenerations);
    }

    public function testThePasswordChangeNeedsTheCurrentPassword(): void
    {
        $user = $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $this->assertStatus(403, fn () => $this->auth()->changePassword($user, 'incorrect', 'a new password'));
    }

    public function testRefusesAShortOrUnchangedNewPassword(): void
    {
        $user = $this->auth()->signIn('LU1ABC', self::PASSWORD);

        $this->assertStatus(422, fn () => $this->auth()->changePassword($user, self::PASSWORD, 'short'));
        $this->assertStatus(422, fn () => $this->auth()->changePassword($user, self::PASSWORD, self::PASSWORD));
    }

    private function auth(): Authenticator
    {
        return new Authenticator($this->users, $this->session, fn (): DateTimeImmutable => $this->now);
    }

    private function request(string $method, ?string $token): Request
    {
        $headers = $token === null ? [] : [Authenticator::TOKEN_HEADER => $token];

        return new Request($method, '/test', headers: $headers);
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

    private function expectFailure(callable $action): void
    {
        try {
            $action();
        } catch (HttpException) {
        }
    }
}
