<?php
// Leaflet.js Basemap Selections

if ($geo_leaflet_maps_sources) {
    ?>
    <!--Determine basemaps and map groups for user selection-->
    var baseMaps = [
        { groupName: "<?php echo escape($lang['map_osm_group']);?>", <!--OSM group-->
            expanded: true,
            layers: {
                <?php if (isset($map_osm) && $map_osm) {
                    ?> "<?php echo escape($lang['map_osm']);?>" : osm_mapnik, <?php
                } ?>
                <?php if (isset($map_osmde) && $map_osmde) {
                    ?> "<?php echo escape($lang['map_osmde']);?>" : osm_de, <?php
                } ?>
                <?php if (isset($map_osmfr) && $map_osmfr) {
                    ?> "<?php echo escape($lang['map_osmfr']);?>" : osm_fr, <?php
                } ?>
                <?php if (isset($map_osmbzh) && $map_osmbzh) {
                    ?> "<?php echo escape($lang['map_osmbzh']);?>" : osm_bzh, <?php
                } ?>
                <?php if (isset($map_osmhot) && $map_osmhot) {
                    ?> "<?php echo escape($lang['map_osmhot']);?>" : osm_hot, <?php
                } ?>
                <?php if (isset($map_osmmtb) && $map_osmmtb) {
                    ?> "<?php echo escape($lang['map_osmmtb']);?>" : osm_mtb, <?php
                } ?>
                <?php if (isset($map_osmhikebike) && $map_osmhikebike) {
                    ?> "<?php echo escape($lang['map_osmhikebike']);?>" : osm_hikebike, <?php
                } ?>
                <?php if (isset($map_otm) && $map_otm) {
                    ?> "<?php echo escape($lang['map_otm']);?>" : osm_otm, <?php
                } ?>
            }
        },

        { groupName: "<?php echo escape($lang['map_usgs_group']);?>", <!--USGS The National Map group-->
            expanded: true,
            layers: {
                <?php if (isset($map_usgstopo) && $map_usgstopo) {
                    ?> "<?php echo escape($lang['map_usgstopo']);?>" : usgs_topo, <?php
                } ?>
                <?php if (isset($map_usgsimagery) && $map_usgsimagery) {
                    ?> "<?php echo escape($lang['map_usgsimagery']);?>" : usgs_imagery, <?php
                } ?>
                <?php if (isset($map_usgsimagerytopo) && $map_usgsimagerytopo) {
                    ?> "<?php echo escape($lang['map_usgsimagerytopo']);?>" : usgs_imagerytopo <?php
                } ?>
            }
        },

        { groupName: "<?php echo escape($lang['map_esri_group']);?>", <!--ESRI group-->
            expanded: true,
            layers: {
                <?php if (isset($map_esristreet) && $map_esristreet) {
                    ?> "<?php echo escape($lang['map_esristreet']);?>" : esri_street, <?php
                } ?>
                <?php if (isset($map_esridelorme) && $map_esridelorme) {
                    ?> "<?php echo escape($lang['map_esridelorme']);?>" : esri_delorme, <?php
                } ?>
                <?php if (isset($map_esritopo) && $map_esritopo) {
                    ?> "<?php echo escape($lang['map_esritopo']);?>" : esri_topo, <?php
                } ?>
                <?php if (isset($map_esriimagery) && $map_esriimagery) {
                    ?> "<?php echo escape($lang['map_esriimagery']);?>" : esri_imagery, <?php
                } ?>
                <?php if (isset($map_esriterrain) && $map_esriterrain) {
                    ?> "<?php echo escape($lang['map_esriterrain']);?>" : esri_terrain, <?php
                } ?>
                <?php if (isset($map_esrirelief) && $map_esrirelief) {
                    ?> "<?php echo escape($lang['map_esrirelief']);?>" : esri_relief, <?php
                } ?>
                <?php if (isset($map_esriphysical) && $map_esriphysical) {
                    ?> "<?php echo escape($lang['map_esriphysical']);?>" : esri_physical, <?php
                } ?>
                <?php if (isset($map_esriocean) && $map_esriocean) {
                    ?> "<?php echo escape($lang['map_esriocean']);?>" : esri_ocean, <?php
                } ?>
                <?php if (isset($map_esrinatgeo) && $map_esrinatgeo) {
                    ?> "<?php echo escape($lang['map_esrinatgeo']);?>" : esri_natgeo, <?php
                } ?>
                <?php if (isset($map_esrigray) && $map_esrigray) {
                    ?> "<?php echo escape($lang['map_esrigray']);?>" : esri_gray <?php
                } ?>
            }
        },

        { groupName: "<?php echo escape($lang['map_stamen_group']);?>", <!--Stamen group-->
            expanded: true,
            layers: {
                <?php if (isset($map_stamentoner) && $map_stamentoner) {
                    ?> "<?php echo escape($lang['map_stamentoner']);?>" : stamen_toner, <?php
                } ?>
                <?php if (isset($map_stamentonerlt) && $map_stamentonerlt) {
                    ?> "<?php echo escape($lang['map_stamentonerlt']);?>" : stamen_tonerlt, <?php
                } ?>
                <?php if (isset($map_stamentonerback) && $map_stamentonerback) {
                    ?> "<?php echo escape($lang['map_stamentonerback']);?>" : stamen_tonerback, <?php
                } ?>
                <?php if (isset($map_stamenterrain) && $map_stamenterrain) {
                    ?> "<?php echo escape($lang['map_stamenterrain']);?>" : stamen_terrain, <?php
                } ?>
                <?php if (isset($map_stamenterrainback) && $map_stamenterrainback) {
                    ?> "<?php echo escape($lang['map_stamenterrainback']);?>" : stamen_terrainback, <?php
                } ?>
                <?php if (isset($map_stamenrelief) && $map_stamenrelief) {
                    ?> "<?php echo escape($lang['map_stamenrelief']);?>" : stamen_relief, <?php
                } ?>
                <?php if (isset($map_stamenwatercolor) && $map_stamenwatercolor) {
                    ?> "<?php echo escape($lang['map_stamenwatercolor']);?>" : stamen_watercolor <?php
                } ?>
            }
        },

        { groupName: "<?php echo escape($lang['map_hydda_group']);?>", <!--Hydda group-->
            expanded: true,
            layers: {
                <?php if (isset($map_hyddafull) && $map_hyddafull) {
                    ?> "<?php echo escape($lang['map_hyddafull']);?>" : hydda_full, <?php
                } ?>
                <?php if (isset($map_hyddabase) && $map_hyddabase) {
                    ?> "<?php echo escape($lang['map_hyddabase']);?>" : hydda_base <?php
                } ?>
            }
        },

        { groupName: "<?php echo escape($lang['map_nasagibs_group']);?>", <!--NASA GIBS group-->
            expanded: true,
            layers: {
                <?php if (isset($map_nasagibscolor) && $map_nasagibscolor) {
                    ?> "<?php echo escape($lang['map_nasagibscolor']);?>" : nasa_gibscolor, <?php
                } ?>
                <?php if (isset($map_nasagibsfalsecolor) && $map_nasagibsfalsecolor) {
                    ?> "<?php echo escape($lang['map_nasagibsfalsecolor']);?>" : nasa_gibsfalsecolor, <?php
                } ?>
                <?php if (isset($map_nasagibsnight) && $map_nasagibsnight) {
                    ?> "<?php echo escape($lang['map_nasagibsnight']);?>" : nasa_gibsnight <?php
                } ?>
            }
        },

        { groupName: "<?php echo escape($lang['map_tf_group']);?>", <!--Thunderforest group-->
            expanded: true,
            layers: {
                <?php if (isset($map_tfocm) && $map_tfocm) {
                    ?> "<?php echo escape($lang['map_tfocm']);?>" : tf_ocm, <?php
                } ?>
                <?php if (isset($map_tftransport) && $map_tftransport) {
                    ?> "<?php echo escape($lang['map_tftransport']);?>" : tf_transport, <?php
                } ?>
                <?php if (isset($map_tftransportdark) && $map_tftransportdark) {
                    ?> "<?php echo escape($lang['map_tftransportdark']);?>" : tf_transportdark, <?php
                } ?>
                <?php if (isset($map_tflandscape) && $map_tflandscape) {
                    ?> "<?php echo escape($lang['map_tflandscape']);?>" : tf_landscape, <?php
                } ?>
                <?php if (isset($map_tfoutdoors) && $map_tfoutdoors) {
                    ?> "<?php echo escape($lang['map_tfoutdoors']);?>" : tf_outdoors, <?php
                } ?>
                <?php if (isset($map_tfpioneer) && $map_tfpioneer) {
                    ?> "<?php echo escape($lang['map_tfpioneer']);?>" : tf_pioneer, <?php
                } ?>
                <?php if (isset($map_tfmobileatlas) && $map_tfmobileatlas) {
                    ?> "<?php echo escape($lang['map_tfmobileatlas']);?>" : tf_mobileatlas, <?php
                } ?>
                <?php if (isset($map_tfneighbourhoodX) && $map_tfneighbourhood) {
                    ?> "<?php echo escape($lang['map_tfneighbourhood']);?>" : tf_neighbourhood <?php
                } ?>
            }
        },

        { groupName: "<?php echo escape($lang['map_mapbox_group']);?>", <!--Mapbox group-->
            expanded: true,
            layers: {
                <?php if (isset($map_mapbox) && $map_mapbox) {
                    ?> "<?php echo escape($lang['map_mapbox']);?>" : mapbox <?php
                } ?>
            }
        }
    ];
    <?php
} else {
    ?>
    var baseMaps = [
    <?php
    foreach ($geo_leaflet_sources as $leaflet_source) {
        echo "{ groupName: \"" .  escape(i18n_get_translated($leaflet_source["name"])) . "\",
            expanded: true,
            layers: {\n";
        foreach ($leaflet_source["variants"] as $variant => $varopts) {
            $variantname =  isset($varopts["name"]) ? $varopts["name"] : $leaflet_source["name"];
            echo "\"" . $variantname . "\" : " . mb_strtolower($leaflet_source["code"] . "_" . $variant) . ",\n";
        }
        echo "}},\n";
    }

    echo "];\n";
}
