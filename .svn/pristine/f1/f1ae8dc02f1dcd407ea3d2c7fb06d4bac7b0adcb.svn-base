<?php
include_once __DIR__ . '/../include/video_functions.php';

if ($pagename == "search" || $pagename == "view") {
    # VideoJS audio player - plays the mp3 preview file created to preview audio resources.

    global $alternative,$css_reload_key,$display,$keyboard_navigation_video_search,$keyboard_navigation_video_view,$keyboard_navigation_video_preview;

    if (!isset($mp3path)) {
        $mp3path = get_resource_path($ref, false, $hide_real_filepath ? 'videojs' : '', false, "mp3", -1, 1, false, "", $alternative);
    }

    if (!isset($thumb_url)) {
        if (isset($thm_url)) {
            $thumb_url = $thm_url;
        } else {
            $thumb = get_resource_path($ref, false, "pre", false, "jpg", -1, 1, false, "", $alternative);
            $thumb_url = $thumb;
        }
    }

    $preload = 'auto';
    // preview size adjustments for search
    if ($pagename == "search") {
        switch ($display) {
            case "xlthumbs":
                $width = "350";
                $height = 350 / $ffmpeg_preview_max_width * $ffmpeg_preview_max_height;
                break;
            case "thumbs":
                $width = "150";
                $height = 150 / $ffmpeg_preview_max_width * $ffmpeg_preview_max_height;
                break;
        }
    } else // Not search, standard page
        {
        $width = $ffmpeg_preview_max_width;
        $height = $ffmpeg_preview_max_height;
    }

    // Play video on hover?
    $play_on_hover = false;
    if (
        ($pagename == 'search' && $video_search_play_hover)
        || ($pagename == 'view' && $video_view_play_hover)
        || ($pagename == 'preview' && $video_preview_play_hover)
    ) {
        $play_on_hover = true;
    }

    // Using keyboard hotkeys?
    $playback_hotkeys = false;
    if (
        ($pagename == 'search' && $keyboard_navigation_video_search)
        || ($pagename == 'view' && $keyboard_navigation_video_view)
        || ($pagename == 'preview' && $keyboard_navigation_video_preview)
    ) {
        $playback_hotkeys = true;
    }

    global $ffmpeg_preview_extension,$css_reload_key,$context;
    ?>
    
    <link href="<?php echo $baseurl_short?>lib/videojs/video-js.min.css?r=<?php echo $css_reload_key?>" rel="stylesheet">
    <script src="<?php echo $baseurl_short?>lib/videojs/video.min.js?r=<?php echo $css_reload_key?>"></script>
    <script src="<?php echo $baseurl_short?>js/videojs-extras.js?r=<?php echo $css_reload_key?>"></script>
    <div class="videojscontent">
        <audio 
            id="<?php echo $context ?>_<?php echo $display ?>_introaudio<?php echo $ref?>"
            controls
            data-setup='{<?php
            if ($play_on_hover) {
                // Unlike video we are leaving the controls showing for audio, otherwise we don't get to see the poster image
                ?>
                "loadingSpinner":false, "children": {"bigPlayButton":false}}
                <?php
            } ?>}'
            preload="<?php echo $preload?>"
            width="<?php echo $width?>" 
            height="<?php echo $height?>" 
            class="video-js vjs-default-skin vjs-big-play-centered <?php echo ($pagename == 'search') ? "video-$display" : ''; ?>" 
            poster="<?php echo $thumb_url?>"
            <?php if ($play_on_hover) { ?>
                onmouseout="videojs_<?php echo $context ?>_<?php echo $display ?>_introaudio<?php echo $ref ?>[0].pause();"
                onmouseover="videojs_<?php echo $context ?>_<?php echo $display ?>_introaudio<?php echo $ref ?>[0].play();"
            <?php } ?>
        >
            <source src="<?php echo $mp3path?>" type="audio/mp3" >
            <p class="vjs-no-js">To hear this audio please enable JavaScript, and consider upgrading to a web browser that <a href="http://videojs.com/html5-video-support/" target="_blank">supports HTML5 audio</a></p>

            <?php display_video_subtitles($ref, $GLOBALS['access']); ?>
        </audio>
    </div>

    <?php if ($play_on_hover) { ?>    
        <script>
            var videojs_<?php echo $context ?>_<?php echo $display ?>_introaudio<?php echo $ref ?> = jQuery('#<?php echo $context ?>_<?php echo $display ?>_introaudio<?php echo $ref ?>');
        </script>
    <?php } ?>

    <!-- START DISABLE VIDEOJS RIGHT CONTEXT MENU -->
    <script>
        jQuery('.video-js').bind('contextmenu',function() { return false; });
    </script>
    <!-- END DISABLE VIDEOJS RIGHT CONTEXT MENU -->

    <!-- END VIDEOJS -->
    <?php
}
