<?php

use Montala\ResourceSpace\CommandPlaceholderArg;

ob_start(); 

$nocache = true;

include_once "../../../include/boot.php";
include_once "../../../include/authenticate.php";
if (!in_array("video_bookend", $plugins)) {
    error_alert($lang["error-plugin-not-activated"]);
    exit();
}
include_once "../../../include/image_processing.php";
include_once "../../../include/video_functions.php";

$ref             = getval("ref", 0, true);
$alternatives    = get_alternative_files($ref);
$ffmpeg_fullpath = get_utility_path("ffmpeg");

# Process submitted form

if(getval("submit", "") != "" && enforcePostRequest(false))
    {
    $temp_dir = get_temp_dir(false, uniqid('bookend'));

    # Join videos (this will stitch the 2 image => video files into one (variable $final)

    $final = "{$temp_dir}/video_bookend_temp_final_{$ref}.mp4";
    $resource = get_resource_data($ref);
    $source = get_resource_path($ref, true, "", false, $resource["file_extension"]);
    $source_pre = get_resource_path($ref, true, "pre", false, $ffmpeg_preview_extension);

    $video_properties = get_video_resolution($source_pre);

    $scaling_params = "scale=" . $video_properties['width'] . ":" . $video_properties['height'] . 
                        ":force_original_aspect_ratio=decrease,pad=" . 
                        $video_properties['width'] . ":" . $video_properties['height'] . 
                        ":-1:-1:color=black";

    $framerate_params = '-r ' . $video_properties['framerate'];
    
    for($n = 1; $n <= 2; $n++)
        {
        $alt = getval("image{$n}", -1, true);

        if($alt != "")
            {
            $extension = "";
            foreach($alternatives as $alternative)
                {
                if($alternative["ref"] == $alt)
                    {
                    $extension = $alternative["file_extension"];         
                    }
                }                 
            }

        $image = get_resource_path($ref, true, "", false, $extension, true, 1, false, '', $alt);

        # Accommodate if 'title image' or 'closing image' is missing, then render image to video accordingly

        if($alt != -1) 
            {
            $path[$n] = "{$temp_dir}/video_bookend_temp_alt_{$alt}.mp4";

            # We need to know if the extension is jpg or gif as the code differs
            # Each gif duration will differ; it will be the gif file duration, not 5 seconds set by ffmpeg
            # $shell_exec_cmd .= " -f lavfi -i aevalsrc=0 -c:v libx264";
            # Playback Issues for Quicktime/Other Codecs - FFMpeg suggests "-pix_fmt yuv420p"
            # Please make sure to use images which have sizes divisible by 2. As required by libx264, the "divisible 
            # By 2" for width and height is needed for YUV 4:2:0 chroma subsampled outputs
            # $shell_exec_cmd .= " -pix_fmt yuv420p -c:a mp3";

            if ($extension == 'jpg' || $extension == 'png')
                {
                run_command(
                    "{$ffmpeg_fullpath} -y -loop 1 -i image -t video_bookend_seconds $framerate_params -filter:v \"crop=trunc(iw/2)*2:trunc(ih/2)*2, $scaling_params\" path_n",
                    false,
                    [
                        'image' => new CommandPlaceholderArg($image, 'is_valid_rs_path'),
                        'video_bookend_seconds' => new CommandPlaceholderArg($video_bookend_seconds, 'is_int_loose'),
                        'path_n' => new CommandPlaceholderArg($path[$n], 'is_safe_basename'),
                    ]
                );
                }

            if($extension == 'gif')
                {
                run_command(
                    "{$ffmpeg_fullpath} -i image $framerate_params -filter:v \"$scaling_params\" path_n",
                    false,
                    [
                        'image' => new CommandPlaceholderArg($image, 'is_valid_rs_path'),
                        'path_n' => new CommandPlaceholderArg($path[$n], 'is_safe_basename'),
                    ]
                );
                }
            }
        }

        
    # Build text file so it works with mp4. Also don't use the original mp4 for source as this just freezes when outputed
    # Only add a line (for the title/closing) if the exist

    $myfile = fopen("{$temp_dir}/bookend_videos.txt", "w");

    $txt = "";
    if(isset($path[1])) { $txt .= "file '$path[1]' \n"; }
    $txt .= "file '$source_pre' \n";
    if(isset($path[2])) { $txt .= "file '$path[2]' \n"; }
    fwrite($myfile, $txt);
    fclose($myfile);

    # ffmpeg join using text file (that stores all the files to be joined)
    run_command(
        'ffmpeg -f concat -safe 0 -i bookend_videos -c copy final',
        false,
        [
            'bookend_videos' => new CommandPlaceholderArg("{$temp_dir}/bookend_videos.txt", 'is_valid_rs_path'),
            'final' => new CommandPlaceholderArg($final, 'is_safe_basename'),
        ]
    );

    # Download the finished file to user...

    $final_video_file_size = filesize_unlimited($final);

    if(file_exists($final) && $final_video_file_size > 0)
        {
        $file_handle = fopen($final, 'rb');

        if($file_handle === false)
            {
            $error = $lang["bookend-could-not-open-file"];
            }

        ob_end_clean();
        header("Content-Disposition: attachment; filename=\"bookend_final_{$ref}.mp4\"");
        header("Content-Type: video/mp4");
        header("Content-Length: {$final_video_file_size}");

        $sent = 0;

        while($sent < $final_video_file_size)
            {
            echo fread($file_handle, $download_chunk_size);
            ob_flush();
            flush();
            $sent += $download_chunk_size;
            if(0 != connection_status()) { break; }
            }

        fclose($file_handle);

        # Delete the tmp bookend files 

        rcRmdir($temp_dir);

        exit();

        }
    else
        {
        $error = $lang["bookend-failed"];
        }
    }

# Display page

include "../../../include/header.php";

if(isset($error))
    {
    print '<div class="PageInformal">' . escape($error) . '</div>';
    }

print '<h1>' . escape($lang["bookend"]) . '</h1>';
print '<p>' . escape($lang["bookend-intro"]) . '</p>';
print '<form method="post">';

generateFormToken("video_bookend");

for($n = 1; $n <= 2; $n++)
    {
    print '<div class="Question">';
    print '<label for="">' . $lang["bookend-image-" . $n] . '</label>';
    print '<select name="image' . $n .'" id="image' . $n . '">';
    print '<option value="">' . $lang["bookend-noimage-" . $n] . '</option>';
    
    foreach ($alternatives as $alternative)
        {
        print '<option value="' . $alternative["ref"] . '">' . escape($alternative["name"]) . '</option>';
        }

    print '</select>';
    print '<div class="clearerleft"></div>';
    print '</div>';
    }

print '<div class="QuestionSubmit">';
print '<input type="submit"  name="submit" value="' . $lang["action-download"] .'" style="width:150px;">';
print '</div>';
print '</form>';

include "../../../include/footer.php";