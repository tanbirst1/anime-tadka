<?php

declare(strict_types=1);

namespace AnimeDekhoApi\Providers;

/**
 * Test shim reproducing a shared host that clamps a single sleep() call.
 *
 * InfinityFree caps sleep(n > 3) at 3s, so sleep(10) returns after 3s. That
 * silently collapses the ad-gate dwell and every episode renders zero servers.
 * When AD_SIM_CLAMP_SLEEP is set, an unqualified sleep() in the provider
 * namespace is capped to that many seconds; unset, it passes through to the
 * real sleep(). The provider must therefore not rely on a single sleep() for
 * its dwell.
 */
function sleep(int $seconds): int
{
    $cap = (int) (getenv('AD_SIM_CLAMP_SLEEP') ?: 0);
    if ($cap > 0 && $seconds > $cap) {
        return \sleep($cap);
    }
    return \sleep($seconds);
}
