<?php
namespace App\Support;

class Config
{
    private static array $config = [];

    public static function get(string $key, $default = null)
    {
        if (empty(self::$config)) {
            self::$config = require PROJECT_ROOT . '/env.php';
        }

        return self::$config[$key] ?? $default;
    }
}
