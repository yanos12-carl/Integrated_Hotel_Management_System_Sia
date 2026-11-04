<?php
declare(strict_types=1);

namespace App\Core;

/** Immutable-ish view of the incoming HTTP request. */
final class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $body = [],
        public array $headers = [],
        public string $ip = '0.0.0.0',
        public array $attributes = []   // filled by middleware, e.g. 'user'
    ) {
        $this->method = strtoupper($method);
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri  = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $candidates = [$base];
        if (str_ends_with($base, '/public')) {
            $candidates[] = substr($base, 0, -7);   // works with and without root .htaccess rewrite
        }
        foreach ($candidates as $b) {
            if ($b !== '' && ($uri === $b || str_starts_with($uri, $b . '/'))) {
                $uri = substr($uri, strlen($b));
                break;
            }
        }

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($k, 5))] = $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $body = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $raw = file_get_contents('php://input') ?: '';
            if (strlen($raw) > 1_048_576) {
                throw new HttpException(413, 'Request body too large.');
            }
            if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (!is_array($decoded)) {
                        throw HttpException::badRequest('Malformed JSON body.');
                    }
                    $body = $decoded;
                }
            } else {
                $body = $_POST;
            }
        }

        return new self($method, self::normalize($uri), $_GET, $body, $headers, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function attr(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
