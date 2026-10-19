<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

use AnimeTadka\Bootstrap;
use AnimeTadka\Config;
use AnimeTadka\Scraper\AnimeTadkaScraper;

/**
 * End-to-end API tests: dispatch requests through the real Router with a
 * fixture-backed scraper. No network access, no server process needed.
 */
final class ApiTest extends TestCase
{
    private \AnimeTadka\Api\Router $router;

    public function setUp(): void
    {
        $config = new Config([
            'BASE_URL' => 'https://animetadka.com',
            'CACHE_ENABLED' => false,
            'REQUEST_DELAY_MS' => 0,
            'LOG_DIR' => sys_get_temp_dir() . '/animetadka-test-logs',
        ]);

        $http = new FixtureHttpClient(dirname(__DIR__) . '/fixtures', $config);
        $scraper = new AnimeTadkaScraper($http, $config);
        $this->router = Bootstrap::router($config, $scraper);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function dispatch(string $method, string $path, array $query = []): array
    {
        $response = $this->router->dispatch($method, $path, $query);
        return ['status' => $response->getStatus(), 'body' => $response->getBody()];
    }

    public function testHealth(): void
    {
        $result = $this->dispatch('GET', '/api/health');
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('ok', $result['body']['data']['status']);
    }

    public function testHome(): void
    {
        $result = $this->dispatch('GET', '/api/home');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['spotlight']);
        $this->assertNotEmpty($result['body']['data']['latest']['all']);
        // Top-10 with day/week/month ranges.
        foreach (['day', 'week', 'month'] as $range) {
            $this->assertNotEmpty($result['body']['data']['top'][$range], "top.{$range} should have items");
        }
    }

    public function testTopEndpoint(): void
    {
        $result = $this->dispatch('GET', '/api/top', ['range' => 'day']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('day', $result['body']['data']['range']);
        $this->assertGreaterThanOrEqual(10, count($result['body']['data']['items']));
    }

    public function testTopInvalidRange(): void
    {
        $result = $this->dispatch('GET', '/api/top', ['range' => 'year']);
        $this->assertEquals(404, $result['status']);
    }

    public function testLatestAll(): void
    {
        $result = $this->dispatch('GET', '/api/latest', ['type' => 'all']);
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['items']);
    }

    public function testLatestSeries(): void
    {
        $result = $this->dispatch('GET', '/api/latest', ['type' => 'series']);
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['items']);
    }

    public function testLatestInvalidType(): void
    {
        $result = $this->dispatch('GET', '/api/latest', ['type' => 'bogus']);
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('error', $result['body']['status']);
    }

    public function testSearch(): void
    {
        $result = $this->dispatch('GET', '/api/search', ['q' => 'naruto']);
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['results']);
    }

    public function testSearchMissingQuery(): void
    {
        $result = $this->dispatch('GET', '/api/search');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('MISSING_QUERY', $result['body']['error']['code']);
    }

    public function testSearchTooShort(): void
    {
        $result = $this->dispatch('GET', '/api/search', ['q' => 'n']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('QUERY_TOO_SHORT', $result['body']['error']['code']);
    }

    public function testAnimeDetail(): void
    {
        $result = $this->dispatch('GET', '/api/anime/naruto-shippuden-hindi-dubbed');
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Naruto Shippūden', $result['body']['data']['title']);
        $this->assertNotEmpty($result['body']['data']['seasons']);
    }

    public function testAnimeEpisodes(): void
    {
        $result = $this->dispatch('GET', '/api/anime/naruto-shippuden-hindi-dubbed/episodes');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['seasons']);
    }

    public function testMovieDetailReturnsServers(): void
    {
        // A movie detail slug has no season blocks: the scraper must follow
        // the page's "WATCH MOVIE NOW" button to the /movie/{hash}/ watch page
        // so servers and download links are returned (not an empty detail).
        $result = $this->dispatch('GET', '/api/anime/the-monkey-king-reborn-hindi-dubbed-multi-audio-bluray-full-movie-watch-online-download');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertNotEmpty($data['servers']);
        $this->assertNotEmpty($data['download_links']);
        $this->assertStringContains('/redirect/download/', (string) $data['download_links'][0]['url']);
    }

    public function testEpisode(): void
    {
        $result = $this->dispatch('GET', '/api/episode/35278977b149');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertNotEmpty($data['servers']);

        $this->assertNotEmpty($data['player_source']);
        $this->assertTrue(str_starts_with((string) $data['player_source'], 'https://'));

        foreach ($data['servers'] as $server) {
            $this->assertNotEmpty($server['source']);
            $this->assertTrue(str_starts_with((string) $server['source'], 'https://'));
            $this->assertArrayHasKey('source_extracted', $server);
        }
    }

    public function testMovie(): void
    {
        $result = $this->dispatch('GET', '/api/movie/34d9ed3a8bd4');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertNotEmpty($data['servers']);

        // The movie fixture's player_url uses the unsupported "toonplay"
        // server, so player_source stays null; supported server entries are
        // still enriched with resolved sources.
        $this->assertArrayHasKey('player_source', $data);
        $this->assertArrayHasKey('player_source_extracted', $data);

        $resolved = 0;
        foreach ($data['servers'] as $server) {
            $this->assertArrayHasKey('source', $server);
            $this->assertArrayHasKey('source_extracted', $server);
            if (!empty($server['source'])) {
                $resolved++;
                $this->assertTrue(str_starts_with((string) $server['source'], 'https://'));
            }
        }
        $this->assertGreaterThanOrEqual(1, $resolved);
    }

    public function testCategorySeries(): void
    {
        $result = $this->dispatch('GET', '/api/category/type/series');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['items']);
    }

    public function testCategoryHindi(): void
    {
        $result = $this->dispatch('GET', '/api/category/lang/hindi');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['items']);
    }

    public function testCategoryGenre(): void
    {
        $result = $this->dispatch('GET', '/api/category/genre/action');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['items']);
    }

    public function testCategoryInvalidType(): void
    {
        $result = $this->dispatch('GET', '/api/category/bogus/whatever');
        $this->assertEquals(404, $result['status']);
    }

    public function testLanguages(): void
    {
        $result = $this->dispatch('GET', '/api/languages');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['languages']);
    }

    public function testGenres(): void
    {
        $result = $this->dispatch('GET', '/api/genres');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['genres']);
    }

    public function testStudios(): void
    {
        $result = $this->dispatch('GET', '/api/studios');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['studios']);
    }

    public function testExtra(): void
    {
        $result = $this->dispatch('GET', '/api/extra');
        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['body']['data']['menu']);
        $this->assertStringContains('animetadka.com', (string) $result['body']['data']['header_logo']);
        $this->assertStringContains('animetadka.com', (string) $result['body']['data']['site_logo']);
        $this->assertStringContains('animetadka.com', (string) $result['body']['data']['favicon']);
    }

    public function testUnknownRoute(): void
    {
        $result = $this->dispatch('GET', '/api/does-not-exist');
        $this->assertEquals(404, $result['status']);
    }

    public function testErrorEnvelopeShape(): void
    {
        $result = $this->dispatch('GET', '/api/search');
        $body = $result['body'];

        $this->assertEquals('error', $body['status']);
        $this->assertArrayHasKey('error', $body);
        $this->assertArrayHasKey('code', $body['error']);
        $this->assertArrayHasKey('message', $body['error']);
        $this->assertArrayHasKey('timestamp', $body);
    }

    public function testSuccessEnvelopeShape(): void
    {
        $result = $this->dispatch('GET', '/api/health');
        $body = $result['body'];

        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('timestamp', $body);
    }

    // --------------------------------------------------------------- Proxy

    public function testProxyWatch(): void
    {
        $result = $this->dispatch('GET', '/api/proxy/watch', [
            'server' => 'toonx',
            'hash' => '49182f81e6a13cf5eaa496d51fea6406',
        ]);

        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals('https://as-cdn21.top/video/49182f81e6a13cf5eaa496d51fea6406', $data['source']);
        $this->assertTrue($data['extracted']);
        $this->assertStringContains('watch.php?toonx=', (string) $data['watch_url']);
    }

    public function testProxyWatchInvalidServer(): void
    {
        $result = $this->dispatch('GET', '/api/proxy/watch', [
            'server' => 'bogus',
            'hash' => 'abc',
        ]);

        $this->assertEquals(400, $result['status']);
    }

    public function testProxyWatchInvalidUrl(): void
    {
        $result = $this->dispatch('GET', '/api/proxy/watch', [
            'url' => 'https://evil.com/watch.php?thugs=abc',
        ]);

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('INVALID_URL', $result['body']['error']['code']);
    }

    public function testProxyDownload(): void
    {
        $result = $this->dispatch('GET', '/api/proxy/download', [
            'url' => 'https://animetadka.com/redirect/download/?dd=5933e',
        ]);

        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals('page', $data['kind']);
        $this->assertStringContains('Jujutsu Kaisen', (string) $data['filename']);
        $this->assertGreaterThanOrEqual(3, count($data['mirrors']));
        $this->assertEquals('Voe DL', $data['mirrors'][0]['name']);
    }

    public function testProxyDownloadInvalidHost(): void
    {
        $result = $this->dispatch('GET', '/api/proxy/download', [
            'url' => 'https://evil.com/redirect/download/?dd=5933e',
        ]);

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('INVALID_URL', $result['body']['error']['code']);
    }
}