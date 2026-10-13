<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class MetaController extends ApiController
{
    public function languages(): Response
    {
        return $this->handle(fn () => Response::success(['languages' => $this->scraper->getLanguages()]));
    }

    public function genres(): Response
    {
        return $this->handle(fn () => Response::success(['genres' => $this->scraper->getGenres()]));
    }

    public function studios(): Response
    {
        return $this->handle(fn () => Response::success(['studios' => $this->scraper->getStudios()]));
    }

    public function extra(): Response
    {
        return $this->handle(fn () => Response::success($this->scraper->getExtra()));
    }
}