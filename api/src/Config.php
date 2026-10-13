<?php

declare(strict_types=1);

namespace AnimeTadka;

/**
 * Configuration container backed by a .env file (KEY=VALUE) plus defaults.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    /**
     * Load configuration from a .env file (if present) merged over defaults.
     *
     * @param array<string, mixed> $defaults
     */
    public static function fromEnv(string $envFile, array $defaults = []): self
    {
        $values = $defaults;

        if (is_file($envFile)) {
            foreach (self::parseEnvFile($envFile) as $key => $value) {
                $values[$key] = $value;
            }
        }

        return new self($values);
    }

    /**
     * @return array<string, string>
     */
    private static function parseEnvFile(string $file): array
    {
        $result = [];
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return $result;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip surrounding quotes.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}