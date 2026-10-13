<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class EpisodeController extends ApiController
{
    /**
     * @param array<string, string> $params
     */
    public function show(array $params): Response
    {
        $hash = trim($params['hash'] ?? '');
        if ($hash === '') {
            return Response::error('INVALID_PARAMETER', 'Episode hash is required.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->getEpisode($hash)));
    }

    /**
     * @param array<string, string> $params
     */
    public function movie(array $params): Response
    {
        $hash = trim($params['hash'] ?? '');
        if ($hash === '') {
            return Response::error('INVALID_PARAMETER', 'Movie hash is required.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->getMovie($hash)));
    }
}