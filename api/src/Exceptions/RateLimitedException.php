<?php

declare(strict_types=1);

namespace AnimeTadka\Exceptions;

/**
 * Raised when the source site rate-limits us (HTTP 429 or similar).
 */
class RateLimitedException extends ScraperException
{
}