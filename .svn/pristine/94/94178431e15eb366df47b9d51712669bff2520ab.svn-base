<?php

// Update cached geographic points for use as heatmap on geographic search
if ($geo_search_heatmap) {
    $defaultarchive = get_default_search_states();
    $defaultarchive = array_filter($defaultarchive, "is_int_loose");

    $allgeopoints = ps_query("SELECT ROUND(geo_lat, 1) AS lat, ROUND(geo_long, 1) AS lng, count(*) AS count FROM resource WHERE ref > 0 
        AND archive IN (" . ps_param_insert(count($defaultarchive)) . ") AND geo_lat IS NOT NULL GROUP BY lat, lng", ps_param_fill($defaultarchive, "i"));

    if (count($allgeopoints) > 0) {
        $heatdata = array(
            "max"   => max(array_column($allgeopoints, "count")),
            "data"  => $allgeopoints
        );

        $heatmap_cache = get_temp_dir() . "/heatmap_" . md5("heatmap" . $scramble_key);
        $heatmapjson = "var heatpoints = " . json_encode($heatdata) . ";";
        file_put_contents($heatmap_cache, $heatmapjson);
    }
}
