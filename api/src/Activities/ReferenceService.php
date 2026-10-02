<?php

declare(strict_types=1);

namespace DxFondito\Activities;

use DxFondito\Audit\AuditLog;
use DxFondito\Audit\Changes;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;

/**
 * The administration of the references (FR-REF-1 to FR-REF-8).
 */
final class ReferenceService
{
    public const MAX_NAME = 150;

    public function __construct(
        private readonly ReferenceStore $references,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * The series with their next free number (FR-REF-3), and all references.
     *
     * @return array{series: list<array<string, mixed>>, references: list<array<string, mixed>>}
     */
    public function overview(): array
    {
        return [
            'series' => array_map(
                fn (Series $series): array => [
                    'id' => $series->id,
                    'code' => $series->code,
                    'name' => $series->name,
                    'nextNumber' => $this->nextNumber($series->id),
                ],
                $this->references->allSeries(),
            ),
            'references' => array_map(
                static fn (Reference $reference): array => $reference->publicData(),
                $this->references->all(),
            ),
        ];
    }

    /**
     * The highest number of the series and one. Thus the code of a deleted reference does not come back.
     */
    public function nextNumber(int $seriesId): int
    {
        return min($this->references->highestNumber($seriesId) + 1, Reference::MAX_NUMBER);
    }

    /**
     * @param int|null $number Null gives the next free number of the series.
     * @throws HttpException 422 for an incorrect value, 409 if the code exists (FR-REF-4).
     */
    public function create(User $actor, int $seriesId, ?int $number, string $name, ?string $description): Reference
    {
        $series = $this->series($seriesId);
        $number ??= $this->nextNumber($seriesId);
        self::checkNumber($number);
        $name = TextInput::name($name, self::MAX_NAME);
        $description = TextInput::description($description);
        $this->requireFreeCode($series, $number, null);

        $id = $this->references->create($seriesId, $number, $name, $description);
        $reference = $this->find($id);
        $this->audit->record($actor->id, AuditLog::REFERENCE_CREATE, $id, [
            'code' => $reference->referenceCode(),
            'name' => $name,
            'description' => $description,
        ]);

        return $reference;
    }

    /**
     * FR-REF-6.
     *
     * @throws HttpException 404, 422, or 409 if the code exists.
     */
    public function update(User $actor, int $id, int $seriesId, int $number, string $name, ?string $description): Reference
    {
        $reference = $this->find($id);
        $series = $this->series($seriesId);
        self::checkNumber($number);
        $name = TextInput::name($name, self::MAX_NAME);
        $description = TextInput::description($description);
        $this->requireFreeCode($series, $number, $id);

        $changes = Changes::between(
            ['code' => $reference->referenceCode(), 'name' => $reference->name, 'description' => $reference->description],
            ['code' => Reference::code($series->code, $number), 'name' => $name, 'description' => $description],
        );
        if ($changes === []) {
            return $reference;
        }

        $this->references->update($id, $seriesId, $number, $name, $description);
        $this->audit->record($actor->id, AuditLog::REFERENCE_UPDATE, $id, [
            'code' => $reference->referenceCode(),
            'changes' => $changes,
        ]);

        return $this->find($id);
    }

    /**
     * FR-REF-7.
     *
     * @throws HttpException 404, or 409 if the reference has activities.
     */
    public function delete(User $actor, int $id): void
    {
        $reference = $this->find($id);
        if ($this->references->hasActivities($id)) {
            throw new HttpException(409, 'The reference has activities');
        }

        $this->references->delete($id);
        $this->audit->record($actor->id, AuditLog::REFERENCE_DELETE, $id, [
            'code' => $reference->referenceCode(),
            'name' => $reference->name,
        ]);
    }

    public function find(int $id): Reference
    {
        return $this->references->find($id) ?? throw new HttpException(404, 'The reference does not exist');
    }

    private function series(int $id): Series
    {
        return $this->references->findSeries($id) ?? throw new HttpException(422, 'The series does not exist');
    }

    private function requireFreeCode(Series $series, int $number, ?int $ownId): void
    {
        $other = $this->references->findByNumber($series->id, $number);
        if ($other !== null && $other->id !== $ownId) {
            throw new HttpException(409, 'The reference code exists');
        }
    }

    private static function checkNumber(int $number): void
    {
        if ($number < 1 || $number > Reference::MAX_NUMBER) {
            throw new HttpException(422, 'The number must be 1 to ' . Reference::MAX_NUMBER);
        }
    }
}
