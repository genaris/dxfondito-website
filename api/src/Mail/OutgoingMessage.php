<?php

declare(strict_types=1);

namespace DxFondito\Mail;

/**
 * A message to send: plain text, its HTML version, and attachments.
 */
final class OutgoingMessage
{
    /**
     * @param list<array{name: string, content: string, type: string}> $attachments
     */
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $text,
        public readonly array $attachments = [],
    ) {
    }
}
