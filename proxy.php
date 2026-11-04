<?php

//This script is being used as a proxy script for sktv-forwarders project. You should host it yourself within your infrastructure if you want to use it.
//In get.php file you just replace SKTV_PROXY_SK or SKTV_PROXY_CZ with the url like this: "https://example.com/proxy.php?q="

// PHP warnings/deprecations must never end up inside a proxied playlist,
// otherwise every client sees a broken #EXTM3U. The old code did exactly that
// on PHP 8.5 (curl_close() deprecation).
ini_set('display_errors', '0');
error_reporting(E_ALL);

function isUrlAllowed($url, $allowedUrlPrefixes) {
    foreach ($allowedUrlPrefixes as $pattern) {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
        if (preg_match($regex, $url)) {
            return true;
        }
    }
    return false;
}

function makeAbsolute($url, $base) {
    if ($url === '' || $url === null) {
        return $url;
    }

    // Already absolute (including non-HTTP schemes).
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        return $url;
    }

    $base_parts = parse_url($base);
    if (!$base_parts || empty($base_parts['scheme']) || empty($base_parts['host'])) {
        return $url;
    }

    $origin = $base_parts['scheme'] . '://' . $base_parts['host']
        . (isset($base_parts['port']) ? ':' . $base_parts['port'] : '');

    // Protocol-relative URL.
    if (strpos($url, '//') === 0) {
        return $base_parts['scheme'] . ':' . $url;
    }

    if ($url[0] === '/') {
        $path = $url;
    } elseif ($url[0] === '?') {
        $path = ($base_parts['path'] ?? '/') . $url;
    } elseif ($url[0] === '#') {
        return $base . $url;
    } else {
        $base_path = $base_parts['path'] ?? '/';
        $dir = substr($base_path, 0, strrpos($base_path, '/') + 1);
        $path = $dir . $url;
    }

    // Normalise "/a/../b" and "/a/./b".
    $suffix = (substr($path, -1) === '/') ? '/' : '';
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '..') {
            array_pop($parts);
        } elseif ($part !== '.' && $part !== '') {
            $parts[] = $part;
        }
    }
    return $origin . '/' . implode('/', $parts) . $suffix;
}

function shouldProxyUrl($url, $alwaysProxiedUrls) {
    if (isUrlAllowed($url, $alwaysProxiedUrls)) {
        return true;
    }
    $parsedUrl = parse_url($url);
    $path = $parsedUrl['path'] ?? '';
    $query = $parsedUrl['query'] ?? '';

    return (strtolower(pathinfo($path, PATHINFO_EXTENSION)) == 'm3u8') ||
           (stripos($query, 'playlist') !== false) ||
           (stripos($query, 'm3u8') !== false);
}

function proxyUrl($url, $referer = '') {
    global $scriptUrl, $proxySegments;
    $out = $scriptUrl . '?q=' . urlencode($url) . '&m3u8-forge=true';
    if ($proxySegments) {
        $out .= '&proxy-segments=true';
    }
    if ($referer) {
        $out .= '&referer=' . urlencode($referer);
    }
    return $out;
}

$allowedUrlPrefixes = [
    'https://*.stv.livebox.sk/*',
    'https://www.rtvs.sk/json/*',
    'https://media.cms.markiza.sk/embed/*',
    'https://media.cms.nova.cz/embed/*',
    'https://mediatn.cms.nova.cz/embed/*',
    'https://*.cms.markiza.sk/*',
    'https://*.cms.nova.cz/*',
    'https://www.ceskatelevize.cz/services/ivysilani/*',
    'https://www.ceskatelevize.cz/ivysilani/*',
    'https://api.ceskatelevize.cz/*',
    'https://api.play-backend.iprima.cz/*',
    'http://prima-ott-live-sec.ssl.cdn.cra.cz/*',
    'https://prima-ott-live-sec.ssl.cdn.cra.cz/*',
    'https://cmesk-ott-live-avod-sec.ssl.cdn.cra.cz/*',
    'https://nova-ott-live-avod-sec.ssl.cdn.cra.cz/*',
    // The CME/CDN hosts above are all covered by this family wildcard. Keep
    // the specific entries for readability and add new CME channels here.
    'http://*.ssl.cdn.cra.cz/*',
    'https://*.ssl.cdn.cra.cz/*',
    'https://live.cdn.joj.sk/*',
    'https://front-api.iol.pt/*',
    'https://services.iol.pt/*',
    'https://video-auth4.iol.pt/*',
    'https://*.iol.pt/*',
    'https://ivys-nw-cdn.o2tv.cz/*',
    'https://ivys-st08-1.o2-tv.cz/*',
    'https://*.o2tv.cz/*',
    'https://*.o2-tv.cz/*',
];

$alwaysProxiedUrls = [
    'https://api.play-backend.iprima.cz/*',
];

$scheme = 'http';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    $scheme = 'https';
}
$scriptUrl = $scheme . "://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";

$encodedUrl = $_GET['q'] ?? '';
$m3u8Forge = isset($_GET['m3u8-forge']) && $_GET['m3u8-forge'] === 'true';
// Rewrite media segments too, not just nested playlists. Needed when even the
// segments are geo-locked (Prima, Nova), because then the client cannot fetch
// them directly.
$proxySegments = isset($_GET['proxy-segments']) && $_GET['proxy-segments'] === 'true';
$referer = str_replace(["\r", "\n"], '', $_GET['referer'] ?? '');
if ($referer && !preg_match('#^https?://#i', $referer)) {
    $referer = '';
}

$url = urldecode($encodedUrl);

$isAllowed = isUrlAllowed($url, $allowedUrlPrefixes);

if ($isAllowed) {
    $ch = curl_init($url);

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $curlHeaders = [];
    $skipHeaders = ['host', 'connection', 'keep-alive', 'transfer-encoding', 'upgrade', 'content-length'];

    foreach ($headers as $name => $value) {
        if (!in_array(strtolower($name), $skipHeaders, true)) {
            $curlHeaders[] = "$name: $value";
        }
    }
    if ($referer) {
        // Replace any client-provided Referer with the one the provider expects.
        $curlHeaders = array_values(array_filter($curlHeaders, function ($header) {
            return stripos($header, 'Referer:') !== 0;
        }));
        $curlHeaders[] = 'Referer: ' . $referer;
    }

    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_ENCODING => '',
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    }

    $response = curl_exec($ch);
    $upstreamCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($response === false) {
        http_response_code(502);
        echo "Error: " . curl_error($ch);
        exit;
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    // Redirects are followed, so rewrite relative URIs against the final URL.
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    // curl_close() is deprecated in PHP 8.5 (and a no-op since PHP 8.0).

    // Process m3u8 content if necessary
    $looksLikePlaylist = strpos($body, '#EXTM3U') !== false || strpos($body, '#EXTINF') !== false;
    if ($m3u8Forge && $looksLikePlaylist) {
        $lines = preg_split('/\r?\n/', $body);
        $processedLines = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (strpos($line, '#') === 0) {
                // Rewrite relative URI="..." attributes (EXT-X-MEDIA, EXT-X-MAP, EXT-X-KEY, ...).
                $line = preg_replace_callback('/URI="([^"]+)"/i', function ($matches) use ($effectiveUrl, $alwaysProxiedUrls, $referer, $scriptUrl, $proxySegments) {
                    $uri = $matches[1];
                    if (preg_match('#^https?://#i', $uri) || strpos($uri, $scriptUrl) === 0) {
                        return $matches[0];
                    }
                    $absoluteUrl = makeAbsolute($uri, $effectiveUrl);
                    if (!$absoluteUrl) {
                        return $matches[0];
                    }
                    if ($proxySegments || shouldProxyUrl($absoluteUrl, $alwaysProxiedUrls)) {
                        return 'URI="' . proxyUrl($absoluteUrl, $referer) . '"';
                    }
                    return 'URI="' . $absoluteUrl . '"';
                }, $line);

                // Rewrite quoted absolute URLs.
                $line = preg_replace_callback('/"(https?:\/\/[^"]+)"/', function ($matches) use ($alwaysProxiedUrls, $referer, $scriptUrl, $proxySegments) {
                    $absoluteUrl = $matches[1];
                    if (strpos($absoluteUrl, $scriptUrl) === 0) {
                        return $matches[0];
                    }
                    if ($proxySegments || shouldProxyUrl($absoluteUrl, $alwaysProxiedUrls)) {
                        return '"' . proxyUrl($absoluteUrl, $referer) . '"';
                    }
                    return $matches[0];
                }, $line);
            } else {
                $absoluteUrl = makeAbsolute(trim($line), $effectiveUrl);
                if (!$absoluteUrl) {
                    continue;
                }
                if ($proxySegments || shouldProxyUrl($absoluteUrl, $alwaysProxiedUrls)) {
                    $line = proxyUrl($absoluteUrl, $referer);
                } else {
                    $line = $absoluteUrl;
                }
            }
            $processedLines[] = $line;
        }
        $body = implode("\n", $processedLines);
    }

    if ($upstreamCode >= 400) {
        http_response_code($upstreamCode);
    }

    $filename = null;
    foreach (preg_split('/\r?\n/', trim($responseHeaders)) as $header) {
        if (stripos($header, 'Content-Disposition:') === 0) {
            // Extract filename from Content-Disposition header
            if (preg_match('/filename[^;=\n]*=([\'\"]*)([^\"\';]*)/i', $header, $matches)) {
                $filename = $matches[2];
            }
        } elseif ($header && stripos($header, 'Transfer-Encoding:') === false && stripos($header, 'Content-Encoding:') === false && stripos($header, 'Connection:') === false && stripos($header, 'Keep-Alive:') === false && stripos($header, 'Location:') === false && stripos($header, 'HTTP/') === false && stripos($header, 'Content-Length:') === false) {
            header($header);
        }
    }

    // If no filename was found in Content-Disposition, try to derive it from the URL
    if (!$filename) {
        $urlPath = parse_url($url, PHP_URL_PATH);
        $filename = basename($urlPath);
    }

    // Set Content-Disposition header with the original or derived filename
    if ($filename) {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }

    echo $body;
} else {
    header("HTTP/1.1 403 Forbidden");
    echo "It seems that this URL is not allowed for proxying. This is purely a temporary solution for sktv-forwarders project. Well there... are you being naughty? ;)";
}