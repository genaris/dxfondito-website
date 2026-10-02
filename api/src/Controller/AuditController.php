<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use DxFondito\Audit\AuditEntry;
use DxFondito\Audit\AuditLog;
use DxFondito\Auth\Authenticator;
use DxFondito\Http\Request;
use DxFondito\Http\Response;

/**
 * The record of actions for an administrator (FR-AUD-4).
 */
final class AuditController
{
    public const PAGE_SIZE = 50;

    public function __construct(
        private readonly Authenticator $auth,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * The `page` parameter selects a page. Page 1 has the newest entries.
     */
    public function list(Request $request): Response
    {
        $this->auth->requireAdministrator($request);

        $pages = max(1, (int) ceil($this->audit->count() / self::PAGE_SIZE));
        $page = $request->query['page'] ?? '1';
        $page = is_string($page) && ctype_digit($page) ? min(max(1, (int) $page), $pages) : 1;

        return Response::json([
            'entries' => array_map(
                static fn (AuditEntry $entry): array => $entry->publicData(),
                $this->audit->list(($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE),
            ),
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}
