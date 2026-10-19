<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

use AnimeTadka\Config;
use AnimeTadka\Scraper\HttpClient;

/**
 * HTTP client that serves saved HTML fixtures instead of the network.
 * Used by integration tests so they run offline and deterministically.
 */
final class FixtureHttpClient extends HttpClient
{
    private string $fixturesDir;

    public function __construct(string $fixturesDir, Config $config)
    {
        parent::__construct($config);
        $this->fixturesDir = rtrim($fixturesDir, '/');
    }

    /**
     * @param array<string, string> $data
     * @param array<string, mixed>  $options
     * @return array{0: int, 1: array<string, string>, 2: string} [status, headers, body]
     */
    protected function fetch(string $method, string $url, array $data, array $options): array
    {
        $file = $this->resolveFixture($url, $data);

        if ($file === null) {
            throw new \AnimeTadka\Exceptions\NotFoundException("Fixture not found for URL: {$url}");
        }

        $body = (string) file_get_contents($file);

        return [200, ['content-type' => 'text/html; charset=UTF-8'], $body];
    }

    public function getMulti(array $urls, array $options = []): array
    {
        $results = [];
        foreach ($urls as $url) {
            try {
                [$status, $headers, $body] = $this->fetch('GET', $url, [], $options);
                $results[$url] = $status >= 400 ? null : $body;
            } catch (\Throwable $e) {
                $results[$url] = null;
            }
        }
        return $results;
    }

    public function postMulti(string $url, array $payloads, array $options = []): array
    {
        $out = [];
        foreach ($payloads as $i => $data) {
            try {
                [$status, $headers, $body] = $this->fetch('POST', $url, $data, $options);
                $out[$i] = $status >= 400 ? null : $body;
            } catch (\Throwable $e) {
                $out[$i] = null;
            }
        }
        return $out;
    }

    private function resolveFixture(string $url, array $data = []): ?string
    {
        $dir = $this->fixturesDir;

        if (str_contains($url, 'admin-ajax.php')) {
            $action = $data['action'] ?? '';
            if ($action === 'get_top_animes') {
                $range = $data['range'] ?? 'day';
                return $dir . '/top-' . $range . '.html';
            }
            return $dir . '/search.html';
        }
        if (str_contains($url, '/home')) {
            return $dir . '/home.html';
        }
        if (str_contains($url, '/redirect/download/')) {
            return $dir . '/download.html';
        }
        if (str_contains($url, '/watch.php')) {
            return $dir . '/watch.html';
        }
        if (str_contains($url, '/ep/')) {
            return $dir . '/episode.html';
        }
        if (str_contains($url, '/movie/')) {
            return $dir . '/movie.html';
        }
        // Movie detail pages (bare /{slug}/) carry the "WATCH MOVIE NOW"
        // button the scraper follows to the /movie/{hash}/ watch page.
        if (str_contains($url, '-full-movie-')) {
            return $dir . '/movie-detail.html';
        }
        if (str_contains($url, '/type/series')) {
            return $dir . '/category-series.html';
        }
        if (str_contains($url, '/lang/hindi')) {
            return $dir . '/category-hindi.html';
        }
        if (str_contains($url, '/genre/action')) {
            return $dir . '/category-action.html';
        }

        // Default: anime detail page.
        return $dir . '/anime.html';
    }
}