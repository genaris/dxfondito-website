<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use Closure;
use DxFondito\Auth\PasswordRules;
use DxFondito\Auth\User;
use DxFondito\Auth\UserStore;
use DxFondito\CallSign;
use DxFondito\Database\Migrator;
use DxFondito\Http\HttpException;
use DxFondito\Http\Request;
use DxFondito\Http\Response;

/**
 * The migration page. The developer opens it after an installation to apply the new migration files.
 * While there are no accounts, the page also makes the first administrator.
 */
final class MigrationController
{
    /**
     * @param Closure(): Migrator $migrator
     */
    public function __construct(
        private readonly Closure $migrator,
        private readonly UserStore $users,
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
        if (!$this->secretIsCorrect($request)) {
            return $this->incorrectSecret();
        }

        $done = ($this->migrator)()->migrate();
        if ($done === []) {
            $content = '<p>No hay migraciones pendientes.</p>';
        } else {
            $items = implode('', array_map(
                static fn (string $name): string => '<li>' . self::escape($name) . '</li>',
                $done,
            ));
            $content = '<p>Migraciones aplicadas:</p><ul>' . $items . '</ul>';
        }

        if ($this->users->count() === 0) {
            $content .= $this->administratorForm();
        }

        return Response::html($this->page($content));
    }

    /**
     * Makes the first administrator. This is possible only while there are no accounts.
     */
    public function createAdministrator(Request $request): Response
    {
        $this->requireEnabled();
        if (!$this->secretIsCorrect($request)) {
            return $this->incorrectSecret();
        }
        if ($this->users->count() !== 0) {
            return Response::html($this->page('<p>Ya hay cuentas. Un administrador crea las cuentas nuevas desde el sitio.</p>'), 409);
        }

        $callSign = CallSign::normalize($request->string('callSign'));
        $name = trim($request->string('name'));
        $password = $request->string('password');

        $errors = [];
        if (!CallSign::isValid($callSign)) {
            $errors[] = 'El indicativo debe tener entre 3 y 20 letras, números o barras.';
        }
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'El nombre debe tener entre 1 y 100 caracteres.';
        }
        if (mb_strlen($password) < PasswordRules::MIN_LENGTH || strlen($password) > PasswordRules::MAX_BYTES) {
            $errors[] = 'La contraseña debe tener entre ' . PasswordRules::MIN_LENGTH . ' y ' . PasswordRules::MAX_BYTES . ' caracteres.';
        }
        if ($errors !== []) {
            $items = implode('', array_map(static fn (string $error): string => '<li>' . $error . '</li>', $errors));

            return Response::html($this->page('<ul>' . $items . '</ul>' . $this->administratorForm($callSign, $name)), 422);
        }

        // The developer chose this password. Thus it is not an initial password.
        $this->users->create($callSign, $name, null, User::ADMINISTRATOR, PasswordRules::hash($password), false);

        return Response::html($this->page(
            '<p>Se creó la cuenta de administrador ' . self::escape($callSign) . '.</p>'
            . '<p><a href="../#/ingresar">Ingresar al sitio</a></p>'
        ));
    }

    private function secretIsCorrect(Request $request): bool
    {
        return hash_equals($this->secret, $request->string('secret'));
    }

    private function incorrectSecret(): Response
    {
        // The delay makes a search for the secret slow.
        sleep(1);

        return Response::html($this->page('<p>La clave no es correcta.</p>'), 403);
    }

    private function administratorForm(string $callSign = '', string $name = ''): string
    {
        return '<h2>Primer administrador</h2>'
            . '<p>No hay cuentas. Cree la cuenta del primer administrador.</p>'
            . '<form method="post" action="?r=/migrate/administrator">'
            . '<p><label>Clave de migración <input type="password" name="secret" required></label></p>'
            . '<p><label>Indicativo <input name="callSign" value="' . self::escape($callSign) . '" required></label></p>'
            . '<p><label>Nombre <input name="name" value="' . self::escape($name) . '" required></label></p>'
            . '<p><label>Contraseña <input type="password" name="password" minlength="' . PasswordRules::MIN_LENGTH . '" required></label></p>'
            . '<button type="submit">Crear administrador</button>'
            . '</form>';
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES);
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
