<?php
declare(strict_types=1);

namespace App\Core;

/** Consistent API envelope: {success, message, data, meta} or {success:false, message, errors}. */
final class Response
{
    private function __construct(
        public int $status,
        public ?array $payload,
        public array $headers = [],
        public ?string $html = null,
        public ?string $filePath = null
    ) {}

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): self
    {
        $payload = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }
        return new self($status, $payload);
    }

    public static function created(mixed $data = null, string $message = 'Created'): self
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public static function error(string $message, int $status = 400, array $errors = [], array $extra = []): self
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors) {
            $payload['errors'] = $errors;
        }
        return new self($status, $payload + $extra);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, null, [], $html);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self($status, null, ['Location' => $url]);
    }

    public static function file(string $path, string $downloadName): self
    {
        return new self(200, null, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) . '"',
            'Content-Length' => (string) filesize($path),
        ], null, $path);
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            header_remove('X-Powered-By');
            http_response_code($this->status);
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: no-referrer');
            header('Cache-Control: no-store');
            foreach ($this->headers as $n => $v) {
                header($n . ': ' . $v);
            }
            if ($this->html !== null) {
                header('Content-Type: text/html; charset=utf-8');
            } elseif ($this->payload !== null) {
                header('Content-Type: application/json; charset=utf-8');
            }
        }
        if ($this->filePath !== null) {
            readfile($this->filePath);
        } elseif ($this->html !== null) {
            echo $this->html;
        } elseif ($this->payload !== null) {
            echo json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
    }
}
