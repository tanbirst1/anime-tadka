<?php

declare(strict_types=1);

namespace AnimeTadka\Exceptions;

/**
 * Raised when the remote server returns an unexpected HTTP status.
 */
class HttpException extends ScraperException
{
    private int $status;

    public function __construct(int $status, string $message = '', ?\Throwable $previous = null)
    {
        $this->status = $status;
        parent::__construct($message !== '' ? $message : "HTTP {$status}", $status, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}