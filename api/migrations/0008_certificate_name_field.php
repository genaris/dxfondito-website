<?php

declare(strict_types=1);

// The certificates show the full name of the participant instead of the date (FR-CER-2).
// The box of the date field of each saved template becomes the box of the name field. The step can run again.

return static function (PDO $pdo, string $storageDir): void {
    $update = $pdo->prepare('UPDATE certificate_templates SET fields = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id, fields FROM certificate_templates')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fields = json_decode($row['fields'], true, flags: JSON_THROW_ON_ERROR);
        if (!isset($fields['date']) || isset($fields['name'])) {
            continue;
        }
        $fields['name'] = $fields['date'];
        unset($fields['date']);
        $update->execute([json_encode($fields, JSON_THROW_ON_ERROR), $row['id']]);
    }
};
