<?php

// Included from preview_preprocessing.php
use Montala\ResourceSpace\CommandPlaceholderArg;

# Increase time limit
set_time_limit(0);

$ffmpeg_fullpath = get_utility_path("ffmpeg");

if ($generateall) {
    # Create a preview video
    $targetfile = get_resource_path($ref, true, "pre", false, $ffmpeg_preview_extension, -1, 1, false, "", $alternative);

    # If target for preview file already exists, remove it
    if (file_exists($targetfile)) {
        unlink($targetfile);
    }

    if (PHP_SAPI !== "cli") {
        set_processing_message(str_replace("[resource]", $ref, $lang["processing_preview_video"]));
    }

    if (file_exists($target)) {
        $snapshotsize = getimagesize($target);
        $width = $snapshotsize[0];
        $height = $snapshotsize[1];
    } else {
        # Get preview sizes from DB
        $preview_sizes = get_all_image_sizes(true);

        $pre_size = array_values(array_filter($preview_sizes, function ($var) {
            return $var['id'] == 'pre';
        }));

        $width = $pre_size[0]['width'];
        $height = $pre_size[0]['height'];
    }
   
    $sourcewidth = $width;
    $sourceheight = $height;
    global $config_windows, $ffmpeg_get_par;
    if ($ffmpeg_get_par) {
        $par = 1;
        # Find out the Pixel Aspect Ratio
        $shell_exec_cmd = $ffmpeg_fullpath . " -i %%FILE%% 2>&1";
        $shell_exec_params = ["%%FILE%%" => new CommandPlaceholderArg($file, 'is_valid_rs_path')];

        if (isset($ffmpeg_command_prefix)) {
            $shell_exec_cmd = $ffmpeg_command_prefix . " " . $shell_exec_cmd;
        }

        $output = run_command($shell_exec_cmd, false, $shell_exec_params);

        preg_match('/PAR ([0-9]+):([0-9]+)/m', $output, $matches);

        if (intval($matches[1] ?? 0) > 0 && intval($matches[2] ?? 0) > 0) {
            $par = $matches[1] / $matches[2];
            if ($par < 1) {
                $width = ceil($width * $par);
            } elseif ($par > 1) {
                $height = ceil($height / $par);
            }
        }
    }

    if ($height < $ffmpeg_preview_min_height) {
        $height = $ffmpeg_preview_min_height;
    }

    if ($width < $ffmpeg_preview_min_width) {
        $width = $ffmpeg_preview_min_width;
    }

    if ($height > $ffmpeg_preview_max_height) {
        $width = ceil($width * ($ffmpeg_preview_max_height / $height));
        $height = $ffmpeg_preview_max_height;
    }

    if ($width > $ffmpeg_preview_max_width) {
        $height = ceil($height * ($ffmpeg_preview_max_width / $width));
        $width = $ffmpeg_preview_max_width;
    }

    # Frame size must be a multiple of two
    if ($width % 2) {
        $width++;
    }
    if ($height % 2) {
        $height++;
    }

    /* Plugin hook to modify the output W & H before running ffmpeg. Better way to return both W and H at the same is appreciated.  */
    $tmp = hook("ffmpegbeforeexec", "", array($ffmpeg_fullpath, $file));
    if (is_array($tmp) && $tmp) {
        list($width, $height) = $tmp;
    }

    if (hook("replacetranscode", "", array($file,$targetfile,$ffmpeg_global_options,$ffmpeg_preview_options,$width,$height))) {
        exit(); // Do not proceed, replacetranscode hook intends to avoid everything below
    }

    if ($extension == 'gif') {
        global $ffmpeg_preview_gif_options;
        $ffmpeg_preview_options = $ffmpeg_preview_gif_options;
    }

    $shell_exec_cmd = $ffmpeg_fullpath . " $ffmpeg_global_options -y -loglevel error -i %%FILE%% " . $ffmpeg_preview_options . " -t %%SECONDS%% -s %%WIDTH%%x%%HEIGHT%% %%TARGETFILE%%";

    $shell_exec_params = [
        "%%FILE%%" => new CommandPlaceholderArg($file, 'is_valid_rs_path'),
        "%%SECONDS%%" => (int) $ffmpeg_preview_seconds,
        "%%WIDTH%%" => (int) $width,
        "%%HEIGHT%%" => (int) $height,
        "%%TARGETFILE%%" => new CommandPlaceholderArg($targetfile, 'is_safe_basename'),
    ];

    if (isset($ffmpeg_command_prefix)) {
        $shell_exec_cmd = $ffmpeg_command_prefix . " " . $shell_exec_cmd;
    }

    $tmp = hook("ffmpegmodpreparams", "", [$shell_exec_cmd, $ffmpeg_fullpath, $file, $shell_exec_params]);
    if ($tmp) {
        $shell_exec_cmd = $tmp;
    }
    run_command($shell_exec_cmd, false, $shell_exec_params);

    if (
        $ffmpeg_get_par
        && (
            isset($snapshotcheck)
            && !$snapshotcheck
        )
        && $par > 0
        && $par <> 1
    ) {
        # recreate snapshot with correct PAR
        $width = $sourcewidth;
        $height = $sourceheight;
        if ($par < 1) {
            $width = ceil($sourcewidth * $par);
        } elseif ($par > 1) {
            $height = ceil($sourceheight / $par);
        }
        # Frame size must be a multiple of two
        if ($width % 2) {
            $width++;
        }
        if ($height % 2) {
            $height++;
        }

        $shell_exec_cmd = "$ffmpeg_fullpath $ffmpeg_global_options -y -loglevel error -i %%FILE%% -s %%WIDTH%%x%%HEIGHT%%  %%TARGETFILE%% -f image2 -vframes 1 -ss %%SNAPSHOTTIME%% %%TARGETFILE%%";
        $shell_exec_params = [
            "%%FILE%%" => new CommandPlaceholderArg($file, 'is_safe_basename'),
            "%%WIDTH%%" => (int) $width,
            "%%HEIGHT%%" => (int) $height,
            "%%SNAPSHOTTIME%%" => (int) $snapshottime,
            "%%TARGETFILE%%" => new CommandPlaceholderArg($targetfile, 'is_safe_basename'),
        ];

        run_command($shell_exec_cmd, false, $shell_exec_params);
    }

    if (!file_exists($targetfile)) {
        debug("FFmpeg failed: " . $shell_exec_cmd);
    }

    // Handle alternative files.
    global $ffmpeg_alternatives;
    if (isset($ffmpeg_alternatives) && $generateall) {
        $ffmpeg_alt_previews = array();
        for ($n = 0; $n < count($ffmpeg_alternatives); $n++) {
            $generate = true;
            if (
                isset($ffmpeg_alternatives[$n]["lines_min"])
                // If this alternative size is larger than the source, do not generate.
                && $ffmpeg_alternatives[$n]["lines_min"] >= $sourceheight
            ) {
                $generate = false;
            }
            if (PHP_SAPI !== "cli") {
                set_processing_message(str_replace(["[resource]","[name]"], [$ref,$ffmpeg_alternatives[$n]["name"]], $lang["processing_alternative_video"]));
            }

            $tmp = hook("preventgeneratealt", "", [$file]);
            if ($tmp === true) {
                $generate = false;
            }

            if ($generate) {
                // Remove any existing alternative file(s) with this name.
                $existing = ps_query("select ref from resource_alt_files where resource = ? and name = ?", array("i", $ref, "s", $ffmpeg_alternatives[$n]["name"]));
                for ($m = 0; $m < count($existing); $m++) {
                    delete_alternative_file($ref, $existing[$m]["ref"]);
                }

                $alt_type = '';
                if (isset($ffmpeg_alternatives[$n]['alt_type'])) {
                    $alt_type = $ffmpeg_alternatives[$n]["alt_type"];
                }

                # Create the alternative file.
                $aref = add_alternative_file($ref, $ffmpeg_alternatives[$n]["name"], '', '', '', 0, $alt_type);
                $apath = get_resource_path($ref, true, "", true, $ffmpeg_alternatives[$n]["extension"], -1, 1, false, "", $aref);

                # Process the video
                $shell_exec_cmd = "$ffmpeg_fullpath $ffmpeg_global_options -y -loglevel error -i %%FILE%% " . $ffmpeg_alternatives[$n]["params"] . " %%TARGETFILE%%";
                $shell_exec_params = [
                    "%%FILE%%" => new CommandPlaceholderArg($file, 'is_safe_basename'),
                    "%%WIDTH%%" => (int) $width,
                    "%%HEIGHT%%" => (int) $height,
                    "%%SNAPSHOTTIME%%" => (int) $snapshottime,
                    "%%TARGETFILE%%" => new CommandPlaceholderArg($apath, 'is_safe_basename'),
                ];

                $tmp = hook("ffmpegmodaltparams", "", array($shell_exec_cmd, $ffmpeg_fullpath, $file, $n, $aref, $shell_exec_params));
                if ($tmp) {
                    $shell_exec_cmd = $tmp;
                }
                run_command($shell_exec_cmd, false, $shell_exec_params);

                if (file_exists($apath)) {
                    # Update the database with the new file details.
                    $file_size = filesize_unlimited($apath);
                    ps_query(
                        "UPDATE resource_alt_files
                                 SET file_name = ?,
                                     file_extension = ?,
                                     file_size = ?,
                                     creation_date = NOW()
                               WHERE ref = ?",
                        [
                        "s", $ffmpeg_alternatives[$n]["filename"] . "." . $ffmpeg_alternatives[$n]["extension"],
                        "s", $ffmpeg_alternatives[$n]["extension"],
                         "i", $file_size, "i", $aref
                        ]
                    );
                    // add this filename to be added to resource.ffmpeg_alt_previews
                    if (isset($ffmpeg_alternatives[$n]['alt_preview']) && $ffmpeg_alternatives[$n]['alt_preview']) {
                        $ffmpeg_alt_previews[] = basename($apath);
                    }
                } else {
                    # Remove the alternative file entries with this name as ffmpeg has failed to create file.
                    $existing = ps_query("SELECT ref FROM resource_alt_files WHERE resource = ? AND name = ?", array("i", $ref, "s", $ffmpeg_alternatives[$n]["name"]));
                    for ($m = 0; $m < count($existing); $m++) {
                        delete_alternative_file($ref, $existing[$m]["ref"]);
                    }
                }
            }
        }
    }
}
