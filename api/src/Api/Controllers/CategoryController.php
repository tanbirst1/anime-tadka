<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class CategoryController extends ApiController
{
    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function show(array $params, array $query): Response
    {
        $type = trim($params['type'] ?? '');
        $slug = trim($params['slug'] ?? '');
        $page = max(1, (int) ($query['page'] ?? 1));
        $filter = $this->typeFilter($query);

        if ($type === '' || $slug === '') {
            return Response::error('INVALID_PARAMETER', 'Category type and slug are required.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->getCategory($type, $slug, $page, $filter)));
    }

    /**
     * Letter listing (A-Z).
     *
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function letter(array $params, array $query): Response
    {
        $letter = trim($params['letter'] ?? '');
        $page = max(1, (int) ($query['page'] ?? 1));
        $filter = $this->typeFilter($query);

        if ($letter === '') {
            return Response::error('INVALID_PARAMETER', 'Letter is required.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->getLetter($letter, $page, $filter)));
    }

    /**
     * Normalize the ?type=series|movies filter from the query string.
     *
     * @param array<string, string> $query
     */
    private function typeFilter(array $query): string
    {
        $filter = strtolower(trim($query['type'] ?? ''));
        return in_array($filter, ['series', 'movies'], true) ? $filter : '';
    }
}