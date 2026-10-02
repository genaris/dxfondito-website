<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DateTimeImmutable;
use DxFondito\Auth\AccountService;
use DxFondito\Auth\Authenticator;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\Request;
use DxFondito\Http\Response;

/**
 * The account requests of section 8.4 of the system design. Only an administrator can use them.
 */
final class UserController
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly AccountService $accounts,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->auth->requireAdministrator($request);
        $now = new DateTimeImmutable();

        return Response::json(array_map(
            static fn (User $user): array => $user->administrationData($now),
            $this->accounts->all(),
        ));
    }

    public function create(Request $request): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $user = $this->accounts->create(
            $actor,
            $request->string('callSign'),
            $request->string('name'),
            $request->string('email'),
            $request->string('role'),
            $request->string('password'),
        );

        return $this->one($user, 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $active = $request->body['active'] ?? null;
        if (!is_bool($active)) {
            throw new HttpException(422, 'The value of active must be true or false');
        }
        $user = $this->accounts->update(
            $actor,
            self::id($params),
            $request->string('name'),
            $request->string('email'),
            $request->string('role'),
            $active,
        );

        return $this->one($user);
    }

    /**
     * @param array<string, string> $params
     */
    public function setPassword(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $user = $this->accounts->setInitialPassword($actor, self::id($params), $request->string('password'));

        return $this->one($user);
    }

    private function one(User $user, int $status = 200): Response
    {
        return Response::json($user->administrationData(new DateTimeImmutable()), $status);
    }

    /**
     * @param array<string, string> $params
     */
    private static function id(array $params): int
    {
        $id = $params['id'] ?? '';
        if (preg_match('/^[1-9][0-9]{0,9}$/', $id) !== 1) {
            throw new HttpException(404, 'The account does not exist');
        }

        return (int) $id;
    }
}
