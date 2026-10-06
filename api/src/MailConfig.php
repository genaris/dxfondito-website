<?php

declare(strict_types=1);

namespace DxFondito;

use RuntimeException;

/**
 * The SMTP server of the QSL mailer (system design, section 6.5). The password stays in config.php.
 */
final class MailConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        /** "ssl" (port 465), "tls" (STARTTLS, port 587) or "" (no encryption, for the local test server). */
        public readonly string $encryption,
        public readonly string $username,
        public readonly string $password,
        public readonly string $fromAddress,
        public readonly string $fromName,
        /** The messages for each hour. DonWeb accepts 100 for each mailbox: the default leaves a margin. */
        public readonly int $hourlyLimit,
        /** The address of the site, for the {sitio} variable of the messages. */
        public readonly string $siteUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $values
     * @return self|null Null without a host: the mailer is off.
     */
    public static function fromArray(array $values): ?self
    {
        $host = (string) ($values['host'] ?? '');
        if ($host === '') {
            return null;
        }
        $encryption = (string) ($values['encryption'] ?? '');
        if (!in_array($encryption, ['ssl', 'tls', ''], true)) {
            throw new RuntimeException('mail.encryption must be ssl, tls or empty');
        }
        $from = (string) ($values['from_address'] ?? '');
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('The configuration has no valid value for mail.from_address');
        }

        return new self(
            host: $host,
            port: (int) ($values['port'] ?? 465),
            encryption: $encryption,
            username: (string) ($values['username'] ?? ''),
            password: (string) ($values['password'] ?? ''),
            fromAddress: $from,
            fromName: (string) ($values['from_name'] ?? 'Grupo DX Fondito'),
            hourlyLimit: max(1, (int) ($values['hourly_limit'] ?? 90)),
            siteUrl: (string) ($values['site_url'] ?? ''),
        );
    }
}
