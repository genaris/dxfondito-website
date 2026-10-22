<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Auth\Authenticator;
use DxFondito\Http\PathId;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Live\LiveService;

/**
 * The requests of the live page (system design, section 5.3). A signed-in user can use them.
 */
final class LiveController
{
    private const NOT_FOUND = 'The activity does not exist';

    public function __construct(
        private readonly Authenticator $auth,
        private readonly LiveService $live,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $this->auth->requireUser($request);
        $since = is_string($request->query['since'] ?? null) ? $request->query['since'] : null;

        return Response::json($this->live->state(PathId::from($params, self::NOT_FOUND), $since));
    }

    /**
     * @param array<string, string> $params
     */
    public function sync(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $since = $request->string('since');

        return Response::json($this->live->sync(
            $actor,
            PathId::from($params, self::NOT_FOUND),
            $request->body['changes'] ?? null,
            $since === '' ? null : $since,
        ));
    }

    /**
     * @param array<string, string> $params
     */
    public function lookup(Request $request, array $params): Response
    {
        $this->auth->requireUser($request);

        return Response::json($this->live->lookup(PathId::from($params, self::NOT_FOUND)));
    }

    /**
     * @param array<string, string> $params
     */
    public function adif(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $operatorId = PathId::from(['id' => (string) ($request->query['operatorId'] ?? '')], 'The operator does not exist');
        $station = is_string($request->query['station'] ?? null) && $request->query['station'] !== '' ? $request->query['station'] : null;
        $file = $this->live->adif($actor, PathId::from($params, self::NOT_FOUND), $operatorId, $station);

        return Response::download($file['content'], $file['name']);
    }
}
