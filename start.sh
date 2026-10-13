#!/usr/bin/env bash
# Start AnimeTadka dev server (site + static assets + /api).
# Usage: ./start.sh [port]
PORT="${1:-8000}"
# Multi-worker mode: video proxy streams hold a connection for minutes;
# a single worker would block all other requests (looks like hanging).
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"
exec php -S "0.0.0.0:$PORT" -t public public/router.php
