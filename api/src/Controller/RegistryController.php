<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Auth\Authenticator;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Registry\RegistryService;

/**
 * The official registries of licensees (section 8.4 of the system design). Only an administrator uses them.
 */
final class RegistryController
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly RegistryService $registries,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->registries->updates());
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $count = $this->registries->update($actor, $params['country'] ?? '');

        return Response::json(['country' => strtoupper($params['country'] ?? ''), 'count' => $count]);
    }
}
