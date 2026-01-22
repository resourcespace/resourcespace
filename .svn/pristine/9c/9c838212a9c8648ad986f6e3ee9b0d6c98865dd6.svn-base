<?php
// Geographic Map Search for Resources Using Leaflet.js and Various Leaflet Plugins

include '../include/boot.php';
include '../include/authenticate.php';
include '../include/header.php';

if ($geo_search_heatmap) { ?>
    <script src="<?php echo $baseurl?>/lib/heatmap.js/heatmap.js"></script>
    <script src="<?php echo $baseurl?>/lib/heatmap.js/leaflet-heatmap.js"></script>
    <?php
} ?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["geographicsearch"]); ?></h1>
    <p>
        <?php
        echo escape($lang["geographicsearch_help"]);
        render_help_link("user/geographic-search");
        ?>
    </p>

    <?php
    // Setup initial map variables.
    $zoomslider = 'false';
    $zoomcontrol = 'true';

    // Set Leaflet map search view height and layer control container height based on $mapheight.
    if (isset($mapsearch_height)) {
        $map1_height = $mapsearch_height;
        $layer_controlheight = $mapsearch_height - 40;
    } else  {
        // Default values.
        $map1_height = 500;
        $layer_controlheight = 460;
    }

    // Show zoom slider instead of default Leaflet zoom control?
    if ($map_zoomslider) {
        $zoomslider = 'true';
        $zoomcontrol = 'false';
    }
    ?>

    <!-- Drag mode selector -->
    <div id="GeoDragMode">
        <?php echo escape($lang['geodragmode']); ?>:&nbsp;
        <input type="radio" name="dragmode" id="dragmodepan" onclick="map1.editTools.stopDrawing();" />
        <label for="dragmodepan"><?php echo escape($lang['geodragmodepan']); ?></label>&nbsp;
        <input type="radio" name="dragmode" id="dragmodearea" checked="checked" onClick="map1.editTools.startRectangle();" />
        <label for="dragmodearea"><?php echo escape($lang['geodragmodeareaselect']); ?></label>
    </div>

    <!--Setup Leaflet map container with sizing-->
    <div id="search_map" style="width: 99%; margin-top:0px; margin-bottom:0px; height: <?php echo $map1_height; ?>px; display:block; border:1px solid black; float:none; overflow: hidden;">
    </div>

    <script>
        <?php set_geo_map_centerview(); ?>
        // Setup and define the Leaflet map with the initial view using leaflet.js and L.Control.Zoomslider.js.

        if (typeof map1 !== 'undefined') {
            map1.remove();
        }

        var map1 = new L.map('search_map', {
            editable: true,
            preferCanvas: true,
            renderer: L.canvas(),
            zoomsliderControl: <?php echo $zoomslider; ?>,
            zoomControl: <?php echo $zoomcontrol; ?>
        }).setView(mapcenterview,mapdefaultzoom);

        // Load available Leaflet basemap groups, layers, and attribute definitions.
        <?php include '../include/map_processing.php'; ?>

        // Define default Leaflet basemap layer using leaflet.js, leaflet.providers.js, and L.TileLayer.PouchDBCached.js
        var defaultLayer = new L.tileLayer.provider('<?php echo $map_default;?>', {
            useCache: '<?php echo $map_default_cache;?>', // Use browser caching of tiles (recommended)?
            detectRetina: '<?php echo $map_retina;?>', // Use retina high resolution map tiles?
            attribution: default_attribute
        }).addTo(map1);

        // Load Leaflet basemap definitions.
        <?php include '../include/map_basemaps.php'; ?>

        // Set styled layer control options for basemaps and add to the Leaflet map using styledLayerControl.js
        var options = {
            container_maxHeight: '<?php echo $layer_controlheight; ?>px',
            group_maxHeight: '380px',
            exclusive: false
        };

        var control = L.Control.styledLayerControl(baseMaps,options);
        map1.addControl(control);

        // Add geocoder search bar using control.geocoder.min.js
        L.Control.geocoder().addTo(map1);

        // Show zoom history navigation bar and add to Leaflet map using Leaflet.NavBar.min.js
        <?php if ($map_zoomnavbar) { ?>
            L.control.navbar().addTo(map1);
        <?php } ?>

        // Add a scale bar to the Leaflet map using leaflet.min.js
        new L.control.scale().addTo(map1);

        // Add a KML overlay to the Leaflet map using leaflet-omnivore.min.js
        <?php if ($map_kml) { ?>
            omnivore.kml('<?php echo $baseurl_short . $map_kml_file?>').addTo(map1);
            <?php
        }

        //Add heatmap to aid searching
        if ($geo_search_heatmap) {
            $heatmapfile = get_temp_dir() . "/heatmap_" . md5("heatmap" . $scramble_key);
            if (file_exists($heatmapfile)) {
                echo file_get_contents($heatmapfile);
                ?>
                var cfg = {
                    // radius should be small ONLY if scaleRadius is true (or small radius is intended)
                    // if scaleRadius is false it will be the constant radius used in pixels
                    "radius": 15,
                    "maxOpacity": .5,
                    // scales the radius based on map zoom
                    "scaleRadius": false,
                    // if set to false the heatmap uses the global maximum for colorization
                    // if activated: uses the data maximum within the current map boundaries
                    // (there will always be a red spot with useLocalExtremas true)
                    "useLocalExtrema": true,
                    latField: 'lat',
                    lngField: 'lng',
                    valueField: 'count'
                };  

                var heatmapLayer = new HeatmapOverlay(cfg).addTo(map1);
                heatmapLayer.setData(heatpoints);
                <?php
            }
        } ?>

        // Fix for Microsoft Edge and Internet Explorer browsers
        map1.invalidateSize(true);

        // Add an Area of Interest (AOI) selection box to the Leaflet map using leaflet-shades.js
        var shades = new L.LeafletShades().addTo(map1);

        // Get AOI coordinates
        shades.on('shades:bounds-changed', function(e) {
            // Get AOI box coordinates in World Geodetic System of 1984 (WGS84, EPSG:4326)
            var trLat = e['bounds']['_northEast']['lat'];
            var trLon = e['bounds']['_northEast']['lng'];
            var blLat = e['bounds']['_southWest']['lat'];
            var blLon = e['bounds']['_southWest']['lng'];

            // Create specially encoded geocoordinate search string to avoid keyword splitting
            var url = "<?php echo $baseurl_short?>pages/search.php?search=!geo" + (blLat + "b" + blLon + "t" + trLat + "b" + trLon).replace(/\-/gi,'m').replace(/\./gi,'p');
            CentralSpaceLoad(url, true);
        });

        jQuery(document).ready(function() {
            map1.editTools.startRectangle();
        });
    </script>
</div>

<?php
include '../include/footer.php';