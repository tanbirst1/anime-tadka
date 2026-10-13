<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class ScheduleController extends ApiController
{
    public function show(): Response
    {
        return $this->handle(fn () => Response::success($this->scraper->getSchedule()));
    }
}
