<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use DxFondito\Http\HttpException;
use RuntimeException;

/**
 * Sends a message, records it, and keeps the limit of each hour (FR-MAIL-7, FR-MAIL-9).
 */
final class MailSender
{
    public function __construct(
        private readonly MailStore $store,
        private readonly ?Mailer $mailer,
        private readonly ?SendingLimit $limit,
    ) {
    }

    public function configured(): bool
    {
        return $this->mailer !== null;
    }

    /**
     * @throws HttpException 503 without an SMTP server in config.php.
     */
    public function requireConfigured(): Mailer
    {
        return $this->mailer ?? throw new HttpException(503, 'The mailer has no SMTP server in config.php');
    }

    public function retryAt(): ?string
    {
        return $this->limit?->retryAt();
    }

    /**
     * @return array{limit: int, used: int}|null
     */
    public function usage(): ?array
    {
        return $this->limit?->usage();
    }

    /**
     * Sends a message and records it as sent or failed. A failed message is not an exception: the result says it.
     *
     * @param Delivery $delivery The record of the message, without method and status.
     */
    public function deliver(OutgoingMessage $message, Delivery $delivery): Delivery
    {
        $mailer = $this->requireConfigured();
        $error = null;
        try {
            $mailer->send($message);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
        $result = new Delivery(
            kind: $delivery->kind,
            baseCallSign: $delivery->baseCallSign,
            method: Delivery::EMAIL,
            status: $error === null ? Delivery::SENT : Delivery::FAILED,
            userId: $delivery->userId,
            activityId: $delivery->activityId,
            season: $delivery->season,
            points: $delivery->points,
            certificateDate: $delivery->certificateDate,
            recipient: $message->to,
            subject: $message->subject,
            items: $delivery->items,
            error: $error,
        );
        $this->store->record($result);

        return $result;
    }

    /**
     * Sends a test message: not in the record, and not for a participant.
     *
     * @throws HttpException 502 if the server does not accept the message.
     */
    public function sendTest(OutgoingMessage $message): void
    {
        try {
            $this->requireConfigured()->send($message);
        } catch (RuntimeException $e) {
            throw new HttpException(502, 'The SMTP server did not accept the message: ' . $e->getMessage());
        }
    }
}
