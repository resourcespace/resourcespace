<?php

/**
* Get video resolution and framerate using FFMpeg
*
* @uses get_video_info()
*
* @param string $file Path to video file
*
* @return array
*/
function get_video_resolution($file)
{
    $video_resolution = array(
        'width'     => 0,
        'height'    => 0,
        'framerate' => 0.0,
    );

    $video_info = get_video_info($file);

    // Different versions of ffprobe store the dimensions in different parts of the json output
    if (!empty($video_info['width'])) {
        $video_resolution['width'] = intval($video_info['width']);
    }

    if (!empty($video_info['height'])) {
        $video_resolution['height'] = intval($video_info['height']);
    }

    if (!empty($video_info['r_frame_rate'])) {
        $framerate_pieces = explode('/', $video_info['r_frame_rate']);

        if (floatval($framerate_pieces[1]) > 0) {
            $video_resolution['framerate'] = floatval($framerate_pieces[0] / $framerate_pieces[1]);
        }
    }

    if (isset($video_info['streams']) && is_array($video_info['streams'])) {
        foreach ($video_info['streams'] as $stream) {
            if (!empty($stream['codec_type']) && 'video' === $stream['codec_type']) {
                $video_resolution['width']  = intval($stream['width']);
                $video_resolution['height'] = intval($stream['height']);

                $framerate_pieces = explode('/', $stream['r_frame_rate']);
                if (floatval($framerate_pieces[1]) > 0) {
                    $video_resolution['framerate'] = floatval($framerate_pieces[0] / $framerate_pieces[1]);
                }

                break;
            }
        }
    }

    return $video_resolution;
}

/**
 * Generate HTML to display subtitles in playback of a video resource.
 *
 * @param  int   $ref      Resource ID
 * @param  int   $access   Resource access level - e.g. 0 for open access
 *
 * @return void
 */
function display_video_subtitles($ref, $access)
{
    global $alt_files_visible_when_restricted;
    $alt_access = hook("altfilesaccess");

    if ($access == 0 || $alt_files_visible_when_restricted) {
        $alt_access = true; # open access (not restricted)
    }

    if ($alt_access) {
        $video_altfiles = get_alternative_files($ref);

        foreach ($video_altfiles as $video_altfile) {
            if (mb_strtolower($video_altfile["file_extension"]) == "vtt") {
                $video_altfile["path"] = get_resource_path($ref, false, '', true, $video_altfile["file_extension"], -1, 1, false, '', $video_altfile["ref"]);
                ?>
                <track class="videojs_alt_track" kind="subtitles" src="<?php echo escape($video_altfile["path"]) ?>" label="<?php echo escape($video_altfile["description"]); ?>" ></track>
                <?php
            }
        }
    }
}

/**
 * Generate JSON array of VideoJS options to be used in the data-setup attribute
 *
 * @param bool  $view_as_gif            True if the video is a GIF file
 * @param bool  $play_on_hover          True if playing video on hover
 * @param array $video_preview_sources  Array of preview sources, including URL, type and label
 *
 * @return string|false
 */
function generate_videojs_options(bool $view_as_gif, bool $play_on_hover, array $video_preview_sources)
{
    global $videojs_resolution_selection, $videojs_resolution_selection_default_res;

    $data_setup = ["playbackRates" => [0.5, 1, 1.5, 2]];

    if ($view_as_gif) {
        $data_setup = array_merge($data_setup, [
            "controls" => false,
            "autoplay" => true,
            "loop" => true,
            "muted" => true
        ]);
    }

    if ($play_on_hover && !$view_as_gif) {
        $data_setup = array_merge($data_setup, [
            "loadingSpinner" => false,
            "TextTrackDisplay" => true,
            "nativeTextTracks" => false,
            "children" => [
                "bigPlayButton" => false,
                "controlBar" => [
                    "children" => [
                        "playToggle" => false,
                        "volumeControl" => false
                    ]
                ]
            ]
        ]);
    }

    if (isset($videojs_resolution_selection) && count($video_preview_sources) > 0 && !$view_as_gif) {
        $data_setup = array_merge($data_setup, [
            "plugins" => [
                "videoJsResolutionSwitcher" => [
                    "default" => $videojs_resolution_selection_default_res
                ]
            ]
        ]);
    }

    return json_encode($data_setup);
}