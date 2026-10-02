<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Activities\ReferenceService;
use DxFondito\Auth\Authenticator;
use DxFondito\Http\HttpException;
use DxFondito\Http\PathId;
use DxFondito\Http\Request;
use DxFondito\Http\Response;

/**
 * The reference requests of section 8.4 of the system design. Only an administrator can use them.
 */
final class ReferenceController
{
    private const NOT_FOUND = 'The reference does not exist';

    public function __construct(
        private readonly Authenticator $auth,
        private readonly ReferenceService $references,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->references->overview());
    }

    public function create(Request $request): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $reference = $this->references->create(
            $actor,
            self::seriesId($request),
            $request->int('number'),
            $request->string('name'),
            $request->string('description'),
        );

        return Response::json($reference->publicData(), 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $number = $request->int('number') ?? throw new HttpException(422, 'The number must be 1 to 999');
        $reference = $this->references->update(
            $actor,
            PathId::from($params, self::NOT_FOUND),
            self::seriesId($request),
            $number,
            $request->string('name'),
            $request->string('description'),
        );

        return Response::json($reference->publicData());
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $this->references->delete($actor, PathId::from($params, self::NOT_FOUND));

        return Response::json(['deleted' => true]);
    }

    private static function seriesId(Request $request): int
    {
        return $request->int('seriesId') ?? throw new HttpException(422, 'The series does not exist');
    }
}
