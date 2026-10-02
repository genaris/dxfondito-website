<?php

declare(strict_types=1);

namespace DxFondito\Auth;

use Closure;
use DateTimeImmutable;
use DxFondito\Audit\AuditLog;
use DxFondito\CallSign;
use DxFondito\Http\HttpException;
use DxFondito\Http\Request;

/**
 * Sign-in, sign-out and the checks of the session (FR-AUT-1 to FR-AUT-6).
 */
final class Authenticator
{
    public const MAX_FAILED_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;
    public const IDLE_SECONDS = 2 * 3600;
    public const TOKEN_HEADER = 'X-CSRF-Token';

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    private ?User $current = null;
    private bool $loaded = false;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly UserStore $users,
        private readonly Session $session,
        private readonly AuditLog $audit,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * @throws HttpException 401 for an incorrect call sign or password, 423 for a locked account,
     *   403 for a deactivated account.
     */
    public function signIn(string $callSign, string $password): User
    {
        $now = ($this->now)();
        $user = $this->users->findByCallSign(CallSign::normalize($callSign));

        if ($user === null) {
            // The same work as for a known call sign. Thus the time of the response does not show
            // which call signs have an account.
            password_verify($password, self::dummyHash());
            throw new HttpException(401, 'Incorrect call sign or password');
        }

        if ($user->lockedUntil !== null && $user->lockedUntil > $now) {
            throw new HttpException(423, 'The account is locked');
        }

        if (!password_verify($password, $user->passwordHash)) {
            $attempts = $user->failedAttempts + 1;
            if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
                $this->users->recordFailedAttempts($user->id, 0, $now->modify('+' . self::LOCK_MINUTES . ' minutes'));
                throw new HttpException(423, 'The account is locked');
            }
            $this->users->recordFailedAttempts($user->id, $attempts, null);
            throw new HttpException(401, 'Incorrect call sign or password');
        }

        if (!$user->active) {
            throw new HttpException(403, 'The account is deactivated');
        }

        if ($user->failedAttempts !== 0 || $user->lockedUntil !== null) {
            $this->users->recordFailedAttempts($user->id, 0, null);
        }
        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)) {
            $this->users->setPassword($user->id, PasswordRules::hash($password), $user->mustChangePassword);
        }

        // A new identifier at each sign-in. Thus an identifier from before the sign-in is of no use.
        $this->session->regenerate();
        $this->session->set('user_id', $user->id);
        $this->session->set('token', bin2hex(random_bytes(32)));
        $this->session->set('last_activity', $now->getTimestamp());

        $this->current = $user;
        $this->loaded = true;

        return $user;
    }

    public function signOut(): void
    {
        $this->session->destroy();
        $this->current = null;
        $this->loaded = true;
    }

    /**
     * The signed-in user, or null. A session ends after two hours without activity,
     * and when an administrator deactivates the account.
     */
    public function current(): ?User
    {
        if ($this->loaded) {
            return $this->current;
        }
        $this->loaded = true;

        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return null;
        }

        $now = ($this->now)()->getTimestamp();
        $lastActivity = $this->session->get('last_activity');
        if (!is_int($lastActivity) || $now - $lastActivity > self::IDLE_SECONDS) {
            $this->session->destroy();

            return null;
        }

        $user = $this->users->findById($userId);
        if ($user === null || !$user->active) {
            $this->session->destroy();

            return null;
        }

        $this->session->set('last_activity', $now);
        $this->current = $user;

        return $user;
    }

    public function token(): ?string
    {
        if ($this->current() === null) {
            return null;
        }
        $token = $this->session->get('token');

        return is_string($token) ? $token : null;
    }

    /**
     * Checks that a user is signed in. A request that changes data must also have the token of the session.
     *
     * @param bool $allowInitialPassword True for the requests that a user with an initial password can use.
     * @throws HttpException 401 without a session, 403 for an incorrect token or an initial password.
     */
    public function requireUser(Request $request, bool $allowInitialPassword = false): User
    {
        $user = $this->current();
        if ($user === null) {
            throw new HttpException(401, 'Sign-in is necessary');
        }

        if ($request->method !== 'GET') {
            $token = $this->token();
            $sent = $request->header(self::TOKEN_HEADER);
            if ($token === null || $sent === null || !hash_equals($token, $sent)) {
                throw new HttpException(403, 'The request token is not correct');
            }
        }

        if ($user->mustChangePassword && !$allowInitialPassword) {
            throw new HttpException(403, 'A new password is necessary');
        }

        return $user;
    }

    /**
     * @throws HttpException 403 if the user is not an administrator.
     */
    public function requireAdministrator(Request $request): User
    {
        $user = $this->requireUser($request);
        if (!$user->isAdministrator()) {
            throw new HttpException(403, 'Only an administrator can do this');
        }

        return $user;
    }

    /**
     * FR-AUT-3 and FR-AUT-4.
     *
     * @throws HttpException 403 for an incorrect current password, 422 for an unacceptable new password.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!password_verify($currentPassword, $user->passwordHash)) {
            throw new HttpException(403, 'The current password is not correct');
        }
        PasswordRules::check($newPassword);
        if ($newPassword === $currentPassword) {
            throw new HttpException(422, 'The new password must be different');
        }

        $this->users->setPassword($user->id, PasswordRules::hash($newPassword), false);
        $this->audit->record($user->id, AuditLog::USER_PASSWORD_CHANGE, $user->id, ['callSign' => $user->callSign]);
        $this->session->regenerate();
        $this->current = $this->users->findById($user->id);
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= PasswordRules::hash('dummy password');
    }
}
