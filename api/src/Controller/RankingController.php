<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use Closure;
use DateTimeImmutable;
use DxFondito\CallSign;
use DxFondito\Http\HttpException;
use DxFondito\Http\PathId;
use DxFondito\Http\Response;
use DxFondito\Ranking\Calculator;
use DxFondito\Ranking\ContactRow;
use DxFondito\Ranking\RankingStore;
use DxFondito\Templates\QslTemplateStore;

/**
 * The ranking and the participant page (FR-PUB-1 to FR-PUB-12). A visitor can use these requests.
 */
final class RankingController
{
    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly RankingStore $store,
        private readonly QslTemplateStore $templates,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * @param array<string, string> $params
     */
    public function ranking(array $params): Response
    {
        $season = PathId::season($params);
        $levels = $this->store->levels();

        return Response::json([
            'season' => $season,
            'levels' => $levels,
            'rows' => Calculator::ranking($this->store->seasonReferences($season), $levels),
        ]);
    }

    /**
     * The seasons of a participant, the current season first (FR-PUB-8 to FR-PUB-12).
     * A participant without contacts gives an empty list of seasons.
     *
     * @param array<string, string> $params
     */
    public function participant(array $params): Response
    {
        $callSign = CallSign::base($params['call'] ?? '');
        if (!CallSign::isValid($callSign)) {
            throw new HttpException(404, 'The participant does not exist');
        }

        $levels = $this->store->levels();
        $current = (int) ($this->now)()->format('Y');
        $bySeason = [];
        $firstContacts = Calculator::firstContacts($this->store->byParticipant($callSign));
        foreach ($firstContacts as $contact) {
            $bySeason[$contact->season][] = $contact;
        }
        // FR-QSL-11: a QSL card needs the template of the operator of the first contact, for its activity.
        $templates = array_flip($this->templates->keys(array_values(array_unique(
            array_map(static fn (ContactRow $contact): int => $contact->activityId, $firstContacts),
        ))));
        uksort($bySeason, static fn (int $a, int $b): int => [$a !== $current, -$a] <=> [$b !== $current, -$b]);

        $seasons = [];
        foreach ($bySeason as $season => $contacts) {
            usort($contacts, static fn (ContactRow $a, ContactRow $b): int => [$a->qsoAt, $a->id] <=> [$b->qsoAt, $b->id]);
            $points = count($contacts);
            $seasons[] = [
                'season' => $season,
                'points' => $points,
                'pointsToNextLevel' => $season === $current ? Calculator::pointsToNextLevel($points, $levels) : null,
                'references' => array_map(
                    static fn (ContactRow $contact): array => self::firstContactData($contact)
                        + ['qsl' => isset($templates[$contact->activityId . ':' . $contact->operatorId])],
                    $contacts,
                ),
                'certificates' => Calculator::certificates($contacts, $levels),
            ];
        }

        return Response::json(['callSign' => $callSign, 'current' => $current, 'levels' => $levels, 'seasons' => $seasons]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstContactData(ContactRow $contact): array
    {
        return [
            'referenceId' => $contact->referenceId,
            'reference' => $contact->referenceCode,
            'referenceName' => $contact->referenceName,
            'activityId' => $contact->activityId,
            'callSign' => $contact->callSign,
            'qsoAt' => str_replace(' ', 'T', $contact->qsoAt) . 'Z',
            'frequency' => $contact->frequency,
            'band' => $contact->band,
            'mode' => $contact->mode,
            'operator' => $contact->operatorCallSign,
        ];
    }
}
