<?php

declare(strict_types=1);

namespace DxFondito\Tests\Mail;

use DxFondito\Mail\AddressBookEntry;
use DxFondito\Mail\MessageTemplate;
use DxFondito\Mail\Recipient;
use DxFondito\Mail\SpanishDate;
use PHPUnit\Framework\TestCase;

final class MessageTemplateTest extends TestCase
{
    public function testWritesTheValuesOfTheVariables(): void
    {
        self::assertSame(
            'Hola Juana! QSL DPS-05',
            MessageTemplate::render('Hola {saludo}! QSL {referencia}', ['saludo' => 'Juana', 'referencia' => 'DPS-05']),
        );
    }

    public function testFindsTheUnknownVariables(): void
    {
        self::assertSame(['nombr', 'fecha_certificado'], MessageTemplate::unknownVariables('qsl', 'Hola {nombr} {saludo} {fecha_certificado} {nombr}'));
        self::assertSame([], MessageTemplate::unknownVariables('certificate', '{nivel} {fecha_certificado}'));
    }

    public function testTheDefaultTextsHaveOnlyKnownVariables(): void
    {
        foreach (MessageTemplate::DEFAULTS as $kind => $text) {
            self::assertSame([], MessageTemplate::unknownVariables($kind, $text['subject'] . $text['body']), $kind);
        }
    }

    public function testTheHtmlVersionHasParagraphsAndLinks(): void
    {
        $html = MessageTemplate::html("Hola <Juana>!\n\nVer https://example.com/#/ranking.\nGracias");

        self::assertStringContainsString('<p>Hola &lt;Juana&gt;!</p>', $html);
        self::assertStringContainsString('<a href="https://example.com/#/ranking">https://example.com/#/ranking</a>.<br>', $html);
    }

    public function testTheGreetingWithoutANameIsTheCallSign(): void
    {
        self::assertSame('Juana', MessageTemplate::greeting('Juana Isabel Ejemplo', 'LU9ZZA'));
        self::assertSame('PY9ZZ', MessageTemplate::greeting('', 'PY9ZZ'));
    }

    public function testDatesInSpanish(): void
    {
        self::assertSame('domingo 4 de octubre de 2026', SpanishDate::withWeekday('2026-10-04'));
        self::assertSame('del viernes 2 de octubre de 2026 al sábado 3 de octubre de 2026', SpanishDate::range('2026-10-02', '2026-10-03'));
    }

    public function testTheAddressOfTheBookComesFirst(): void
    {
        $known = [['email' => 'new@example.com', 'lastQsoAt' => '2026-10-04 12:00:00'], ['email' => 'old@example.com', 'lastQsoAt' => '2026-08-01 12:00:00']];

        $fromLogs = Recipient::resolve(null, $known, [], null);
        $fromBook = Recipient::resolve(new AddressBookEntry('LU9ZZA', null, 'book@example.com', false, null), $known, [], null);

        self::assertSame(['new@example.com', 'adif', ['old@example.com']], [$fromLogs->email, $fromLogs->source, $fromLogs->others]);
        self::assertSame(['book@example.com', 'book'], [$fromBook->email, $fromBook->source]);
    }

    public function testABouncedAddressIsNotUsed(): void
    {
        $known = [['email' => 'new@example.com', 'lastQsoAt' => '2026-10-04 12:00:00'], ['email' => 'old@example.com', 'lastQsoAt' => '2026-08-01 12:00:00']];

        $recipient = Recipient::resolve(new AddressBookEntry('LU9ZZA', null, 'new@example.com', false, null), $known, ['new@example.com' => true], null);

        self::assertSame(['old@example.com', 'adif'], [$recipient->email, $recipient->source]);
    }

    public function testShowsAChangeOfTheAddressSinceTheLastMessage(): void
    {
        $known = [['email' => 'new@example.com', 'lastQsoAt' => '2026-10-04 12:00:00']];

        self::assertSame('old@example.com', Recipient::resolve(null, $known, [], 'old@example.com')->changedFrom);
        self::assertNull(Recipient::resolve(null, $known, [], 'new@example.com')->changedFrom);
    }
}
