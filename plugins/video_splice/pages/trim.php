<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";
include_once "../../../include/image_processing.php";
include_once "../include/trim_functions.php";

$ref=getval("ref","",true);
$alt=getval("alternative","",true);

$search=getval("search","");
$offset=getval("offset",0,true);
$order_by=getval("order_by","");
$archive=getval("archive","",true);
$restypes=getval("restypes","");
if (strpos($search,"!")!==false) {$restypes="";}
$modal = (getval("modal", "") == "true");

$default_sort_direction="DESC";
if (substr($order_by,0,5)=="field"){$default_sort_direction="ASC";}
$sort=getval("sort",$default_sort_direction);
$curpos=getval("curpos","");
$go=getval("go","");

$trimmed_resources_new = empty(getval("trimmed_resources_new",null))?null:explode(",", getval("trimmed_resources_new",null));
$trimmed_resources_alt = empty(getval("trimmed_resources_alt",null))?null:explode(",", getval("trimmed_resources_alt",null));
$collection_add = getval("collection_add",null);
$start_time = getval("input_start",null);
$end_time = getval("input_end",null);
$output_type = getval("output_type",null);
$access = get_resource_access($ref);

$urlparams= array(
    "resource" => $ref,
    "ref" => $ref,
    "search" => $search,
    "order_by" => $order_by,
    "offset" => $offset,
    "restypes" => $restypes,
    "archive" => $archive,
    "default_sort_direction" => $default_sort_direction,
    "sort" => $sort,
    "curpos" => $curpos,
    "modal" => ($modal ? "true" : ""),
);

global $lang, $context, $display, $video_preview_original;

// fetch resource data.
$resource=get_resource_data($ref);
if(!is_array($resource))
    {
    error_alert($lang['error-pageload'],false);
    exit();
    }

$editaccess = get_edit_access($resource['ref'], $resource["archive"], $resource);

$can_create_resource = $editaccess && (checkperm("d") || checkperm("c"));
$can_create_alternative = $editaccess && !checkperm("A");
$can_download = $access === RESOURCE_ACCESS_FULL;

if ($ref < 0 || !$can_create_resource && !$can_create_alternative && !$can_download)
    {
    exit(escape($lang["error-permissiondenied"]));
    }

if($resource["lock_user"] > 0 && $resource["lock_user"] != $userref)
    {
    $error = get_resource_lock_message($resource["lock_user"]);
    http_response_code(403);
    exit($error);
    }

// try to find a preview file.
$video_preview_file = get_resource_path(
    $ref,
    true,
    "pre",
    false,
    $ffmpeg_preview_extension
);
// get original file to find full duration
$video_original_file = get_resource_path(
    $ref,
    true,
    "",
    false,
    $resource["file_extension"]
);

$preview_duration = get_video_duration($video_preview_file);
if($preview_duration == 0)
    {
    $preview_duration = get_video_duration(get_resource_path($ref, true, 'pre', false, $ffmpeg_preview_extension));
    }
$original_duration = intval(get_video_duration($video_original_file));
$preview_cap = $original_duration;
if(!$video_preview_original && ($preview_duration < $original_duration) && $preview_duration > 0)
    {
    $preview_cap = $preview_duration;
    }

if(isset($start_time) && isset($end_time) && isset($output_type))
    {
    global $ffmpeg_preview_extension, $videosplice_description_field, $alternative_file_previews, $notify_on_resource_change_days, $usercollection;

    // process video
    // set up the start and end timepoints which will be used
    $ffmpeg_start_time = gmdate("H:i:s", $start_time);
    $ffmpeg_end_time = gmdate("H:i:s", $end_time);
    $ffmpeg_duration_time = gmdate("H:i:s", $end_time - $start_time);

    // create new resource
    if ($output_type == "new" && $can_create_resource)
        {
        // create a new resource.
        $newref=copy_resource($ref,-1,$lang["video_splice_createdfromvideosplice"]);
        $target=get_resource_path(
            $newref,
            true,
            "",
            true,
            $ffmpeg_preview_extension,
            -1,
            1,
            false,
            "",
            -1,
            false
        );
        update_archive_status($newref,get_default_archive_state(0));
        update_field($newref,$videosplice_description_field, "Trimmed video created from resource " . $ref . ":
            Start time from original: " . $ffmpeg_start_time . "
            End time from original: " . $ffmpeg_end_time . "
            Total duration: " . $ffmpeg_duration_time);

        // Set created_by, archive and extension
        ps_query("update resource set created_by=?,archive=?,file_extension=? where ref=?",array("i",$userref,"i",get_default_archive_state(),"s",$ffmpeg_preview_extension,"i",$newref));

            // Unlink the target
        if (file_exists($target)) {unlink ($target);}

        generate_video_trim($target, $video_original_file, $ref, $ffmpeg_start_time, $ffmpeg_duration_time);
        
        create_previews($newref,false,$ffmpeg_preview_extension);

        if ($collection_add == "yes")
            {
            // Add the resource to the user's collection
            add_resource_to_collection($newref,$usercollection);
            }

        // add ref to list
        $trimmed_resources_new[] = $newref;
        }
    elseif ($output_type == "alt" && $can_create_alternative)
        {
        // Upload an alternative file
        $resource_data = get_resource_data($ref);

        // Add a new alternative file
        $alt_filename = "Video trim for resource " . $ref . ": " . $ffmpeg_start_time . "-" . $ffmpeg_end_time;
        $alt_ref=add_alternative_file($ref,$alt_filename);

        // Find the path for this resource.
        $target=get_resource_path(
            $ref,
            true,
            "",
            true,
            $ffmpeg_preview_extension,
            -1,
            1,
            false,
            "",
            $alt_ref
        );

        // Unlink the target
        if (file_exists($target)) {unlink ($target);}

        generate_video_trim($target, $video_original_file, $ref, $ffmpeg_start_time, $ffmpeg_duration_time);
        
        chmod($target,0777);
        $file_size = @filesize_unlimited($target);

        // Save alternative file data.
        ps_query("update resource_alt_files set file_name= ?,file_extension= ?,file_size= ?,creation_date=now() where resource= ? and ref= ?",
                [
                's', $alt_filename,
                's', $ffmpeg_preview_extension,
                'i', $file_size,
                'i', $ref,
                'i', $alt_ref
                ]
        );

        if ($alternative_file_previews)
            {
            create_previews($ref,false,$ffmpeg_preview_extension,false,false,$alt_ref);
            }

        hook('after_alt_upload','',array($ref,array("ref"=>$alt_ref,"file_size"=>$file_size,"extension"=>$ffmpeg_preview_extension,"name"=>$alt_filename,"altdescription"=>"","path"=>$target,"basefilename"=>str_ireplace("." . $ffmpeg_preview_extension, '', $alt_filename))));

        // Check to see if we need to notify users of this change
        if($notify_on_resource_change_days!=0)
            {
            // we don't need to wait for this..
            ob_flush();flush();
            notify_resource_change($ref);
            }

        // Update disk usage
        update_disk_usage($ref);

        // add ref to list
        $trimmed_resources_alt[] = $alt_ref;
        }
    elseif ($output_type == "download" && $can_download)
        {
        $target = get_temp_dir(false, 'user_downloads') . "/" . generateSecureKey(16) . "_$userref." . $ffmpeg_preview_extension;
        $download_filename = escape($lang["action-trim"]) . "_{$ref}_{$ffmpeg_start_time}_{$ffmpeg_end_time}.{$ffmpeg_preview_extension}";

        generate_video_trim($target, $video_original_file, $ref, $ffmpeg_start_time, $ffmpeg_duration_time);
        
        // Download file
        $filesize = filesize_unlimited($target);
        ob_flush();

        header(sprintf('Content-Disposition: attachment; filename="%s"', $download_filename));
        header("Content-Length: " . $filesize);
        set_time_limit(0);

        $sent = 0;
        $handle = fopen($target, "r");

        global $download_chunk_size;

        // Now we need to loop through the file and echo out chunks of file data
        while($sent < $filesize)
            {
            echo fread($handle, $download_chunk_size);
            ob_flush();
            $sent += $download_chunk_size;
            }

        fclose($handle);

        resource_log($ref, LOG_CODE_DOWNLOADED, 0, " - Video trimmed from $ffmpeg_start_time to $ffmpeg_end_time");

        #Delete File:
        unlink($target);
        }
    }
?>

<div class="BasicsBox">
<?php
if (getval("context",false) == "Modal"){$previous_page_modal = true;}
else {$previous_page_modal = false;}
if(!$modal)
    {
    ?>
    <p>
    <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl . "/pages/view.php",$urlparams); ?>"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?></a>
    </p>
    <?php
    }
elseif($previous_page_modal)
    {
    $urlparams["context"]="Modal";
    ?>
    <p>
    <a onClick="return ModalLoad(this,true);" href="<?php echo generateURL($baseurl . "/pages/view.php",$urlparams); ?>"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?></a>
    </p>
    <?php
    }
    ?>
    <div class="RecordHeader">
        <div class="BackToResultsContainer">
            <div class="backtoresults">
            <?php
            if($modal)
                {
                ?>
                <a class="maxLink icon-maximize-2" href="<?php echo generateURL($baseurl . "/plugins/video_splice/pages/trim.php", $urlparams, array("modal" => "")); ?>" onclick="return CentralSpaceLoad(this);" title="<?php echo escape($lang["maximise"]); ?>"></a>
                &nbsp;<a href="#" class="closeLink icon-x" onclick="ModalClose();" title="<?php echo escape($lang["close"]); ?>"></a>
                <?php
                }
                ?>
            </div>
        </div>
    </div>
<?php
if(!empty($trimmed_resources_new))
    {
    $links_holder = "";
    foreach ($trimmed_resources_new as $trimmed_ref) {
        $links_holder = $links_holder . '<a href="' . generateURL($baseurl . '/pages/view.php', array('ref' => $trimmed_ref)) . '">' . escape($trimmed_ref) . '</a> ';
    }
    echo "<div class=\"PageInformal\"><i class='icon-square-check'></i>&nbsp;" . str_replace("%links", $links_holder, escape($lang["video-trim_new-response"])) . "</div>";
    }
if(!empty($trimmed_resources_alt))
    {
    $parent_link = '<a href="' . generateURL($baseurl . '/pages/view.php', array('ref' => $ref)) . '">' . $ref . '</a>';
    $links_holder = "";
    foreach ($trimmed_resources_alt as $trimmed_ref) {
        $links_holder = $links_holder . '<a href="' . generateURL($baseurl . '/pages/preview.php', array('ref' => $ref, 'alternative' => $trimmed_ref)) . '">' . escape($trimmed_ref) . '</a> ';
    }
    echo "<div class=\"PageInformal\"><i class='icon-square-check'></i>&nbsp;" . str_replace("%ref", $parent_link, str_replace("%links", $links_holder, escape($lang["video-trim_alt-response"]))) . "</div>";
    }
    ?>
<h1><?php echo escape($lang["video-trim"]); render_help_link("plugins/video-splice");?></h1>
<?php
if(isset($resource["field".$view_title_field]))
    {
    echo "<h2>" . escape(i18n_get_translated($resource["field".$view_title_field])) . "</h2><br/>";
    }
?>
<div class="RecordBox">
    <div class="RecordPanel RecordPanelLarge">
        <div class="RecordResource">
        <?php
        if ((!(isset($resource["is_transcoding"]) && $resource["is_transcoding"]!=0) && file_exists($video_preview_file)) && !resource_has_access_denied_by_RT_size($resource['resource_type'], 'pre'))
            {
            // Include the player if a video preview file exists for this resource.
            ?>
            <div id="previewimagewrapper">
                <?php
                include dirname (__FILE__, 4) . "/pages/video_player.php";
                ?>
            </div>
<?php
            }
        ?>
        </div>
    </div>
    <?php
    if($camera_autorotation)
        {
        // If enabled and specified in URL then override the default
        $autorotate = getval("autorotate","");

        if($autorotate == "")
            {
            $autorotate = (isset($autorotation_preference) ? $autorotation_preference : false);
            }
        else
            {
            $autorotate = true;
            }
        }
    else
        {
        $autorotate = false;
        }

    $collection_add = getval("collection_add", "");
    if($embedded_data_user_select)
      {
      $no_exif=getval("exif_option","");
      }
    else
      {
      $no_exif=getval("no_exif","");
      }

    $form_action = generateURL($baseurl_short . "plugins/video_splice/pages/trim.php",$urlparams);
    ?>
    <form method="post"
          action="<?php echo $form_action; ?>"
          id="trimform"
          onsubmit="">
            <?php generateFormToken("trimform"); ?>
            <div class="Question" id="video_trim_tool">
            <label><?php echo escape($lang["video-trim"]); ?></label>
            <div class="video-trim-tool">
                <input type="range" name="input_start" id="input-start" min="0" max="<?php echo $original_duration ?>" value="0">
                <input type="range" name="input_end" id="input-end" min="0" max="<?php echo $original_duration ?>" value="<?php echo $original_duration ?>">

                <div class="video-trim-slider">
                    <p id="start-timestamp">00:00:00</p>
                    <div class="bar"></div>
                    <div class="selected">
                        <p id="duration-timestamp">00:00:00</p>
                    </div>
                    <div class="handle start"></div>
                    <div class="handle end"></div>
                    <p id="end-timestamp">00:00:00</p>
                </div>
            </div>
            <div class="clearerleft"> </div>
            </div>
            <div class="Question" id="resource_ref_div" style="border-top:none;">
            <label><?php echo escape($lang["resourceid"]); ?></label>
            <div class="Fixed"><?php echo urlencode($ref) ?></div>
            <div class="clearerleft"> </div>
            </div>
            <div class="Question" id="question_file">
                <label><?php echo escape($lang["file"]); ?></label>
            <div class="Fixed">
            <?php
            if ((int) $resource["has_image"] !== RESOURCE_PREVIEWS_NONE)
                { ?>
                <img alt="<?php echo escape(i18n_get_translated($resource['field'.$view_title_field] ?? ""));?>"
                id="preview" align="top" src="<?php echo get_resource_path($ref,false,($edit_large_preview && !$modal?"pre":"thm"),false,$resource["preview_extension"],-1,1,false)?>" class="ImageBorder" style="margin-right:10px; max-width: 40vw;"/>
                <?php // check for watermarked version and show it if it exists
                if (checkperm("w"))
                    {
                    $wmpath=get_resource_path($ref,true,($edit_large_preview?"pre":"thm"),false,$resource["preview_extension"],-1,1,true);
                    if (file_exists($wmpath))
                        { ?>
                        <img alt="<?php echo escape(i18n_get_translated($resource['field'.$view_title_field] ?? ""));?>"
                         style="display:none;" id="wmpreview" align="top" src="<?php echo get_resource_path($ref,false,($edit_large_preview?"pre":"thm"),false,$resource["preview_extension"],-1,1,true)?>" class="ImageBorder"/>
                        <?php
                        }
                    } ?>
                <br />
                <?php
                }
            else
                {
                // Show the no-preview icon
                echo get_nopreview_html((string) $resource["file_extension"], $resource["resource_type"]);
                ?>
                <br />
                <?php
                }
            if ($resource["file_extension"]!="")
                { ?>
                <strong>
                <?php
                echo str_replace_formatted_placeholder("%extension", $resource["file_extension"], $lang["cell-fileoftype"]) . " (" . formatfilesize(@filesize_unlimited(get_resource_path($ref,true,"",false,$resource["file_extension"]))) . ")";
                ?>
                </strong>
                <?php
                if (checkperm("w") && (int) $resource["has_image"] !== RESOURCE_PREVIEWS_NONE && file_exists($wmpath))
                    {?>
                    &nbsp;&nbsp;
                    <a href="#" onclick='jQuery("#wmpreview").toggle();jQuery("#preview").toggle();if (jQuery(this).text()=="<?php echo escape($lang["showwatermark"]); ?>"){jQuery(this).text("<?php echo escape($lang["hidewatermark"]); ?>");} else {jQuery(this).text("<?php echo escape($lang["showwatermark"]); ?>");}'><?php echo escape($lang["showwatermark"]); ?></a>
                    <?php
                    }?>
                <br />
                <?php
                }
            ?>
            </div>
            <div class="clearerleft"> </div>
        </div>
        <div class="Question" id="question_uploadtype">
            <label for="uploadtype"><?php echo escape($lang["video-trim_output"]); ?></label>
            <select name="output_type" id="uploadtype" class="stdwidth" onChange="show_hide_collection_add()">
            <?php if($can_create_alternative) { ?><option value="alt"><?php echo escape($lang["addalternativefile"]); ?></option><?php } ?>
            <?php if($can_create_resource) { ?><option value="new"><?php echo escape($lang["createnewresource"]); ?></option><?php } ?>
            <?php if($can_download) { ?><option value="download"><?php echo escape($lang["download"]); ?></option><?php } ?>
            </select>
            <div class="clearerleft"> </div>
        </div>
        <div class="Question" id="question_collectionadd" style="display:none;">
            <label for="collectionadd"><?php echo escape($lang["addtocurrentcollection"]); ?></label>
            <input name="collection_add" id="collectionadd" value="yes" type="checkbox">
            <div class="clearerleft"> </div>
        </div>
        <div class="QuestionSubmit">
             <input name="trim_submit" class="trimsubmit" type="submit" value="<?php echo escape($lang["action-trim"]); ?>" onclick="stopLoop()">
             <br />
             <div class="clearerleft"> </div>
        </div>
        <input type="hidden" name="trimmed_resources_new" value="<?php echo escape(isset($trimmed_resources_new)?implode(',', $trimmed_resources_new):''); ?>" />
        <input type="hidden" name="trimmed_resources_alt" value="<?php echo escape(isset($trimmed_resources_alt)?implode(',', $trimmed_resources_alt):''); ?>" />
    </form>
</div>
<script>
var inputStart = document.getElementById("input-start");
var inputEnd = document.getElementById("input-end");
var handleStart = document.querySelector(".video-trim-slider > .handle.start");
var handleEnd = document.querySelector(".video-trim-slider > .handle.end");
var selected = document.querySelector(".video-trim-slider > .selected");
var thisLoop = null; // holder for current loop to be reset on slider change

function setStartValue() {
    var thisInput = inputStart,
        min = parseInt(thisInput.min),
        max = parseInt(thisInput.max);
console.log(thisInput.max);
    thisInput.value = Math.min(parseInt(thisInput.value), parseInt(inputEnd.value) - 1);

    var percent = ((thisInput.value - min) / (max - min)) * 100;

    handleStart.style.left = percent + "%";
    selected.style.left = percent + "%";
    generateTimestamps();
}
setStartValue();

function setEndValue() {
    var thisInput = inputEnd,
        min = parseInt(thisInput.min),
        max = parseInt(thisInput.max);

    thisInput.value = Math.max(parseInt(thisInput.value), parseInt(inputStart.value) + 1);

    var percent = ((thisInput.value - min) / (max - min)) * 100;

    handleEnd.style.right = (100 - percent) + "%";
    selected.style.right = (100 - percent) + "%";
    generateTimestamps();
}
setEndValue();

inputStart.addEventListener("input", setStartValue);
inputEnd.addEventListener("input", setEndValue);

inputStart.addEventListener("mouseover", function() {
    handleStart.classList.add("hover");
});
inputStart.addEventListener("mouseout", function() {
    handleStart.classList.remove("hover");
});
inputStart.addEventListener("mousedown", function() {
    handleStart.classList.add("active");
    setHandlePriority(inputStart);
});
inputStart.addEventListener("mouseup", function() {
    handleStart.classList.remove("active");
    startCalculatedPreviewPlayback(inputStart.value, inputEnd.value);
    console.log("Start: " + inputStart.value + " End: " + inputEnd.value);
});

inputEnd.addEventListener("mouseover", function() {
    handleEnd.classList.add("hover");
});
inputEnd.addEventListener("mouseout", function() {
    handleEnd.classList.remove("hover");
});
inputEnd.addEventListener("mousedown", function() {
    handleEnd.classList.add("active");
    setHandlePriority(inputEnd);
});
inputEnd.addEventListener("mouseup", function() {
    handleEnd.classList.remove("active");
    startCalculatedPreviewPlayback(inputStart.value, inputEnd.value);
    console.log("Start: " + inputStart.value + " End: " + inputEnd.value);
});

function startCalculatedPreviewPlayback(start, end){
    var preview = document.getElementById("<?php echo $context ?>_<?php echo $display ?>_introvideo<?php echo $ref?>_html5_api");
    preview.currentTime = start;

    // if preview file duration smaller then original file the start or end of trim preview needs to be capped
    if (start > <?php echo $preview_cap ?> || end > <?php echo $preview_cap ?>)
        {
        styledalert("<?php echo escape($lang["video-trim-warning"]); ?>", "<?php echo strip_tags_and_attributes($lang["video-trim-warning-text"]); ?>", 500);

        preview.currentTime = 0;
        preview.pause();
        }
    else
        {
        preview.play();

        console.log(typeof thisInterval);
        if(typeof thisLoop !== 'undefined')
        {
        clearInterval(thisLoop);
        }

        thisLoop = setInterval(function()
          {
          if(preview.currentTime > end)
              {
              preview.currentTime = start;
              }
          else if(preview.currentTime < start)
              {
              preview.currentTime = start;
              }
          });
        }
    }

function stopLoop(){
    var preview = document.getElementById("<?php echo $context ?>_<?php echo $display ?>_introvideo<?php echo $ref?>_html5_api");

    preview.currentTime = 0;
    preview.pause();
    }

function generateTimestamps(){
    document.getElementById("start-timestamp").innerHTML = secondsToHHMMSS(inputStart.value);
    document.getElementById("end-timestamp").innerHTML = secondsToHHMMSS(inputEnd.value);
    document.getElementById("duration-timestamp").innerHTML = secondsToHHMMSS(inputEnd.value-inputStart.value);
    }

function secondsToHHMMSS(seconds){
    var timeSeconds = new Date(0);
    timeSeconds.setSeconds(seconds);
    var timeHHMMSS = timeSeconds.toISOString().substr(11, 8);
    console.log(timeHHMMSS)
    return timeHHMMSS;
    }

function setHandlePriority(lastHandleTouched){
    //to help prevent overlap at the extremities leaving unable to move handle back
    inputStart.style.zIndex = "";
    inputEnd.style.zIndex = "";
    lastHandleTouched.style.zIndex = "4";
    }

function show_hide_collection_add(){
    if (jQuery('#uploadtype').val() == 'new')
        {
        jQuery('#question_collectionadd').show();
        }
    else
        {
        jQuery('#question_collectionadd').hide();
        }
}

jQuery('#trimform').submit(function() {
    if (jQuery('#uploadtype').val() != 'download') {
        return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(this, true);
    }
});
</script>
