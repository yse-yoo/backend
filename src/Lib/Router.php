<?php

namespace App\Lib;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(string $method, string $path): void
    {
        $path = $this->normalizePath($path);
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $path);

            if ($params === null) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            ($route['handler'])(...$params);
            return;
        }

        if ($pathMatched) {
            Http::error('Method not allowed.', 405);
        }

        Http::error('Endpoint not found.', 404);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->normalizePath($pattern),
            'handler' => $handler,
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (($segments[0] ?? '') === 'pos_2026') {
            array_shift($segments);
        }

        return '/' . implode('/', $segments);
    }

    /**
     * @return array<int, int|string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $patternParts = array_values(array_filter(explode('/', trim($pattern, '/'))));
        $pathParts = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];

        foreach ($patternParts as $index => $patternPart) {
            $pathPart = $pathParts[$index];

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)}$/', $patternPart) === 1) {
                if ($pathPart === '') {
                    return null;
                }

                if ($patternPart === '{id}') {
                    if (!ctype_digit($pathPart)) {
                        Http::error('Invalid resource ID.', 404);
                    }

                    $params[] = (int)$pathPart;
                    continue;
                }

                $params[] = $pathPart;
                continue;
            }

            if ($patternPart !== $pathPart) {
                return null;
            }
        }

        return $params;
    }
}
