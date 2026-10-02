<?php

declare(strict_types=1);

namespace DxFondito\Ranking;

interface RankingStore
{
    /**
     * @return list<int> The certificate levels, such as [5, 10, 15].
     */
    public function levels(): array;

    /**
     * The references where each participant has one or more contacts in the season.
     * One line for each participant and reference, not for each contact. Thus the ranking reads few lines.
     *
     * @return list<array{callSign: string, referenceId: int, seriesCode: string, number: int}>
     */
    public function seasonReferences(int $season): array;

    /**
     * @return list<ContactRow> The contacts of one participant in all seasons.
     */
    public function byParticipant(string $baseCallSign): array;

    /**
     * @return list<ContactRow>
     */
    public function byActivity(int $activityId): array;
}
