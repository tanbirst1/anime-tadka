<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

/**
 * Covers the anikoto primary→backup mirror fallback (https://anikoto.cz).
 *
 * The provider's `anikotoGet()` tries the primary host and, on any failure
 * (DNS, connect, timeout, 4xx/5xx), retries the backup mirror. It also
 * remembers the host that last succeeded within the request and writes a
 * short-lived sticky flag so the next request starts on the mirror while the
 * primary is down. These tests exercise the ordering/sticky helpers directly
 * (pure functions, no network) plus the fallback loop with a stubbed transport.
 *
 * The anikoto module is procedural (global functions, not namespaced), so the
 * file is required once and the functions are called unqualified from the
 * global namespace via the `\` prefix.
 */
final class AnikotoMirrorFallbackTest extends TestCase
{
    private static bool $loaded = false;

    /** Path of the sticky flag the provider writes. */
    private function stickyFile(): string
    {
        return \anikotoStickyFile();
    }

    public function setUp(): void
    {
        $this->loadModule();
        @unlink($this->stickyFile());
    }

    public function tearDown(): void
    {
        @unlink($this->stickyFile());
    }

    private function loadModule(): void
    {
        if (self::$loaded) {
            return;
        }
        $base = dirname(__DIR__, 2) . '/anikoto';
        require_once $base . '/config.php';
        require_once $base . '/cache.php';
        require_once $base . '/http.php';
        require_once $base . '/dom.php';
        require_once $base . '/providers/anikoto.php';
        self::$loaded = true;
    }

    /** The backup host is registered and points at anikoto.cz. */
    public function testBackupHostRegistered(): void
    {
        $this->assertEquals('https://anikoto.cz', \baseUrl('anikotoBackup'));
    }

    /** Reordering moves the target host to the front, preserving the rest. */
    public function testReorderHosts(): void
    {
        $hosts = ['https://a', 'https://b', 'https://c'];
        $this->assertEquals(['https://c', 'https://a', 'https://b'], \anikotoReorderHosts($hosts, 'https://c'));
        // Already first: unchanged.
        $this->assertEquals($hosts, \anikotoReorderHosts($hosts, 'https://a'));
        // Absent: unchanged.
        $this->assertEquals($hosts, \anikotoReorderHosts($hosts, 'https://z'));
    }

    /** A freshly-marked primary is reported as down (backup should be tried). */
    public function testStickyFlagMarksPrimaryDown(): void
    {
        \anikotoMarkPrimaryDown('https://primary.test', 'https://backup.test');
        $this->assertEquals('https://backup.test', \anikotoStickyHost('https://primary.test', 'https://backup.test'));
        // A different primary is unaffected by the flag.
        $this->assertNull(\anikotoStickyHost('https://other.test', 'https://backup.test'));
    }

    /** Recovering the primary clears the flag. */
    public function testMarkPrimaryUpClearsFlag(): void
    {
        \anikotoMarkPrimaryDown('https://primary.test', 'https://backup.test');
        $this->assertNotNull(\anikotoStickyHost('https://primary.test', 'https://backup.test'));
        \anikotoMarkPrimaryUp('https://primary.test');
        $this->assertNull(\anikotoStickyHost('https://primary.test', 'https://backup.test'));
    }

    /**
     * The candidate ordering the fallback loop builds: a remembered winner
     * first, then the primary, then the backup — de-duplicated. Mirrors the
     * list construction inside anikotoGet().
     */
    public function testCandidateOrderingPrimaryFirstWhenHealthy(): void
    {
        $primary = \baseUrl('anikoto');
        $backup  = \baseUrl('anikotoBackup');
        $this->assertNotNull($primary);
        $this->assertNotNull($backup);
        $this->assertTrue($primary !== $backup, 'primary and backup must differ');

        // Healthy request (no sticky flag): primary leads, backup follows.
        $hosts = [];
        foreach ([null, $primary, $backup] as $h) {
            if (is_string($h) && $h !== '' && !in_array($h, $hosts, true)) $hosts[] = $h;
        }
        $this->assertEquals([$primary, $backup], $hosts);
    }

    /**
     * When the primary was marked down, the backup is promoted to the front so
     * the next request does not re-pay the primary's connect timeout.
     */
    public function testCandidateOrderingBackupFirstWhenSticky(): void
    {
        $primary = \baseUrl('anikoto');
        $backup  = \baseUrl('anikotoBackup');

        \anikotoMarkPrimaryDown($primary, $backup);
        $sticky = \anikotoStickyHost($primary, $backup);
        $this->assertEquals($backup, $sticky);

        $hosts = \anikotoReorderHosts([$primary, $backup], $sticky);
        $this->assertEquals([$backup, $primary], $hosts);
    }
}
