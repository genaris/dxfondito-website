<?php

declare(strict_types=1);

namespace DxFondito\Logs;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DxFondito\Activities\Activity;
use DxFondito\Activities\ActivityStore;
use DxFondito\Audit\AuditLog;
use DxFondito\Auth\User;
use DxFondito\Auth\UserStore;
use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;
use Throwable;

/**
 * The upload in two steps (system design, section 5.2), the log list and the deletion (FR-LOG-1 to FR-LOG-25).
 */
final class LogService
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly LogStore $logs,
        private readonly ActivityStore $activities,
        private readonly UserStore $users,
        private readonly FileStore $files,
        private readonly AuditLog $audit,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * Reads the file and gives the summary. Saves nothing (FR-LOG-10).
     *
     * @return array<string, mixed>
     */
    public function preview(User $actor, int $activityId, ?int $operatorId, ?UploadedFile $file): array
    {
        $activity = $this->activity($activityId);
        $operator = $this->operator($actor, $operatorId);
        $content = self::content($file);

        return $this->summaryData(LogSummary::read($content, $activity, $operator), $operator, $file);
    }

    /**
     * Reads the file again and saves the log, its valid records and the original file (FR-LOG-15, FR-LOG-18).
     *
     * @throws HttpException 422 if the file has no valid records (FR-LOG-16).
     */
    public function save(User $actor, int $activityId, ?int $operatorId, ?UploadedFile $file): Log
    {
        $activity = $this->activity($activityId);
        $operator = $this->operator($actor, $operatorId);
        $content = self::content($file);
        $summary = LogSummary::read($content, $activity, $operator);
        if ($summary->contacts === []) {
            throw new HttpException(422, 'The file has no valid records');
        }

        $fileName = self::fileName($file);
        $storedName = $this->files->save($content);
        try {
            $id = $this->logs->create(
                $activity->id,
                $operator->id,
                $actor->id,
                $fileName,
                $storedName,
                $summary->contacts,
                ($this->now)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            );
        } catch (Throwable $e) {
            $this->files->delete($storedName);
            throw $e;
        }

        $this->audit->record($actor->id, AuditLog::LOG_UPLOAD, $id, [
            'label' => $activity->label(),
            'fileName' => $fileName,
            'operator' => $operator->callSign,
            'contacts' => count($summary->contacts),
        ]);

        return $this->find($id);
    }

    /**
     * FR-LOG-19 and FR-LOG-20.
     *
     * @return list<Log>
     */
    public function byActivity(int $activityId): array
    {
        $this->activity($activityId);

        return $this->logs->byActivity($activityId);
    }

    /**
     * FR-LOG-21.
     *
     * @return list<Contact>
     */
    public function contacts(Log $log): array
    {
        return $this->logs->contacts($log->id);
    }

    /**
     * The original file, for the operator of the log and the administrators (FR-LOG-18).
     *
     * @return array{name: string, content: string}
     */
    public function file(User $actor, int $id): array
    {
        $log = $this->find($id);
        $this->requireOwnerOrAdministrator($actor, $log);
        $content = $this->files->read($log->storedName) ?? throw new HttpException(404, 'The file does not exist');

        return ['name' => $log->fileName, 'content' => $content];
    }

    /**
     * FR-LOG-22, FR-LOG-23 and FR-LOG-25.
     */
    public function delete(User $actor, int $id): void
    {
        $log = $this->find($id);
        $this->requireOwnerOrAdministrator($actor, $log);
        $activity = $this->activities->find($log->activityId);

        $this->logs->delete($log->id);
        $this->files->delete($log->storedName);
        $this->audit->record($actor->id, AuditLog::LOG_DELETE, $log->id, [
            'label' => $activity?->label(),
            'fileName' => $log->fileName,
            'operator' => $log->operatorCallSign,
            'contacts' => $log->contactCount,
        ]);
    }

    public function find(int $id): Log
    {
        return $this->logs->find($id) ?? throw new HttpException(404, 'The log does not exist');
    }

    public static function canManage(User $actor, Log $log): bool
    {
        return $actor->isAdministrator() || $actor->id === $log->operatorId;
    }

    private function requireOwnerOrAdministrator(User $actor, Log $log): void
    {
        if (!self::canManage($actor, $log)) {
            throw new HttpException(403, 'Only the operator of the log or an administrator can do this');
        }
    }

    /**
     * The operator of the new log: the user, or for an administrator the selected account (FR-LOG-3, FR-LOG-4).
     */
    private function operator(User $actor, ?int $operatorId): User
    {
        if ($operatorId === null || $operatorId === $actor->id) {
            return $actor;
        }
        if (!$actor->isAdministrator()) {
            throw new HttpException(403, 'An operator can upload only the own logs');
        }
        $operator = $this->users->findById($operatorId);
        if ($operator === null || !$operator->active) {
            throw new HttpException(422, 'The operator does not exist');
        }

        return $operator;
    }

    private function activity(int $id): Activity
    {
        return $this->activities->find($id) ?? throw new HttpException(404, 'The activity does not exist');
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(LogSummary $summary, User $operator, ?UploadedFile $file): array
    {
        return [
            'fileName' => self::fileName($file),
            'operator' => ['id' => $operator->id, 'callSign' => $operator->callSign],
        ] + $summary->publicData();
    }

    /**
     * @throws HttpException 422 without a file or with a different file type, 413 for a large file.
     */
    private static function content(?UploadedFile $file): string
    {
        if ($file === null || $file->error === UPLOAD_ERR_NO_FILE) {
            throw new HttpException(422, 'A file is necessary');
        }
        if ($file->isTooLarge() || $file->size > self::MAX_BYTES) {
            throw new HttpException(413, 'The file is larger than 5 MB');
        }
        if ($file->error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'The upload of the file failed');
        }
        if (preg_match('/\.(adi|adif)$/i', $file->name) !== 1) {
            throw new HttpException(422, 'The file must be an ADIF file (.adi or .adif)');
        }

        return $file->content();
    }

    private static function fileName(?UploadedFile $file): string
    {
        $name = trim(basename(str_replace('\\', '/', (string) $file?->name)));

        return mb_substr($name === '' ? 'log.adi' : $name, 0, 255);
    }
}
