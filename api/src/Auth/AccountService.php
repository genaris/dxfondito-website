<?php

declare(strict_types=1);

namespace DxFondito\Auth;

use DxFondito\CallSign;
use DxFondito\Http\HttpException;

/**
 * The administration of the accounts (FR-USR-1 to FR-USR-8).
 * The system does not delete accounts. An administrator deactivates them (FR-USR-5, FR-USR-7).
 */
final class AccountService
{
    public function __construct(private readonly UserStore $users)
    {
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        return $this->users->all();
    }

    /**
     * FR-USR-1 and FR-USR-2. The user must change the initial password at the first sign-in.
     *
     * @throws HttpException 422 for an incorrect value, 409 if the call sign has an account.
     */
    public function create(string $callSign, string $name, ?string $email, string $role, string $initialPassword): User
    {
        $callSign = CallSign::normalize($callSign);
        if (!CallSign::isValid($callSign)) {
            throw new HttpException(422, 'The call sign must have 3 to 20 letters, digits or / characters');
        }
        $name = self::checkName($name);
        $email = self::checkEmail($email);
        self::checkRole($role);
        PasswordRules::check($initialPassword);

        if ($this->users->findByCallSign($callSign) !== null) {
            throw new HttpException(409, 'The call sign has an account');
        }

        $id = $this->users->create($callSign, $name, $email, $role, PasswordRules::hash($initialPassword), true);

        return $this->find($id);
    }

    /**
     * FR-USR-3 and FR-USR-5. The call sign does not change: it identifies the account and its logs.
     *
     * @throws HttpException 404, 422, or 409 if no active administrator would remain (FR-USR-8).
     */
    public function update(int $id, string $name, ?string $email, string $role, bool $active): User
    {
        $user = $this->find($id);
        $name = self::checkName($name);
        $email = self::checkEmail($email);
        self::checkRole($role);

        $removesAdministrator = $user->isAdministrator() && $user->active
            && ($role !== User::ADMINISTRATOR || !$active);
        if ($removesAdministrator && $this->users->countActiveAdministrators() <= 1) {
            throw new HttpException(409, 'The system must keep one or more active administrators');
        }

        $this->users->update($id, $name, $email, $role, $active);

        return $this->find($id);
    }

    /**
     * FR-USR-4. The new initial password also opens a locked account.
     *
     * @throws HttpException 404 or 422.
     */
    public function setInitialPassword(int $id, string $initialPassword): User
    {
        $user = $this->find($id);
        PasswordRules::check($initialPassword);

        $this->users->setPassword($user->id, PasswordRules::hash($initialPassword), true);
        $this->users->recordFailedAttempts($user->id, 0, null);

        return $this->find($id);
    }

    private function find(int $id): User
    {
        return $this->users->findById($id) ?? throw new HttpException(404, 'The account does not exist');
    }

    private static function checkName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new HttpException(422, 'The name must have 1 to 100 characters');
        }

        return $name;
    }

    private static function checkEmail(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'The email address is not correct');
        }

        return $email;
    }

    private static function checkRole(string $role): void
    {
        if ($role !== User::OPERATOR && $role !== User::ADMINISTRATOR) {
            throw new HttpException(422, 'The role must be operator or administrator');
        }
    }
}
