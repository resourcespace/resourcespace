<?php

// EMu Constants:
define('EMU_SCRIPT_MODE_IMPORT', 100);
define('EMU_SCRIPT_MODE_SYNC', 1000);

$SCRIPTS = array(
    EMU_SCRIPT_MODE_IMPORT => array(
        'file' => 'emu_script.php',
        'name' => 'EMu import script',
    ),
    EMU_SCRIPT_MODE_SYNC   => array(
        'file' => 'emu_sync_script.php',
        'name' => 'EMu sync script',
    )
);


// ID of user to run scripts as
// Defaults to 1, which normally is Super Admin
$emu_userref = 1;

// EMu Server connection settings
$emu_api_server               = 'http://[server.address]';
$emu_api_server_port          = 25040;

// EMu script
$emu_enable_script              = true;
$emu_script_mode                = null;
$emu_test_mode                  = false;
$emu_email_notify               = '';
$emu_interval_run               = ''; // see http://php.net/manual/en/datetime.formats.relative.php or http://php.net/manual/en/datetime.add.php
$emu_script_failure_notify_days = 3;

// $emu_log_directory              = ''; // This must now must be set in the primary config file
$emu_created_by_script_field    = null;

// EMu settings
// metadata field used to store the EMu identifier (IRN)
$emu_irn_field       = null;
$emu_resource_types  = array();
$emu_search_criteria = '';

/* EMu - ResourceSpace mappings
IMPORTANT: ResourceSpace is expecting an atomic value for the field, anything else will fail

$emu_rs_saved_mappings[module_name][column] = rs_field_id

Example:
$emu_rs_saved_mappings['epublic']['ObjTitle'] = 20;
$emu_rs_saved_mappings['epublic']['ObjName'] = 17;
$emu_rs_saved_mappings['emultimedia']['ChaAspectRatio'] = 32;
*/
$emu_rs_saved_mappings = plugin_encode_complex_configs([]);

// Modifying plugin configs requires bypassing the check for "modifiedTimeStamp" and forces
// ResourceSpace to get all records again so that newly added columns will be updated
$emu_config_modified_timestamp = null;
