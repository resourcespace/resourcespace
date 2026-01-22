<?php
include_once '../../../include/boot.php';
include_once '../../../include/authenticate.php';
include_once '../../../include/image_processing.php';

$ref = getval("ref",0, true);
$search     = getval("search","");
$offset     = getval("offset",0,true);
$order_by   = getval("order_by","");
$sort       = getval("sort","");
$k          = getval("k","");

// Build view url for redirect
$urlparams = array(
    "ref"       =>  $ref,
    "search"    =>  $search,
    "offset"    =>  $offset,
    "order_by"  =>  $order_by,
    "sort"      =>  $sort,
    "k"         =>  $k
    );
$view_url = generateURL($baseurl_short . 'pages/view.php',$urlparams);

// for_original exists when the plugin is used to create custom video formats on the fly for the original file ($video_tracks_allow_original_custom_formats)
$for_original = filter_var(getval('for_original', false), FILTER_VALIDATE_BOOLEAN);

// Validation for errors which prevent the use of this plugin
$message = '';
if($ref <= 0)
    {
    $error   = true;
    $message = $lang['video_tracks_invalid_resource'];
    }

$access = get_resource_access($ref);
if($access != 0)
    {
    $error   = true;
    $message = $lang['error-permissiondenied'];
    }

if(isset($error))
    {
    error_alert($message,false);
    exit();
    }

$video_tracks_output_formats ??= $video_tracks_output_formats_default;
$resource                    = get_resource_data($ref);
$edit_access                 = get_edit_access($ref, $resource['archive']);
$offline                     = ($offline_job_queue && $resource['file_size'] >= ($video_tracks_process_size_limit * 1024 * 1024));
$generate                    = (getval('generate', '') == 'yes' ? true : false);

// The user can decide if he/ she wants to wait for the file to be transcoded or be notified when ready
$transcode_now = (getval('transcode_now', '') == 'yes' ? true : false);
if($generate && $offline && $transcode_now)
    {
    $offline = false;
    }

$altfiles = get_alternative_files($ref);
$subtitle_alts = [];
$audio_alts = [];
foreach($altfiles as $altfile)
    {
    if(in_array(mb_strtolower($altfile["file_extension"]),$video_tracks_subtitle_extensions)){$subtitle_alts[]=$altfile;}
    if(in_array(mb_strtolower($altfile["file_extension"]),$video_tracks_audio_extensions)){$audio_alts[]=$altfile;}
    }

// Generate request processing
if($generate && enforcePostRequest(false)) {
    $video_track_format  = getval("video_track_format","");
    $video_subtitle_file = getval("video_subtitle_file",0,true,"is_int_loose");
    $video_audio_file    = getval("video_audio_file",0,true,"is_int_loose");
    $track_save_alt      = (getval("video_track_save_alt", '') == "yes" ? true: false);
    $track_download      = (getval("video_track_download", '') == "yes" ? true: false);
    $continue            = true;

    // Validate the user generate request
    if(!isset($video_tracks_output_formats[$video_track_format])) {
        $message=$lang["video_tracks_invalid_option"];
        $continue=false;
    }
    if(!$track_save_alt && !$track_download)  {
        $message=$lang["video_tracks_select_generate_opt"];
        $continue=false;
    } 
    if($track_save_alt && checkperm("A") )  {
        $message=$lang["video_tracks_save_alt_not_perm"];
        $continue=false;
    } 

    if($continue) {
        // Prepare file names and paths for the selected track format 
        $ffmpeg_fullpath = get_utility_path("ffmpeg");
        $ffprobe_fullpath = get_utility_path("ffprobe");
        $filesource = get_resource_path($ref,true,"",false,$resource["file_extension"]);
        $randstring=md5(rand() . microtime());

        // Get the ffmpeg command for the selected track format
        $video_track_command = $video_tracks_output_formats[$video_track_format];
        $shell_exec_params = [];
        $placeholder_extra = md5(rand() . microtime()); // To block attempts to inject code
        $shell_exec_cmd = $ffmpeg_fullpath . " " . $ffmpeg_global_options . " -i %%SOURCE$placeholder_extra%%";
        $shell_exec_params["%%SOURCE$placeholder_extra%%"] = $filesource;

        $probeout = run_command($ffprobe_fullpath . " -i " . escapeshellarg($filesource), true);
        if(preg_match("/Duration: (\d+):(\d+):(\d+)\.\d+, start/", $probeout, $match))
            {
            $duration = $match[1]*3600+$match[2]*60+$match[3];
            $shell_exec_cmd .= " -t %%DURATION$placeholder_extra%%";
            $shell_exec_params["%%DURATION$placeholder_extra%%"] = $duration;
            }

        if(in_array($video_audio_file,array_column($audio_alts,"ref")))
            {
            $audio_info=get_alternative_file($ref,$video_audio_file);
            $audio_path=get_resource_path($ref,true,"",false,$audio_info["file_extension"],-1,1,false,"",$video_audio_file);
            $shell_exec_cmd .= " -i %%SOURCEAUDIO$placeholder_extra%%";
            $shell_exec_params["%%SOURCEAUDIO$placeholder_extra%%"] = $audio_path;
            $shell_exec_cmd .= " -map 0:v -map 1:a";
            }

        if(in_array($video_subtitle_file,array_column($subtitle_alts,"ref")))
            {
            $subtitle_info=get_alternative_file($ref,$video_subtitle_file);
            $subtitle_path=get_resource_path($ref,true,"",false,$subtitle_info["file_extension"],-1,1,false,"",$video_subtitle_file);
            $shell_exec_cmd .= " -vf subtitles=%%SUBTITLES$placeholder_extra%%";
            $shell_exec_params["%%SUBTITLES$placeholder_extra%%"] = $subtitle_path;
            }
        $shell_exec_cmd .= " " . $video_track_command["command"] . " %%TARGETFILE$placeholder_extra%%";

        // Alternative file save
        if($track_save_alt) {
            $origfilename=get_data_by_field($ref,$filename_field);
            $altname=$video_track_format;
            $description=getval("video_track_alt_desc","");
            if($offline) {
                // Add this to the job queue for offline processing
                $job_data=array();
                $job_data["resource"]=$ref;
                $job_data["command"]=$shell_exec_cmd;
                $job_data["command_params"]=$shell_exec_params;
                $job_data["output_file_placeholder"]="%%TARGETFILE$placeholder_extra%%";
                $job_data["alt_name"]=$altname;
                $job_data["alt_description"]=$description;
                $job_data["alt_extension"]=$video_track_command["extension"];
                $job_code=$ref . $altname . md5($job_data["command"]); // unique code for this job, used to prevent duplicate job creation
                $job_success_lang="alternative_file_created" . str_replace(array('%ref','%title'),array($ref,$resource['field' . $view_title_field]),$lang["ref-title"]);
                $job_failure_lang="alternative_file_creation_failed" . ": " . str_replace(array('%ref','%title'),array($ref,$resource['field' . $view_title_field]),$lang["ref-title"]);
                $jobadded=job_queue_add("create_alt_file",$job_data,$userref,'',$job_success_lang,$job_failure_lang,$job_code);
                if(!is_int_loose($jobadded)) {
                    $message =  $jobadded;
                } else {
                    $message=$lang["video_tracks_offline_notice"];
                }
            } else {
                $newaltfile=add_alternative_file($ref,$altname,$description,str_replace("." . $resource["file_extension"],"." . $video_track_command["extension"],$origfilename),$video_track_command["extension"]);
                $targetfile=get_resource_path($ref,true,"",false, $video_track_command["extension"],-1,1,false,"",$newaltfile);
            }
        } else {
            // Else it must be a download; generate a path based on userref
            $targetfile = get_temp_dir(false,'user_downloads') . "/" . $ref . "_" . md5($username . $randstring . $scramble_key) . "." . $video_track_command["extension"];
            if($offline) {
                $job_data=array();
                $job_success_lang=$lang["download_file_created"]  . " - " . str_replace(array('%ref','%title'),array($ref,$resource['field' . $view_title_field]),$lang["ref-title"]);
                $job_failure_lang=$lang["download_file_creation_failed"] . " - " . str_replace(array('%ref','%title'),array($ref,$resource['field' . $view_title_field]),$lang["ref-title"]);
                $job_data["resource"]=$ref;
                $job_data["command"]=$shell_exec_cmd;
                $job_data["command_params"]=$shell_exec_params;
                $job_data["outputfile"]=$targetfile;
                $job_data["output_file_placeholder"]="%%TARGETFILE$placeholder_extra%%";
                $job_data["url"]=$baseurl_short . "pages/download.php?userfile=" . $ref . "_" . $randstring . "." . $video_track_command["extension"];
                $job_data["lifetime"]=DOWNLOAD_FILE_LIFETIME;
                $job_code=$ref . $userref . md5($job_data["command"]); // unique code for this job, used to prevent duplicate job creation
                $jobadded=job_queue_add("create_download_file",$job_data,$userref,'',$job_success_lang,$job_failure_lang,$job_code);
                if(!is_int_loose($jobadded)) {
                    $message =  $jobadded;
                } else {
                    $message=$lang["video_tracks_offline_notice"];
                }
            } else {
                $filename=get_download_filename($ref,"",-1,$video_track_command["extension"]);
                $track_download=true;
            }
        }
        
        // Now run the generate command
        if(!$offline) {
            $shell_exec_params["%%TARGETFILE$placeholder_extra%%"] = $targetfile;
            if ($config_windows) {
                $shell_exec_cmd = str_replace(array_keys($shell_exec_params),array_values($shell_exec_params),$shell_exec_cmd);
                file_put_contents(get_temp_dir() . "/ffmpeg_" . $randstring . ".bat",$shell_exec_cmd);
                $shell_exec_cmd=get_temp_dir() . "/ffmpeg_" . $randstring . ".bat";
                $shell_exec_params= [];
                $deletebat = true;
            }

            $output = run_command($shell_exec_cmd,false,$shell_exec_params);
            if(file_exists($targetfile)) {
                if($track_save_alt) {
                    // Save as alternative
                    $newfilesize=filesize_unlimited($targetfile);
                    ps_query("UPDATE resource_alt_files SET file_size=? WHERE resource=? AND ref=?",array("i",$newfilesize,"i",$ref,"i",$newaltfile));
                    if ($alternative_file_previews) {
                        create_previews($ref,false,$video_track_command["extension"],false,false,$newaltfile);
                    }
                    $message = $lang["alternative_file_created"];
                }
                elseif($track_download) {
                    // Download file
                    $filesize=filesize_unlimited($targetfile);
                    ob_flush();

                    header(sprintf('Content-Disposition: attachment; filename="%s"', $filename));
                    header("Content-Length: " . $filesize);
                    set_time_limit(0);

                    $sent = 0;
                    $handle = fopen($targetfile, "r");

                    // Now we need to loop through the file and echo out chunks of file data
                    while($sent < $filesize)
                        {
                        echo fread($handle, $download_chunk_size);
                        ob_flush();
                        $sent += $download_chunk_size;
                        }
                    #Delete File:
                    unlink($targetfile);
                } 
            } else {
                $message=$lang["error"];
            }

            if(isset($deletebat) && file_exists($shell_exec_cmd)) {
                unlink($shell_exec_cmd);
            }
        }

    } // End continue 
}?>
<script>
var video_tracks_offline = <?php echo $offline ? 'true' : 'false'; ?>;
</script>
<div class="BasicsBox">
    <h1><?php echo escape($lang["video_tracks_create_video_link"]);?> </h1>
    <?php
    if ($message!="")
        {
        echo "<div class=\"PageInformal\">" . escape($message) . "</div>";
        }
    ?>
    <form id="video_tracks_create_form" method="post" action="<?php echo $baseurl . "/plugins/video_tracks/pages/create_video.php" ;?>">
        <?php generateFormToken("video_tracks_create_form"); ?>
        <input name="ref" type="hidden" value="<?php echo $ref; ?>">
        <input type="hidden" name="generate" value="yes" />
        <div class="Question" id="question_video_track_format">
            <label for="video_track_format"><?php echo escape($lang["video_tracks_select_output"]); ?></label>
            <select class="stdwidth" name="video_track_format" id="video_track_format" >
            <?php
            foreach ($video_tracks_output_formats as $video_tracks_output_format=>$video_tracks_output_command)
                {
                echo "<option value='" . escape(trim($video_tracks_output_format)) . "' >" . escape(trim($video_tracks_output_format)) . "</option>";
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>
    <?php 
    if(count($subtitle_alts)>0)
        {?>
        <!-- Select subtitle file -->
        <div class="Question" id="question_video_subtitles">
            <label for="video_subtitle_file"><?php echo escape($lang["video_tracks_select_subtitle"]); ?></label>
            <select class="stdwidth" name="video_subtitle_file" id="video_subtitle_file" >
            <option value=""><?php echo escape($lang["select"]); ?></option>
            <?php
            foreach ($subtitle_alts as $subtitle_alt)
                {
                if(in_array(mb_strtolower($subtitle_alt["file_extension"]),$video_tracks_subtitle_extensions))
                    {
                    echo "<option value='" . $subtitle_alt["ref"] . "' >" . escape(trim($subtitle_alt["description"])) . " (" . $subtitle_alt["name"] . ")</option>";
                    }     
                }
            ?>
            </select>
            <div class="clearerleft"> </div>
        </div>
        <?php
        }
        
    if(count($audio_alts)>0)
        {?>
        <!-- Select audio file -->
        <div class="Question" id="question_video_audio">
            <label for="video_audio_file"><?php echo escape($lang["video_tracks_select_audio"]); ?></label>
            <select class="stdwidth" name="video_audio_file" id="video_audio_file" >
            <option value=""><?php echo escape($lang["select"]); ?></option>
            <?php
            foreach ($audio_alts as $audio_alt)
                {
                if(in_array(mb_strtolower($audio_alt["file_extension"]),$video_tracks_audio_extensions))
                    {
                    echo "<option value='" . $audio_alt["ref"] . "' >" . escape(trim($audio_alt["description"])) . " (" . $audio_alt["name"] . ")</option>";
                    }     
                }
            ?>
            </select>
            <div class="clearerleft"> </div>
        </div>
        <?php
        }
        ?>
        <div class="Question" id="question_video_save_to">
            <label><?php echo escape($lang["video_tracks_save_to"]); ?></label>
            <table cellpadding="5" cellspacing="0">
                <tbody>
                    <tr>
                    <?php
                    if($edit_access)
                        {
                        ?>
                        <td>
                            <input type="radio" 
                                    id="video_track_save_alt" 
                                    class="Inline video_track_save_option" 
                                    name="video_track_save_alt" 
                                    value="yes" 
                                    onClick="
                                        jQuery('#video_track_download').prop('checked', false);
                                        jQuery('#question_transcode_now_or_notify_me').slideUp();
                                        jQuery('#question_alternative_description').slideDown();
                            ">
                            <label class="customFieldLabel Inline"
                                   for="video_track_save_alt"><?php echo escape($lang['video_tracks_save_alternative']); ?></label>
                        </td>
                        <?php
                        }
                        ?>
                        <td>
                            <input type="radio"
                                   id="video_track_download"
                                   class="Inline video_track_save_option"
                                   name="video_track_download"
                                   value="yes"
                                   onClick="
                                        jQuery('#video_track_save_alt').prop('checked', false);
                                        jQuery('#question_alternative_description').slideUp();
                                        jQuery('#question_transcode_now_or_notify_me').slideDown();
                            ">
                            <label class="customFieldLabel Inline"
                                   for="video_track_download"><?php echo escape($lang['download']); ?></label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="clearerleft"></div>
        </div>

        <div class="Question" id="question_alternative_description" style="display:none;">
            <label for="video_track_alt_desc" ><?php echo escape($lang["description"]); ?></label>
            <input type="text" class="stdwidth" id="video_track_alt_desc" name="video_track_alt_desc" value="" />
            <div class="clearerleft"></div>
        </div>
    <?php
    if($offline)
        {
        ?>
        <div id="question_transcode_now_or_notify_me" class="Question" style="display:none;">
            <label><?php echo escape($lang['video_tracks_transcode_now_or_notify_me_label']); ?></label>
            <table cellpadding="5" cellspacing="0">
                <tbody>
                    <tr>
                        <td>
                            <input type="checkbox"
                                   id="transcode_now"
                                   class="Inline"
                                   name="transcode_now"
                                   value="yes"
                                   onClick="
                                        video_tracks_offline = true;
                                        if(jQuery(this).is(':checked'))
                                            {
                                            video_tracks_offline = false;
                                            }
                                   ">
                            <label class="customFieldLabel Inline"
                                   for="transcode_now"><?php echo escape($lang['video_tracks_transcode_now_label']); ?></label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="clearerleft"></div>
        </div>
        <?php
        }
        ?>
        <div class="video_tracks_buttons">
            <input type="submit"
                   name="submit"
                   class="video_tracks_button"
                   value="<?php echo escape($lang["video_tracks_generate"]); ?>"
                   onClick="
                        if(jQuery('#video_track_download').is(':checked') && !video_tracks_offline)
                            {
                            this.form.submit;
                            }
                        else
                            {
                            ModalPost(this.form,false,true);
                            jQuery('.video_tracks_button').attr('disabled',true);

                            return false;
                            }
                    ">
            <input type="submit" name="submit" class="video_tracks_button" value="<?php echo escape($lang["close"]); ?>" 
                onclick="return CentralSpaceLoad('<?php echo $view_url; ?>', true);"/>

        </div>
    </form>
</div><!--End of BasicsBox -->
