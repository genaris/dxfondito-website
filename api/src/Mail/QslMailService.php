<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use DxFondito\Activities\Activity;
use DxFondito\Activities\ActivityStore;
use DxFondito\Audit\AuditLog;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Ranking\Calculator;
use DxFondito\Ranking\ContactRow;
use DxFondito\Ranking\RankingStore;
use DxFondito\Registry\LicenseeName;
use DxFondito\Registry\LicenseeStore;
use DxFondito\Templates\QslService;
use DxFondito\Templates\QslTemplateStore;

/**
 * The QSL messages of an activity (FR-MAIL-6 to FR-MAIL-11, FR-MAIL-14): one message for each participant, with
 * the QSL cards of all contacts of the participant in the activity. Only an administrator sends them.
 */
final class QslMailService
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    /** A message went, and the participant has QSL cards that were not in it: a log that came later. */
    public const NEW_CARDS = 'new';

    /** The participants of one request. A batch of the browser stays below the time limit of PHP. */
    private const MAX_BATCH = 10;

    public function __construct(
        private readonly ActivityStore $activities,
        private readonly RankingStore $ranking,
        private readonly QslTemplateStore $qslTemplates,
        private readonly QslService $cards,
        private readonly MailStore $store,
        private readonly LicenseeStore $licensees,
        private readonly Templates $templates,
        private readonly MailSender $sender,
        private readonly AuditLog $audit,
        private readonly string $siteUrl,
    ) {
    }

    /**
     * The message of the activity and the state of each participant.
     *
     * @return array<string, mixed>
     */
    public function overview(int $activityId): array
    {
        $activity = $this->activity($activityId);
        $participants = $this->participants($activity);

        return [
            'activity' => $activity->publicData(),
            'template' => $this->templates->get(MessageTemplate::QSL, Templates::activityScope($activityId)),
            'variables' => MessageTemplate::VARIABLES[MessageTemplate::QSL],
            'configured' => $this->sender->configured(),
            'usage' => $this->sender->usage(),
            'retryAt' => $this->sender->retryAt(),
            'participants' => array_map(static fn (array $item): array => self::participantData($item), array_values($participants)),
        ];
    }

    /**
     * The number of participants of an activity in each state, for the list of activities.
     *
     * @return array{participants: int, sent: int, pending: int}
     */
    public function counts(int $activityId): array
    {
        $participants = $this->participants($this->activity($activityId));
        $sent = count(array_filter($participants, static fn (array $item): bool => $item['status'] === self::SENT));

        return ['participants' => count($participants), 'sent' => $sent, 'pending' => count($participants) - $sent];
    }

    /**
     * @throws HttpException 422 for an incorrect text.
     */
    public function saveTemplate(User $actor, int $activityId, string $subject, string $body): void
    {
        $this->activity($activityId);
        $this->templates->save($actor, MessageTemplate::QSL, Templates::activityScope($activityId), $subject, $body);
    }

    public function resetTemplate(User $actor, int $activityId): void
    {
        $this->activity($activityId);
        $this->templates->reset($actor, MessageTemplate::QSL, Templates::activityScope($activityId));
    }

    /**
     * The message of a participant with a text that is not saved yet (FR-MAIL-11): the editor shows it.
     *
     * @return array<string, mixed>
     */
    public function preview(int $activityId, string $callSign, string $subject, string $body): array
    {
        $activity = $this->activity($activityId);
        $participant = $this->participant($activity, $callSign);
        $values = $this->values($activity, $participant, $participant['cards']);

        return [
            'to' => $participant['recipient']->email,
            'subject' => MessageTemplate::render($subject, $values),
            'text' => MessageTemplate::render($body, $values),
            'attachments' => array_map(fn (ContactRow $contact): string => $this->cardName($contact), $participant['cards']),
            'unknown' => MessageTemplate::unknownVariables(MessageTemplate::QSL, $subject . "\n" . $body),
        ];
    }

    /**
     * Sends the message of a participant to the address of the administrator (FR-MAIL-11). It is not in the record.
     *
     * @throws HttpException 422 without an address of the administrator or with an incorrect text.
     */
    public function test(User $actor, int $activityId, string $callSign, string $subject, string $body): string
    {
        if ($actor->email === null || $actor->email === '') {
            throw new HttpException(422, 'The account has no e-mail address');
        }
        Templates::check(MessageTemplate::QSL, $subject, $body);
        $activity = $this->activity($activityId);
        $participant = $this->participant($activity, $callSign);
        if ($participant['cards'] === []) {
            throw new HttpException(422, 'The participant has no QSL card');
        }
        $this->sender->sendTest($this->message($activity, $participant, $participant['cards'], $actor->email, $subject, $body));

        return $actor->email;
    }

    /**
     * Sends the messages of some participants with the saved text (FR-MAIL-6, FR-MAIL-9).
     * Without resend, a participant gets the QSL cards that no earlier message had. With resend, all QSL cards.
     * At the limit of the hour, the result has the time when the browser can continue.
     *
     * @param list<string> $callSigns
     * @return array{results: list<array<string, mixed>>, retryAt: string|null}
     */
    public function send(User $actor, int $activityId, array $callSigns, bool $resend): array
    {
        $this->sender->requireConfigured();
        $activity = $this->activity($activityId);
        $template = $this->templates->get(MessageTemplate::QSL, Templates::activityScope($activityId));
        $participants = $this->participants($activity);
        $results = [];
        $retryAt = null;
        $sent = 0;
        $failed = 0;

        foreach (array_slice(self::callSigns($callSigns), 0, self::MAX_BATCH) as $callSign) {
            $participant = $participants[$callSign] ?? null;
            $cards = $participant === null ? [] : ($resend ? $participant['cards'] : $participant['newCards']);
            $problem = match (true) {
                $participant === null => 'not-participant',
                $participant['recipient']->noMail => 'no-mail',
                $participant['recipient']->email === null => 'no-email',
                $participant['cards'] === [] => 'no-qsl',
                $cards === [] => 'already-sent',
                default => null,
            };
            if ($problem !== null) {
                $results[] = ['callSign' => $callSign, 'status' => 'skipped', 'reason' => $problem];
                continue;
            }
            $retryAt = $this->sender->retryAt();
            if ($retryAt !== null) {
                break;
            }
            $message = $this->message($activity, $participant, $cards, $participant['recipient']->email, $template['subject'], $template['body']);
            $delivery = $this->sender->deliver($message, new Delivery(
                kind: MessageTemplate::QSL,
                baseCallSign: $callSign,
                method: Delivery::EMAIL,
                status: Delivery::SENT,
                userId: $actor->id,
                activityId: $activityId,
                items: array_map(self::key(...), $cards),
            ));
            $delivery->succeeded() ? $sent++ : $failed++;
            $results[] = ['callSign' => $callSign, 'status' => $delivery->status, 'error' => $delivery->error];
        }

        if ($sent + $failed > 0) {
            $this->audit->record($actor->id, AuditLog::MAIL_SEND, $activityId, [
                'label' => $activity->label(),
                'kind' => MessageTemplate::QSL,
                'sent' => $sent,
                'failed' => $failed,
            ]);
        }

        return ['results' => $results, 'retryAt' => $retryAt];
    }

    /**
     * Marks the QSL cards of some participants as sent without a message: they went by hand before (FR-MAIL-14).
     *
     * @param list<string> $callSigns
     * @return int The number of marked participants.
     */
    public function mark(User $actor, int $activityId, array $callSigns): int
    {
        $activity = $this->activity($activityId);
        $participants = $this->participants($activity);
        $marked = 0;
        foreach (self::callSigns($callSigns) as $callSign) {
            $participant = $participants[$callSign] ?? null;
            if ($participant === null) {
                continue;
            }
            $this->store->record(new Delivery(
                kind: MessageTemplate::QSL,
                baseCallSign: $callSign,
                method: Delivery::MANUAL,
                status: Delivery::SENT,
                userId: $actor->id,
                activityId: $activityId,
                items: array_map(self::key(...), $participant['contacts']),
            ));
            $marked++;
        }
        if ($marked > 0) {
            $this->audit->record($actor->id, AuditLog::MAIL_MARK, $activityId, [
                'label' => $activity->label(),
                'kind' => MessageTemplate::QSL,
                'callSigns' => array_slice(self::callSigns($callSigns), 0, 50),
            ]);
        }

        return $marked;
    }

    /**
     * Deletes the manual marks of some participants. A sent message stays: it is a fact.
     *
     * @param list<string> $callSigns
     */
    public function unmark(User $actor, int $activityId, array $callSigns): int
    {
        $activity = $this->activity($activityId);
        $deleted = 0;
        foreach (self::callSigns($callSigns) as $callSign) {
            $deleted += $this->store->deleteManualMarks(MessageTemplate::QSL, $callSign, $activityId, null, null);
        }
        if ($deleted > 0) {
            $this->audit->record($actor->id, AuditLog::MAIL_UNMARK, $activityId, [
                'label' => $activity->label(),
                'kind' => MessageTemplate::QSL,
                'callSigns' => array_slice(self::callSigns($callSigns), 0, 50),
            ]);
        }

        return $deleted;
    }

    /**
     * The participants of the activity, by base call sign.
     *
     * @return array<string, array{callSign: string, name: string, recipient: Recipient, contacts: list<ContactRow>,
     *   cards: list<ContactRow>, newCards: list<ContactRow>, status: string, last: Delivery|null}>
     */
    private function participants(Activity $activity): array
    {
        $byCall = [];
        foreach (Calculator::activityContacts($this->ranking->byActivity($activity->id)) as $contact) {
            $byCall[$contact->baseCallSign][] = $contact;
        }
        ksort($byCall, SORT_STRING);
        $calls = array_map('strval', array_keys($byCall));
        // FR-QSL-9: a QSL card needs the template of the operator of the contact, for the activity.
        $withTemplate = array_flip($this->qslTemplates->keys([$activity->id]));
        $deliveries = [];
        foreach ($this->store->activityDeliveries($activity->id) as $delivery) {
            $deliveries[$delivery->baseCallSign][] = $delivery;
        }
        $entries = $this->store->entries($calls);
        $known = $this->store->knownEmails($calls);
        $invalid = $this->store->invalidEmails();
        $lastRecipients = $this->store->lastRecipients($calls);

        $participants = [];
        foreach ($byCall as $callSign => $contacts) {
            $callSign = (string) $callSign;
            $cards = array_values(array_filter(
                $contacts,
                static fn (ContactRow $contact): bool => isset($withTemplate[$contact->activityId . ':' . $contact->operatorId]),
            ));
            $covered = [];
            $succeeded = false;
            foreach ($deliveries[$callSign] ?? [] as $delivery) {
                if ($delivery->succeeded()) {
                    $succeeded = true;
                    $covered += array_flip($delivery->items);
                }
            }
            $newCards = array_values(array_filter($cards, static fn (ContactRow $contact): bool => !isset($covered[self::key($contact)])));
            $name = $this->licensees->name($callSign);
            $participants[$callSign] = [
                'callSign' => $callSign,
                'name' => $name === null ? '' : LicenseeName::format($name),
                'recipient' => Recipient::resolve($entries[$callSign] ?? null, $known[$callSign] ?? [], $invalid, $lastRecipients[$callSign] ?? null),
                'contacts' => $contacts,
                'cards' => $cards,
                'newCards' => $newCards,
                'status' => !$succeeded ? self::PENDING : ($newCards === [] ? self::SENT : self::NEW_CARDS),
                'last' => ($deliveries[$callSign] ?? []) === [] ? null : end($deliveries[$callSign]),
            ];
        }

        return $participants;
    }

    /**
     * @return array{callSign: string, name: string, recipient: Recipient, contacts: list<ContactRow>,
     *   cards: list<ContactRow>, newCards: list<ContactRow>, status: string, last: Delivery|null}
     */
    private function participant(Activity $activity, string $callSign): array
    {
        return $this->participants($activity)[strtoupper(trim($callSign))]
            ?? throw new HttpException(404, 'The call sign has no contact in the activity');
    }

    /**
     * @param array{callSign: string, name: string, recipient: Recipient, contacts: list<ContactRow>,
     *   cards: list<ContactRow>, newCards: list<ContactRow>, status: string, last: Delivery|null} $item
     * @return array<string, mixed>
     */
    private static function participantData(array $item): array
    {
        return [
            'callSign' => $item['callSign'],
            'name' => $item['name'],
            'recipient' => $item['recipient']->data(),
            'contacts' => count($item['contacts']),
            'cards' => count($item['cards']),
            'newCards' => count($item['newCards']),
            'status' => $item['status'],
            'last' => $item['last']?->data(),
        ];
    }

    /**
     * @param array{callSign: string, name: string, recipient: Recipient, contacts: list<ContactRow>,
     *   cards: list<ContactRow>, newCards: list<ContactRow>, status: string, last: Delivery|null} $participant
     * @param list<ContactRow> $cards
     */
    private function message(Activity $activity, array $participant, array $cards, string $to, string $subject, string $body): OutgoingMessage
    {
        $values = $this->values($activity, $participant, $cards);
        $attachments = [];
        foreach ($cards as $contact) {
            $card = $this->cards->card($participant['callSign'], $contact->id);
            $attachments[] = ['name' => $card['name'], 'content' => $card['content'], 'type' => 'image/jpeg'];
        }

        return new OutgoingMessage(
            to: $to,
            subject: MessageTemplate::render($subject, $values),
            text: MessageTemplate::render($body, $values),
            attachments: $attachments,
        );
    }

    /**
     * The values of the variables of a message (FR-MAIL-10).
     *
     * @param array{callSign: string, name: string, recipient: Recipient, contacts: list<ContactRow>,
     *   cards: list<ContactRow>, newCards: list<ContactRow>, status: string, last: Delivery|null} $participant
     * @param list<ContactRow> $cards
     * @return array<string, string>
     */
    private function values(Activity $activity, array $participant, array $cards): array
    {
        $operators = array_values(array_unique(array_map(static fn (ContactRow $contact): string => $contact->operatorCallSign, $cards)));
        $points = count(array_filter(
            Calculator::firstContacts($this->ranking->byParticipant($participant['callSign'])),
            static fn (ContactRow $contact): bool => $contact->season === $activity->season,
        ));

        return [
            'indicativo' => $participant['callSign'],
            'nombre' => $participant['name'],
            'saludo' => MessageTemplate::greeting($participant['name'], $participant['callSign']),
            'referencia' => $activity->reference->referenceCode(),
            'actividad' => $activity->reference->name,
            'fecha' => SpanishDate::range($activity->startDate, $activity->endDate),
            'operadores' => self::listText($operators),
            'temporada' => (string) $activity->season,
            'puntos' => (string) $points,
            'cantidad' => (string) count($cards),
            'qsos' => implode("\n", array_map(self::qsoLine(...), $cards)),
            'sitio' => $this->siteUrl,
        ];
    }

    /**
     * One line of the {qsos} variable, such as "- 04/10/2026 14:30 UTC · 7.130 MHz · SSB · con LU2AOG/A".
     */
    private static function qsoLine(ContactRow $contact): string
    {
        $date = substr($contact->qsoAt, 8, 2) . '/' . substr($contact->qsoAt, 5, 2) . '/' . substr($contact->qsoAt, 0, 4);
        $frequency = $contact->frequency !== null ? $contact->frequency . ' MHz' : (string) $contact->band;

        return sprintf('- %s %s UTC · %s · %s · con %s', $date, substr($contact->qsoAt, 11, 5), $frequency, $contact->mode, $contact->operatorCallSign);
    }

    /**
     * "A", "A y B", "A, B y C".
     *
     * @param list<string> $items
     */
    private static function listText(array $items): string
    {
        if (count($items) < 2) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' y ' . $last;
    }

    private function cardName(ContactRow $contact): string
    {
        return sprintf(
            'QSL_%s_%s_%s_%s.jpg',
            $contact->baseCallSign,
            $contact->referenceCode,
            str_replace('-', '', substr($contact->qsoAt, 0, 10)),
            str_replace(':', '', substr($contact->qsoAt, 11, 5)),
        );
    }

    /**
     * The key of a contact in the record of messages. It stays valid when an operator uploads the same log again.
     */
    private static function key(ContactRow $contact): string
    {
        return $contact->operatorId . '|' . $contact->qsoAt;
    }

    /**
     * @param list<mixed> $callSigns
     * @return list<string>
     */
    private static function callSigns(array $callSigns): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => is_string($value) ? strtoupper(trim($value)) : '', $callSigns),
            static fn (string $value): bool => $value !== '',
        )));
    }

    private function activity(int $id): Activity
    {
        return $this->activities->find($id) ?? throw new HttpException(404, 'The activity does not exist');
    }
}
