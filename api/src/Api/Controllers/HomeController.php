<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class HomeController extends ApiController
{
    public function home(): Response
    {
        return $this->handle(fn () => Response::success($this->scraper->getHome()));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function latest(array $params, array $query): Response
    {
        return $this->handle(function () use ($query) {
            $type = $query['type'] ?? 'all';
            $page = max(1, (int) ($query['page'] ?? 1));
            return Response::success($this->scraper->getLatest($type, $page));
        });
    }

    /**
     * Full "All Animes" listing with pagination.
     *
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function all(array $params, array $query): Response
    {
        return $this->handle(function () use ($query) {
            $page = max(1, (int) ($query['page'] ?? 1));
            return Response::success($this->scraper->getAllAnimes($page));
        });
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function top(array $params, array $query): Response
    {
        return $this->handle(function () use ($query) {
            $range = $query['range'] ?? 'day';
            return Response::success([
                'range' => $range,
                'items' => $this->scraper->getTopAnimes($range),
            ]);
        });
    }
}