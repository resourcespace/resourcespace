<?php

/*
Runs a job to create an alternative file from the specified command
Requires the following job data:-
$job_data["resource"] -  Resource ID
$job_data["alt_name"] - name of alternative file
$job_data["alt_description"] - description of alternative file
$job_data["alt_extension"] - extension of alternative file
$job_data["command"] - command to create the file, must have %%TARGETFILE%%" as a placeholder since we haven't created the ID alternative file yet

e.g.

'/usr/bin/ffmpeg'  -i /var/www_rs/include/../filestore/1/8/0/0/4/0_eb6ff5c241414l/110040_c246d960fbde50e.mp4 -t 11 -i /var/www/include/../filestore/1/8/0/0/4/0_eb6ff5c241414l/180040_alt_537_d5029d98370c888.mp3 -map 0:v -map 1:a -vf subtitles=/var/www_rs/include/../filestore/1/8/0/0/4/0_eb6ff5c241414l/180040_alt_533_dedf5ed7d156119.srt %%TARGETFILE%%

*/

include_once __DIR__ . "/../image_processing.php";
global $filename_field, $offline_job_prefixes;
$jobsuccess = false;
$job_cmd_ok = false;


$resource = get_resource_data($job_data["resource"]);
$origfilename = get_data_by_field($job_data["resource"], $filename_field);
$randstring = md5(rand() . microtime());

$newaltfile = add_alternative_file($job_data["resource"], $job_data["alt_name"], $job_data["alt_description"], str_replace("." . $resource["file_extension"], "." . $job_data["alt_extension"], $origfilename), $job_data["alt_extension"]);
$targetfile = get_resource_path($job_data["resource"], true, "", false, $job_data["alt_extension"], -1, 1, false, "", $newaltfile);
$shell_exec_cmd = $job_data["command"];
$shell_exec_params = $job_data["command_params"];
$shell_exec_params[$job_data["output_file_placeholder"]] = $targetfile;

// Check we are using a whitelisted command path to create file
foreach ($offline_job_prefixes as $offline_job_prefix) {
    $cmd_path = get_utility_path($offline_job_prefix);
    if (substr($shell_exec_cmd, 0, strlen($cmd_path)) == $cmd_path) {
        $job_cmd_ok = true;
        break;
    }
}

// Skip if any other unwanted characters in command (|,<,>,!,&,#,; or `)
if ($job_cmd_ok && !preg_match("/(\||<|>|;|!|&|#|`)/i", $shell_exec_cmd)) {
    global $config_windows;
    if ($config_windows) {
        $shell_exec_cmd = str_replace(array_keys($shell_exec_params), array_values($shell_exec_params), $shell_exec_cmd);
        file_put_contents(get_temp_dir() . "/create_alt_" . $randstring . ".bat", $shell_exec_cmd);
        echo "Running command " . $shell_exec_cmd . PHP_EOL;
        $shell_exec_cmd = get_temp_dir() . "/create_alt_" . $randstring . ".bat";
        $shell_exec_params = [];
        $deletebat = true;
    }
    $output = run_command($shell_exec_cmd, false, $shell_exec_params);
    if (file_exists($targetfile)) {
        $newfilesize = filesize_unlimited($targetfile);
        ps_query("update resource_alt_files set file_size = ? where resource = ? and ref = ?", array("i", $newfilesize, "i", $job_data["resource"], "i", $newaltfile));
        global $alternative_file_previews, $lang, $baseurl, $view_title_field, $offline_job_delete_completed;
        if ($alternative_file_previews) {
            create_previews($job_data["resource"], false, $job_data["alt_extension"], false, false, $newaltfile);
        }
        $message = ($job_success_text != "") ? $job_success_text : $lang["alternative_file_created"] . ": " . str_replace(array('%ref','%title'), array($job_data['resource'],$resource['field' . $view_title_field]), $lang["ref-title"]) . "(" . $job_data["alt_name"] . "," . $job_data["alt_description"] . ")";
        message_add($job["user"], $message, $GLOBALS["baseurl_short"] . "?r=" . $job_data["resource"], 0);
        if ($offline_job_delete_completed) {
            job_queue_delete($jobref);
        } else {
            job_queue_update($jobref, $job_data, STATUS_COMPLETE);
        }
        $jobsuccess = true;
    }

    if (isset($deletebat) && file_exists($shell_exec_cmd)) {
        unlink($shell_exec_cmd);
    }
}

if (!$jobsuccess) {
    // Job failed, upate job queue
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    $message = ($job_success_text != "") ? $job_success_text : $lang["alternative_file_creation_failed"] . ": " . str_replace(array('%ref','%title'), array($job_data['resource'],$resource['field' . $view_title_field]), $lang["ref-title"]) . "(" . $job_data["alt_name"] . "," . $job_data["alt_description"] . ")";
    message_add($job["user"], $message, $baseurl_short . "?r=" . $job_data["resource"], 0);
}
