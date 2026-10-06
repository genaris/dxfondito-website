<?php

declare(strict_types=1);

namespace DxFondito\Tests\Mail;

use DxFondito\Mail\AddressBookEntry;
use DxFondito\Mail\Delivery;
use DxFondito\Mail\MailStore;

final class MemoryMailStore implements MailStore
{
    /** @var array<string, list<array{email: string, lastQsoAt: string}>> */
    public array $known = [];

    /** @var array<string, true> */
    public array $invalid = [];

    /** @var array<string, AddressBookEntry> */
    public array $book = [];

    /** @var array<string, array{subject: string, body: string}> */
    public array $templates = [];

    /** @var list<Delivery> */
    public array $deliveries = [];

    /** The time of the next recorded message: the tests set it. */
    public string $now = '2026-10-05 12:00:00';

    public function knownEmails(array $callSigns): array
    {
        return array_intersect_key($this->known, array_flip($callSigns));
    }

    public function invalidEmails(): array
    {
        return $this->invalid;
    }

    public function setInvalid(string $email, bool $invalid, int $userId): void
    {
        if ($invalid) {
            $this->invalid[$email] = true;
        } else {
            unset($this->invalid[$email]);
        }
    }

    public function addressBook(): array
    {
        ksort($this->book);

        return array_values($this->book);
    }

    public function entries(array $callSigns): array
    {
        return array_intersect_key($this->book, array_flip($callSigns));
    }

    public function saveEntry(AddressBookEntry $entry, int $userId): void
    {
        $this->book[$entry->callSign] = $entry;
    }

    public function deleteEntry(string $callSign): void
    {
        unset($this->book[$callSign]);
    }

    public function template(string $kind, string $scope): ?array
    {
        return $this->templates[$kind . '|' . $scope] ?? null;
    }

    public function saveTemplate(string $kind, string $scope, string $subject, string $body, int $userId): void
    {
        $this->templates[$kind . '|' . $scope] = ['subject' => $subject, 'body' => $body];
    }

    public function deleteTemplate(string $kind, string $scope): void
    {
        unset($this->templates[$kind . '|' . $scope]);
    }

    public function activityDeliveries(int $activityId): array
    {
        return array_values(array_filter($this->deliveries, static fn (Delivery $item): bool => $item->kind === 'qsl' && $item->activityId === $activityId));
    }

    public function certificateDeliveries(int $season): array
    {
        return array_values(array_filter($this->deliveries, static fn (Delivery $item): bool => $item->kind === 'certificate' && $item->season === $season));
    }

    public function record(Delivery $delivery): void
    {
        $this->deliveries[] = new Delivery(
            kind: $delivery->kind,
            baseCallSign: $delivery->baseCallSign,
            method: $delivery->method,
            status: $delivery->status,
            userId: $delivery->userId,
            activityId: $delivery->activityId,
            season: $delivery->season,
            points: $delivery->points,
            certificateDate: $delivery->certificateDate,
            recipient: $delivery->recipient,
            subject: $delivery->subject,
            items: $delivery->items,
            error: $delivery->error,
            createdAt: $this->now,
            id: count($this->deliveries) + 1,
        );
    }

    public function deleteManualMarks(string $kind, string $callSign, ?int $activityId, ?int $season, ?int $points): int
    {
        $before = count($this->deliveries);
        $this->deliveries = array_values(array_filter(
            $this->deliveries,
            static fn (Delivery $item): bool => !($item->method === 'manual' && $item->kind === $kind && $item->baseCallSign === $callSign
                && $item->activityId === $activityId && $item->season === $season && $item->points === $points),
        ));

        return $before - count($this->deliveries);
    }

    public function emailsSince(string $since): array
    {
        $times = array_map(
            static fn (Delivery $item): string => $item->createdAt,
            array_filter($this->deliveries, static fn (Delivery $item): bool => $item->method === 'email' && $item->createdAt > $since),
        );
        sort($times);

        return ['count' => count($times), 'oldest' => $times[0] ?? null];
    }

    public function lastRecipients(array $callSigns): array
    {
        $last = [];
        foreach ($this->deliveries as $item) {
            if ($item->method === 'email' && $item->status === 'sent' && in_array($item->baseCallSign, $callSigns, true)) {
                $last[$item->baseCallSign] = (string) $item->recipient;
            }
        }

        return $last;
    }
}
