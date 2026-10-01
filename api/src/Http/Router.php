<?php

declare(strict_types=1);

namespace DxFondito\Http;

use Closure;

final class Router
{
    /** @var list<array{method: string, regex: string, handler: Closure}> */
    private array $routes = [];

    /**
     * A pattern can have parameters in braces, such as /seasons/{season}/ranking.
     * The handler gets the request and the values of the parameters.
     *
     * @param Closure(Request, array<string, string>): Response $handler
     */
    public function add(string $method, string $pattern, Closure $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $pathFound = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            $pathFound = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, $params);
        }

        if ($pathFound) {
            throw new HttpException(405, 'Method not allowed');
        }
        throw new HttpException(404, 'Not found');
    }
}
