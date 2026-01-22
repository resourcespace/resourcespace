<?php

include_once "../../../include/boot.php";

include_once "../../../include/authenticate.php";
include_once "../include/splice_functions.php";

// Fetch videos and process...
$notification = "";
$videos = do_search("!collection" . $usercollection, '', 'collection', 0, -1, "ASC", false, 0, false, false, '', false, true, true);
$videos_data = do_search("!collection" . $usercollection, '', 'collection', 0, -1, "ASC");
$splice_order = explode(",", getval("splice_order", ""));
$video_splice_video = getval("video_splice_video", null);
$video_splice_resolution = getval("video_splice_resolution", null);
$video_splice_frame_rate = getval("video_splice_frame_rate", null);
$video_splice_audio = getval("video_splice_audio", null);
$video_splice_type = getval("video_splice_type", null);
$description = getval("video_splice_new_desc", "");
$auto_populate_video_info = getval("auto_populate", "");
$offline = $offline_job_queue;

$error = false;

// Remove resources with incorrect file extensions
global $videosplice_allowed_extensions;

foreach ($videos_data as $key => $video)
    {
    if(!in_array($video["file_extension"], $videosplice_allowed_extensions))
        {
        unset($videos_data[$key]);
        unset($videos[$key]);
        $videos_data = array_values($videos_data);
        $videos = array_values($videos);
        }
    }
$min_access = collection_min_access($videos);
$allowed_actions = [];
if ($min_access === 0) {
    $allowed_actions = ['video_splice_save_export', 'video_splice_download'];
    if (checkperm('c') || checkperm('d')) {
        $allowed_actions[] = 'video_splice_save_new';
    }
}

// The user can decide if they want to wait for the file to be transcoded or be notified when ready when offline jobs enabled
$transcode_now = (getval('transcode_now', '') == 'yes' ? true : false);
if($offline && $transcode_now)
    {
    $offline = false;
    }

// Assure that no new valid resources added or removed from collection
if (getval("splice_submit","") != "" && (count($splice_order) != count($videos))) 
    {
    $error = true;
    $notification = $lang["video_splice_incorrect_quantity"];
    }
// Get the correct splice_order and put it into an array $videos_reordered
elseif (
    getval("splice_submit","") != "" && count($videos) > 1 
    && enforcePostRequest(false) && in_array($video_splice_type, $allowed_actions)
    ) {
    $videos_reordered = array();
    $videos_data_reordered = array();

    foreach($splice_order as $key => $splice_ref)
        {
        $splice_order[$key] = ltrim($splice_ref, 'splice_');
        $this_key = $splice_order[$key];
        $the_key_i_need = array_search($this_key, array_column($videos, 'ref'));
        $videos_reordered[] = $videos[$the_key_i_need];
        $videos_data_reordered[] = $videos_data[$the_key_i_need];
        }

    // Reset $videos to the correct order from $videos_reordered
    $videos = $videos_reordered;
    $videos_data = $videos_data_reordered;

    // If attempting export with config not set error straight out
    if($video_splice_type == "video_splice_save_export" && empty($video_export_folder)) {
        $error = true;
        $notification = $lang["video_splice_no_export_folder"];
    }
    // If no error above process chosen options and generate merged video
    elseif($video_splice_video!=null && $video_splice_resolution!=null && $video_splice_frame_rate!=null)
        {
        // Build the chosen ffmpeg commands as set in the config
        $target_video_command = $ffmpeg_std_video_options[$video_splice_video]["command"];
        $target_video_extension = $ffmpeg_std_video_options[$video_splice_video]["extension"];
        $target_audio = $ffmpeg_std_audio_options[$video_splice_audio]["command"];
        $target_width = $ffmpeg_std_resolution_options[$video_splice_resolution]["width"];
        $target_height = $ffmpeg_std_resolution_options[$video_splice_resolution]["height"];
        $target_frame_rate = $ffmpeg_std_frame_rate_options[$video_splice_frame_rate]["value"];

        // Check if video export folder set and created
        if (
            $video_splice_type == "video_splice_save_export"
            && $video_export_folder != "" 
            && !is_dir($video_export_folder)
            ) {
            mkdir($video_export_folder, 0777);
        }

        if($offline)
            {
            // Process offline job and pick matching success/failure/notification strings
            $generate_merged_video_job_data = array(
                'videos' => $videos,
                'video_splice_type' => $video_splice_type,
                'target_video_command' => $target_video_command,
                'target_video_extension' => $target_video_extension,
                'target_audio' => $target_audio,
                'target_width' => $target_width,
                'target_height' => $target_height,
                'target_frame_rate' => $target_frame_rate,
                'description' => $description,
                'auto_populate_video_info' => $auto_populate_video_info,
                'offline' => $offline
            );

            $generate_merged_video_job_failure_text = $lang["video_splice_failure"];

            switch ($video_splice_type)
                {
                case "video_splice_save_new":
                    $generate_merged_video_job_success_text = $lang["video_splice_new_offline_message"];
                    $jobadded = job_queue_add('generate_merged_video', $generate_merged_video_job_data, '', '', $generate_merged_video_job_success_text, $generate_merged_video_job_failure_text);
                    $notification = str_replace("%job", $jobadded, $lang["video_splice_new_offline"]);
                    break;

                case "video_splice_save_export":
                    $generate_merged_video_job_success_text = str_replace("%location", $video_export_folder, $lang["video_splice_export_completed"]);
                    $jobadded = job_queue_add('generate_merged_video', $generate_merged_video_job_data, '', '', $generate_merged_video_job_success_text, $generate_merged_video_job_failure_text);
                    $notification = str_replace("%job", $jobadded, $lang["video_splice_export_offline"]);
                    break;

                case "video_splice_download":
                    $generate_merged_video_job_success_text = str_replace("%location", $video_export_folder, $lang["video_splice_download_offline_message"]);
                    $jobadded = job_queue_add('generate_merged_video', $generate_merged_video_job_data, '', '', $generate_merged_video_job_success_text, $generate_merged_video_job_failure_text);
                    $notification = str_replace("%job", $jobadded, $lang["video_splice_download_offline"]);
                    break;
                }
            }
        else
            {
            // Process standard function and pick matching success/failure/notification strings
            $return_info = generate_merged_video(
                $videos,
                $video_splice_type,
                $target_video_command,
                $target_video_extension,
                $target_audio,
                $target_width,
                $target_height,
                $target_frame_rate,
                $description,
                $auto_populate_video_info,
                $offline
                );

            if($return_info)
            {
                switch ($video_splice_type)
                    {
                    case "video_splice_save_new":

                        $link_holder = '<a href="' . generateURL($baseurl . '/pages/view.php', array('ref' => $return_info)) . '">' . $return_info . '</a> ';
                        $notification = str_replace("%link", $link_holder, $lang["video_splice_new_completed"]);

                        break;

                    case "video_splice_save_export":

                        $notification = str_replace("%location", $video_export_folder, $lang["video_splice_export_completed"]);

                        break;

                    case "video_splice_download":

                        $notification = str_replace("%location", $video_export_folder, $lang["video_splice_export_completed"]);

                        break;
                    }
                }
            else
                {
                $error = true;
                $notification = str_replace("%location", $video_export_folder, $lang["video_splice_failure"]);
                }
            }
        }
    }
elseif (getval("splice_submit","") != "")
    {
    $error = true;
    $notification = escape($lang['video_splice_error_invalid_action']);
    }

// Generate front end page
include "../../../include/header.php";

?>
<div class="BasicsBox">
<h1><?php echo escape($lang["video-splice"]); render_help_link("plugins/video-splice");?></h1>
<p><?php echo escape($lang["video-splice-intro"]); ?></p>
<?php
    // Holder/area for message feedback
    if ($notification!="")
        {
        if($error)
            {
            echo "<div class=\"PageInformal\"><i class='icon-circle-x'></i>&nbsp;" . $notification . "</div>";
            }
        else
            {
            echo "<div class=\"PageInformal\"><i class='icon-square-check'></i>&nbsp;" . $notification . "</div>";
            }
        }
?>
<div class="RecordBox">
    <!--Section for video preview drag and drop to reorder-->
    <div class="RecordPanel RecordPanelLarge">
        <div class="RecordResource">
            <div id="splice_scroll">
                <div id="splice_reel" style="overflow: hidden; height: 200px !important; width:<?php echo (count($videos)+2) * 180;?>px">
                <?php

                    foreach ($videos_data as $video_data)
                        {
                        if ((int) $video_data["has_image"] !== RESOURCE_PREVIEWS_NONE)
                            {
                            $img = get_resource_path($video_data["ref"], false, "thm", false, $video_data["preview_extension"], -1, 1, false, $video_data["file_modified"]);
                            }
                        else
                            {
                            $img = "../../../gfx/no_preview/default.png";
                            }

                ?>
                <img alt="<?php echo escape(i18n_get_translated($video_data['field'.$view_title_field] ?? ""));?>"
                 src="<?php echo $img ?>" id="splice_<?php echo $video_data["ref"]; ?>" class="splice_item">
                <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    $form_action = generateURL($baseurl_short . "plugins/video_splice/pages/splice.php");
    ?>
    <!--Form information-->
    <form method="post"
          action="<?php echo $form_action; ?>"
          id="spliceform"
          onsubmit="
            return Post(this, true);">
            <?php generateFormToken("spliceform"); ?>
            <div class="Question" id="video_splice_tool">
            <label><?php echo escape($lang["video_splice_order"]); ?></label>
            <p id="ids_in_order"></p>
            <div class="clearerleft"> </div>
            </div>
        <div class="Question" id="question_video_splice_video">
            <label for="video_splice_video"><?php echo escape($lang["video_splice_select_video"]); ?></label>
            <select class="stdwidth" name="video_splice_video" id="video_splice_video">
            <?php
            foreach ($ffmpeg_std_video_options as $video_output_video=>$video_splice_output_command)
                {
                echo "<option value='" . escape(trim($video_output_video)) . "' " . (isset($ffmpeg_std_video_options[$video_output_video]["default"])?"selected":"") . ">" . escape(trim($video_output_video)) . "</option>";
                }
                ?>
            </select>
        <div class="clearerleft"></div>
        </div>
        <div class="Question" id="question_video_splice_resolution">
            <label for="video_splice_resolution"><?php echo escape($lang["video_splice_select_resolution"]); ?></label>
            <select class="stdwidth" name="video_splice_resolution" id="video_splice_resolution" >
            <?php
            foreach ($ffmpeg_std_resolution_options as $video_output_resolution=>$video_splice_output_command)
                {
                echo "<option value='" . escape(trim($video_output_resolution)) . "' " . (isset($ffmpeg_std_resolution_options[$video_output_resolution]["default"])?"selected":"") . ">" . escape(trim($video_output_resolution)) . "</option>";
                }
                ?>
            </select>
        <div class="clearerleft"></div>
        </div>
        <div class="Question" id="question_video_splice_frame_rate">
            <label for="video_splice_frame_rate"><?php echo escape($lang["video_splice_select_frame_rate"]); ?></label>
            <select class="stdwidth" name="video_splice_frame_rate" id="video_splice_frame_rate" >
            <?php
            foreach ($ffmpeg_std_frame_rate_options as $video_output_frame_rate=>$video_splice_output_command)
                {
                echo "<option value='" . escape(trim($video_output_frame_rate)) . "' " . (isset($ffmpeg_std_frame_rate_options[$video_output_frame_rate]["default"])?"selected":"") . ">" . escape(trim($video_output_frame_rate)) . "</option>";
                }
                ?>
            </select>
        <div class="clearerleft"></div>
        </div>
        <div class="Question" id="question_video_splice_audio">
            <label for="video_splice_audio"><?php echo escape($lang["video_splice_select_audio"]); ?></label>
            <select class="stdwidth" name="video_splice_audio" id="video_splice_audio" >
            <?php
            foreach ($ffmpeg_std_audio_options as $video_output_audio=>$video_splice_output_command)
                {
                echo "<option value='" . escape(trim($video_output_audio)) . "' " . (isset($ffmpeg_std_audio_options[$video_output_audio]["default"])?"selected":"") . ">" . escape(trim($video_output_audio)) . "</option>";
                }
                ?>
            </select>
        <div class="clearerleft"></div>
        </div>
        <div class="Question" id="question_video_save_to">
            <label><?php echo escape($lang["video_splice_save_to"]); ?></label>
            <table cellpadding="5" cellspacing="0">
                <tbody>
                    <tr>
                        <?php 
                        if(in_array('video_splice_save_new', $allowed_actions)) {
                        ?>
                        <td>
                            <input type="radio"
                                   id="video_splice_save_new"
                                   class="Inline video_splice_save_option"
                                   name="video_splice_type"
                                   value="video_splice_save_new"
                                   checked
                                   onClick="
                                        jQuery('#video_splice_download').prop('checked', false);
                                        jQuery('#video_splice_save_export').prop('checked', false);
                                        jQuery('#question_transcode_now_or_notify_me').slideUp();
                                        jQuery('#question_description').slideDown();
                                        jQuery('#question_auto_populate_video_info').slideDown();
                            ">
                            <label class="customFieldLabel Inline"
                                   for="video_splice_save_new"><?php echo escape($lang['video_splice_create_new']); ?></label>
                        </td>
                        <?php
                        }

                        if ($video_export_folder !== "" && in_array('video_splice_save_export', $allowed_actions)) {
                        ?>
                        <td>
                            <input type="radio"
                                   id="video_splice_save_export"
                                   class="Inline video_splice_save_option"
                                   name="video_splice_type"
                                   value="video_splice_save_export"
                                   onClick="
                                        jQuery('#video_splice_save_new').prop('checked', false);
                                        jQuery('#video_splice_download').prop('checked', false);
                                        jQuery('#question_description').slideUp();
                                        jQuery('#question_auto_populate_video_info').slideUp();
                                        jQuery('#question_transcode_now_or_notify_me').slideUp();
                            ">
                            <label class="customFieldLabel Inline"
                                   for="video_splice_save_export"><?php echo escape($lang['video_splice_save_export']); ?></label>
                        </td>
                        <?php 
                        }

                        if (in_array('video_splice_download', $allowed_actions)) { 
                        ?>
                        <td>
                            <input type="radio"
                                   id="video_splice_download"
                                   class="Inline video_splice_save_option"
                                   name="video_splice_type"
                                   value="video_splice_download"
                                   onClick="
                                        jQuery('#video_splice_save_export').prop('checked', false);
                                        jQuery('#video_splice_save_new').prop('checked', false);
                                        jQuery('#question_description').slideUp();
                                        jQuery('#question_auto_populate_video_info').slideUp();
                                        jQuery('#question_transcode_now_or_notify_me').slideDown();
                            ">
                            <label class="customFieldLabel Inline"
                                   for="video_splice_download"><?php echo escape($lang['download']); ?></label>
                        </td>
                        <?php 
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
            <div class="clearerleft"></div>
        </div>
        <!--Create new specific questions-->
        <div class="Question" id="question_description">
            <label for="video_splice_new_desc" ><?php echo escape($lang["description"]); ?></label>
            <input type="text" class="stdwidth" id="video_splice_new_desc" name="video_splice_new_desc" value="" />
            <div class="clearerleft"></div>
        </div>
        <div class="Question" id="question_auto_populate_video_info">
            <label><?php echo escape($lang['video_splice_auto_populate_video_info_label']); ?></label>
            <table cellpadding="5" cellspacing="0">
                <tbody>
                    <tr>
                        <td>
                            <input type="checkbox"
                                   id="auto_populate"
                                   class="Inline"
                                   name="auto_populate"
                                   value="yes"
                                   checked
                                   ">
                            <label class="customFieldLabel Inline"
                                   for="auto_populate"><?php echo escape($lang['video_splice_auto_populate_label']); ?></label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="clearerleft"></div>
        </div>
        <!--Download specific question-->
        <?php
        if($offline)
            {
            ?>
            <div class="Question" id="question_transcode_now_or_notify_me" style="display:none;">
                <label><?php echo escape($lang['video_splice_transcode_now_or_notify_me_label']); ?></label>
                <table cellpadding="5" cellspacing="0">
                    <tbody>
                        <tr>
                            <td>
                                <input type="checkbox"
                                       id="transcode_now"
                                       class="Inline"
                                       name="transcode_now"
                                       value="yes"
                                       ">
                                <label class="customFieldLabel Inline"
                                       for="transcode_now"><?php echo escape($lang['video_splice_transcode_now_label']); ?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="clearerleft"></div>
            </div>
            <?php
            }
            ?>
        <!--Hidden value to track new order to post on submit-->
        <input type="hidden" name="splice_order" id="splice_reel_order" />
        <div class="QuestionSubmit">
             <input name="splice_submit" class="spliceubmit" type="submit" value="<?php echo escape($lang["action-splice"]); ?>" onclick="CentralSpaceShowProcessing();">
             <br />
             <div class="clearerleft"> </div>
        </div>
    </form>
</div>
<script type="text/javascript">

    function ReorderResourcesInCollectionSplice(idsInOrder)
        {
        var newOrder = [];

        jQuery.each(idsInOrder, function()
            {
            newOrder.push(this.substring(7));
            });

        jQuery.ajax(
            {
            type: 'POST',
            url: '<?php echo $baseurl_short?>pages/collections.php?collection=<?php echo urlencode($usercollection) ?>&reorder=true',
            data:
                {
                order:JSON.stringify(newOrder),
                <?php echo generateAjaxToken('ReorderResourcesInCollectionSplice'); ?>
                },
            success: function()
                {
                var results = new RegExp('[\\?&amp;]' + 'search' + '=([^&amp;#]*)').exec(window.location.href);
                var ref = new RegExp('[\\?&amp;]' + 'ref' + '=([^&amp;#]*)').exec(window.location.href);
                if ((ref==null)&&(results!== null)&&('<?php echo urlencode("!collection" . $usercollection); ?>' === results[1])) CentralSpaceLoad('<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!collection" . $usercollection); ?>',true);
                }
            });
        }

    function generateOrderString(orderArray){
        var orderString = "";
        orderArray.forEach((ref) => { orderString = orderString + ref.substring(7) + ", "});
        // remove final ", "
        orderString = orderString.substring(0, orderString.length - 2);
        document.getElementById("ids_in_order").innerHTML = orderString;
        }

    /* Start splice reel sortable */
    jQuery("#splice_reel").sortable({ axis: "x" });

    /* Re-order collections */
    jQuery(document).ready(function()
        {
        var idsInOrder = jQuery('#splice_reel').sortable("toArray");
        jQuery('#splice_reel_order').val(idsInOrder);
        var collection = <?php echo $usercollection; ?>;
        var k = <?php echo $k? $k : "''"; ?>;
        generateOrderString(idsInOrder);
        jQuery('#splice_reel').sortable(
            {
            axis: "x",
            helper:"clone",
            items: ".splice_item",
            distance: 20,
            stop: function(event, ui)
                {
                var idsInOrder = jQuery('#splice_reel').sortable("toArray");
                jQuery('#splice_reel_order').val(idsInOrder);
                ReorderResourcesInCollectionSplice(idsInOrder);
                generateOrderString(idsInOrder);
                ChangeCollection(collection,k);
                }
            });
        jQuery('.CollectionPanelShell').disableSelection();
        jQuery("#CollectionDiv").on("click",".CollectionResourceRemove",function()
            {
            var splice_id = "#splice_"+jQuery(this).closest(".CollectionPanelShell").attr("id").replace(/[^0-9]/gi,"");
            jQuery(splice_id).remove();
            });
        });

</script>

<?php include "../../../include/footer.php";