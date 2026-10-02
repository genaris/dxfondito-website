<?php

declare(strict_types=1);

namespace DxFondito\Tests\Logs;

use DateTimeImmutable;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;
use DxFondito\Logs\LogService;
use DxFondito\Tests\Activities\MemoryActivityStore;
use DxFondito\Tests\Activities\MemoryReferenceStore;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LogServiceTest extends TestCase
{
    private const ADIF = "<EOH>\n"
        . "<CALL:6>LU2ABC<QSO_DATE:8>20260510<TIME_ON:4>1200<MODE:2>CW<FREQ:5>7.030<EOR>\n"
        . "<CALL:4>CX1A<QSO_DATE:8>20260510<TIME_ON:4>1205<MODE:2>CW<FREQ:5>7.030<EOR>\n"
        . "<CALL:4>PY1A<QSO_DATE:8>20260510<MODE:2>CW<FREQ:5>7.030<EOR>\n";

    private MemoryUserStore $users;
    private MemoryLogStore $logs;
    private MemoryFileStore $files;
    private MemoryAuditLog $audit;
    private LogService $service;
    private User $admin;
    private User $operator;
    private User $otherOperator;
    private int $activityId;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->users = new MemoryUserStore();
        $this->admin = $this->user('LU1ADM', User::ADMINISTRATOR);
        $this->operator = $this->user('LU1ABC', User::OPERATOR);
        $this->otherOperator = $this->user('LU3XYZ', User::OPERATOR);

        $references = new MemoryReferenceStore();
        $activities = new MemoryActivityStore($references);
        $referenceId = $references->create(1, 1, 'Hospital', null);
        $this->activityId = $activities->create($referenceId, 2026, '2026-05-10', '13:00', '2026-05-10', '20:00', null);

        $this->logs = new MemoryLogStore($this->users);
        $this->files = new MemoryFileStore();
        $this->audit = new MemoryAuditLog();
        $this->service = new LogService(
            $this->logs,
            $activities,
            $this->users,
            $this->files,
            $this->audit,
            fn (): DateTimeImmutable => new DateTimeImmutable('2026-05-11 08:00:00'),
        );
    }

    protected function tearDown(): void
    {
        array_map('unlink', $this->tempFiles);
    }

    public function testThePreviewSavesNothing(): void
    {
        $summary = $this->service->preview($this->operator, $this->activityId, null, $this->file());

        self::assertSame('dps01.adi', $summary['fileName']);
        self::assertSame('LU1ABC', $summary['operator']['callSign']);
        self::assertSame(2, $summary['validCount']);
        self::assertCount(1, $summary['invalid']);
        self::assertSame([], $this->logs->logs);
        self::assertSame([], $this->files->files);
    }

    public function testSavesTheValidRecordsAndTheOriginalFile(): void
    {
        $log = $this->service->save($this->operator, $this->activityId, null, $this->file());

        self::assertSame(2, $log->contactCount);
        self::assertSame($this->operator->id, $log->operatorId);
        self::assertSame($this->operator->id, $log->uploadedById);
        self::assertSame('2026-05-11 08:00:00', $log->uploadedAt);
        self::assertSame(['LU2ABC', 'CX1A'], array_map(fn ($contact) => $contact->callSign, $this->logs->contacts[$log->id]));
        self::assertSame(self::ADIF, $this->files->read($log->storedName));
        self::assertSame(
            ['label' => 'DPS-01 (2026-05-10)', 'fileName' => 'dps01.adi', 'operator' => 'LU1ABC', 'contacts' => 2],
            $this->audit->entries[0]['detail'],
        );
    }

    public function testAnAdministratorSelectsTheOperator(): void
    {
        $log = $this->service->save($this->admin, $this->activityId, $this->operator->id, $this->file());

        self::assertSame($this->operator->id, $log->operatorId);
        self::assertSame($this->admin->id, $log->uploadedById);
    }

    public function testAnOperatorCannotUploadForADifferentOperator(): void
    {
        $this->assertStatus(403, fn () => $this->service->save($this->operator, $this->activityId, $this->otherOperator->id, $this->file()));
    }

    public function testRefusesAFileWithoutValidRecords(): void
    {
        $this->assertStatus(422, fn () => $this->service->save($this->operator, $this->activityId, null, $this->file('<CALL:6>LU2ABC<EOR>')));
        self::assertSame([], $this->files->files);
    }

    public function testRefusesADifferentFileType(): void
    {
        $this->assertStatus(422, fn () => $this->service->preview($this->operator, $this->activityId, null, $this->file(self::ADIF, 'log.txt')));
        $this->assertStatus(422, fn () => $this->service->preview($this->operator, $this->activityId, null, null));
    }

    public function testAcceptsTheAdifExtensionInUppercaseLetters(): void
    {
        $summary = $this->service->preview($this->operator, $this->activityId, null, $this->file(self::ADIF, 'LOG.ADIF'));

        self::assertSame(2, $summary['validCount']);
    }

    public function testRefusesALargeFile(): void
    {
        $file = new UploadedFile('big.adi', '/nonexistent', LogService::MAX_BYTES + 1);
        $this->assertStatus(413, fn () => $this->service->preview($this->operator, $this->activityId, null, $file));

        $refusedByPhp = new UploadedFile('big.adi', '', 0, UPLOAD_ERR_INI_SIZE);
        $this->assertStatus(413, fn () => $this->service->preview($this->operator, $this->activityId, null, $refusedByPhp));
    }

    public function testRefusesAnUnknownActivity(): void
    {
        $this->assertStatus(404, fn () => $this->service->preview($this->operator, 99, null, $this->file()));
    }

    public function testRemovesTheFileWhenTheDatabaseFails(): void
    {
        $this->logs->failNextCreate = true;

        try {
            $this->service->save($this->operator, $this->activityId, null, $this->file());
            self::fail('The save did not fail.');
        } catch (RuntimeException) {
        }

        self::assertSame([], $this->files->files);
        self::assertSame([], $this->audit->entries);
    }

    public function testTheOperatorDeletesTheOwnLog(): void
    {
        $log = $this->service->save($this->operator, $this->activityId, null, $this->file());

        $this->service->delete($this->operator, $log->id);

        self::assertSame([], $this->logs->logs);
        self::assertSame([], $this->files->files);
        self::assertSame(['log.upload', 'log.delete'], $this->audit->actions());
    }

    public function testAnOperatorCannotDeleteTheLogOfADifferentOperator(): void
    {
        $log = $this->service->save($this->operator, $this->activityId, null, $this->file());

        $this->assertStatus(403, fn () => $this->service->delete($this->otherOperator, $log->id));
        // FR-LOG-23: an administrator can delete all logs.
        $this->service->delete($this->admin, $log->id);
        self::assertSame([], $this->logs->logs);
    }

    public function testOnlyTheOperatorAndTheAdministratorsGetTheOriginalFile(): void
    {
        $log = $this->service->save($this->operator, $this->activityId, null, $this->file());

        self::assertSame(['name' => 'dps01.adi', 'content' => self::ADIF], $this->service->file($this->operator, $log->id));
        self::assertSame(self::ADIF, $this->service->file($this->admin, $log->id)['content']);
        $this->assertStatus(403, fn () => $this->service->file($this->otherOperator, $log->id));
    }

    private function user(string $callSign, string $role): User
    {
        return $this->users->findById($this->users->create($callSign, $callSign, null, $role, 'hash', false));
    }

    private function file(string $content = self::ADIF, string $name = 'dps01.adi'): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'adif');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile($name, $path, strlen($content));
    }

    private function assertStatus(int $status, callable $action): void
    {
        try {
            $action();
            self::fail('The action did not fail.');
        } catch (HttpException $e) {
            self::assertSame($status, $e->status, $e->getMessage());
        }
    }
}
