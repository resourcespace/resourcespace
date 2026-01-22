<?php

// This script is used to add new related resources from a CSV file

include_once __DIR__ . "/../../include/boot.php";
command_line_only();

function show_script_help()
{
    echo PHP_EOL . $_SERVER["SCRIPT_FILENAME"] . " - add new related resources from the provided CSV file

    - The CSV used must have no header row
    - The first CSV column must contain a single resource ID
    - The second CSV column must contain a comma separated list of all the resource IDs to relate
      This column must be quoted so that the separating commas are not interpreted as part of the CSV structure
    - Existing relationships for the first resource will be kept and any new resources will be added

    Script options

        -c --csv [path to CSV file]
                           Full path to CSV file

        -d --dryrun
                           Only show commands that will be run" . PHP_EOL;
    exit(1);
}

// CLI options check
$csv_path = "";
$dryrun = false;
$cli_long_options  = array(
    'csv:',
    'dryrun::',
);
$cli_short_options = "c:d";

$options = getopt($cli_short_options, $cli_long_options);
foreach ($options as $option_name => $option_value) {
    if ($option_name == 'help') {
        show_script_help();
    }
    if ($option_name === 'csv' || $option_name === "c") {
        $csv_path = trim($option_value);
    }
    if ($option_name === "dryrun" || $option_name === "d") {
        $dryrun = true;
    }
}

if ($csv_path === "") {
    show_script_help();
}

echo PHP_EOL . "Processing CSV file: '" . $csv_path . "'" . PHP_EOL;
echo "Dry run :     " . ($dryrun ? "TRUE" : "FALSE") . PHP_EOL . PHP_EOL;

if (!file_exists($csv_path)) {
    exit("ERROR: Could not read CSV file. Check file permissions" . PHP_EOL);
}

ob_start();
setup_command_line_user();


// Check for BOM and skip if present
$bom = "\xef\xbb\xbf";
$csvfile = fopen($csv_path, "r");
if (fgets($csvfile, 4) !== $bom) {
    // BOM not found
    rewind($csvfile);
}

$errors = [];
$completed = 0;
$curline = 0;
while (($line = fgetcsv($csvfile)) !== false) {
    $curline++;
    if (count($line) != 2) {
        // check that the current row has the correct number of columns
        $errors[] = "Incorrect number of columns(" . count($line) . ") found on line " . $curline . " (should be 2)";
        continue;
    }
    $resource = (int) trim($line[0], "\"");
    $related = trim($line[1], "\"");

    $resdata = get_resource_data($resource);
    if (!$resdata) {
        $errors[] = "Invalid resource ID: " . $resource . " specified on line " . $curline;
        continue;
    }
    $torelate = array_filter(explode(",", trim($related, "\"")), "is_int_loose");

    $success = false;
    if (!$dryrun) {
        $success = update_related_resource($resource, $torelate, true);
    }

    if ($success) {
        echo " - Updated resource " . $resource . ". Added related resources: " . implode(",", $torelate) . PHP_EOL;
        ++$completed;
    } elseif ($dryrun) {
        echo " - Update resource " . $resource . ". Add related resources: " . implode(",", $torelate) . PHP_EOL;
        ++$completed;
    } else {
        $errors[] = "Failed to update resource " . $resource . ". Possible invalid related resource ID specified in line " . $curline;
    }
        ob_flush();
}

if (!$dryrun) {
    echo PHP_EOL . "Finished. Successfully updated " . $completed . " resource(s)." . PHP_EOL;
} else {
    echo PHP_EOL . "Finished. Script would update " . $completed . " resource(s)." . PHP_EOL;
}

if (count($errors) > 0) {
    echo PHP_EOL . "There were " . count($errors) . " errors encountered: " . PHP_EOL . " - " . implode(PHP_EOL . " - ", $errors) . PHP_EOL;
}
