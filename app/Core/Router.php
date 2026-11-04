<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router. Patterns: /api/v1/guests/{id:\d+}. Handlers: [Controller::class, 'method'] or closure.
 * Middleware are class names with handle(Request): void (throw HttpException to stop).
 */
final class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function get(string $p, mixed $h, array $mw = []): void { $this->add('GET', $p, $h, $mw); }
    public function post(string $p, mixed $h, array $mw = []): void { $this->add('POST', $p, $h, $mw); }
    public function put(string $p, mixed $h, array $mw = []): void { $this->add('PUT', $p, $h, $mw); }
    public function patch(string $p, mixed $h, array $mw = []): void { $this->add('PATCH', $p, $h, $mw); }
    public function delete(string $p, mixed $h, array $mw = []): void { $this->add('DELETE', $p, $h, $mw); }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $oldPrefix = $this->prefix;
        $oldMw     = $this->groupMiddleware;
        $this->prefix .= $prefix;
        $this->groupMiddleware = array_merge($oldMw, $middleware);
        $callback($this);
        $this->prefix = $oldPrefix;
        $this->groupMiddleware = $oldMw;
    }

    private function add(string $method, string $pattern, mixed $handler, array $middleware): void
    {
        $full  = Request::normalize($this->prefix . $pattern);
        $regex = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', static function ($m) {
            return '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
        }, $full);
        $this->routes[] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#',
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['middleware'] as $mw) {
                // "Class" or "Class:arg1,arg2" (e.g. Permission:user.manage)
                $class = str_contains($mw, ':') ? strstr($mw, ':', true) : $mw;
                $args  = str_contains($mw, ':') ? explode(',', substr(strstr($mw, ':'), 1)) : [];
                (new $class(...$args))->handle($request);
            }
            $h = $route['handler'];
            if (is_array($h)) {
                [$class, $method] = $h;
                $result = (new $class())->$method($request, $params);
            } else {
                $result = $h($request, $params);
            }
            if (!$result instanceof Response) {
                throw new \LogicException('Route handlers must return a Response.');
            }
            return $result;
        }
        if ($allowed) {
            return Response::error('Method not allowed.', 405)->withHeader('Allow', implode(', ', array_unique($allowed)));
        }
        return Response::error('Endpoint not found.', 404);
    }
}
