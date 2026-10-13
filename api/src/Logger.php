<?php

declare(strict_types=1);

namespace AnimeTadka;

/**
 * Minimal structured file logger.
 */
final class Logger
{
    private string $file;
    private int $level;

    private const LEVELS = [
        'debug' => 10,
        'info' => 20,
        'warning' => 30,
        'error' => 40,
    ];

    public function __construct(string $file, string $level = 'info')
    {
        $this->file = $file;
        $this->level = self::LEVELS[$level] ?? self::LEVELS['info'];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $levelCode = self::LEVELS[$level] ?? self::LEVELS['info'];
        if ($levelCode < $this->level) {
            return;
        }

        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %-7s %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        // Note: no LOCK_EX — flock() is unsupported on some Android FUSE
        // filesystems and silently fails.
        @file_put_contents($this->file, $line, FILE_APPEND);
    }
}