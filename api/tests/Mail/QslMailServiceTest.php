<?php

declare(strict_types=1);

namespace DxFondito\Tests\Mail;

use DateTimeImmutable;
use DateTimeZone;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;
use DxFondito\Mail\AddressBookEntry;
use DxFondito\Mail\MailSender;
use DxFondito\Mail\QslMailService;
use DxFondito\Mail\SendingLimit;
use DxFondito\Mail\Templates;
use DxFondito\Ranking\ContactRow;
use DxFondito\Templates\Fonts;
use DxFondito\Templates\QslService;
use DxFondito\Templates\TextRenderer;
use DxFondito\Tests\Activities\MemoryActivityStore;
use DxFondito\Tests\Activities\MemoryReferenceStore;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use DxFondito\Tests\Logs\MemoryFileStore;
use DxFondito\Tests\Registry\MemoryLicenseeStore;
use DxFondito\Tests\Templates\FieldLayoutTest;
use DxFondito\Tests\Templates\MemoryQslTemplateStore;
use DxFondito\Tests\Templates\MemoryRankingStore;
use PHPUnit\Framework\TestCase;

final class QslMailServiceTest extends TestCase
{
    private MemoryMailStore $store;
    private MemoryMailer $mailer;
    private MemoryRankingStore $ranking;
    private MemoryAuditLog $audit;
    private QslService $cards;
    private User $admin;
    private User $operator;
    private User $otherOperator;
    private int $activityId;
    private int $nextContact = 1;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $users = new MemoryUserStore();
        $this->admin = $users->findById($users->create('LU1ADM', 'Admin', 'admin@example.com', User::ADMINISTRATOR, 'hash', false));
        $this->operator = $users->findById($users->create('LU1OP', 'Op', null, User::OPERATOR, 'hash', false));
        $this->otherOperator = $users->findById($users->create('LU2OP', 'Op', null, User::OPERATOR, 'hash', false));

        $references = new MemoryReferenceStore();
        $this->activities = new MemoryActivityStore($references);
        $referenceId = $references->create(1, 5, 'Parque Rivadavia', null);
        $this->activityId = $this->activities->create($referenceId, 2026, '2026-10-04', '13:00', '2026-10-04', '18:00', null);

        $this->ranking = new MemoryRankingStore();
        $this->qslTemplates = new MemoryQslTemplateStore();
        $this->licensees = new MemoryLicenseeStore();
        $this->licensees->replace('AR', ['LU9ZZA' => 'JUANA ISABEL EJEMPLO'], 'test');
        $this->audit = new MemoryAuditLog();
        $this->cards = new QslService(
            $this->qslTemplates,
            $this->activities,
            $users,
            $this->ranking,
            new MemoryFileStore(),
            new TextRenderer(new Fonts(dirname(__DIR__, 2) . '/fonts')),
            $this->audit,
            $this->licensees,
        );
        // Only the first operator has a QSL card template.
        $this->cards->save($this->admin, $this->activityId, $this->operator->id, $this->image(), (string) json_encode(FieldLayoutTest::layout()));

        $this->store = new MemoryMailStore();
        $this->store->known = ['LU9ZZA' => [['email' => 'juana@example.com', 'lastQsoAt' => '2026-10-04 12:00:00']]];
        $this->mailer = new MemoryMailer();
        $this->ranking->contacts = [
            $this->contact('LU9ZZA', '2026-10-04 13:30:00', $this->operator),
            $this->contact('LU9ZZA', '2026-10-04 14:10:00', $this->otherOperator),
            $this->contact('PY9ZZ', '2026-10-04 13:45:00', $this->operator),
        ];
    }

    private MemoryActivityStore $activities;
    private MemoryQslTemplateStore $qslTemplates;
    private MemoryLicenseeStore $licensees;

    protected function tearDown(): void
    {
        array_map('unlink', $this->tempFiles);
    }

    public function testTheOverviewHasTheStateOfEachParticipant(): void
    {
        $participants = $this->service()->overview($this->activityId)['participants'];

        self::assertSame(['LU9ZZA', 'PY9ZZ'], array_column($participants, 'callSign'));
        self::assertSame(['pending', 2, 1], [$participants[0]['status'], $participants[0]['contacts'], $participants[0]['cards']]);
        self::assertSame(['juana@example.com', 'adif'], [$participants[0]['recipient']['email'], $participants[0]['recipient']['source']]);
        self::assertNull($participants[1]['recipient']['email']);
    }

    public function testSendsOneMessageForEachParticipantWithItsQslCards(): void
    {
        $result = $this->service()->send($this->admin, $this->activityId, ['LU9ZZA', 'PY9ZZ'], false);

        self::assertSame(['sent', 'skipped'], array_column($result['results'], 'status'));
        self::assertSame('no-email', $result['results'][1]['reason']);
        $message = $this->mailer->sent[0];
        self::assertSame('juana@example.com', $message->to);
        self::assertSame('QSL DPS-05 Parque Rivadavia - LU9ZZA', $message->subject);
        self::assertStringContainsString('Hola Juana!', $message->text);
        self::assertStringContainsString('- 04/10/2026 13:30 UTC · 7.130 MHz · SSB · con LU1OP', $message->text);
        self::assertSame(['QSL_LU9ZZA_DPS-05_20261004_1330.jpg'], array_column($message->attachments, 'name'));
        self::assertSame('sent', $this->service()->overview($this->activityId)['participants'][0]['status']);
        self::assertSame(['mail.send'], array_slice($this->audit->actions(), -1));
    }

    public function testAQslCardThatCameLaterGoesInANewMessage(): void
    {
        $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], false);
        // The second operator uploads the template later.
        $this->cards->save($this->admin, $this->activityId, $this->otherOperator->id, $this->image(), (string) json_encode(FieldLayoutTest::layout()));

        $participant = $this->service()->overview($this->activityId)['participants'][0];
        $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], false);

        self::assertSame(['new', 1], [$participant['status'], $participant['newCards']]);
        self::assertSame(['QSL_LU9ZZA_DPS-05_20261004_1410.jpg'], array_column($this->mailer->sent[1]->attachments, 'name'));
    }

    public function testDoesNotSendTheSameQslCardsAgainWithoutResend(): void
    {
        $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], false);

        $again = $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], false);
        $resend = $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], true);

        self::assertSame('already-sent', $again['results'][0]['reason']);
        self::assertSame('sent', $resend['results'][0]['status']);
        self::assertCount(2, $this->mailer->sent);
    }

    public function testAManualMarkCountsAsSentAndCanBeUndone(): void
    {
        $service = $this->service();

        $service->mark($this->admin, $this->activityId, ['PY9ZZ']);
        $marked = $service->overview($this->activityId)['participants'][1];
        $service->unmark($this->admin, $this->activityId, ['PY9ZZ']);

        self::assertSame(['sent', 'manual'], [$marked['status'], $marked['last']['method']]);
        self::assertSame('pending', $service->overview($this->activityId)['participants'][1]['status']);
        self::assertSame([], $this->mailer->sent);
    }

    public function testUnmarkKeepsASentMessage(): void
    {
        $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], false);

        self::assertSame(0, $this->service()->unmark($this->admin, $this->activityId, ['LU9ZZA']));
        self::assertSame('sent', $this->service()->overview($this->activityId)['participants'][0]['status']);
    }

    public function testStopsAtTheLimitOfTheHour(): void
    {
        $this->store->known['PY9ZZ'] = [['email' => 'py@example.com', 'lastQsoAt' => '2026-10-04 13:45:00']];

        $result = $this->service(hourlyLimit: 1)->send($this->admin, $this->activityId, ['LU9ZZA', 'PY9ZZ'], false);

        self::assertCount(1, $this->mailer->sent);
        self::assertSame('2026-10-05T13:01:00Z', $result['retryAt']);
    }

    public function testAFailedMessageIsInTheRecordAndStaysPending(): void
    {
        $this->mailer->failures['juana@example.com'] = '550 Mailbox unavailable';

        $result = $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], false);
        $participant = $this->service()->overview($this->activityId)['participants'][0];

        self::assertSame(['failed', '550 Mailbox unavailable'], [$result['results'][0]['status'], $result['results'][0]['error']]);
        self::assertSame(['pending', 'failed'], [$participant['status'], $participant['last']['status']]);
    }

    public function testNoMessageForACallSignThatAskedForNone(): void
    {
        $this->store->book['LU9ZZA'] = new AddressBookEntry('LU9ZZA', null, null, true, 'Pidió no recibir correos');

        $result = $this->service()->send($this->admin, $this->activityId, ['LU9ZZA'], false);

        self::assertSame('no-mail', $result['results'][0]['reason']);
    }

    public function testTheTextOfTheActivityReplacesTheGeneralText(): void
    {
        $service = $this->service();
        $service->saveTemplate($this->admin, $this->activityId, 'Gracias {saludo}', 'Llovió, pero salimos. {qsos}');

        $service->send($this->admin, $this->activityId, ['LU9ZZA'], false);

        self::assertSame('Gracias Juana', $this->mailer->sent[0]->subject);
        self::assertTrue($service->overview($this->activityId)['template']['own']);
    }

    public function testRefusesAnUnknownVariable(): void
    {
        $this->assertStatus(422, fn () => $this->service()->saveTemplate($this->admin, $this->activityId, 'QSL {nombr}', 'Hola'));
    }

    public function testThePreviewShowsTheMessageOfAParticipant(): void
    {
        $preview = $this->service()->preview($this->activityId, 'lu9zza', 'QSL {indicativo}', 'Hola {saludo}, {cantidad} QSL. {nombr}');

        self::assertSame(['juana@example.com', 'QSL LU9ZZA', 'Hola Juana, 1 QSL. {nombr}'], [$preview['to'], $preview['subject'], $preview['text']]);
        self::assertSame(['nombr'], $preview['unknown']);
    }

    public function testTheTestMessageGoesToTheAdministrator(): void
    {
        $to = $this->service()->test($this->admin, $this->activityId, 'LU9ZZA', 'QSL {indicativo}', 'Hola {saludo}');

        self::assertSame('admin@example.com', $to);
        self::assertSame('admin@example.com', $this->mailer->sent[0]->to);
        self::assertSame([], $this->store->deliveries);
    }

    private function service(int $hourlyLimit = 90): QslMailService
    {
        $now = static fn (): DateTimeImmutable => new DateTimeImmutable('2026-10-05 12:30:00', new DateTimeZone('UTC'));

        return new QslMailService(
            $this->activities,
            $this->ranking,
            $this->qslTemplates,
            $this->cards,
            $this->store,
            $this->licensees,
            new Templates($this->store, $this->audit),
            new MailSender($this->store, $this->mailer, new SendingLimit($this->store, $hourlyLimit, $now)),
            $this->audit,
            'https://example.com/',
        );
    }

    private function contact(string $base, string $qsoAt, User $operator): ContactRow
    {
        return new ContactRow(
            id: $this->nextContact++,
            baseCallSign: $base,
            callSign: $base,
            name: null,
            qsoAt: $qsoAt,
            frequency: '7.130',
            band: '40m',
            mode: 'SSB',
            activityId: $this->activityId,
            startDate: '2026-10-04',
            season: 2026,
            referenceId: 1,
            seriesCode: 'DPS',
            referenceCode: 'DPS-05',
            referenceName: 'Parque Rivadavia',
            operatorCallSign: $operator->callSign,
            operatorId: $operator->id,
            rstSent: '59',
        );
    }

    private function image(): UploadedFile
    {
        $image = imagecreatetruecolor(800, 500);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 240, 255));
        ob_start();
        imagepng($image);
        $content = (string) ob_get_clean();
        $path = (string) tempnam(sys_get_temp_dir(), 'qsl');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile('qsl.png', $path, strlen($content));
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
