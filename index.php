<?php
include "channels.inc.php";
?><!DOCTYPE html>
<html>
    <title>sktv forwarders</title>
    <style>
        * {
            font-family: monospace;
        }
        table {
            border-collapse: collapse;
            text-align: center;
        }
        td, th {
            border: 1px solid black;
            padding: 0.4rem;
        }
        .i {
            display: inline;
        }
        .purple {
            color: #CC3399;
        }
        .blue {
            color: #3399FF;
        }
        .title {
            padding-bottom: 0;
            margin-bottom: 0;
            margin-top: 2rem;
        }
        .pagetitle {
            font-size: x-large;
            border-bottom: 0.5rem solid black;
            border-style: none none dotted none;
        }
        .title-ext {
            margin-top: 6rem;
        }
    </style>
    <div class="i pagetitle">
        <h1 class="i purple">sk</h1><h1 class="i blue">tv</h1><h1 class="i">&nbsp;forwarders</h1>
    </div>
    <p>licensed under AGPL-3.0-or-later, <a href="https://github.com/santomet/sktv-forwarders">source available</a></p>
<?php
foreach($channels as $i) {
    ?>    <h1 class="title"><?php echo htmlspecialchars($i["name"]); ?></h1>
    <table>
        <tr>
            <th>Channel</th>
            <th>Streaming URL</th>
            <th>Original Streaming URL</th>
        </tr>
<?php
    foreach($i["channels"] as $j) {
        ?>
        <tr>
            <td><?php echo htmlspecialchars($j["name"]); ?></td>
            <td><a href="get.php?x=<?php echo $j["id"]; ?>"><?php echo htmlspecialchars($j["id"]); ?></a></td>
            <td><a href="<?php echo $j["streamURL"]; ?>"><?php 
    echo htmlspecialchars(strlen($j["streamURL"]) > 40 ? (substr($j["streamURL"], 0, 37) . "...") : $j["streamURL"]);
?></a></td>
        </tr>
<?php
    }
?>
    </table>
<?php
    echo $i["note"];
}
?>    <p>&copy; <?php echo date("Y"); ?> Created originally by <a href="https://github.com/NezbednikSK">nezbednik</a>, now maintained by <a href="?>    <p>&copy; <?php echo date("Y"); ?> Created originally by <a href="//home.nezbednik.eu.org:2000/">nezbednik</a>, now maintained by <a href="//home.nezbednik.eu.org:2000/">santomet</a></p>
">santomet</a></p>
</html>
