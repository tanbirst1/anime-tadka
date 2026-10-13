<?php
/**
 * Simple router — maps URL paths to controller actions
 */

class Router {
    private $routes = [];
    private $middleware = [];
    private $compiled = [];

    public function get($path, $handler, $middleware = []) {
        $this->routes['GET'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        $this->compiled = [];
    }

    public function post($path, $handler, $middleware = []) {
        $this->routes['POST'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        $this->compiled = [];
    }

    private function compilePatterns() {
        if (!empty($this->compiled)) {
            return;
        }
        foreach ($this->routes as $method => $patterns) {
            $this->compiled[$method] = [];
            foreach ($patterns as $pattern => $route) {
                $regex = preg_replace_callback('#\{(\w+)(?::([^}]+))?\}#', function($m) {
                    $name = $m[1];
                    $pat = $m[2] ?? '[^/]+';
                    return "(?P<$name>$pat)";
                }, $pattern);
                $this->compiled[$method][$pattern] = [
                    'regex' => '#^' . $regex . '$#',
                    'route' => $route,
                ];
            }
        }
    }

    public function match($method, $uri) {
        $this->compilePatterns();
        $method = strtoupper($method);
        // HEAD must behave like GET without a body (media probing, players).
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');
        
        // Exact match
        if (isset($this->compiled[$method][$uri])) {
            return $this->compiled[$method][$uri]['route'];
        }

        // Pattern match with parameters
        if (isset($this->compiled[$method])) {
            foreach ($this->compiled[$method] as $pattern => $entry) {
                if (preg_match($entry['regex'], $uri, $matches)) {
                    $route = $entry['route'];
                    $route['params'] = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    return $route;
                }
            }
        }

        return null;
    }

    public function dispatch($method, $uri) {
        $route = $this->match($method, $uri);
        
        if (!$route) {
            http_response_code(404);
            require dirname(dirname(__DIR__)) . '/app/Views/errors/404.php';
            return;
        }

        // Run middleware
        foreach ($route['middleware'] as $mw) {
            if (is_callable($mw)) {
                $result = $mw();
                if ($result === false) return;
            }
        }

        // Resolve handler
        $handler = $route['handler'];
        $params = $route['params'] ?? [];

        if (is_string($handler)) {
            list($controllerName, $method) = explode('@', $handler);
            $controllerFile = dirname(__DIR__) . "/app/Controllers/{$controllerName}.php";
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
                $controller = new $controllerName();
                call_user_func_array([$controller, $method], $params);
            } else {
                http_response_code(500);
                echo "Controller not found: $controllerName";
            }
        } elseif (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            $controllerName = $handler[0];
            $methodName = $handler[1];
            if (!class_exists($controllerName)) {
                $controllerFile = dirname(__DIR__) . "/Controllers/{$controllerName}.php";
                if (is_file($controllerFile)) {
                    require_once $controllerFile;
                }
            }
            if (class_exists($controllerName)) {
                $controller = new $controllerName();
                call_user_func_array([$controller, $methodName], array_values($params));
            } else {
                http_response_code(500);
                echo "Controller not found: $controllerName";
            }
        } elseif (is_callable($handler)) {
            call_user_func_array($handler, array_values($params));
        }
    }
}
