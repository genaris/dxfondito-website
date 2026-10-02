<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use DxFondito\Ranking\ContactRow;

/**
 * The texts of the fields of a QSL card (FR-QSL-3).
 */
final class QslCard
{
    /**
     * The data of the contact (FR-QSL-8).
     *
     * @param string $name The name of the participant in the official registries, or an empty text (FR-QSL-3a).
     *   The NAME field of the log is not on the public QSL card.
     * @return array<string, string>
     */
    public static function values(ContactRow $contact, string $name): array
    {
        return [
            'call_sign' => $contact->callSign,
            'name' => $name,
            // DD/MM/YYYY and HH:MM in UTC.
            'date' => substr($contact->qsoAt, 8, 2) . '/' . substr($contact->qsoAt, 5, 2) . '/' . substr($contact->qsoAt, 0, 4),
            'time' => substr($contact->qsoAt, 11, 5),
            'frequency' => $contact->frequency !== null ? $contact->frequency . ' MHz' : (string) $contact->band,
            'mode' => $contact->mode,
            'rst' => $contact->rstSent ?? '',
        ];
    }

    /**
     * The example data of the sample image (FR-QSL-5).
     *
     * @return array<string, string>
     */
    public static function sample(): array
    {
        return [
            'call_sign' => 'LU1ABC/P',
            'name' => 'Juana Pérez',
            'date' => '04/10/2026',
            'time' => '14:30',
            'frequency' => '7.130 MHz',
            'mode' => 'SSB',
            'rst' => '59',
        ];
    }
}
