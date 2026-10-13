<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class AnimeController extends ApiController
{
    /**
     * @param array<string, string> $params
     */
    public function show(array $params): Response
    {
        $slug = trim($params['slug'] ?? '');
        if ($slug === '') {
            return Response::error('INVALID_PARAMETER', 'Anime slug is required.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->getAnime($slug)));
    }

    /**
     * @param array<string, string> $params
     */
    public function episodes(array $params): Response
    {
        $slug = trim($params['slug'] ?? '');
        if ($slug === '') {
            return Response::error('INVALID_PARAMETER', 'Anime slug is required.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->getAnimeEpisodes($slug)));
    }
}