<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Local alternative to SMTP: messages are appended to storage/private/mail-outbox.log (not web-accessible).
 * Replace send() with PHPMailer/SMTP in production without touching callers.
 */
final class MailOutbox
{
    public static function send(string $to, string $subject, string $body): void
    {
        $dir = BASE_PATH . '/storage/private';
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        $entry = sprintf("=== %s ===\nTo: %s\nSubject: %s\n\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, $body);
        @file_put_contents($dir . '/mail-outbox.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
