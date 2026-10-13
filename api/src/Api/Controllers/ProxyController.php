<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

/**
 * Proxy endpoints that resolve stream sources and download mirrors without
 * streaming media bytes or bypassing anti-bot protection.
 */
final class ProxyController extends ApiController
{
    private const SERVERS = ['thugs', 'toonx', 'hyx'];

    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function watch(array $params, array $query): Response
    {
        $url = trim((string) ($query['url'] ?? ''));

        if ($url !== '') {
            $parts = parse_url($url);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = (string) ($parts['path'] ?? '');

            if (!in_array($host, ['animetadka.com', 'www.animetadka.com'], true) || $path !== '/watch.php') {
                return Response::error('INVALID_URL', 'Only animetadka.com watch.php URLs are allowed.', 400);
            }

            $server = '';
            $hash = '';
            $found = 0;

            parse_str((string) ($parts['query'] ?? ''), $qs);
            foreach (self::SERVERS as $candidate) {
                $value = (string) ($qs[$candidate] ?? '');
                if ($value !== '') {
                    $found++;
                    $server = $candidate;
                    $hash = $value;
                }
            }

            if ($found !== 1 || !preg_match('/^[a-zA-Z0-9]+$/', $hash)) {
                return Response::error('INVALID_URL', 'Only animetadka.com watch.php URLs are allowed.', 400);
            }
        } else {
            $server = strtolower(trim((string) ($query['server'] ?? '')));
            $hash = trim((string) ($query['hash'] ?? ''));

            if ($server === '' || $hash === '') {
                return Response::error('MISSING_PARAM', 'Provide ?url= or ?server=&hash=.', 400);
            }

            if (!in_array($server, self::SERVERS, true) || !preg_match('/^[a-zA-Z0-9]+$/', $hash)) {
                return Response::error('INVALID_URL', 'Only animetadka.com watch.php URLs are allowed.', 400);
            }
        }

        return $this->handle(fn () => Response::success($this->scraper->getWatchSource($server, $hash, true)));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function download(array $params, array $query): Response
    {
        $url = trim((string) ($query['url'] ?? ''));

        if ($url === '') {
            $dd = (string) ($query['dd'] ?? '');
            if ($dd !== '') {
                $url = 'https://animetadka.com/redirect/download/?dd=' . rawurlencode($dd);
            }
        }

        if ($url === '') {
            return Response::error('MISSING_PARAM', 'The "url" parameter is required.', 400);
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($host, ['animetadka.com', 'www.animetadka.com'], true)) {
            return Response::error('INVALID_URL', 'Only animetadka.com download URLs are allowed.', 400);
        }

        return $this->handle(fn () => Response::success($this->scraper->resolveDownload($url)));
    }
}
