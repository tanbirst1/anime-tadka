<?php
/**
 * Configuration loader — reads .env and provides config access
 */

class Config {
    private static $data = [];
    private static $loaded = false;

    public static function load($path = null) {
        if (self::$loaded) return;
        
        $path = $path ?: dirname(dirname(__DIR__)) . '/.env';
        if (!file_exists($path)) {
            throw new RuntimeException("No .env file found at: $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            
            // Remove quotes
            if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value)-1] === '"') {
                $value = substr($value, 1, -1);
            }
            
            self::$data[$key] = $value;
            putenv("$key=$value");
        }

        // Load the global site config (config/config.php) on top of .env.
        // Values here are available via Config::get('site_name'), etc.
        $configFile = dirname(dirname(__DIR__)) . '/config/config.php';
        if (file_exists($configFile)) {
            $siteConfig = require $configFile;
            if (is_array($siteConfig)) {
                foreach ($siteConfig as $key => $value) {
                    self::$data[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        if (!self::$loaded) self::load();
        return self::$data[$key] ?? getenv($key) ?: $default;
    }

    public static function set($key, $value) {
        self::$data[$key] = $value;
    }

    public static function all() {
        if (!self::$loaded) self::load();
        return self::$data;
    }

    public static function has($key) {
        if (!self::$loaded) self::load();
        return isset(self::$data[$key]) || getenv($key) !== false;
    }
}
