<?php

declare(strict_types=1);

/**
 * Minimal zero-dependency test runner.
 *
 * Usage:
 *   php tests/run.php
 *
 * Discovers *Test.php classes in tests/Unit and tests/Integration,
 * runs every test*() method, and reports pass/fail.
 */

use AnimeTadka\Tests\AssertionFailed;

require dirname(__DIR__) . '/src/autoload.php';

// Test infrastructure lives outside the src/ autoload map.
require __DIR__ . '/TestCase.php';
require __DIR__ . '/FixtureHttpClient.php';

$root = __DIR__;
$dirs = [$root . '/Unit', $root . '/Integration'];

$classes = [];
foreach ($dirs as $dir) {
    foreach (glob($dir . '/*Test.php') ?: [] as $file) {
        require $file;
        $class = 'AnimeTadka\\Tests\\' . basename($file, '.php');
        if (class_exists($class)) {
            $classes[] = $class;
        }
    }
}

if ($classes === []) {
    fwrite(STDERR, "No test classes found.\n");
    exit(1);
}

$total = 0;
$passed = 0;
$failed = 0;
$failures = [];

foreach ($classes as $class) {
    $instance = new $class();

    // Optional setUp hook.
    if (method_exists($instance, 'setUp')) {
        $instance->setUp();
    }

    $methods = get_class_methods($instance);
    foreach ($methods as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        $total++;
        $start = microtime(true);

        try {
            $instance->{$method}();
            $passed++;
            printf("  ✓ %s::%s (%.0fms)\n", $class, $method, (microtime(true) - $start) * 1000);
        } catch (AssertionFailed $e) {
            $failed++;
            $failures[] = "{$class}::{$method}: {$e->getMessage()}";
            printf("  ✗ %s::%s — %s\n", $class, $method, $e->getMessage());
        } catch (\Throwable $e) {
            $failed++;
            $failures[] = "{$class}::{$method}: " . get_class($e) . ': ' . $e->getMessage();
            printf("  ✗ %s::%s — %s: %s\n", $class, $method, get_class($e), $e->getMessage());
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
printf("Tests: %d  Passed: %d  Failed: %d\n", $total, $passed, $failed);

if ($failures !== []) {
    echo "\nFailures:\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
}

exit($failed === 0 ? 0 : 1);