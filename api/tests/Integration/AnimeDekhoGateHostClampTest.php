<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

use AnimeDekhoApi\Core\Config as AnimeConfig;
use AnimeDekhoApi\Providers\AnimeDekho;
use AnimeDekhoApi\Utils\HttpClient;

/**
 * Regression coverage for the "src not load" bug on shared hosting.
 *
 * The gate dwell was originally a single sleep($seconds). Free hosts such as
 * InfinityFree clamp one sleep() call to ~3s, so sleep(10) returns after 3s.
 * That silently collapses the dwell, the verify token 403s, no `dgt_verified`
 * cookie is planted and every episode renders zero servers — even though the
 * same code works on a normal host.
 *
 * The shim (tests/shim-clamped-sleep.php) reproduces the clamp: with
 * AD_SIM_CLAMP_SLEEP set, an unqualified sleep() in the provider's namespace is
 * capped to that many seconds. The provider must therefore dwell without
 * relying on a single sleep() call.
 */
final class AnimeDekhoGateHostClampTest extends TestCase
{
    private const STUB_PORT = 8902;
    private const STUB_URL = 'http://127.0.0.1:8902';

    /** Gate dwell the stub enforces; the clamp is set well below it. */
    private const STUB_DWELL = 3;

    private mixed $serverProcess = null;
    private array $serverPipes = [];
    private string $cookieFile = '';

    public function setUp(): void
    {
        // Defining the namespaced sleep() shim for the whole test process is
        // harmless: the provider no longer calls sleep() at all.
        require_once __DIR__ . '/../shim-clamped-sleep.php';

        $this->cookieFile = dirname(__DIR__, 2) . '/storage/cache/anime-clamp-test-cookies.txt';
        $this->startStub();
        register_shutdown_function(fn () => $this->stopStub());

        putenv('ANIMEDEKHO_BASE_URL=' . self::STUB_URL);
        putenv('ANIMEDEKHO_COOKIE_FILE=' . $this->cookieFile);
        putenv('ANIMEDEKHO_GATE_DWELL=' . (self::STUB_DWELL + 2));
    }

    public function tearDown(): void
    {
        putenv('ANIMEDEKHO_BASE_URL');
        putenv('ANIMEDEKHO_COOKIE_FILE');
        putenv('ANIMEDEKHO_GATE_DWELL');
        putenv('AD_SIM_CLAMP_SLEEP');
        @unlink($this->cookieFile);
        $this->stopStub();
    }

    /**
     * With sleep() clamped below the gate window (as on the live host), the
     * episode must still solve the gate and load servers. A single sleep()
     * dwell would collapse to the clamp and leave zero servers.
     */
    public function testEpisodeLoadsServersDespiteClampedSleep(): void
    {
        putenv('AD_SIM_CLAMP_SLEEP=1'); // sleep(anything) → 1s
        @unlink($this->cookieFile);

        $start = microtime(true);
        $ep = $this->provider()->episode('overgeared-1x4', null);
        $elapsed = microtime(true) - $start;

        $this->assertEquals(
            2,
            count($ep['servers']),
            'the dwell must survive a host that clamps sleep() — otherwise the gate stays unsolved'
        );
        $this->assertGreaterThanOrEqual(
            self::STUB_DWELL,
            $elapsed,
            'the full dwell window must elapse even when sleep() is clamped'
        );
    }

    /** Without the clamp the same flow still works (control). */
    public function testEpisodeLoadsServersWithoutClamp(): void
    {
        putenv('AD_SIM_CLAMP_SLEEP=0');
        @unlink($this->cookieFile);

        $ep = $this->provider()->episode('overgeared-1x4', null);

        $this->assertEquals(2, count($ep['servers']));
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
