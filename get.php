<?php
function loc($x) {
    header("Location: " . $x);
    die();
}

//without proxy
// function stv_url($x) {
//     return json_decode(file_get_contents("https://www.rtvs.sk/json/live5f.json?c=" . $x . "&b=msie&p=win&v=11&f=0&d=1"), true)["clip"]["sources"][0]["src"];
// }

//stv with proxy
function stv_url($x) {
    $playlisturl = json_decode(file_get_contents("https://www.rtvs.sk/json/live5f.json?c=" . $x . "&b=msie&p=win&v=11&f=0&d=1"), true)["clip"]["sources"][0]["src"];
    $postdata = http_build_query(
        array(
                'url' => $playlisturl
        )
        );
    $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            ));

    $context = stream_context_create($opts);
    $playlist = file_get_contents("https://www.proxyserver.sk/index.php", false, $context);
    $lines = explode("\n", $playlist);
    return $lines[5]; //1080p is on line 4
}
 
//Markiza with proxy
function markiza_url() {
    $siteurl = "https://media.cms.markiza.sk/embed/markiza-live?autoplay=any";
    $postdata = http_build_query(
        array(
                'url' => $siteurl
        )
        );
    $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            ));

    $context = stream_context_create($opts);
    $sitecontent = file_get_contents("https://www.proxyserver.sk/index.php", false, $context);
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
    $content = file_get_contents("https://proxy.zelvar.cz/subdom/proxy/index.php?q=https%3A%2F%2Fmedia" . ($tn ? "tn" : "") . ".cms.nova.cz%2Fembed%2F" . $x . ($tn ? "" : "live") . "%3Fautoplay%3D1&hl=200", false, stream_context_create(array("ssl"=>array("verify_peer_name"=>false))));
    return join("", explode("\\", explode("\"", explode("[{\"src\":\"", $content)[1])[0]));
}

function ta3_url() {
    return "https:" . explode("\"", explode("\" : \"", file_get_contents("https://embed.livebox.cz/ta3_v2/live-source.js"))[1])[0];
}

function m_url($c, $ref = "https://hirado.hu/") {
    $z = file_get_contents("https://player.mediaklikk.hu/playernew/player.php?video=" . $c . "&osfamily=OS%20X&browsername=", false, stream_context_create(array("http"=>array("header"=>"Referer: " . $ref))));
    $addr = "https:" . implode("", explode("\\", explode("\"", explode("\"file\"", $z)[1])[1]));
    return $addr;
}

function cnn_portugal() {
    $endpoint = json_decode(file_get_contents("https://front-api.iol.pt/api/v1/live/broadcast?canal=CNN"), true);
    return $endpoint["videoUrl"] . "wmsAuthSign=" . file_get_contents("https://services.iol.pt/matrix?userId=", false, stream_context_create(array("http"=>array("header"=>"User-Agent: Mozilla/5.0 (Linux; Android 8.1.0; SM-A260F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.61 Mobile Safari/537.36\r\n"))));
}

function dacast($i) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    #$frame = file_get_contents("https://iframe.dacast.com/live/" . $i, false, stream_context_create(array("http"=>array("header"=>"User-Agent: Mozilla/5.0 (Windows NT 10.0; rv:106.0) Gecko/20100101 Firefox/106.0\r\nReferer: https://www.cnc3.co.tt/\r\n"))));
    #$refer = explode("\"", explode("referrerToken=\"", $frame)[1])[0];
    #$id = explode("\"", explode("id=\"", $frame)[1])[0];
    $info = file_get_contents("https://playback.dacast.com/content/access?contentId=" . $i . "&provider=universe", false, stream_context_create(array("http"=>array("header"=>"User-Agent: Mozilla/5.0 (Windows NT 10.0; rv:106.0) Gecko/20100101 Firefox/106.0\r\nReferer: https://iframe.dacast.com/"))));
    return json_decode($info, true)["hls"];
}

function tego($url) {
    $uid = rand(0, 255) . rand(0, 255) . rand(0, 255) . rand(0, 255);
    $info = json_decode(file_get_contents("https://tegostream.com/player/wms_auth.php", false, stream_context_create(array("http"=>array("method"=>"POST","content"=>json_encode(array("uid"=>$uid)))))), true);
    loc($url . $info["wmsAuthSign"]);
}

function ceskatelevize($index) {
    $ua = "WeRead/4.1.1 WRBrand/Huawei Dalvik/2.1.0 (Linux; U; Android 7.0; EVA-L09 Build/HUAWEIEVA-L09)";

    $c = curl_init("https://proxy.zelvar.cz/subdom/proxy/index.php?q=" . urlencode("https://www.ceskatelevize.cz/services/ivysilani/xml/token") . "&hl=200");
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, "user=iDevicesMotion");
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_USERAGENT, $ua);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    $token = simplexml_load_string("<?xml" . explode("<?xml", curl_exec($c))[1])->__toString();
    curl_close($c);

    $c = curl_init("https://proxy.zelvar.cz/subdom/proxy/index.php?q=" . urlencode("https://www.ceskatelevize.cz/services/ivysilani/xml/playlisturl") . "&hl=200");
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query(array(
        "ID"=>("CT" . $index),
        "playerType"=>"iPad",
        "quality"=>"1080p",
        "playlistType"=>"json",
        "canPlayDrm"=>"false",
        "token"=>$token
    )));
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_USERAGENT, $ua);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    $_info = new DOMDocument();
    $_info->loadXML("<?xml" . explode("<?xml", curl_exec($c))[1]);
    curl_close($c);

    $c = curl_init("https://proxy.zelvar.cz/subdom/proxy/index.php?q=" . urlencode(implode("https://", explode("http://", $_info->textContent))) . "&hl=200");
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_USERAGENT, $ua);
    curl_setopt($c, CURLOPT_HEADER, true);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    $a = explode("{", curl_exec($c));
    $a[0] = "";
    $info = json_decode(implode("{", $a));
    loc(implode("https://", explode("http://", $info->playlist[0]->streamUrls->main)));
}

function myvideo($ch) {
    $c = curl_init("https://api.myvideo.ge/api/v1/auth/token");
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, "client_id=7&grant_type=client_implicit");
    curl_setopt($c, CURLOPT_REFERER, "https://tv.myvideo.ge/");
    curl_setopt($c, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
    $data = json_decode(curl_exec($c), true);

    $c2 = curl_init("https://api.myvideo.ge/api/v1/channel/chunk/" . $ch);
    curl_setopt($c2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c2, CURLOPT_REFERER, "https://tv.myvideo.ge/");
    curl_setopt($c2, CURLOPT_HTTPHEADER, array("Authorization: Bearer " . $data["access_token"]));
    $data2 = json_decode(curl_exec($c2), true);

    curl_close($c);
    curl_close($c2);
    return $data2["data"]["attributes"]["file"];
}

function onlinestream($a) {
    $c = curl_init($a);
    curl_setopt($c, CURLOPT_USERAGENT, "curl/8.0.1");
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    $j = explode("\n", curl_exec($c));
    curl_close($c);
    $k = "";
    for ($i = 0; $i < count($j); $i++) {
        if ($j[$i] == "" || $j[$i][0] == "#") continue;
        $k = $j[$i];
        break;
    }
    return $k;
}

function canal13($event) {
    $token = file_get_contents("https://us-central1-canal-13-stream-api.cloudfunctions.net/media/token");
    $token2 = json_decode($token, true);
    return "https://origin.dpsgo.com/ssai/event/" . $event . "/master.m3u8?auth-token=" . urlencode($token2["data"]["authToken"]);
}

function dead() {
    header("X-SKTV-Video-Dead: 1");
    loc("/video_unavailable/master.m3u8");
}

header("Content-Type: text/plain");

if (!isset($_GET["x"])) die("Channel not set! (?x=)");
$channel = $_GET["x"];

if ($channel == "TA3") {
    loc(ta3_url());
}
else if ($channel == "STV1") {
    loc(stv_url("1"));
}
else if ($channel == "STV2") {
    loc(stv_url("2"));
}
else if ($channel == "STV24") {
    loc(stv_url("3"));
}
else if ($channel == "STV-O") {
    loc(stv_url("4"));
}
else if ($channel == "RTVS") {
    loc(stv_url("6"));
}
else if ($channel == "NR_SR") {
    loc(stv_url("5"));
}
else if ($channel == "SPORT") {
    loc(stv_url("15"));
}
else if ($channel == "Markiza") {
    loc(markiza_url());
}
else if ($channel == "Nova") {
    loc(nova_url("nova-"));
}
else if ($channel == "NovaFun") {
    loc(nova_url("nova-2-"));
}
else if ($channel == "NovaLady") {
    loc(nova_url("nova-lady-"));
}
else if ($channel == "NovaGold") {
    loc(nova_url("nova-gold-"));
}
else if ($channel == "NovaCinema") {
    loc(nova_url("nova-cinema-"));
}
else if ($channel == "NovaAction") {
    loc(nova_url("nova-action-"));
}
else if ($channel == "NovaTNLive") {
    loc(nova_url("ETpdC5paJa8", true));
}
else if ($channel == "M1") {
    //loc(m_url("mtv1live"));
    dead();
}
else if ($channel == "M2") {
    //loc(m_url("mtv2live"));
    dead();
}
else if ($channel == "M4Plus") {
    //loc(m_url("mtv4plus"));
    dead();
}
else if ($channel == "M5") {
    //loc(m_url("mtv5live"));
    dead();
}
else if ($channel == "Duna") {
    //loc(m_url("dunalive"));
    dead();
}
else if ($channel == "Duna_World") {
    //loc(m_url("dunaworldlive"));
    dead();
}
else if ($channel == "CNN_Portugal") {
    loc(cnn_portugal());
}
else if ($channel == "CT1") {
    ceskatelevize(1);
}
else if ($channel == "CT2") {
    ceskatelevize(2);
}
else if ($channel == "CT24") {
    ceskatelevize(3);
}
else if ($channel == "CTsport") {
    ceskatelevize(4);
}
else if ($channel == "CT_D") {
    ceskatelevize(5);
}
else if ($channel == "CTart") {
    ceskatelevize(6);
}
else if ($channel == "CTsportPlus") {
    ceskatelevize(28);
}
else if ($channel == "CNC3") {
    loc(dacast("392bb373-b598-93f4-770f-19e696e810f4-live-106c3c62-9624-11ba-f1f0-6b3497d7c026"));
}
else if ($channel == "gayelle") {
    loc(tego("https://cdn003.tegotv.com/liveabr/Live_TV~Gayelle_Limited~Gayelle_The_Caribbean/playlist_dvr.m3u8"));
}
else if ($channel == "TTT") {
    loc(tego("https://cdn001.tegotv.com/liveabr/Live_TV~TTT~TTT~Foreign/playlist_dvr.m3u8"));
}
else if ($channel == "Synergy_TV") {
    loc(tego("https://cdn001.tegotv.com/liveabr/Live_TV~Synergy_Entertainment_Network_Limited~Synergy_TV/playlist_dvr.m3u8"));
}
else if ($channel == "IBN") {
    loc(tego("https://cdn003.tegotv.com/liveabr/Live_TV~IBN_Communications_Company~IBN/playlist_dvr.m3u8"));
}
else if ($channel == "Trinity_TV") {
    loc(tego("https://cdn001.tegotv.com/liveabr/Live_TV~Trinity_TV~Trinity_TV/playlist_dvr.m3u8"));
}
else if ($channel == "Rustavi2") {
    loc(myvideo("rustavi2hqnew"));
}
else if ($channel == "TVN") {
    $c = file_get_contents("https://live.tvn.cl/");
    $id = explode("'", explode("id: '", $c)[1])[0];
    $token = explode("'", explode("access_token: '", $c)[1])[0];
    loc("https://mdstrm.com/live-stream-playlist/" . $id . ".m3u8?access_token=" . urlencode($token));
}
else if ($channel == "Chilevision") {
    $c1 = file_get_contents("https://www.chilevision.cl/senal-online");
    $scriptUrl = explode("'", explode("script src='", explode("mdstrm-player", $c1)[1])[1])[0];
    $c2 = file_get_contents($scriptUrl);
    $id = explode("'", explode("id = '", $c2)[1])[0];
    $token = explode("'", explode("token = '", $c2)[1])[0];
    loc("https://mdstrm.com/live-stream-playlist/" . $id . ".m3u8?access_token=" . urlencode($token));
}
else if ($channel == "Canal13") {
    loc(canal13("bFL1IVq9RNGlWQaqgiFuNw"));
}
else if ($channel == "GO_TV") {
    loc("https://gortv-m3u.7m.pl/grab.php");
}
else {
    header("Status: 404");
    header("Content-Type: text/plain");
    echo "404 Not Found\n";
}
?>
