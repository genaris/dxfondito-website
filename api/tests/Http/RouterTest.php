<?php

declare(strict_types=1);

namespace DxFondito\Tests\Http;

use DxFondito\Http\HttpException;
use DxFondito\Http\Request;
use DxFondito\Http\Response;
use DxFondito\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testCallsTheHandlerOfTheRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/health', fn (): Response => Response::json(['status' => 'ok']));

        $response = $router->dispatch(new Request('GET', '/health'));

        self::assertSame(200, $response->status);
        self::assertSame('{"status":"ok"}', $response->body);
    }

    public function testGivesTheParametersToTheHandler(): void
    {
        $router = new Router();
        $router->add(
            'GET',
            '/seasons/{season}/ranking',
            fn (Request $request, array $params): Response => Response::json($params),
        );

        $response = $router->dispatch(new Request('GET', '/seasons/2026/ranking'));

        self::assertSame('{"season":"2026"}', $response->body);
    }

    public function testSelectsTheRouteByItsMethod(): void
    {
        $router = new Router();
        $router->add('GET', '/migrate', fn (): Response => Response::json('form'));
        $router->add('POST', '/migrate', fn (): Response => Response::json('run'));

        $response = $router->dispatch(new Request('POST', '/migrate'));

        self::assertSame('"run"', $response->body);
    }

    public function testRefusesAnUnknownPath(): void
    {
        $router = new Router();
        $router->add('GET', '/health', fn (): Response => Response::json([]));

        try {
            $router->dispatch(new Request('GET', '/health/more'));
            self::fail('The router accepted an unknown path.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }

    public function testRefusesAnIncorrectMethod(): void
    {
        $router = new Router();
        $router->add('GET', '/health', fn (): Response => Response::json([]));

        try {
            $router->dispatch(new Request('DELETE', '/health'));
            self::fail('The router accepted an incorrect method.');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
        }
    }
}
