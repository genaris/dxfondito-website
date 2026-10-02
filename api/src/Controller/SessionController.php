<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Auth\Authenticator;
use DxFondito\Http\Request;
use DxFondito\Http\Response;

/**
 * The session requests of section 8.2 of the system design.
 */
final class SessionController
{
    public function __construct(private readonly Authenticator $auth)
    {
    }

    public function show(): Response
    {
        return $this->state();
    }

    public function signIn(Request $request): Response
    {
        $this->auth->signIn($request->string('callSign'), $request->string('password'));

        return $this->state();
    }

    public function signOut(): Response
    {
        $this->auth->signOut();

        return $this->state();
    }

    public function changePassword(Request $request): Response
    {
        $user = $this->auth->requireUser($request, allowInitialPassword: true);
        $this->auth->changePassword($user, $request->string('currentPassword'), $request->string('newPassword'));

        return $this->state();
    }

    /**
     * The signed-in user and the token for the requests that change data.
     */
    private function state(): Response
    {
        $user = $this->auth->current();

        return Response::json([
            'user' => $user?->publicData(),
            'token' => $this->auth->token(),
        ]);
    }
}
