<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

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
    if (parse_url($url, PHP_URL_SCHEME) != '') return $url;
    
    $base_parts = parse_url($base);
    if ($url[0] == '/') {
        $abs = $base_parts['scheme'] . '://' . $base_parts['host'] . $url;
    } else {
        $path = isset($base_parts['path']) ? dirname($base_parts['path']) : '/';
        $abs = $base_parts['scheme'] . '://' . $base_parts['host'] . $path . '/' . $url;
    }
    return preg_replace("/([^:]\/)\/+/", "$1", $abs);
}

function shouldProxyUrl($url, $alwaysProxiedUrls) {
    if (isUrlAllowed($url, $alwaysProxiedUrls)) {
        return true;
    }
    $parsedUrl = parse_url($url);
    $path = $parsedUrl['path'] ?? '';
    $query = $parsedUrl['query'] ?? '';

    return (pathinfo($path, PATHINFO_EXTENSION) == 'm3u8') || 
           (strpos($query, 'playlist') !== false) || 
           (strpos($query, 'm3u8') !== false);
}

function proxyUrl($url) {
    global $scriptUrl;
    return $scriptUrl . '?q=' . urlencode($url) . '&m3u8-forge=true';
}

$allowedUrlPrefixes = [
    'https://*.stv.livebox.sk/*',
    'https://media.cms.markiza.sk/embed/*',
    'https://www.ceskatelevize.cz/services/ivysilani/*',
    'https://www.ceskatelevize.cz/ivysilani/*',
    'https://media.cms.nova.cz/embed/*',
    'https://api.play-backend.iprima.cz/*',
    'http://prima-ott-live-sec.ssl.cdn.cra.cz/*',
    'https://prima-ott-live-sec.ssl.cdn.cra.cz/*'
];

$alwaysProxiedUrls = [
    'https://api.play-backend.iprima.cz/*'
];

$scriptUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";

$encodedUrl = $_GET['q'] ?? '';
$m3u8Forge = isset($_GET['m3u8-forge']) && $_GET['m3u8-forge'] === 'true';

$url = urldecode($encodedUrl);

$isAllowed = isUrlAllowed($url, $allowedUrlPrefixes);

if ($isAllowed) {
    $ch = curl_init($url);
    
    $headers = getallheaders();
    $curlHeaders = [];
    
    foreach ($headers as $name => $value) {
        if (strtolower($name) !== 'host') {
            $curlHeaders[] = "$name: $value";
        }
    }
    
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
        CURLOPT_ENCODING => '',
    ]);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    }
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error: " . curl_error($ch);
        curl_close($ch);
        exit;
    }
    
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);

    // Process m3u8 content if necessary
    if ($m3u8Forge && (pathinfo($url, PATHINFO_EXTENSION) == 'm3u8' || strpos($body, '#EXTM3U') !== false)) {
        $lines = explode("\n", $body);
        $processedLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '#') === 0) {
                $processedLines[] = preg_replace_callback('/(")(https?:\/\/[^"]+)(")/', function($matches) use ($alwaysProxiedUrls) {
                    $absoluteUrl = $matches[2];
                    if (shouldProxyUrl($absoluteUrl, $alwaysProxiedUrls)) {
                        return $matches[1] . proxyUrl($absoluteUrl) . $matches[3];
                    }
                    return $matches[0];
                }, $line);
            } else {
                $processedLines[] = preg_replace_callback('/^(https?:\/\/[^\s]+)|\s*((?!https?:\/\/)[^\s]+)/', function($matches) use ($url, $alwaysProxiedUrls) {
                    $matchedUrl = $matches[1] ?: $matches[2];
                    $absoluteUrl = makeAbsolute($matchedUrl, $url);
                    if (shouldProxyUrl($absoluteUrl, $alwaysProxiedUrls)) {
                        return proxyUrl($absoluteUrl);
                    }
                    return $absoluteUrl;
                }, $line);
            }
        }
        $body = implode("\n", $processedLines);
    }

$filename = null;
$headersSent = false;
foreach (explode("\r\n", $responseHeaders) as $header) {
    if (!$headersSent && stripos($header, 'HTTP/') === 0) {
        header($header);
        $headersSent = true;
    } elseif (stripos($header, 'Content-Disposition:') === 0) {
        // Extract filename from Content-Disposition header
        if (preg_match('/filename[^;=\n]*=([\'\"]*)([^\"\';]*)/i', $header, $matches)) {
            $filename = $matches[2];
        }
    } elseif ($header && stripos($header, 'Transfer-Encoding:') === false && stripos($header, 'Content-Encoding:') === false) {
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