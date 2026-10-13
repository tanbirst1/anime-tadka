<?php

declare(strict_types=1);

namespace AnimeTadka\Api;

/**
 * Minimal REST router with {param} placeholders.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern: string, regex: string, handler: callable}>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static function (array $m): string {
                $inner = isset($m[2]) && $m[2] !== '' ? $m[2] : '[^/]+';
                return '(?P<' . $m[1] . '>' . $inner . ')';
            },
            $pattern
        );
        $regex = '#^' . str_replace('#', '\#', $regex) . '$#';

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    /**
     * @param array<string, string> $query
     */
    public function dispatch(string $method, string $path, array $query = []): Response
    {
        $path = '/' . trim($path, '/');

        $methodRoutes = $this->routes[$method] ?? [];

        foreach ($methodRoutes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];
                return $handler($params, $query);
            }
        }

        // Method not allowed but path exists?
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path)) {
                    return Response::error('METHOD_NOT_ALLOWED', "Method {$method} not allowed for this route", 405);
                }
            }
        }

        return Response::error('NOT_FOUND', 'Route not found', 404);
    }
}