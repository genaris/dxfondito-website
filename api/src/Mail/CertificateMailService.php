<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use DxFondito\Audit\AuditLog;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Ranking\Calculator;
use DxFondito\Ranking\ContactRow;
use DxFondito\Ranking\RankingStore;
use DxFondito\Registry\LicenseeName;
use DxFondito\Registry\LicenseeStore;
use DxFondito\Templates\Certificate;
use DxFondito\Templates\CertificateService;
use DxFondito\Templates\CertificateTemplateStore;

/**
 * The certificate messages of a season (FR-MAIL-12 to FR-MAIL-14): one message for each certificate.
 */
final class CertificateMailService
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    /** A message went, and the date of the certificate is different now: a log that came later or a deleted log. */
    public const CHANGED = 'changed';
    /** A message went, and the participant does not have the certificate now: a deleted log (R-CER-4). */
    public const REVOKED = 'revoked';
    /** The participant has the certificate, and the level has no template in the season (FR-CER-7). */
    public const UNAVAILABLE = 'unavailable';

    private const MAX_BATCH = 10;

    public function __construct(
        private readonly RankingStore $ranking,
        private readonly CertificateTemplateStore $certificateTemplates,
        private readonly CertificateService $certificates,
        private readonly MailStore $store,
        private readonly LicenseeStore $licensees,
        private readonly Templates $templates,
        private readonly MailSender $sender,
        private readonly AuditLog $audit,
        private readonly string $siteUrl,
    ) {
    }

    /**
     * The message of the season and the state of each certificate.
     *
     * @return array<string, mixed>
     */
    public function overview(int $season): array
    {
        return [
            'season' => $season,
            'template' => $this->templates->get(MessageTemplate::CERTIFICATE, Templates::seasonScope($season)),
            'variables' => MessageTemplate::VARIABLES[MessageTemplate::CERTIFICATE],
            'configured' => $this->sender->configured(),
            'usage' => $this->sender->usage(),
            'retryAt' => $this->sender->retryAt(),
            'certificates' => array_map(static fn (array $item): array => self::itemData($item), array_values($this->items($season))),
        ];
    }

    /**
     * The certificates that wait for a message, and the certificates that changed after their message (FR-MAIL-13).
     *
     * @param list<int> $seasons
     * @return array{pending: int, changed: int}
     */
    public function summary(array $seasons): array
    {
        $pending = 0;
        $changed = 0;
        foreach ($seasons as $season) {
            foreach ($this->items($season) as $item) {
                $pending += $item['status'] === self::PENDING ? 1 : 0;
                $changed += $item['status'] === self::CHANGED ? 1 : 0;
            }
        }

        return ['pending' => $pending, 'changed' => $changed];
    }

    public function saveTemplate(User $actor, int $season, string $subject, string $body): void
    {
        $this->templates->save($actor, MessageTemplate::CERTIFICATE, Templates::seasonScope($season), $subject, $body);
    }

    public function resetTemplate(User $actor, int $season): void
    {
        $this->templates->reset($actor, MessageTemplate::CERTIFICATE, Templates::seasonScope($season));
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(int $season, string $callSign, int $points, string $subject, string $body): array
    {
        $item = $this->item($season, $callSign, $points);
        $values = $this->values($season, $item);

        return [
            'to' => $item['recipient']->email,
            'subject' => MessageTemplate::render($subject, $values),
            'text' => MessageTemplate::render($body, $values),
            'attachments' => $item['date'] === null ? [] : [sprintf('Certificado_%s_%d_%d.pdf', $item['callSign'], $season, $item['points'])],
            'unknown' => MessageTemplate::unknownVariables(MessageTemplate::CERTIFICATE, $subject . "\n" . $body),
        ];
    }

    /**
     * @throws HttpException 422 without an address of the administrator, or for a certificate that cannot go.
     */
    public function test(User $actor, int $season, string $callSign, int $points, string $subject, string $body): string
    {
        if ($actor->email === null || $actor->email === '') {
            throw new HttpException(422, 'The account has no e-mail address');
        }
        Templates::check(MessageTemplate::CERTIFICATE, $subject, $body);
        $item = $this->item($season, $callSign, $points);
        if ($item['date'] === null || !$item['available']) {
            throw new HttpException(422, 'The certificate is not available');
        }
        $this->sender->sendTest($this->message($season, $item, $actor->email, $subject, $body));

        return $actor->email;
    }

    /**
     * Sends some certificates with the saved text. A certificate that went before needs $again: the administrator
     * saw the warning and decided to send it again (FR-MAIL-13).
     *
     * @param list<array{callSign?: mixed, points?: mixed}> $requested
     * @return array{results: list<array<string, mixed>>, retryAt: string|null}
     */
    public function send(User $actor, int $season, array $requested, bool $again): array
    {
        $this->sender->requireConfigured();
        $template = $this->templates->get(MessageTemplate::CERTIFICATE, Templates::seasonScope($season));
        $items = $this->items($season);
        $results = [];
        $retryAt = null;
        $sent = 0;
        $failed = 0;

        foreach (array_slice(self::keys($requested), 0, self::MAX_BATCH) as $key) {
            $item = $items[$key] ?? null;
            $problem = match (true) {
                $item === null, $item['date'] === null => 'not-reached',
                !$item['available'] => 'no-template',
                $item['recipient']->noMail => 'no-mail',
                $item['recipient']->email === null => 'no-email',
                $item['last'] !== null && !$again => 'already-sent',
                default => null,
            };
            if ($problem !== null) {
                $results[] = ['key' => $key, 'status' => 'skipped', 'reason' => $problem];
                continue;
            }
            $retryAt = $this->sender->retryAt();
            if ($retryAt !== null) {
                break;
            }
            $message = $this->message($season, $item, $item['recipient']->email, $template['subject'], $template['body']);
            $delivery = $this->sender->deliver($message, new Delivery(
                kind: MessageTemplate::CERTIFICATE,
                baseCallSign: $item['callSign'],
                method: Delivery::EMAIL,
                status: Delivery::SENT,
                userId: $actor->id,
                season: $season,
                points: $item['points'],
                certificateDate: $item['date'],
            ));
            $delivery->succeeded() ? $sent++ : $failed++;
            $results[] = ['key' => $key, 'status' => $delivery->status, 'error' => $delivery->error];
        }

        if ($sent + $failed > 0) {
            $this->audit->record($actor->id, AuditLog::MAIL_SEND, null, [
                'kind' => MessageTemplate::CERTIFICATE,
                'season' => $season,
                'sent' => $sent,
                'failed' => $failed,
            ]);
        }

        return ['results' => $results, 'retryAt' => $retryAt];
    }

    /**
     * Marks some certificates as sent without a message (FR-MAIL-14). The mark keeps the date of the certificate.
     *
     * @param list<array{callSign?: mixed, points?: mixed}> $requested
     */
    public function mark(User $actor, int $season, array $requested): int
    {
        $items = $this->items($season);
        $marked = [];
        foreach (self::keys($requested) as $key) {
            $item = $items[$key] ?? null;
            if ($item === null || $item['date'] === null) {
                continue;
            }
            $this->store->record(new Delivery(
                kind: MessageTemplate::CERTIFICATE,
                baseCallSign: $item['callSign'],
                method: Delivery::MANUAL,
                status: Delivery::SENT,
                userId: $actor->id,
                season: $season,
                points: $item['points'],
                certificateDate: $item['date'],
            ));
            $marked[] = $key;
        }
        if ($marked !== []) {
            $this->audit->record($actor->id, AuditLog::MAIL_MARK, null, [
                'kind' => MessageTemplate::CERTIFICATE,
                'season' => $season,
                'certificates' => array_slice($marked, 0, 50),
            ]);
        }

        return count($marked);
    }

    /**
     * @param list<array{callSign?: mixed, points?: mixed}> $requested
     */
    public function unmark(User $actor, int $season, array $requested): int
    {
        $deleted = 0;
        $keys = self::keys($requested);
        foreach ($keys as $key) {
            [$callSign, $points] = explode(':', $key);
            $deleted += $this->store->deleteManualMarks(MessageTemplate::CERTIFICATE, $callSign, null, $season, (int) $points);
        }
        if ($deleted > 0) {
            $this->audit->record($actor->id, AuditLog::MAIL_UNMARK, null, [
                'kind' => MessageTemplate::CERTIFICATE,
                'season' => $season,
                'certificates' => array_slice($keys, 0, 50),
            ]);
        }

        return $deleted;
    }

    /**
     * Each certificate of the season that a participant has now or that went before, by "CALL:points".
     *
     * @return array<string, array{callSign: string, points: int, date: string|null, available: bool, name: string,
     *   recipient: Recipient, status: string, last: Delivery|null, firstContacts: int}>
     */
    private function items(int $season): array
    {
        $levels = $this->ranking->levels();
        $withTemplate = array_flip($this->certificateTemplates->levels($season));
        $byCall = [];
        foreach (Calculator::firstContacts($this->ranking->bySeason($season)) as $contact) {
            $byCall[$contact->baseCallSign][] = $contact;
        }
        $reached = [];
        foreach ($byCall as $callSign => $contacts) {
            foreach (Calculator::certificates($contacts, $levels) as $certificate) {
                $reached[$callSign . ':' . $certificate['points']] = $certificate['date'];
            }
        }
        // The last successful message or mark of each certificate.
        $last = [];
        foreach ($this->store->certificateDeliveries($season) as $delivery) {
            if ($delivery->succeeded()) {
                $last[$delivery->baseCallSign . ':' . $delivery->points] = $delivery;
            }
        }

        $keys = array_unique([...array_keys($reached), ...array_keys($last)]);
        $calls = array_values(array_unique(array_map(static fn (string $key): string => explode(':', $key)[0], $keys)));
        $entries = $this->store->entries($calls);
        $known = $this->store->knownEmails($calls);
        $invalid = $this->store->invalidEmails();
        $lastRecipients = $this->store->lastRecipients($calls);

        $items = [];
        foreach ($keys as $key) {
            [$callSign, $points] = explode(':', $key);
            $date = $reached[$key] ?? null;
            $delivery = $last[$key] ?? null;
            $available = isset($withTemplate[(int) $points]);
            $status = match (true) {
                $date === null => self::REVOKED,
                $delivery === null => $available ? self::PENDING : self::UNAVAILABLE,
                $delivery->certificateDate !== $date => self::CHANGED,
                default => self::SENT,
            };
            $name = $this->licensees->name($callSign);
            $items[$key] = [
                'callSign' => $callSign,
                'points' => (int) $points,
                'date' => $date,
                'available' => $available,
                'name' => $name === null ? '' : LicenseeName::format($name),
                'recipient' => Recipient::resolve($entries[$callSign] ?? null, $known[$callSign] ?? [], $invalid, $lastRecipients[$callSign] ?? null),
                'status' => $status,
                'last' => $delivery,
                'firstContacts' => count($byCall[$callSign] ?? []),
            ];
        }
        uasort($items, static fn (array $a, array $b): int => [$a['callSign'], $a['points']] <=> [$b['callSign'], $b['points']]);

        return $items;
    }

    /**
     * @return array{callSign: string, points: int, date: string|null, available: bool, name: string,
     *   recipient: Recipient, status: string, last: Delivery|null, firstContacts: int}
     */
    private function item(int $season, string $callSign, int $points): array
    {
        return $this->items($season)[strtoupper(trim($callSign)) . ':' . $points]
            ?? throw new HttpException(404, 'The participant does not have the certificate');
    }

    /**
     * @param array{callSign: string, points: int, date: string|null, available: bool, name: string,
     *   recipient: Recipient, status: string, last: Delivery|null, firstContacts: int} $item
     * @return array<string, mixed>
     */
    private static function itemData(array $item): array
    {
        return [
            'callSign' => $item['callSign'],
            'points' => $item['points'],
            'level' => Certificate::levelName($item['points']),
            'date' => $item['date'],
            'available' => $item['available'],
            'name' => $item['name'],
            'recipient' => $item['recipient']->data(),
            'status' => $item['status'],
            'last' => $item['last']?->data(),
        ];
    }

    /**
     * @param array{callSign: string, points: int, date: string|null, available: bool, name: string,
     *   recipient: Recipient, status: string, last: Delivery|null, firstContacts: int} $item
     */
    private function message(int $season, array $item, string $to, string $subject, string $body): OutgoingMessage
    {
        $values = $this->values($season, $item);
        $certificate = $this->certificates->certificate($item['callSign'], $season, $item['points']);

        return new OutgoingMessage(
            to: $to,
            subject: MessageTemplate::render($subject, $values),
            text: MessageTemplate::render($body, $values),
            attachments: [['name' => $certificate['name'], 'content' => $certificate['content'], 'type' => 'application/pdf']],
        );
    }

    /**
     * @param array{callSign: string, points: int, date: string|null, available: bool, name: string,
     *   recipient: Recipient, status: string, last: Delivery|null, firstContacts: int} $item
     * @return array<string, string>
     */
    private function values(int $season, array $item): array
    {
        return [
            'indicativo' => $item['callSign'],
            'nombre' => $item['name'],
            'saludo' => MessageTemplate::greeting($item['name'], $item['callSign']),
            'nivel' => Certificate::levelName($item['points']),
            'referencias' => (string) $item['points'],
            'fecha_certificado' => $item['date'] === null ? '' : SpanishDate::long($item['date']),
            'temporada' => (string) $season,
            'puntos' => (string) $item['firstContacts'],
            'sitio' => $this->siteUrl,
        ];
    }

    /**
     * @param list<mixed> $requested
     * @return list<string> "CALL:points" keys.
     */
    private static function keys(array $requested): array
    {
        $keys = [];
        foreach ($requested as $item) {
            if (!is_array($item) || !is_string($item['callSign'] ?? null) || !is_int($item['points'] ?? null)) {
                continue;
            }
            $keys[] = strtoupper(trim($item['callSign'])) . ':' . $item['points'];
        }

        return array_values(array_unique($keys));
    }
}
