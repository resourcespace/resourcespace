<?php

// Check either resource file integrity or presence only depending on config

include_once __DIR__ . "/../../include/boot.php";
include_once __DIR__ . "/../../include/image_processing.php";
command_line_only();

if (!$GLOBALS["file_integrity_checks"]) {
    exit($GLOBALS["lang"]["function_not_enabled"] . " (\$file_integrity_checks = false)" . PHP_EOL);
}

// CLI options check
$maxresources = 0;
$lastchecked = 0;
$silence_notices = false;
$update_old = false;
$clear_lock = false;
$cli_long_options  = [
    'help',
    'lastchecked:',
    'maxresources:',
    'silence-notices',
    'update-old',
    'clear-lock',
];

foreach (getopt('', $cli_long_options) as $option_name => $option_value) {
    if ($option_name == 'help') {
        echo $argv[0] . " - used to Check either resource file integrity or presence only depending on config.\n\n";
        echo "Script options\n\n";
        echo "    --lastchecked [number of days]\n    Only check resources not validated in the last X days\n\n";
        echo "    --maxresources [number]\n    Maximum number of resources to check\n\n";
        echo "    --silence-notices\n    Don't send notifications to admins\n\n";
        echo "    --update-old\n    Update resources that failed due to checksums generated with an old setup (i.e. \$file_checksums_50k)\n\n";
        echo "    --clear-lock\n    Clear the existing process lock\n\n";
        exit(1);
    } elseif ($option_name == 'lastchecked') {
        $lastchecked = (int) $option_value;
    } elseif ($option_name == 'maxresources') {
        $maxresources = (int) $option_value;
    } elseif ($option_name === 'silence-notices') {
        $silence_notices = true;
    } elseif ($option_name === 'update-old') {
        $update_old = true;
    } elseif ($option_name === 'clear-lock') {
        $clear_lock = true;
    }
}

if ($clear_lock) {
    echo 'Clearing process lock' . PHP_EOL;
    clear_process_lock('file_integrity_check');
} elseif (is_process_lock("file_integrity_check")) {
    exit(" - File integrity process lock is in place.Skipping.\n");
}
set_process_lock("file_integrity_check");

$resources = get_resources_to_validate($lastchecked);
$current_config_fails = $negated_config_fails = $failures = [];
if (count($resources) > 0) {
    if ($maxresources > 0) {
        $resources = array_slice($resources, 0, $maxresources);
    }

    /*
    Validate both "versions" of the checksum:
    - current configuration (ideally this is full file checksum)
    - with old configuration setup by negating the $file_checksums_50k

    Failing both of these checks is a confirmed fail. If the resource had an old checksum (i.e. not running the
    update_checksum script) then the admin has the option to update those particular records.
    */
    logScript("Validating " . count($resources) . " resources.");
    $current_config_fails = check_resources($resources);
    if (count($current_config_fails) > 0) {
        logScript('The following resources failed: ' . implode(', ', $current_config_fails));
        logScript('Validating ' . count($current_config_fails) . ' resources (with old config setup - !$file_checksums_50k)');

        $orig_file_checksums_50k = $GLOBALS['file_checksums_50k'];
        $GLOBALS['file_checksums_50k'] = !$GLOBALS['file_checksums_50k'];
        $negated_config_fails = check_resources(
            array_filter($resources, fn($val) => in_array($val['ref'], $current_config_fails))
        );
        $GLOBALS['file_checksums_50k'] = $orig_file_checksums_50k;
        if (count($negated_config_fails) > 0) {
            logScript('The following resources failed: ' . implode(', ', $negated_config_fails));
        }

        $resources_w_old_checksums = array_diff($current_config_fails, $negated_config_fails);
        if ($resources_w_old_checksums !== []) {
            logScript('The following resources have an old checksum recorded: ' . implode(', ', $resources_w_old_checksums));
            if ($update_old) {
                logScript('Updating old checksums...');
                foreach ($resources as $resource) {
                    if (!in_array($resource['ref'], $resources_w_old_checksums)) {
                        continue;
                    }

                    if (generate_file_checksum($resource['ref'], $resource['file_extension'], true)) {
                        logScript("- Key for {$resource['ref']} generated");
                    } else {
                        logScript("- Key for {$resource['ref']} NOT generated");
                    }
                }
            }
        }
    }

    $failures = array_intersect($current_config_fails, $negated_config_fails);
    if (count($failures) > 0) {
        logScript('The following resources failed (confirmed): ' . implode(', ', $failures));
        if (!$silence_notices) {
            send_integrity_failure_notices($failures);
        }
    }
}

clear_process_lock("file_integrity_check");
logScript("Finished validating " . count($resources) . " resources. There were " . count($failures) . " failures");
