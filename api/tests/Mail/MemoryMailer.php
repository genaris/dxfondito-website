<?php

declare(strict_types=1);

namespace DxFondito\Tests\Mail;

use DxFondito\Mail\Mailer;
use DxFondito\Mail\OutgoingMessage;
use RuntimeException;

final class MemoryMailer implements Mailer
{
    /** @var list<OutgoingMessage> */
    public array $sent = [];

    /** @var array<string, string> The error of the server for some addresses. */
    public array $failures = [];

    public function send(OutgoingMessage $message): void
    {
        if (isset($this->failures[$message->to])) {
            throw new RuntimeException($this->failures[$message->to]);
        }
        $this->sent[] = $message;
    }
}
