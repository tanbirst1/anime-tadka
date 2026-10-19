<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

use AnimeDekhoApi\Core\Config as AnimeConfig;
use AnimeDekhoApi\Providers\AnimeDekho;
use AnimeDekhoApi\Utils\HttpClient;

/**
 * Regression coverage for the src-extraction speed-up.
 *
 * Every watch page renders only the active server's direct player URL; the
 * other tabs resolve lazily through /player/server-src. That probe used to
 * re-walk the whole wrapper chain on every tap. resolveWrapperSrc() now caches
 * a wrapper's resolved player URL in a 15-minute file cache, so repeated
 * lookups cost zero upstream requests while the mapping is fresh.
 *
 * The gate stub counts /player.php hits, so this test fails if the cache is
 * removed or stops being consulted.
 */
final class AnimeDekhoWrapperSrcCacheTest extends TestCase
{
    private const STUB_PORT = 8903;
    private const STUB_URL = 'http://127.0.0.1:8903';
    private const STUB_DWELL = 2;

    private mixed $serverProcess = null;
    private array $serverPipes = [];
    private string $cookieFile = '';
    private string $counterFile = '';

    public function setUp(): void
    {
        $this->cookieFile = dirname(__DIR__, 2) . '/storage/cache/anime-srccache-test-cookies.txt';
        $this->counterFile = __DIR__ . '/../stub-wrapper-counter.txt';
        $this->startStub();
        register_shutdown_function(fn () => $this->stopStub());

        putenv('ANIMEDEKHO_BASE_URL=' . self::STUB_URL);
        putenv('ANIMEDEKHO_COOKIE_FILE=' . $this->cookieFile);
        putenv('ANIMEDEKHO_GATE_DWELL=' . (self::STUB_DWELL + 1));

        @unlink($this->cookieFile);
        @unlink($this->counterFile);
        $this->clearWrapperSrcCache();
    }

    public function tearDown(): void
    {
        putenv('ANIMEDEKHO_BASE_URL');
        putenv('ANIMEDEKHO_COOKIE_FILE');
        putenv('ANIMEDEKHO_GATE_DWELL');
        @unlink($this->cookieFile);
        @unlink($this->counterFile);
        $this->clearWrapperSrcCache();
        $this->stopStub();
    }

    /**
     * Resolving the same wrapper twice must fetch it once: the second call is
     * served from cache (memo within the request, file cache across requests).
     */
    public function testResolvingTheSameWrapperTwiceFetchesItOnce(): void
    {
        $provider = $this->provider();
        $wrapper = self::STUB_URL . '/player.php?url=AAA';

        $first = $provider->resolveWrapperSrc($wrapper);
        $this->assertEquals('https://abyssplayer.com/m5bXILRqW', $first, 'the wrapper resolves to the external player');
        $afterFirst = (int) @file_get_contents($this->counterFile);
        $this->assertEquals(1, $afterFirst, 'the first resolve fetches the wrapper once');

        // A brand-new provider instance simulates the next /player/server-src
        // request; the file cache must serve it without touching upstream.
        $second = $this->provider()->resolveWrapperSrc($wrapper);
        $this->assertEquals($first, $second, 'the cached resolve returns the same src');
        $this->assertEquals(
            1,
            (int) @file_get_contents($this->counterFile),
            'the second resolve is served from cache — no extra upstream fetch'
        );
    }

    /** A non-wrapper URL is never fetched and never cached. */
    public function testNonWrapperSrcIsNotFetched(): void
    {
        @unlink($this->counterFile);
        $provider = $this->provider();
        $this->assertNull($provider->resolveWrapperSrc('https://vidmoly.biz/embed-x.html'));
        $this->assertEquals(0, (int) @file_get_contents($this->counterFile), 'a direct player URL is not a wrapper');
    }

    private function clearWrapperSrcCache(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/animedekho/storage/cache/wrapper_src_*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function provider(): AnimeDekho
    {
        require_once dirname(__DIR__, 2) . '/animedekho/app/autoload.php';
        return new AnimeDekho(new HttpClient(), AnimeConfig::baseUrl());
    }

    private function startStub(): void
    {
        $cmd = sprintf(
            'STUB_GATE_DWELL=%d php -S 127.0.0.1:%d %s 2>/dev/null',
            self::STUB_DWELL,
            self::STUB_PORT,
            escapeshellarg(dirname(__DIR__) . '/stub-animedekho-gate.php')
        );
        $this->serverProcess = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $this->serverPipes);

        $ready = false;
        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen('127.0.0.1', self::STUB_PORT, $errno, $errstr, 0.2);
            if ($conn !== false) {
                fclose($conn);
                $ready = true;
                break;
            }
            usleep(100_000);
        }
        if (!$ready) {
            $this->fail('AnimeDekho gate stub did not start');
        }
    }

    private function stopStub(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
    }
}
