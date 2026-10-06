<?php

declare(strict_types=1);

namespace DxFondito\Tests\Mail;

use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Mail\AddressBookService;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use DxFondito\Tests\Registry\MemoryLicenseeStore;
use PHPUnit\Framework\TestCase;

final class AddressBookServiceTest extends TestCase
{
    private MemoryMailStore $store;
    private MemoryAuditLog $audit;
    private AddressBookService $service;
    private User $admin;

    protected function setUp(): void
    {
        $users = new MemoryUserStore();
        $this->admin = $users->findById($users->create('LU1ADM', 'Admin', null, User::ADMINISTRATOR, 'hash', false));
        $this->store = new MemoryMailStore();
        $this->store->known = ['LU9ZZA' => [['email' => 'log@example.com', 'lastQsoAt' => '2026-10-04 12:00:00']]];
        $this->audit = new MemoryAuditLog();
        $licensees = new MemoryLicenseeStore();
        $licensees->replace('AR', ['LU9ZZA' => 'JUANA ISABEL EJEMPLO'], 'test');
        $this->service = new AddressBookService($this->store, $licensees, $this->audit);
    }

    public function testSavesAnEntryForTheBaseCallSign(): void
    {
        $this->service->save($this->admin, 'lu9zza/p', '', ' Juana@Example.com ', false, 'Por WhatsApp');

        $data = $this->service->show('LU9ZZA');

        self::assertSame('juana@example.com', $data['entry']['email']);
        self::assertSame(['juana@example.com', 'book', ['log@example.com']], [$data['recipient']['email'], $data['recipient']['source'], $data['recipient']['others']]);
        self::assertSame('Juana Isabel Ejemplo', $data['officialName']);
        self::assertSame(['address-book.save'], $this->audit->actions());
    }

    public function testAnEntryWithoutDataIsDeleted(): void
    {
        $this->service->save($this->admin, 'LU9ZZA', '', 'juana@example.com', false, '');

        $this->service->save($this->admin, 'LU9ZZA', '', '', false, '');

        self::assertSame([], $this->store->book);
    }

    public function testRefusesAnIncorrectAddress(): void
    {
        try {
            $this->service->save($this->admin, 'LU9ZZA', '', 'juana@', false, '');
            self::fail('The save did not fail.');
        } catch (HttpException $e) {
            self::assertSame(422, $e->status);
        }
    }

    public function testABouncedAddressIsMarked(): void
    {
        $this->service->setInvalid($this->admin, 'LOG@example.com', true);

        $data = $this->service->show('LU9ZZA');

        self::assertTrue($data['known'][0]['invalid']);
        self::assertNull($data['recipient']['email']);
    }
}
