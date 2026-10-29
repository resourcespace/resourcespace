<?php

// Test of staticsync functionality
command_line_only();

// Staticsync affects this so keep a copy and restore it later
$saved_user_data    = $userdata;
$saved_userref      = $userref;
$saved_perms        = $userpermissions;
$staticsync_test_perms = array("s","g","j*");
$userpermissions    = $staticsync_test_perms;
if (isset($unoconv_path)) {
    $saved_unoconv_path = $unoconv_path;
    // Disable unoconv previews as this is not being tested here and failures can interrupt test
    unset($unoconv_path);
}
$saved_alternative_file_previews = $alternative_file_previews;
$saved_enable_thumbnail_creation_on_upload = $enable_thumbnail_creation_on_upload;

// Ensure the environment configuration isn't preventing other use cases from passing (e.g. test A)
if (isset($staticsync_file_minimum_age)) {
    $saved_staticsync_file_minimum_age = $staticsync_file_minimum_age;
    unset($staticsync_file_minimum_age);
}

// Command line args are used by staticsync so need to save them
$savedargv = $argv;
$savedargc = $argc;

// Set up staticsync to use a folder and make sure it exists.
$syncdir = $storagedir . "/staticsync/";
if (file_exists($syncdir)) {
    rcRmdir($syncdir);
}

mkdir($syncdir);
// Set up test path
$test_path = $syncdir . "test_folder/featured/";
if (!file_exists($test_path)) {
    mkdir($test_path, 0777, true);
}

// Set our test config
$staticsync_userref = $userref;
$theme_category_levels = 20;
$staticsync_ingest = true;
$staticsync_autotheme = true;
$enable_thumbnail_creation_on_upload = false;
$alternative_file_previews = false;

// Create file to sync
copy(__DIR__ . '/../../gfx/homeanim/1.jpg', $test_path . "teststatic.jpg");

// Required for test L ($nogo)
$nogo = '[to_skip]';
$test_nogo_path = $syncdir . "to_skip/";
if (!file_exists($test_nogo_path)) {
    mkdir($test_nogo_path, 0777, true);
}
copy(__DIR__ . '/../../gfx/homeanim/1.jpg', $test_nogo_path . "skipped.jpg");

// Required for test D
$staticsync_extension_mapping[2] = array("txt");
file_put_contents($test_path . "testtextsync.txt", "TEST");

// Required for test F
$sync_tree_field = create_resource_type_field("Sync tree", 0, FIELD_TYPE_CATEGORY_TREE, "synctree", true);
$staticsync_mapped_category_tree = $sync_tree_field;

// Required for Test G, H, and M
$projectspath = $test_path . "projects/conferenceA/";
if (!file_exists($projectspath)) {
    mkdir($projectspath, 0777, true);
}
file_put_contents($projectspath . "projecta.txt", "TEST");
$project_field = create_resource_type_field("Sync Project", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "syncproject", true);

$landscapepath = $test_path . "landscape/mountains/" . get_resource_types()[0]["name"];
if (!file_exists($landscapepath)) {
    mkdir($landscapepath, 0777, true);
}
file_put_contents($projectspath . "mountaina.txt", "TEST");
$landscape_field = create_resource_type_field("Landscape", get_resource_types()[0]["ref"], FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "landscape", true);

$staticsync_mapfolders[] = array (
    "match" => "/projects/",
    "field" => $project_field,
    "level" => 4
);
$staticsync_mapfolders[] = array (
"match" => "/landscape/",
"field" => "resource_type",
"level" => 5
);
$staticsync_mapfolders[] = array (
    "match" => "/landscape/",
    "field" => 148,
    "level" => 4
);

// Required for Test I
$alternativespath = $projectspath . "projecta.txt_alternatives/";
if (!file_exists($alternativespath)) {
    mkdir($alternativespath, 0777, true);
}
file_put_contents($alternativespath . "projecta_alt1.txt", "TEST_ALT1");
file_put_contents($alternativespath . "projecta_alt2.txt", "TEST_ALT2");

// Required for Test J
$staticsync_alternative_file_text = "_alt_";
$aftpath = $test_path . "altfiletext/";
if (!file_exists($aftpath)) {
    mkdir($aftpath, 0777, true);
}
file_put_contents($aftpath . "aft_primary.txt", "TEST_AFT_PRIMARY");
file_put_contents($aftpath . "aft_primary_alt_testj.txt", "TEST_AFT_ALT");

$userpermissions = $saved_perms; # Restore permissions temporarily for creation of / upload to resource.
$existing_resource_for_alt = create_resource(1, 0);
file_put_contents($aftpath . "existing.txt", "EXISTING - file for a resource to be created");
upload_file($existing_resource_for_alt, true, false, false, $aftpath . "existing.txt", false, false);
unlink($aftpath . "existing.txt");
$userpermissions = $staticsync_test_perms; # Reapply custom permissions set earlier.
file_put_contents($aftpath . "existing_alt_testj.txt", "ALT_EXISTING - alternative file to add to existing resource with staticsync");

// Required for Test K
$staticsync_alt_suffixes = true;
$staticsync_alt_suffix_array = array (
   '_odd' => "Odd file",
   '_side' => "Sidecar file",
    );

$altsuffixpath = $test_path . "alt_suffixes/";
if (!file_exists($altsuffixpath)) {
    mkdir($altsuffixpath, 0777, true);
}
file_put_contents($altsuffixpath . "alt_suffix_primary.txt", "TEST_ALT_SUFFIX_PRIMARY");
file_put_contents($altsuffixpath . "alt_suffix_primary_odd.txt", "TEST_ALT_SUFFIX_ODD");
file_put_contents($altsuffixpath . "alt_suffix_primary_side.txt", "TEST_ALT_SUFFIX_SIDE");

// Required for Test M


// Run staticsync, but hold back the output (has to be an include not a PHP exec so the above test config is used)
$argv = array();
$argc = 0;
ob_flush();
ob_start();
$staticsync_suppress_output = true;
include __DIR__ . "/../../pages/tools/staticsync.php";
ob_end_clean();

// Test A: check the file has gone
if (file_exists($test_path . "teststatic.jpg")) {
    echo "Test A failed; File was not ingested.";
    return false;
}

// Test B: Check that a search for the filename returns a result
$results = do_search("teststatic");
if (!is_array($results) || count($results) == 0) {
    echo "Test B failed: ingested file could not be found.";
    return false;
}

$resid = $results[0]["ref"];

// Test C: Check that $staticsync_autotheme worked
$fcs = get_featured_collections(0, array("access_control" => false));
$testfc = array_search("Test_folder", array_column($fcs, "name"));
if ($testfc === false) {
    echo "Test C: Featured collection 'Test_folder' not created by \$staticsync_autotheme - ";
    return false;
}

// Test D: Check that $staticsync_autotheme created the correct sub featured collection
$subfcs = get_featured_collections($fcs[$testfc]["ref"], array("access_control" => false));
$featuredfc = array_search("Featured", array_column($subfcs, "name"));
if ($featuredfc === false) {
    echo "Test D: Featured collection 'Featured' not created by \$staticsync_autotheme - ";
    return false;
}

// Test E -check $staticsync_extension_mapping
$results = do_search("testtextsync");
if (!is_array($results) || count($results) == 0 || $results[0]["resource_type"] != 2) {
    echo "Test E failed: \$staticsync_extension_mapping failed";
    return false;
}

// Test F - $staticsync_mapped_category_tree
$treedata = get_data_by_field($resid, $sync_tree_field, false);
if (!is_array($treedata) || count($treedata) != 2 || "test_folder/featured" !== implode('/', array_column($treedata, 'name'))) {
    echo "Test F failed: \$staticsync_mapped_category_tree failed - ";
    return false;
}

// Test G,H - Check extracting data using $staticsync_mapfolders works
$results = do_search("projecta");
if (!is_array($results) || count($results) == 0) {
    echo "Test G failed: \$staticsync_extension_mapping failed - ";
    return false;
}
$projectresource = $results[0]["ref"];
$mappeddata = get_data_by_field($projectresource, $project_field);
if (trim($mappeddata) != "conferenceA") {
    echo "Test H failed: \$staticsync_mapfolders failed - ";
    return false;
}

// Test I - staticsync_alternatives_suffix
$alts_i = get_alternative_files($projectresource);
if (!is_array($alts_i) || count($alts_i) != 2) {
    echo "Test I failed: \$staticsync_alternatives_suffix failed - ";
    return false;
}

// Test J - staticsync_alternative_file_text
$results = do_search("aft_primary");
if (!is_array($results) || count($results) == 0) {
    echo "Test J failed: ingested file 'aft_primary.txt' could not be found - ";
    return false;
}
$aft_resource = $results[0]["ref"];
$alts_j = get_alternative_files($aft_resource);
if (!is_array($alts_j) || count($alts_j) != 1 || $alts_j[0]["description"] != "testj") {
    echo "Test J failed: \$staticsync_alternative_file_text failed - ";
    return false;
}
if (file_exists($aftpath . "existing_alt_testj.txt")) {
    echo "Test J failed: \$staticsync_alternative_file_text failed to find existing resource for alternative -";
    return false;
}

// Test K - staticsync_alt_suffix_array
$results = do_search("alt_suffix_primary");
if (!is_array($results) || count($results) != 1) {
    echo "Test K failed: ingested file 'alt_suffix_primary.txt' could not be found - ";
    return false;
}
$altsuffix_resource = $results[0]["ref"];
$alts_k = get_alternative_files($altsuffix_resource);
if (
    !is_array($alts_k)
    ||
    count($alts_k) != 2
    ||
    !match_values(array_column($alts_k, 'name'), array_values($staticsync_alt_suffix_array))
) {
    echo "Test K failed: \$staticsync_alt_suffix_array failed - ";
    return false;
}

// Test L - $nogo : Confirm resource was not created from "skipped.jpg"
$results = do_search("skipped");
if (is_array($results) && count($results) > 0) {
    echo "Test L failed: File in \$nogo location was imported - ";
    return false;
}

// Test M - apply resource type specific field data from mapped folders
$results = do_search("mountains");
if (is_array($results) && count($results) != 1) {
    echo "Test M failed: Resource Type Specific data not applied from mapped folders - ";
    return false;
}

$userref            = $saved_userref;
$userpermissions    = $saved_perms;
$userdata           = $saved_user_data;
$argv = $savedargv;
$argc = $savedargc;
if (isset($unoconv_path)) {
    $unoconv_path = $saved_unoconv_path;
}
$alternative_file_previews = $saved_alternative_file_previews;
$enable_thumbnail_creation_on_upload = $saved_enable_thumbnail_creation_on_upload;

if (isset($saved_staticsync_file_minimum_age)) {
    $staticsync_file_minimum_age = $saved_staticsync_file_minimum_age;
    unset($saved_staticsync_file_minimum_age);
}

return true;
