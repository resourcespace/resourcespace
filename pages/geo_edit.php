<?php
// Resource Map Geolocation Edit Using Leaflet.js and Various Leaflet Plugins

// Check if geolocation/maps have been disabled.
global $disable_geocoding, $lang;

if ($disable_geocoding) {
    header('HTTP/1.1 403 Forbidden');
    exit($lang['error-geocodingdisabled']);
}

include '../include/boot.php';
include '../include/authenticate.php';
include '../include/header.php';

// Setup initial map variables.
global $default_display, $baseurl, $mapsearch_height, $map_default, $map_zoomslider, $map_zoomnavbar, $map_kml, $map_kml_file, $map_default_cache, $map_layer_cache, $map_retina, $mapedit_mapheight, $layer_controlheight;
$zoomslider = 'false';
$zoomcontrol = 'true';

// Show zoom slider instead of default Leaflet zoom control?
if ($map_zoomslider) {
    $zoomslider = 'true';
    $zoomcontrol = 'false';
}

// Fetch the resource data.
$ref = getval('ref', 0, true);

// See if we came from the ../pages/geolocate_collection.php page.
$geocol = getval('geocol', '', true);
$resource = get_resource_data($ref);

if (!$resource) {
    $onload_message = array("title" => $lang["error"],"text" => $lang['resourcenotfound']);
    include "../include/footer.php";
    exit();
}

// Check if the user is allowed to edit this resource.
if (!get_edit_access($ref, $resource['archive'], $resource)) {
    exit($lang['error-permissiondenied']);
}

// Update database with geolocation.
$valid_coords = true;

if (isset($_POST['submit']) && enforcePostRequest(false)) {
    $s = explode(",", getval('geo-loc', ''));

    $lat = isset($s[0]) ? $s[0] : "";
    $lng = isset($s[1]) ? $s[1] : "";

    if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
        $valid_coords = false;
    }

    if (!is_numeric($lng) || $lng < -180 || $lng > 180) {
        $valid_coords = false;
    }

    if (count($s) == 2 && $valid_coords) {
        $mapzoom = getval('map-zoom', '');
        if ($mapzoom >= 2 && $mapzoom <= 21) {
            ps_query("update resource set geo_lat= ?,geo_long= ?,mapzoom= ? where ref= ?", ['d', $s[0], 'd', $s[1], 'i', $mapzoom, 'i', $ref]);
        } else {
            ps_query("update resource set geo_lat= ?,geo_long= ?,mapzoom=null where ref=?", ['d', $s[0], 'd', $s[1],'i', $ref]);
        }

        if ($resource["geo_lat"] != "" && $resource["geo_long"] != "") {
            $old_location = $resource["geo_lat"] . ", " . $resource["geo_long"];
        }

        resource_log(
            $ref,
            LOG_CODE_EDITED_RESOURCE,
            null,
            "Edited Location",
            $old_location ?? "",
            $lat . ", " . $lng
        );
    } elseif (getval('geo-loc', '') == '') {
        # Blank geo-location
        ps_query("update resource set geo_lat=null,geo_long=null,mapzoom=null where ref= ?", ['i', $ref]);
        resource_log(
            $ref,
            LOG_CODE_EDITED_RESOURCE,
            null,
            "Removed Location",
            $resource["geo_lat"] . ", " . $resource["geo_long"],
            ""
        );
    }
    # Reload resource data
    $resource = get_resource_data($ref, false);
}

$zoom = getval('new_zoom', $resource["mapzoom"]);

if (!$valid_coords && getval('geo-loc', '') != '') {
    echo "<p class='FormIncorrect'>" . escape($lang['location-validation-error'])  . "</p>";
}
?>

<div class="RecordBox">
    <div class="RecordPanel">
        <div class="Title">
            <?php
            echo escape($lang['location-title']);
            render_help_link("user/geolocation");
            ?>
        </div>

        <p>
            <a
                onclick="return CentralSpaceLoad(this,true);"
                href="<?php echo $baseurl_short . ($geocol != '' ? "pages/geolocate_collection.php?ref=" . $geocol : "pages/view.php?ref=" . $ref) ?>"
            >
                <?php echo LINK_CARET_BACK . escape($geocol != '' ? $lang['backtogeolocatecollection'] : $lang['backtoresourceview']); ?>
            </a>
        </p>
        <!--Setup Leaflet map container with sizing-->
        <div id="map_edit" style="width: 99%; margin-top:0px; margin-bottom:0px; height: <?php echo $mapedit_mapheight;?>px; display:block; border:1px solid black; float:none; overflow: hidden;">
        </div>

        <script>
            // Setup and define the Leaflet map with the initial view using leaflet.js and L.Control.Zoomslider.js
            <?php set_geo_map_centerview(); ?>
            var map2 = new L.map('map_edit', {
                renderer: L.canvas(),
                zoomsliderControl: <?php echo $zoomslider?>,
                zoomControl: <?php echo $zoomcontrol?>,
                worldCopyJump: true
            }).setView(mapcenterview,mapdefaultzoom);

            map2.on('baselayerchange', function (e) {
                currentLayerID = e.layer._leaflet_id;
                SetCookie('geo_layer', e.layer.options.name);
            });

            // Load available Leaflet basemap groups, layers, and attribute definitions.
            <?php include '../include/map_processing.php'; ?>

            // Define default Leaflet basemap layer using leaflet.js, leaflet.providers.js, and L.TileLayer.PouchDBCached.js
            var defaultLayer = new L.tileLayer.provider('<?php echo $map_default;?>', {
                useCache: '<?php echo $map_default_cache;?>', // Use browser caching of tiles (recommended)?
                detectRetina: '<?php echo $map_retina;?>', // Use retina high resolution map tiles?
                attribution: default_attribute
            }).addTo(map2);

            // Load Leaflet basemap definitions.
            <?php include '../include/map_basemaps.php';
            // Get the resource type to determine the icon to use
            $maprestype = get_resource_types($resource['resource_type']);
            $markercolour = (isset($maprestype[0]) && isset($MARKER_COLORS[$maprestype[0]["colour"]])) ? (int)$maprestype[0]["colour"] : ($resource['resource_type'] % count($MARKER_COLORS));
            $markercolourjs =  strtolower($MARKER_COLORS[$markercolour])  . "Icon";
            ?>

            // Set styled layer control options for basemaps and add to the Leaflet map using styledLayerControl.js
            var options = {
                container_maxHeight: '<?php echo $layer_controlheight?>px',
                group_maxHeight: '180px',
                exclusive: false
            };

            var control = L.Control.styledLayerControl(baseMaps,options);
            map2.addControl(control);

            // Add geocoder search bar using control.geocoder.min.js
            L.Control.geocoder().addTo(map2);

            // Show zoom history navigation bar and add to Leaflet map using Leaflet.NavBar.min.js
            <?php if ($map_zoomnavbar) { ?>
                L.control.navbar().addTo(map2);
            <?php } ?>

            // Add a scale bar to the Leaflet map using leaflet.min.js
            new L.control.scale().addTo(map2);
            
            // Add a KML overlay to the Leaflet map using leaflet-omnivore.min.js
            <?php if ($map_kml) { ?>
                omnivore.kml('<?php echo $baseurl?>/filestore/system/<?php echo $map_kml_file?>').addTo(map1);
            <?php } ?>

            // Fix for Microsoft Edge and Internet Explorer browsers
            map2.invalidateSize(true);

            // Limit geocoordinate values to six decimal places for display on marker hover
            function georound(num) {
                return +(Math.round(num + "e+6") + "e-6");
            }

            // Add a marker to the map if the resource has valid coordinates
            var resourceMarker = {};
            <?php
            if (
                leaflet_coordinate_check($resource['geo_lat'], 'latitude')
                && leaflet_coordinate_check($resource['geo_long'], 'longitude')
            ) {
                $resourcezoom = leaflet_map_zoom($resource['mapzoom']); ?>
                resourceLat = <?php echo escape($resource['geo_lat']); ?>;
                resourceLong = <?php echo escape($resource['geo_long']); ?>;
                resourceZoom = <?php echo $resourcezoom; ?>;

                resourceMarker = L.marker([resourceLat, resourceLong], {
                    icon: <?php echo $markercolourjs; ?>,
                    title: georound(resourceLat) + ", " + georound(resourceLong) + " (WGS84)"
                }).addTo(map2);
                map2.setView([resourceLat, resourceLong], resourceZoom);
                <?php
            } ?>

            // Place a marker on the map when clicked
            map2.on('click', function(e) {
                currentZoom = map2.getZoom();
                console.log('Zoom: ' + currentZoom);
                realpoint = map2.wrapLatLng(e.latlng);
                geoLat = realpoint.lat;
                geoLong = realpoint.lng;

                // Clear existing marker when locating a new marker as we only want one marker for the resource
                if (resourceMarker != undefined) {
                    map2.removeLayer(resourceMarker);
                };

                // Add a marker to show where you clicked on the map last and center the map on the marker
                resourceMarker = L.marker([geoLat, geoLong], {
                    icon: <?php echo $markercolourjs; ?>,
                    title: georound(geoLat) + ", " + georound(geoLong) + " (WGS84)"
                }).addTo(map2);

                // Set the resource marker geolocation value
                document.getElementById('map-input').value = georound(geoLat) + ', ' + georound(geoLong);
                document.getElementById('map-zoom').value = currentZoom;
                map2.setView([geoLat, geoLong], currentZoom);
                        
                <?php if ($edit_autosave) { ?>
                    jQuery.ajax({
                        type: "POST",
                        url: "<?php echo $baseurl_short; ?>pages/geo_edit.php",
                        dataType: "text",
                        data: {
                            'submit': 'true',
                            'ajax': 'true',
                            'ref': '<?php echo $ref ; ?>',
                            'geo-loc': geoLat + ',' + geoLong,
                            'map-zoom': currentZoom,
                            csrf_identifier: '<?php echo $CSRF_token_identifier; ?>',
                            <?php echo generateAjaxToken('geo_edit'); ?>,
                        }
                    });
                <?php } ?>               
            });

            map2.on('zoomend', function() {   
                currentZoom = map2.getZoom();     
                console.debug('Zoom: ' + currentZoom);
                document.getElementById('map-zoom').value = currentZoom;
            });
        </script>

        <!--Resource marker latitude and longitude form-->
        <form id="map-form" method="post" action="<?php echo $baseurl_short?>pages/geo_edit.php">
            <?php generateFormToken("map-form"); ?>
            <input name="ref" type="hidden" value="<?php echo $ref; ?>" />
            <input name="submit" type="hidden" value="true" />
            <input name="geocol" type="hidden" value="<?php echo escape($geocol); ?>" />
            <input name="map-zoom" type="hidden" value="<?php echo escape($zoom ?? ""); ?>" id="map-zoom" />
            <?php echo escape($lang['marker'] . " " . strtolower($lang['latlong'])); ?>: <input name="geo-loc" type="text" size="50" value="<?php echo $resource['geo_long'] == "" ? "" : escape($resource['geo_lat'] . ',' . $resource['geo_long']) ?>" id="map-input" />
            <input name="submit" type="submit" value="<?php echo escape($lang['save']); ?>" onclick="return CentralSpacePost(this.form,true);" />
        </form>
    </div>
</div>

<?php
include '../include/footer.php';