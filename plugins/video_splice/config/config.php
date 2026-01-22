<?php
// Default configs
$videosplice_resourcetype = '3';
$videosplice_description_field = 18;
$videosplice_video_bitrate_field = 77;
$videosplice_video_size_field = 79;
$videosplice_frame_rate_field = 76;
$videosplice_aspect_ratio_field = 78;
$videosplice_allowed_extensions = array("mp4","mov","flv","mpeg","mpg");

// This array stops the fields being deleted if plugin is in use
$videosplice_fieldvars = array(
    "videosplice_description_field",
    "videosplice_video_bitrate_field",
    "videosplice_video_size_field",
    "videosplice_frame_rate_field",
    "videosplice_aspect_ratio_field"
);

// Used for video splice plugins to decide commands used for video output.
// SECURITY NOTE: This option should not be exposed to the user as these are run unescaped as they are actual options
$ffmpeg_std_video_options = $GLOBALS["ffmpeg_std_video_options"] ?? array(
    "MP4 700kbps"=>array(
        "command"=>"mp4 -b:v 700k -crf 20",
        "extension"=>"mp4"),
    "MP4 1500kbps"=>array(
        "command"=>"mp4 -b:v 1500k -crf 20",
        "extension"=>"mp4"),
    "MP4 2500kbps"=>array(
        "command"=>"mp4 -b:v 2500k -crf 20",
        "extension"=>"mp4",
        "default"=>true),
    "MP4 4000kbps"=>array(
        "command"=>"mp4 -b:v 4000k -crf 20",
        "extension"=>"mp4"),
        );

// Used for video splice plugins to decide commands used for audio output.
// SECURITY NOTE: This option should not be exposed to the user as these are run unescaped as they are actual options
$ffmpeg_std_audio_options = $GLOBALS["ffmpeg_std_audio_options"] ?? array(
    "AAC 32 kbps 22.05 kHz Mono"=>array(
        "command"=>"-acodec aac -b:a 32k -ar 22050 -ac 1"),
    "AAC 64 kbps 44.1 kHz Mono"=>array(
        "command"=>"-acodec aac -b:a 64k -ar 44100 -ac 1",
        "default"=>true),
    "AAC 64 kbps 48 kHz Mono"=>array(
        "command"=>"-acodec aac -b:a 64k -ar 48000 -ac 1"),
    "AAC 96 kbps 44.1 kHz Mono"=>array(
        "command"=>"-acodec aac -b:a 96k -ar 44000 -ac 1"),
    "AAC 96 kbps 48 kHz Mono"=>array(
        "command"=>"-acodec aac -b:a 96k -ar 48000 -ac 1"),
    "MP3 64 kbps 22.05 kHz Mono"=>array(
        "command"=>"-acodec mp3 -b:a 64k -ar 22050 -ac 1"),
    "MP3 96 kbps 44.1 kHz Mono"=>array(
        "command"=>"-acodec mp3 -b:a 96k -ar 44100 -ac 1"),
    "MP3 96 kbps 48 kHz Mono"=>array(
        "command"=>"-acodec mp3 -b:a 96k -ar 48000 -ac 1"),
    "MP3 128 kbps 44.1 kHz Mono"=>array(
        "command"=>"-acodec mp3 -b:a 128k -ar 44100 -ac 1"),
    "MP3 128 kbps 48 kHz Mono"=>array(
        "command"=>"-acodec mp3 -b:a 128k -ar 48000 -ac 1")
        );

// Used by video splice plugin to decide common resolution for all video files to be merged
$ffmpeg_std_resolution_options = $GLOBALS["ffmpeg_std_resolution_options"] ?? array(
    "2560 x 1440"=>array(
        "width"=>"2560",
        "height"=>"1440"),
    "1920 x 1080"=>array(
        "width"=>"1920",
        "height"=>"1080"),
    "1280 x 720"=>array(
        "width"=>"1280",
        "height"=>"720",
        "default"=>true),
    "960 x 540"=>array(
        "width"=>"960",
        "height"=>"540"),
    "854 x 480"=>array(
        "width"=>"854",
        "height"=>"480"),
    "640 x 360"=>array(
        "width"=>"640",
        "height"=>"360"),
    "480 x 270"=>array(
        "width"=>"480",
        "height"=>"270"),
    "426 x 240"=>array(
        "width"=>"426",
        "height"=>"240")
    );

// Used by video splice plugin to decide common frame rate for all video files to be merged
$ffmpeg_std_frame_rate_options = $GLOBALS["ffmpeg_std_frame_rate_options"] ?? array(
    "23.98(fps)"=>array(
        "value"=>"23.98"),
    "30(fps)"=>array(
        "value"=>"30",
        "default"=>true),
    "60(fps)"=>array(
        "value"=>"60")
    );

// Folder location used for video exports in video track and video splice plugins.
// Video tracks will use its own config if provided to keep backwards compatibility.
$video_export_folder = $GLOBALS["video_export_folder"] ?? "";
