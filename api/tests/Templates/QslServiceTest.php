<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;
use DxFondito\Ranking\ContactRow;
use DxFondito\Templates\Fonts;
use DxFondito\Templates\QslCard;
use DxFondito\Templates\QslService;
use DxFondito\Templates\TextRenderer;
use DxFondito\Tests\Activities\MemoryActivityStore;
use DxFondito\Tests\Activities\MemoryReferenceStore;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use DxFondito\Tests\Logs\MemoryFileStore;
use PHPUnit\Framework\TestCase;

final class QslServiceTest extends TestCase
{
    private MemoryUserStore $users;
    private MemoryQslTemplateStore $templates;
    private MemoryRankingStore $ranking;
    private MemoryFileStore $files;
    private MemoryAuditLog $audit;
    private QslService $service;
    private User $admin;
    private User $operator;
    private User $otherOperator;
    private int $activityId;
    private int $referenceId;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->users = new MemoryUserStore();
        $this->admin = $this->user('LU1ADM', User::ADMINISTRATOR);
        $this->operator = $this->user('LU1OP', User::OPERATOR);
        $this->otherOperator = $this->user('LU2OP', User::OPERATOR);

        $references = new MemoryReferenceStore();
        $activities = new MemoryActivityStore($references);
        $this->referenceId = $references->create(1, 1, 'Hospital', null);
        $this->activityId = $activities->create($this->referenceId, 2026, '2026-05-10', '2026-05-10', null);

        $this->templates = new MemoryQslTemplateStore();
        $this->ranking = new MemoryRankingStore();
        $this->files = new MemoryFileStore();
        $this->audit = new MemoryAuditLog();
        $this->service = new QslService(
            $this->templates,
            $activities,
            $this->users,
            $this->ranking,
            $this->files,
            new TextRenderer(new Fonts(dirname(__DIR__, 2) . '/fonts')),
            $this->audit,
        );
    }

    protected function tearDown(): void
    {
        array_map('unlink', $this->tempFiles);
    }

    public function testTheOperatorSavesTheOwnTemplate(): void
    {
        $template = $this->service->save($this->operator, $this->activityId, $this->operator->id, $this->image(), $this->fields());

        self::assertSame($this->operator->id, $template->operatorId);
        self::assertSame(['file1.png'], array_keys($this->files->files));
        self::assertSame(['qsl-template.save'], $this->audit->actions());
    }

    public function testAnOperatorCannotSaveTheTemplateOfADifferentOperator(): void
    {
        $this->assertStatus(403, fn () => $this->service->save($this->operator, $this->activityId, $this->otherOperator->id, $this->image(), $this->fields()));
    }

    public function testAnAdministratorSavesAllTemplates(): void
    {
        $template = $this->service->save($this->admin, $this->activityId, $this->otherOperator->id, $this->image(), $this->fields());

        self::assertSame($this->otherOperator->id, $template->operatorId);
    }

    public function testANewTemplateNeedsAnImage(): void
    {
        $this->assertStatus(422, fn () => $this->service->save($this->operator, $this->activityId, $this->operator->id, null, $this->fields()));
    }

    public function testAChangeOfTheFieldsKeepsTheImage(): void
    {
        $this->service->save($this->operator, $this->activityId, $this->operator->id, $this->image(), $this->fields());

        $template = $this->service->save($this->operator, $this->activityId, $this->operator->id, null, $this->fields(height: 50));

        self::assertSame('file1.png', $template->storedName);
        self::assertSame(50, $template->fields['call_sign']['height']);
    }

    public function testANewImageReplacesTheOldFile(): void
    {
        $this->service->save($this->operator, $this->activityId, $this->operator->id, $this->image(), $this->fields());

        $template = $this->service->save($this->operator, $this->activityId, $this->operator->id, $this->image(), $this->fields());

        self::assertSame('file2.png', $template->storedName);
        self::assertSame(['file2.png'], array_keys($this->files->files));
    }

    public function testRefusesAFieldOutOfTheImage(): void
    {
        $this->assertStatus(422, fn () => $this->service->save($this->operator, $this->activityId, $this->operator->id, $this->image(), $this->fields(x: 900)));
    }

    public function testRefusesAFileThatIsNotAnImage(): void
    {
        $file = $this->upload('not an image', 'qsl.png');

        $this->assertStatus(422, fn () => $this->service->save($this->operator, $this->activityId, $this->operator->id, $file, $this->fields()));
    }

    public function testThePreviewGivesAJpegImageAndSavesNothing(): void
    {
        $jpeg = $this->service->preview($this->operator, $this->image(), null, null, $this->fields());

        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($jpeg)[2]);
        self::assertSame([], $this->files->files);
        self::assertSame([], $this->templates->templates);
    }

    public function testTheCardUsesTheTemplateOfTheOperatorOfTheFirstContact(): void
    {
        $this->service->save($this->admin, $this->activityId, $this->otherOperator->id, $this->image(), $this->fields());
        $this->ranking->contacts = [
            $this->contact('2026-05-10 15:00:00', $this->operator),
            $this->contact('2026-05-10 12:00:00', $this->otherOperator),
        ];

        $card = $this->service->card('LU9ZZ', 2026, $this->referenceId);

        self::assertSame('QSL_LU9ZZ_DPS-01_2026.jpg', $card['name']);
        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($card['content'])[2]);
    }

    public function testTheCardIsNotAvailableWithoutTheTemplateOfThatOperator(): void
    {
        // The template of a different operator does not apply (FR-QSL-9, FR-QSL-11).
        $this->service->save($this->operator, $this->activityId, $this->operator->id, $this->image(), $this->fields());
        $this->ranking->contacts = [$this->contact('2026-05-10 12:00:00', $this->otherOperator)];

        $this->assertStatus(404, fn () => $this->service->card('LU9ZZ', 2026, $this->referenceId));
    }

    public function testNoCardWithoutAContact(): void
    {
        $this->assertStatus(404, fn () => $this->service->card('LU9ZZ', 2026, $this->referenceId));
    }

    public function testTheOperatorDeletesTheOwnTemplate(): void
    {
        $this->service->save($this->operator, $this->activityId, $this->operator->id, $this->image(), $this->fields());

        $this->assertStatus(403, fn () => $this->service->delete($this->otherOperator, $this->activityId, $this->operator->id));
        $this->service->delete($this->operator, $this->activityId, $this->operator->id);

        self::assertSame([], $this->templates->templates);
        self::assertSame([], $this->files->files);
    }

    public function testTheValuesOfTheCard(): void
    {
        $values = QslCard::values($this->contact('2026-05-10 14:07:00', $this->operator));

        self::assertSame(
            ['call_sign' => 'LU9ZZ/P', 'name' => 'Ana', 'date' => '10/05/2026', 'time' => '14:07', 'frequency' => '7.13 MHz', 'mode' => 'SSB', 'rst' => '59'],
            $values,
        );
    }

    public function testTheBandWithoutAFrequency(): void
    {
        $contact = $this->contact('2026-05-10 14:07:00', $this->operator, frequency: null);

        self::assertSame('40m', QslCard::values($contact)['frequency']);
    }

    private function contact(string $qsoAt, User $operator, ?string $frequency = '7.13'): ContactRow
    {
        static $id = 1;

        return new ContactRow(
            id: $id++,
            baseCallSign: 'LU9ZZ',
            callSign: 'LU9ZZ/P',
            name: 'Ana',
            qsoAt: $qsoAt,
            frequency: $frequency,
            band: '40m',
            mode: 'SSB',
            activityId: $this->activityId,
            startDate: '2026-05-10',
            season: 2026,
            referenceId: $this->referenceId,
            seriesCode: 'DPS',
            referenceCode: 'DPS-01',
            referenceName: 'Hospital',
            operatorCallSign: $operator->callSign,
            operatorId: $operator->id,
            rstSent: '59',
        );
    }

    private function fields(int $x = 100, int $height = 40): string
    {
        $fields = FieldLayoutTest::layout();
        $fields['call_sign']['x'] = $x;
        $fields['call_sign']['height'] = $height;

        return (string) json_encode($fields);
    }

    private function image(): UploadedFile
    {
        $image = imagecreatetruecolor(800, 500);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 240, 255));
        ob_start();
        imagepng($image);

        return $this->upload((string) ob_get_clean(), 'qsl.png');
    }

    private function upload(string $content, string $name): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'qsl');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile($name, $path, strlen($content));
    }

    private function user(string $callSign, string $role): User
    {
        return $this->users->findById($this->users->create($callSign, $callSign, null, $role, 'hash', false));
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
