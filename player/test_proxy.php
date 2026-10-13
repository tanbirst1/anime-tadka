<?php
$url = $_GET['url'] ?? '';
if (empty($url)) { http_response_code(400); echo 'no url'; exit; }

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => ['Referer: https://watchhentai.net/', 'User-Agent: Mozilla/5.0'],
    CURLOPT_HEADER => true,
]);
$resp = curl_exec($ch);
$info = curl_getinfo($ch);
$err = curl_error($ch);
curl_close($ch);

header('Content-Type: text/plain');
echo "HTTP: " . $info['http_code'] . "\n";
echo "Size: " . strlen($resp) . "\n";
echo "Error: $err\n";
echo "URL: $url\n";
echo "Content-Type: " . ($info['content_type'] ?? 'none') . "\n";
if (strlen($resp) > 0) {
    $parts = explode("\r\n\r\n", $resp, 2);
    echo "--- Headers ---\n" . $parts[0] . "\n";
    echo "--- Body (first 200) ---\n" . substr($parts[1] ?? '', 0, 200) . "\n";
}
