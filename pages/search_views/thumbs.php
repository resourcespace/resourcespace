<?php

$resource_view_title = i18n_get_translated($result[$n]["field" . $view_title_field] ?? "");

if ($display == "xlthumbs") {
    $resolved_title_trim = $xl_search_results_title_trim;
} else {
    $resolved_title_trim = $search_results_title_trim;
}
$class = array();

if ($use_selection_collection && in_array($ref, $selection_collection_resources)) {
    $class[] = "Selected";
}

?>

<div class="resource-card <?php echo implode(" ", $class) . " "; echo $display == "xlthumbs" ? "xl" : "normal" ?>"
     id="ResourceShell<?php echo escape($ref); ?>">
    <div class="resource-card-action-bar">
        <?php 
            if ($use_selection_collection) {
                if (!in_array($result[$n]['resource_type'], $collection_block_restypes)) { ?>
                <label>
                <input 
                    type="checkbox" 
                    id="check<?php echo escape($ref); ?>" 
                    class="checkselect checkselectmedium"
                    data-resource="<?php echo escape($result[$n]["ref"]); ?>"
                    aria-label="<?php echo escape($lang["action-selectresource"]); ?>"
                    <?php
                    echo render_csrf_data_attributes("ToggleCollectionResourceSelection_{$result[$n]["ref"]}");

                    if (in_array($ref, $selection_collection_resources)) { ?>
                        checked
                        <?php
                    }
                    ?>
                >
                <span class="check" aria-hidden="true" title="<?php echo escape($lang['action-selectresource'] . (($resource_view_title != "") ? " - " . $resource_view_title : "")) ?>"></span>
                </label>
            <?php } else { ?>
                <input type="checkbox" class="checkselect" style="opacity: 0;">
            <?php
            }
        }

        include "resource_actions_menu.php";

        ?>
    </div>
        
    <a
        class="resource-card-image <?php echo $display == "xlthumbs" ? "xl" : "normal" ?>"
        href="<?php echo $url; ?>"  
        onclick="return <?php echo $resource_view_modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);" 
        title="<?php echo str_replace(array("\"","'"), "", escape($resource_view_title)); ?>"
    >
        <?php hook("resourcethumbtop"); ?>
        
        <?php
        // Render preview image
        if ($display == "xlthumbs") {
            $usesize = $GLOBALS['retina_mode'] && resource_download_allowed($result[$n]['ref'], 'scr', $result[$n]['resource_type']) ? "scr" : "pre";
        } else {	
            $usesize = $GLOBALS['retina_mode'] ? "pre" : "thm";
        }
        
        $arrsizes = array_unique([$usesize,"pre","thm"]);
        $thumbnail = get_resource_preview($result[$n], $arrsizes, $access, $watermark);

        if ($thumbnail !== false) {
            // Use standard preview image
            if ($result[$n]["thumb_height"] !== $thumbnail["height"] || $result[$n]["thumb_width"] !== $thumbnail["width"]) {
                // Preview image dimensions differ from the size data stored for the current resource
                $result[$n]["thumb_height"] = $thumbnail["height"];
                $result[$n]["thumb_width"]  = $thumbnail["width"];
            }
            render_resource_image($result[$n], $thumbnail["url"], $display);
            // For videos ($ffmpeg_supported_extensions), if we have snapshots set, add code to fetch them from the server
            // when user hovers over the preview thumbnail
            if (1 < $ffmpeg_snapshot_frames && (in_array($result[$n]['file_extension'], $ffmpeg_supported_extensions) || ($result[$n]['file_extension'] == 'gif' && $ffmpeg_preview_gif)) && 0 < get_video_snapshots($ref, false, true)) {
                ?>
                <script>
                    jQuery('#CentralSpace #ResourceShell<?php echo $ref; ?> a img').mousemove(function(event) {
                        var x_coord             = event.pageX - jQuery(this).offset().left;
                        var video_snapshots     = <?php echo json_encode(get_video_snapshots($ref, false, false, true)); ?>;
                        var snapshot_segment_px = Math.ceil(jQuery(this).width() / Object.keys(video_snapshots).length);
                        var snapshot_number     = x_coord == 0 ? 1 : Math.ceil(x_coord / snapshot_segment_px);
                        if (typeof(ss_img_<?php echo $ref; ?>) === "undefined") {
                            ss_img_<?php echo $ref; ?> = new Array();
                        }
                        ss_img_<?php echo $ref; ?>[snapshot_number] = new Image();
                        ss_img_<?php echo $ref; ?>[snapshot_number].src = video_snapshots[snapshot_number];
                        jQuery(this).attr('src', ss_img_<?php echo $ref; ?>[snapshot_number].src);
                    }).mouseout(function(event) {
                        jQuery(this).attr('src', "<?php echo $thumbnail["url"]; ?>");
                    });
                </script>
                <?php
            }
        } else {
            echo get_nopreview_html((string) $result[$n]["file_extension"],$result[$n]['resource_type']);
        }

        hook("aftersearchimg", "", array($result[$n], $thumbnail["url"] ?? "", $display))
        ?>
    </a>
    <div class="resource-card-content">
        <div class="resource-card-content-top">

    <?php

    $df_alt = hook("displayfieldsalt");
    $df_normal = $df;

    if ($df_alt) {
        $df = $df_alt;
    }

    # thumbs_display_fields
    for ($x = 0; $x < count($df); $x++) {
        if (!in_array($df[$x]['ref'], $thumbs_display_fields)) {
            continue;
        }

        $value = $result[$n]['field' . $df[$x]['ref']] ?? "";

        # swap title fields if necessary
        if (
            isset($metadata_template_resource_type)
            && isset($metadata_template_title_field)
            && is_int_loose($metadata_template_title_field)
            && $df[$x]['ref'] == $view_title_field
            && $result[$n]['resource_type'] == $metadata_template_resource_type
        ) {
            $value = $result[$n]['field' . $metadata_template_title_field];
        }

        // extended css behavior
        if (
            in_array($df[$x]['ref'], $thumbs_display_extended_fields) &&
            ((isset($metadata_template_title_field) && $df[$x]['ref'] != $metadata_template_title_field) || !isset($metadata_template_title_field)) && 
            $value !== ""
        ) {
            ?>
            <div
                class="ResourceTypeField<?php echo $df[$x]['ref']; echo $x == 0 ? ' resource-card-title ' : ' resource-card-field'; ?>"
                title="<?php echo str_replace(array("\"","'"), "", escape(i18n_get_translated($value))); ?>"
            >
                <div class="extended">
                    <?php
                    if ($x == 0) { // add link if necessary ?>
                        <a 
                            href="<?php echo $url; ?>"  
                            onClick="return <?php echo $resource_view_modal ? "Modal" : "CentralSpace"; ?>Load(this,true);" 
                        >
                        <?php
                    } //end link
                    echo format_display_field($value);

                    if ($x == 0) { // add link if necessary ?>
                        </a>
                        <?php
                    } //end link?> 
                    &nbsp;
                </div>
            </div>
            <?php
            // normal behavior
        } elseif ((isset($metadata_template_title_field) && $df[$x]['ref'] != $metadata_template_title_field) || !isset($metadata_template_title_field) && $value !== "") {
            ?>
            <div
                class="ResourceTypeField<?php echo $df[$x]['ref']; echo $x == 0 ? ' resource-card-title' : ' resource-card-field'; ?>"
                title="<?php echo str_replace(array("\"","'"), "", escape(i18n_get_translated($value))); ?>"
            >
                <?php
                if ($x == 0) { // add link if necessary ?>
                    <a 
                        href="<?php echo $url; ?>"  
                        onClick="return <?php echo $resource_view_modal ? "Modal" : "CentralSpace"; ?>Load(this,true);" 
                    >
                    <?php
                } //end link
                echo escape(tidy_trim(TidyList(i18n_get_translated($value)), $resolved_title_trim));
                if ($x == 0) { // add link if necessary ?>
                    </a>
                    <?php
                } //end link ?>
            </div>
            <?php
        }
    }
        hook("icons");
    ?>  

        </div>
        <div class="resource-card-content-bottom">
            <div class="resource-card-pill-bar">
                <?php
                if ($display_resource_id_in_thumbnail && $ref > 0) {
                ?>
                    <span class="resource-card-pill resource-card-id"># <?php echo escape($ref); ?></span>
                <?php } 
                
                if ($thumbs_display_archive_state) {

                    switch($result[$n]['archive']) {
                        case -2:
                        case -1:
                            $status_css = "pending";
                            break;
                        case 0:
                            $status_css = "active";
                            break;
                        case 1:
                        case 2:
                            $status_css = "archive";
                            break;
                        case 3:
                            $status_css = "deleted";
                            break;
                        default:
                            $status_css = "custom";
                    }

                    $icon = $workflowicons[$result[$n]['archive']] ?? (WORKFLOW_DEFAULT_ICONS[$result[$n]['archive']] ?? WORKFLOW_DEFAULT_ICON);
                    $workflow_html = "<i class='icon-" . escape($icon) . "'></i>";
                ?>
                    <span class="resource-card-pill resource-card-status <?php echo escape($status_css); ?>">
                        <?php echo $workflow_html; ?>
                        <?php echo isset($lang["status" . $result[$n]['archive']]) ? (escape($lang["status" . $result[$n]['archive']])) : ($lang["status"] . "&nbsp;" . $result[$n]['archive']); ?>
                    </span>
                <?php
                }
                if (isset($show_annotation_count) && $show_annotation_count) {
                    $annotations_count = $result[$n]["annotation_count"] ?? getResourceAnnotationsCount($ref);

                    if ($annotations_count > 0) {
                        ?>
                        <span class="resource-card-pill resource-card-annotations"><i class="icon-captions"></i><?php echo (int) $annotations_count; ?></span>
                        <?php
                    }
                    
                } ?>
            </div>
            <div class="resource-card-type-bar">
                <?php
                echo '<div class="resource-card-type">';
                foreach ($types as $type) {
                    if (($type["ref"] == $result[$n]['resource_type']) && isset($type["icon"]) && $type["icon"] != "") {
                        echo '<i title="' . escape($type["name"]) . '" class="icon-' . escape($type["icon"]) . '"></i>';
                        }
                    }
                if (isset($result[$n]['file_extension']) && $result[$n]['file_extension'] != "") { ?>
                    <?php echo "<span>" . strtoupper(escape($result[$n]['file_extension'])) . "</span>"; ?>
                    <?php
                }
                echo '</div>'; 
                 ?>
                <div class="resource-card-tools">
                    <?php
                    // Remove from collection icon
                    if (!checkperm('b') && ($k == '' || $internal_share_access)) {
                        $col_link_class = ['resource-card-add-remove', 'icon-minus'];
                        if (
                            isset($usercollection_resources)
                            && is_array($usercollection_resources)
                            && !in_array($ref, $usercollection_resources)
                        ) {
                            $col_link_class[] = 'DisplayNone';
                        }

                        $onclick = 'toggle_addremove_to_collection_icon(this);';
                        echo remove_from_collection_link($ref, implode(' ', $col_link_class), $onclick, 0, $resource_view_title) . '</a>';
                    }

                    // Add to collection icon
                    if (
                        $pagename != "collections"
                        && !checkperm('b')
                        && !in_array($result[$n]['resource_type'], $collection_block_restypes)
                        && ('' == $k || $internal_share_access)
                    ) {
                        $col_link_class = ['resource-card-add-remove', 'icon-plus'];

                        if (
                            isset($usercollection_resources)
                            && is_array($usercollection_resources)
                            && in_array($ref, $usercollection_resources)
                        ) {
                            $col_link_class[] = 'DisplayNone';
                        }

                        $onclick = 'toggle_addremove_to_collection_icon(this);';
                        echo add_to_collection_link($ref, $onclick, '', implode(' ', $col_link_class), $resource_view_title) . '</a>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>