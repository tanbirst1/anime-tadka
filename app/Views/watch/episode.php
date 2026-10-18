<?php
$episode = $episode ?? [];
$servers = $servers ?? [];
$episodeServers = $episode['servers'] ?? $servers;
$episodes = $episode['episodes'] ?? [];
$seasons = $episode['seasons'] ?? [];
$recommendations = $episode['recommendations'] ?? [];
?>
<div class="ep-main-wrapper">

    <div class="movie-nav-top">
        <a href="<?= View::hurl('/') ?><?= View::escape($episode['animeSlug'] ?? '') ?><?= str_contains($episode['backLabel'] ?? '', 'Movie') ? '?type=movie' : '?type=series' ?>" class="back-info-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span><?= View::escape($episode['backLabel'] ?? 'Back to Anime Details') ?></span>
        </a>
    </div>

    <a href="/fix-buffer" class="sa-buffer-notify">
        <div class="sa-notify-left">
            <span class="sa-pulse-dot"></span>
            <span class="sa-notify-text">Facing Buffer / Loading / Lag Issues? <span class="sa-accent-color">Fix it Here</span></span>
        </div>
        <svg class="sa-notify-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
    </a>

    <?php
    // Player source priority:
    // 1. episode-level player_source (pre-resolved CDN URL)
    // 2. First server's source (the actual embed URL, e.g. CDN link)
    // 3. episode-level player_url (animetadka.com redirect — may not iframe)
    $playerSrc = $episode['player_source'] ?? '';
    if (empty($playerSrc) && !empty($episodeServers)) {
        $firstServer = reset($episodeServers);
        $playerSrc = $firstServer['source'] ?? ($firstServer['url'] ?? '');
    }
    if (empty($playerSrc)) {
        $playerSrc = $episode['player_url'] ?? '';
    }
    // Watchhentai provider: play through the custom player page (jwplayer
    // + qualities + proxy) instead of a raw file iframe.
    $isWhPlayer = (Config::get('provider') === 'watchhentai') && !empty($episode['hash']);
    if ($isWhPlayer) {
        $whActive = 0;
        foreach (array_values($episodeServers) as $whI => $whS) {
            if (!empty($whS['active'])) { $whActive = $whI; break; }
        }
        $playerSrc = '/player/watchhentai/' . rawurlencode((string) $episode['hash']) . '?server=' . $whActive;
    }
    $whHash = (string) ($episode['hash'] ?? '');
    ?>

    <?php
    // No usable sources (e.g. failed watchhentai scrape): a server-less
    // player URL would just 404 inside the frame, so show a retry notice
    // instead of a dead iframe.
    $showEmptyState = trim((string) $playerSrc) === '' || (empty($episodeServers) && $isWhPlayer);
    // animedekho.tv arms a one-per-24h "skip the ad" wall on its side. Its
    // prompt must never reach our visitors: api_episode_data() serves the
    // last payload that resolved during those windows, and when there is
    // genuinely nothing to play we show the neutral retry state instead of
    // sending anyone to a third-party verification page.
    ?>
    <?php if ($showEmptyState): ?>
    <div class="player-container" id="at-container-v2" style="background:#000 !important; display:block !important; visibility:visible !important; opacity:1 !important;">
        <div style="width:100%; height:100%; min-height:240px; display:flex !important; flex-direction:column; align-items:center; justify-content:center; gap:16px; padding:24px; box-sizing:border-box; text-align:center;">
            <div aria-hidden="true" style="width:38px; height:38px; border:3px solid rgba(255,255,255,.18); border-top-color:#389ce9; border-radius:50%; animation:atSrcSpin .9s linear infinite;"></div>
            <p style="margin:0; color:#fff; font-size:15px; font-weight:600;">Preparing your player…</p>
            <p style="margin:0; color:#9aa; font-size:13px;">Fetching the original source. The player loads on its own — no verification needed.</p>
            <p id="at-src-status" style="margin:0; color:#7ea; font-size:12px;"></p>
        </div>
    </div>
    <style>@keyframes atSrcSpin{to{transform:rotate(360deg)}}</style>
    <script>
    // Eager source gate: the player is only loaded once the server has scraped
    // the original watch source. Poll the readiness probe and reload into the
    // real player as soon as it is ready (no manual Retry, no dead frame).
    (function () {
        var hash = <?= json_encode((string) ($episode['hash'] ?? '')) ?>;
        if (!hash) return;
        var tries = 0, MAX = 20, DELAY = 3000;
        var status = document.getElementById('at-src-status');
        function poll() {
            tries++;
            fetch('/player/ready?hash=' + encodeURIComponent(hash) + '&t=' + Date.now(), { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ready) { location.reload(); return; }
                    if (status) status.textContent = 'Still working… attempt ' + tries;
                    if (tries < MAX) { setTimeout(poll, DELAY); return; }
                    if (status) status.innerHTML = 'Taking longer than usual. <a href="javascript:location.reload()" style="color:#389ce9;font-weight:700;">Tap to retry now</a>.';
                })
                .catch(function () {
                    if (status) status.textContent = 'Still working… attempt ' + tries;
                    if (tries < MAX) setTimeout(poll, DELAY);
                });
        }
        setTimeout(poll, 1000);
    })();
    </script>
    <?php else: ?>
    <div class="player-container" id="at-container-v2" style="background:#000 !important; display:block !important; visibility:visible !important; opacity:1 !important;">
        <iframe id="at-player-v2" src="<?= $playerSrc ?>" frameborder="0" allowfullscreen="" style="width:100%; height:100%; display:block !important; visibility:visible !important; opacity:1 !important;"></iframe>
    </div>
    <?php endif; ?>
    <script>
    (function () {
        var frame = document.getElementById('at-player-v2');
        // Empty state renders no iframe: nothing to lock or switch.
        if (!frame) return;
        // Block extra taps while a switch is already loading (rapid taps
        // restart the load and nothing ever finishes).
        var switching = false;
        var switchTimer = null;
        function unlock() {
            switching = false;
            if (switchTimer) { clearTimeout(switchTimer); switchTimer = null; }
        }
        // Safety valve: some frames never fire `load` (blocked/aborted), which
        // would otherwise swallow every future switch.
        function armTimeout() {
            if (switchTimer) clearTimeout(switchTimer);
            switchTimer = setTimeout(unlock, 10000);
        }
        frame.addEventListener('load', unlock);
        // Normalize hrefs so '/x?server=1' vs absolute/relative forms compare equal.
        function sameSrc(url) {
            if (!url) return false;
            try {
                var next = new URL(url, location.origin);
                var cur = new URL(frame.src, location.origin);
                return next.pathname === cur.pathname && next.search === cur.search;
            } catch (err) {
                return frame.src === url;
            }
        }
        // Tabs render after this script: bind on DOM ready.
        document.addEventListener('DOMContentLoaded', function () {
            Array.prototype.forEach.call(document.querySelectorAll('.srv-btn'), function (btn) {
                btn.addEventListener('click', function (e) {
                    // Re-clicking the tab that is already shown never fires
                    // `load`, so don't take the switching lock for it.
                    if (btn.classList.contains('active') || sameSrc(btn.getAttribute('data-url'))) {
                        return;
                    }
                    if (switching) {
                        e.stopImmediatePropagation();
                        e.preventDefault();
                        return;
                    }
                    switching = true;
                    armTimeout();
                });
            });
            // Lazy resolution: only the active server's direct player URL is
            // scraped on page load. Any other tab is resolved on demand (first
            // click), and a tab already shown by a restored preference is
            // resolved immediately so the frame never loads a raw wrapper.
            Array.prototype.forEach.call(document.querySelectorAll('.srv-btn'), function (btn) {
                if (btn.getAttribute('data-resolved') === '1') return;
                function apply(src) {
                    btn.setAttribute('data-url', src);
                    // jQuery caches data-* on first read; keep its copy in sync
                    // so theme.js sets the frame to the resolved original src.
                    if (window.jQuery) { try { window.jQuery(btn).data('url', src); } catch (err) {} }
                }
                function resolve(cb) {
                    if (btn.classList.contains('at-resolving')) return;
                    btn.classList.add('at-resolving');
                    fetch('/player/server-src?url=' + encodeURIComponent(btn.getAttribute('data-url')) + '&t=' + Date.now(), { cache: 'no-store' })
                        .then(function (r) { return r.json(); })
                        .catch(function () { return null; })
                        .then(function (d) {
                            if (d && d.ok && d.src) apply(d.src);
                            btn.classList.remove('at-resolving');
                            btn.setAttribute('data-resolved', '1');
                            if (cb) cb();
                        });
                }
                // Tab already being shown (e.g. restored preference): resolve now.
                if (btn.classList.contains('active') && frame) {
                    resolve(function () {
                        var u = btn.getAttribute('data-url');
                        if (u) frame.src = u;
                    });
                    return;
                }
                btn.addEventListener('click', function (e) {
                    if (btn.getAttribute('data-resolved') === '1') return;
                    // Take over this click: resolve first, then replay it.
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    resolve(function () { btn.click(); });
                }, true); // capture: runs before the switch handler
            });
        });
    })();
    </script>

    <a style="display:block;margin:16px 0;font-size:16px;font-weight:500;text-align:center;border-radius:8px;padding:5px;background:#389ce9;text-decoration:none;color:#fff;" href="https://tiny.animetadka.com/47LJO" target="_blank">
        <svg style="width:30px;height:20px;vertical-align:middle;margin:0px 5px;" viewBox="0 0 21 18"><g fill="none"><path fill="#ffffff" d="M0.554,7.092 L19.117,0.078 C19.737,-0.156 20.429,0.156 20.663,0.776 C20.745,0.994 20.763,1.23 20.713,1.457 L17.513,16.059 C17.351,16.799 16.62,17.268 15.88,17.105 C15.696,17.065 15.523,16.987 15.37,16.877 L8.997,12.271 C8.614,11.994 8.527,11.458 8.805,11.074 C8.835,11.033 8.869,10.994 8.905,10.958 L15.458,4.661 C15.594,4.53 15.598,4.313 15.467,4.176 C15.354,4.059 15.174,4.037 15.036,4.125 L6.104,9.795 C5.575,10.131 4.922,10.207 4.329,10.002 L0.577,8.704 C0.13,8.55 -0.107,8.061 0.047,7.614 C0.131,7.374 0.316,7.182 0.554,7.092 Z"></path></g></svg>Join Telegram
    </a>

    <style>
        .at-modern-controls { display: flex; align-items: center; justify-content: center; gap: 15px; background: rgba(15, 15, 20, 0.7); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 10px 18px; margin: 0 auto 20px auto; max-width: fit-content; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .at-modern-toggle-item { display: flex; align-items: center; gap: 8px; }
        .at-modern-divider { width: 1px; height: 18px; background: rgba(255,255,255,0.15); border-radius: 2px; }
        .at-modern-label { font-size: 11px; font-weight: 800; color: #e2e8f0; letter-spacing: 0.5px; text-transform: uppercase; user-select: none; }
        .at-mini-switch { position: relative; display: inline-block; width: 32px; height: 18px; flex-shrink: 0; }
        .at-mini-switch input { opacity: 0; width: 0; height: 0; }
        .at-mini-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.2); transition: .3s; border-radius: 34px; }
        .at-mini-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 2px; bottom: 2px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .at-mini-switch input:checked + .at-mini-slider { background-color: #9b59b6; }
        .at-mini-switch input:checked + .at-mini-slider:before { transform: translateX(14px); }
    </style>

    <div id="sa-auto-controls" style="display: none !important;">
        <div class="at-modern-controls">
            <div class="at-modern-toggle-item">
                <span class="at-modern-label">AUTO NEXT</span>
                <label class="at-mini-switch">
                    <input type="checkbox" id="pref-auto-next">
                    <span class="at-mini-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <div id="cdn21-auto-controls" style="display:none;">
        <div class="at-modern-controls">
            <div class="at-modern-toggle-item">
                <span class="at-modern-label">AUTO NEXT</span>
                <label class="at-mini-switch">
                    <input type="checkbox" id="cdn21-auto-next">
                    <span class="at-mini-slider"></span>
                </label>
            </div>
            <div class="at-modern-divider"></div>
            <div class="at-modern-toggle-item">
                <span class="at-modern-label">SKIP INTRO/OUTRO</span>
                <label class="at-mini-switch">
                    <input type="checkbox" id="cdn21-auto-skip">
                    <span class="at-mini-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <div class="sa-server-hint" id="sa-hint-text" style="display: flex !important; justify-content: center; align-items: center; gap: 10px; margin-bottom: 15px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="2.5"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
        <span style="color:#888; font-size:11px; font-weight:900; text-transform:uppercase;">If Current Server Not Working Switch To Another Server</span>
    </div>

    <?php $lazyResolvable = (Config::get('provider') === 'animedekho') && !$isWhPlayer; ?>
    <div class="server-tabs">
        <?php foreach ($episodeServers as $i => $s): ?>
            <?php
            $srvUrl = ($isWhPlayer && $whHash !== '') ? ('/player/watchhentai/' . rawurlencode($whHash) . '?server=' . (int) $i) : ($s['source'] ?: $s['url']);
            // Only animedekho wraps its player behind a wrapper that must be
            // scraped; other providers already hand back the final URL.
            $srvResolved = !$lazyResolvable || !empty($s['resolved']);
            ?>
            <button class="srv-btn<?= ($s['active'] ?? ($i === 0)) ? ' active' : '' ?>" data-url="<?= View::escape($srvUrl) ?>" data-resolved="<?= $srvResolved ? '1' : '0' ?>" data-auto="<?= !empty($s['auto']) ? 'true' : 'false' ?>" data-cdn21="<?= !empty($s['cdn21']) ? 'true' : 'false' ?>">
                <?= View::escape($s['name']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php
    // Download servers are kept out of the streaming tabs and surfaced behind
    // a single Download button that opens a modal listing every quality.
    // animedekho only exposes the raw download.php endpoint up front (resolving
    // it to the real file is the expensive part), so those rows are marked
    // `lazy`: the modal shows a loading row per server and resolves them one by
    // one via /ep/downloads only after the visitor opens it.
    $downloadServers = $episode['download_servers'] ?? [];
    if (empty($downloadServers)) {
        foreach (($episode['download_links'] ?? []) as $dl) {
            if (!is_array($dl) || empty($dl['url'])) continue;
            $downloadServers[] = [
                'name' => $dl['label'] ?? 'Download',
                'url' => $dl['url'],
                'raw' => '',
                'lazy' => false,
                'quality' => '',
                'language' => '',
            ];
        }
    }
    // Only servers that actually carry a download endpoint get a row.
    $downloadServers = array_values(array_filter($downloadServers, static function ($dl) {
        return is_array($dl) && (!empty($dl['url']) || !empty($dl['raw']));
    }));
    $dlSlug = (string) ($episode['hash'] ?? '');
    ?>
    <?php if (!empty($downloadServers)): ?>
    <div class="dl-actions">
        <button type="button" class="dl-open-btn" id="dl-open-btn" aria-haspopup="dialog">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Download</span>
            <span class="dl-open-count"><?= count($downloadServers) ?></span>
        </button>
    </div>
    <div class="dl-modal" id="dl-modal" role="dialog" aria-modal="true" aria-label="Download servers" hidden>
        <div class="dl-modal-backdrop" data-dl-close></div>
        <div class="dl-modal-panel">
            <div class="dl-modal-head">
                <h3>Download Servers</h3>
                <button type="button" class="dl-modal-x" data-dl-close aria-label="Close">&times;</button>
            </div>
            <div class="dl-modal-list" id="dl-modal-list">
                <?php foreach ($downloadServers as $dl): ?>
                <?php if (!empty($dl['lazy'])): ?>
                <div class="dl-item dl-item--pending" data-dl-lazy>
                    <span class="dl-item-name"><?= View::escape($dl['name'] ?: 'Download') ?></span>
                    <span class="dl-item-meta">
                        <?php if (!empty($dl['quality'])): ?><span class="dl-tag"><?= View::escape($dl['quality']) ?></span><?php endif; ?>
                        <?php if (!empty($dl['language'])): ?><span class="dl-tag"><?= View::escape($dl['language']) ?></span><?php endif; ?>
                    </span>
                    <span class="dl-item-status"><span class="dl-spinner" aria-hidden="true"></span>Fetching link…</span>
                </div>
                <?php else: ?>
                <a class="dl-item" href="<?= View::escape($dl['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">
                    <span class="dl-item-name"><?= View::escape($dl['name'] ?: 'Download') ?></span>
                    <span class="dl-item-meta">
                        <?php if (!empty($dl['quality'])): ?><span class="dl-tag"><?= View::escape($dl['quality']) ?></span><?php endif; ?>
                        <?php if (!empty($dl['language'])): ?><span class="dl-tag"><?= View::escape($dl['language']) ?></span><?php endif; ?>
                    </span>
                    <svg class="dl-item-dl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                </a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var openBtn = document.getElementById('dl-open-btn');
        var modal = document.getElementById('dl-modal');
        if (!openBtn || !modal) return;
        var list = document.getElementById('dl-modal-list');
        var slug = <?= json_encode($dlSlug) ?>;
        var lazyRows = list ? Array.prototype.slice.call(list.querySelectorAll('[data-dl-lazy]')) : [];
        var loaded = false;
        function open() { modal.hidden = false; document.body.style.overflow = 'hidden'; }
        function close() { modal.hidden = true; document.body.style.overflow = ''; }
        openBtn.addEventListener('click', function () { open(); loadDownloads(); });
        modal.addEventListener('click', function (e) { if (e.target.hasAttribute('data-dl-close')) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });

        var DL_ICON = '<svg class="dl-item-dl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';

        // Turn a pending row into a real link (or an "unavailable" notice).
        function fillRow(row, dl) {
            row.classList.remove('dl-item--pending');
            var status = row.querySelector('.dl-item-status');
            if (dl && dl.url) {
                var a = document.createElement('a');
                a.className = 'dl-item dl-item--reveal';
                a.href = dl.url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer nofollow';
                a.innerHTML = row.innerHTML;
                var st = a.querySelector('.dl-item-status');
                if (st) st.remove();
                a.insertAdjacentHTML('beforeend', DL_ICON);
                row.replaceWith(a);
            } else {
                row.classList.add('dl-item--empty');
                if (status) status.innerHTML = 'Link unavailable';
            }
        }

        // Resolve the pending rows one at a time so the list fills in
        // top-to-bottom with a short reveal between each.
        function loadDownloads() {
            if (loaded || !lazyRows.length || !slug) return;
            loaded = true;
            fetch('/ep/downloads?slug=' + encodeURIComponent(slug), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var downloads = (data && data.downloads) || [];
                    lazyRows.forEach(function (row, i) {
                        setTimeout(function () { fillRow(row, downloads[i]); }, i * 350);
                    });
                })
                .catch(function () {
                    lazyRows.forEach(function (row) { fillRow(row, null); });
                });
        }
    })();
    </script>
    <?php endif; ?>

    <?php if (!empty($downloadServers)): ?>
    <style>
        .dl-actions { display: flex; justify-content: center; margin: 4px 0 22px; }
        .dl-open-btn { display: inline-flex; align-items: center; gap: 9px; background: linear-gradient(135deg, #9b59b6, #7d3c98); color: #fff; border: 0; border-radius: 14px; padding: 12px 26px; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 5px 15px rgb(155 89 182 / .4); transition: transform .15s ease, box-shadow .15s ease; }
        .dl-open-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgb(155 89 182 / .55); }
        .dl-open-btn svg { width: 18px; height: 18px; }
        .dl-open-count { background: rgb(0 0 0 / .3); border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 800; }
        .dl-modal[hidden] { display: none; }
        .dl-modal { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .dl-modal-backdrop { position: absolute; inset: 0; background: rgb(0 0 0 / .72); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
        .dl-modal-panel { position: relative; width: 100%; max-width: 460px; max-height: 80vh; overflow: hidden; display: flex; flex-direction: column; background: #14141c; border: 1px solid rgb(255 255 255 / .1); border-radius: 18px; box-shadow: 0 24px 70px rgb(0 0 0 / .55); }
        .dl-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid rgb(255 255 255 / .08); }
        .dl-modal-head h3 { margin: 0; font-size: 16px; font-weight: 800; color: #fff; }
        .dl-modal-x { background: none; border: 0; color: #999; font-size: 26px; line-height: 1; cursor: pointer; padding: 0 4px; }
        .dl-modal-x:hover { color: #fff; }
        .dl-modal-list { padding: 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
        .dl-item { display: flex; align-items: center; gap: 12px; padding: 13px 16px; border-radius: 12px; background: rgb(255 255 255 / .04); border: 1px solid rgb(255 255 255 / .06); text-decoration: none; color: #e8e8f0; transition: background .15s ease, border-color .15s ease; }
        .dl-item:hover { background: rgb(155 89 182 / .18); border-color: rgb(155 89 182 / .5); }
        .dl-item-name { flex: 1; font-size: 14px; font-weight: 700; }
        .dl-item-meta { display: flex; gap: 6px; }
        .dl-tag { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #c9a7e0; background: rgb(155 89 182 / .18); border-radius: 6px; padding: 3px 7px; }
        .dl-item-dl { width: 18px; height: 18px; color: #9b59b6; flex-shrink: 0; }
        .dl-item-status { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 700; color: #9a9ab0; }
        .dl-item--pending { border-style: dashed; }
        .dl-item--empty .dl-item-status { color: #d98; }
        .dl-item--reveal { animation: dlReveal .28s ease; }
        @keyframes dlReveal { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
        .dl-spinner { width: 14px; height: 14px; border: 2px solid rgb(255 255 255 / .2); border-top-color: #9b59b6; border-radius: 50%; animation: dlSpin .7s linear infinite; flex-shrink: 0; }
        @keyframes dlSpin { to { transform: rotate(360deg); } }
    </style>
    <?php endif; ?>

    <?php if (!empty($episode['prev_episode_url']) || !empty($episode['next_episode_url'])): ?>
    <div class="ep-nav-flex">
        <?php if (!empty($episode['prev_episode_url'])): ?><a href="<?= View::escape(View::hurl($episode['prev_episode_url']) . '?type=episode') ?>" class="nav-btn">← Previous</a><?php endif; ?><?php if (!empty($episode['next_episode_url'])): ?><a href="<?= View::escape(View::hurl($episode['next_episode_url']) . '?type=episode') ?>" class="nav-btn" id="next-ep-btn">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="ep-header">
        <h1 class="ep-main-title">
            <span class="title-accent" style="color: var(--gold) !important;"><?= View::escape($episode['accent'] ?? '') ?></span>
            <span class="title-main" style="color: #fff !important;"><?= View::escape($episode['title'] ?? '') ?></span>
        </h1>
        <?php if (!empty($episode['rating']) || !empty($episode['runtime'])): ?>
        <div class="ep-meta-row-animated">
            <?php if (!empty($episode['rating'])): ?>
            <div class="meta-item-box">
                <svg class="icon-star" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"></path></svg>
                <span>Rating: <strong><?= View::escape($episode['rating']) ?></strong></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($episode['runtime'])): ?>
            <div class="meta-item-box">
                <svg class="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Runtime: <strong><?= View::escape($episode['runtime']) ?></strong></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($episode['description'])): ?>
        <div class="ep-content-text"><p><?= View::escape($episode['description']) ?></p></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($episodes)): ?>
    <div class="list-section">
        <h4 style="margin-top: 30px; font-weight: 700; color: #fff;">OTHER EPISODES</h4>

        <div class="ep-search-container">
            <div style="position: relative; flex-grow: 1; display: flex; align-items: center;">
                <div class="ep-search-icon-prefix" style="position: absolute; left: 18px; width: 20px; height: 20px; color: rgba(181, 53, 246, 0.7); pointer-events: none; z-index: 2; transition: all 0.3s ease;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
                <input type="text" id="ep-number-search" class="ep-search-input" placeholder="Search episode number..." inputmode="numeric" pattern="[0-9]*" style="padding-left: 55px; width: 100%;">
                <button id="ep-search-clear" class="ep-search-clear" aria-label="Clear search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <div class="ep-scroll-wrapper" id="ep-scroll-container">
            <div id="ep-search-loader" class="ep-search-loader" style="display: none;">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>

            <div id="ep-search-no-results" class="ep-search-no-results" style="display: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>No episode found.</span>
            </div>

            <div class="ep-list-drawer-cinematic" id="ep-list">
                <?php foreach ($episodes as $ep): ?>
                    <a href="<?= View::escape(View::hurl('/ep/') . View::escape($ep['hash']) . '/?type=episode') ?>" id="<?= View::escape($ep['num']) ?>" class="ep-row-premium<?= !empty($ep['active']) ? ' is-playing' : '' ?>" data-hash="<?= View::escape($ep['hash']) ?>" data-ep-num="<?= View::escape($ep['num']) ?>" data-num="<?= View::escape($ep['num']) ?>" data-anime="<?= View::escape($episode['animeSlug'] ?? '') ?>">
                        <div class="thumb-wrap">
                            <img src="<?= View::escape($ep['thumb']) ?>" loading="lazy" alt="E<?= View::escape($ep['num']) ?>">
                            <div class="watched-ribbon" style="display:none"><span>WATCHED</span></div>
                        </div>
                        <div class="ep-info-wrap">
                            <strong>E<?= View::escape($ep['num']) ?>: <?php if (($ep['accent'] ?? '') !== ''): ?><span style="color:#ffff00;"><?= View::escape($ep['accent']) ?></span> <?php endif; ?><?= View::escape($ep['title']) ?></strong>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <script>window.__AT_CURRENT__ = {animeSlug: <?= json_encode($episode['animeSlug'] ?? '') ?>, hash: <?= json_encode($episode['hash'] ?? '') ?>, num: <?= json_encode($episode['number'] ?? '') ?>, title: <?= json_encode($episode['title'] ?? '') ?>, poster: <?= json_encode($episode['animePoster'] ?? '') ?>, animeTitle: <?= json_encode($episode['animeTitle'] ?? '') ?>};</script>
            <script>
// Optimized int-only search - overrides theme.js slow 400ms loader + parseInt loop
(function(){
  const input = document.getElementById('ep-number-search');
  const clearBtn = document.getElementById('ep-search-clear');
  const loader = document.getElementById('ep-search-loader');
  const noRes = document.getElementById('ep-search-no-results');
  const list = document.getElementById('ep-list');
  if(!input || !list) return;
  // remove old listeners by cloning
  const newInput = input.cloneNode(true);
  input.parentNode.replaceChild(newInput, input);
  const inp = newInput;
  const newClear = clearBtn.cloneNode(true);
  clearBtn.parentNode.replaceChild(newClear, clearBtn);
  const clr = newClear;
  // build fast index: num(str) -> element, also by id
  const rows = Array.from(list.querySelectorAll('.ep-row-premium'));
  // Use full id + data attributes to distinguish same ep number across seasons (e.g. S2E2 vs S3E2, 1x2 vs 2x2)
  const rowIndex = rows.map(r => {
    const id = (r.id || '').toLowerCase();
    const epNum = (r.getAttribute('data-ep-num') || r.getAttribute('data-num') || '').toLowerCase();
    const hash = (r.getAttribute('data-hash') || r.getAttribute('href') || '').toLowerCase();
    const title = (r.textContent || '').toLowerCase();
    return {el: r, keys: [id, epNum, hash, title].join(' ')};
  });
  // hide loader initially (perf: no 400ms delay)
  if(loader) loader.style.display='none';
  let lastVal='';
  inp.addEventListener('input', ()=>{
    let v = inp.value.replace(/[^0-9xX]/g,'').slice(0,6).toLowerCase();
    if(v!==inp.value) inp.value=v;
    if(v===lastVal) return;
    lastVal=v;
    if(!v){
      clr.style.display='none';
      if(noRes) noRes.style.display='none';
      list.style.display='';
      rows.forEach(r=>r.style.display='');
      return;
    }
    clr.style.display='flex';
    const q = v.toLowerCase();
    let found = 0;
    rowIndex.forEach(({el, keys}) => {
      // Match full id like "2" or "s3e2" or "1x2": require exact token or substring with word boundary
      const isMatch = keys.includes(q) || keys.includes('s' + q) || keys.includes('x' + q);
      el.style.display = isMatch ? '' : 'none';
      if (isMatch) found++;
    });
    if(found){
      list.style.display='';
      if(noRes) noRes.style.display='none';
      const first = rowIndex.find(({el}) => el.style.display !== 'none');
      if(first) first.el.scrollIntoView({behavior:'smooth', block:'center'});
    } else {
      list.style.display='none';
      if(noRes) noRes.style.display='flex';
    }
  });
  clr.addEventListener('click', ()=>{
    inp.value=''; lastVal='';
    clr.style.display='none';
    if(noRes) noRes.style.display='none';
    list.style.display='';
    rows.forEach(r=>r.style.display='');
    inp.focus();
  });
  // also support direct #hash jump via id
  if(location.hash){
    const h=location.hash.replace('#','').toLowerCase();
    const found = rowIndex.find(({keys}) => keys.includes(h));
    if(found) found.el.scrollIntoView({block:'center'});
  }
})();
            </script>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($seasons)): ?>
    <h4 style="margin-top:50px;">OTHER SEASONS</h4>
    <div class="season-grid-cinematic">
        <?php foreach ($seasons as $s): ?>
            <a href="<?= View::escape(View::hurl('/ep/') . View::escape($s['hash']) . '/?type=episode') ?>" class="season-card-rect">
                <div class="s-bg-blur" style="background-image: url('<?= View::escape($s['poster']) ?>');"></div>
                <span class="s-label"><?= View::escape($s['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($recommendations)): ?>
    <section class="reco-section">
        <div class="reco-header">
            <svg class="reco-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            <h3>Recommended For You</h3>
        </div>
        <div class="reco-slider-container">
            <div class="reco-slider" id="recoSlider">
                <?php foreach ($recommendations as $r): ?>
                    <div class="reco-item">
                        <a href="<?= View::escape(View::typeHref('/', $r['type'] ?? 'series', $r['slug'])) ?>">
                            <div class="reco-poster">
                                <img width="200" height="300" src="<?= View::escape($r['poster']) ?>" loading="lazy" class="attachment-medium size-medium wp-post-image" alt="">
                                <div class="reco-overlay"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg></div>
                            </div>
                            <span class="reco-title"><?= View::escape($r['title']) ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="reco-dots" id="recoDots"><div class="reco-dot active"></div><div class="reco-dot"></div><div class="reco-dot"></div><div class="reco-dot"></div><div class="reco-dot"></div><div class="reco-dot"></div></div>
    </section>
    <?php endif; ?>
</div>