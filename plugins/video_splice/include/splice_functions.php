<?php
use Montala\ResourceSpace\CommandPlaceholderArg;

function generate_merged_video($videos, $video_splice_type, $target_video_command, $target_video_extension, $target_audio_command, $target_width, $target_height, $target_frame_rate, $description, $auto_populate_video_info, $offline)
{
    include_once __DIR__ . "/../../../include/image_processing.php";

    global $ffmpeg_global_options, $videosplice_resourcetype, $videosplice_description_field, $videosplice_video_bitrate_field, $videosplice_video_size_field, $videosplice_frame_rate_field, $videosplice_aspect_ratio_field, $username, $scramble_key, $ffmpeg_std_video_options, $ffmpeg_std_audio_options, $ffmpeg_std_frame_rate_options, $video_export_folder, $download_chunk_size, $userref, $date_field, $lang;

    // Grab ffmpeg and ffprobe paths
    $ffmpeg_fullpath = get_utility_path("ffmpeg");
    $ffprobe_fullpath = get_utility_path("ffprobe");

    // Generate random string for filename usage
    $randstring=md5(rand() . microtime());

    $target_order_count = 0;
    $target_completed_locations = array();
    $video_refs = array_column($videos, 'ref');

    // Generate temp location to do re encoding, adding blank audio and splicing
    $target_temp_location = get_temp_dir(false,"splice/" . implode("-", $video_refs) . "_" . md5($username . $randstring . $scramble_key));

    // Loop through original videos creating new ones encoding as per options
    foreach ($videos as $video) {
        $source_video_file = get_resource_path($video["ref"],true,"",false,get_resource_data($video["ref"])["file_extension"]);
        $shell_exec_cmd = $ffprobe_fullpath . " -i [SOURCE_VIDEO_FILE] -show_streams -select_streams a -loglevel error";
        $shell_exec_params = [
            '[SOURCE_VIDEO_FILE]' => new CommandPlaceholderArg($source_video_file, 'is_valid_rs_path'),
        ];
        $audiocheck = run_command($shell_exec_cmd, false, $shell_exec_params);
        $has_no_audio = empty($audiocheck) ? "_noaudio" : "";
        $target_completed_location = $target_temp_location . "/" . $target_order_count . $has_no_audio . "." . $target_video_extension;

        // SECURITY NOTE: The target_video_command and target_audio_command are not escaped as they come from config and contain multiple option/value combinations thus cannot be escaped
        $validcommandregex = '/^[a-zA-Z0-9\s\-:]*$/';
        if (!preg_match($validcommandregex, $target_video_command)) {
            debug("Invalid video command: " . $target_video_command);
            return false;
        }
        if (!preg_match($validcommandregex, $target_audio_command)) {
            debug("Invalid audio command: " . $target_audio_command);
            return false;
        }

        $video_splice_options = ' -f ' . $target_video_command . " " . $target_audio_command . ' -vf "fps=[TARGET_FRAME_RATE],scale=[TARGET_WIDTH]:[TARGET_HEIGHT]:force_original_aspect_ratio=decrease,pad=[TARGET_WIDTH]:[TARGET_HEIGHT]:(ow-iw)/2:(oh-ih)/2" -sws_flags lanczos';

        $video_splice_command = $ffmpeg_fullpath . " " . $ffmpeg_global_options . " -i [SOURCE_VIDEO_FILE] " . $video_splice_options . " [TARGET_COMPLETED_LOCATION]";
        $video_splice_params = [
            '[SOURCE_VIDEO_FILE]' => new CommandPlaceholderArg($source_video_file, 'is_valid_rs_path'),
            '[TARGET_FRAME_RATE]' => new CommandPlaceholderArg($target_frame_rate, 'is_numeric'),
            '[TARGET_WIDTH]' => (int) $target_width,
            '[TARGET_HEIGHT]' => (int) $target_height,
            '[TARGET_COMPLETED_LOCATION]' => new CommandPlaceholderArg($target_completed_location, 'is_valid_rs_path'),
        ];
        run_command($video_splice_command, true, $video_splice_params);

        // If file has no audio channels create blank audio channel to ensure all video files contain audio thus wont loose audio on videos that have it
        if (empty($audiocheck)) {
            $no_audio_command = $ffmpeg_fullpath . " -i [NO_AUDIO_LOCATION] -f lavfi -i anullsrc -vcodec copy " . $target_audio_command . " -shortest [WITH_AUDIO_LOCATION]";
            $with_audio_location = str_replace("_noaudio", "", $target_completed_location);
            $no_audio_params = [
                '[NO_AUDIO_LOCATION]' => new CommandPlaceholderArg($target_completed_location, 'is_valid_rs_path'),
                '[WITH_AUDIO_LOCATION]' => new CommandPlaceholderArg($with_audio_location, 'is_valid_rs_path'),
            ];
            run_command($no_audio_command, false, $no_audio_params);
            unlink($target_completed_location);
            $target_completed_location = $with_audio_location;
        }
        $target_completed_locations[] = $target_completed_location;
        $target_order_count++;
    }

    $list_file_command = "";
    foreach ($target_completed_locations as $target_completed_location) {
        // Build list file contents (easiest way to merge videos)
        $list_file_command .= "file '" . $target_completed_location . "'\n";
    }
    file_put_contents($target_temp_location . "/list.txt", $list_file_command);

    // Merge video files using list file
    $mergedfile = $target_temp_location . "/merged." . $target_video_extension;
    $merge_command = $ffmpeg_fullpath .  " -f concat -safe 0 -i [LIST_LOCATION] -c copy [OUTPUT_FILE]";
    $merge_params = [
        '[LIST_LOCATION]' => new CommandPlaceholderArg($target_temp_location . "/list.txt", 'is_valid_rs_path'),
        '[OUTPUT_FILE]' => new CommandPlaceholderArg($mergedfile, 'is_valid_rs_path'),
    ];
    run_command($merge_command, false, $merge_params);

    // Tidy up as we go along now final file created
    unlink($target_temp_location . "/list.txt");
    foreach ($target_completed_locations as $target_completed_location) {
        unlink($target_completed_location);
    }

    if ($video_splice_type == "video_splice_save_new") {
        // Create new blank resource with the type specified in the config
        $ref = create_resource($videosplice_resourcetype, 0,-1,$lang["video_splice_createdfromvideosplice"]);
        $resource_location = get_resource_path(
            $ref,
            true,
            "",
            true,
            $target_video_extension,
            -1,
            1,
            false,
            "",
            -1,
            false
        );

        // Place new merged file in resource location
        rename($mergedfile, $resource_location);
        rmdir($target_temp_location);
        create_previews($ref,false,$target_video_extension);

        // Add file extension to db so front end recognises the file
        ps_query("update resource set file_extension=? where ref=?",array("s",$target_video_extension,"i",$ref));

        // Add current date to date field
        update_field($ref, $date_field, date("Y-m-d H:i:s"));

        // If description provided add it
        if (!empty($description)) {
            update_field($ref, $videosplice_description_field, $description);
        }

        // If user wants video information auto populated then add it
        if ($auto_populate_video_info) {
            update_field($ref, $videosplice_video_bitrate_field, array_keys($ffmpeg_std_video_options)[array_search($target_video_command, array_column($ffmpeg_std_video_options, 'command'))]);
            update_field($ref, $videosplice_video_size_field, $target_width . "x" . $target_height);
            update_field($ref, $videosplice_frame_rate_field, array_keys($ffmpeg_std_frame_rate_options)[array_search($target_frame_rate, array_column($ffmpeg_std_frame_rate_options, 'value'))]);

            // Quick way to figure out standard ratios
            $ratio = substr($target_width > $target_height?$target_width / $target_height:$target_height / $target_width, 0, 3);
            switch ($ratio) {
                case 1.3:
                    $aspect_ratio = "4:3";
                    break;

                case 1.6:
                    $aspect_ratio = "16:10";
                    break;

                case 1.7:
                    $aspect_ratio = "16:9";
                    break;
            }

            if (isset($aspect_ratio)) {
                update_field($ref, $videosplice_aspect_ratio_field, $aspect_ratio);
            }
        }
        return $ref;
    } elseif ($video_splice_type == "video_splice_download") {
        // Move to download directory
        $download_filename = $userref . "_" . md5($username . $randstring . $scramble_key) . "." . $target_video_extension;
        $download_file_location = get_temp_dir(false,"user_downloads") . DIRECTORY_SEPARATOR . $download_filename;
        rename($mergedfile, $download_file_location);
        rmdir($target_temp_location);

        if ($offline) {
            // For offline we want to return the location of the file so it can be added as a link in the user message
            return $userref . "_" . $randstring . "." . $target_video_extension;
        } else {
            // Download file
            $filesize = filesize_unlimited($download_file_location);
            ob_flush();

            header(sprintf('Content-Disposition: attachment; filename="%s"', $download_filename));
            header("Content-Length: " . $filesize);
            set_time_limit(0);

            $sent = 0;
            $handle = fopen($download_file_location, "r");
            // Now we need to loop through the file and echo out chunks of file data
            while ($sent < $filesize) {
                echo fread($handle, $download_chunk_size);
                ob_flush();
                $sent += $download_chunk_size;
            }
            // Delete File
            fclose($handle);
            unlink($download_file_location);
        }
        return true;
    }

    // if one of the above options has not worked then return false
    return false;
}