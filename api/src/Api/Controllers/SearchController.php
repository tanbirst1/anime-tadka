<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class SearchController extends ApiController
{
    private const MIN_QUERY_LENGTH = 2;

    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function search(array $params, array $query): Response
    {
        $q = trim($query['q'] ?? '');

        if ($q === '') {
            return Response::error('MISSING_QUERY', 'The "q" parameter is required.', 400);
        }

        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return Response::error('QUERY_TOO_SHORT', 'Query must be at least ' . self::MIN_QUERY_LENGTH . ' characters.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->search($q)));
    }
}