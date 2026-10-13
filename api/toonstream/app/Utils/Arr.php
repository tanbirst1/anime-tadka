<?php

declare(strict_types=1);

namespace ToonStreamApi\Utils;

/** Small array helpers shared across providers. */
final class Arr
{
    /**
     * De-duplicate a list of assoc arrays by one key, keeping first occurrences.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function uniqueBy(array $items, string $key): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $value = $item[$key] ?? null;
            if ($value !== null && isset($seen[(string) $value])) {
                continue;
            }
            if ($value !== null) {
                $seen[(string) $value] = true;
            }
            $out[] = $item;
        }
        return $out;
    }

    /** Extract the first number from a string ("TMDB 8.178" → "8.178"). */
    public static function firstNumber(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        return preg_match('/(\d+(?:\.\d+)?)/', $text, $m) ? $m[1] : null;
    }
}
