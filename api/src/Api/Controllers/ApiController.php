<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;
use AnimeTadka\Exceptions\HttpException;
use AnimeTadka\Exceptions\NotFoundException;
use AnimeTadka\Exceptions\ParseException;
use AnimeTadka\Exceptions\RateLimitedException;
use AnimeTadka\Exceptions\ScraperException;
use AnimeTadka\Logger;
use AnimeTadka\Scraper\AnimeTadkaScraper;

/**
 * Base controller: shared exception → JSON error mapping.
 */
abstract class ApiController
{
    protected AnimeTadkaScraper $scraper;
    protected ?Logger $logger;

    public function __construct(AnimeTadkaScraper $scraper, ?Logger $logger = null)
    {
        $this->scraper = $scraper;
        $this->logger = $logger;
    }

    /**
     * Run a handler and convert scraper exceptions into safe JSON errors.
     * Stack traces are never exposed to the client.
     */
    protected function handle(callable $handler): Response
    {
        try {
            return $handler();
        } catch (NotFoundException $e) {
            return Response::error('NOT_FOUND', $e->getMessage(), 404);
        } catch (RateLimitedException $e) {
            $this->log('warning', 'Rate limited by source', ['error' => $e->getMessage()]);
            return Response::error('RATE_LIMITED', 'The source site is rate limiting requests. Try again later.', 429);
        } catch (HttpException $e) {
            $status = $e->getStatus();
            $code = match (true) {
                $status === 403 => 'FORBIDDEN',
                $status === 404 => 'NOT_FOUND',
                $status === 429 => 'RATE_LIMITED',
                $status >= 500 => 'SOURCE_ERROR',
                default => 'HTTP_ERROR',
            };
            $this->log('warning', 'HTTP error from source', ['status' => $status, 'error' => $e->getMessage()]);
            return Response::error($code, 'The source site returned an error.', $status >= 500 ? 502 : $status);
        } catch (ParseException $e) {
            $this->log('error', 'Parse failure', ['error' => $e->getMessage()]);
            return Response::error('PARSE_ERROR', 'The source page could not be parsed.', 502);
        } catch (ScraperException $e) {
            $this->log('error', 'Scraper failure', ['error' => $e->getMessage()]);
            return Response::error('SCRAPE_ERROR', 'Failed to fetch data from the source site.', 502);
        } catch (\Throwable $e) {
            $this->log('error', 'Unexpected error', ['error' => $e->getMessage()]);
            return Response::error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
        }
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->{$level}($message, $context);
        }
    }
}