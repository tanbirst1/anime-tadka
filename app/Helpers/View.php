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

    /**
     * Content link carrying an explicit type definer. Every card/anchor that
     * points at a title says what it opens via a `?type=` query string
     * (episode|series|movie) so the controllers never have to guess.
     *
     * Card slugs are provider-specific: some are bare ("jojos-1x2"), others
     * already encode a route prefix and type ("ep/jojos?type=episode"). This
     * normalizes both shapes before rebuilding the URL.
     *
     * @param string $base  section-relative prefix, e.g. '/ep/' or '/'
     * @param string $type  raw content type ('episode', 'movies', 'series', …)
     * @param string $slug  title slug (possibly pre-decorated)
     */
    public static function typeHref($base, $type, $slug) {
        $slug = preg_replace('/\?.*$/', '', (string) $slug);
        $slug = preg_replace('#^ep/#', '', $slug);
        $path = self::hurl(rtrim((string) $base, '/') . '/' . ltrim($slug, '/'));
        $t = self::normalizeType($type);
        return $t === '' ? $path : $path . '?type=' . $t;
    }

    /** Collapse a raw content type to one of episode|series|movie ('' if unknown). */
    public static function normalizeType($type) {
        $t = strtolower(trim((string) $type));
        if ($t === '') return '';
        if (str_starts_with($t, 'movie')) return 'movie';
        if ($t === 'episode' || $t === 'ep' || $t === 'epi') return 'episode';
        if ($t === 'series' || $t === 'serie' || $t === 'tv') return 'series';
        return '';
    }
}
