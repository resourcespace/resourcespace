<?php

command_line_only();

$enable_thumbnail_creation_on_upload = false; // Don't need previews here
$saved_file_integrity_checks = $GLOBALS["file_integrity_checks"];
$GLOBALS["file_integrity_checks"] = true;
$GLOBALS["valid_upload_paths"][] = realpath(__DIR__ . "/../../");
ps_query("TRUNCATE resource");

// Create resources with files
$test_image_type = create_resource_type("15000 image resources");

$test15000_resources = [];
$test15000_resources[0] = create_resource($test_image_type, 0);
$test15000_resources[1] = create_resource($test_image_type, 0);
$test15000_resources[2] = create_resource($test_image_type, 0);

foreach ($test15000_resources as $resource) {
    upload_file($resource, true, false, false, __DIR__ . "/../../gfx/watermark.png", false, false);
}

// Test A - Check only for presence of main resource files
$result = validate_resource_files($test15000_resources, ["file_exists" => true]);
if (array_search(false, $result) !== false) {
    echo " Test A - Failed to validate presence of resource files";
    return false;
}

// Test B - Missing resource file
$path = get_resource_path($test15000_resources[1], true, '', false, 'png');
unlink($path);
$result = validate_resource_files($test15000_resources, ["file_exists" => true]);
if (array_search(false, $result) !== $test15000_resources[1]) {
    echo " Test B - Failed to detect absence of resource file";
    return false;
}

// Test C - Check resource type in $file_integrity_ignore_resource_types
$ignore_image_type = create_resource_type("15000 ignore resource type");
$file_integrity_ignore_resource_types = [$ignore_image_type];
update_resource_type($test15000_resources[1], $ignore_image_type);
$tovalidate = get_resources_to_validate();
if (in_array($test15000_resources[1], array_column($tovalidate, "ref"))) {
    echo " Test C - get_resources_to_validate() returned resource of type in \$file_integrity_ignore_resource_types array";
    return false;
}


// Test D - Check resource in worflow state that is also in $file_integrity_ignore_states
$file_integrity_ignore_states = [2];
update_archive_status($test15000_resources[2], 2);
$tovalidate = get_resources_to_validate();
if (in_array($test15000_resources[2], array_column($tovalidate, "ref"))) {
    echo " Test D - get_resources_to_validate() returned resource with workflow state in \$file_integrity_ignore_states array";
    return false;
}

// Test E - Validate resource checksums
$file_checksums = true;
$file_checksums_offline = false;
upload_file($test15000_resources[2], true, false, false, __DIR__ . "/../../documentation/licenses/resourcespace.txt", false, false);
generate_file_checksum($test15000_resources[0], "png");
generate_file_checksum($test15000_resources[2], "txt");

$file_integrity_ignore_states = [];
$file_integrity_ignore_resource_types = [];

// Corrupt a file by adding text
$path = get_resource_path($test15000_resources[2], true, '', false, 'txt');
file_put_contents($path, "_corrupt", FILE_APPEND | LOCK_EX);

$tovalidate = get_resources_to_validate();
$results = check_resources($tovalidate); // Defaults to full checks
if (count($results) > 2) {
    echo " Test E - Incorrectly detected corrupted files";
    return false;
} elseif (!in_array($test15000_resources[2], $results)) {
    echo " Test E - failed to detect corrupted file";
    return false;
}

// Check validate_resource_files script can handle old (changed) config setup
$validate_resource_files_script = function (array $args) {
    $argv = $args;
    $argc = count($argv);
    include dirname(__DIR__, 2) . '/pages/tools/validate_resource_files.php';
};
$orig_file_checksums_50k = $file_checksums_50k;
$resource_ut = create_resource($test_image_type, 0);
upload_file($resource_ut, true, false, false, __DIR__ . '/../../gfx/watermark.png', false, false);
$file_checksums_50k = false;
generate_file_checksum($resource_ut, 'png');
$file_checksums_50k = $orig_file_checksums_50k; # simulate config change
$script_result = cast_echo_to_string($validate_resource_files_script, [[]]);
$found_log = preg_match('/old checksum recorded: (\d+(?: ?, ?\d+)*)/', $script_result, $script_result_matches);
if (!$found_log || !in_array($resource_ut, explode(', ', $script_result_matches[1]))) {
    echo 'Test F: Failed to detect old config setup - ';
    test_log("validate_resource_files_script results >>>\n{$script_result}<<<");
    test_log("script_result_matches: " . print_r($script_result_matches, true));
    test_log('--- ');
    return false;
}

$GLOBALS["file_integrity_checks"] = $saved_file_integrity_checks;
return true;
