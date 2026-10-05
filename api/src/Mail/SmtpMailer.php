<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use DxFondito\MailConfig;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Sends the messages through the authenticated SMTP server of the host (system design, section 6.5).
 * DonWeb recommends port 465 with SSL. The sender is the mailbox of the group.
 */
final class SmtpMailer implements Mailer
{
    private const TIMEOUT = 20;

    public function __construct(private readonly MailConfig $config)
    {
    }

    public function send(OutgoingMessage $message): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config->host;
            $mail->Port = $this->config->port;
            $mail->Timeout = self::TIMEOUT;
            $mail->SMTPAuth = $this->config->username !== '';
            $mail->Username = $this->config->username;
            $mail->Password = $this->config->password;
            $mail->SMTPSecure = match ($this->config->encryption) {
                'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                'tls' => PHPMailer::ENCRYPTION_STARTTLS,
                default => '',
            };
            // Without encryption (the local test server), PHPMailer must not try STARTTLS by itself.
            $mail->SMTPAutoTLS = $this->config->encryption !== '';
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($this->config->fromAddress, $this->config->fromName);
            $mail->addAddress($message->to);
            $mail->Subject = $message->subject;
            $mail->isHTML(true);
            $mail->Body = MessageTemplate::html($message->text);
            $mail->AltBody = $message->text;
            foreach ($message->attachments as $attachment) {
                $mail->addStringAttachment($attachment['content'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['type']);
            }
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(), previous: $e);
        }
    }
}
