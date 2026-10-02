<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Auth\Authenticator;
use DxFondito\CallSign;
use DxFondito\Http\HttpException;
use DxFondito\Http\PathId;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Templates\CertificateService;
use DxFondito\Templates\CertificateTemplate;

/**
 * The certificate templates (section 8.4 of the system design) and the certificate download (section 8.1).
 */
final class CertificateController
{
    private const NO_LEVEL = 'The certificate level does not exist';

    public function __construct(
        private readonly Authenticator $auth,
        private readonly CertificateService $certificates,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json([
            'levels' => $this->certificates->levels(),
            'templates' => array_map(
                static fn (array $item): array => self::data($item['template'], $item['width'], $item['height']),
                $this->certificates->all(),
            ),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function image(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);
        $image = $this->certificates->image(PathId::season($params), PathId::from($params, self::NO_LEVEL, 'points'));

        return Response::file($image['content'], $image['type']);
    }

    /**
     * A multipart request with the `fields` JSON text and, for a new image, the `file` field.
     *
     * @param array<string, string> $params
     */
    public function save(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $this->refuseLargeBody($request);
        $template = $this->certificates->save(
            $actor,
            PathId::season($params),
            PathId::from($params, self::NO_LEVEL, 'points'),
            $request->files['file'] ?? null,
            $request->string('fields'),
        );

        return Response::json(self::data($template, 0, 0));
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $this->certificates->delete($actor, PathId::season($params), PathId::from($params, self::NO_LEVEL, 'points'));

        return Response::json(['deleted' => true]);
    }

    /**
     * A sample certificate as a JPEG image. The image is the `file` field, or the image of the template
     * of `season` and `points`.
     */
    public function preview(Request $request): Response
    {
        $this->auth->requireAdministrator($request);
        $this->refuseLargeBody($request);
        $jpeg = $this->certificates->preview(
            $request->files['file'] ?? null,
            $request->int('season'),
            $request->int('points'),
            $request->string('fields'),
        );

        return Response::file($jpeg, 'image/jpeg');
    }

    /**
     * The certificate of a participant as a PDF file, for a visitor (FR-CER-4, FR-CER-5).
     *
     * @param array<string, string> $params
     */
    public function certificate(array $params): Response
    {
        $callSign = CallSign::base($params['call'] ?? '');
        if (!CallSign::isValid($callSign)) {
            throw new HttpException(404, 'The participant does not exist');
        }
        $certificate = $this->certificates->certificate(
            $callSign,
            PathId::season($params),
            PathId::from($params, self::NO_LEVEL, 'points'),
        );

        return Response::file($certificate['content'], 'application/pdf', $certificate['name']);
    }

    private function refuseLargeBody(Request $request): void
    {
        if ($request->bodyTooLarge) {
            throw new HttpException(413, 'The image is larger than 10 MB');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(CertificateTemplate $template, int $width, int $height): array
    {
        return [
            'season' => $template->season,
            'points' => $template->points,
            'fields' => $template->fields,
            'width' => $width,
            'height' => $height,
        ];
    }
}
