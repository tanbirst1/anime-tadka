<?php
require_once('../_config.php');
$slug = isset($_GET['slug']) ? htmlspecialchars($_GET['slug']) : '';
if (empty($slug)) { die('Error: No slug'); }

// Step 1: Scrape video page for player iframe URL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://watchhentai.net/videos/' . rawurlencode($slug) . '/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER => ['Accept: text/html'],
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($httpCode >= 400) $html = false;

$title = 'Unknown Title';
$thumbnail = '';
$description = '';
$downloadUrl = '';
$videoSrc = '';
$playerUrl = '';

if ($html !== false) {
    if (preg_match('/<h1>([^<]+)<\/h1>/i', $html, $m)) {
        $title = html_entity_decode(trim(preg_replace('/\s*-\s*Watch Hentai\s*$/i', '', $m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('/itemprop="thumbnailUrl"\s+content="([^"]+)"/i', $html, $m)) {
        $thumbnail = $m[1];
    } elseif (preg_match("/<meta\s+property='og:image'\s+content='([^']+)'/i", $html, $m)) {
        $thumbnail = $m[1];
    }
    $seriesTitle = '';
    if (preg_match('/<a href="https:\/\/watchhentai\.net\/series\/[^"]+"[^>]*>([^<]+)<\/a>/i', $html, $m)) {
        $seriesTitle = trim($m[1]);
    }
    $synopsis = '';
    if (preg_match('/<div class="synopsis">\s*<p>([\s\S]*?)<\/p>/i', $html, $m)) {
        $synopsis = trim(strip_tags($m[1]));
    }
    if ($seriesTitle) $description .= $seriesTitle . "\n\n";
    if ($synopsis) $description .= $synopsis . "\n\n";

    if (preg_match('/data-litespeed-src=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
        $playerUrl = str_replace('&amp;', '&', $m[1]);
    } elseif (preg_match('/src=[\'"]([^\'"]*jwplayer[^\'"]*)[\'"]/i', $html, $m)) {
        $playerUrl = str_replace('&amp;', '&', $m[1]);
    }
}

// Step 2: Fetch the player iframe page to find the real video source
if ($playerUrl) {
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $playerUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_REFERER => 'https://watchhentai.net/',
    ]);
    $playerHtml = curl_exec($ch2);
    $playerHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($playerHtml !== false && $playerHttpCode < 400) {
        if (preg_match('/["\']file["\']\s*:\s*["\']([^"\']+)["\']/i', $playerHtml, $m)) {
            $videoSrc = $m[1];
        } elseif (preg_match('/<source\s+src=["\']([^"\']+)["\']/i', $playerHtml, $m)) {
            $videoSrc = $m[1];
        } elseif (preg_match('/data-file=["\']([^"\']+)["\']/i', $playerHtml, $m)) {
            $videoSrc = $m[1];
        }
    }
}

// Fallback: extract source from player URL query param
if (empty($videoSrc) && $playerUrl && preg_match('/[?&;]source=([^&;]+)/i', $playerUrl, $m)) {
    $videoSrc = urldecode($m[1]);
}

$hasVideo = !empty($videoSrc);
$proxyUrl = $hasVideo ? '/hentai/player/proxy.php?url=' . urlencode($videoSrc) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?> - JWPLAYER</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div id="player"></div>

    <script src='https://content.jwplatform.com/libraries/KB5zFt7A.js'></script>
    <script>
        const playerInstance = jwplayer("player").setup({
            controls: true,
            sharing: true,
            displaytitle: true,
            displaydescription: true,
            abouttext: "Buy Me a Coffee",
            aboutlink: "https://www.buymeacoffee.com/pingo",
            skin: { name: "netflix" },
            logo: { file: "", link: "" },
            captions: { color: "#FFF", fontSize: 14, backgroundOpacity: 0, edgeStyle: "raised" },
            image: "<?php echo htmlspecialchars($thumbnail); ?>",
            playlist: [{
                title: "",
                description: "",
                image: "<?php echo htmlspecialchars($thumbnail); ?>",
                sources: [<?php if ($hasVideo): ?>{ file: "<?php echo $proxyUrl; ?>", type: "mp4", label: "1080p", "default": true }<?php endif; ?>]
            }],
            advertising: { client: "vast", schedule: [{ offset: "", tag: "" }] }
        });

        playerInstance.on("ready", function () {
            const styleTitle = () => {
                const tc = document.querySelector('.jw-title');
                const pt = document.querySelector('.jw-title-primary');
                const st = document.querySelector('.jw-title-secondary');
                if (tc) { tc.style.textAlign = 'left'; tc.style.left = '10px'; tc.style.right = 'auto'; tc.style.maxWidth = '80%'; }
                if (pt) { pt.style.whiteSpace = 'nowrap'; pt.style.overflow = 'hidden'; pt.style.textOverflow = 'ellipsis'; pt.style.maxWidth = '100%'; }
                if (st) { st.style.textAlign = 'left'; st.style.display = '-webkit-box'; st.style.webkitLineClamp = '4'; st.style.webkitBoxOrient = 'vertical'; st.style.overflow = 'hidden'; st.style.textOverflow = 'ellipsis'; }
            };
            styleTitle(); setTimeout(styleTitle, 100); setTimeout(styleTitle, 500);

            const buttonId = "download-video-button";
            const iconPath = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDB6Ii8+PHBhdGggZD0iTTMgMTloMTh2Mkgzdi0yem0xMC01LjgyOEwxOS4wNzEgNy4xbDEuNDE0IDEuNDE0TDEyIDE3IDMuNTE1IDguNTE1IDQuOTI5IDcuMSAxMSAxMy4xN1YyaDJ2MTEuMTcyeiIgZmlsbD0icmdiYSgyNDcsMjQ3LDI0NywxKSIvPjwvc3ZnPg==";
            playerInstance.addButton(iconPath, "Download Video", function() {
                window.open("<?php echo '/hentai/download/' . urlencode($slug); ?>", "_blank");
            }, buttonId);

            playerInstance.on("adBlock", () => { const m = document.querySelector("div.modal"); if (m) m.style.display = "flex"; });
            const closeBtn = document.getElementById("close");
            if (closeBtn) closeBtn.addEventListener("click", () => location.reload());

            const pc = playerInstance.getContainer();
            const bc = pc.querySelector(".jw-button-container");
            const rc = pc.querySelector(".jw-display-icon-rewind");
            if (rc) {
                const fc = rc.cloneNode(true);
                const fb = fc.querySelector(".jw-icon-rewind");
                fb.style.transform = "scaleX(-1)"; fb.ariaLabel = "Forward 10 Seconds";
                const nc = pc.querySelector(".jw-display-icon-next");
                if (nc && nc.parentNode) nc.parentNode.insertBefore(fc, nc);
                <?php if (empty($episode['nextEpisode'])): ?>if (nc) nc.style.display = "none";<?php endif; ?>
                const rb = bc ? bc.querySelector(".jw-icon-rewind") : null;
                if (rb) {
                    const fcb = rb.cloneNode(true); fcb.style.transform = "scaleX(-1)"; fcb.ariaLabel = "Forward 10 Seconds";
                    rb.parentNode.insertBefore(fcb, rb.nextElementSibling);
                    [fb, fcb].forEach(b => { b.onclick = () => { const p = playerInstance.getPosition(); playerInstance.seek(Math.min(p + 10, playerInstance.getDuration())); }; });
                }
            }

            document.addEventListener('keydown', (e) => {
                if (e.target.tagName === 'INPUT') return;
                switch(e.key) {
                    case 'ArrowRight': e.preventDefault(); playerInstance.seek(playerInstance.getPosition() + 10); break;
                    case 'ArrowLeft': e.preventDefault(); playerInstance.seek(Math.max(playerInstance.getPosition() - 10, 0)); break;
                    case ' ': case 'k': e.preventDefault(); playerInstance.playToggle(); break;
                    case 'f': e.preventDefault(); playerInstance.setFullscreen(!playerInstance.getFullscreen()); break;
                    case 'm': e.preventDefault(); playerInstance.setMute(!playerInstance.getMute()); break;
                }
            });
        });

        playerInstance.on("error", function(e) {
            console.error("Player error:", e);
            <?php if ($hasVideo): ?>
            // Fallback: try direct source
            playerInstance.load([{ file: "<?php echo htmlspecialchars($videoSrc); ?>", type: "mp4" }]);
            playerInstance.play();
            <?php endif; ?>
        });
    </script>
</body>
</html>
