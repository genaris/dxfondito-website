<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;
use DxFondito\Ranking\ContactRow;
use DxFondito\Templates\Certificate;
use DxFondito\Templates\CertificateService;
use DxFondito\Templates\Fonts;
use DxFondito\Templates\TextRenderer;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use DxFondito\Tests\Logs\MemoryFileStore;
use PHPUnit\Framework\TestCase;

final class CertificateServiceTest extends TestCase
{
    private MemoryCertificateTemplateStore $templates;
    private MemoryRankingStore $ranking;
    private MemoryFileStore $files;
    private MemoryAuditLog $audit;
    private CertificateService $service;
    private User $admin;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $users = new MemoryUserStore();
        $this->admin = $users->findById($users->create('LU1ADM', 'Admin', null, User::ADMINISTRATOR, 'hash', false));
        $this->templates = new MemoryCertificateTemplateStore();
        $this->ranking = new MemoryRankingStore();
        $this->files = new MemoryFileStore();
        $this->audit = new MemoryAuditLog();
        $this->service = new CertificateService(
            $this->templates,
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

    public function testSavesATemplateForALevelOfASeason(): void
    {
        $template = $this->service->save($this->admin, 2026, 5, $this->image(), $this->fields());

        self::assertSame([2026, 5], [$template->season, $template->points]);
        self::assertSame(['call_sign', 'date'], array_keys($template->fields));
        self::assertSame(['certificate-template.save'], $this->audit->actions());
    }

    public function testRefusesALevelThatDoesNotExist(): void
    {
        $this->assertStatus(404, fn () => $this->service->save($this->admin, 2026, 7, $this->image(), $this->fields()));
    }

    public function testANewTemplateNeedsAnImage(): void
    {
        $this->assertStatus(422, fn () => $this->service->save($this->admin, 2026, 5, null, $this->fields()));
    }

    public function testANewImageReplacesTheOldFile(): void
    {
        $this->service->save($this->admin, 2026, 5, $this->image(), $this->fields());

        $template = $this->service->save($this->admin, 2026, 5, $this->image(), $this->fields());

        self::assertSame(['file2.png'], array_keys($this->files->files));
        self::assertSame('file2.png', $template->storedName);
    }

    public function testRefusesAFieldOutOfTheImage(): void
    {
        $this->assertStatus(422, fn () => $this->service->save($this->admin, 2026, 5, $this->image(), $this->fields(x: 900)));
    }

    public function testThePreviewGivesAJpegImageAndSavesNothing(): void
    {
        $jpeg = $this->service->preview($this->image(), null, null, $this->fields());

        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($jpeg)[2]);
        self::assertSame([], $this->templates->templates);
    }

    public function testTheCertificateIsAPdfFile(): void
    {
        $this->service->save($this->admin, 2026, 5, $this->image(), $this->fields());
        $this->ranking->contacts = $this->contacts(5);

        $certificate = $this->service->certificate('LU9ZZ', 2026, 5);

        self::assertSame('Certificado_LU9ZZ_2026_5.pdf', $certificate['name']);
        self::assertStringStartsWith('%PDF-', $certificate['content']);
    }

    public function testNoCertificateBelowTheLevel(): void
    {
        // FR-CER-6: four points do not give the certificate of five points.
        $this->service->save($this->admin, 2026, 5, $this->image(), $this->fields());
        $this->ranking->contacts = $this->contacts(4);

        $this->assertStatus(404, fn () => $this->service->certificate('LU9ZZ', 2026, 5));
    }

    public function testNoCertificateWithoutTheTemplateOfTheSeason(): void
    {
        // FR-CER-7, D-22: the template of a different season does not apply.
        $this->service->save($this->admin, 2025, 5, $this->image(), $this->fields());
        $this->ranking->contacts = $this->contacts(5);

        $this->assertStatus(404, fn () => $this->service->certificate('LU9ZZ', 2026, 5));
    }

    public function testTheDeletionRemovesTheFile(): void
    {
        $this->service->save($this->admin, 2026, 5, $this->image(), $this->fields());

        $this->service->delete($this->admin, 2026, 5);

        self::assertSame([], $this->templates->templates);
        self::assertSame([], $this->files->files);
        self::assertSame(['certificate-template.save', 'certificate-template.delete'], $this->audit->actions());
    }

    public function testTheValuesOfTheCertificate(): void
    {
        self::assertSame(['call_sign' => 'LU9ZZ', 'date' => '4 de octubre de 2026'], Certificate::values('LU9ZZ', '2026-10-04'));
        self::assertSame('1 de enero de 2027', Certificate::longDate('2027-01-01'));
    }

    /**
     * One first contact with each of the references 1 to $count, on different dates.
     *
     * @return list<ContactRow>
     */
    private function contacts(int $count): array
    {
        $contacts = [];
        for ($i = 1; $i <= $count; $i++) {
            $date = sprintf('2026-05-%02d', $i);
            $contacts[] = new ContactRow(
                id: $i,
                baseCallSign: 'LU9ZZ',
                callSign: 'LU9ZZ',
                name: null,
                qsoAt: $date . ' 12:00:00',
                frequency: '7.130',
                band: '40m',
                mode: 'SSB',
                activityId: $i,
                startDate: $date,
                season: 2026,
                referenceId: $i,
                seriesCode: 'DPS',
                referenceCode: sprintf('DPS-%02d', $i),
                referenceName: 'Puesto ' . $i,
                operatorCallSign: 'LU1OP',
            );
        }

        return $contacts;
    }

    private function fields(int $x = 100): string
    {
        $box = ['y' => 100, 'width' => 400, 'height' => 60, 'colour' => '#1A1A1A', 'align' => 'center', 'font' => 'sans-bold'];

        return (string) json_encode([
            'call_sign' => ['x' => $x] + $box,
            'date' => ['x' => 100, 'y' => 300, 'width' => 400, 'height' => 30, 'colour' => '#1A1A1A', 'align' => 'center', 'font' => 'sans'],
        ]);
    }

    private function image(): UploadedFile
    {
        $image = imagecreatetruecolor(800, 500);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 250, 235));
        ob_start();
        imagepng($image);
        $content = (string) ob_get_clean();
        $path = (string) tempnam(sys_get_temp_dir(), 'cer');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile('certificado.png', $path, strlen($content));
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
