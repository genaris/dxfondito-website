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
use DxFondito\Templates\CertificateTemplateStore;
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
        private readonly CertificateTemplateStore $certificateTemplates,
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
            // FR-CER-7: the levels with a template in the season. The ranking gives a link only for them.
            'certificateLevels' => $this->certificateTemplates->levels($season),
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
        $all = $this->store->byParticipant($callSign);
        // The first contact with each reference in each season gives the point (R-PTS-1, R-OPR-3).
        $firstContacts = Calculator::firstContacts($all);
        $pointIds = array_flip(array_map(static fn (ContactRow $contact): int => $contact->id, $firstContacts));
        $firstBySeason = [];
        foreach ($firstContacts as $contact) {
            $firstBySeason[$contact->season][] = $contact;
        }
        $bySeason = [];
        foreach (Calculator::activityContacts($all) as $contact) {
            $bySeason[$contact->season][] = $contact;
        }
        // FR-QSL-9, FR-QSL-11: the QSL card of a contact needs the template of its operator, for its activity.
        $templates = array_flip($this->templates->keys(array_values(array_unique(
            array_map(static fn (ContactRow $contact): int => $contact->activityId, $all),
        ))));
        uksort($bySeason, static fn (int $a, int $b): int => [$a !== $current, -$a] <=> [$b !== $current, -$b]);

        $seasons = [];
        foreach ($bySeason as $season => $contacts) {
            $first = $firstBySeason[$season];
            $points = count($first);
            $seasons[] = [
                'season' => $season,
                'points' => $points,
                'pointsToNextLevel' => $season === $current ? Calculator::pointsToNextLevel($points, $levels) : null,
                // All contacts, in the order of time. Each contact has its QSL card (D-27).
                'contacts' => array_map(
                    static fn (ContactRow $contact): array => self::contactData($contact) + [
                        'point' => isset($pointIds[$contact->id]),
                        'qsl' => isset($templates[$contact->activityId . ':' . $contact->operatorId]),
                    ],
                    $contacts,
                ),
                'certificates' => array_map(
                    fn (array $certificate): array => $certificate
                        + ['available' => in_array($certificate['points'], $this->certificateLevels($season), true)],
                    Calculator::certificates($first, $levels),
                ),
            ];
        }

        return Response::json(['callSign' => $callSign, 'current' => $current, 'levels' => $levels, 'seasons' => $seasons]);
    }

    /** @var array<int, list<int>> */
    private array $certificateLevelsBySeason = [];

    /**
     * FR-CER-7: the levels with a template in the season.
     *
     * @return list<int>
     */
    private function certificateLevels(int $season): array
    {
        return $this->certificateLevelsBySeason[$season] ??= $this->certificateTemplates->levels($season);
    }

    /**
     * @return array<string, mixed>
     */
    private static function contactData(ContactRow $contact): array
    {
        return [
            'id' => $contact->id,
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
