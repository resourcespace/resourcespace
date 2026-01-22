<?php
include_once dirname(__DIR__) . '/include/museumplus_functions.php';
include_once dirname(__DIR__) . '/include/museumplus_resource_functions.php';
include_once dirname(__DIR__) . '/include/museumplus_search_functions.php';

############################################################
### Constants ##############################################
############################################################
define('MPLUS_LOCK', 'museumplus_import');
define('MPLUS_LAST_IMPORT', 'last_museumplus_import');
define('MPLUS_LAST_LOG_TRUNCATE', 'last_museumplus_log_truncate');
define('MPLUS_FIELD_ID', '__id'); # This field holds the technical ID of a module item. @see http://docs.zetcom.com/ws/


############################################################
### MuseumPlus API (MpRIA) #################################
############################################################
$museumplus_host = '';
$museumplus_host_api = '';
$museumplus_application = ''; # Retained for older M+ URLs where the application name must be supplied separately. Likely blank for newer setups.
$museumplus_api_user = '';
$museumplus_api_pass = '';

// Batch chunk size for Mplus API requests (e.g for expert searches).
// IMPORTANT: Do not expose this to the end user. If you need to adjust it, add it to config.php. @type is integer
$museumplus_api_batch_chunk_size = (isset($GLOBALS['museumplus_api_batch_chunk_size']) && is_int($GLOBALS['museumplus_api_batch_chunk_size']) && $GLOBALS['museumplus_api_batch_chunk_size'] > 0 ? $GLOBALS['museumplus_api_batch_chunk_size'] : 50);


############################################################
### ResourceSpace settings #################################
############################################################
$museumplus_module_name_field = 0;
// Field to hold extra secondary links. RS will just generate appropriate M+ URLs based on each link. Secondary links 
// have a special syntax format: <module name>:<technical ID> e.g. "Object:1234".
$museumplus_secondary_links_field = 0;

// Should updating a resource association data -or- "unlinking" a resource from a module record clear the data of the mapped fields in RS?
// IMPORTANT: Do not expose this to the end user. If you need to adjust it, add it to config.php. @type is boolean
$museumplus_clear_field_mappings_on_change = (isset($GLOBALS['museumplus_clear_field_mappings_on_change']) && is_bool($GLOBALS['museumplus_clear_field_mappings_on_change']) ? $GLOBALS['museumplus_clear_field_mappings_on_change'] : false);

// Show a custom top navigation menu with a direct link to the special search (!mplus_invalid_assoc)
// IMPORTANT: Do not expose this to the end user. If you need to adjust it, add it to config.php and/or user group config overrides. @type is boolean
$museumplus_top_nav = (isset($GLOBALS['museumplus_top_nav']) && is_bool($GLOBALS['museumplus_top_nav']) ? $GLOBALS['museumplus_top_nav'] : true);

// Truncate the museumplus_log table at regular intervals (default 7 days)
// IMPORTANT: Do not expose this to the end user. If you need to adjust it, add it to config.php. @type is int
$museumplus_truncate_log_interval = (isset($GLOBALS['museumplus_truncate_log_interval']) && is_int($GLOBALS['museumplus_truncate_log_interval']) && $GLOBALS['museumplus_truncate_log_interval'] > 0 ? $GLOBALS['museumplus_truncate_log_interval'] : 7);

// List of user groups that can bypass denying write access to the mapped fields.
// IMPORTANT: Do not expose this to the end user. If you need to adjust it, add it to config.php. @type is array
$museumplus_ug_bypass_F_perm = (isset($GLOBALS['museumplus_ug_bypass_F_perm']) && is_array($GLOBALS['museumplus_ug_bypass_F_perm']) ? $GLOBALS['museumplus_ug_bypass_F_perm'] : []);


############################################################
### Script settings ########################################
############################################################
$museumplus_enable_script = true;
$museumplus_interval_run = '+1 day'; // see http://php.net/manual/en/datetime.formats.relative.php or http://php.net/manual/en/datetime.add.php
// $museumplus_log_directory = '';  // This must now must be set in the primary config file
$museumplus_script_failure_notify_days = 3;
$museumplus_integrity_check_field = 0; # NOT IN USE until we can reliably get integrity checks of the data from M+. Even __lastModified can't guarantee us some of the virtual fields haven't been changed from other modules.


############################################################
### MuseumPlus - ResourceSpace modules setup ###############
############################################################
$museumplus_modules_saved_config = plugin_encode_complex_configs([
    1 => [
        'module_name' => 'Object',
        'mplus_id_field' => '',
        'rs_uid_field' => 0,
        'applicable_resource_types' => [],
        'field_mappings' => [],
    ]
]);
