<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use DxFondito\Activities\Activity;
use DxFondito\Activities\ActivityStore;
use DxFondito\Audit\AuditLog;
use DxFondito\Auth\User;
use DxFondito\Auth\UserStore;
use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;
use DxFondito\Logs\FileStore;
use DxFondito\Ranking\RankingStore;
use DxFondito\Registry\LicenseeName;
use DxFondito\Registry\LicenseeStore;

/**
 * The QSL card templates and the QSL cards (FR-QSL-1 to FR-QSL-11). Each contact has its QSL card (D-27).
 */
final class QslService
{
    public function __construct(
        private readonly QslTemplateStore $templates,
        private readonly ActivityStore $activities,
        private readonly UserStore $users,
        private readonly RankingStore $ranking,
        private readonly FileStore $files,
        private readonly TextRenderer $renderer,
        private readonly AuditLog $audit,
        private readonly LicenseeStore $licensees,
    ) {
    }

    /**
     * The templates of an activity, each with the size of its image.
     *
     * @return list<array{template: QslTemplate, width: int, height: int}>
     */
    public function byActivity(int $activityId): array
    {
        $this->activity($activityId);

        return array_map(
            fn (QslTemplate $template): array => ['template' => $template] + $this->size($template),
            $this->templates->byActivity($activityId),
        );
    }

    /**
     * @return array{content: string, type: string}
     */
    public function image(int $activityId, int $operatorId): array
    {
        $template = $this->find($activityId, $operatorId);
        $content = $this->files->read($template->storedName) ?? throw new HttpException(404, 'The image does not exist');

        return ['content' => $content, 'type' => str_ends_with($template->storedName, '.png') ? 'image/png' : 'image/jpeg'];
    }

    /**
     * Saves a new template, or changes the fields or the image of a template (FR-QSL-1, FR-QSL-2, FR-QSL-4).
     *
     * @throws HttpException 403 for the template of a different operator, 422 for an incorrect image or field.
     */
    public function save(User $actor, int $activityId, int $operatorId, ?UploadedFile $file, string $fieldsJson): QslTemplate
    {
        $activity = $this->activity($activityId);
        $operator = $this->operator($actor, $operatorId);
        $old = $this->templates->find($activityId, $operatorId);

        if ($file !== null && $file->error !== UPLOAD_ERR_NO_FILE) {
            $image = TemplateImage::fromUpload($file);
        } elseif ($old !== null) {
            $image = TemplateImage::fromContent($this->files->read($old->storedName) ?? '');
        } else {
            throw new HttpException(422, 'An image is necessary');
        }
        $fields = FieldLayout::check(FieldLayout::decode($fieldsJson), FieldLayout::QSL_FIELDS, $image->width, $image->height);

        $newImage = $old === null || ($file !== null && $file->error !== UPLOAD_ERR_NO_FILE);
        $storedName = $newImage ? $this->files->save($image->content, $image->extension) : $old->storedName;
        $this->templates->save($activityId, $operatorId, $storedName, $fields);
        if ($newImage && $old !== null) {
            $this->files->delete($old->storedName);
        }

        $this->audit->record($actor->id, AuditLog::QSL_TEMPLATE_SAVE, $activityId, [
            'label' => $activity->label(),
            'operator' => $operator->callSign,
            'newImage' => $newImage,
        ]);

        return $this->find($activityId, $operatorId);
    }

    public function delete(User $actor, int $activityId, int $operatorId): void
    {
        $activity = $this->activity($activityId);
        $template = $this->find($activityId, $operatorId);
        $this->requireEditor($actor, $operatorId);

        $this->templates->delete($activityId, $operatorId);
        $this->files->delete($template->storedName);
        $this->audit->record($actor->id, AuditLog::QSL_TEMPLATE_DELETE, $activityId, [
            'label' => $activity->label(),
            'operator' => $template->operatorCallSign,
        ]);
    }

    /**
     * A sample QSL card with example data, before the save operation (FR-QSL-5).
     * The image is the uploaded file, or the image of the saved template.
     */
    public function preview(User $actor, ?UploadedFile $file, ?int $activityId, ?int $operatorId, string $fieldsJson): string
    {
        if ($file !== null && $file->error !== UPLOAD_ERR_NO_FILE) {
            $image = TemplateImage::fromUpload($file);
        } elseif ($activityId !== null && $operatorId !== null) {
            $this->requireEditor($actor, $operatorId);
            $image = TemplateImage::fromContent($this->image($activityId, $operatorId)['content']);
        } else {
            throw new HttpException(422, 'An image is necessary');
        }
        $fields = FieldLayout::check(FieldLayout::decode($fieldsJson), FieldLayout::QSL_FIELDS, $image->width, $image->height);

        return $this->renderer->render($image->content, $fields, QslCard::sample(), true);
    }

    /**
     * The QSL card of one contact of a participant (FR-QSL-6 to FR-QSL-11).
     *
     * @return array{content: string, name: string}
     * @throws HttpException 404 if the contact is not of the participant, or without a template of the operator (FR-QSL-11).
     */
    public function card(string $baseCallSign, int $contactId): array
    {
        $contact = null;
        foreach ($this->ranking->byParticipant($baseCallSign) as $row) {
            if ($row->id === $contactId) {
                $contact = $row;
            }
        }
        if ($contact === null) {
            throw new HttpException(404, 'The participant has no such contact');
        }

        // FR-QSL-9: the template of the operator of the contact, for the activity of the contact.
        $template = $this->templates->find($contact->activityId, $contact->operatorId)
            ?? throw new HttpException(404, 'The QSL card is not available');
        $content = $this->files->read($template->storedName) ?? throw new HttpException(404, 'The QSL card is not available');

        return [
            'content' => $this->renderer->render($content, $template->fields, QslCard::values($contact, $this->name($baseCallSign)), true),
            'name' => sprintf(
                'QSL_%s_%s_%s_%s.jpg',
                $baseCallSign,
                $contact->referenceCode,
                str_replace('-', '', substr($contact->qsoAt, 0, 10)),
                str_replace(':', '', substr($contact->qsoAt, 11, 5)),
            ),
        ];
    }

    /**
     * FR-QSL-3a: the name of the participant in the registries of Argentina and Uruguay, or an empty text.
     */
    private function name(string $baseCallSign): string
    {
        $name = $this->licensees->name($baseCallSign);

        return $name === null ? '' : LicenseeName::format($name);
    }

    public static function canEdit(User $actor, int $operatorId): bool
    {
        return $actor->isAdministrator() || $actor->id === $operatorId;
    }

    private function find(int $activityId, int $operatorId): QslTemplate
    {
        return $this->templates->find($activityId, $operatorId) ?? throw new HttpException(404, 'The template does not exist');
    }

    private function activity(int $id): Activity
    {
        return $this->activities->find($id) ?? throw new HttpException(404, 'The activity does not exist');
    }

    /**
     * FR-QSL-2: an operator saves the own templates. An administrator saves all templates.
     */
    private function operator(User $actor, int $operatorId): User
    {
        $this->requireEditor($actor, $operatorId);
        $operator = $operatorId === $actor->id ? $actor : $this->users->findById($operatorId);
        if ($operator === null || !$operator->active) {
            throw new HttpException(422, 'The operator does not exist');
        }

        return $operator;
    }

    private function requireEditor(User $actor, int $operatorId): void
    {
        if (!self::canEdit($actor, $operatorId)) {
            throw new HttpException(403, 'Only the operator or an administrator can do this');
        }
    }

    /**
     * @return array{width: int, height: int}
     */
    private function size(QslTemplate $template): array
    {
        $info = @getimagesizefromstring($this->files->read($template->storedName) ?? '');

        return $info === false ? ['width' => 0, 'height' => 0] : ['width' => $info[0], 'height' => $info[1]];
    }
}
