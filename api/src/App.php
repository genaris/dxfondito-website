<?php

declare(strict_types=1);

namespace DxFondito;

use DxFondito\Audit\PdoAuditLog;
use DxFondito\Auth\AccountService;
use DxFondito\Auth\Authenticator;
use DxFondito\Auth\PdoUserStore;
use DxFondito\Auth\PhpSession;
use DxFondito\Controller\AuditController;
use DxFondito\Controller\HealthController;
use DxFondito\Controller\MigrationController;
use DxFondito\Controller\SessionController;
use DxFondito\Controller\UserController;
use DxFondito\Database\Connection;
use DxFondito\Database\Migrator;
use DxFondito\Http\HttpException;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Http\Router;
use PDO;
use Throwable;

final class App
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $root)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router(Config::load($this->root), $request)->dispatch($request);
        } catch (HttpException $e) {
            return Response::error($e->status, $e->getMessage());
        } catch (Throwable $e) {
            // The browser gets no details. The details go to the error log of the server.
            error_log((string) $e);

            return Response::error(500, 'Internal error');
        }
    }

    private function router(Config $config, Request $request): Router
    {
        // The API opens the database connection only when a request needs it.
        $pdo = fn (): PDO => $this->pdo ??= Connection::open($config);
        $migrator = fn (): Migrator => new Migrator($pdo(), $this->root . '/migrations');
        $users = new PdoUserStore($pdo);
        $audit = new PdoAuditLog($pdo);
        $auth = new Authenticator($users, new PhpSession($config->storageDir . '/sessions', $request->secure), $audit);

        $health = new HealthController($migrator);
        $migration = new MigrationController($migrator, $users, $audit, $config->migrationSecret);
        $session = new SessionController($auth);
        $accounts = new UserController($auth, new AccountService($users, $audit));
        $auditRecord = new AuditController($auth, $audit);

        $router = new Router();
        $router->add('GET', '/health', fn (): Response => $health->show());
        $router->add('GET', '/migrate', fn (): Response => $migration->form());
        $router->add('POST', '/migrate', fn (Request $request): Response => $migration->run($request));
        $router->add('POST', '/migrate/administrator', fn (Request $request): Response => $migration->createAdministrator($request));
        $router->add('GET', '/session', fn (): Response => $session->show());
        $router->add('POST', '/session', fn (Request $request): Response => $session->signIn($request));
        $router->add('DELETE', '/session', fn (): Response => $session->signOut());
        $router->add('PUT', '/session/password', fn (Request $request): Response => $session->changePassword($request));
        $router->add('GET', '/users', fn (Request $request): Response => $accounts->list($request));
        $router->add('POST', '/users', fn (Request $request): Response => $accounts->create($request));
        $router->add('PUT', '/users/{id}', fn (Request $request, array $params): Response => $accounts->update($request, $params));
        $router->add('GET', '/audit', fn (Request $request): Response => $auditRecord->list($request));
        $router->add('PUT', '/users/{id}/password', fn (Request $request, array $params): Response => $accounts->setPassword($request, $params));

        return $router;
    }
}
