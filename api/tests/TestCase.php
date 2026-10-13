<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

/**
 * Base test case with assertion helpers.
 */
abstract class TestCase
{
    private int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function assertionCount(): int
    {
        return $this->assertions;
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->fail($message !== '' ? $message : 'Expected true, got false');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if ($condition) {
            $this->fail($message !== '' ? $message : 'Expected false, got true');
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->fail(sprintf(
                '%sExpected %s, got %s',
                $message !== '' ? $message . ' — ' : '',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        $this->assertions++;
        if ($value === null) {
            $this->fail($message !== '' ? $message : 'Expected non-null value');
        }
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->assertions++;
        if ($value !== null) {
            $this->fail($message !== '' ? $message : 'Expected null value');
        }
    }

    protected function assertNotEmpty(mixed $value, string $message = ''): void
    {
        $this->assertions++;
        if (empty($value)) {
            $this->fail($message !== '' ? $message : 'Expected non-empty value');
        }
    }

    protected function assertIsArray(mixed $value, string $message = ''): void
    {
        $this->assertions++;
        if (!is_array($value)) {
            $this->fail($message !== '' ? $message : 'Expected array');
        }
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            $this->fail(sprintf(
                '%sExpected string to contain "%s"',
                $message !== '' ? $message . ' — ' : '',
                $needle
            ));
        }
    }

    protected function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            $this->fail(sprintf(
                '%sExpected string NOT to contain "%s"',
                $message !== '' ? $message . ' — ' : '',
                $needle
            ));
        }
    }

    protected function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($actual < $expected) {
            $this->fail(sprintf(
                '%sExpected %s >= %s',
                $message !== '' ? $message . ' — ' : '',
                var_export($actual, true),
                var_export($expected, true)
            ));
        }
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertions++;
        if (!array_key_exists($key, $array)) {
            $this->fail(sprintf(
                '%sExpected array to have key "%s"',
                $message !== '' ? $message . ' — ' : '',
                (string) $key
            ));
        }
    }

    protected function assertInstanceOf(string $class, mixed $value, string $message = ''): void
    {
        $this->assertions++;
        if (!$value instanceof $class) {
            $this->fail(sprintf(
                '%sExpected instance of %s, got %s',
                $message !== '' ? $message . ' — ' : '',
                $class,
                get_debug_type($value)
            ));
        }
    }

    protected function fail(string $message): never
    {
        throw new AssertionFailed($message);
    }
}

final class AssertionFailed extends \RuntimeException
{
}