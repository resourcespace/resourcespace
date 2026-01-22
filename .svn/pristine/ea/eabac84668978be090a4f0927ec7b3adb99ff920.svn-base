<?php
// Resource View Leaflet Map Using Leaflet.js and Various Leaflet Plugins

// Setup initial Leaflet map variables.
global $lang, $baseurl, $baseurl_short, $view_mapheight, $map_default, $map_zoomslider, $map_zoomnavbar, $map_kml, $map, $map_kml_file, $map_retina, $map_polygon_field, $modal, $fields;
$zoomslider = 'false';
$zoomcontrol = 'true';
$polygon = '';
$modal = (getval("modal", "") == 'true');

// Set Leaflet map search view height and layer control container height based on $mapheight.
if (isset($view_mapheight)) {
    $map1_height = $view_mapheight;
    $layer_controlheight = $view_mapheight - 40;
} else // Default values.
    {
    $map1_height = 300;
    $layer_controlheight = 250;
}

// Show zoom slider instead of default Leaflet zoom control?
if ($map_zoomslider) {
    $zoomslider = 'true';
    $zoomcontrol = 'false';
}

if ($hide_geolocation_panel && !isset($geolocation_panel_only)) { ?>
    <script>
        function ShowGeolocation()
            {
            if(!jQuery("#GeolocationData").length){
                jQuery.ajax({
                    type:"GET",
                    url: '<?php echo $baseurl_short?>pages/ajax/geolocation_loader.php?ref=<?php echo urlencode($ref)?>&k=<?php echo urlencode($k)?>',
                    success: function(data){
                        jQuery("#GeolocationHideLink").after(data);
                        }
                    });
                }

            jQuery("#GeolocationData").slideDown();
            jQuery("#GeolocationHideLink").show();
            jQuery("#GeolocationShowLink").hide();
            }
        function HideGeolocation()
            {
            jQuery("#GeolocationData").slideUp();
            jQuery("#GeolocationShowLink").show();
            jQuery("#GeolocationHideLink").hide();
            }
    </script> <?php
}

// Begin geolocation section.
if (!isset($geolocation_panel_only)) { ?>
    <div class="RecordBox">
    <div class="RecordPanel"> <?php

    if ($hide_geolocation_panel) { ?>
        <div id="GeolocationShowLink" class="CollapsibleSection" ><?php echo "<a href=\"javascript: void(0)\" onClick=\"ShowGeolocation();\">&#x25B8;&nbsp;" . escape($lang['showgeolocationpanel']) . "</a>";?></div>
        <div id="GeolocationHideLink" class="CollapsibleSection" style="display:none"><?php echo "<a href=\"javascript: void(0)\" onClick=\"HideGeolocation();return false;\">&#x25BE;&nbsp;" . escape($lang['hidegeolocationpanel']) . "</a>";?></div> <?php
    }
}

if (!$hide_geolocation_panel || isset($geolocation_panel_only)) { ?>
    <div id="GeolocationData">
    <div class="Title"><?php echo escape($lang['location-title']); ?></div>
    <?php

    if ($resource['geo_lat'] != '' && $resource['geo_long'] != '') { ?>
        <?php if ($edit_access) { ?>
            <p>
                <?php echo LINK_CARET ?><a href="<?php echo $baseurl_short?>pages/geo_edit.php?ref=<?php echo urlencode($ref); ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang['location-edit']); ?></a>
            </p>
            <?php
        }
        $zoom = leaflet_map_zoom($resource['mapzoom']);

        // Check for modal view.
        if (!$modal) {
            $map_container      = 'map_id';
            $map_container_obj  = "map_obj";
        } else {
            $map_container      = 'map_id_modal';
            $map_container_obj  = "map_modal_obj";
        }
        ?>
    <!--Setup Leaflet map container with sizing-->
    <div id="<?php echo $map_container; ?>" style="width: 99%; margin-top:0px; margin-bottom:0px; height: <?php echo $map1_height;?>px; display:block; border:1px solid black; float:none; overflow: hidden;">
    </div>

    <script type="text/javascript">

        // Define available Leaflet basemaps groups and layers using leaflet.providers.js, L.TileLayer.PouchDBCached.js, and styledLayerControl.js based on ../include/map_functions.php.
        <?php include __DIR__ . '/map_processing.php'; ?>
        <!--Determine basemaps and map groups for user selection-->
        <?php include __DIR__ . '/map_basemaps.php'; ?>        

        jQuery(document).ready(function ()
            {
            // Setup and define the Leaflet map with the initial view using leaflet.js and L.Control.Zoomslider.js
            var <?php echo $map_container_obj; ?>_geo_lat = <?php echo $resource['geo_lat']; ?>;
            var <?php echo $map_container_obj; ?>_geo_long = <?php echo $resource['geo_long']; ?>;
            var <?php echo $map_container_obj; ?>_zoom = <?php echo $zoom; ?>;
        
            if (typeof <?php echo $map_container_obj; ?> !== "undefined")
                {
                <?php echo $map_container_obj; ?>.remove();
                }
            var <?php echo $map_container_obj; ?> = new L.map(<?php echo $map_container; ?>, {
            preferCanvas: true,
                renderer: L.canvas(),
                zoomsliderControl: <?php echo $zoomslider?>,
                zoomControl: <?php echo $zoomcontrol?>
                }).setView([<?php echo $map_container_obj; ?>_geo_lat, <?php echo $map_container_obj; ?>_geo_long], <?php echo $map_container_obj; ?>_zoom);
            <?php echo $map_container_obj; ?>.invalidateSize(); 

            // Define default Leaflet basemap layer using leaflet.js, leaflet.providers.js, and L.TileLayer.PouchDBCached.js
    
            var defaultLayer = new L.tileLayer.provider('<?php echo $map_default;?>', {
                useCache: '<?php echo $map_default_cache;?>', <!--Use browser caching of tiles (recommended)?-->
                detectRetina: '<?php echo $map_retina;?>', <!--Use retina high resolution map tiles?-->
                attribution: default_attribute
            }).addTo(<?php echo $map_container_obj; ?>);
            <?php echo $map_container_obj; ?>.invalidateSize(true);

            // Set styled layer control options for basemaps and add to the Leaflet map using styledLayerControl.js
            var options = {
                container_maxHeight: "<?php echo $layer_controlheight?>px",
                group_maxHeight: "180px",
                exclusive: false
            };

            var control = L.Control.styledLayerControl(baseMaps,options);
            <?php echo $map_container_obj; ?>.addControl(control);

            // Show zoom history navigation bar and add to Leaflet map using Leaflet.NavBar.min.js
            <?php if ($map_zoomnavbar && $view_mapheight >= 400) { ?>
                L.control.navbar().addTo(<?php echo $map_container_obj; ?>); <?php
            } ?>

            // Add a scale bar to the Leaflet map using leaflet.min.js
            new L.control.scale().addTo(<?php echo $map_container_obj; ?>);

            // Add a KML overlay to the Leaflet map using leaflet-omnivore.min.js
                <?php if ($map_kml) { ?>
                omnivore.kml('<?php echo $baseurl?>/filestore/system/<?php echo $map_kml_file?>').addTo(<?php echo $map_container_obj; ?>); <?php
                } ?>

            // Limit geocoordinate values to six decimal places for display on marker hover
            function georound(num) {
                return +(Math.round(num + "e+6") + "e-6");
            }

            // Add a marker for the resource
            L.marker([<?php echo $map_container_obj; ?>_geo_lat, <?php echo $map_container_obj; ?>_geo_long], {
                    <?php
                    $maprestype = get_resource_types($resource['resource_type']);
                    $markercolour = (isset($maprestype[0]) && isset($MARKER_COLORS[$maprestype[0]["colour"]])) ? (int)$maprestype[0]["colour"] : ($resource['resource_type'] % count($MARKER_COLORS));
                    echo "icon: " . strtolower($MARKER_COLORS[$markercolour])  . "Icon,\n";
                    ?>
                title: georound(<?php echo $map_container_obj; ?>_geo_lat) + ", " + georound(<?php echo $map_container_obj; ?>_geo_long) + " (WGS84)"
            }).addTo(<?php echo $map_container_obj; ?>);

            // Add the resource footprint polygon to the map and pan/zoom to the polygon
                <?php if (is_numeric($map_polygon_field)) {
                    $polygon = leaflet_polygon_parsing($fields, false);
                    if (!is_null($polygon['values']) && $polygon['values'] != "" && $polygon['values'] != "[]") { ?>
                    var refPolygon = L.polygon([<?php echo $polygon['values']; ?>]).addTo(<?php echo $map_container_obj; ?>);
                        <?php echo $map_container_obj; ?>.fitBounds(refPolygon.getBounds(), {
                        padding: [25, 25]
                    }); <?php
                    }
                } else // Pan to the marker location.
                { ?>
                    <?php echo $map_container_obj; ?>.setView([<?php echo $map_container_obj; ?>_geo_lat, <?php echo $map_container_obj; ?>_geo_long], <?php echo $map_container_obj; ?>_zoom); <?php
                }

                ?>
            // Fix for Microsoft Edge and Internet Explorer browsers
                <?php echo $map_container_obj; ?>.invalidateSize(true);
            });

        </script>

        <!--Show resource geolocation value-->
        <div id="resource_coordinate" style="margin-top:0px; margin-bottom:0px; width: 99%;">
            <p> <?php echo escape($lang['marker'] . ' ' . strtolower($lang['latlong'])) . ': ';
            echo round($resource['geo_lat'], 6) . ', ';
            echo round($resource['geo_long'], 6) . ' (WGS84)'; ?> </p>
        </div>
            <?php
    } else { ?>
        <a href="<?php echo $baseurl_short?>pages/geo_edit.php?ref=<?php echo urlencode($ref); ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_PLUS ?><?php echo escape($lang['location-add']);?></a> <?php
    }

    if ($view_panels) { ?>
    <script>
    jQuery(document).ready(function ()
        {
        let parent_element = jQuery('#<?php echo $modal ? 'modal' : 'CentralSpace' ?>');
        parent_element.find("#GeolocationData").children(".Title").attr("panel", "GeolocationData").appendTo(parent_element.find("#Titles1"));
        removePanel = parent_element.find("#GeolocationData").parent().parent(".RecordBox");
        parent_element.find("#GeolocationData").appendTo(parent_element.find("#Panel1")).addClass("TabPanel").hide();
        removePanel.remove();

        //Function to switch tab panels
        jQuery('.ViewPanelTitles').children('.Title').click(function()
            {
            parent_element.find(this).parent().parent().children('.TabPanel').hide();
            parent_element.find(this).parent().children('.Title').removeClass('Selected');
            parent_element.find(this).addClass('Selected');
            parent_element.find('#' + jQuery(this).attr('panel')).show();
            <?php if (isset($map_container_obj)) {
                echo $map_container_obj ?? "" . ".invalidateSize(true);";
            } ?>
            });
        });
        </script> <?php
    } ?>
    </div> <?php
}

if (!isset($geolocation_panel_only)) { ?>
    </div> <!--End of RecordPanel-->
    </div> <!--End of RecordBox--> <?php
}
