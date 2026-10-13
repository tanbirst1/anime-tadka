<?php
/**
 * Simple template engine — renders PHP views with variable binding
 */

class View {
    private static $shared = [];
    private static $viewCache = [];
    private static $layoutCache = [];

    public static function share($key, $value) {
        self::$shared[$key] = $value;
    }

    public static function render($view, $data = [], $layout = null) {
        $data = array_merge(self::$shared, $data);
        extract($data);

        // Dynamic HTML must never be heuristically cached: player/EP markup
        // changed across releases and stale copies kept old (broken) embeds
        // alive. Media/API have their own cache headers; this is HTML only.
        if (!headers_sent()) {
            header('Cache-Control: no-store, must-revalidate');
        }
        
        $viewPath = self::resolveView($view);
        if ($viewPath === null) {
            $content = "<!-- View not found: {$view} -->";
        } else {
            ob_start();
            require $viewPath;
            $content = ob_get_clean();
        }

        if ($layout) {
            $layoutPath = self::resolveLayout($layout);
            ob_start();
            require $layoutPath;
            $output = ob_get_clean();
            echo $output;
        } else {
            echo $content;
        }
    }

    private static function resolveView($view) {
        if (isset(self::$viewCache[$view])) {
            return self::$viewCache[$view];
        }
        $path = dirname(__DIR__) . "/Views/{$view}.php";
        if (file_exists($path)) {
            self::$viewCache[$view] = $path;
            return $path;
        }
        self::$viewCache[$view] = null;
        return null;
    }

    private static function resolveLayout($layout) {
        if (isset(self::$layoutCache[$layout])) {
            return self::$layoutCache[$layout];
        }
        $path = dirname(__DIR__) . "/Views/layouts/{$layout}.php";
        self::$layoutCache[$layout] = $path;
        return $path;
    }

    public static function partial($view, $data = []) {
        $data = array_merge(self::$shared, $data);
        extract($data);
        
        $viewPath = dirname(__DIR__) . "/Views/partials/{$view}.php";
        if (file_exists($viewPath)) {
            require $viewPath;
        }
    }

    public static function component($name, $data = []) {
        self::partial("components/{$name}", $data);
    }

    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Section-aware URL: prefixes site-section base (e.g. '/hentai') when
     * rendering inside a provider section, passthrough otherwise.
     */
    public static function hurl($path) {
        $base = $GLOBALS['__link_base'] ?? '';
        if ($base === '' || !is_string($path) || !str_starts_with($path, '/')) {
            return $path;
        }
        return $base . $path;
    }
}
