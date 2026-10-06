<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Activities\Activity;
use DxFondito\Activities\ActivityStore;
use DxFondito\Auth\Authenticator;
use DxFondito\Http\HttpException;
use DxFondito\Http\PathId;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Mail\AddressBookService;
use DxFondito\Mail\CertificateMailService;
use DxFondito\Mail\MessageTemplate;
use DxFondito\Mail\QslMailService;
use DxFondito\Mail\Templates;

/**
 * The QSL mailer (section 8.4 of the system design). Only an administrator uses it (FR-MAIL-1).
 */
final class MailController
{
    private const NO_ACTIVITY = 'The activity does not exist';

    public function __construct(
        private readonly Authenticator $auth,
        private readonly ActivityStore $activities,
        private readonly AddressBookService $book,
        private readonly Templates $templates,
        private readonly QslMailService $qsl,
        private readonly CertificateMailService $certificates,
    ) {
    }

    // The address book.

    public function addressBook(Request $request): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->book->all());
    }

    /**
     * @param array<string, string> $params
     */
    public function contact(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->book->show($params['call'] ?? ''));
    }

    /**
     * @param array<string, string> $params
     */
    public function saveContact(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $this->book->save(
            $actor,
            $params['call'] ?? '',
            $request->string('name'),
            $request->string('email'),
            ($request->body['noMail'] ?? false) === true,
            $request->string('notes'),
        );

        return Response::json($this->book->show($params['call'] ?? ''));
    }

    /**
     * @param array<string, string> $params
     */
    public function deleteContact(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $this->book->delete($actor, $params['call'] ?? '');

        return Response::json(['deleted' => true]);
    }

    public function setInvalid(Request $request): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $this->book->setInvalid($actor, $request->string('email'), ($request->body['invalid'] ?? false) === true);

        return Response::json(['email' => $request->string('email')]);
    }

    // The general texts of the messages.

    /**
     * @param array<string, string> $params
     */
    public function template(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);
        $kind = self::kind($params);

        return Response::json($this->templates->get($kind) + ['variables' => MessageTemplate::VARIABLES[$kind]]);
    }

    /**
     * @param array<string, string> $params
     */
    public function saveTemplate(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $kind = self::kind($params);
        $this->templates->save($actor, $kind, Templates::GENERAL, $request->string('subject'), $request->string('body'));

        return Response::json($this->templates->get($kind));
    }

    /**
     * @param array<string, string> $params
     */
    public function resetTemplate(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $kind = self::kind($params);
        $this->templates->reset($actor, $kind, Templates::GENERAL);

        return Response::json($this->templates->get($kind));
    }

    // The overview and the warning for the administrators.

    /**
     * The activities of a season with the state of their QSL messages.
     *
     * @param array<string, string> $params
     */
    public function season(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);
        $season = PathId::season($params);

        return Response::json([
            'season' => $season,
            'activities' => array_map(
                fn (Activity $activity): array => $activity->publicData() + $this->qsl->counts($activity->id),
                $this->activities->bySeason($season),
            ),
            'certificates' => $this->certificates->summary([$season]),
        ]);
    }

    /**
     * FR-MAIL-13: the certificates that wait for a message, for the warning after the sign-in.
     */
    public function summary(Request $request): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json(['certificates' => $this->certificates->summary($this->activities->seasons())]);
    }

    // The QSL messages of an activity.

    /**
     * @param array<string, string> $params
     */
    public function activity(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->qsl->overview(PathId::from($params, self::NO_ACTIVITY)));
    }

    /**
     * @param array<string, string> $params
     */
    public function saveActivityTemplate(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $id = PathId::from($params, self::NO_ACTIVITY);
        $this->qsl->saveTemplate($actor, $id, $request->string('subject'), $request->string('body'));

        return Response::json($this->qsl->overview($id));
    }

    /**
     * @param array<string, string> $params
     */
    public function resetActivityTemplate(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $id = PathId::from($params, self::NO_ACTIVITY);
        $this->qsl->resetTemplate($actor, $id);

        return Response::json($this->qsl->overview($id));
    }

    /**
     * @param array<string, string> $params
     */
    public function previewQsl(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->qsl->preview(
            PathId::from($params, self::NO_ACTIVITY),
            $request->string('callSign'),
            $request->string('subject'),
            $request->string('body'),
        ));
    }

    /**
     * @param array<string, string> $params
     */
    public function testQsl(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $to = $this->qsl->test(
            $actor,
            PathId::from($params, self::NO_ACTIVITY),
            $request->string('callSign'),
            $request->string('subject'),
            $request->string('body'),
        );

        return Response::json(['to' => $to]);
    }

    /**
     * @param array<string, string> $params
     */
    public function sendQsl(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);

        return Response::json($this->qsl->send(
            $actor,
            PathId::from($params, self::NO_ACTIVITY),
            self::list($request, 'callSigns'),
            ($request->body['resend'] ?? false) === true,
        ));
    }

    /**
     * @param array<string, string> $params
     */
    public function markQsl(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $count = $this->qsl->mark($actor, PathId::from($params, self::NO_ACTIVITY), self::list($request, 'callSigns'));

        return Response::json(['marked' => $count]);
    }

    /**
     * @param array<string, string> $params
     */
    public function unmarkQsl(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $count = $this->qsl->unmark($actor, PathId::from($params, self::NO_ACTIVITY), self::list($request, 'callSigns'));

        return Response::json(['unmarked' => $count]);
    }

    // The certificate messages of a season.

    /**
     * @param array<string, string> $params
     */
    public function certificates(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->certificates->overview(PathId::season($params)));
    }

    /**
     * @param array<string, string> $params
     */
    public function saveCertificateTemplate(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $season = PathId::season($params);
        $this->certificates->saveTemplate($actor, $season, $request->string('subject'), $request->string('body'));

        return Response::json($this->certificates->overview($season));
    }

    /**
     * @param array<string, string> $params
     */
    public function resetCertificateTemplate(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $season = PathId::season($params);
        $this->certificates->resetTemplate($actor, $season);

        return Response::json($this->certificates->overview($season));
    }

    /**
     * @param array<string, string> $params
     */
    public function previewCertificate(Request $request, array $params): Response
    {
        $this->auth->requireAdministrator($request);

        return Response::json($this->certificates->preview(
            PathId::season($params),
            $request->string('callSign'),
            $request->int('points') ?? 0,
            $request->string('subject'),
            $request->string('body'),
        ));
    }

    /**
     * @param array<string, string> $params
     */
    public function testCertificate(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $to = $this->certificates->test(
            $actor,
            PathId::season($params),
            $request->string('callSign'),
            $request->int('points') ?? 0,
            $request->string('subject'),
            $request->string('body'),
        );

        return Response::json(['to' => $to]);
    }

    /**
     * @param array<string, string> $params
     */
    public function sendCertificates(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);

        return Response::json($this->certificates->send(
            $actor,
            PathId::season($params),
            self::list($request, 'certificates'),
            ($request->body['again'] ?? false) === true,
        ));
    }

    /**
     * @param array<string, string> $params
     */
    public function markCertificates(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $count = $this->certificates->mark($actor, PathId::season($params), self::list($request, 'certificates'));

        return Response::json(['marked' => $count]);
    }

    /**
     * @param array<string, string> $params
     */
    public function unmarkCertificates(Request $request, array $params): Response
    {
        $actor = $this->auth->requireAdministrator($request);
        $count = $this->certificates->unmark($actor, PathId::season($params), self::list($request, 'certificates'));

        return Response::json(['unmarked' => $count]);
    }

    /**
     * @param array<string, string> $params
     */
    private static function kind(array $params): string
    {
        $kind = $params['kind'] ?? '';
        if (!isset(MessageTemplate::VARIABLES[$kind])) {
            throw new HttpException(404, 'The kind of message does not exist');
        }

        return $kind;
    }

    /**
     * @return list<mixed>
     */
    private static function list(Request $request, string $key): array
    {
        $value = $request->body[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }
}
