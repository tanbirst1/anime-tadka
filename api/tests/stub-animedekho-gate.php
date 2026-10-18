<?php

declare(strict_types=1);

/**
 * Stub of animedekho.tv's dgt ad-gate for AnimeDekho provider tests.
 *
 * Run: php -S 127.0.0.1:8901 tests/stub-animedekho-gate.php
 *
 * It reproduces the real gate's timing check: the `?dgt_verify=1` token
 * carries the epoch it was minted (`i`) and is only honoured once at least
 * GATE_DWELL seconds have elapsed; earlier hits answer 403 "That was too
 * fast" and set no cookie. That is exactly the condition the provider's
 * solveGate() has to respect.
 *
 * Env knobs (set on the stub process, so tests stay fast):
 *   STUB_GATE_DWELL  seconds the gate makes a visitor wait (default 2)
 *   STUB_GATE_REJECT when set, the verify token always 403s (unsolvable gate)
 */

$gateDwell = (int) (getenv('STUB_GATE_DWELL') ?: 2);
$gateReject = getenv('STUB_GATE_REJECT') !== false;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8901');

/** Whether the client already holds the unlock cookie. */
$verified = isset($_COOKIE['dgt_verified']);

if ($path === '/' && isset($_GET['dgt_gate'])) {
    // Ad-gate page: a single skip form whose shortlink is the verify token.
    $next = (string) ($_GET['next'] ?? ($base . '/epi/overgeared-1x4/'));
    $token = $base . '/?dgt_verify=1&e=' . (time() + 3600) . '&i=' . time()
        . '&t=' . bin2hex(random_bytes(8)) . '&next=' . rawurlencode($next);
    if ($gateReject) {
        $token .= '&reject=1';
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body><div class="dgt-ch"><form class="dgt-ch__form" method="post" action="' . $base . '/skip/vshort.php">'
        . '<input type="hidden" name="postlink" value="' . htmlspecialchars($next, ENT_QUOTES) . '">'
        . '<input type="hidden" name="shortlink" value="' . htmlspecialchars($token, ENT_QUOTES) . '">'
        . '<button type="submit">Skip AD [v1] and Enjoy</button></form></div></body></html>';
    exit;
}

if ($path === '/' && isset($_GET['dgt_verify'])) {
    $i = isset($_GET['i']) && is_numeric($_GET['i']) ? (int) $_GET['i'] : 0;
    if ($gateReject || $i <= 0 || (time() - $i) < $gateDwell) {
        // The real gate's "That was too fast" reply: no cookie, 403.
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Verification</title></head><body>'
            . 'That was too fast. Please complete the ad step first. Go back and try again</body></html>';
        exit;
    }
    setcookie('dgt_verified', time() . '.' . bin2hex(random_bytes(8)), [
        'expires' => time() + 86400,
        'path' => '/',
        'httponly' => true,
    ]);
    $next = (string) ($_GET['next'] ?? ($base . '/epi/overgeared-1x4/'));
    header('Location: ' . $next, true, 302);
    exit;
}

if ($path === '/wp-json/dgt/v1/watch') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$verified) {
        echo json_encode([
            'gated' => true,
            'gate' => $base . '/?dgt_gate=1&next=' . rawurlencode($base . '/epi/overgeared-1x4/'),
            'hours' => 24,
        ]);
        exit;
    }
    echo json_encode([
        'gated' => false,
        'sources' => [
            [
                'id' => 0,
                'kind' => 'embed',
                'server' => 'HydraX',
                'language' => '1080p',
                'quality' => 'Multi-Audio',
                'embed' => $base . '/player.php?url=AAA',
                'show_embed' => true,
                'show_download' => false,
            ],
            [
                'id' => 1,
                'kind' => 'embed',
                'server' => 'Vidmoly',
                'embed' => 'https://vidmoly.biz/embed-8m6y2ks7x4wr.html',
                'show_embed' => true,
                'show_download' => false,
            ],
        ],
    ]);
    exit;
}

if ($path === '/player.php') {
    // Same-site wrapper exposing the external player in an iframe.
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body><iframe src="https://abyssplayer.com/m5bXILRqW" allowfullscreen></iframe></body></html>';
    exit;
}

if (preg_match('#^/epi/[^/]+/?$#', $path) === 1) {
    $slug = trim(explode('/', trim($path, '/'))[1] ?? 'overgeared-1x4');
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><head><title>' . htmlspecialchars($slug) . '</title></head><body>'
        . '<div class="dgt-watch" id="dgt-watch" data-series="15611" data-term="59794"'
        . ' data-episode="{&quot;label&quot;:&quot;S1 E4&quot;,&quot;name&quot;:&quot;Episode 4&quot;}"'
        . ' data-snapshot="{&quot;title&quot;:&quot;Overgeared&quot;,&quot;id&quot;:15611}"></div>'
        . '<div class="dgt-crumbs"><a href="/series-hindi/overgeared/">← Overgeared</a></div>'
        . '</body></html>';
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo 'Not found';
