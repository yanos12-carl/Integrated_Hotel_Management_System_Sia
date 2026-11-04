<?php
declare(strict_types=1);

namespace App\Core;

/** Dot-notation access to config/*.php, e.g. Config::get('database.host'). */
final class Config
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file  = array_shift($parts);
        if (!isset(self::$cache[$file])) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            self::$cache[$file] = is_file($path) ? require $path : [];
        }
        $value = self::$cache[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
