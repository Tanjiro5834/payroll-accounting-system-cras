<?php
namespace App\Config;

class Config
{
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) return;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            [$key, $value] = explode('=', $line, 2) + [null, null];
            if ($key) self::$values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    public static function get(string $key, $default = null)
    {
        return self::$values[$key] ?? $default;
    }
}