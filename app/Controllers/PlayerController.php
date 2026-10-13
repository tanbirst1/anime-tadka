<?php
/**
 * Custom player controller — watchhentai playback through the site's own
 * player (jwplayer + quality sources + range-aware proxy) instead of a
 * raw mp4 iframe.
 *
 * Routes (see routes/web.php):
 *   /player/watchhentai/{slug}  → player page for an episode/movie slug
 *   /player/proxy?url=...       → mp4 byte-stream proxy (Referer + Range)
 */
class PlayerController {
    /**
     * Render the custom player for a watchhentai episode or movie slug.
     * Optional ?server=N picks which server's sources to load (default 0).
     */
    public function watchhentai($slug) {
        $slug = trim((string) $slug);
        $serverIdx = max(0, (int) ($_GET['server'] ?? 0));

        // This route only ever serves watchhentai: pin the provider so the
        // custom player resolves correctly regardless of site provider.
        Config::set('provider', 'watchhentai');
        api_provider_reset();

        $cacheKey = 'player_wh_' . md5($slug . '|' . $serverIdx);
        // Short TTL: upstream hstorage mp4 URLs rotate/are signed, so a long
        // cache serves dead /player/proxy links long after rotation.
        $data = api_cache_get($cacheKey, 300);
        // Stale-guard: a cached payload with no sources is useless — ignore
        // it and re-scrape (treat as a cache miss).
        if (!is_array($data) || empty($data['sources'])) {
            $episode = api_episode_data($slug);
            if (!is_array($episode) || empty($episode['servers'])) {
                http_response_code(404);
                require ROOT_PATH . '/app/Views/errors/404.php';
                return;
            }
            $servers = array_values($episode['servers']);
            if (!isset($servers[$serverIdx])) {
                $serverIdx = 0;
            }
            $server = $servers[$serverIdx];

            // Quality sources: prefer the server's decoded list, else the
            // single resolved file. Default to the lightest quality so
            // playback starts fast on slow links (user can switch up).
            $sources = [];
            foreach ($server['sources'] ?? [] as $q) {
                if (!is_array($q) || empty($q['file'])) continue;
                $sources[] = [
                    'label' => $q['label'] ?? 'HD',
                    'file' => '/player/proxy?url=' . rawurlencode($q['file']),
                    'codec' => $q['codec'] ?? null,
                    // Container hint (mp4/m3u8/webm) from the file extension:
                    // the browser is unknown here, so no server-side ordering —
                    // player-wh.php ranks by codec/container client-side.
                    'container' => self::containerOf($q['file']),
                    'raw' => $q['file'],
                ];
            }
            usort($sources, static function ($a, $b) {
                $num = static function ($label) {
                    if (preg_match('/(\d{3,4})\s*p/i', (string) $label, $m)) return (int) $m[1];
                    return PHP_INT_MAX;
                };
                return $num($a['label']) <=> $num($b['label']);
            });
            if ($sources === [] && !empty($server['source'])) {
                $sources[] = [
                    'label' => 'HD',
                    'file' => '/player/proxy?url=' . rawurlencode($server['source']),
                    'codec' => null,
                    'container' => self::containerOf($server['source']),
                ];
            }

            // Per-server playability for client-side fallback: a server is
            // playable unless every known source is HEVC (unknown counts
            // as playable — Safari handles it).
            $serversMeta = [];
            foreach (array_values($servers) as $idx => $srv) {
                $playable = false;
                $codecs = [];
                foreach ($srv['sources'] ?? [] as $sq) {
                    $c = is_array($sq) ? ($sq['codec'] ?? null) : null;
                    if ($c !== null) {
                        $codecs[] = $c;
                    }
                    if ($c !== 'hevc') {
                        $playable = true;
                    }
                }
                if ($codecs === []) {
                    $playable = true; // nothing known: assume playable
                }
                $serversMeta[] = [
                    'idx' => $idx,
                    'name' => $srv['name'] ?? ('Server ' . ($idx + 1)),
                    'playable' => $playable,
                ];
            }

            $data = [
                'title' => $episode['title'] ?? $slug,
                'slug' => $slug,
                'serverIdx' => $serverIdx,
                'serverName' => $server['name'] ?? ('Server ' . ($serverIdx + 1)),
                'servers' => $servers,
                'serversMeta' => $serversMeta,
                'hevcOnly' => $serversMeta !== [] && array_reduce($serversMeta, static fn ($allHevc, $m) => $allHevc && ($m['playable'] === false), true),
                'sources' => $sources,
                'poster' => $episode['animePoster'] ?? '',
                'fallback' => $server['url'] ?? '',
            ];
            if ($sources !== []) {
                api_cache_set($cacheKey, $data, 300);
            }
        }

        View::render('watch/player-wh', $data, null);
    }

    /**
     * Container hint for the client-side source ranking: derive mp4 / m3u8 /
     * webm from the file's extension (unknown extension -> mp4, which is
     * what almost every direct source here is).
     */
    private static function containerOf(string $url): string
    {
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($ext, ['m3u', 'm3u8'], true)) {
            return 'm3u8';
        }
        if ($ext === 'webm') {
            return 'webm';
        }
        if (in_array($ext, ['mp4', 'm4v', 'mov'], true)) {
            return 'mp4';
        }
        return $ext !== '' && preg_match('/^[a-z0-9]{1,5}$/', $ext) ? $ext : 'mp4';
    }

    /**
     * Byte-stream proxy for hstorage mp4s: forwards Range requests with a
     * proper Referer so hotlink protection passes and seeking works.
     */
    public function proxy() {
        $url = trim((string) ($_GET['url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            http_response_code(400);
            echo 'Missing url';
            return;
        }
        // Only proxy media hosts, never arbitrary URLs.
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || (!str_contains($host, 'hstorage') && !str_contains($host, 'watchhentai'))) {
            // CDN host not on the allowlist (e.g. upstream changed host):
            // instead of 403-ing playback dead, redirect to the raw URL so
            // the video can still play direct.
            header('Location: ' . $url, true, 302);
            return;
        }

        $clientRange = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
        $baseHeaders = [
            'Referer: https://watchhentai.net/',
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ];
        // Forward the client's cache validators so revalidation behaves
        // like a direct upstream connection. (Range is managed below.)
        foreach (['HTTP_IF_RANGE' => 'If-Range', 'HTTP_IF_NONE_MATCH' => 'If-None-Match', 'HTTP_IF_MODIFIED_SINCE' => 'If-Modified-Since'] as $srv => $name) {
            if (!empty($_SERVER[$srv])) {
                $baseHeaders[] = $name . ': ' . $_SERVER[$srv];
            }
        }

        // Parse the client's requested range (bytes=A-B, bytes=A-, bytes=-N).
        $reqStart = 0;
        $reqEnd = null; // null = to end of file
        if (preg_match('/bytes\s*=\s*(\d*)-(\d*)/', $clientRange, $m)) {
            if ($m[1] !== '') {
                $reqStart = (int) $m[1];
            }
            if ($m[2] !== '') {
                $reqEnd = (int) $m[2];
            }
        }

        // Shared-hosting guard: never serve more than one slice per
        // response, so no PHP process streams for minutes (execution caps
        // would kill it mid-file). Players simply request the next range
        // when they need more; seeks work unchanged.
        $SLICE = 10 * 1024 * 1024;
        if ($reqEnd === null && $clientRange !== '') {
            $reqEnd = $reqStart + $SLICE - 1;
        }
        set_time_limit(0);
        ignore_user_abort(true);
        $isHead = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD');

        $sent = 0;          // body bytes flushed to the client
        $total = null;      // full file length (from first upstream response)
        $mime = 'video/mp4';
        $etag = null;
        $lastMod = null;
        $headersSent = false;
        $attempt = 0;
        $maxAttempts = 500; // total upstream executions cap (chunks + retries)
        $chunkFailures = 0; // consecutive failed chunks; abort request at 5
        // Chunk size: short-lived upstream connections finish before the
        // network stalls them (was: one open-ended stream per request).
        $CHUNK = 8 * 1024 * 1024;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $offset = $reqStart + $sent;
            // Stop when the client's requested range is fully delivered.
            if ($reqEnd !== null && $offset > $reqEnd) {
                return;
            }
            if ($total !== null && $offset >= $total) {
                return;
            }
            $chunkEnd = $offset + $CHUNK - 1;
            if ($reqEnd !== null) {
                $chunkEnd = min($chunkEnd, $reqEnd);
            }
            if ($total !== null) {
                $chunkEnd = min($chunkEnd, $total - 1);
            }
            $upRange = 'bytes=' . $offset . '-' . $chunkEnd;
            $chunkLeft = $chunkEnd - $offset + 1; // forward budget, bytes
            $chunkDone = false; // set when the budget is exactly drained
            $reqHeaders = $baseHeaders;
            $reqHeaders[] = 'Range: ' . $upRange;

            $resp = ['status' => 0];
            $skip = 0; // bytes to discard when upstream ignores resume range
            $got = 0;  // body bytes received this attempt
            $opened = false;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => $reqHeaders,
                CURLOPT_BUFFERSIZE => 256 * 1024,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_TCP_KEEPALIVE => 1,
                // Stall detection: upstream goes idle (not closed) mid-stream.
                // Abort reads slower than ~50KB/s over 20s so the resume
                // loop below re-requests from the current offset instead of
                // hanging the player forever (jwplayer 224003).
                CURLOPT_LOW_SPEED_LIMIT => 51200,
                CURLOPT_LOW_SPEED_TIME => 20,
                CURLOPT_NOBODY => $isHead,
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$resp) {
                    $t = trim($line);
                    if ($t === '') {
                        return strlen($line);
                    }
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $t, $m) === 1) {
                        $resp['status'] = (int) $m[1];
                    } elseif (preg_match('/^([^:]+):\s*(.*)$/', $t, $m) === 1) {
                        $resp[strtolower($m[1])] = trim($m[2]);
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$sent, &$got, &$skip, &$opened, &$headersSent, &$total, &$mime, &$etag, &$lastMod, &$resp, &$chunkLeft, &$chunkDone, &$offset, $reqStart, $reqEnd, $isHead) {
                    $len = strlen($chunk);
                    if ($len === 0) {
                        return 0;
                    }
                    if (!$opened) {
                        $opened = true;
                        $upStatus = $resp['status'] ?? 200;
                        if (!empty($resp['content-type'])) {
                            $mime = explode(';', $resp['content-type'])[0];
                        }
                        if (!empty($resp['etag'])) {
                            $etag = $resp['etag'];
                        }
                        if (!empty($resp['last-modified'])) {
                            $lastMod = $resp['last-modified'];
                        }
                        if ($total === null) {
                            if (!empty($resp['content-range']) && preg_match('#/(\d+)#', $resp['content-range'], $mm) === 1) {
                                $total = (int) $mm[1];
                            } elseif ($upStatus === 200 && isset($resp['content-length'])) {
                                // Full representation from byte 0: its length
                                // IS the file total (not offset + length).
                                $total = (int) $resp['content-length'];
                            }
                        }
                        if ($upStatus === 200 && $offset > 0) {
                            // Upstream ignored our range and sent the file
                            // from byte 0: discard everything before the
                            // offset the client still needs.
                            $skip = $offset;
                        }
                        if (!$headersSent) {
                            $headersSent = true;
                            // Deliberately OMIT Content-Length: upstream short
                            // reads are transparently re-fetched and appended,
                            // so a declared length can end up wrong and the
                            // client kills the response (ERR_CONTENT_LENGTH_
                            // MISMATCH -> player error). The byte stream stays
                            // exact; Content-Range carries the totals.
                            $partial = $reqEnd !== null || $reqStart > 0 || $upStatus === 206;
                            if ($partial && $total !== null) {
                                $end = $reqEnd ?? ($total - 1);
                                http_response_code(206);
                                header('Content-Range: bytes ' . $reqStart . '-' . $end . '/' . $total);
                            } else {
                                http_response_code($upStatus === 206 ? 206 : 200);
                            }
                            header('Content-Type: ' . $mime);
                            header('Accept-Ranges: bytes');
                            if ($etag !== null) {
                                header('ETag: ' . $etag);
                            }
                            if ($lastMod !== null) {
                                header('Last-Modified: ' . $lastMod);
                            }
                        }
                    }
                    if ($skip > 0) {
                        if ($len <= $skip) {
                            $skip -= $len;
                            $got += $len;
                            return $len;
                        }
                        $chunk = substr($chunk, $skip);
                        $got += $len;
                        $len = strlen($chunk);
                        $skip = 0;
                    } else {
                        $got += $len;
                    }
                    // Chunk budget: never forward more than requested for
                    // this chunk; aborting upstream here is the normal
                    // end-of-chunk, not an error (see $chunkDone below).
                    if ($chunkLeft <= 0) {
                        $chunkDone = true;
                        return 0;
                    }
                    if ($len > $chunkLeft) {
                        $chunk = substr($chunk, 0, $chunkLeft);
                        $len = $chunkLeft;
                    }
                    $chunkLeft -= $len;
                    if ($chunkLeft <= 0) {
                        $chunkDone = true;
                    }
                    if ($isHead) {
                        return $len;
                    }
                    echo $chunk;
                    $sent += $len;
                    @ob_flush();
                    flush();
                    if (connection_aborted()) {
                        return 0;
                    }
                    return $len;
                },
            ]);
            curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            if ($isHead) {
                return;
            }
            $expect = null;
            if ($reqEnd !== null) {
                $expect = $reqEnd - $reqStart + 1;
            } elseif ($total !== null) {
                $expect = $total - $reqStart;
            }
            if ($expect !== null && $sent >= $expect) {
                return; // complete
            }
            // Budget-drained abort (CURLE_WRITE_ERROR with $chunkDone) is the
            // normal end of a chunk: move to the next one immediately.
            if ($chunkDone && ($errno === 0 || $errno === 23)) {
                $chunkFailures = 0;
                continue;
            }
            if ($got === 0) {
                // Nothing arrived: report upstream failure, don't spin.
                if ($sent === 0 && !$headersSent) {
                    http_response_code(502);
                    echo 'Upstream fetch failed';
                }
                return;
            }
            if ($errno === 0 && $expect === null) {
                return; // unknown length, upstream closed cleanly
            }
            // Short / dropped chunk: back off and re-request it. After
            // repeated failures give the client what arrived; the player
            // resumes the rest via its own range requests.
            if (++$chunkFailures >= 5) {
                return;
            }
            usleep(min(2000000, 300000 * $chunkFailures));
        }
    }
}
