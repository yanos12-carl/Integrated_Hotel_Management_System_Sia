<?php
declare(strict_types=1);

namespace App\Core;

/** Central error handling: clients get a safe message + error_id; details go to storage/logs only. */
final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
        set_exception_handler([self::class, 'handle']);
        register_shutdown_function(static function (): void {
            $e = error_get_last();
            if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $id = bin2hex(random_bytes(6));
                Logger::error('Fatal error', ['error_id' => $id, 'message' => $e['message'], 'file' => $e['file'], 'line' => $e['line']]);
                if (!headers_sent()) {
                    Response::error('An unexpected error occurred.', 500, [], ['error_id' => $id])->send();
                }
            }
        });
    }

    public static function toResponse(\Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            return Response::error($e->getMessage() ?: 'Request failed.', $e->getStatus(), $e->getErrors());
        }
        $id = bin2hex(random_bytes(6));
        Logger::error('Unhandled exception', [
            'error_id' => $id,
            'class'    => $e::class,
            'message'  => $e->getMessage(),
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
        ]);
        try {   // best effort: lets Admin see errors; never allowed to fail the response
            Database::execute('INSERT INTO error_logs (error_id, level, message, context, method, path, user_id) VALUES (?,?,?,?,?,?,?)', [
                $id, 'error', mb_substr($e->getMessage(), 0, 500),
                json_encode(['class' => $e::class, 'file' => basename($e->getFile()), 'line' => $e->getLine()]),
                Context::get('method'), mb_substr((string) Context::get('path'), 0, 255), Context::get('user_id'),
            ]);
        } catch (\Throwable) {}
        $extra = ['error_id' => $id];
        if (Config::get('app.debug', false) === true) {
            $extra['debug'] = ['class' => $e::class, 'message' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()];
        }
        return Response::error('An unexpected error occurred.', 500, [], $extra);
    }

    public static function handle(\Throwable $e): void
    {
        self::toResponse($e)->send();
    }
}
