<?php

declare(strict_types=1);

namespace WatchHentaiApi\Core;

/** Raised when the scraped site cannot be reached or answers non-200. */
final class ScrapeException extends \RuntimeException
{
}
