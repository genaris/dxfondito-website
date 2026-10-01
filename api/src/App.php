<?php

declare(strict_types=1);

namespace DxFondito;

use DxFondito\Controller\HealthController;
use DxFondito\Controller\MigrationController;
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
            return $this->router(Config::load($this->root))->dispatch($request);
        } catch (HttpException $e) {
            return Response::error($e->status, $e->getMessage());
        } catch (Throwable $e) {
            // The browser gets no details. The details go to the error log of the server.
            error_log((string) $e);

            return Response::error(500, 'Internal error');
        }
    }

    private function router(Config $config): Router
    {
        // The API opens the database connection only when a request needs it.
        $migrator = fn (): Migrator => new Migrator(
            $this->pdo ??= Connection::open($config),
            $this->root . '/migrations',
        );

        $health = new HealthController($migrator);
        $migration = new MigrationController($migrator, $config->migrationSecret);

        $router = new Router();
        $router->add('GET', '/health', fn (): Response => $health->show());
        $router->add('GET', '/migrate', fn (): Response => $migration->form());
        $router->add('POST', '/migrate', fn (Request $request): Response => $migration->run($request));

        return $router;
    }
}
