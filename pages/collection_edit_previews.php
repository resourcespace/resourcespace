<?php
include "../include/boot.php";
include "../include/authenticate.php";
include_once "../include/image_processing.php";

$ref = getval("ref", ($usercollection ?? 0), true);
$offset = getval("offset", 0, true);
$find = getval("find", "");
$col_order_by = getval("col_order_by", "name");
$order_by = getval("order_by", "");
$sort = getval("sort", "ASC");

# Fetch collection data
$collection_ref = $ref; // preserve collection id because tweaking resets $ref to resource ids
$collection = get_collection($ref);

if ($collection !== false) {
    # Check access
    if (!allow_multi_edit($collection_ref, $collection_ref)) {
        exit(escape($lang["no_access_to_collection"]));
    }

    $resources = do_search("!collection" . $ref);

    # Remove $fstemplate_alt_threshold resources - these should only be updated in the template system.
    $resources = array_filter($resources, function ($resource) { return !resource_file_readonly($resource['ref']); });

    $colcount = count($resources);
    $count_resources_processed = 0;

    if (getval("tweak", "") != "" && enforcePostRequest(false)) {
        $tweak = getval("tweak", "");
        switch ($tweak) {
            case "rotateclock":
                foreach ($resources as $resource) {
                    ++ $count_resources_processed;
                    $GLOBALS['processing_message_override'] = str_replace(["[count]", "[total]"], [$count_resources_processed, $colcount], $lang["processing_resource_in_group"]);
                    tweak_preview_images($resource['ref'], 270, 0, $resource["preview_extension"], -1, $resource['file_extension']);
                }
                $message = $lang["complete"];
                break;
            case "rotateanti":
                foreach ($resources as $resource) {
                    ++ $count_resources_processed;
                    $GLOBALS['processing_message_override'] = str_replace(["[count]", "[total]"], [$count_resources_processed, $colcount], $lang["processing_resource_in_group"]);
                    tweak_preview_images($resource['ref'], 90, 0, $resource["preview_extension"], -1, $resource['file_extension']);
                }
                $message = $lang["complete"];
                break;
            case "gammaplus":
                foreach ($resources as $resource) {
                    ++ $count_resources_processed;
                    $GLOBALS['processing_message_override'] = str_replace(["[count]", "[total]"], [$count_resources_processed, $colcount], $lang["processing_resource_in_group"]);
                    tweak_preview_images($resource['ref'], 0, 1.3, $resource["preview_extension"]);
                }
                $message = $lang["complete"];
                break;
            case "gammaminus":
                foreach ($resources as $resource) {
                    ++ $count_resources_processed;
                    $GLOBALS['processing_message_override'] = str_replace(["[count]", "[total]"], [$count_resources_processed, $colcount], $lang["processing_resource_in_group"]);
                    tweak_preview_images($resource['ref'], 0, 0.7, $resource["preview_extension"]);
                }
                $message = $lang["complete"];
                break;
            case "restore":
                if ($GLOBALS["offline_job_queue"]) {
                    foreach ($resources as $resource) {
                        $create_previews_job_data = [
                            'resource' => $resource['ref'],
                            'thumbonly' => false,
                            'extension' => $resource["file_extension"],
                            'previewonly' => false,
                            'previewbased' => false,
                            'alternative' => -1,
                            'ignoremaxsize' => true,
                        ];
                        $create_previews_job_success_text = str_replace('%RESOURCE', $ref, $lang['jq_create_previews_success_text']);
                        $create_previews_job_failure_text = str_replace('%RESOURCE', $ref, $lang['jq_create_previews_failure_text']);
                        job_queue_add('create_previews', $create_previews_job_data, '', '', $create_previews_job_success_text, $create_previews_job_failure_text);
                    }
                    $message = $lang["recreatepreviews_pending"];
                } elseif ($GLOBALS["enable_thumbnail_creation_on_upload"] === false || isset($GLOBALS["preview_generate_max_file_size"])) {
                    $params = array_merge(['i',RESOURCE_PREVIEWS_NONE], ps_param_fill(array_column($resources, "ref"), "i"));
                    ps_query("UPDATE resource SET has_image = ?, preview_attempts=0 WHERE ref IN (" . ps_param_insert(count($resources)) . ")", $params);
                    $message = $lang["recreatepreviews_pending"];
                } else {
                    // No offline preview functionality enabled - to be created synchronously
                    foreach ($resources as $resource) {
                        ++ $count_resources_processed;
                        $GLOBALS['processing_message_override'] = str_replace(["[count]", "[total]"], [$count_resources_processed, $colcount], $lang["processing_resource_in_group"]);
                        $ingested = empty($resource['file_path']);
                        delete_previews($resource);
                        create_previews(
                            $resource['ref'], 
                            false, 
                            in_array($resource["file_extension"], NON_PREVIEW_EXTENSIONS) ? 'jpg' : $resource["file_extension"], 
                            in_array($resource["file_extension"], NON_PREVIEW_EXTENSIONS), 
                            false, 
                            -1, 
                            true, 
                            $ingested
                        );
                        $message = $lang["complete"];
                    }
                }
                $ref = $collection_ref; // restore collection id because tweaking resets $ref to resource ids
                break;
        }
        unset($GLOBALS['processing_message_override']);
        set_processing_message(""); // Clear once complete or unread messages will hang around
        refresh_collection_frame($collection_ref);
    }
} else {
    $message = $lang['error-collectionnotfound'];
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["editresourcepreviews"]) ?></h1>
    <p><?php echo escape(text("introtext")); ?></p>
    <?php if (isset($message)) { ?>
        <div class="PageInformal"><?php echo escape($message); ?></div>
        <?php
    }
    if ($collection) { ?>
        <form
            method=post 
            id="collectionform"
            action="<?php echo $baseurl_short?>pages/collection_edit_previews.php"
        >
            <?php generateFormToken("collectionpreviewsform"); ?>
            <input type=hidden value='<?php echo (int) $ref ?>' name="ref" id="ref"/>

            <div class="Question">
                <label><?php echo escape($lang["collection"]) ?></label>
                <div class="Fixed"><?php echo escape(i18n_get_collection_name($collection)) ?></div>
                <div class="clearerleft"></div>
            </div>

            <?php if (allow_multi_edit($resources, $ref)) { ?>
                <div class="Question">
                    <label for="tweak">
                        <?php echo escape($lang["imagecorrection"])?>
                        <br/>
                        <?php echo escape($lang["previewthumbonly"])?>
                    </label>
                    <select class="stdwidth" name="tweak" id="tweak" onchange="return CentralSpacePost(this.form, true);">
                        <option value=""><?php echo escape($lang["select"])?></option>
                        <?php if (!$image_rotate_reverse_options) { ?>
                            <option value="rotateclock"><?php echo escape($lang["rotateclockwise"])?></option>
                            <option value="rotateanti"><?php echo escape($lang["rotateanticlockwise"])?></option>
                        <?php } else { ?>
                            <option value="rotateanti"><?php echo escape($lang["rotateclockwise"])?></option>
                            <option value="rotateclock"><?php echo escape($lang["rotateanticlockwise"])?></option>
                        <?php } ?>
                        <option value="gammaplus"><?php echo escape($lang["increasegamma"])?></option>
                        <option value="gammaminus"><?php echo escape($lang["decreasegamma"])?></option>
                        <option value="restore"><?php echo escape($lang["recreatepreviews"])?></option>
                    </select>
                    <div class="clearerleft"></div>
                </div>
            <?php } ?>
        </form>
        <?php
    }
    ?>
</div>

<?php
include "../include/footer.php";
