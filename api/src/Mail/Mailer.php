<?php

declare(strict_types=1);

namespace DxFondito\Mail;

interface Mailer
{
    /**
     * @throws \RuntimeException if the server does not accept the message. The text says the cause.
     */
    public function send(OutgoingMessage $message): void;
}
