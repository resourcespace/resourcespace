<?php
// Map Search View Using Leaflet.js and Various Leaflet Plugins

global $disable_geocoding, $lang;

// Check if geolocation/maps have been disabled.
if ($disable_geocoding) {
    header('HTTP/1.1 403 Forbidden');
    exit($lang['error-geocodingdisabled']);
}

// Setup initial Leaflet map variables.
global $baseurl, $mapsearch_height, $map_default, $geomarker, $preview_paths;
global $map_centerview, $map_zoomslider, $map_zoomnavbar, $map_kml, $map_kml_file;
global $map_retina, $marker_resource_preview, $MARKER_COLORS;

$zoomslider = 'false';
$zoomcontrol = 'true';

// Set Leaflet map search view height and layer control container height based on $mapsearch_height.
if (isset($mapsearch_height)) {
    $map1_height = $mapsearch_height;
    $layer_controlheight = $mapsearch_height - 40;
} else // Default values.
    {
    $map1_height = '500';
    $layer_controlheight = 460;
}

// Show zoom slider instead of default Leaflet zoom control?
if ($map_zoomslider) {
    $zoomslider = 'true';
    $zoomcontrol = 'false';
}

get_geo_maps_scripts();
?>

<!--Map introtext-->
<div id="map1_introtext" style="margin-top:0px; margin-bottom:0px; width: 99%;"></div>

<!--Setup Leaflet map container with sizing-->
<div id="map_results" style="width: 99%; margin-top:0px; margin-bottom:0px; height: <?php echo $map1_height; ?>px; display:block; border:1px solid black; float:none; overflow: hidden;"></div>

<script type="text/javascript">
    if (typeof map1 !== 'undefined') {
        map1.remove();
    }
    // Setup and define the Leaflet map with the initial view using leaflet.js and L.Control.Zoomslider.js
    <?php set_geo_map_centerview(); ?>
    var map1 = new L.map('map_results', {
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
        group_maxHeight: '180px',
        exclusive: false
    };

    // Limit geocoordinate values to six decimal places for display on marker hover
    function georound(num) {
        return +(Math.round(num + "e+6") + "e-6");
    }

    var control = L.Control.styledLayerControl(baseMaps,options);
    map1.addControl(control);

    // Show zoom history navigation bar and add to Leaflet map using Leaflet.NavBar.min.js
    <?php if ($map_zoomnavbar) { ?>
        L.control.navbar().addTo(map1); <?php
    } ?>

    // Add a scale bar to the Leaflet map using leaflet.min.js
    new L.control.scale().addTo(map1);

    // Add a KML overlay to the Leaflet map using leaflet-omnivore.min.js
    <?php if ($map_kml) { ?>
        omnivore.kml('<?php echo $baseurl?>/filestore/system/<?php echo $map_kml_file?>').addTo(map1); <?php
    } ?>

    // If no data (markers), only show the empty Leaflet map
    <?php if (!empty($geomarker)) { ?>
        // Setup and configure initial marker info from resource data
        var geomarker = <?php echo str_replace(array('"', '\\'), '', json_encode($geomarker))?>;
        var previewPaths = <?php echo json_encode($preview_paths); ?>;
        var markerArray = [];
        var win_url;

        // Setup marker clustering using leaflet.markercluster.js for many overlapping markers common in low zoom levels
        var markers = L.markerClusterGroup({
            maxClusterRadius: 75,
            disableClusteringAtZoom: 14,
            chunkedLoading: true, // Load markers in chunks to avoid slow browser response
            elementsPlacementStrategy: 'original-locations' // Cluster items placement strategy
        });

        // Cycle through the resources to create markers as needed and colored by resource type
        for (var i = 0; i < geomarker.length; i++) {
            var lon = geomarker[i][0]; // Resource longitude value
            var lat = geomarker[i][1]; // Resource latitude value
            var rf = geomarker[i][2]; // Resource reference value
            var rtype = geomarker[i][3]; // Resource type
            var cmfm = geomarker[i][4]; // Custom metadata field marker coloring
            var preview = previewPaths[i]; // Resource preview image path

            // Check for resources without geolocation or invalid coordinates and skip those
            if (lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
                <?php
                // Check if using a custom metadata field for coloring the markers and redefine rtype.
                if (isset($marker_metadata_field) && is_numeric($marker_metadata_field)) {
                    // Clear the resource type already set
                    echo "rtype = 0;\n";
                    for ($i = 0; $i < count($marker_metadata_array); $i++) {
                        echo "\n";?>
                        if (cmfm >= <?php echo $marker_metadata_array[$i]['min']; ?> && cmfm <= <?php echo $marker_metadata_array[$i]['max']; ?>) {                            
                            rtype = <?php echo $i; ?>;
                        }
                        <?php
                    }
                } ?>

                // Set each resource marker color based on resource type or metadata field to marker color mapping
                switch(rtype) {
                    <?php
                    if (!isset($marker_metadata_field)) {
                        $maprestypes = get_resource_types();
                        foreach ($maprestypes as $maprestype) {
                            $markercolour = (isset($maprestype["colour"]) && $maprestype["colour"] != "" && $maprestype["colour"] > -1 && $maprestype["colour"] < count($MARKER_COLORS)) ? (int)$maprestype["colour"] : floor(($maprestype["ref"] % count($MARKER_COLORS)));
                            echo "\n    case " . $maprestype["ref"] . ":\n";
                            echo "        iconColor = "  . strtolower($MARKER_COLORS[$markercolour])  . "Icon;\n";
                            echo "        break;\n";
                        } ?>
                        default:
                            iconColor = blackIcon;
                        <?php
                    } else {
                        for ($i = 0; $i < count($marker_metadata_array); $i++) {
                            echo "\n    case " . $i . ":\n";
                            echo "        iconColor = "  . strtolower($MARKER_COLORS[$i])  . "Icon;\n";
                            echo "        break;\n";
                        }
                        ?>
                        default:
                            iconColor = blackIcon;
                        <?php
                    }
                    ?>
                }

                // Define the marker arrays for the markers, zoom to the markers, and marker click function using leaflet.js and leaflet-color-markers.js
                // Create a marker for each resource for map zoom to the markers
                markerArray.push(new L.marker([lat, lon], {
                    opacity: 0
                }).addTo(map1));

                // Create a marker for each resource
                <?php if ($marker_resource_preview) { ?>
                    var marker = new L.marker([lat, lon], {
                        icon: iconColor,
                        riseOnHover: true,
                        win_url: geomarker[i][2],
                        title: georound(lat) + ", " + georound(lon) + " (WGS84)"
                    }); 
                    
                    // Show the resource preview image
                    var imagePath = "<img src='" + preview + "'/>";
                    var text1 = "<?php echo escape($lang['resourceid']); ?>";
                    var imageLink = '<a href=' + baseurl + '/pages/view.php?ref=' + rf + " target='_blank'" + '  onclick="return ModalLoad(this,true);">' + '<img src=' + preview + '>' + '</a>';
                    marker.bindPopup(imageLink + text1 + " " + rf + "<br>" + georound(lat) + ", " + georound(lon), {
                        minWidth: 175,
                        autoPan: true,
                        autoPanPaddingTopLeft: 5,
                        autoPanPaddingBottomRight: 5
                    });
                <?php } else { // Show resource ID in marker tooltip. ?>
                    var marker = new L.marker([lat, lon], {
                        icon: iconColor,
                        title: 'ID# ' + rf,
                        riseOnHover: true,
                        win_url: geomarker[i][2]
                    }).on('click', showModal); <?php
                } ?>

                // Add markers to the layer array
                markers.addLayer(marker);
            }
        }

        // Add the markers layer to the map
        map1.addLayer(markers);

        jQuery(document).ready(function() {
            // Zoom to the markers on the map regardless of the initial view
            var group = L.featureGroup(markerArray);
            map1.fitBounds(group.getBounds().pad(0.25));
        });
        
        // On marker click, open a modal corresponding to the specific resource
        function showModal(e) {
            ModalLoad(baseurl + '/pages/view.php?ref=' + this.options.win_url);
        }

        // Fix for Microsoft Edge and Internet Explorer browsers
        map1.invalidateSize(true);
    <?php } ?>
</script>

<!--Create a map marker legend below the map and only show for defined types up to eight.-->
<p style="margin-top:4px;margin-bottom:0px;">
    <?php leaflet_markers_legend(); ?>
</p>
