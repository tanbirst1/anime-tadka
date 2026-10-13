<?php
$url = $_GET['url'] ?? '';
if (empty($url)) { http_response_code(400); exit; }

$headers = [
    'Referer: https://watchhentai.net/',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
];
if (!empty($_SERVER['HTTP_RANGE'])) {
    $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_BUFFERSIZE => 256 * 1024,
    CURLOPT_HEADERFUNCTION => function($ch, $line) {
        $t = trim($line);
        if ($t === '' || stripos($t, 'HTTP/') === 0 || stripos($t, 'Content-Type:') === 0 || stripos($t, 'Content-Length:') === 0 || stripos($t, 'Content-Range:') === 0 || stripos($t, 'Accept-Ranges:') === 0) {
            header($t);
        }
        return strlen($line);
    },
]);

curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(502);
}
curl_close($ch);
