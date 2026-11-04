<?php
/**
 * End-to-end stream checker for sktv-forwarders.
 *
 * Usage:
 *   php .github/scripts/check_streams.php [base-url] [channel-id ...]
 *
 * Examples:
 *   php .github/scripts/check_streams.php http://127.0.0.1:8123
 *   php .github/scripts/check_streams.php https://example.com/sktv-forwarders STV1 CT1 Prima
 *
 * The base URL must point at the directory containing get.php. The script only
 * uses the public get.php endpoint, so it does not need the proxy secrets.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$base = $argv[1] ?? 'http://127.0.0.1:8123';
$only = array_slice($argv, 2);
$repo = dirname(__DIR__, 2);
require $repo . '/channels.inc.php';

function http_get($url, $referer = null, $timeout = 25) {
    $ch = curl_init($url);
    $headers = [
        'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36',
        'Accept: */*',
    ];
    if ($referer) {
        $headers[] = 'Referer: ' . $referer;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    if ($response === false) {
        return ['code' => 0, 'error' => $error, 'headers' => '', 'body' => '', 'contentType' => ''];
    }
    return [
        'code' => $code,
        'error' => '',
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
        'contentType' => $contentType,
    ];
}

function header_value($headers, $name) {
    foreach (preg_split('/\r?\n/', $headers) as $line) {
        if (stripos($line, $name . ':') === 0) {
            return trim(substr($line, strlen($name) + 1));
        }
    }
    return null;
}

function absolute_url($url, $base) {
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    $parts = parse_url($base);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (strpos($url, '//') === 0) {
        return $parts['scheme'] . ':' . $url;
    }
    if ($url[0] === '/') {
        return $origin . $url;
    }
    $path = $parts['path'] ?? '/';
    return $origin . substr($path, 0, strrpos($path, '/') + 1) . $url;
}

function check_playlist($url, $referer, $depth = 0) {
    $response = http_get($url, $referer);
    if ($response['code'] === 0) {
        return ['ok' => false, 'msg' => 'connection error: ' . $response['error']];
    }
    if ($response['code'] >= 400) {
        return ['ok' => false, 'msg' => 'HTTP ' . $response['code']];
    }
    $body = ltrim($response['body']);
    if (strpos($body, '#EXTM3U') !== 0) {
        $snippet = substr(preg_replace('/\s+/', ' ', $body), 0, 100);
        return ['ok' => false, 'msg' => 'not an m3u8 (HTTP ' . $response['code'] . ', ' . $response['contentType'] . '): ' . $snippet];
    }
    if (preg_match('/#EXT-X-STREAM-INF/', $body)) {
        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if ($depth < 2) {
                return check_playlist(absolute_url($line, $url), $referer, $depth + 1);
            }
            return ['ok' => true, 'msg' => 'master playlist OK'];
        }
    }
    if (strpos($body, '#EXTINF') === false) {
        return ['ok' => false, 'msg' => 'playlist has no segments'];
    }
    return ['ok' => true, 'msg' => 'media playlist OK (' . substr_count($body, '#EXTINF') . ' segments)'];
}

$pass = 0;
$unavailable = 0;
$fail = 0;

foreach ($channels as $country) {
    foreach ($country['channels'] as $channel) {
        $id = $channel['id'];
        if ($only && !in_array($id, $only, true)) {
            continue;
        }

        $response = http_get(rtrim($base, '/') . '/get.php?x=' . urlencode($id));
        if ($response['code'] === 0) {
            printf("%-14s %-16s FAIL connection error: %s\n", $country['name'], $id, $response['error']);
            $fail++;
            continue;
        }

        $location = header_value($response['headers'], 'Location');
        $referer = null;
        $streamUrl = null;

        if ($location) {
            if (strpos($location, 'video_unavailable') !== false) {
                printf("%-14s %-16s N/A  upstream unavailable (placeholder)\n", $country['name'], $id);
                $unavailable++;
                continue;
            }
            $streamUrl = $location;
        } elseif (preg_match('/#EXTVLCOPT:http-referrer=([^\r\n]+)/', $response['body'], $m)) {
            $referer = trim($m[1]);
            $lines = preg_split('/\r?\n/', trim($response['body']));
            $streamUrl = trim(end($lines));
        } elseif (stripos($response['body'], '#EXTM3U') === 0) {
            printf("%-14s %-16s OK   inline playlist\n", $country['name'], $id);
            $pass++;
            continue;
        } else {
            printf("%-14s %-16s FAIL unexpected response (HTTP %d)\n", $country['name'], $id, $response['code']);
            $fail++;
            continue;
        }

        $check = check_playlist($streamUrl, $referer);
        if ($check['ok']) {
            printf("%-14s %-16s OK   %s\n", $country['name'], $id, $check['msg']);
            $pass++;
        } else {
            printf("%-14s %-16s FAIL %s\n", $country['name'], $id, $check['msg']);
            $fail++;
        }
    }
}

printf("\nPASS: %d  UNAVAILABLE: %d  FAIL: %d\n", $pass, $unavailable, $fail);
exit($fail > 0 ? 1 : 0);
