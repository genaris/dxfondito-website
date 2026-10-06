<?php

declare(strict_types=1);

namespace DxFondito\Mail;

/**
 * The subject and the body of a message, with variables in braces such as {saludo} (FR-MAIL-10, FR-MAIL-11).
 * The text is plain text. The system makes the HTML version.
 */
final class MessageTemplate
{
    public const QSL = 'qsl';
    public const CERTIFICATE = 'certificate';

    /** The variables of each kind of message, with a description for the editor. */
    public const VARIABLES = [
        self::QSL => [
            'indicativo' => 'El indicativo del participante',
            'nombre' => 'El nombre del listado oficial, o vacío',
            'saludo' => 'El primer nombre, o el indicativo si no hay nombre',
            'referencia' => 'La referencia, como DPS-05',
            'actividad' => 'El nombre de la actividad',
            'fecha' => 'La fecha de la actividad',
            'operadores' => 'Los operadores que el participante contactó',
            'temporada' => 'El año de la temporada',
            'puntos' => 'Los puntos del participante en la temporada',
            'cantidad' => 'La cantidad de QSL adjuntas',
            'qsos' => 'Una línea por QSO: hora, frecuencia, modo y operador',
            'sitio' => 'La dirección del sitio',
        ],
        self::CERTIFICATE => [
            'indicativo' => 'El indicativo del participante',
            'nombre' => 'El nombre del listado oficial, o vacío',
            'saludo' => 'El primer nombre, o el indicativo si no hay nombre',
            'nivel' => 'El nivel: Bronce, Plata u Oro',
            'referencias' => 'Las referencias necesarias para el nivel',
            'fecha_certificado' => 'La fecha del certificado',
            'temporada' => 'El año de la temporada',
            'puntos' => 'Los puntos del participante en la temporada',
            'sitio' => 'La dirección del sitio',
        ],
    ];

    /** The default messages. An administrator changes them in the administration (FR-MAIL-10). */
    public const DEFAULTS = [
        self::QSL => [
            'subject' => 'QSL {referencia} {actividad} - {indicativo}',
            'body' => "Hola {saludo}!\n\n"
                . "Muchas gracias por participar de la actividad {referencia} {actividad}, el {fecha}.\n\n"
                . "Te enviamos la QSL Especial de tu contacto:\n{qsos}\n\n"
                . "Puntos en la temporada {temporada}: {puntos}. En {sitio} podés ver el ranking y descargar tus QSL.\n\n"
                . "73!\nGrupo DX Fondito",
        ],
        self::CERTIFICATE => [
            'subject' => 'Certificado {nivel} {temporada} - {indicativo}',
            'body' => "Hola {saludo}!\n\n"
                . "¡Felicitaciones! Con {referencias} referencias distintas en la temporada {temporada}, "
                . "alcanzaste el certificado {nivel} del Diploma Puestos de Salud.\n\n"
                . "Te lo enviamos adjunto.\n\n"
                . "En {sitio} podés ver el ranking y seguir sumando puntos.\n\n"
                . "73!\nGrupo DX Fondito",
        ],
    ];

    /**
     * The text with the value of each variable. An unknown variable stays as it is: check() finds it first.
     *
     * @param array<string, string> $values
     */
    public static function render(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static fn (array $match): string => $values[$match[1]] ?? $match[0],
            $template,
        );
    }

    /**
     * The unknown variables of a text, such as "nombr" in "{nombr}".
     *
     * @return list<string>
     */
    public static function unknownVariables(string $kind, string $template): array
    {
        preg_match_all('/\{([^{}\s]*)\}/u', $template, $matches);
        $known = self::VARIABLES[$kind] ?? [];

        return array_values(array_unique(array_filter($matches[1], static fn (string $name): bool => !isset($known[$name]))));
    }

    /**
     * The HTML version of a plain text: paragraphs, line breaks and links (FR-MAIL-11).
     */
    public static function html(string $text): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $text))) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $escaped = htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $linked = (string) preg_replace('#\bhttps?://[^\s<]+[^\s<.,;:!?)]#', '<a href="$0">$0</a>', $escaped);
            $html .= '<p>' . nl2br($linked, false) . "</p>\n";
        }

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"></head>'
            . '<body style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.5; color: #1a1a1a;">'
            . "\n" . $html . '</body></html>';
    }

    /**
     * The greeting: the first given name, or the call sign without a name. Thus a message never says "Hola !".
     */
    public static function greeting(string $name, string $callSign): string
    {
        $first = trim(explode(' ', trim($name))[0] ?? '');

        return $first !== '' ? $first : $callSign;
    }
}
