<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

use AnimeTadka\Config;
use AnimeTadka\Exceptions\HttpException;
use AnimeTadka\Exceptions\RateLimitedException;
use AnimeTadka\Exceptions\ScraperException;
use AnimeTadka\Scraper\HttpClient;

/**
 * HTTP client tests against a local stub server (tests/stub-server.php).
 */
final class HttpClientTest extends TestCase
{
    private const STUB_URL = 'http://127.0.0.1:8899';

    private ?HttpClient $client = null;
    private mixed $serverProcess = null;
    private array $serverPipes = [];

    public function setUp(): void
    {
        $this->startStubServer();

        $config = new Config([
            'BASE_URL' => self::STUB_URL,
            'HTTP_TIMEOUT' => 5,
            'HTTP_CONNECT_TIMEOUT' => 2,
            'HTTP_MAX_RETRIES' => 1,
            'HTTP_RETRY_BACKOFF_MS' => 100,
            'REQUEST_DELAY_MS' => 0,
            'CACHE_ENABLED' => false,
        ]);

        $this->client = new HttpClient($config);
    }

    public function tearDown(): void
    {
        $this->stopStubServer();
    }

    private function startStubServer(): void
    {
        $cmd = sprintf(
            'php -S 127.0.0.1:8899 %s 2>/dev/null',
            escapeshellarg(dirname(__DIR__) . '/stub-server.php')
        );

        $this->serverProcess = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $this->serverPipes);

        // Wait for the server to accept connections.
        $ready = false;
        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen('127.0.0.1', 8899, $errno, $errstr, 0.2);
            if ($conn !== false) {
                fclose($conn);
                $ready = true;
                break;
            }
            usleep(100_000);
        }

        if (!$ready) {
            $this->fail('Stub server did not start');
        }

        // Reset the request counter.
        @file_get_contents(self::STUB_URL . '/reset');
    }

    private function stopStubServer(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
    }

    public function testGetHtmlSuccess(): void
    {
        $html = $this->client->getHtml(self::STUB_URL . '/ok');
        $this->assertStringContains('Stub OK', $html);
    }

    public function testGetHtml404ThrowsHttpException(): void
    {
        $threw = false;
        try {
            $this->client->getHtml(self::STUB_URL . '/notfound');
        } catch (HttpException $e) {
            $threw = true;
            $this->assertEquals(404, $e->getStatus());
        }
        $this->assertTrue($threw, 'Expected HttpException for 404');
    }

    public function testGetHtml500RetriesThenThrows(): void
    {
        $threw = false;
        try {
            $this->client->getHtml(self::STUB_URL . '/error500');
        } catch (HttpException $e) {
            $threw = true;
            $this->assertEquals(500, $e->getStatus());
        }
        $this->assertTrue($threw, 'Expected HttpException for 500');
    }

    public function testGetHtml429ThrowsRateLimited(): void
    {
        $threw = false;
        try {
            $this->client->getHtml(self::STUB_URL . '/ratelimit');
        } catch (RateLimitedException) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected RateLimitedException for 429');
    }

    public function testGetHtmlTimeoutThrows(): void
    {
        // Dedicated client with a 1s timeout; stub /slow sleeps 3s.
        $config = new Config([
            'BASE_URL' => self::STUB_URL,
            'HTTP_TIMEOUT' => 1,
            'HTTP_CONNECT_TIMEOUT' => 1,
            'HTTP_MAX_RETRIES' => 0,
            'HTTP_RETRY_BACKOFF_MS' => 100,
            'REQUEST_DELAY_MS' => 0,
            'CACHE_ENABLED' => false,
        ]);

        $client = new HttpClient($config);

        $threw = false;
        try {
            $client->getHtml(self::STUB_URL . '/slow');
        } catch (ScraperException) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected ScraperException on timeout');
    }

    public function testGetHtmlRejectsNonHtmlContentType(): void
    {
        $threw = false;
        try {
            $this->client->getHtml(self::STUB_URL . '/malformed');
        } catch (ScraperException) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected ScraperException for non-HTML content type');
    }

    public function testGetHtmlInvalidUrlThrows(): void
    {
        $threw = false;
        try {
            $this->client->getHtml('not-a-url');
        } catch (ScraperException) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected ScraperException for invalid URL');
    }

    public function testCacheServesSecondRequest(): void
    {
        // NOTE: sys_get_temp_dir() is /tmp on this device and is read-only;
        // use a writable project-local cache dir instead.
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache-test';

        $config = new Config([
            'BASE_URL' => self::STUB_URL,
            'HTTP_TIMEOUT' => 5,
            'HTTP_CONNECT_TIMEOUT' => 2,
            'HTTP_MAX_RETRIES' => 1,
            'HTTP_RETRY_BACKOFF_MS' => 100,
            'REQUEST_DELAY_MS' => 0,
            'CACHE_ENABLED' => true,
            'CACHE_DIR' => $cacheDir,
            'CACHE_TTL' => 3600,
        ]);

        $client = new HttpClient($config);

        $first = $client->getHtml(self::STUB_URL . '/count');
        $second = $client->getHtml(self::STUB_URL . '/count');

        $this->assertEquals($first, $second, 'Cached response should equal first response');

        // Counter must be 1: the second request was served from cache.
        // Read the counter file directly — fetching /count would increment it.
        $count = (int) @file_get_contents(dirname(__DIR__) . '/stub-counter.txt');
        $this->assertEquals(1, $count, 'Second request should not hit the server');

        // Cleanup.
        foreach (glob($cacheDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($cacheDir);
    }
}