<?php

declare(strict_types=1);

namespace AnimeTadka\Exceptions;

/**
 * Raised when a page cannot be parsed (missing selectors, malformed HTML, ...).
 */
class ParseException extends ScraperException
{
}