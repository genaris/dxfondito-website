<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use DxFondito\Audit\AuditLog;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;
use DxFondito\Logs\FileStore;
use DxFondito\Ranking\Calculator;
use DxFondito\Ranking\ContactRow;
use DxFondito\Ranking\RankingStore;
use DxFondito\Registry\LicenseeName;
use DxFondito\Registry\LicenseeStore;

/**
 * The certificate templates and the certificates (FR-CER-1 to FR-CER-7).
 * Only an administrator controls the templates. The controller checks the role.
 */
final class CertificateService
{
    public function __construct(
        private readonly CertificateTemplateStore $templates,
        private readonly RankingStore $ranking,
        private readonly FileStore $files,
        private readonly TextRenderer $renderer,
        private readonly AuditLog $audit,
        private readonly LicenseeStore $licensees,
    ) {
    }

    /**
     * All templates, each with the size of its image.
     *
     * @return list<array{template: CertificateTemplate, width: int, height: int}>
     */
    public function all(): array
    {
        return array_map(
            fn (CertificateTemplate $template): array => ['template' => $template] + $this->size($template),
            $this->templates->all(),
        );
    }

    /**
     * @return list<int> The certificate levels, such as [5, 10, 15].
     */
    public function levels(): array
    {
        return $this->ranking->levels();
    }

    /**
     * @return array{content: string, type: string}
     */
    public function image(int $season, int $points): array
    {
        $template = $this->find($season, $points);
        $content = $this->files->read($template->storedName) ?? throw new HttpException(404, 'The image does not exist');

        return ['content' => $content, 'type' => str_ends_with($template->storedName, '.png') ? 'image/png' : 'image/jpeg'];
    }

    /**
     * Saves a new template, or changes the fields or the image of a template (FR-CER-1, FR-CER-3).
     *
     * @throws HttpException 404 for a level that does not exist, 422 for an incorrect image or field.
     */
    public function save(User $actor, int $season, int $points, ?UploadedFile $file, string $fieldsJson): CertificateTemplate
    {
        $this->requireLevel($points);
        $old = $this->templates->find($season, $points);
        $hasFile = $file !== null && $file->error !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            $image = TemplateImage::fromUpload($file);
        } elseif ($old !== null) {
            $image = TemplateImage::fromContent($this->files->read($old->storedName) ?? '');
        } else {
            throw new HttpException(422, 'An image is necessary');
        }
        $fields = FieldLayout::check(FieldLayout::decode($fieldsJson), FieldLayout::CERTIFICATE_FIELDS, $image->width, $image->height);

        $newImage = $old === null || $hasFile;
        $storedName = $newImage ? $this->files->save($image->content, $image->extension) : $old->storedName;
        $this->templates->save($season, $points, $storedName, $fields);
        if ($newImage && $old !== null) {
            $this->files->delete($old->storedName);
        }

        $this->audit->record($actor->id, AuditLog::CERTIFICATE_TEMPLATE_SAVE, null, [
            'season' => $season,
            'points' => $points,
            'newImage' => $newImage,
        ]);

        return $this->find($season, $points);
    }

    public function delete(User $actor, int $season, int $points): void
    {
        $template = $this->find($season, $points);
        $this->templates->delete($season, $points);
        $this->files->delete($template->storedName);
        $this->audit->record($actor->id, AuditLog::CERTIFICATE_TEMPLATE_DELETE, null, ['season' => $season, 'points' => $points]);
    }

    /**
     * A sample certificate image with example data, before the save operation.
     * The image is the uploaded file, or the image of the saved template.
     */
    public function preview(?UploadedFile $file, ?int $season, ?int $points, string $fieldsJson): string
    {
        if ($file !== null && $file->error !== UPLOAD_ERR_NO_FILE) {
            $image = TemplateImage::fromUpload($file);
        } elseif ($season !== null && $points !== null) {
            $image = TemplateImage::fromContent($this->image($season, $points)['content']);
        } else {
            throw new HttpException(422, 'An image is necessary');
        }
        $fields = FieldLayout::check(FieldLayout::decode($fieldsJson), FieldLayout::CERTIFICATE_FIELDS, $image->width, $image->height);

        return $this->renderer->render($image->content, $fields, Certificate::sample());
    }

    /**
     * The certificate of a participant as a PDF file (FR-CER-4 to FR-CER-7).
     *
     * @param string|null $name The full name on the certificate. Null for the name of the official lists, or an
     *   empty text for a participant that is not on the lists: the download. The mailer gives the name.
     * @return array{content: string, name: string}
     * @throws HttpException 404 if the participant does not have the certificate (FR-CER-6), or without a template (FR-CER-7).
     */
    public function certificate(string $baseCallSign, int $season, int $points, ?string $name = null): array
    {
        $contacts = array_values(array_filter(
            Calculator::firstContacts($this->ranking->byParticipant($baseCallSign)),
            static fn (ContactRow $contact): bool => $contact->season === $season,
        ));
        $reached = false;
        foreach (Calculator::certificates($contacts, $this->ranking->levels()) as $certificate) {
            $reached = $reached || $certificate['points'] === $points;
        }
        if (!$reached) {
            throw new HttpException(404, 'The participant does not have the certificate');
        }

        $template = $this->templates->find($season, $points) ?? throw new HttpException(404, 'The certificate is not available');
        $content = $this->files->read($template->storedName) ?? throw new HttpException(404, 'The certificate is not available');
        $jpeg = $this->renderer->render($content, $template->fields, Certificate::values($baseCallSign, $name ?? $this->officialName($baseCallSign)));

        return [
            'content' => CertificatePdf::fromJpeg($jpeg),
            'name' => sprintf('Certificado_%s_%d_%d.pdf', $baseCallSign, $season, $points),
        ];
    }

    /**
     * The name of the participant in the registries of Argentina and Uruguay, or an empty text (as FR-QSL-3a).
     */
    public function officialName(string $baseCallSign): string
    {
        $name = $this->licensees->name($baseCallSign);

        return $name === null ? '' : LicenseeName::format($name);
    }

    private function find(int $season, int $points): CertificateTemplate
    {
        return $this->templates->find($season, $points) ?? throw new HttpException(404, 'The template does not exist');
    }

    private function requireLevel(int $points): void
    {
        if (!in_array($points, $this->ranking->levels(), true)) {
            throw new HttpException(404, 'The certificate level does not exist');
        }
    }

    /**
     * @return array{width: int, height: int}
     */
    private function size(CertificateTemplate $template): array
    {
        $info = @getimagesizefromstring($this->files->read($template->storedName) ?? '');

        return $info === false ? ['width' => 0, 'height' => 0] : ['width' => $info[0], 'height' => $info[1]];
    }
}
