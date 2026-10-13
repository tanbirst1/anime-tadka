<?php
require_once __DIR__ . '/config.php';

function httpGet($url, $params = []) {
  if (!empty($params)) {
    $sep = strpos($url, '?') === false ? '?' : '&';
    $url .= $sep . http_build_query($params);
  }

  $isApi = strpos($url, 'anikotoapi.site') !== false;
  $headers = json_decode($isApi ? API_HEADERS : HEADERS, true);

  // Guard: without ext-curl curl_init() and the CURLOPT_* constants are
  // undefined and referencing them is a fatal Error.
  if (!function_exists('curl_init') || !function_exists('curl_setopt_array')) {
    $headerLines = [];
    foreach ($headers as $k => $v) {
      if (strtolower((string) $k) === 'accept-encoding') {
        continue; // stream wrapper does not decode compressed bodies
      }
      $headerLines[] = $k . ': ' . $v;
    }
    $result = httpViaStream($url, 'GET', $headerLines, null);
    if ($result[2] !== '') throw new Exception("HTTP request failed: {$result[2]}");
    if ($result[1] >= 400) throw new Exception("Request failed with status code {$result[1]} for $url");
    return $result[0];
  }

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => TIMEOUT,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => '',
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // prefer IPv4: dual-stack DNS stalls
    CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers),
  ]);

  $body = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  // Retry without brotli if encoding issue
  if (stripos($error, 'encoding') !== false) {
    $ch = curl_init();
    $headers['Accept-Encoding'] = 'gzip, deflate';
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => TIMEOUT,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_ENCODING => 'gzip, deflate',
      CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // prefer IPv4: dual-stack DNS stalls
      CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers),
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
  }

  if ($error) throw new Exception("HTTP request failed: $error");
  if ($httpCode >= 400) throw new Exception("Request failed with status code $httpCode for $url");

  return $body;
}

function httpPostJson($url, $jsonBody, $headers = []) {
  $defaultHeaders = [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
  ];
  $allHeaders = array_merge($defaultHeaders, $headers);

  // Guard: without ext-curl curl_init() and the CURLOPT_* constants are
  // undefined and referencing them is a fatal Error.
  if (!function_exists('curl_init') || !function_exists('curl_setopt_array')) {
    $payload = is_string($jsonBody) ? $jsonBody : json_encode($jsonBody);
    $result = httpViaStream($url, 'POST', $allHeaders, $payload);
    if ($result[2] !== '') throw new Exception("HTTP request failed: {$result[2]}");

    $decoded = json_decode($result[0], true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;
    return $result[0];
  }

  $ch = curl_init();

  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => TIMEOUT,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => is_string($jsonBody) ? $jsonBody : json_encode($jsonBody),
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // prefer IPv4: dual-stack DNS stalls
    CURLOPT_HTTPHEADER => $allHeaders,
  ]);

  $body = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) throw new Exception("HTTP request failed: $error");

  $decoded = json_decode($body, true);
  if (json_last_error() === JSON_ERROR_NONE) return $decoded;
  return $body;
}

/**
 * Fallback transport when ext-curl is missing: PHP's http stream wrapper.
 *
 * @param string|string[]|null $payload  null = GET, otherwise POST body
 * @return array{0: string|false, 1: int, 2: string} [body, status, error]
 */
function httpViaStream($url, $method, array $headerLines, $payload) {
  $httpOptions = [
    'method' => strtoupper((string) $method),
    'timeout' => TIMEOUT,
    'follow_location' => 1,
    'max_redirects' => 5,
    'ignore_errors' => true, // expose the body; status parsed below
    'header' => $headerLines === [] ? '' : implode("\r\n", $headerLines) . "\r\n",
  ];
  if ($payload !== null) {
    $httpOptions['content'] = $payload;
  }

  $context = stream_context_create([
    'http' => $httpOptions,
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false], // CURLOPT_SSL_VERIFYPEER => false
  ]);

  $body = @file_get_contents($url, false, $context);

  // $http_response_header may contain the blocks of every followed
  // redirect; the final status is the last HTTP/ line.
  $status = 0;
  foreach ($http_response_header ?? [] as $line) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
      $status = (int) $m[1];
    }
  }

  $error = '';
  if ($body === false) {
    $error = error_get_last()['message'] ?? 'stream request failed';
  }

  return [$body, $status, $error];
}
