<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Auth\Authenticator;
use DxFondito\CallSign;
use DxFondito\Http\HttpException;
use DxFondito\Http\PathId;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Templates\Fonts;
use DxFondito\Templates\QslService;
use DxFondito\Templates\QslTemplate;

/**
 * The QSL card templates (section 8.3 of the system design), the QSL card download and the fonts.
 */
final class QslController
{
    private const NO_ACTIVITY = 'The activity does not exist';
    private const NO_OPERATOR = 'The template does not exist';

    public function __construct(
        private readonly Authenticator $auth,
        private readonly QslService $qsl,
        private readonly Fonts $fonts,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function list(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $items = $this->qsl->byActivity(PathId::from($params, self::NO_ACTIVITY));

        return Response::json(array_map(
            static fn (array $item): array => self::data($item['template'], $item['width'], $item['height'])
                + ['canEdit' => QslService::canEdit($actor, $item['template']->operatorId)],
            $items,
        ));
    }

    /**
     * @param array<string, string> $params
     */
    public function image(Request $request, array $params): Response
    {
        $this->auth->requireUser($request);
        $image = $this->qsl->image(
            PathId::from($params, self::NO_ACTIVITY),
            PathId::from($params, self::NO_OPERATOR, 'operatorId'),
        );

        return Response::file($image['content'], $image['type']);
    }

    /**
     * A multipart request with the `fields` JSON text and, for a new image, the `file` field.
     *
     * @param array<string, string> $params
     */
    public function save(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $this->refuseLargeBody($request);
        $template = $this->qsl->save(
            $actor,
            PathId::from($params, self::NO_ACTIVITY),
            PathId::from($params, self::NO_OPERATOR, 'operatorId'),
            $request->files['file'] ?? null,
            $request->string('fields'),
        );

        return Response::json(self::data($template, 0, 0) + ['canEdit' => true]);
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): Response
    {
        $actor = $this->auth->requireUser($request);
        $this->qsl->delete(
            $actor,
            PathId::from($params, self::NO_ACTIVITY),
            PathId::from($params, self::NO_OPERATOR, 'operatorId'),
        );

        return Response::json(['deleted' => true]);
    }

    /**
     * A sample QSL card as a JPEG image (FR-QSL-5). The image is the `file` field, or the image of the
     * template of `activityId` and `operatorId`.
     */
    public function preview(Request $request): Response
    {
        $actor = $this->auth->requireUser($request);
        $this->refuseLargeBody($request);
        $jpeg = $this->qsl->preview(
            $actor,
            $request->files['file'] ?? null,
            $request->int('activityId'),
            $request->int('operatorId'),
            $request->string('fields'),
        );

        return Response::file($jpeg, 'image/jpeg');
    }

    /**
     * The QSL card of a participant, for a visitor (FR-QSL-6, FR-QSL-10).
     *
     * @param array<string, string> $params
     */
    public function card(array $params): Response
    {
        $callSign = CallSign::base($params['call'] ?? '');
        if (!CallSign::isValid($callSign)) {
            throw new HttpException(404, 'The participant does not exist');
        }
        $card = $this->qsl->card(
            $callSign,
            PathId::season($params),
            PathId::from($params, 'The reference does not exist', 'referenceId'),
        );

        return Response::file($card['content'], 'image/jpeg', $card['name']);
    }

    /**
     * A font of the system, for the field editor of the browser. The editor and the API use the same fonts.
     *
     * @param array<string, string> $params
     */
    public function font(array $params): Response
    {
        $name = $params['name'] ?? '';
        if (!Fonts::exists($name)) {
            throw new HttpException(404, 'The font does not exist');
        }

        return Response::file((string) file_get_contents($this->fonts->path($name)), 'font/ttf', null, 'public, max-age=604800');
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
    private static function data(QslTemplate $template, int $width, int $height): array
    {
        return [
            'activityId' => $template->activityId,
            'operator' => ['id' => $template->operatorId, 'callSign' => $template->operatorCallSign],
            'fields' => $template->fields,
            'width' => $width,
            'height' => $height,
        ];
    }
}
