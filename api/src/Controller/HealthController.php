<?php

declare(strict_types=1);

namespace DxFondito\Controller;

use Closure;
use DxFondito\Database\Migrator;
use DxFondito\Http\Response;
use PDOException;

final class HealthController
{
    /**
     * @param Closure(): Migrator $migrator
     */
    public function __construct(private readonly Closure $migrator)
    {
    }

    public function show(): Response
    {
        try {
            $schema = ($this->migrator)()->latest();
            $database = true;
        } catch (PDOException $e) {
            error_log('Health check: ' . $e->getMessage());
            $schema = null;
            $database = false;
        }

        return Response::json([
            'status' => 'ok',
            'database' => $database,
            'schema' => $schema,
        ]);
    }
}
