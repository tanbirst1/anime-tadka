<?php
/**
 * URL and asset helpers
 */

class Url {
    public static function base($path = '') {
        $base = Config::get('APP_URL', '');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    public static function asset($path) {
        return self::base('public/assets/' . ltrim($path, '/'));
    }

    public static function css($file) {
        return self::asset("css/{$file}");
    }

    public static function js($file) {
        return self::asset("js/{$file}");
    }

    public static function image($path) {
        return self::asset("images/{$path}");
    }

    public static function current() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    public static function path() {
        return '/' . trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    }

    public static function is($pattern) {
        $current = self::path();
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        return (bool) preg_match($regex, $current);
    }

    public static function redirect($url) {
        header("Location: {$url}");
        exit;
    }

    public static function back() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }
}
