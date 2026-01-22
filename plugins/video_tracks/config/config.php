<?php
// video_tracks plugin configuration file

// Auto convert alternative SRT subtitle files to VTT for use in previews?
$video_tracks_convert_vtt = true;
$video_tracks_download_export=false;
$video_tracks_audio_extensions=array("mp3","wav","m4a","ogg");
$video_tracks_subtitle_extensions=array("vtt","srt");
$video_tracks_permitted_video_extensions=array("mp4","avi","wmv","mpeg","mov","mkv", "flv","mpg");
$video_tracks_process_size_limit=100;

$video_tracks_allow_original_custom_formats = false;

$video_tracks_output_formats_default = array(
    "mp4" => array(
        "command" => "-f mp4 -ar 22050 -b 650k -ab 32k -ac 1",
        "extension" => "mp4",
    )             
);