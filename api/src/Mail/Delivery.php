<?php

declare(strict_types=1);

namespace DxFondito\Mail;

/**
 * One message of the record of messages, or one manual mark (FR-MAIL-7, FR-MAIL-14).
 */
final class Delivery
{
    public const EMAIL = 'email';
    public const MANUAL = 'manual';
    public const SENT = 'sent';
    public const FAILED = 'failed';

    /**
     * @param list<string> $items For a QSL message, the contacts of its QSL cards as "operatorId|qsoAt" keys.
     *   The keys stay valid when an operator uploads the same log again.
     * @param string|null $certificateDate YYYY-MM-DD: the date of the certificate at the time of the message.
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $baseCallSign,
        public readonly string $method,
        public readonly string $status,
        public readonly int $userId,
        public readonly ?int $activityId = null,
        public readonly ?int $season = null,
        public readonly ?int $points = null,
        public readonly ?string $certificateDate = null,
        public readonly ?string $recipient = null,
        public readonly ?string $subject = null,
        public readonly array $items = [],
        public readonly ?string $error = null,
        public readonly string $createdAt = '',
        public readonly int $id = 0,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->status === self::SENT;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'method' => $this->method,
            'status' => $this->status,
            'recipient' => $this->recipient,
            'certificateDate' => $this->certificateDate,
            'error' => $this->error,
            'at' => $this->createdAt === '' ? null : str_replace(' ', 'T', $this->createdAt) . 'Z',
        ];
    }
}
