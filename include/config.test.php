<?php

###############################
## ResourceSpace
## Test environment Configuration
###############################

if (!defined('RS_TEST_MODE')) {
    define('RS_TEST_MODE', 1);
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Reset ResourceSpace configuration to installation defaults (including new installs)
$saved = [];
$savedconfigs = [
    'mysql_db',
    'mysql_charset',
    'mysql_server',
    'mysql_server_port',
    'mysql_username',
    'mysql_password',
    'read_only_db_username',
    'read_only_db_password',
    'imagemagick_path',
    'ghostscript_path',
    'exiftool_path',
];
foreach ($savedconfigs as $savedconfig) {
    $saved[$savedconfig] = $GLOBALS[$savedconfig];
}

// Save existing $baseurl so tests will still use valid URLs
// $saved_url = $baseurl != "http://my.site/resourcespace" ? $baseurl : "http://localhost";
include RESOURCESPACE_BASE_PATH . '/include/config.default.php';
eval(file_get_contents(RESOURCESPACE_BASE_PATH . '/include/config.new_installs.php'));
// $baseurl = $saved_url;

foreach ($saved as $key => $savedsetting) {
    $GLOBALS[$key] = $savedsetting;
}
// End of reset ResourceSpace configuration

// todo: this will likely end up being set for the Browser (E2E) testing as it will run off PHP built-in server
// @see https://www.php.net/manual/en/features.commandline.webserver.php
$baseurl = 'http://127.0.0.1:8081';

// Database
$mysql_db = 'rs_test_db';
$mysql_charset = 'utf8mb4';
$mysql_force_strict_mode = true;

// Filestore
$storagedir = isset($storagedir) ?  "{$storagedir}/rs_test" : dirname(__DIR__) . '/filestore/rs_test';
$storageurl = isset($storageurl) ?  "{$storageurl}/rs_test" : "{$baseurl}/filestore/rs_test";

if (!file_exists($storagedir)) {
    mkdir($storagedir);
}

$homeanim_folder = 'filestore/rs_test/system/homeanim';

// Logging (for troubleshooting)
// $debug_log = true;
// $debug_extended_info = true;
// $show_detailed_errors = true;
// $debug_log_location = "{$storagedir}/tmp/test-env.log";

// The test framework is capturing exceptions for reporting so make our error handler convert errors to exceptions
$use_error_exception = true;

// API
// $enable_remote_apis = true;
// $api_scramble_key = 'eff7a3c82924ae466757f4582f9a3f85d1c5acc74fcbfda34b0b565614de4d4c';
// $api_upload_urls = ['localhost'];

// General environment configuration
$applicationname = 'ResourceSpace Test Environment';
$scramble_key = '8727b670ece05cd3feeb217a1fc94a2604d4b9983818ba98211d90316ec766b6';
$query_cache_enabled = false;
$password_min_length = 7;
$password_min_alpha = $password_min_numeric = 1;
$password_min_uppercase = $password_min_special = 0;

// BEGIN PEST TEST CONFIGURATION
$GLOBALS['baseurl'] = 'http://127.0.0.1:37619';
$GLOBALS['tempdir'] = '/var/www/html/trunk/filestore/rs_test/tmp/8960f71157391c06f1555eac8c3ec86c';
$GLOBALS['collection_download'] = true;
$GLOBALS['collection_download_original'] = true;
$GLOBALS['collection_download_tar_size'] = 1;
$GLOBALS['offline_job_queue'] = false;
$GLOBALS['download_usage'] = false;
$GLOBALS['terms_download'] = false;
$GLOBALS['exiftool_write'] = false;
$GLOBALS['download_filename_format'] = '%resource.%extension';
// END PEST TEST CONFIGURATION

// Reconnect using the test environment configuration (expects the database to already be provisioned at this stage!)
sql_connect();
