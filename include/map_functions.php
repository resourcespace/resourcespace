<?php

// To add additional basemap sources, see http://leaflet-extras.github.io/leaflet-providers/preview/index.html for the provider names, attribution, maximum zoom level, and any other required provider parameters, and add to the appropriate basemap group below or create a new basemap group.  Will also need to add additional code into the <!--Determine basemaps and map groups for user selection--> section on each PHP page using Leaflet maps (../pages/geo_search.php), the Leaflet Providers section in ../include/config.default.php, and the appropriate providers group section in ../languages/en.php.

// Define available Leaflet basemaps groups and layers using leaflet.providers.js, L.TileLayer.PouchDBCached.js, and styledLayerControl.js.

use Gettext\Languages\Exporter\Php;

/**
 * Generates OpenStreetMap basemaps for use in a Leaflet map.
 *
 * This function defines various tile layers from OpenStreetMap and related providers,
 * setting properties such as caching, retina display support, maximum zoom levels,
 * and attribution for each layer.
 *
 * @return string JavaScript code that initializes the OSM basemaps for Leaflet.
 */
function leaflet_osm_basemaps()
{
    global $map_default_cache, $map_retina;

    return "<!--OpenStreetMap (OSM) basemap group-->
        var osm_attribute = 'Map data © <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors';

        var osm_mapnik = L.tileLayer.provider('OpenStreetMap.Mapnik', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 19,
            attribution: osm_attribute
        });

        var osm_de = L.tileLayer.provider('OpenStreetMap.DE', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 18,
            attribution: osm_attribute
        });

        var osm_fr_attribute = '&copy; Openstreetmap France | &copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>';
        var osm_fr = L.tileLayer.provider('OpenStreetMap.France', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 20,
            attribution: osm_fr_attribute
        });

        var osm_ch = L.tileLayer.provider('OpenStreetMap.CH', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 18,
            attribution: osm_attribute
        });

        var osm_bzh = L.tileLayer.provider('OpenStreetMap.BZH', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 19,
            attribution: osm_attribute
        });

        var osm_hot = L.tileLayer.provider('OpenStreetMap.HOT', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 19,
            attribution: osm_attribute
        });

        var osm_hikebike = L.tileLayer.provider('HikeBike.HikeBike', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 19,
            attribution: osm_attribute
        });

        var osm_mtb = L.tileLayer.provider('MtbMap', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: osm_attribute
        });

        var osm_otm_attribute = 'Map data: &copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>, <a href=\"http://viewfinderpanoramas.org\">SRTM</a> | Map style: &copy; <a href=\"https://opentopomap.org\">OpenTopoMap</a> (<a href=\"https://creativecommons.org/licenses/by-sa/3.0/\">CC-BY-SA</a>)';
        var osm_otm = L.tileLayer.provider('OpenTopoMap', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 17,
            attribution: osm_otm_attribute
        }); ";
}

/**
 * Generates ESRI basemaps for use in a Leaflet map.
 *
 * This function defines various tile layers from ESRI, setting properties such as caching,
 * retina display support, maximum zoom levels, and attribution for each layer.
 *
 * @return string JavaScript code that initializes the ESRI basemaps for Leaflet.
 */
function leaflet_esri_basemaps() // ESRI basemaps.
{
    global $map_default_cache, $map_retina;

    return "<!--ESRI basemap group-->
        var esri_street_attribute = 'Tiles &copy; Esri &mdash; Source: Esri, DeLorme, NAVTEQ, USGS, Intermap, iPC, NRCAN, Esri Japan, METI, Esri China (Hong Kong), Esri (Thailand), TomTom, 2012';
        var esri_street = L.tileLayer.provider('Esri.WorldStreetMap', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: esri_street_attribute
        });

        var esri_delorme_attribute = 'Tiles &copy; Esri &mdash; Copyright: &copy;2012 DeLorme';
        var esri_delorme = L.tileLayer.provider('Esri.DeLorme', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 1,
            maxZoom: 11,
            attribution: esri_delorme_attribute
        });

        var esri_topo_attribute = 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ, TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ordnance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User Community';
        var esri_topo = L.tileLayer.provider('Esri.WorldTopoMap', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: esri_topo_attribute
        });

        var esri_imagery_attribute = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';
        var esri_imagery = L.tileLayer.provider('Esri.WorldImagery', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: esri_imagery_attribute
        });

        var esri_terrain_attribute = 'Tiles &copy; Esri &mdash; Source: USGS, Esri, TANA, DeLorme, and NPS';
        var esri_terrain = L.tileLayer.provider('Esri.WorldTerrain', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 13,
            attribution: esri_terrain_attribute
        });

        var esri_relief_attribute = 'Tiles &copy; Esri &mdash; Source: Esri';
        var esri_relief = L.tileLayer.provider('Esri.WorldShadedRelief', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 13,
            attribution: esri_relief_attribute
        });

        var esri_physical_attribute = 'Tiles &copy; Esri &mdash; Source: US National Park Service';
        var esri_physical = L.tileLayer.provider('Esri.WorldPhysical', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 8,
            attribution: esri_physical_attribute
        });

        var esri_ocean_attribute = 'Tiles &copy; Esri &mdash; Sources: GEBCO, NOAA, CHS, OSU, UNH, CSUMB, National Geographic, DeLorme, NAVTEQ, and Esri';
        var esri_ocean = L.tileLayer.provider('Esri.OceanBasemap', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 13,
            attribution: esri_ocean_attribute
        });

        var esri_natgeo_attribute = 'Tiles &copy; Esri &mdash; National Geographic, Esri, DeLorme, NAVTEQ, UNEP-WCMC, USGS, NASA, ESA, METI, NRCAN, GEBCO, NOAA, iPC';
        var esri_natgeo = L.tileLayer.provider('Esri.NatGeoWorldMap', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 16,
            attribution: esri_natgeo_attribute
        });

        var esri_gray_attribute = 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ';
        var esri_gray = L.tileLayer.provider('Esri.WorldGrayCanvas', {
            useCache: '" . ( $map_default_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 16,
            attribution: esri_gray_attribute
        }); ";
}

function leaflet_stamen_basemaps() // Stamen basemaps.
{
    global $map_layer_cache, $map_retina;

    return "<!--Stamen basemap group-->
        var stamen_attribute = 'Map tiles by <a href=\"http://stamen.com\">Stamen Design</a>, <a href=\"http://creativecommons.org/licenses/by/3.0\">CC BY 3.0</a> &mdash; Map data &copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>';

        var stamen_toner = L.tileLayer.provider('Stamen.Toner', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 0,
            maxZoom: 20,
            ext: 'png',
            attribution: stamen_attribute
        });

        var stamen_tonerlt = L.tileLayer.provider('Stamen.TonerLite', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 0,
            maxZoom: 20,
            ext: 'png',
            attribution: stamen_attribute
        });

        var stamen_tonerback = L.tileLayer.provider('Stamen.TonerBackground', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 0,
            maxZoom: 20,
            ext: 'png',
            attribution: stamen_attribute
        });

        var stamen_terrain = L.tileLayer.provider('Stamen.Terrain', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 0,
            maxZoom: 18,
            ext: 'png',
            attribution: stamen_attribute
        });

        var stamen_terrainback = L.tileLayer.provider('Stamen.TerrainBackground', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 0,
            maxZoom: 18,
            ext: 'png',
            attribution: stamen_attribute
        });

        var stamen_relief = L.tileLayer.provider('Stamen.TopOSMRelief', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 0,
            maxZoom: 20,
            ext: 'jpg',
            attribution: stamen_attribute
        });

        var stamen_watercolor = L.tileLayer.provider('Stamen.Watercolor', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 1,
            maxZoom: 16,
            ext: 'jpg',
            attribution: stamen_attribute
        }); ";
}

/**
 * Generates Hydda basemaps for use in a Leaflet map.
 *
 * @return string JavaScript code that initializes the Hydda basemaps for Leaflet.
 */
function leaflet_hydda_basemaps() // Hydda basemaps.
{
    global $map_layer_cache, $map_retina;

    return "<!--Hydda basemap group-->
        var hydda_attribute = 'Tiles courtesy of <a href=\"http://openstreetmap.se/\" target=\"_blank\">OpenStreetMap Sweden</a> &mdash; Map data &copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>';

        var hydda_full = L.tileLayer.provider('Hydda.Full', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 18,
            attribution: hydda_attribute
        });

        var hydda_base = L.tileLayer.provider('Hydda.Base', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 18,
            attribution: hydda_attribute
        }); ";
}

/**
 * Generates NASA basemaps for use in a Leaflet map.
 *
 * @return string JavaScript code that initializes the NASA basemaps for Leaflet.
 */
function leaflet_nasa_basemaps() // NASA basemaps.
{
    global $map_layer_cache, $map_retina;

    return "<!--NASA GIBS basemap group-->
        var nasa_attribute = 'Imagery provided by services from the Global Imagery Browse Services (GIBS), operated by the NASA/GSFC/Earth Science Data and Information System (<a href=\"https://earthdata.nasa.gov\">ESDIS</a>) with funding provided by NASA/HQ.';

        var nasa_gibscolor = L.tileLayer.provider('NASAGIBS.ModisTerraTrueColorCR', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 1,
            maxZoom: 9,
            format: 'jpg',
            attribution: nasa_attribute
        });

        var nasa_gibsfalsecolor = L.tileLayer.provider('NASAGIBS.ModisTerraBands367CR', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 1,
            maxZoom: 9,
            format: 'jpg',
            attribution: nasa_attribute
        });

        var nasa_gibsnight = L.tileLayer.provider('NASAGIBS.ViirsEarthAtNight2012', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            minZoom: 1,
            maxZoom: 8,
            format: 'jpg',
            attribution: nasa_attribute
        }); ";
}

/**
 * Generates USGS basemaps for use in a Leaflet map.
 *
 * @return string JavaScript code that initializes the USGS basemaps for Leaflet.
 */
function leaflet_usgs_basemaps() // U.S. Geological Survey The National Map basemaps.
{
    global $map_layer_cache, $map_retina;

    return "<!--USGS The National Map basemaps group-->
        var usgstnm_attribute = 'Map data <a href=\"https://www.doi.gov\">U.S. Department of the Interior</a> | <a href=\"https://www.usgs.gov\">U.S. Geological Survey</a>';

        var usgs_topo = L.tileLayer.provider('USGSTNM.USTopo', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: usgstnm_attribute
        }); 

        var usgs_imagery = L.tileLayer.provider('USGSTNM.USImagery', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: usgstnm_attribute
        });

        var usgs_imagerytopo = L.tileLayer.provider('USGSTNM.USImageryTopo', {
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: usgstnm_attribute
        }); ";
}

/**
 * Generates Thunderforest basemaps for use in a Leaflet map.
 *
 * @return string JavaScript code that initializes the Thunderforest basemaps for Leaflet.
 */
function leaflet_thunderforest_basemaps() // Thunderforest basemaps.
{
    global $map_layer_cache, $map_retina, $map_tfapi;

    return "<!--Thunderforest basemap group (requires an API key)-->
        var tf_attribute = '&copy; <a href=\"http://www.thunderforest.com/\">Thunderforest</a>, &copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>';

        var tf_ocm = L.tileLayer.provider('Thunderforest.OpenCycleMap', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        });

        var tf_transport = L.tileLayer.provider('Thunderforest.Transport', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        });

        var tf_transportdark = L.tileLayer.provider('Thunderforest.TransportDark', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        });

        var tf_landscape = L.tileLayer.provider('Thunderforest.Landscape', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        });

        var tf_outdoors = L.tileLayer.provider('Thunderforest.Outdoors', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        });

        var tf_pioneer = L.tileLayer.provider('Thunderforest.Pioneer', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        }); 
        
        var tf_mobileatlas = L.tileLayer.provider('Thunderforest.MobileAtlas', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        });
        
        var tf_neighbourhood = L.tileLayer.provider('Thunderforest.Neighbourhood', {
            apikey: '<?php echo $map_tfapi?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            maxZoom: 22,
            attribution: tf_attribute
        }); ";
}

function leaflet_mapbox_basemaps() // Mapbox basemaps.
{
    global $map_layer_cache, $map_retina, $map_mapboxid, $map_mapboxtoken, $map_mapboxattribution;

    return "<!--Mapbox basemaps group (requires API keys)-->
        var mapbox = L.tileLayer.provider('MapBox', {
            id: '<?php echo $map_mapboxid?>',
            accessToken: '<?php echo $map_mapboxtoken?>',
            useCache: '" .  ( $map_layer_cache ? "true" : "false" ) . "',
            detectRetina: '" . ( $map_retina ? "true" : "false" ) . "',
            attribution: '<?php echo $map_mapboxattribution?>'
        }); ";
}

/**
 * Generates Mapbox basemaps for use in a Leaflet map.
 *
 * This function initializes a Mapbox tile layer, requiring an API ID and access token.
 * It also allows configuration for caching, retina display support, and attribution.
 *
 * @return string JavaScript code that initializes the Mapbox basemap for Leaflet.
 */
function leaflet_map_zoom($map_zoom)
{
    global $resource, $geolocation_default_bounds;

    // If no zoom level is set or is non-numeric, define as 0 to enable automatic zoom assignment below.
    $zoom = trim((string) $map_zoom);
    if (!is_int_loose($zoom)) {
        $zoom = 2;
        $arr_bounds = explode(",", $geolocation_default_bounds);
        if (count($arr_bounds) == 3) {
            $zoom = end($arr_bounds);
        }
    }

    if (!($zoom >= 2 && $zoom <= 21)) {
        $zoom = 16;
        $siglon = round(100000 * abs($resource['geo_long'])) % 100000;
        $siglat = round(100000 * abs($resource['geo_lat'])) % 100000;
        if ($siglon % 100000 == 0 && $siglat % 100000 == 0) {
            $zoom = 3;
        } elseif ($siglon % 10000 == 0 && $siglat % 10000 == 0) {
            $zoom = 6;
        } elseif ($siglon % 1000 == 0 && $siglat % 1000 == 0) {
            $zoom = 10;
        } elseif ($siglon % 100 == 0 && $siglat % 100 == 0) {
            $zoom = 15;
        }
    }
    return $zoom;
}

/**
 * Parses the resource polygon string to extract latitude and longitude bounds and formats the polygon string.
 *
 * @param array $fields The resource fields array containing polygon data.
 * @param bool $minmax Flag to determine if minimum and maximum latitude and longitude values should be calculated.
 * @return array An associative array containing the minimum and maximum latitude and longitude values,
 *               as well as the formatted polygon string for Leaflet display.
 */
function leaflet_polygon_parsing($fields, $minmax = true)
{
    global $map_polygon_field;

    // Search resource $fields array for the $map_polygon_field.
    $key1 = array_search($map_polygon_field, array_column($fields, 'ref'));

    if ($minmax) {
        // Strip coordinate pair parathenses from polygon array.
        $values = str_replace(')', '', str_replace('(', '', explode(',', $fields[$key1]['value'])));

        // Determine minimum and maximum latitude values.
        $lat_values = array($values[0], $values[2], $values[4], $values[6]);
        $polygon['lat_min'] = min($lat_values);
        $polygon['lat_max'] = max($lat_values);

        // Determine minimum and maximum longitude values.
        $long_values = array($values[1], $values[3], $values[5], $values[7]);
        $polygon['long_min'] = min($long_values);
        $polygon['long_max'] = max($long_values);
    }

    // Format polygon string for Leaflet footprint display below.
    $polygon1 = str_replace('(', '[', $fields[$key1]['value']);
    $polygon1 = str_replace(')', ']', $polygon1);
    $polygon['values'] = '[' . $polygon1 . ']';

    return $polygon;
}

/**
 * Validates geolocation coordinates to ensure they are numeric and within acceptable bounds.
 *
 * @param mixed $coordinate The coordinate value to check.
 * @param string $type The type of coordinate ('latitude' or 'longitude').
 * @return bool True if the coordinate is valid; otherwise, false.
 */
function leaflet_coordinate_check($coordinate, $type)
{
    $check = false;
    if (!is_numeric($coordinate)) {
        return false;
    }

    if ($type == 'latitude' && $coordinate >= -20037508.34 && $coordinate <= 20037508.34) {
        $check = true;
    }

    if ($type == 'longitude' && $coordinate >= -20037508.34 && $coordinate <= 20037508.34) {
        $check = true;
    }

    return $check;
}

/**
 * Creates a map color markers legend for Leaflet maps.
 *
 * This function generates HTML for a legend that displays markers with colors corresponding to resource types
 * or custom metadata field values. If a custom metadata field is defined, the legend reflects that; otherwise,
 * it shows the default resource types and their associated colors.
 *
 * @return void Outputs the HTML for the legend.
 */
function leaflet_markers_legend()
{
    global $lang, $marker_metadata_field, $marker_metadata_array, $MARKER_COLORS;

    if (!isset($marker_metadata_field) || $lang['custom_metadata_markers'] == '') { ?>
        <b> <?php echo escape($lang['legend_text']); ?>&nbsp;</b>
        <?php
        $restypes = get_resource_types();
        foreach ($restypes as $restype) {
            $markercolour = (isset($restype["colour"]) && $restype["colour"] > 0) ? (int)$restype["colour"] : ($restype['ref'] % count($MARKER_COLORS));
            echo "<img src='../lib/leaflet_plugins/leaflet-colormarkers-1.0.0/img/marker-icon-" . strtolower($MARKER_COLORS[$markercolour])  . ".png' alt='" . $MARKER_COLORS[$markercolour] . " Icon' style='width:19px;height:31px;'>" . $restype["name"] . "&nbsp;";
        }
    } else // Custom metadata field color markers legend.
        { ?>
        <b> <?php echo escape($lang['custom_metadata_markers']); ?>&nbsp;</b> <?php

        // Loop through and create the custom color marker legend text, ignoring the first 'unset' item
        for ($i = 0; $i < count($marker_metadata_array); $i++) {
            $ltext[$i] = $marker_metadata_array[$i]['min'] . "-" . $marker_metadata_array[$i]['max'];
        }

        for ($i = 0; $i < count($marker_metadata_array); $i++) {
            ?> <img src="../lib/leaflet_plugins/leaflet-colormarkers-1.0.0/img/marker-icon-<?php echo strtolower($MARKER_COLORS[$i])?>.png" alt="<?php echo $MARKER_COLORS[$i]; ?> Icon" style="width:19px;height:31px;"> <?php echo $ltext[$i]; ?> &nbsp; <?php
        }
    }
}

/**
 * Adds map providers for Leaflet maps.
 *
 * This function generates a JavaScript snippet that defines various tile layer providers for use in Leaflet maps.
 * It supports OpenStreetMap and ESRI basemaps, as well as custom providers defined in the global variable `$geo_leaflet_sources`.
 * The function also includes options for caching tile layers and handling different zoom levels and attribution.
 *
 * @return void Outputs the HTML and JavaScript for adding map providers to Leaflet.
 */
function header_add_map_providers()
{
    global $geo_leaflet_sources, $baseurl, $geo_tile_caching;
    ?>
    <script>
    // Copied from leaflet-providers.js
    (function (root, factory) {
        if (typeof define === 'function' && define.amd) {
            // AMD. Register as an anonymous module.
            define(['leaflet'], factory);
        } else if (typeof modules === 'object' && module.exports) {
            // define a Common JS module that relies on 'leaflet'
            module.exports = factory(require('leaflet'));
        } else {
            // Assume Leaflet is loaded into global object L already
            factory(L);
        }
    }(this, function (L) {
        'use strict';

        L.TileLayer.Provider = L.TileLayer.extend({
            initialize: function (arg, options) {
                var providers = L.TileLayer.Provider.providers;

                var parts = arg.split('.');

                var providerName = parts[0];
                var variantName = parts[1];

                if (!providers[providerName]) {
                    throw 'No such provider (' + providerName + ')';
                }

                var provider = {
                    url: providers[providerName].url,
                    options: providers[providerName].options
                };

                // overwrite values in provider from variant.
                if (variantName && 'variants' in providers[providerName]) {
                    if (!(variantName in providers[providerName].variants)) {
                        throw 'No such variant of ' + providerName + ' (' + variantName + ')';
                    }
                    var variant = providers[providerName].variants[variantName];
                    var variantOptions;
                    if (typeof variant === 'string') {
                        variantOptions = {
                            variant: variant
                        };
                    } else {
                        variantOptions = variant.options;
                    }
                    provider = {
                        url: variant.url || provider.url,
                        options: L.Util.extend({}, provider.options, variantOptions)
                    };
                }

                // replace attribution placeholders with their values from toplevel provider attribution,
                // recursively
                var attributionReplacer = function (attr) {
                    if (attr.indexOf('{attribution.') === -1) {
                        return attr;
                    }
                    return attr.replace(/\{attribution.(\w*)\}/g,
                        function (match, attributionName) {
                            return attributionReplacer(providers[attributionName].options.attribution);
                        }
                    );
                };
                provider.options.attribution = attributionReplacer(provider.options.attribution);

                // Compute final options combining provider options with any user overrides
                var layerOpts = L.Util.extend({}, provider.options, options);
                L.TileLayer.prototype.initialize.call(this, provider.url, layerOpts);
            }
        });

        /**
        * Definition of providers.
        * see http://leafletjs.com/reference.html#tilelayer for options in the options map.
        */

        L.TileLayer.Provider.providers = {

        <?php
        foreach ($geo_leaflet_sources as $leaflet_source) {
            echo escape($leaflet_source["code"])  . ": {\n";
            if ($geo_tile_caching) {
                // Is this the search page? If so need to get collection ID to authenticate external shares
                $searchparts = explode(" ", getval("search", ""));
                $collection = str_replace("!collection", "", $searchparts[0]);
                $resource = getval("ref", ""); // For resource view page
                $urlparams = array(
                    "provider"  =>  $leaflet_source["code"],
                    "resource"  => $resource,
                    "collection"  => $collection,
                    "k"  =>  getval("k", ""),
                    );
                $sourceurl = generateURL($baseurl . "/pages/ajax/tiles.php", $urlparams) . "&x={x}&y={y}&z={z}";
            } else {
                $sourceurl =  $leaflet_source["url"];
            }
            echo "        url: '" . $sourceurl . "',\n";
            echo "        options: {\n";
            if (isset($leaflet_source["maxZoom"]) && is_int_loose($leaflet_source["maxZoom"])) {
                echo "        maxZoom: " . (int)$leaflet_source["maxZoom"] . ",\n";
            }
            if (isset($leaflet_source["attribution"])) {
                echo "        attribution: '" . $leaflet_source["attribution"] . "',\n";
            }
            echo "    },\n"; // End of options
            echo "        variants: {\n";
            foreach ($leaflet_source["variants"] as $variant => &$variantdata) {
                echo $variant  . ": {\n        ";
                if (isset($variantdata["url"])) {
                    if ($geo_tile_caching) {
                        $urlparams["variant"] = $variant;
                        $variantdata["url"] = generateURL($baseurl . "/pages/ajax/tiles.php", $urlparams) . "&x={x}&y={y}&z={z}";
                    }
                    echo "    url: '" . $variantdata["url"] . "'\n";
                }
                echo "},\n";
            }
            echo "         },\n"; // End of variants
            echo "},\n"; // End of leaflet source
        }
        ?>
        ResourceSpace: {
            url: '<?php echo $baseurl; ?>/pages/ajax/tiles.php?x={x}&y={y}&z={z}',
            options: {
                maxZoom: 3,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                },
            variants: { OSM: {}}
            }

        };

        L.tileLayer.provider = function (provider, options) {
            return new L.TileLayer.Provider(provider, options);
        };

        return L;
    }));
    </script>
    <?php
}

/**
 * Checks the current page and includes necessary geolocation libraries for Leaflet maps.
 *
 * This function loads the Leaflet Control Geocoder plugin and checks if the current page requires geolocation capabilities.
 * It also handles polyfills for browser compatibility, specifically for Internet Explorer and Edge.
 *
 * @return void Outputs the HTML and JavaScript for including geolocation libraries if applicable.
 */
function get_geolibraries()
{
    global $baseurl, $pagename, $map_default_cache, $map_layer_cache, $geo_leaflet_maps_sources,
    $map_zoomnavbar, $map_kml;
    $map_pages = array(
        "geo_edit",
        "geo_search",
        "search",
        "view",
        "edit",
        );
    if (!in_array($pagename, $map_pages)) {
        return false;
    }?>

    <!--Leaflet Control Geocoder 1.10.0 plugin files-->
    <link rel="stylesheet" href="<?php echo $baseurl?>/lib/leaflet_plugins/leaflet-control-geocoder-1.10.0/dist/Control.Geocoder.css"/>
    <script src="<?php echo $baseurl?>/lib/leaflet_plugins/leaflet-control-geocoder-1.10.0/dist/Control.Geocoder.min.js"></script>
    <?php
}

/**
 *  Set bounds for default map view (geo_search.php and geo_edit.php)
 *
 * @return void
 */
function set_geo_map_centerview()
{
    global $geolocation_default_bounds;
    $centerparts = explode(",", $geolocation_default_bounds);
    echo "\n    mapcenterview= L.CRS.EPSG3857.unproject(L.point(" . $centerparts[0] . "," . $centerparts[1] . "));\n";
    echo "mapdefaultzoom = " . (int)$centerparts[2] . ";\n";
}

/**
 * Outputs the script tag for including additional Leaflet plugins.
 *
 * This function loads the necessary JavaScript for the Leaflet Marker Cluster library, which helps in managing
 * large sets of markers on Leaflet maps by clustering them for better visualization.
 *
 * @return void Outputs the script tag for the Marker Cluster plugin.
 */
function get_geo_maps_scripts()
{
    global $baseurl;
    ?>
    <script src="<?php echo $baseurl?>/lib/leaflet_plugins/leaflet-markercluster-1.4.1/dist/leaflet.markercluster.min.js"></script>
    <?php
}