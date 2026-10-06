<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The limit of messages for each hour of the host (FR-MAIL-9). DonWeb accepts 100 messages for each hour and
 * mailbox: after the limit, the server refuses messages for an hour. The record of messages counts the messages.
 */
final class SendingLimit
{
    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly MailStore $store,
        private readonly int $hourlyLimit,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * Null if a message can go now. Otherwise the time when the next message can go, as YYYY-MM-DDTHH:MM:SSZ.
     */
    public function retryAt(): ?string
    {
        $now = ($this->now)();
        $window = $this->store->emailsSince($now->modify('-1 hour')->format('Y-m-d H:i:s'));
        if ($window['count'] < $this->hourlyLimit || $window['oldest'] === null) {
            return null;
        }
        // The oldest message of the hour leaves the window one hour after it went. One more minute is a margin.
        $retry = new DateTimeImmutable($window['oldest'], new DateTimeZone('UTC'));

        return $retry->modify('+61 minutes')->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return array{limit: int, used: int}
     */
    public function usage(): array
    {
        $now = ($this->now)();

        return [
            'limit' => $this->hourlyLimit,
            'used' => $this->store->emailsSince($now->modify('-1 hour')->format('Y-m-d H:i:s'))['count'],
        ];
    }
}
