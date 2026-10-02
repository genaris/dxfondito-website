<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use Closure;
use DateTimeImmutable;
use DxFondito\Activities\Activity;
use DxFondito\Activities\ActivityService;
use DxFondito\Activities\ActivityStore;
use DxFondito\Auth\Authenticator;
use DxFondito\Http\HttpException;
use DxFondito\Http\PathId;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Logs\LogStore;
use DxFondito\Ranking\Calculator;
use DxFondito\Ranking\ContactRow;
use DxFondito\Ranking\RankingStore;

/**
 * The public season and activity requests (section 8.1), and the activity requests of an administrator (section 8.4).
 */
final class ActivityController
{
    private const NOT_FOUND = 'The activity does not exist';

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly Authenticator $auth,
        private readonly ActivityStore $activities,
        private readonly ActivityService $service,
        private readonly LogStore $logs,
        private readonly RankingStore $ranking,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * The seasons with activities and the current season (R-SEA-3), the newest first.
     */
    public function seasons(): Response
    {
        $current = (int) ($this->now)()->format('Y');
        $seasons = array_unique([$current, ...$this->activities->seasons()]);
        rsort($seasons);

        return Response::json(['current' => $current, 'seasons' => $seasons]);
    }

    /**
     * FR-PUB-13 and FR-PUB-13a.
     *
     * @param array<string, string> $params
     */
    public function bySeason(array $params): Response
    {
        return Response::json(array_map(
            static fn (Activity $activity): array => $activity->publicData(),
            $this->activities->bySeason(PathId::season($params)),
        ));
    }

    /**
     * FR-PUB-14, FR-PUB-15 and FR-ACT-2a: the activity, the operators with a log in it,
     * and the participants with the operator of their earliest contact in the activity.
     *
     * @param array<string, string> $params
     */
    public function show(array $params): Response
    {
        $activity = $this->service->find(PathId::from($params, self::NOT_FOUND));

        return Response::json($activity->publicData() + [
            'operators' => array_map(
                static fn (array $operator): string => $operator['callSign'],
                $this->logs->operators($activity->id),
            ),
            'participants' => array_map(
                static fn (ContactRow $contact): array => [
                    'callSign' => $contact->baseCallSign,
                    'operator' => $contact->operatorCallSign,
                    'qsoAt' => str_replace(' ', 'T', $contact->qsoAt) . 'Z',
                ],
                Calculator::activityParticipants($this->ranking->byActivity($activity->id)),
            ),
        ]);
    }

    public function create(Request $request): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $activity = $this->service->create(
            $actor,
            self::referenceId($request),
            $request->string('startDate'),
            $request->string('endDate'),
            $request->string('description'),
        );

        return Response::json($activity->publicData(), 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $activity = $this->service->update(
            $actor,
            PathId::from($params, self::NOT_FOUND),
            self::referenceId($request),
            $request->string('startDate'),
            $request->string('endDate'),
            $request->string('description'),
        );

        return Response::json($activity->publicData());
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $this->service->delete($actor, PathId::from($params, self::NOT_FOUND));

        return Response::json(['deleted' => true]);
    }

    private static function referenceId(Request $request): int
    {
        return $request->int('referenceId') ?? throw new HttpException(422, 'The reference does not exist');
    }
}
