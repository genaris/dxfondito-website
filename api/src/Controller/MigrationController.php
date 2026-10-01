<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use Closure;
use DxFondito\Database\Migrator;
use DxFondito\Http\HttpException;
use DxFondito\Http\Request;
use DxFondito\Http\Response;

/**
 * The migration page. The developer opens it after an installation to apply the new migration files.
 */
final class MigrationController
{
    /**
     * @param Closure(): Migrator $migrator
     */
    public function __construct(
        private readonly Closure $migrator,
        private readonly string $secret,
    ) {
    }

    public function form(): Response
    {
        $this->requireEnabled();

        return Response::html($this->page(
            '<form method="post">'
            . '<label>Clave de migración <input type="password" name="secret" required autofocus></label> '
            . '<button type="submit">Aplicar migraciones</button>'
            . '</form>'
        ));
    }

    public function run(Request $request): Response
    {
        $this->requireEnabled();

        $secret = $request->body['secret'] ?? '';
        if (!is_string($secret) || !hash_equals($this->secret, $secret)) {
            // The delay makes a search for the secret slow.
            sleep(1);

            return Response::html($this->page('<p>La clave no es correcta.</p>'), 403);
        }

        $done = ($this->migrator)()->migrate();
        if ($done === []) {
            return Response::html($this->page('<p>No hay migraciones pendientes.</p>'));
        }

        $items = implode('', array_map(
            static fn (string $name): string => '<li>' . htmlspecialchars($name) . '</li>',
            $done,
        ));

        return Response::html($this->page('<p>Migraciones aplicadas:</p><ul>' . $items . '</ul>'));
    }

    private function requireEnabled(): void
    {
        if ($this->secret === '') {
            throw new HttpException(404, 'Not found');
        }
    }

    private function page(string $content): string
    {
        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>Migraciones</title></head>'
            . '<body><h1>Migraciones</h1>' . $content . '</body></html>';
    }
}
