<?php
/*
Job handler to run generate_merged_video() to combine videos together via the splice plugin

Requires the following job data:-
$job_data['videos'] - all the videos to merge
$job_data['target_video_command'] - the chosen video encoding command
$job_data['target_video_extension'] - the chosen video encoding extension
$job_data['target_audio'] - the chosen audio encoding
$job_data['target_width'] - the chosen video resolution width
$job_data['target_height'] - the chosen video resolution height
$job_data['target_frame_rate'] - the chosen video frame rate
$job_data['description'] - the description to add to the new resource if supplied
$job_data['auto_populate_video_info'] - whether the user wants the video information added to the new resource e.g. bitrate, framerate
$offline = $job_data['offline'] - if the jobs offline or not as function works for both
*/

include_once __DIR__ . "/../include/splice_functions.php";

global $offline_job_delete_completed, $baseurl;

// Grab job data
$videos = $job_data['videos'];
$video_splice_type = $job_data['video_splice_type'];
$target_video_command = $job_data['target_video_command'];
$target_video_extension = $job_data['target_video_extension'];
$target_audio = $job_data['target_audio'];
$target_width = $job_data['target_width'];
$target_height = $job_data['target_height'];
$target_frame_rate = $job_data['target_frame_rate'];
$description = $job_data['description'];
$auto_populate_video_info = $job_data['auto_populate_video_info'];
$offline = $job_data['offline'];

// Run function
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

// If information has been returned then successfull. This can be a ref number or a file lcoation
if($return_info)
    {
    $video_refs = array_column($videos, 'ref');

    // If new resource then return_info is a ref number to make a link to view page
    if($video_splice_type == "video_splice_save_new")
        {
        $link_holder = $baseurl . "/pages/view.php?ref=" . $return_info;
        }

    // If downloading then return_info is a filelocation to make a link to download
    if($video_splice_type == "video_splice_download")
        {
        $link_holder = $baseurl . "/pages/download.php?userfile=" . $return_info . "&filename=" . implode("-", $video_refs) . "_" . pathinfo($return_info,PATHINFO_FILENAME);
        }

    // Job tidy up
    if($offline_job_delete_completed)
        {
        job_queue_delete($jobref);
        }
    else
        {
        job_queue_update($jobref,$job_data,STATUS_COMPLETE);
        }

    // Final message is the success text specified on job creation in splice.php and link if supplied above
    message_add($job["user"], $job_success_text, isset($link_holder)?$link_holder:null);
    }
else
    {
    // If we get here something is wrong
    job_queue_update($jobref,$job_data,STATUS_ERROR);
    message_add($job["user"], $job_failure_text);
    }