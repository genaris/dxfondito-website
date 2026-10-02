<?php

declare(strict_types=1);

namespace DxFondito;

use DxFondito\Activities\ActivityService;
use DxFondito\Activities\PdoActivityStore;
use DxFondito\Activities\PdoReferenceStore;
use DxFondito\Activities\ReferenceService;
use DxFondito\Audit\PdoAuditLog;
use DxFondito\Auth\AccountService;
use DxFondito\Auth\Authenticator;
use DxFondito\Auth\PdoUserStore;
use DxFondito\Auth\PhpSession;
use DxFondito\Controller\ActivityController;
use DxFondito\Controller\AuditController;
use DxFondito\Controller\CertificateController;
use DxFondito\Controller\HealthController;
use DxFondito\Controller\LogController;
use DxFondito\Controller\MigrationController;
use DxFondito\Controller\QslController;
use DxFondito\Controller\RankingController;
use DxFondito\Controller\ReferenceController;
use DxFondito\Controller\RegistryController;
use DxFondito\Controller\SessionController;
use DxFondito\Controller\UserController;
use DxFondito\Database\Connection;
use DxFondito\Database\Migrator;
use DxFondito\Http\HttpException;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Http\Router;
use DxFondito\Logs\LocalFileStore;
use DxFondito\Logs\LogService;
use DxFondito\Logs\PdoLogStore;
use DxFondito\Ranking\PdoRankingStore;
use DxFondito\Registry\CurlHttpClient;
use DxFondito\Registry\PdoLicenseeStore;
use DxFondito\Registry\RegistryService;
use DxFondito\Templates\CertificateService;
use DxFondito\Templates\Fonts;
use DxFondito\Templates\PdoCertificateTemplateStore;
use DxFondito\Templates\PdoQslTemplateStore;
use DxFondito\Templates\QslService;
use DxFondito\Templates\TextRenderer;
use PDO;
use Throwable;

final class App
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $root)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router(Config::load($this->root), $request)->dispatch($request);
        } catch (HttpException $e) {
            return Response::error($e->status, $e->getMessage());
        } catch (Throwable $e) {
            // The browser gets no details. The details go to the error log of the server.
            error_log((string) $e);

            return Response::error(500, 'Internal error');
        }
    }

    private function router(Config $config, Request $request): Router
    {
        // The API opens the database connection only when a request needs it.
        $pdo = fn (): PDO => $this->pdo ??= Connection::open($config);
        $migrator = fn (): Migrator => new Migrator($pdo(), $this->root . '/migrations', $config->storageDir);
        $users = new PdoUserStore($pdo);
        $audit = new PdoAuditLog($pdo);
        $auth = new Authenticator($users, new PhpSession($config->storageDir . '/sessions', $request->secure), $audit);

        $health = new HealthController($migrator);
        $migration = new MigrationController($migrator, $users, $audit, $config->migrationSecret);
        $session = new SessionController($auth);
        $accounts = new UserController($auth, new AccountService($users, $audit));
        $auditRecord = new AuditController($auth, $audit);
        $referenceStore = new PdoReferenceStore($pdo);
        $activityStore = new PdoActivityStore($pdo);
        $logStore = new PdoLogStore($pdo);
        $rankingStore = new PdoRankingStore($pdo);
        $qslTemplates = new PdoQslTemplateStore($pdo);
        $certificateTemplates = new PdoCertificateTemplateStore($pdo);
        $licensees = new PdoLicenseeStore($pdo);
        $fonts = new Fonts($this->root . '/fonts');
        $renderer = new TextRenderer($fonts);
        $templateFiles = new LocalFileStore($config->storageDir . '/templates');
        $ranking = new RankingController($rankingStore, $qslTemplates, $certificateTemplates);
        $references = new ReferenceController($auth, new ReferenceService($referenceStore, $audit));
        $activities = new ActivityController(
            $auth,
            $activityStore,
            new ActivityService($activityStore, $referenceStore, $audit),
            $logStore,
            $rankingStore,
            $qslTemplates,
        );
        $logs = new LogController($auth, new LogService(
            $logStore,
            $activityStore,
            $users,
            new LocalFileStore($config->storageDir . '/logs'),
            $audit,
        ));

        $qsl = new QslController($auth, new QslService(
            $qslTemplates,
            $activityStore,
            $users,
            $rankingStore,
            $templateFiles,
            $renderer,
            $audit,
            $licensees,
        ), $fonts);
        $registries = new RegistryController($auth, new RegistryService(
            $licensees,
            new CurlHttpClient(),
            $audit,
            $this->root . '/certs/sectigo-r36-chain.pem',
        ));
        $certificates = new CertificateController($auth, new CertificateService(
            $certificateTemplates,
            $rankingStore,
            $templateFiles,
            $renderer,
            $audit,
        ));

        $router = new Router();
        $router->add('GET', '/health', fn (): Response => $health->show());
        $router->add('GET', '/migrate', fn (): Response => $migration->form());
        $router->add('POST', '/migrate', fn (Request $request): Response => $migration->run($request));
        $router->add('POST', '/migrate/administrator', fn (Request $request): Response => $migration->createAdministrator($request));
        $router->add('GET', '/session', fn (): Response => $session->show());
        $router->add('POST', '/session', fn (Request $request): Response => $session->signIn($request));
        $router->add('DELETE', '/session', fn (): Response => $session->signOut());
        $router->add('PUT', '/session/password', fn (Request $request): Response => $session->changePassword($request));
        $router->add('GET', '/users', fn (Request $request): Response => $accounts->list($request));
        $router->add('POST', '/users', fn (Request $request): Response => $accounts->create($request));
        $router->add('PUT', '/users/{id}', fn (Request $request, array $params): Response => $accounts->update($request, $params));
        $router->add('PUT', '/users/{id}/password', fn (Request $request, array $params): Response => $accounts->setPassword($request, $params));
        $router->add('GET', '/audit', fn (Request $request): Response => $auditRecord->list($request));
        $router->add('GET', '/seasons', fn (): Response => $activities->seasons());
        $router->add('GET', '/seasons/{season}/ranking', fn (Request $request, array $params): Response => $ranking->ranking($params));
        $router->add('GET', '/participants/{call}', fn (Request $request, array $params): Response => $ranking->participant($params));
        $router->add('GET', '/seasons/{season}/activities', fn (Request $request, array $params): Response => $activities->bySeason($params));
        $router->add('GET', '/activities/{id}', fn (Request $request, array $params): Response => $activities->show($params));
        $router->add('POST', '/activities', fn (Request $request): Response => $activities->create($request));
        $router->add('PUT', '/activities/{id}', fn (Request $request, array $params): Response => $activities->update($request, $params));
        $router->add('DELETE', '/activities/{id}', fn (Request $request, array $params): Response => $activities->delete($request, $params));
        $router->add('POST', '/activities/{id}/logs', fn (Request $request, array $params): Response => $logs->upload($request, $params));
        $router->add('GET', '/activities/{id}/logs', fn (Request $request, array $params): Response => $logs->byActivity($request, $params));
        $router->add('GET', '/logs/{id}', fn (Request $request, array $params): Response => $logs->show($request, $params));
        $router->add('GET', '/logs/{id}/file', fn (Request $request, array $params): Response => $logs->file($request, $params));
        $router->add('DELETE', '/logs/{id}', fn (Request $request, array $params): Response => $logs->delete($request, $params));
        $router->add('GET', '/activities/{id}/qsl-templates', fn (Request $request, array $params): Response => $qsl->list($request, $params));
        $router->add('GET', '/activities/{id}/qsl-templates/{operatorId}/image', fn (Request $request, array $params): Response => $qsl->image($request, $params));
        $router->add('POST', '/activities/{id}/qsl-templates/{operatorId}', fn (Request $request, array $params): Response => $qsl->save($request, $params));
        $router->add('DELETE', '/activities/{id}/qsl-templates/{operatorId}', fn (Request $request, array $params): Response => $qsl->delete($request, $params));
        $router->add('POST', '/template-preview', fn (Request $request): Response => $qsl->preview($request));
        $router->add('GET', '/participants/{call}/qsl/{contactId}', fn (Request $request, array $params): Response => $qsl->card($params));
        $router->add('GET', '/participants/{call}/certificates/{season}/{points}', fn (Request $request, array $params): Response => $certificates->certificate($params));
        $router->add('GET', '/certificate-templates', fn (Request $request): Response => $certificates->list($request));
        $router->add('GET', '/certificate-templates/{season}/{points}/image', fn (Request $request, array $params): Response => $certificates->image($request, $params));
        $router->add('POST', '/certificate-templates/{season}/{points}', fn (Request $request, array $params): Response => $certificates->save($request, $params));
        $router->add('DELETE', '/certificate-templates/{season}/{points}', fn (Request $request, array $params): Response => $certificates->delete($request, $params));
        $router->add('POST', '/certificate-preview', fn (Request $request): Response => $certificates->preview($request));
        $router->add('GET', '/registries', fn (Request $request): Response => $registries->list($request));
        $router->add('POST', '/registries/{country}', fn (Request $request, array $params): Response => $registries->update($request, $params));
        $router->add('GET', '/fonts/{name}', fn (Request $request, array $params): Response => $qsl->font($params));
        $router->add('GET', '/references', fn (Request $request): Response => $references->list($request));
        $router->add('POST', '/references', fn (Request $request): Response => $references->create($request));
        $router->add('PUT', '/references/{id}', fn (Request $request, array $params): Response => $references->update($request, $params));
        $router->add('DELETE', '/references/{id}', fn (Request $request, array $params): Response => $references->delete($request, $params));

        return $router;
    }
}
