<?php
// Leaflet.js Basemap Processing
if ($geo_leaflet_maps_sources) {
    echo leaflet_osm_basemaps();
    echo leaflet_usgs_basemaps();
    echo leaflet_esri_basemaps();
    echo leaflet_stamen_basemaps();
    echo leaflet_hydda_basemaps();
    echo leaflet_nasa_basemaps();
    echo leaflet_thunderforest_basemaps();
    echo leaflet_mapbox_basemaps();
    ?>
    <!-- Define Leaflet default basemap attribution-->
    <?php switch ($map_default) {
        case 'OpenStreetMap.Mapnik' || 'OpenStreetMap.DE' || 'OpenStreetMap.BZH' || 'OpenStreetMap.HOT' || 'MtbMap' || 'HikeBike.HikeBike':
            ?> var default_attribute = osm_attribute; <?php
            break;

        case 'OpenStreetMap.France':
            ?> var default_attribute = osm_fr_attribute; <?php
            break;

        case 'OpenTopoMap':
            ?> var default_attribute = osm_otm_attribute; <?php
            break;

        case 'OpenMapSurfer.Roads':
            ?> var default_attribute = oms_attribute; <?php
            break;

        default:
            ?> var default_attribute = ''; <?php
    }
} else {
    foreach ($geo_leaflet_sources as $leaflet_source) {
        foreach ($leaflet_source["variants"] as $variant => $varopts) {
            $varcode = mb_strtolower($leaflet_source["code"] . "_" . $variant);

            $varoptions = array();
            $varoptions["maxZoom"] = $leaflet_source["maxZoom"];
            $varoptions["attribution"] = $leaflet_source["attribution"];

            // Options can be set at root or overridden by a variant
            if (isset($varopts["options"])) {
                foreach ($varopts["options"] as $option => $optval) {
                    $varoptions[$option] = $optval;
                }
            }
            $attribution = isset($varoptions["options"]["attribution"]) ? $varoptions["options"]["attribution"] : $varoptions["attribution"];
            echo "var " . $varcode . "_attribute = '" . $attribution . "';\n";
            if (mb_strtolower($map_default) == mb_strtolower($leaflet_source["code"] . "." . $variant)) {
                echo "default_attribute = '" . $attribution . "';\n";
            }
            echo "var " . $varcode . " = L.tileLayer.provider('" . $leaflet_source["code"] . "."  . $variant . "', {\n";
            echo "    useCache: '" . ($map_default_cache ? "true" : "false") . "',\n";
            echo "    detectRetina: '" . ($map_retina ? "true" : "false") . "',\n";
            foreach ($varoptions as $varoption => $optval) {
                if ($varoption == "attribution") {
                    echo "    " . escape($varoption) . ": '" . $optval . "',\n";
                } else {
                    echo "    " . escape($varoption) . ": " . escape($optval) . ",\n";
                }
            }
            echo "});\n";
        }
    }
    echo "var rs_default = L.tileLayer.provider('ResourceSpace.OSM', {
        useCache: 'true',
        detectRetina: 'false',
        maxZoom: 3,
        attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors',
        });\n\n";
}