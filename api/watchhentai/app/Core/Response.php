<?php

declare(strict_types=1);

namespace WatchHentaiApi\Core;

/**
 * Shared HTTP response helpers — every route exits through here so the
 * envelope shape stays identical across the whole API.
 */
final class Response
{
    public static function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): never
    {
        self::cors();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Success envelope: { success, provider, [stats], results }.
     *
     * @param array<string, mixed> $results
     * @param array<string, mixed>|null $stats
     */
    public static function ok(array $results, ?array $stats = null): never
    {
        $payload = [
            'success' => true,
            'provider' => Config::PROVIDER,
        ];
        if ($stats !== null) {
            $payload['stats'] = $stats;
        }
        $payload['results'] = $results;
        self::json($payload);
    }

    /**
     * Error envelope: { success: false, provider, message, statusCode }.
     */
    public static function fail(string $message, int $status): never
    {
        self::json([
            'success' => false,
            'provider' => Config::PROVIDER,
            'message' => $message,
            'statusCode' => $status,
        ], $status);
    }
}
