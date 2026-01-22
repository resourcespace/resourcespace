#!/usr/bin/php
<?php

if (PHP_SAPI != 'cli') {
    exit("Command line execution only.");
}

include __DIR__ . "/../include/boot.php";
include_once __DIR__ . "/../include/image_processing.php";

# Prevent this script from creating offline jobs for tasks such as extracting text.
# Offline jobs shouldn't be created here as they require a valid user ref to be processed.
# This is running offline anyway so no need to create more jobs.
$offline_job_queue = false;

$ignoremaxsize = false;
$noimage = false;
if ($argc >= 2) {
    $validargs = false;
    if (in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
        echo "To clear the lock after a failed run, ";
        echo "pass in '-clearlock'\n";
        echo "To ignore the maximum preview size configured ($preview_generate_max_file_size), ";
        echo "pass in '-ignoremaxsize'.\n";
        exit("Bye!");
    }
    if (in_array('-ignoremaxsize', $argv)) {
        $ignoremaxsize = true;
        $validargs = true;
    }
    if (in_array('-noimage', $argv)) {
        $noimage = true; #
        $validargs = true;
    }
    if (in_array('-clearlock', $argv)) {
        if (is_process_lock("create_previews")) {
            clear_process_lock("create_previews");
        }
        $validargs = true;
    }
    if (!$validargs) {
        exit("Unknown argv: " . $argv[1]);
    }
}


# Check for a process lock
if (is_process_lock("create_previews")) {
    exit("Process lock is in place. Deferring.");
}
set_process_lock("create_previews");

if (function_exists("pcntl_signal")) {
    $multiprocess = true;
} else {
    $multiprocess = false;
}

// We store the start date.
$global_start_time = microtime(true);

// We define the number of threads.
$max_forks = 3;

$lock_directory = '.';

// We create an array to store children pids.
$children = array();

/**
 * This function clean up the list of children pids.
 * This allow to detect the freeing of a thread slot.
 */
function reap_children()
{
    global $children;

    $tmp = array();

    foreach ($children as $pid) {
        if (pcntl_waitpid($pid, $status, WNOHANG) != $pid) {
            array_push($tmp, $pid);
        }
    }

    $children = $tmp;

    return count($tmp);
} // reap_children()



/**
 * This function is used to process SIGALRM signal.
 * This is usefull when the parent process is killed.
 */
function sigalrm_handler()
{
    die("[SIGALRM] hang in thumbnails creation ?\n");
}



/**
 * This function is used to process SIGCHLD signal.
 *
 */
function sigchld_handler($signal)
{
    reap_children();

    pcntl_waitpid(-1, $status, WNOHANG);
}



/**
 * This function is used to process SIGINT signal.
 *
 */
function sigint_handler()
{
    die("[SIGINT] exiting.\n");
}


// We define the functions to use for signal handling.
if ($multiprocess) {
    pcntl_signal(SIGALRM, 'sigalrm_handler');
    pcntl_signal(SIGCHLD, 'sigchld_handler');
}
$sql = "SELECT ref,
               file_extension,
               IFNULL(preview_attempts, 1) preview_attempts,
               creation_date
          FROM resource 
         WHERE ref > 0
           AND no_file <> 1
           AND (preview_attempts < ? OR preview_attempts IS NULL)
           AND file_extension IS NOT NULL
           AND LENGTH(file_extension) > 0
           AND LOWER(file_extension) NOT IN (" . ps_param_insert(count($no_preview_extensions)) . ")";
$params = array_merge(["i", SYSTEM_MAX_PREVIEW_ATTEMPTS], ps_param_fill($no_preview_extensions, "s"));

$extraconditions = "";
if (!$noimage) {
    $extraconditions .= " AND has_image != ? ";
    $params[] = "i";
    $params[] = RESOURCE_PREVIEWS_ALL;
}

$resources = ps_query($sql . $extraconditions, $params);

foreach ($resources as $resource) { // For each resources
  // We wait for a fork emplacement to be freed.
    if ($multiprocess) {
        while (count($children) >= $max_forks) {
        // We clean children list.
            reap_children();
            sleep(1);
        }
    }

    if (!$multiprocess || count($children) < $max_forks) { // Test if we can create a new fork.
    // fork
        if (!$multiprocess) {
            $pid = false;
        } else {
            $pid = pcntl_fork();
        }

        if ($pid == -1) {
            die("fork failed!\n");
        } elseif ($pid) {
            array_push($children, $pid);
        } else {
            if ($multiprocess) {
                pcntl_signal(SIGCHLD, SIG_IGN);
                pcntl_signal(SIGINT, SIG_DFL);
            }

          // Processing resource.
            echo sprintf("Processing resource id " . $resource['ref'] . " - preview attempt #" . $resource['preview_attempts'] . "\n");

            $start_time = microtime(true);

          // For each fork, we need a new connection to database.
            sql_connect();

          # Below added to catch an issue with previews failing when large video files were taking a long time to copy to StaticSync location
            echo "Created at: " . $resource['creation_date'] . "\nTime now: " . date("Y-m-d H:i:s") . "\n";
            $resourceage = time() - strtotime($resource['creation_date']);
            if ($resource['preview_attempts'] > 3 && $resourceage < 1000) {
                echo "Just added so may not have finished copying, resetting attempts \n";
                ps_query("UPDATE resource SET preview_attempts = 0 WHERE ref = ?", array("i", $resource['ref']));
                continue;
            }

          #check whether resource already has mp3 preview in which case we set preview_attempts to 5
            if (
                $resource['file_extension'] != "mp3"
                && in_array($resource['file_extension'], $ffmpeg_audio_extensions)
                && file_exists(get_resource_path($resource['ref'], true, "", false, "mp3"))
            ) {
                $ref = $resource['ref'];
                echo "Resource already has mp3 preview\n";
                ps_query("UPDATE resource SET preview_attempts = 5 WHERE ref = ?", array("i", $ref));
            } elseif ($resource['preview_attempts'] < 5 && $resource['file_extension'] != "") {
                if (!empty($resource['file_path'])) {
                    $ingested = false;
                } else {
                    $ingested = true;
                }

            # Increment the preview count.
                  ps_query("UPDATE resource SET preview_attempts = IFNULL(preview_attempts, 1) + 1 WHERE ref = ?", array("i", $resource['ref']));

                  $success = create_previews($resource['ref'], false, $resource['file_extension'], false, false, -1, $ignoremaxsize, $ingested);
                  hook('after_batch_create_preview');
                  $success_string = ($success ? "successfully" : "with error" );
                  echo sprintf("Processed resource %d %s in %01.2f seconds.\n", $resource['ref'], $success_string, microtime(true) - $start_time);
            }

            if ($multiprocess) {
                // We exit in order to avoid fork bombing.
                exit(0);
            }
        }
    } // Test if we can create a new fork
} // For each resources

// We wait for all forks to exit.
if ($multiprocess) {
    while (count($children)) {
      // We clean children list.
        reap_children();
        sleep(1);
    }
}

echo sprintf("Completed in %01.2f seconds.\n", microtime(true) - $global_start_time);

clear_process_lock("create_previews");
