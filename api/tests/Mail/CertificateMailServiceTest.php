<?php

declare(strict_types=1);

namespace DxFondito\Tests\Mail;

use DateTimeImmutable;
use DateTimeZone;
use DxFondito\Auth\User;
use DxFondito\Http\UploadedFile;
use DxFondito\Mail\CertificateMailService;
use DxFondito\Mail\MailSender;
use DxFondito\Mail\SendingLimit;
use DxFondito\Mail\Templates;
use DxFondito\Ranking\ContactRow;
use DxFondito\Templates\CertificateService;
use DxFondito\Templates\Fonts;
use DxFondito\Templates\TextRenderer;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use DxFondito\Tests\Logs\MemoryFileStore;
use DxFondito\Tests\Registry\MemoryLicenseeStore;
use DxFondito\Tests\Templates\MemoryCertificateTemplateStore;
use DxFondito\Tests\Templates\MemoryRankingStore;
use PHPUnit\Framework\TestCase;

final class CertificateMailServiceTest extends TestCase
{
    private MemoryMailStore $store;
    private MemoryMailer $mailer;
    private MemoryRankingStore $ranking;
    private MemoryCertificateTemplateStore $certificateTemplates;
    private CertificateService $certificates;
    private MemoryLicenseeStore $licensees;
    private MemoryAuditLog $audit;
    private User $admin;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $users = new MemoryUserStore();
        $this->admin = $users->findById($users->create('LU1ADM', 'Admin', 'admin@example.com', User::ADMINISTRATOR, 'hash', false));
        $this->ranking = new MemoryRankingStore();
        $this->certificateTemplates = new MemoryCertificateTemplateStore();
        $this->audit = new MemoryAuditLog();
        $this->certificates = new CertificateService(
            $this->certificateTemplates,
            $this->ranking,
            new MemoryFileStore(),
            new TextRenderer(new Fonts(dirname(__DIR__, 2) . '/fonts')),
            $this->audit,
        );
        $this->certificates->save($this->admin, 2026, 5, $this->image(), self::fields());
        $this->licensees = new MemoryLicenseeStore();
        $this->store = new MemoryMailStore();
        $this->store->known = ['LU9ZZA' => [['email' => 'juana@example.com', 'lastQsoAt' => '2026-10-04 12:00:00']]];
        $this->mailer = new MemoryMailer();
        // Five references for LU9ZZA: the Bronce certificate, with the date of the fifth activity.
        $this->ranking->contacts = array_merge(self::contacts('LU9ZZA', 5), self::contacts('PY9ZZ', 4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', $this->tempFiles);
    }

    public function testTheOverviewHasTheCertificatesOfTheSeason(): void
    {
        $certificates = $this->service()->overview(2026)['certificates'];

        self::assertCount(1, $certificates);
        self::assertSame(['LU9ZZA', 5, 'Bronce', '2026-08-05', 'pending'], [
            $certificates[0]['callSign'], $certificates[0]['points'], $certificates[0]['level'], $certificates[0]['date'], $certificates[0]['status'],
        ]);
    }

    public function testSendsTheCertificateAsAPdfFile(): void
    {
        $this->service()->send($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5]], false);

        $message = $this->mailer->sent[0];
        self::assertSame('Certificado Bronce 2026 - LU9ZZA', $message->subject);
        self::assertSame('application/pdf', $message->attachments[0]['type']);
        self::assertStringStartsWith('%PDF-', $message->attachments[0]['content']);
        self::assertSame('sent', $this->service()->overview(2026)['certificates'][0]['status']);
    }

    public function testAChangedCertificateNeedsTheDecisionOfTheAdministrator(): void
    {
        $service = $this->service();
        $service->send($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5]], false);
        // A log of an earlier activity comes later: the fifth point is earlier now.
        $this->ranking->contacts[] = self::contact('LU9ZZA', 99, '2026-07-20');

        $item = $service->overview(2026)['certificates'][0];
        $without = $service->send($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5]], false);
        $with = $service->send($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5]], true);

        self::assertSame(['changed', '2026-08-04', '2026-08-05'], [$item['status'], $item['date'], $item['last']['certificateDate']]);
        self::assertSame('already-sent', $without['results'][0]['reason']);
        self::assertSame('sent', $with['results'][0]['status']);
        self::assertSame('sent', $service->overview(2026)['certificates'][0]['status']);
        self::assertSame(['pending' => 0, 'changed' => 0], $service->summary([2026]));
    }

    public function testACertificateThatIsNotReachedNowIsRevoked(): void
    {
        $service = $this->service();
        $service->mark($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5]]);
        // A deleted log: four references.
        array_pop($this->ranking->contacts);
        $this->ranking->contacts = array_values(array_filter($this->ranking->contacts, static fn (ContactRow $row): bool => !($row->baseCallSign === 'LU9ZZA' && $row->referenceId === 5)));

        $item = $service->overview(2026)['certificates'][0];
        $result = $service->send($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5]], true);

        self::assertSame(['revoked', null], [$item['status'], $item['date']]);
        self::assertSame('not-reached', $result['results'][0]['reason']);
    }

    public function testAManualMarkKeepsTheDateOfTheCertificate(): void
    {
        $service = $this->service();

        $service->mark($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5], ['callSign' => 'PY9ZZ', 'points' => 5]]);
        $item = $service->overview(2026)['certificates'][0];

        self::assertSame(['sent', 'manual', '2026-08-05'], [$item['status'], $item['last']['method'], $item['last']['certificateDate']]);
        self::assertCount(1, $this->store->deliveries);
        self::assertSame(1, $service->unmark($this->admin, 2026, [['callSign' => 'LU9ZZA', 'points' => 5]]));
    }

    public function testTheSummaryCountsThePendingCertificates(): void
    {
        self::assertSame(['pending' => 1, 'changed' => 0], $this->service()->summary([2026]));
    }

    public function testALevelWithoutTemplateIsNotAvailable(): void
    {
        $this->ranking->contacts = array_merge($this->ranking->contacts, self::contacts('LU9ZZA', 10, 6));

        $items = $this->service()->overview(2026)['certificates'];

        self::assertSame(['pending', 'unavailable'], array_column($items, 'status'));
    }

    private function service(): CertificateMailService
    {
        $now = static fn (): DateTimeImmutable => new DateTimeImmutable('2026-10-05 12:30:00', new DateTimeZone('UTC'));

        return new CertificateMailService(
            $this->ranking,
            $this->certificateTemplates,
            $this->certificates,
            $this->store,
            $this->licensees,
            new Templates($this->store, $this->audit),
            new MailSender($this->store, $this->mailer, new SendingLimit($this->store, 90, $now)),
            $this->audit,
            'https://example.com/',
        );
    }

    /**
     * One contact with each of the references $from to $count, on the days 1 to $count of August.
     *
     * @return list<ContactRow>
     */
    private static function contacts(string $base, int $count, int $from = 1): array
    {
        $contacts = [];
        for ($i = $from; $i <= $count; $i++) {
            $contacts[] = self::contact($base, $i, sprintf('2026-08-%02d', $i));
        }

        return $contacts;
    }

    private static function contact(string $base, int $reference, string $date): ContactRow
    {
        static $id = 1;

        return new ContactRow(
            id: $id++,
            baseCallSign: $base,
            callSign: $base,
            name: null,
            qsoAt: $date . ' 12:00:00',
            frequency: '7.130',
            band: '40m',
            mode: 'SSB',
            activityId: $reference,
            startDate: $date,
            season: 2026,
            referenceId: $reference,
            seriesCode: 'DPS',
            referenceCode: sprintf('DPS-%02d', $reference),
            referenceName: 'Puesto ' . $reference,
            operatorCallSign: 'LU1OP',
            operatorId: 2,
        );
    }

    private static function fields(): string
    {
        $box = ['y' => 100, 'width' => 400, 'height' => 60, 'colour' => '#1A1A1A', 'align' => 'center', 'font' => 'sans-bold'];

        return (string) json_encode([
            'call_sign' => ['x' => 100] + $box,
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
}
