<?php 

$graph_tiles = ps_query("SELECT ref, url FROM dash_tile WHERE left(url, 21) = 'pages/team/ajax/graph'");
foreach ($graph_tiles as $tile) {
    $url = generateURL('pages/ajax/dash_tile.php', ['tltype' => 'conf', 'tlstyle' => 'analytics', 'tile' => $tile['ref'], 'data' => $tile['url']]);
    ps_query(
        "UPDATE dash_tile SET url = ? WHERE ref = ?",
        ['s', $url, 'i', $tile['ref']]
    );
}
$upload_tiles = ps_query("SELECT ref, url FROM dash_tile WHERE link LIKE '%uploader=%'");
foreach ($upload_tiles as $tile) {
    $tile['url'] = str_replace(['ftxt', 'tlstyle='], ['conf', 'tlstyle=upld'], $tile['url']);
    ps_query(
        "UPDATE dash_tile SET url = ? WHERE ref = ?",
        ['s', $tile['url'], 'i', $tile['ref']]
    );
}
