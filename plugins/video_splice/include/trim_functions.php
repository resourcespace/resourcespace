<?php
use Montala\ResourceSpace\CommandPlaceholderArg;

/**
 * Trim video as requested from pages/trim.php
 *
 * @param  string   $target                 Path to the target file. This is the output location for the trim.
 * @param  string   $source_video_file      Path to the source video file. This file will be the input for the trim.
 * @param  int      $resource_ref           Ref of the resource to be trimmed.
 * @param  string   $ffmpeg_start_time      Start of trim - formatted with gmdate()
 * @param  string   $ffmpeg_duration_time   Duration of trim - formatted with gmdate()
 * 
 * @return  void
 */
function generate_video_trim(string $target, string $source_video_file, int $resource_ref, string $ffmpeg_start_time, string $ffmpeg_duration_time) : void
    {
    global $config_windows, $userref;

    // establish FFMPEG location.
    $ffmpeg_fullpath = get_utility_path("ffmpeg");
    $use_avconv = false;
    if (strpos($ffmpeg_fullpath, 'avconv') !== false) {
        $use_avconv = true;
    }

    if ($config_windows)
        {
        // Windows systems have a hard time with the long paths used for video generation.
        $target_ext = strrchr($target, '.');
        $source_ext = strrchr($source_video_file, '.');
        $temp_filename = $userref . '_' . md5(rand() . microtime());
        $target_temp = get_temp_dir() . "/vs_t" . $temp_filename . '.' . $target_ext;
        $target_temp = str_replace("/", "\\", $target_temp);
        $source_temp = get_temp_dir() . "/vs_s" . $resource_ref . '.' . $source_ext;
        $source_temp = str_replace("/", "\\", $source_temp);
        copy($source_video_file, $source_temp);
        $shell_exec_cmd = $ffmpeg_fullpath . " -y -ss %FFMPEG_START_TIME% -i %SOURCE_TEMP% -t %FFMPEG_DURATION_TIME%" . ($use_avconv ? '-strict experimental -acodec copy ' : ' -c copy ') . '%TARGET_TEMP%';
        $shell_exec_params = array(
            '%FFMPEG_START_TIME%' => $ffmpeg_start_time,
            '%SOURCE_TEMP%' => new CommandPlaceholderArg($source_temp, 'is_safe_basename'),
            '%FFMPEG_DURATION_TIME%' => $ffmpeg_duration_time,
            '%TARGET_TEMP%' => new CommandPlaceholderArg($target_temp, 'is_safe_basename')
            );
        run_command($shell_exec_cmd, false, $shell_exec_params);
        rename($target_temp, $target);
        unlink($source_temp);
        }
    else
        {
        $ffprobe_array = get_video_info($source_video_file);
        if(isset($ffprobe_array['streams'][1]['codec_name']) && $ffprobe_array['streams'][1]['codec_name'] = "pcm_s24le")
            {
            # ffmpeg does not support PCM in the MP4 container
            $shell_exec_cmd = $ffmpeg_fullpath . " -y -ss %FFMPEG_START_TIME% -i %SOURCE_VIDEO_FILE% -t '%FFMPEG_DURATION_TIME%' -c copy -c:a aac %TARGET%";
            $shell_exec_params = array(
                '%FFMPEG_START_TIME%' => $ffmpeg_start_time,
                '%SOURCE_VIDEO_FILE%' => new CommandPlaceholderArg($source_video_file, 'is_safe_basename'),
                '%FFMPEG_DURATION_TIME%' => $ffmpeg_duration_time,
                '%TARGET%' => new CommandPlaceholderArg($target, 'is_safe_basename')
                );
            run_command($shell_exec_cmd, false, $shell_exec_params);
            }
        else
            {
            $shell_exec_cmd = $ffmpeg_fullpath . " -y -ss %FFMPEG_START_TIME% -i %SOURCE_VIDEO_FILE% -t %FFMPEG_DURATION_TIME% " . ($use_avconv ? '-strict experimental -acodec copy ' : ' -c copy ') . '%TARGET%';
            $shell_exec_params = array(
                '%FFMPEG_START_TIME%' => $ffmpeg_start_time,
                '%SOURCE_VIDEO_FILE%' => new CommandPlaceholderArg($source_video_file, 'is_safe_basename'),
                '%FFMPEG_DURATION_TIME%' => $ffmpeg_duration_time,
                '%TARGET%' => new CommandPlaceholderArg($target, 'is_safe_basename')
                );
            run_command($shell_exec_cmd, false, $shell_exec_params);
            }
        }
    }