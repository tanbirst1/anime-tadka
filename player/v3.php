<?php
require_once('../_config.php');
$slug = isset($_GET['slug']) ? htmlspecialchars($_GET['slug']) : '';
if (empty($slug)) { die('Error: No slug'); }

// Scrape video page for player iframe URL and video source
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://watchhentai.net/videos/' . rawurlencode($slug) . '/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER => ['Accept: text/html'],
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$title = 'Watch Hentai';
$thumbnail = '';
$videoSrc = '';
$playerUrl = '';

if ($html !== false && $httpCode < 400) {
    if (preg_match('/<h1>([^<]+)<\/h1>/i', $html, $m)) {
        $title = html_entity_decode(trim(preg_replace('/\s*-\s*Watch Hentai\s*$/i', '', $m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('/itemprop="thumbnailUrl"\s+content="([^"]+)"/i', $html, $m)) {
        $thumbnail = $m[1];
    } elseif (preg_match("/<meta\s+property='og:image'\s+content='([^']+)'/i", $html, $m)) {
        $thumbnail = $m[1];
    }
    if (preg_match('/data-litespeed-src=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
        $playerUrl = str_replace('&amp;', '&', $m[1]);
    } elseif (preg_match('/src=[\'"]([^\'"]*jwplayer[^\'"]*)[\'"]/i', $html, $m)) {
        $playerUrl = str_replace('&amp;', '&', $m[1]);
    }
}

// Try to extract video source: first from the player iframe page, then from query param
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
        }
    }
    if (empty($videoSrc) && preg_match('/[?&;]source=([^&;]+)/i', $playerUrl, $m)) {
        $videoSrc = urldecode($m[1]);
    }
}

$hasVideo = !empty($videoSrc);
$proxyUrl = $hasVideo ? '/hentai/player/proxy.php?url=' . urlencode($videoSrc) : '';
$downloadUrl = '/hentai/download/' . urlencode($slug);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./style.css">
    <style>
        .player-fallback { display:none; width:100%; height:100%; }
        .player-fallback iframe { width:100%; height:100%; border:none; }
    </style>
</head>
<body>
    <div id="player"></div>
    <div class="player-fallback" id="playerFallback">
        <iframe src="<?php echo htmlspecialchars($playerUrl ?: 'https://watchhentai.net/videos/' . rawurlencode($slug) . '/'); ?>" allow="autoplay; fullscreen" allowfullscreen></iframe>
    </div>

    <script>
    var debugInfo = { playerUrl: <?php echo json_encode($playerUrl); ?>, videoSrc: <?php echo json_encode($videoSrc); ?>, proxyUrl: <?php echo json_encode($proxyUrl); ?> };
    console.log("V3 Player Debug:", debugInfo);
    </script>

    <script src='https://content.jwplatform.com/libraries/KB5zFt7A.js'></script>
    <script>
<?php if ($hasVideo): ?>
        const playerInstance = jwplayer("player").setup({
            controls: true, sharing: true, displaytitle: true, displaydescription: true,
            abouttext: "watch hentai", aboutlink: "<?php echo $websiteUrl; ?>",
            skin: { name: "netflix" },
            logo: { file: "", link: "" },
            captions: { color: "#FFF", fontSize: 14, backgroundOpacity: 0, edgeStyle: "raised" },
            image: "<?php echo htmlspecialchars($thumbnail); ?>",
            playlist: [{ title: "", description: "", image: "<?php echo htmlspecialchars($thumbnail); ?>", sources: [{ file: "<?php echo $proxyUrl; ?>", type: "mp4", label: "1080p", "default": true }] }],
        });

        playerInstance.on("ready", function () {
            const buttonId = "download-video-button";
            const iconPath = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDB6Ii8+PHBhdGggZD0iTTMgMTloMTh2Mkgzdi0yem0xMC01LjgyOEwxOS4wNzEgNy4xbDEuNDE0IDEuNDE0TDEyIDE3IDMuNTE1IDguNTE1IDQuOTI5IDcuMSAxMSAxMy4xN1YyaDJ2MTEuMTcyeiIgZmlsbD0icmdiYSgyNDcsMjQ3LDI0NywxKSIvPjwvc3ZnPg==";
            playerInstance.addButton(iconPath, "Download Video", function() { window.open("<?php echo $downloadUrl; ?>", "_blank"); }, buttonId);
            // Forward button setup
            try {
                var pc = playerInstance.getContainer();
                var rc = pc.querySelector(".jw-display-icon-rewind");
                if (rc) {
                    var fc = rc.cloneNode(true);
                    var fb = fc.querySelector(".jw-icon-rewind"); fb.style.transform = "scaleX(-1)"; fb.ariaLabel = "Forward 10s";
                    var nc = pc.querySelector(".jw-display-icon-next");
                    if (nc && nc.parentNode) nc.parentNode.insertBefore(fc, nc);
                    var bc = pc.querySelector(".jw-button-container");
                    var rb = bc ? bc.querySelector(".jw-icon-rewind") : null;
                    if (rb) {
                        var fcb = rb.cloneNode(true); fcb.style.transform = "scaleX(-1)"; fcb.ariaLabel = "Forward 10s";
                        rb.parentNode.insertBefore(fcb, rb.nextElementSibling);
                        [fb, fcb].forEach(function(b) { b.onclick = function() { var p = playerInstance.getPosition(); playerInstance.seek(Math.min(p + 10, playerInstance.getDuration())); }; });
                    }
                }
            } catch(e) { console.log("UI setup:", e); }
            document.addEventListener('keydown', function(e) {
                if (e.target.tagName === 'INPUT') return;
                switch(e.key) { case 'ArrowRight': e.preventDefault(); playerInstance.seek(playerInstance.getPosition() + 10); break; case 'ArrowLeft': e.preventDefault(); playerInstance.seek(Math.max(playerInstance.getPosition() - 10, 0)); break; case ' ': case 'k': e.preventDefault(); playerInstance.playToggle(); break; case 'f': e.preventDefault(); playerInstance.setFullscreen(!playerInstance.getFullscreen()); break; case 'm': e.preventDefault(); playerInstance.setMute(!playerInstance.getMute()); break; }
            });
        });

        playerInstance.on("error", function(e) {
            console.error("JWPlayer error, falling back to iframe:", e, debugInfo);
            document.getElementById('player').style.display = 'none';
            document.getElementById('playerFallback').style.display = 'block';
        });
<?php else: ?>
        document.getElementById('player').style.display = 'none';
        document.getElementById('playerFallback').style.display = 'block';
<?php endif; ?>
    </script>
</body>
</html>
