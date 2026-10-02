<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Auth\Authenticator;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\PathId;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Logs\Contact;
use DxFondito\Logs\Log;
use DxFondito\Logs\LogService;

/**
 * The log requests of section 8.3 of the system design. A signed-in user can use them.
 */
final class LogController
{
    private const NOT_FOUND = 'The log does not exist';

    public function __construct(
        private readonly Authenticator $auth,
        private readonly LogService $logs,
    ) {
    }

    /**
     * The upload in two steps. The `mode` field is `preview` or `save`.
     *
     * @param array<string, string> $params
     */
    public function upload(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        if ($request->bodyTooLarge) {
            throw new HttpException(413, 'The file is larger than 5 MB');
        }
        $activityId = PathId::from($params, 'The activity does not exist');
        $operatorId = $request->int('operatorId');
        $file = $request->files['file'] ?? null;

        return match ($request->string('mode')) {
            'preview' => Response::json($this->logs->preview($actor, $activityId, $operatorId, $file)),
            'save' => Response::json($this->data($this->logs->save($actor, $activityId, $operatorId, $file), $actor), 201),
            default => throw new HttpException(422, 'The mode must be preview or save'),
        };
    }

    /**
     * @param array<string, string> $params
     */
    public function byActivity(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $logs = $this->logs->byActivity(PathId::from($params, 'The activity does not exist'));

        return Response::json(array_map(
            fn (Log $log): array => $this->data($log, $actor),
            $logs,
        ));
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $log = $this->logs->find(PathId::from($params, self::NOT_FOUND));

        return Response::json($this->data($log, $actor) + [
            'contacts' => array_map(static fn (Contact $contact): array => $contact->publicData(), $this->logs->contacts($log)),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function file(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $file = $this->logs->file($actor, PathId::from($params, self::NOT_FOUND));

        return Response::download($file['content'], $file['name']);
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $this->logs->delete($actor, PathId::from($params, self::NOT_FOUND));

        return Response::json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Log $log, User $actor): array
    {
        // The browser shows the buttons for the download and the deletion only to these users.
        return $log->publicData() + ['canManage' => LogService::canManage($actor, $log)];
    }
}
