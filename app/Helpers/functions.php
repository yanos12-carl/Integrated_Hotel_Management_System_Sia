<?php
declare(strict_types=1);

use App\Core\Config;

/** HTML-escape for output (XSS protection). */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path prefix of the app, e.g. "/hotel-management-system" (empty when served from a domain root). */
function base_path(): string
{
    return rtrim((string) parse_url((string) Config::get('app.url'), PHP_URL_PATH), '/');
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Render resources/views/<name>.php inside an optional layout. */
function view(string $name, array $data = [], ?string $layout = null): string
{
    $render = static function (string $__file, array $__data): string {
        extract($__data, EXTR_SKIP);
        ob_start();
        require $__file;
        return (string) ob_get_clean();
    };
    $content = $render(BASE_PATH . '/resources/views/' . $name . '.php', $data);
    if ($layout === null) {
        return $content;
    }
    return $render(BASE_PATH . '/resources/views/layouts/' . $layout . '.php', $data + ['content' => $content]);
}

// Fallbacks so the app also runs where the mbstring extension is disabled (XAMPP enables it by default).
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int { return (int) preg_match_all('/./us', $s) ?: strlen($s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $enc = null): string
    {
        preg_match_all('/./us', $s, $m);
        $chars = $m[0] ?: str_split($s);
        return implode('', array_slice($chars, $start, $length));
    }
}

/** Inline SVG icons (stroke-based, 24x24). */
function icon(string $name): string
{
    static $p = [
        'home' => 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
        'users' => 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0',
        'building' => 'M4 21V4h10v17M14 9h6v12M8 8h2M8 12h2M8 16h2M17 13h1M17 17h1M2 21h20',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'settings' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'login' => 'M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3',
        'monitor' => 'M3 5h18v11H3zM8 21h8M12 16v5',
        'alert' => 'M12 3l10 18H2zM12 10v5M12 18h.01',
        'check' => 'M20 6L9 17l-5-5',
        'bell' => 'M6 9a6 6 0 0112 0c0 6 2 7 2 8H4c0-1 2-2 2-8zM10 21h4',
        'database' => 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
        'megaphone' => 'M3 11v2a1 1 0 001 1h3l8 5V5L7 10H4a1 1 0 00-1 1zM19 8a5 5 0 010 8',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'trend' => 'M3 17l6-6 4 4 8-8M21 7h-5M21 7v5',
        'key' => 'M15 7a4 4 0 11-3.5 5.9L4 20.4V17h3v-3h3l1.4-1.4A4 4 0 0115 7z',
        'save' => 'M5 3h11l3 3v15H5zM8 3v6h7V3M8 14h8v7H8z',
        'chevron' => 'M15 6l-6 6 6 6',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$name] ?? $p['list']) . '"/></svg>';
}
