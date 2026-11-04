<?php
declare(strict_types=1);

namespace App\Core;

/** Daily file logger under storage/logs. Redacts sensitive keys; strips newlines (log injection). */
final class Logger
{
    private const SENSITIVE = ['password', 'passwd', 'secret', 'token', 'authorization', 'cookie', 'card', 'cvv', 'csrf', 'api_key'];
    private static ?string $dir = null;

    public static function setDirectory(?string $dir): void { self::$dir = $dir; }

    public static function debug(string $m, array $c = []): void { self::write('DEBUG', $m, $c); }
    public static function info(string $m, array $c = []): void { self::write('INFO', $m, $c); }
    public static function warning(string $m, array $c = []): void { self::write('WARNING', $m, $c); }
    public static function error(string $m, array $c = []): void { self::write('ERROR', $m, $c); }

    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = self::$dir ?? (BASE_PATH . '/storage/logs');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return; // logging must never crash the request
        }
        $message = str_replace(["\r", "\n"], ' ', $message);
        $json    = $context ? ' ' . json_encode(self::redact($context), JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : '';
        $line    = sprintf("[%s] %s %s%s\n", date('Y-m-d H:i:s'), $level, $message, $json);
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redact($value);
                continue;
            }
            foreach (self::SENSITIVE as $needle) {
                if (is_string($key) && stripos($key, $needle) !== false) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }
        }
        return $data;
    }
}
