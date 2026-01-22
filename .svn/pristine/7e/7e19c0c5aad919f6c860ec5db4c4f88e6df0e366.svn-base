<?php

// Global everything we need, in case called inside a function (e.g. for push_metadata support)
global $k,$lang,$show_resourceid,$show_access_field,$show_resource_type,$show_hitcount, $resource_hit_count_on_downloads,
       $show_contributed_by,$baseurl_short,$search,$enable_related_resources,$modal,
       $sort_tabs, $arr_fieldrestypes, $display_check_data;

// Is this a modal?
$modal = (getval("modal", "") == "true");

// Check display conditions here to save checking in each loop
$displaycheck = [];

$display_check_data = $fields_all;
for ($i = 0; $i < count($fields); $i++) {
    $displaycheck[$fields[$i]["ref"]] = check_display_condition($i, $fields[$i], $fields_all, false, $resource['ref']) ? true : false;
}

// -----------------------  Tab calculation -----------------
$disable_tabs = true;
$system_tabs = get_tab_name_options();
debug(sprintf('$system_tabs = %s', json_encode($system_tabs)));
$tabs_fields_assoc = [];

// Do not show related resources in tabs for the pushed metadata
$show_tab_resources = !(isset($GLOBALS["showing_pushed_metadata"]) && $GLOBALS["showing_pushed_metadata"]);
$configured_resource_type_tabs = [];

if (
    $show_tab_resources
    && isset($related_type_show_with_data)
    && !empty($related_type_show_with_data)
    && ($related_type_upload_link || count(get_related_resources($ref)) > 0)
) {
    $configured_resource_type_tabs = ps_array(
        "SELECT DISTINCT t.ref AS `value`
            FROM resource_type AS rt
        INNER JOIN tab AS t ON t.ref = rt.tab
            WHERE rt.ref IN(" . ps_param_insert(count($related_type_show_with_data)) . ") AND rt.ref <> ?;",
        array_merge(ps_param_fill($related_type_show_with_data, 'i'), ['i', $resource['resource_type']]),
        'schema'
    );
}

// Clean the tabs by removing the ones that would end up being empty
foreach (array_keys($system_tabs) as $tab_ref) {
    // Always keep the Resource type tabs if configured so
    if (in_array($tab_ref, $configured_resource_type_tabs)) {
        // Related resources can be rendered in tabs shown alongside the regular data tabs instead of in their usual position lower down the page
        $tabs_fields_assoc[$tab_ref] = [];
        continue;
    }

    for ($i = 0; $i < count($fields); ++$i) {
        $fields[$i]['tab'] = (int) $fields[$i]['tab'];
        $field_can_show_on_tab = (
            $fields[$i]['display_field'] == 1
            && $fields[$i]['value'] != ''
            && $fields[$i]['value'] != ','
            && ($access == 0 || ($access == 1 && !$fields[$i]['hide_when_restricted']))
            && ($displaycheck[$fields[$i]["ref"]] ?? false)
        );

        // Check if the field can show on this tab
        if ($tab_ref > 0 && $tab_ref == $fields[$i]['tab'] && $field_can_show_on_tab) {
            $tabs_fields_assoc[$tab_ref][$i] = $fields[$i]['ref'];
            $disable_tabs = false;
        }
        // Unassigned or invalid tab links end up on the "not set" list (IF they will be rendered)
        elseif (
            !isset($tabs_fields_assoc[0][$i])
            && (0 === $fields[$i]['tab'] || !isset($system_tabs[$fields[$i]['tab']]))
            && $field_can_show_on_tab
        ) {
            $tabs_fields_assoc[0][$i] = $fields[$i]['ref'];
        }
    }
}

// System is configured with tabs once at least a field has been associated with a valid tab and the field will be rendered
if ($disable_tabs) {
    $tabs_fields_assoc = [];
} elseif (isset($tabs_fields_assoc[0]) && count($tabs_fields_assoc[0]) > 0) {
    foreach (array_keys($tabs_fields_assoc[0]) as $i) {
        $fields[$i]['tab'] = 1;
    }

    // Any fields marked as "not set" get placed in the Default (ref #1) tab
    $tabs_fields_assoc[1] = $tabs_fields_assoc[0];
    unset($tabs_fields_assoc[0]);
}

$fields_tab_names = array_intersect_key($system_tabs, $tabs_fields_assoc);

debug(sprintf('$fields_tab_names = %s', json_encode($fields_tab_names)));
// -----------------------  END: Tab calculation -----------------
?>

<div id="Metadata">
    <?php
    if (
        $show_resourceid 
        || $show_access_field 
        || $show_resource_type 
        || $show_hitcount 
        || $show_contributed_by
    ) {
    ?>
    <div class="NonMetadataProperties">
        <?php if ($show_resourceid) { ?>
            <div class="itemNarrow">
                <h3><?php echo escape($lang["resourceid"]); ?></h3>
                <p><?php echo escape($ref); ?></p>
            </div>
            <?php
        }

        if ($show_access_field) {
            ?>
            <div class="itemNarrow">
                <h3><?php echo escape($lang["access"]); ?></h3>
                <p><?php echo escape($lang["access{$resource['access']}"] ?? ''); ?></p>
            </div>
            <?php
        }

        if ($show_resource_type) {
            ?>
            <div class="itemNarrow">
                <h3><?php echo escape($lang["resourcetype"]); ?></h3>
                <p><?php echo escape(get_resource_type_name($resource["resource_type"])); ?></p>
            </div>
            <?php
        }

        if ($show_hitcount) {
            ?>
            <div class="itemNarrow">
                <h3><?php echo escape($resource_hit_count_on_downloads ? $lang["downloads"] : $lang["hitcount"]); ?></h3>
                <p><?php echo $resource["hit_count"] + $resource["new_hit_count"]; ?></p>
            </div>
            <?php
        }

        // Contributed by
        if ($show_contributed_by) {
            $udata = get_user($resource["created_by"]);

            if ($udata !== false) {
                $udata_fullname = escape($udata["fullname"] ?? "");
                $udata_a_tag_href = generateURL("{$baseurl_short}pages/team/team_user_edit.php", ['ref' => $udata["ref"]]);
                $udata_a_tag = sprintf(
                    '<a href="%s" onclick="return CentralSpaceLoad(this, true);">%s</a>',
                    $udata_a_tag_href,
                    $udata_fullname
                );
                ?>
                <div class="itemNarrow">
                    <h3><?php echo escape($lang["contributedby"]); ?></h3>
                    <p><?php echo checkperm("u") ? $udata_a_tag : $udata_fullname; ?></p>
                </div>
                <?php
            }
        }
        ?>
        <div class="clearerleft"></div>
    </div><!-- End of NonMetadataProperties -->
    <?php
    }

    global $extra;
    $extra = "";

    #  -----------------------------  Draw tabs ---------------------------
    $tabname = "";
    $tabcount = 0;

    if ((isset($fields_tab_names) && !empty($fields_tab_names)) && count($fields) > 0) {
        if (
            $show_resourceid 
            || $show_access_field 
            || $show_resource_type 
            || $show_hitcount 
            || $show_contributed_by
        ) {
        ?>
        <div class="Title"><?php echo escape($lang['metadata']); ?></div>
        <?php
        }
        ?>
        <div class="TabBar">
            <?php
            foreach ($fields_tab_names as $tab_name) {
                $class_TabSelected = $tabcount == 0 ? ' TabSelected' : '';
                
                if ($modal) {
                    $tabOnClick = "SelectMetaTab(" . $ref . "," . $tabcount . ",true);";
                } else {
                    $tabOnClick = "SelectMetaTab(" . $ref . "," . $tabcount . ",false);";
                }
                ?>

                <div id="<?php echo $modal ? "Modal" : ""; ?>tabswitch<?php echo $tabcount . '-' . $ref; ?>" class="Tab<?php echo $class_TabSelected; ?>">
                    <a href="#" onclick="<?php echo $tabOnClick?>"><?php echo escape($tab_name); ?></a>
                </div>

                <?php
                $tabcount++;
            }
            ?>
        </div> <!-- end of TabBar -->
        <?php
    }

    $tabModalityClass = ($modal ? " MetaTabIsModal-" : " MetaTabIsNotModal-") . $ref; ?>

    <div
        class="TabbedPanel<?php echo $tabModalityClass;
        echo ($tabcount > 0) ? " StyledTabbedPanel" : ''; ?>"
        id="<?php echo ($modal ? "Modaltab0" : "tab0") . '-' . $ref; ?>"
    >
        <!-- START of FIRST TabbedPanel -->
        <div class="clearerleft"></div>
        <div class="TabbedPanelInner">
            <?php
            #  ----------------------------- Draw standard and template fields ------------------------
            $tabname                        = '';
            $tabcount                       = 0;
            $extra                          = '';
            $show_default_related_resources = true;

            // Process each tab which has fields attached to a defined tab name or the Default tab
            foreach ($fields_tab_names as $tab_ref => $tabname) {
                for ($i = 0; $i < count($fields); $i++) {
                    if (
                        (
                            $fields[$i]["global"] == 1
                            || in_array($resource['resource_type'], $arr_fieldrestypes[$fields[$i]['ref']])
                            ||
                            (
                                isset($metadata_template_resource_type)
                                && $resource['resource_type'] == $metadata_template_resource_type
                            )
                        )
                        && ($displaycheck[$fields[$i]["ref"]] ?? false)
                        && $tab_ref == $fields[$i]['tab']
                        && !hook('renderfield', '', array($fields[$i], $resource))
                    ) {
                        display_field_data($fields[$i]);
                    }
                }

                // Fields without templates which are linked to the in-process tab have now all been rendered
                // Those with templates which are linked to the in-process tab have had their markup appended to $extra

                // Show related resources which have the in-process tab name:
                include '../include/related_resources.php';

                // Now render any markup previously sidelined in $extra (eg. fields with a display template)
                ?>
                <div class="clearerleft"></div>
                <?php
                echo $extra;
                $extra = '';
                ?>
                </div><!-- END of TabbedPanelInner-->
                </div> <!-- END of TabbedPanel (after extra rendered) -->
                <?php
                // All fields linked to the in-process tab are now rendered

                // If this is not the last in-process tab then render the next TabbedPanel ready for the next tranche of fields
                $tabcount++;

                if ($tabcount != count($fields_tab_names)) {
                    ?>
                    <div class="TabbedPanel StyledTabbedPanel <?php echo $tabModalityClass?>" style="display:none;" id="<?php echo $modal ? "Modal" : ""; ?>tab<?php echo $tabcount . '-' . $ref?>">
                    <!-- START of NEXT TabbedPanel -->
                    <div class="clearerleft"></div>
                    <div>
                    <?php
                }
            }

            if (empty($fields_tab_names)) {
                // Sort the fields via order_by
                foreach ($fields as $field) {
                    $fieldorders[$field["ref"]] = $field["order_by"];
                }

                array_multisort($fieldorders, SORT_ASC, $fields);

                for ($i = 0; $i < count($fields); $i++) {
                    if (
                        ($displaycheck[$fields[$i]["ref"]] ?? false)
                        && !hook('renderfield', "", array($fields[$i], $resource))
                    ) {
                        display_field_data($fields[$i]);
                    }
                }
                // Close TabbedPanel - it is now opened before the $fields_tab_names loop even if no real tabs exist
                ?>
                <div class="clearerleft"></div>
                </div>
                </div> <!-- END of TabbedPanel -->
                <?php
            }
            ?>

            <div class="clearerleft"></div>
            <?php if (!isset($related_type_show_with_data)) {
                echo $extra;
            } ?>
            <div class="clearerleft"></div>
        </div>
<!-- End of Metadata-->
<div class="clearerleft"></div>
