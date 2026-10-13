<?php
// Custom watchhentai player page (framed by /ep/ watch view).
// Each server uses its own fixed skin: server 0 (Primary) -> Netflix,
// any other server -> jwplayer stock skin. No settings UI.
$title = $title ?? 'Watch';
$sources = array_values($sources ?? []);
$poster = $poster ?? '';
$serverIdx = (int) ($serverIdx ?? 0);
$skin = ($serverIdx === 0) ? 'netflix' : 'default';
// jwplayer stock skin name for the non-Netflix server.
$jwSkin = ($skin === 'netflix') ? 'netflix' : 'seven';
// Container hint (mp4/m3u8/webm) normally arrives from PlayerController;
// older cached payloads predate it, so derive it from the file URL here.
$whContainerOf = static function (array $s): string {
    if (!empty($s['container'])) {
        return (string) $s['container'];
    }
    $url = (string) ($s['raw'] ?? '');
    if ($url === '' && !empty($s['file'])) {
        $q = parse_url((string) $s['file'], PHP_URL_QUERY);
        parse_str((string) $q, $qp);
        $url = (string) ($qp['url'] ?? '');
    }
    $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['m3u', 'm3u8'], true)) {
        return 'm3u8';
    }
    if ($ext === 'webm') {
        return 'webm';
    }
    return 'mp4';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= View::escape($title) ?></title>
    <?php if ($skin === 'netflix'): ?>
    <link rel="stylesheet" href="/player/style.css">
    <?php endif; ?>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; width: 100%; overflow: hidden; background: #000; }
        #player { position: absolute; inset: 0; width: 100% !important; height: 100% !important; }
        /* Stream-interrupted overlay: our player only, tap to resume. */
        .wh-retry { display: none; position: absolute; inset: 0; z-index: 50;
            align-items: center; justify-content: center; flex-direction: column; gap: 12px;
            background: rgba(0, 0, 0, .78); color: #fff; text-align: center; padding: 20px;
            font-family: system-ui, -apple-system, Arial, sans-serif; }
        .wh-retry.is-on { display: flex; }
        .wh-retry p { margin: 0; font-size: 14px; color: rgba(255, 255, 255, .8); }
        .wh-retry button { min-height: 44px; padding: 10px 22px; border-radius: 10px; cursor: pointer;
            border: 1px solid rgba(155, 89, 182, .6); background: rgba(155, 89, 182, .25);
            color: #fff; font-size: 14px; font-weight: 800; }
        .wh-retry button:hover { background: #9b59b6; }
    </style>
</head>
<body>
    <div id="player"></div>
    <div class="wh-retry" id="whRetry">
        <p id="whRetryMsg">Stream interrupted.</p>
        <button type="button" id="whRetryBtn">Tap to resume</button>
    </div>

    <!-- Async on purpose: a blocked/hung jwplayer CDN must never stall the
         page — the inline script below boots native video after 4s. -->
    <script src="https://content.jwplatform.com/libraries/KB5zFt7A.js" async id="whJwLib"></script>
    <script>
<?php if ($sources !== []): ?>
        var whSources = <?= json_encode(array_map(function ($s, $i) use ($whContainerOf) {
            $container = $whContainerOf($s);
            $type = $container === 'm3u8' ? 'hls' : ($container === 'webm' ? 'webm' : 'mp4');
            return ['file' => $s['file'], 'type' => $type, 'label' => $s['label'], 'codec' => $s['codec'] ?? null, 'container' => $container, 'default' => $i === 0];
        }, $sources, array_keys($sources)), JSON_UNESCAPED_SLASHES) ?>;
        var whServersMeta = <?= json_encode($serversMeta ?? []) ?>;
        var whServerIdx = <?= (int) ($serverIdx ?? 0) ?>;
        var whSlug = <?= json_encode($slug ?? '') ?>;

        // ---- Client capability detection (Chrome, Edge, Firefox, Safari
        // desktop+iOS, Opera, Samsung Internet, UC/X5 webviews...).
        // canPlayType returns '' = definitely not, 'maybe'/'probably' = yes.
        var whCaps = (function () {
            var v = document.createElement('video');
            function ok(t) {
                try { var r = v.canPlayType(t); return r === 'probably' || r === 'maybe'; }
                catch (e) { return false; }
            }
            return {
                hevc: ok('video/mp4; codecs="hev1.1.6.L123.B0"') || ok('video/mp4; codecs="hvc1.1.6.L123.B0"'),
                av1: ok('video/mp4; codecs="av01.0.05M.08"'),
                hlsNative: ok('application/vnd.apple.mpegurl') // Safari / iOS only
            };
        })();
        function whCanHevc() { return !!whCaps.hevc; }

        // ---- Source classification + client-side ranking ----
        function whIsHls(s) {
            if (!s) return false;
            if (s.container === 'm3u8' || s.type === 'hls') return true;
            return /\.m3u8(\?|#|$)/i.test(s.file || '');
        }
        function whIsHevc(s) {
            if (!s) return false;
            var c = String(s.codec || '').toLowerCase();
            return c === 'hevc' || c === 'h265' || c === 'hev1' || c === 'hvc1';
        }
        // 0 h264, 1 unknown/null (playable by policy), 2 av1 if this browser
        // plays it, 3 hevc if this browser plays it, 4 av1 unsupported,
        // 5 hevc unsupported. Nothing is dropped — only ordered.
        function whCodecTier(s) {
            var c = String(s.codec || '').toLowerCase();
            if (c === '') return 1;
            if (c === 'h264' || c === 'avc' || c === 'avc1' || c.indexOf('avc1') === 0) return 0;
            if (c === 'hevc' || c === 'h265' || c === 'hev1' || c === 'hvc1') return whCaps.hevc ? 3 : 5;
            if (c === 'av1' || c.indexOf('av01') === 0) return whCaps.av1 ? 2 : 4;
            return 1; // vp9/other/unknown: treat as playable-unknown
        }
        function whRankSources(list) {
            return list.map(function (s, i) { return { s: s, i: i }; })
                .sort(function (a, b) {
                    var d = whCodecTier(a.s) - whCodecTier(b.s);
                    return d !== 0 ? d : a.i - b.i; // stable: keep lightest-quality order
                })
                .map(function (x, i) {
                    x.s.default = (i === 0); // jwplayer picks the default entry
                    return x.s;
                });
        }
        var whRanked = whRankSources(whSources);
        // First source the native player should try: ranked order, skipping
        // codecs/HLS variants this browser cannot play.
        function whPickSource() {
            for (var i = 0; i < whRanked.length; i++) {
                var s = whRanked[i];
                if (whIsHevc(s) && !whCaps.hevc) continue;
                if (whIsHls(s) && !whCaps.hlsNative && whHlsState === 'failed') continue;
                return s;
            }
            return whRanked.length ? whRanked[0] : null;
        }

        // ---- HLS (m3u8) via hls.js: only when there is an m3u8 source and
        // the browser has no native HLS (Safari/iOS need nothing here). ----
        var whHlsState = 'idle'; // idle | loading | ok | failed
        var whHlsCbs = [];
        var whHls = null;
        function whEnsureHls(cb) {
            if (whCaps.hlsNative) { cb(true); return; }
            if (whHlsState === 'ok') { cb(true); return; }
            if (whHlsState === 'failed') { cb(false); return; }
            if (window.Hls && window.Hls.isSupported && window.Hls.isSupported()) {
                whHlsState = 'ok';
                cb(true);
                return;
            }
            whHlsCbs.push(cb);
            if (whHlsState === 'loading') return;
            whHlsState = 'loading';
            var flush = function (ok) {
                whHlsState = ok ? 'ok' : 'failed';
                var cbs = whHlsCbs;
                whHlsCbs = [];
                for (var i = 0; i < cbs.length; i++) {
                    try { cbs[i](ok); } catch (e) {}
                }
            };
            try {
                var sc = document.createElement('script');
                sc.src = 'https://cdn.jsdelivr.net/npm/hls.js@1';
                sc.async = true;
                var timer = setTimeout(function () { flush(false); }, 12000);
                sc.onload = function () {
                    clearTimeout(timer);
                    flush(!!(window.Hls && window.Hls.isSupported && window.Hls.isSupported()));
                };
                sc.onerror = function () { clearTimeout(timer); flush(false); };
                document.head.appendChild(sc);
            } catch (e) { flush(false); }
        }
        function whHlsAttach(v, url) {
            whHlsDetach(v);
            var H = window.Hls;
            whHls = new H({ enableWorker: true });
            whHls.on(H.Events.ERROR, function (_e, data) {
                if (!data || !data.fatal) return;
                // Fatal: release the source so whPickSource moves past .m3u8.
                whHlsDetach(v);
                whHlsState = 'failed';
                whNativeFail();
            });
            whHls.loadSource(url);
            whHls.attachMedia(v);
        }
        function whHlsDetach(v) {
            if (!whHls) return;
            try { whHls.detachMedia(); whHls.destroy(); } catch (e) {}
            whHls = null;
            if (v) { try { v.removeAttribute('src'); } catch (e) {} }
        }
        // Point a <video> at a ranked source, transparently using hls.js for
        // m3u8 when the browser has no native HLS. On hls.js failure the
        // picker is re-run and falls through to the next non-m3u8 source.
        function whApplySource(v, s, done) {
            done = done || function () {};
            if (!s) { done(false); return; }
            if (whIsHls(s) && !whCaps.hlsNative) {
                whEnsureHls(function (ok) {
                    if (ok) {
                        try { whHlsAttach(v, s.file); done(true); return; }
                        catch (e) { whHlsState = 'failed'; }
                    }
                    var next = whPickSource(); // .m3u8 now skipped
                    if (next && next !== s) { whApplySource(v, next, done); return; }
                    done(false);
                });
                return;
            }
            whHlsDetach(v);
            v.src = s.file;
            done(true);
        }

        // ---- iOS gesture rule: play() outside a gesture is rejected, so
        // only attempt it once the user has actually interacted. ----
        var whIsIOS = /iP(hone|ad|od)/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var whGesture = false;
        ['pointerdown', 'touchstart', 'mousedown', 'click'].forEach(function (ev) {
            document.addEventListener(ev, function () { whGesture = true; }, { capture: true, passive: true });
        });
        function whTryPlay(v) {
            if (!v) return;
            if (whIsIOS && !whGesture) return; // native controls still allow a tap
            try { var p = v.play(); if (p && p.catch) p.catch(function () {}); } catch (e) {}
        }

        function whServerPlayable(meta) {
            if (!meta) return true;
            if (meta.playable === false) return false; // server known HEVC-only
            return true;
        }
        function whNextPlayable(fromIdx, forHevc) {
            for (var i = 0; i < whServersMeta.length; i++) {
                if (i === fromIdx) continue;
                var m = whServersMeta[i];
                if (!forHevc && m && m.playable === false) continue;
                return i;
            }
            return -1;
        }
        (function whBootServer() {
            var params = new URLSearchParams(window.location.search);
            if (params.get('fb') === '1') return; // already fallback-routed
            var hevcOk = whCanHevc();
            var hevcOnly = <?= !empty($hevcOnly) ? 'true' : 'false' ?>;
            if (hevcOnly && !hevcOk) {
                // No playable source for this browser: say so plainly
                // instead of cycling doomed retries.
                var msg = document.getElementById('whRetryMsg');
                if (msg) msg.textContent = 'This video needs HEVC support, which this browser cannot play. Try Safari or another title.';
                document.getElementById('whRetry').classList.add('is-on');
                var rb = document.getElementById('whRetryBtn');
                if (rb) rb.style.display = 'none';
                throw new Error('hevc-only for this browser');
            }
            var cur = null;
            for (var i = 0; i < whServersMeta.length; i++) {
                if (whServersMeta[i] && whServersMeta[i].idx === whServerIdx) cur = whServersMeta[i];
            }
            var curOk = cur ? (hevcOk || cur.playable !== false) : true;
            if (!curOk) {
                var nxt = whNextPlayable(whServerIdx, hevcOk);
                if (nxt >= 0) {
                    window.location.replace(window.location.pathname + '?server=' + nxt + '&fb=1');
                    throw new Error('routing to playable server');
                }
            }
        })();

        var playerInstance = null;

        // Retry overlay + native player (shared by the jwplayer and
        // no-jwplayer paths).
        var whRetryEl = document.getElementById('whRetry');
        var whRetryBtn = document.getElementById('whRetryBtn');
        var whTries = 0, whMaxTries = 3, whAt = 0, whWasPlaying = false, whVideo = null;

        function whShowRetry() { whRetryEl.classList.add('is-on'); }
        function whHideRetry() { whRetryEl.classList.remove('is-on'); }
        if (whRetryBtn) whRetryBtn.addEventListener('click', function () {
            whGesture = true; // this click IS the gesture iOS requires
            whHideRetry();
            whTries = 0;
            whResumeNative();
        });

        function whResumeNative() {
            var v = whVideo;
            if (!v || !whRanked.length) return;
            var s = whPickSource();
            if (!s) return;
            var applyAt = function () {
                if (whAt > 0) { try { v.currentTime = whAt; } catch (e) {} }
            };
            v.addEventListener('loadedmetadata', applyAt, { once: true });
            whApplySource(v, s, function (ok) {
                if (ok) { whTryPlay(v); } else { whNativeFail(); }
            });
            setTimeout(applyAt, 900);
        }

        function whNativeFail() {
            var v = whVideo;
            if (v) { try { whAt = v.currentTime || whAt; whWasPlaying = !v.paused; } catch (e) {} }
            if (whTries < whMaxTries) {
                whTries++;
                setTimeout(whResumeNative, 1500 * whTries);
            } else if (new URLSearchParams(window.location.search).get('fb') !== '1') {
                // One automatic hop to the next playable server before
                // giving up to the manual overlay (loop-guarded by &fb=1).
                var nxt = whNextPlayable(whServerIdx, whCanHevc());
                if (nxt >= 0) {
                    window.location.replace(window.location.pathname + '?server=' + nxt + '&fb=1');
                    return;
                }
                whShowRetry();
            } else {
                whShowRetry();
            }
        }

        function whPlayNative() {
            try {
                var wrap = document.getElementById('player');
                var v = whVideo;
                if (!v) {
                    v = document.createElement('video');
                    v.controls = true;
                    v.preload = 'auto';
                    v.playsInline = true;
                    v.setAttribute('playsinline', '');
                    v.setAttribute('webkit-playsinline', ''); // iOS UIWebView/WKWebView
                    v.setAttribute('x5-playsinline', '');     // UC / QQ X5 webviews
                    v.poster = <?= json_encode($poster) ?>;
                    v.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;background:#000';
                    wrap.innerHTML = '';
                    wrap.appendChild(v);
                    whVideo = v;
                    v.addEventListener('timeupdate', function () {
                        try { if (v.currentTime > 0) whAt = v.currentTime; } catch (e) {}
                    });
                    v.addEventListener('error', whNativeFail);
                }
                whTries = 0;
                var s = whPickSource();
                if (!s) { whShowRetry(); return; }
                whApplySource(v, s, function (ok) {
                    if (ok) { whTryPlay(v); } else { whShowRetry(); }
                });
            } catch (err) {
                whShowRetry();
            }
        }

        // ---- jwplayer boot: loads async, so a blocked or hung CDN must not
        // leave a black player. 4s without setup -> native immediately. ----
        var whNativeStarted = false, whJwStarted = false, whJwTimer = null;

        function whBootNative(reason) {
            if (whNativeStarted || whJwStarted) return;
            whNativeStarted = true;
            if (whJwTimer) { clearTimeout(whJwTimer); whJwTimer = null; }
            var go = function () {
                try { whPlayNative(); } catch (e) { whShowRetry(); }
            };
            // The library script tag sits above this inline script, so the
            // #player element always exists; still wait for DOMContentLoaded
            // when the parser hasn't finished (keeps the old behaviour).
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', go, { once: true });
            } else {
                go();
            }
        }

        function whJwSetup() {
            if (whJwStarted || whNativeStarted) return;
            whJwStarted = true;
            if (whJwTimer) { clearTimeout(whJwTimer); whJwTimer = null; }
            try {
        playerInstance = jwplayer("player").setup({
            controls: true, sharing: false, displaytitle: false,
            skin: { name: <?= json_encode($jwSkin) ?> },
            logo: { file: "", link: "" },
            captions: { color: "#FFF", fontSize: 14, backgroundOpacity: 0, edgeStyle: "raised" },
            image: <?= json_encode($poster) ?>,
            playlist: [{ title: <?= json_encode($title) ?>, image: <?= json_encode($poster) ?>, sources: whRanked }],
            aspectratio: "16:9",
            primary: "html5",
            preload: "auto",
            width: "100%"
        });

        playerInstance.on("ready", function () {
            try {
                var pc = playerInstance.getContainer();
                var rc = pc.querySelector(".jw-display-icon-rewind");
                if (rc) {
                    var fc = rc.cloneNode(true);
                    var fb = fc.querySelector(".jw-icon-rewind");
                    if (fb) { fb.style.transform = "scaleX(-1)"; fb.ariaLabel = "Forward 10s"; }
                    var nc = pc.querySelector(".jw-display-icon-next");
                    if (nc && nc.parentNode) nc.parentNode.insertBefore(fc, nc);
                    var bc = pc.querySelector(".jw-button-container");
                    var rb = bc ? bc.querySelector(".jw-icon-rewind") : null;
                    if (rb) {
                        var fcb = rb.cloneNode(true);
                        fcb.style.transform = "scaleX(-1)"; fcb.ariaLabel = "Forward 10s";
                        rb.parentNode.insertBefore(fcb, rb.nextElementSibling);
                        [fb, fcb].forEach(function (b) { if (b) b.onclick = function () { playerInstance.seek(Math.min(playerInstance.getPosition() + 10, playerInstance.getDuration())); }; });
                    }
                }
            } catch (e) { console.log("UI setup:", e); }
            document.addEventListener('keydown', function (e) {
                if (e.target.tagName === 'INPUT') return;
                switch (e.key) {
                    case 'ArrowRight': e.preventDefault(); playerInstance.seek(playerInstance.getPosition() + 10); break;
                    case 'ArrowLeft': e.preventDefault(); playerInstance.seek(Math.max(playerInstance.getPosition() - 10, 0)); break;
                    case ' ': case 'k': e.preventDefault(); playerInstance.playToggle(); break;
                    case 'f': e.preventDefault(); playerInstance.setFullscreen(!playerInstance.getFullscreen()); break;
                    case 'm': e.preventDefault(); playerInstance.setMute(!playerInstance.getMute()); break;
                }
            });
        });

        // Our player only: never an external embed. jwplayer failure moves
        // to native <video>; native failure auto-retries, then offers
        // tap-to-resume from the last position.
        playerInstance.on("error", function (e) {
            console.error("Player error, trying native video:", e);
            try { if (playerInstance && playerInstance.stop) playerInstance.stop(); } catch (e2) {}
            whNativeStarted = true;
            whPlayNative();
        });
            } catch (e) {
                // jwplayer threw during setup: fall back to native video.
                console.error("jwplayer setup failed, using native video:", e);
                whJwStarted = false;
                whNativeStarted = false;
                whBootNative('jw-setup-throw');
            }
        }

        if (typeof jwplayer !== 'undefined') {
            whJwSetup();
        } else {
            whJwTimer = setTimeout(function () { whBootNative('jw-timeout'); }, 4000);
            var whLib = document.getElementById('whJwLib');
            if (whLib) {
                whLib.addEventListener('load', function () {
                    if (whNativeStarted) return; // native already took over
                    if (typeof jwplayer !== 'undefined') whJwSetup();
                    else whBootNative('jw-missing');
                });
                // CDN blocked (adblocker/offline): boot native right away.
                whLib.addEventListener('error', function () { whBootNative('jw-blocked'); });
            }
        }
<?php else: ?>
        document.getElementById('whRetry').classList.add('is-on');
<?php endif; ?>
    </script>
</body>
</html>
