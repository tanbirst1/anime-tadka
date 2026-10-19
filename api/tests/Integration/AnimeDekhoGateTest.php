<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

use AnimeDekhoApi\Core\Config as AnimeConfig;
use AnimeDekhoApi\Providers\AnimeDekho;
use AnimeDekhoApi\Utils\HttpClient;

/**
 * AnimeDekho provider tests against a real HTTP stub of the site's dgt
 * ad-gate (tests/stub-animedekho-gate.php).
 *
 * Regression coverage for the "src not load" bug: the site's gate mints a
 * verify token carrying its issue time and only honours it after a dwell
 * window. The provider must wait that window out before hitting the token,
 * otherwise the verify 403s, no `dgt_verified` cookie is planted, /watch
 * stays gated and every episode renders zero servers.
 */
final class AnimeDekhoGateTest extends TestCase
{
    private const STUB_PORT = 8901;
    private const STUB_URL = 'http://127.0.0.1:8901';

    /** Seconds the stub gate makes a client wait (STUB_GATE_DWELL). */
    private const STUB_DWELL = 2;

    private mixed $serverProcess = null;
    private array $serverPipes = [];
    private string $cookieFile = '';

    public function setUp(): void
    {
        $this->cookieFile = dirname(__DIR__, 2) . '/storage/cache/anime-gate-test-cookies.txt';
        $this->startStub();
        // The runner does not call tearDown(), so make sure the stub dies with
        // the process even if a test fails mid-flight.
        register_shutdown_function(fn () => $this->stopStub());

        putenv('ANIMEDEKHO_BASE_URL=' . self::STUB_URL);
        putenv('ANIMEDEKHO_COOKIE_FILE=' . $this->cookieFile);
    }

    public function tearDown(): void
    {
        putenv('ANIMEDEKHO_BASE_URL');
        putenv('ANIMEDEKHO_COOKIE_FILE');
        putenv('ANIMEDEKHO_GATE_DWELL');
        @unlink($this->cookieFile);
        $this->stopStub();
    }

    /** A fresh jar so every test starts from the stub's gated state. */
    private function resetGate(): void
    {
        @unlink($this->cookieFile);
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

    private function provider(): AnimeDekho
    {
        require_once dirname(__DIR__, 2) . '/animedekho/app/autoload.php';
        return new AnimeDekho(new HttpClient(), AnimeConfig::baseUrl());
    }

    /**
     * The core regression: with the gate armed, episode() must solve it (by
     * dwelling past the token window) and return the site's servers, with the
     * active server's wrapper resolved to its real player URL.
     */
    public function testEpisodeSolvesGateAndLoadsServers(): void
    {
        putenv('ANIMEDEKHO_GATE_DWELL=' . (self::STUB_DWELL + 2));
        $this->resetGate();

        $start = microtime(true);
        $ep = $this->provider()->episode('overgeared-1x4', null);
        $elapsed = microtime(true) - $start;

        $this->assertNotEmpty($ep['servers'], 'servers should be populated once the gate is solved');
        $this->assertEquals(2, count($ep['servers']), 'both stub servers should be returned');
        $this->assertEquals('HydraX', $ep['servers'][0]['name']);
        $this->assertEquals('Vidmoly', $ep['servers'][1]['name']);

        // The active (first) server's same-site wrapper resolves to the
        // external player it embeds.
        $this->assertEquals(
            'https://abyssplayer.com/m5bXILRqW',
            $ep['servers'][0]['originalSrc'],
            'the active server wrapper should resolve to its real player URL'
        );

        // The dwell must actually have been observed: without it the verify
        // would 403 and the servers would be empty.
        $this->assertGreaterThanOrEqual(
            self::STUB_DWELL,
            $elapsed,
            'episode() must dwell past the gate token window before verifying'
        );
    }

    /**
     * Control: with too short a dwell the gate's verify 403s, no cookie is
     * planted, and the episode has no servers. This proves the dwell — not
     * some other coincidence — is what unlocks playback.
     */
    public function testEpisodeWithoutDwellStaysGated(): void
    {
        putenv('ANIMEDEKHO_GATE_DWELL=0');
        $this->resetGate();

        $ep = $this->provider()->episode('overgeared-1x4', null);

        $this->assertIsArray($ep['servers']);
        $this->assertEquals([], $ep['servers'], 'a rejected verify must leave the episode with no servers');
    }

    /**
     * token() clears the jar, observes the real gated state, then solves the
     * gate itself so the returned servers are already unlocked.
     */
    public function testTokenSolvesGateAndReturnsServers(): void
    {
        putenv('ANIMEDEKHO_GATE_DWELL=' . (self::STUB_DWELL + 1));
        $this->resetGate();

        $t = $this->provider()->token('overgeared-1x4');

        $this->assertTrue($t['gated'], 'the stub should report a fresh IP as gated');
        $this->assertNotNull($t['activatedVerifyUrl'], 'token() should activate a verify link');
        $this->assertNotEmpty($t['verifyUrls'], 'the gate page should expose skip forms');
        $this->assertNotEmpty($t['servers'], 'token() should return unlocked servers');
        $this->assertTrue($t['cookieUnlocked'], 'cookieUnlocked should be true once servers resolve');
    }
}
