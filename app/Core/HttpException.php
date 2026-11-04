<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown anywhere in the app to produce a clean JSON error with a specific HTTP status. */
class HttpException extends \RuntimeException
{
    public function __construct(
        private int $status,
        string $message = '',
        private array $errors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatus(): int { return $this->status; }
    public function getErrors(): array { return $this->errors; }

    public static function badRequest(string $m = 'Bad request.', array $e = []): self { return new self(400, $m, $e); }
    public static function unauthorized(string $m = 'Authentication required.'): self { return new self(401, $m); }
    public static function forbidden(string $m = 'You do not have permission to perform this action.'): self { return new self(403, $m); }
    public static function notFound(string $m = 'Resource not found.'): self { return new self(404, $m); }
    public static function conflict(string $m = 'The request conflicts with the current state.', array $e = []): self { return new self(409, $m, $e); }
    public static function unprocessable(array $errors, string $m = 'Validation failed.'): self { return new self(422, $m, $errors); }
}
