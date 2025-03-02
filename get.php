<?php

// Proxy functions
$SKTV_PROXY_SK="https://dopi.ci/scripts/proxy.php?q=";
$SKTV_PROXY_CZ="https://santovic-test.6f.sk/sktv-proxy.php?q=";
$ZELVAR_CZ_LEGACY="https://proxy.zelvar.cz/subdom/proxy/index.php?hl=200&q=";

function loc($x) {
    header("Location: " . $x);
    die();
}

function notfound($x) {
    header($_SERVER['SERVER_PROTOCOL']." 301 Moved Permanently", true );
    header("Location: " . $x);
    die();
}

function m3u8_refer($url, $referer) {
    header('Content-type: application/x-mpegURL');
    echo "#EXTVLCOPT:http-referrer=" . $referer . "\n" . "#EXTVLCOPT:adaptive-use-access" . "\n" . $url;
    die();
}

function proxyserversk_legacy($url) {
    $postdata = http_build_query(
        array(
                'url' => $url
        )
        );
    $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            ));

    $context = stream_context_create($opts);
    return file_get_contents("https://www.proxyserver.sk/index.php", false, $context);
}

function proxysktv_sk_simple($url) {
    global $SKTV_PROXY_SK;
    return file_get_contents($SKTV_PROXY_SK . urlencode($url), false);
}

function proxysktv_cz_simple($url) {
    global $SKTV_PROXY_CZ;
    return file_get_contents($SKTV_PROXY_CZ . urlencode($url), false);
}

function proxy_zelvar_simple($url) {
    global $ZELVAR_CZ_LEGACY;
    return file_get_contents($ZELVAR_CZ_LEGACY . urlencode($url), false, stream_context_create(array("ssl"=>array("verify_peer_name"=>false))));
}

// RTVS ---------------

//without proxy
// function stv_url($x) {
//     return json_decode(file_get_contents("https://www.rtvs.sk/json/live5f.json?c=" . $x . "&b=msie&p=win&v=11&f=0&d=1"), true)["clip"]["sources"][0]["src"];
// }

//stv with proxy
function stv_url_proxy($x) {
    $playlisturl = json_decode(file_get_contents("https://www.rtvs.sk/json/live5f.json?c=" . $x . "&b=msie&p=win&v=11&f=0&d=1"), true)["clip"]["sources"][0]["src"];
    $playlist = proxysktv_sk_simple($playlisturl);
    $lines = explode("\n", $playlist);
    return $lines[5]; //1080p is on line 4
}
 
//Markiza with proxy
function markiza_url_proxy($x) {
    $siteurl = "https://media.cms.markiza.sk/embed/" . $x . "-live?autoplay=any";
    $sitecontent = proxysktv_sk_simple($siteurl);
    $streamurl = join("", explode("\\", explode("\"", explode("[{\"src\":\"", $sitecontent)[1])[0]));
    return $streamurl;
}

/*
// NOVA without proxy, only works if server is in Czech
function nova_url($x) {
    return join("", explode("\\", explode("\"", explode("[{\"src\":\"", file_get_contents("https://media.cms.nova.cz/embed/nova" . $x . "live?autoplay=1"))[1])[0]));
}
*/

function nova_url($x, $tn = false) {
    $content = proxy_zelvar_simple("https://media" . ($tn ? "tn" : "") . ".cms.nova.cz/embed/" . $x . ($tn ? "" : "live") . "?autoplay=1");
    return join("", explode("\\", explode("\"", explode("[{\"src\":\"", $content)[1])[0]));
}

function ta3_url() {
    return "https:" . explode("\"", explode("\" : \"", file_get_contents("https://embed.livebox.cz/ta3_v2/live-source.js"))[1])[0];
}


function cnn_portugal() {
    $endpoint = json_decode(file_get_contents("https://front-api.iol.pt/api/v1/live/broadcast?canal=CNN"), true);
    return $endpoint["videoUrl"] . "wmsAuthSign=" . file_get_contents("https://services.iol.pt/matrix?userId=", false, stream_context_create(array("http"=>array("header"=>"User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; SM-A260F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.61 Mobile Safari/537.36\r\n"))));
}

function ceskatelevize($code) {
    $url = "https://api.ceskatelevize.cz/video/v1/playlist-live/v1/stream-data/channel/" . $code . "?canPlayDrm=false&streamType=dash&quality=1080p";
    $response = proxysktv_cz_simple($url);
    $manifest = json_decode($response, true);
    $finalurl = $manifest["streamUrls"]["main"];
    return $finalurl;
}

function prima($id) {
    global $SKTV_PROXY_CZ;
    $primajson = json_decode(proxysktv_cz_simple("https://api.play-backend.iprima.cz/api/v1/products/" . $id . "/play"));
    $primaurl = $primajson->streamInfos[0]->url;
    $primaurlhq = str_replace("lq.m3u8", "hd.m3u8", $primaurl);
    return $primaurlhq;
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
else if ($channel == "Nova") {
    m3u8_refer(nova_url("nova-"), "https://media.cms.nova.cz/");
}
else if ($channel == "NovaFun") {
    m3u8_refer(nova_url("nova-2-"), "https://media.cms.nova.cz/");
}
else if ($channel == "NovaLady") {
    m3u8_refer(nova_url("nova-lady-"), "https://media.cms.nova.cz/");
}
else if ($channel == "NovaGold") {
    m3u8_refer(nova_url("nova-gold-"), "https://media.cms.nova.cz/");
}
else if ($channel == "NovaCinema") {
    m3u8_refer(nova_url("nova-cinema-"), "https://media.cms.nova.cz/");
}
else if ($channel == "NovaAction") {
    m3u8_refer(nova_url("nova-action-"), "https://media.cms.nova.cz/");
}
else if ($channel == "NovaTNLive") {
    loc(nova_url("ETpdC5paJa8", true));
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
    if($forge) loc($SKTV_PROXY_CZ . urlencode(prima("id-p111013")) . "&m3u8-forge=true");
    else loc(prima("id-p111013"));
}
else if ($channel == "PrimaCool") {
    if($forge) loc($SKTV_PROXY_CZ . urlencode(prima("id-p111014")) . "&m3u8-forge=true");
    else loc(prima("id-p111014"));
}
else if ($channel == "PrimaZoom") {
    if($forge) loc($SKTV_PROXY_CZ . urlencode(prima("id-p111015")) . "&m3u8-forge=true");
    else loc(prima("id-p111015"));
}
else if ($channel == "PrimaLove") {
    if($forge) loc($SKTV_PROXY_CZ . urlencode(prima("id-p111016")) . "&m3u8-forge=true");
    else loc(prima("id-p111016"));
}
else if ($channel == "PrimaMax") {
    if($forge) loc($SKTV_PROXY_CZ . urlencode(prima("id-p111017")) . "&m3u8-forge=true");
    else loc(prima("id-p111017"));
}
else {
    notfound("video_unavailable/unavailable.m3u8");
}
?>
