<?php
function add_channel(&$arr, $name, $id, $streamURL) {
    array_push($arr, array("name"=>$name, "id"=>$id, "streamURL"=>$streamURL));
}

function add_country(&$arr, $name, $countrycode, $channels, $note = "") {
    array_push($arr, array("name"=>$name, "countrycode"=>$countrycode, "channels"=>$channels, "note"=>$note));
}

$channels = array();

// Slovakia
$chan_sk = array();
add_channel($chan_sk, "Jednotka", "STV1", "https://www.rtvs.sk/televizia/live-1");
add_channel($chan_sk, "Dvojka", "STV2", "https://www.rtvs.sk/televizia/live-2");
add_channel($chan_sk, "RTVS 24", "STV24", "https://www.rtvs.sk/televizia/live-24");
add_channel($chan_sk, "RTVS Šport", "SPORT", "https://www.rtvs.sk/televizia/sport");
add_channel($chan_sk, ":O", "STV-O", "https://www.rtvs.sk/televizia/live-o");
add_channel($chan_sk, "RTVS", "RTVS", "https://www.rtvs.sk/televizia/live-rtvs");
add_channel($chan_sk, "NR SR", "NR_SR", "https://www.rtvs.sk/televizia/live-nr-sr");
add_channel($chan_sk, "TA3", "TA3", "https://www.ta3.com/live");
add_channel($chan_sk, "Markiza", "Markiza", "https://media.cms.markiza.sk/embed/markiza-live?autoplay=any");
add_channel($chan_sk, "(Markiza) Dajto", "Dajto", "https://media.cms.markiza.sk/embed/dajto-live?autoplay=any");
add_channel($chan_sk, "(Markiza) Doma", "Doma", "https://media.cms.markiza.sk/embed/doma-live?autoplay=any");
add_channel($chan_sk, "(Markiza) Krimi", "Krimi", "https://media.cms.markiza.sk/embed/krimi-live?autoplay=any");
add_channel($chan_sk, "(Markiza) Klasik", "Klasik", "https://media.cms.markiza.sk/embed/klasik-live?autoplay=any");

$slovakiaNote = array (
    "    <br>",
    "    <details>",
    "        <summary>note: All markiza channels need the Referer to be https://media.cms.markiza.sk/ !</summary>",
    "        <pre style=\"background-color: gainsboro;\">vlc --adaptive-use-access --http-referrer=https://media.cms.markiza.sk/ [URL]",
    "    </details>",
    ""
);

add_country($channels, "Slovakia", "sk", $chan_sk, implode("\n", $slovakiaNote));

// Czech Republic
$chan_cz = array();
add_channel($chan_cz, "ČT1", "CT1", "https://www.ceskatelevize.cz/zive/ct1/");
add_channel($chan_cz, "ČT2", "CT2", "https://www.ceskatelevize.cz/zive/ct2/");
add_channel($chan_cz, "ČT24", "CT24", "https://ct24.ceskatelevize.cz/#live");
add_channel($chan_cz, "ČT sport", "CTsport", "https://sport.ceskatelevize.cz/#live");
add_channel($chan_cz, "ČT :D", "CT_D", "https://decko.ceskatelevize.cz/zive");
add_channel($chan_cz, "ČT art", "CTart", "https://www.ceskatelevize.cz/zive/art/");
add_channel($chan_cz, "ČT sport Plus", "CTsportPlus", "https://sport.ceskatelevize.cz/clanek/ostatni/program-vysilani-ct-sport-na-webu-v-mobilu-a-hbbtv/5ddda79bfccd259ea46d41bc");
add_channel($chan_cz, "Nova", "Nova", "https://tv.nova.cz/sledujte-zive/1-nova");
add_channel($chan_cz, "Nova Cinema", "NovaCinema", "https://tv.nova.cz/sledujte-zive/2-nova-cinema");
add_channel($chan_cz, "Nova Action", "NovaAction", "https://tv.nova.cz/sledujte-zive/3-nova-action");
add_channel($chan_cz, "Nova Fun", "NovaFun", "https://tv.nova.cz/sledujte-zive/4-nova-fun");
add_channel($chan_cz, "Nova Gold", "NovaGold", "https://tv.nova.cz/sledujte-zive/5-nova-gold");
add_channel($chan_cz, "Nova Lady", "NovaLady", "https://tv.nova.cz/sledujte-zive/29-nova-lady");
add_channel($chan_cz, "TN Live", "NovaTNLive", "https://tn.nova.cz/tnlive");

$czechNote = array(
    "    <br>",
    "    <details>",
    "        <summary>note: all Nova channels (excluding TN Live) need the Referer to be <a href=\"https://media.cms.nova.cz\" style=\"text-decoration: none;\">https://media.cms.nova.cz</a>!</summary>",
    "        <pre style=\"background-color: gainsboro;\">vlc --adaptive-use-access --http-referrer=https://media.cms.nova.cz/ [URL]",
    "    </details>",
    ""
);

add_country($channels, "Czech Republic", "cz", $chan_cz, implode("\n", $czechNote));

/*
// Hungary
$chan_hu = array();
add_channel($chan_hu, "M1 /broken/", "M1", "https://hirado.hu/elo/m1/");
add_channel($chan_hu, "M2 /broken/", "M2", "https://mediaklikk.hu/m2-elo/");
add_channel($chan_hu, "M4 Sport + /broken/", "M4Plus", "https://m4sport.hu/elo/mtv4plus/");
add_channel($chan_hu, "M5 /broken/", "M5", "https://mediaklikk.hu/m5-elo/");
add_channel($chan_hu, "Duna /broken/", "Duna", "https://mediaklikk.hu/duna-elo/");
add_channel($chan_hu, "Duna World /broken/", "Duna_World", "https://mediaklikk.hu/duna-world-elo/");

add_country($channels, "Hungary", "hu", $chan_hu);
*/

// Portugal
$chan_pt = array();
add_channel($chan_pt, "CNN Portugal", "CNN_Portugal", "https://cnnportugal.iol.pt/direto");

add_country($channels, "Portugal", "pt", $chan_pt);

// Trinidad and Tobago
$chan_tt = array();
add_channel($chan_tt, "CNC3", "CNC3", "https://www.cnc3.co.tt/.live-stream/");
add_channel($chan_tt, "gayelle", "gayelle", "https://tegostream.com/tv?channel=864");
add_channel($chan_tt, "TTT", "TTT", "https://tegostream.com/tv?channel=255");
add_channel($chan_tt, "Synergy TV", "Synergy_TV", "https://tegostream.com/tv?channel=259");
add_channel($chan_tt, "IBN", "IBN", "https://tegostream.com/tv?channel=846");
add_channel($chan_tt, "Trinity TV", "Trinity_TV", "https://tegostream.com/tv?channel=265");

add_country($channels, "Trinidad and Tobago", "tt", $chan_tt);

$chan_ge = array();
add_channel($chan_ge, "Rustavi 2", "Rustavi2", "https://rustavi2.ge/en/dvr1");

add_country($channels, "Georgia", "ge", $chan_ge);

$chan_cl = array();
add_channel($chan_cl, "TVN", "TVN", "https://www.tvn.cl/en-vivo");
add_channel($chan_cl, "Chilevisión [broken]", "Chilevision", "https://www.chilevision.cl/senal-online");
add_channel($chan_cl, "Canal 13", "Canal13", "https://www.13.cl/en-vivo");
/*
add_channel($chan_cl, "13 Entretención", "13Entretencion", "https://www.13.cl/13e");
add_channel($chan_cl, "13 Cultura", "13Cultura", "https://www.13.cl/13cultura");
add_channel($chan_cl, "13 Prime", "13Prime", "https://www.13.cl/13p");
add_channel($chan_cl, "13 Kids", "13Kids", "https://www.13.cl/13kids");
add_channel($chan_cl, "13 Realities", "13Realities", "https://www.13.cl/13realities");
add_channel($chan_cl, "13 Teleseries", "13Teleseries", "https://www.13.cl/13t");
*/

add_country($channels, "Chile", "cl", $chan_cl);
