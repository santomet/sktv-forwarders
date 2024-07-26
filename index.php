<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>sktv forwarders revival</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto Mono', monospace;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="container mx-auto p-4">
        <div class="flex items-center justify-center space-x-2 text-4xl font-bold mb-8">
            <h1 class="text-purple-600">sk</h1>
            <h1 class="text-blue-600">tv</h1>
            <h1>forwarders revival</h1>
        </div>
        <p class="text-center mb-8">licensed under AGPL-3.0-or-later, <a href="https://github.com/santomet/sktv-forwarders" class="text-blue-500 underline">source available</a></p>
        <?php
        include "channels.inc.php";
        foreach($channels as $i) {
        ?>
        <div class="mb-12">
            <h1 class="text-2xl font-bold mb-4"><?php echo htmlspecialchars($i["name"]); ?></h1>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-300">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 border-b">Channel</th>
                            <th class="px-4 py-2 border-b">Streaming URL</th>
                            <th class="px-4 py-2 border-b">Original Streaming URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach($i["channels"] as $j) {
                        ?>
                        <tr>
                            <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($j["name"]); ?></td>
                            <td class="px-4 py-2 border-b"><a href="get.php?x=<?php echo $j["id"]; ?>" class="text-blue-500 underline"><?php echo htmlspecialchars($j["id"]); ?></a></td>
                            <td class="px-4 py-2 border-b"><a href="<?php echo $j["streamURL"]; ?>" class="text-blue-500 underline"><?php echo htmlspecialchars(strlen($j["streamURL"]) > 40 ? (substr($j["streamURL"], 0, 37) . "...") : $j["streamURL"]); ?></a></td>
                        </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-4"><?php echo $i["note"]; ?></p>
        </div>
        <?php
        }
        ?>
        <p class="text-center mt-8">&copy; <?php echo date("Y"); ?> Created originally by <a href="https://github.com/NezbednikSK" class="text-blue-500 underline">nezbednik</a>, now maintained by <a href="https://santomet.eu">santomet</a> (And redesigned by <a href="https://odjezdy.online" class="text-blue-500 underline">mxnticek</a> using <a href="https://screenshottocode.com/" class="text-blue-500 underline bold">Screenshot To Code</a>)</p>
        <p class="text-center mt-8"> Disclaimer: This project is an open-source initiative provided for educational purposes only. The software and scripts contained herein are intended to be used exclusively by individuals who have legitimate access to the resources, such as by completing any required registrations or residing in regions where access is permitted by the content provider </p>
    </div>
</body>
</html>
