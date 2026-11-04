<?php

// Proxy paths (filled in at deploy time by the GitHub Actions workflow).
$SKTV_PROXY_SK="${SECRET_SKTV_PROXY_SK}";
$SKTV_PROXY_CZ="${SECRET_SKTV_PROXY_CZ}";

function loc($x) {
    if (!$x) {
        notfound("video_unavailable/unavailable.m3u8");
    }
    header("Location: " . $x);
    die();
}

function notfound($x) {
    header($_SERVER['SERVER_PROTOCOL']." 302 Found", true, 302);
    header("Location: " . $x);
    die();
}

function m3u8_refer($url, $referer) {
    if (!$url) {
        notfound("video_unavailable/unavailable.m3u8");
    }
    header('Content-type: application/x-mpegURL');
    echo "#EXTVLCOPT:http-referrer=" . $referer . "\n" . "#EXTVLCOPT:adaptive-use-access" . "\n" . $url;
    die();
}

// Fetch a URL with curl. Returns null on failure instead of emitting PHP
// warnings (which used to corrupt playlists when the proxy was unhappy).
function http_get($url, $headers = [], $timeout = 25) {
    if (!$url) {
        return null;
    }
    $ch = curl_init($url);
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36',
        'Accept: */*',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    // curl_close() is deprecated in PHP 8.5 and a no-op since PHP 8.0.
    if ($body === false || $code >= 400) {
        error_log("sktv: upstream GET failed (HTTP $code, $error)");
        return null;
    }
    return $body;
}

function http_get_proxy($proxyBase, $url, $timeout = 30) {
    if (!$proxyBase || !$url) {
        return null;
    }
    return http_get($proxyBase . urlencode($url), [], $timeout);
}

// Extract the first embedded stream URL from a CME (Markiza/Nova) embed page.
function extract_embed_src($html) {
    if (!$html) {
        return null;
    }
    if (preg_match('/\[\{"src"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $html, $m)) {
        $decoded = json_decode('"' . $m[1] . '"');
        return is_string($decoded) ? $decoded : stripcslashes($m[1]);
    }
    if (preg_match('/"src"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $html, $m)) {
        $decoded = json_decode('"' . $m[1] . '"');
        return is_string($decoded) ? $decoded : stripcslashes($m[1]);
    }
    return null;
}

// Decode a JSON API response even if a broken proxy prefixed it with PHP
// warnings or HTML. The old proxy did exactly that on PHP 8.5.
function json_decode_body($body) {
    if (!$body) {
        return null;
    }
    $objectStart = strpos($body, '{');
    $arrayStart = strpos($body, '[');
    if ($objectStart === false && $arrayStart === false) {
        return null;
    }
    if ($objectStart === false) {
        $start = $arrayStart;
    } elseif ($arrayStart === false) {
        $start = $objectStart;
    } else {
        $start = min($objectStart, $arrayStart);
    }
    $decoded = json_decode(substr($body, $start), true);
    return is_array($decoded) ? $decoded : null;
}

// Resolve a (possibly relative) playlist URI against its playlist URL.
function absolute_url($url, $base) {
    if ($url === '' || $url === null) {
        return null;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
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
    $basePath = $parts['path'] ?? '/';
    $dir = substr($basePath, 0, strrpos($basePath, '/') + 1);
    return $origin . $dir . $url;
}

// Pick the highest-bandwidth variant from a master playlist. This replaces
// the old hardcoded "$lines[5]" which broke as soon as the proxy emitted a
// single warning before the playlist.
function best_variant($playlist, $baseUrl) {
    if (!$playlist) {
        return null;
    }
    $lines = preg_split('/\r?\n/', $playlist);
    $best = null;
    $bestBandwidth = -1;
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
        if (!preg_match('/#EXT-X-STREAM-INF:.*?BANDWIDTH=(\d+)/i', $lines[$i], $m)) {
            continue;
        }
        $bandwidth = (int) $m[1];
        for ($j = $i + 1; $j < $count; $j++) {
            $candidate = trim($lines[$j]);
            if ($candidate === '') {
                continue;
            }
            if ($candidate[0] === '<' || strpos($candidate, ' ') !== false) {
                continue;
            }
            if ($candidate[0] !== '#') {
                if ($bandwidth > $bestBandwidth) {
                    $bestBandwidth = $bandwidth;
                    $best = $candidate;
                }
            }
            break;
        }
    }
    return absolute_url($best, $baseUrl);
}

// RTVS ---------------

function stv_url_proxy($x) {
    global $SKTV_PROXY_SK;
    $jsonUrl = "https://www.rtvs.sk/json/live5f.json?c=" . $x . "&b=msie&p=win&v=11&f=0&d=1";
    $json = http_get($jsonUrl);
    if (!$json && $SKTV_PROXY_SK) {
        $json = http_get_proxy($SKTV_PROXY_SK, $jsonUrl);
    }
    $data = json_decode_body($json);
    $playlistUrl = $data["clip"]["sources"][0]["src"] ?? null;
    if (!$playlistUrl) {
        return null;
    }

    $playlist = $SKTV_PROXY_SK ? http_get_proxy($SKTV_PROXY_SK, $playlistUrl) : null;
    if (!$playlist) {
        $playlist = http_get($playlistUrl);
    }
    if (!$playlist) {
        return null;
    }

    // Some sources are already media playlists.
    if (strpos($playlist, '#EXT-X-STREAM-INF') === false) {
        return strpos($playlist, '#EXTINF') !== false ? $playlistUrl : null;
    }
    return best_variant($playlist, $playlistUrl);
}

// Markiza ---------------

function markiza_url_proxy($x, $tn = false) {
    global $SKTV_PROXY_SK;
    $siteurl = "https://media.cms.markiza.sk/embed/" . $x . ($tn ? "" : "-live") . "?autoplay=any";
    $sitecontent = $SKTV_PROXY_SK ? http_get_proxy($SKTV_PROXY_SK, $siteurl) : null;
    if (!$sitecontent) {
        $sitecontent = http_get($siteurl);
    }
    return extract_embed_src($sitecontent);
}

// Nova ---------------

function nova_url($x, $tn = false) {
    global $SKTV_PROXY_CZ;
    $siteurl = "https://media" . ($tn ? "tn" : "") . ".cms.nova.cz/embed/" . $x . ($tn ? "" : "live") . "?autoplay=1";
    $sitecontent = http_get($siteurl);
    // Outside the Czech Republic Nova serves a licensing error page. Retry
    // through the CZ proxy (which must be hosted in CZ to be useful).
    if (!$sitecontent || strpos($sitecontent, 'not available in your country') !== false) {
        $sitecontent = $SKTV_PROXY_CZ ? http_get_proxy($SKTV_PROXY_CZ, $siteurl) : null;
    }
    if (!$sitecontent || strpos($sitecontent, 'not available in your country') !== false) {
        return null;
    }
    return extract_embed_src($sitecontent);
}

function ta3_url() {
    $js = http_get("https://embed.livebox.cz/ta3_v2/live-source.js");
    if (!$js) {
        return null;
    }
    if (preg_match('/\[\{"src"\s*:\s*"([^"]+)"/', $js, $m)) {
        return strpos($m[1], '//') === 0 ? 'https:' . $m[1] : $m[1];
    }
    return null;
}

function cnn_portugal() {
    $response = http_get("https://front-api.iol.pt/api/v1/live/broadcast?canal=CNN");
    $endpoint = json_decode_body($response);
    if (empty($endpoint["videoUrl"])) {
        return null;
    }
    $token = http_get("https://services.iol.pt/matrix?userId=", [
        "User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; SM-A260F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.61 Mobile Safari/537.36",
    ]);
    if ($token === null) {
        return null;
    }
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    $base = $endpoint["videoUrl"];
    $separator = (strpos($base, '?') === false) ? '?' : '';
    return $base . $separator . "wmsAuthSign=" . $token;
}

// Czech Television ----------------

function ceskatelevize($code) {
    global $SKTV_PROXY_CZ;
    $url = "https://api.ceskatelevize.cz/video/v1/playlist-live/v1/stream-data/channel/" . $code . "?canPlayDrm=false&streamType=hls&quality=1080p";
    $response = $SKTV_PROXY_CZ ? http_get_proxy($SKTV_PROXY_CZ, $url) : null;
    if (!$response) {
        $response = http_get($url);
    }
    $manifest = json_decode_body($response);
    if (empty($manifest["streamUrls"]["main"]) || !empty($manifest["isBlocked"])) {
        return null;
    }
    return $manifest["streamUrls"]["main"];
}

function prima($id) {
    global $SKTV_PROXY_CZ;
    $apiUrl = "https://api.play-backend.iprima.cz/api/v1/products/" . $id . "/play";
    $response = $SKTV_PROXY_CZ ? http_get_proxy($SKTV_PROXY_CZ, $apiUrl) : null;
    if (!$response) {
        $response = http_get($apiUrl);
    }
    $primajson = json_decode_body($response);
    if (empty($primajson["streamInfos"][0]["url"])) {
        return null;
    }
    $primaurl = $primajson["streamInfos"][0]["url"];
    // Prefer the HD variant when the API hands us the LQ one.
    $primaurl = str_replace("live_lq.m3u8", "live_hd.m3u8", $primaurl);
    $primaurl = str_replace("lq.m3u8", "hd.m3u8", $primaurl);
    return $primaurl;
}

// Wrap a playlist in the CZ proxy and ask it to rewrite all segment URIs too.
// This is what makes the geo-locked channels playable outside CZ, provided the
// CZ proxy really is hosted in the Czech Republic.
function czech_forge($url, $referer = "") {
    global $SKTV_PROXY_CZ;
    if (!$url || !$SKTV_PROXY_CZ) {
        return $url;
    }
    $proxied = $SKTV_PROXY_CZ . urlencode($url) . "&m3u8-forge=true&proxy-segments=true";
    if ($referer) {
        $proxied .= "&referer=" . urlencode($referer);
    }
    return $proxied;
}

header("Content-Type: text/plain");

if (!isset($_GET["x"])) die("Channel not set! (?x=)");
$channel = $_GET["x"];
$forge = isset($_GET['forge']) && $_GET['forge'] === 'true';

if ($channel == "TA3") {
    loc(ta3_url());
}
else if ($channel == "STV1") {
    loc(stv_url_proxy("1"));
}
else if ($channel == "STV2") {
    loc(stv_url_proxy("2"));
}
else if ($channel == "STV24") {
    loc(stv_url_proxy("3"));
}
else if ($channel == "STV-O") {
    loc(stv_url_proxy("4"));
}
else if ($channel == "RTVS") {
    loc(stv_url_proxy("6"));
}
else if ($channel == "NR_SR") {
    loc(stv_url_proxy("5"));
}
else if ($channel == "SPORT") {
    loc(stv_url_proxy("15"));
}
else if ($channel == "Markiza") {
    m3u8_refer(markiza_url_proxy("markiza"), "https://media.cms.markiza.sk/");
}
else if ($channel == "Doma") {
    m3u8_refer(markiza_url_proxy("doma"), "https://media.cms.markiza.sk/");
}
else if ($channel == "Dajto") {
    m3u8_refer(markiza_url_proxy("dajto"), "https://media.cms.markiza.sk/");
}
else if ($channel == "Krimi") {
    m3u8_refer(markiza_url_proxy("krimi"), "https://media.cms.markiza.sk/");
}
else if ($channel == "Klasik") {
    m3u8_refer(markiza_url_proxy("klasik"), "https://media.cms.markiza.sk/");
}
else if ($channel == "MarkizaTNLive") {
    m3u8_refer(markiza_url_proxy("BQeGg0uPHJP", true), "https://media.cms.markiza.sk/");
}
else if ($channel == "JOJ") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/joj-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "JOJP") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/plus-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "Wau") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/wau-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "JOJ24") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/joj_news-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "JOJFamily") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/family-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "JOJCinema") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/cinema-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "JOJSport" || $channel == "JOJSport2") {
    // The free JOJ Sport feed was removed; JOJ Sport 2 still has a public feed.
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/joj_sport2-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "Jojko") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/jojko-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "CSFilm") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/cs_film-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "CSHistory") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/cs_history-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "CSMystery") {
    m3u8_refer("https://live.cdn.joj.sk/live/andromeda/cs_mystery-1080.m3u8", "https://media.joj.sk/");
}
else if ($channel == "Nova") {
    $url = nova_url("nova-");
    m3u8_refer($forge ? czech_forge($url, "https://media.cms.nova.cz/") : $url, "https://media.cms.nova.cz/");
}
else if ($channel == "NovaFun") {
    $url = nova_url("nova-2-");
    m3u8_refer($forge ? czech_forge($url, "https://media.cms.nova.cz/") : $url, "https://media.cms.nova.cz/");
}
else if ($channel == "NovaLady") {
    $url = nova_url("nova-lady-");
    m3u8_refer($forge ? czech_forge($url, "https://media.cms.nova.cz/") : $url, "https://media.cms.nova.cz/");
}
else if ($channel == "NovaGold") {
    $url = nova_url("nova-gold-");
    m3u8_refer($forge ? czech_forge($url, "https://media.cms.nova.cz/") : $url, "https://media.cms.nova.cz/");
}
else if ($channel == "NovaCinema") {
    $url = nova_url("nova-cinema-");
    m3u8_refer($forge ? czech_forge($url, "https://media.cms.nova.cz/") : $url, "https://media.cms.nova.cz/");
}
else if ($channel == "NovaAction") {
    $url = nova_url("nova-action-");
    m3u8_refer($forge ? czech_forge($url, "https://media.cms.nova.cz/") : $url, "https://media.cms.nova.cz/");
}
else if ($channel == "NovaTNLive") {
    $url = nova_url("ETpdC5paJa8", true);
    loc($forge ? czech_forge($url) : $url);
}
else if ($channel == "CNN_Portugal") {
    loc(cnn_portugal());
}
else if ($channel == "CT1") {
    loc(ceskatelevize("CH_1"));
}
else if ($channel == "CT2") {
    loc(ceskatelevize("CH_2"));
}
else if ($channel == "CT24") {
    loc(ceskatelevize("CH_24"));
}
else if ($channel == "CTsport") {
    loc(ceskatelevize("CH_4"));
}
else if ($channel == "CT_D") {
    loc(ceskatelevize("CH_5"));
}
else if ($channel == "CTart") {
    loc(ceskatelevize("CH_6"));
}
else if ($channel == "CTsportPlus") {
    loc(ceskatelevize("CH_25"));
}
else if ($channel == "Prima") {
    $url = prima("id-p111013");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaCool") {
    $url = prima("id-p111014");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaZoom") {
    $url = prima("id-p111015");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaLove") {
    $url = prima("id-p111016");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaMax") {
    $url = prima("id-p111017");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaKrimi") {
    $url = prima("id-p432829");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaNews") {
    $url = prima("id-p650443");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaStar") {
    $url = prima("id-p846043");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaShow") {
    $url = prima("id-p899572");
    loc($forge ? czech_forge($url, "https://www.iprima.cz/") : $url);
}
else if ($channel == "PrimaCase") {
    //Channel announced launch on 2025
    notfound("video_unavailable/unavailable.m3u8");
}
else if ($channel == "PrimaPort") {
    //Channel announced launch on 2025/2026
    notfound("video_unavailable/unavailable.m3u8");
}
else {
    notfound("video_unavailable/unavailable.m3u8");
}
?>