<?php

declare(strict_types=1);

namespace DxFondito\Tests\Registry;

use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Registry\RegistryService;
use DxFondito\Tests\Audit\MemoryAuditLog;
use DxFondito\Tests\Auth\MemoryUserStore;
use PHPUnit\Framework\TestCase;

final class RegistryServiceTest extends TestCase
{
    private MemoryLicenseeStore $licensees;
    private MemoryAuditLog $audit;
    private User $admin;

    protected function setUp(): void
    {
        $users = new MemoryUserStore();
        $this->admin = $users->findById($users->create('LU1ADM', 'Admin', null, User::ADMINISTRATOR, 'hash', false));
        $this->licensees = new MemoryLicenseeStore();
        $this->audit = new MemoryAuditLog();
    }

    public function testUpdatesTheListOfArgentina(): void
    {
        $http = new FakeHttpClient([
            RegistryService::ENACOM_URL => ['<form><input type="hidden" name="csrf_token" value="abc"></form>', self::enacomPage(6000)],
        ]);

        $count = $this->service($http)->update($this->admin, 'ar');

        self::assertSame(6000, $count);
        self::assertSame('NAME 1', $this->licensees->name('LU1A1'));
        // The second request sends the token, and both use the certificates of ENACOM.
        self::assertSame(['csrf_token' => 'abc', 'valor' => '', 'mostrarTodos' => '1'], $http->requests[1]['form']);
        self::assertSame('/certs/chain.pem', $http->requests[1]['caFile']);
        self::assertSame(['registry.update'], $this->audit->actions());
    }

    public function testKeepsTheOldListIfTheNewListIsTooSmall(): void
    {
        $this->licensees->replace('AR', ['LU9ZZA' => 'JUANA ISABEL EJEMPLO'], 'old');
        $http = new FakeHttpClient([
            RegistryService::ENACOM_URL => ['<input name="csrf_token" value="abc">', self::enacomPage(10)],
        ]);

        $this->assertStatus(502, fn () => $this->service($http)->update($this->admin, 'AR'));
        self::assertSame('JUANA ISABEL EJEMPLO', $this->licensees->name('LU9ZZA'));
    }

    public function testAFailedDownloadGivesAnError(): void
    {
        $this->assertStatus(502, fn () => $this->service(new FakeHttpClient([]))->update($this->admin, 'UY'));
    }

    public function testRefusesAnUnknownCountry(): void
    {
        $this->assertStatus(404, fn () => $this->service(new FakeHttpClient([]))->update($this->admin, 'BR'));
    }

    private function service(FakeHttpClient $http): RegistryService
    {
        return new RegistryService($this->licensees, $http, $this->audit, '/certs/chain.pem');
    }

    private static function enacomPage(int $count): string
    {
        $rows = '';
        for ($i = 1; $i <= $count; $i++) {
            $rows .= sprintf('<tr><td>NAME %d</td><td>GENERAL</td><td>LU1A%d</td><td></td><td></td><td></td></tr>', $i, $i);
        }

        return '<table><tr><th>Radioaficionado</th><th>Categoria</th><th>Señal Distintiva</th></tr>' . $rows . '</table>';
    }

    private function assertStatus(int $status, callable $action): void
    {
        try {
            $action();
            self::fail('The action did not fail.');
        } catch (HttpException $e) {
            self::assertSame($status, $e->status, $e->getMessage());
        }
    }
}
