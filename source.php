<?php

function makename() {
    while(true) {
        $name = tempnam(dirname(__FILE__) . "/temp/", "sktv-forwarders-source-") . ".zip";
        if (!file_exists($name)) break;
    }
    return $name;
}

$_tempdir = dirname(__FILE__) . "/temp/";
$tempdir = opendir($_tempdir);
while(($file = readdir($tempdir)) !== false) {
    if ($file == "." || $file == "..") continue;
    if ((time() - filemtime($_tempdir . $file)) > 120) unlink($_tempdir . $file);
}
closedir($tempdir);

$filename = makename();
$zip = new ZipArchive();
$zip->open($filename, ZipArchive::CREATE);

function adddir($dirloc, $pref) {
    global $zip;
    $curloc = getcwd();
    chdir($dirloc);
    $dir = opendir(".");
    while(($file = readdir($dir)) !== false) {
        if ($file == "." || $file == ".." || $file == "temp") continue;
        if (is_dir($file)) {
            $bkpPref = $pref . "";
            $pref .= ($pref == "" ? "" : "/") . $file . "/";
            adddir($file, $pref);
            $pref = $bkpPref . "";
        }
        else $zip->addFile($file, $pref . $file);
    }
    closedir($dir);
    chdir($curloc);
}
adddir(".", "");

$zip->close();

$path = pathinfo($filename);
$generated = "/temp/" . $path["filename"] . "." . $path["extension"];

header("Status: 302");
header("Location: " . $generated);
