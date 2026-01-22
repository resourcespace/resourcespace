<?php

if ('cli' != PHP_SAPI) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Access denied');
}

include __DIR__ . '/../../../include/boot.php';
include_once __DIR__ . '/../../../include/image_processing.php';
include_once __DIR__ . '/../include/emu_functions.php';
include_once __DIR__ . '/../include/emu_api.php';

// Init
ob_end_clean();
set_time_limit($cron_job_time_limit);
define('EMU_SCRIPT_SYNC_LOCK', 'emu_sync_mode_lock');

$test_skip_multiple    = 0;
$emu_rs_mappings       = plugin_decode_complex_configs($emu_rs_saved_mappings);
$emu_script_start_time = microtime(true);
$emu_records_limit     = 100;
$emu_records_data      = array();

$cli_short_options = 'hc';
$cli_long_options  = array(
    'help',
    'clearlock',
    'emu_test_mode:',
    'emu_userref:'
);

// Use log file if set
// IMPORTANT: running this script directly (without going via cron_copy_hitcount) may
// not guarantee log directory is set properly, with required permissions in place
$emu_log_file = null;
if ('' != $emu_log_directory) {
    $emu_log_file = fopen($emu_log_directory . DIRECTORY_SEPARATOR . 'emu_script_log_sync_' . date('d_m_Y__H_i') . '.log', 'ab');
}

// Last chance to make sure this is what you want
if (EMU_SCRIPT_MODE_SYNC != $emu_script_mode) {
    emu_script_log('ALERT - Trying to run script with wrong script mode set.', $emu_log_file);

    exit();
}

// CLI options check
foreach (getopt($cli_short_options, $cli_long_options) as $option_name => $option_value) {
    if (in_array($option_name, array('h', 'help'))) {
        emu_script_log('To clear the lock after a failed run, pass in "--clearlock", "-clearlock", "-c" or "--c"', $emu_log_file);
        exit();
    }

    if (
        in_array($option_name, array('c', 'clearlock'))
        && is_process_lock(EMU_SCRIPT_SYNC_LOCK)
    ) {
        clear_process_lock(EMU_SCRIPT_SYNC_LOCK);
    }

    // Allow CLI options to override $emu_* variables
    if ('emu' != substr($option_name, 0, 3)) {
        continue;
    }

    $$option_name = $option_value;
}

// Check for a process lock
if (is_process_lock(EMU_SCRIPT_SYNC_LOCK)) {
    echo 'EMu script lock is in place. Deferring.' . PHP_EOL . 'To clear the lock after a failed run use --clearlock flag.' . PHP_EOL;

    $emu_script_failed_subject = ($emu_test_mode ? 'TESTING MODE: ' : '') . 'EMu Import script - FAILED';
    send_mail($email_notify, $emu_script_failed_subject, "The EMu script failed to run because a process lock was in place. This indicates that the previous run did not complete.\r\n\r\nIf you need to clear the lock after a failed run, run the script as follows:\r\n\r\nphp emu_script.php --clearlock\r\n", $email_from);
    exit();
}
set_process_lock(EMU_SCRIPT_SYNC_LOCK);

// Run script as a user of the system
if (isset($emu_userref)) {
    $userref  = $emu_userref;
    $userdata = get_user($userref);

    setup_user($userdata);
}

emu_script_log('Starting...', $emu_log_file);
$GLOBALS['use_error_exception'] = true;
try {
    $emu_api = new EMuAPI($emu_api_server, $emu_api_server_port);
} catch (Throwable $t) {
    emu_script_log(PHP_EOL . $t->getMessage(), $emu_log_file);
    exit();
}

// Step 1 - Build the EMu Objects Data array
emu_script_log(PHP_EOL . 'Building EMu found records data array', $emu_log_file);
foreach ($emu_rs_mappings as $emu_module => $emu_module_columns) {
    $emu_api->setModule($emu_module);

    $columns_list = array_keys($emu_module_columns);

    $emu_api->setColumns($columns_list);

    // Build where clause
    $search_terms = new IMuTerms();
    if (!check_config_changed()) {
        $script_last_ran = ps_value('SELECT `value` FROM sysvars WHERE name = "last_emu_import"', [], '');

        if ('' != $script_last_ran) {
            $search_terms->add('modifiedTimeStamp', emu_format_date(strtotime($script_last_ran)), '>');
        }
    }
    $search_terms = add_search_criteria($search_terms);
    $emu_api->setTerms($search_terms);

    $emu_records_found = $emu_api->runSearch();

    // Skip modules that did not return any records back
    if (0 >= $emu_records_found) {
        emu_script_log("Skip module '{$emu_module}' as it didn't return any records back for search criteria provided", $emu_log_file);
        continue;
    }

    emu_script_log("Found {$emu_records_found} records that match your search criteria in '{$emu_module}' module", $emu_log_file);

    $emu_api->setColumns(array('multimedia'));
    $columns_list[] = 'multimedia';

    $offset = 0;

    while ($offset < $emu_records_found) {
        // Get objects data in batches otherwise you get End of Stream Exception
        $objects_data = $emu_api->getSearchResults($offset, $emu_records_limit);

        foreach ($objects_data as $object_data) {
            foreach ($object_data as $object_data_column => $object_data_column_value) {
                if (!in_array($object_data_column, $columns_list)) {
                    continue;
                }

                $emu_records_data[$object_data['irn']][$object_data_column] = $object_data_column_value;
            }
        }

        // Ready to go to the next batch
        $offset += $emu_records_limit;
    }
}

// Step 2 - Get existing ResourceSpace resources with an IRN set
emu_script_log(PHP_EOL . 'Finding existing ResourceSpace resources with an IRN set', $emu_log_file);
/*
Example:
Array
(
    [0] => Array
        (
            [resource] => 1
            [object_irn] => 74766
            [created_by_script_flag] =>
            [file_checksum] => 02b7d23bcd4eba8491049f56a64fe046
        )

    [1] => Array
        (
            [resource] => 5
            [object_irn] => 904207
            [created_by_script_flag] => script
            [file_checksum] => be17ac47cc132f4dbb13e7b2633ea898
        )
)

*/
$rs_emu_resources       = get_emu_resources();
$rs_emu_resources_count = count($rs_emu_resources);
emu_script_log("Found {$rs_emu_resources_count} resources in ResourceSpace with IRN set.", $emu_log_file);

/*
Using step 1 & 2 to figure out how many:
- existing resources created by SCRIPT and not found in the latest search need to be archived,
- new resources need to be added,
- multimedia files need to be added as alternatives in ResourceSpace because checksums don't match anymore
- resources simply just need their metadata updated
*/
if (0 < $rs_emu_resources_count) {
    emu_script_log(PHP_EOL . 'Archiving resources', $emu_log_file);

    $GLOBALS['use_error_exception'] = true;
    try {
        $emu_api_expired_resources = new EMuAPI($emu_api_server, $emu_api_server_port);
    } catch (Throwable $t) {
        emu_script_log(PHP_EOL . $t->getMessage(), $emu_log_file);
        exit();
    }

    foreach ($rs_emu_resources as $rs_emu_resource) {
        if (array_key_exists($rs_emu_resource['object_irn'], $emu_records_data)) {
            continue;
        }

        if ('SCRIPT' != $rs_emu_resource['created_by_script_flag']) {
            continue;
        }

        foreach (array_keys($emu_rs_mappings) as $module_name) {
            $emu_api_expired_resources->setModule($module_name);
            $emu_api_expired_resources->setIrnColumn();

            $search_terms = new IMuTerms();
            $search_terms->add('irn', $rs_emu_resource['object_irn']);
            $search_terms = add_search_criteria($search_terms);
            $emu_api_expired_resources->setTerms($search_terms);

            // A record has been found so skip this resource
            if (0 < $emu_api_expired_resources->runSearch()) {
                emu_script_log("Skipping resource ID {$rs_emu_resource['resource']} because a record was found when searching by IRN {$rs_emu_resource['object_irn']} and search criteria", $emu_log_file);

                continue 2;
            }
        }

        emu_script_log("Resource ID {$rs_emu_resource['resource']} is being archived because IRN {$rs_emu_resource['object_irn']} was not found in the last search result set", $emu_log_file);

        if ($emu_test_mode) {
            emu_script_log("SQL: UPDATE resource SET archive = '2' WHERE ref = '{$rs_emu_resource['resource']}'", $emu_log_file);
        } else {
            ps_query("UPDATE resource SET archive = '2' WHERE ref = ?", ["i",$rs_emu_resource['resource']]);
        }
    }
}

// Step 3 - Process new/ existing resources (also add the original file (master) if required)
emu_script_log(PHP_EOL . 'Process new/ existing resources', $emu_log_file);
foreach ($emu_records_data as $emu_record_irn => $emu_record_fields) {
    ##########################################
    ## TODO: CRITICAL - comment once tested ##
    ##########################################
    // if(5 > $test_skip_multiple)
    //     {
    //     $emu_test_mode = false;
    //     }
    // else
    //     {
    //     $emu_test_mode = true;
    //     }

    // $test_skip_multiple++;
    #########################################
    #########################################

    $irn_index_in_rs_emu_resources = array_search($emu_record_irn, array_column($rs_emu_resources, 'object_irn'));

    if (false === $irn_index_in_rs_emu_resources) {
        // Processing new resource
        emu_script_log('################################################################################', $emu_log_file);
        emu_script_log("Processing new resource for IRN {$emu_record_irn}", $emu_log_file);

        if ($emu_test_mode) {
            emu_script_log('Test mode: Cannot create new resource, update metadata and import media file while testing', $emu_log_file);

            continue;
        }

        // Add as default resource type
        // Note: when processing multimedia files, the system will try and detect what resource type it should be based
        // on the extension of the file and update it using update_resource_type()
        $new_resource_ref = create_resource(
            $resource_type_extension_mapping_default,
            0,
            $userref,
            $lang["emu_createdfromemu"]
        );

        if (!$new_resource_ref) {
            emu_script_log("Could not create new resource for IRN {$emu_record_irn}", $emu_log_file);

            continue;
        }

        emu_script_log("Created new resource with ID {$new_resource_ref}", $emu_log_file);

        // Update metadata fields for this resource
        if (update_field($new_resource_ref, $emu_irn_field, $emu_record_irn)) {
            emu_script_log("Set EMu IRN field value to '{$emu_record_irn}' for resource ID {$new_resource_ref}", $emu_log_file);
        }

        if (update_field($new_resource_ref, $emu_created_by_script_field, 'SCRIPT')) {
            emu_script_log("Set created by script field value to 'SCRIPT' for resource ID {$new_resource_ref}", $emu_log_file);
        }

        if (emu_update_resource_metadata_from_record($new_resource_ref, $emu_record_fields, $emu_rs_mappings)) {
            emu_script_log("Updated resource ID {$new_resource_ref} metadata from EMu record for IRN '{$emu_record_irn}'", $emu_log_file);
        }

        // Add master multimedia file as original file to the newly created resource
        // Grab master multimedia file from EMu
        if (0 === count($emu_record_fields['multimedia'])) {
            emu_script_log("No multimedia files found for resource with IRN {$emu_record_irn}", $emu_log_file);

            continue;
        }

        $emu_master_file = array();

        // Order multimedia files ascending by their IRN
        // This means we always consider the first multimedia file (ie. lowest IRN of a multimedia record) the original file
        $multimedia_irns = array();
        foreach ($emu_record_fields['multimedia'] as $emu_multimedia_record_key => $emu_multimedia_record) {
            $multimedia_irns[$emu_multimedia_record_key] = $emu_multimedia_record['irn'];
        }
        array_multisort($multimedia_irns, SORT_ASC, $emu_record_fields['multimedia']);

        foreach ($emu_record_fields['multimedia'] as $emu_multimedia_record) {
            // Once we found a master file, we don't need to look for others
            if (0 !== count($emu_master_file)) {
                break;
            }

            $emu_master_file = $emu_api->getObjectMultimediaByIrn($emu_multimedia_record['irn']);
        }

        if (0 === count($emu_master_file)) {
            emu_script_log("Could not get any of the multimedia files for IRN {$emu_record_irn}. Found multimedia files were: "
                . PHP_EOL
                . print_r($emu_record_fields['multimedia'], true), $emu_log_file);

            continue;
        }

        // Get the path for the file we are downloading (generate path)
        $rs_emu_file_path = get_resource_path($new_resource_ref, true, '', true, pathinfo($emu_master_file['resource']['identifier'], PATHINFO_EXTENSION));

        emu_script_log("Preparing to download media file to {$rs_emu_file_path}", $emu_log_file);

        if (EMuAPI::getMediaFile($emu_master_file, $rs_emu_file_path)) {
            emu_script_log('Sucessfully downloaded media file', $emu_log_file);

            // Update basic resource/ file data and create previews
            if (
                emu_update_resource(
                    $new_resource_ref,
                    get_resource_type_from_extension(
                        pathinfo($emu_master_file['resource']['identifier'], PATHINFO_EXTENSION),
                        $resource_type_extension_mapping,
                        $resource_type_extension_mapping_default
                    ),
                    $rs_emu_file_path
                )
            ) {
                emu_script_log('Sucessfully created previews', $emu_log_file);
            } else {
                emu_script_log('Failed tp create previews', $emu_log_file);
            }
        } else {
            emu_script_log('Failed to download media file', $emu_log_file);
        }

        continue;
    }

    // Processing existing resource
    $existing_resource_ref  = $rs_emu_resources[$irn_index_in_rs_emu_resources]['resource'];
    $existing_file_checksum = $rs_emu_resources[$irn_index_in_rs_emu_resources]['file_checksum'];

    emu_script_log('################################################################################', $emu_log_file);
    emu_script_log("Processing existing resource for IRN {$emu_record_irn}", $emu_log_file);
    emu_script_log("IRN {$emu_record_irn} was found at index {$irn_index_in_rs_emu_resources} in \$rs_emu_resources, proof: ", $emu_log_file);
    foreach ($rs_emu_resources[$irn_index_in_rs_emu_resources] as $key => $value) {
        emu_script_log(" * {$key} = {$value}", $emu_log_file);
    }
    emu_script_log('and will need to be updated (metadata and multimedia checksum checked)', $emu_log_file);

    // Update metadata for this resource and then check media file checksum. If != then add media file from EMu as alternative for this resource
    if (!$emu_test_mode && emu_update_resource_metadata_from_record($existing_resource_ref, $emu_record_fields, $emu_rs_mappings)) {
        emu_script_log("Updated resource ID {$existing_resource_ref} metadata from EMu record for IRN '{$emu_record_irn}'", $emu_log_file);
    } elseif ($emu_test_mode) {
        emu_script_log('Test mode: updating resource metadata fields based on EMu record column values', $emu_log_file);
    } else {
        emu_script_log("Failed to update resource ID {$existing_resource_ref} metadata from EMu record for IRN '{$emu_record_irn}'", $emu_log_file);
    }

    emu_script_log("Checking multimedia file for resource ID {$existing_resource_ref}...", $emu_log_file);

    if (0 === count($emu_record_fields['multimedia'])) {
        emu_script_log("No multimedia files found for resource with IRN {$emu_record_irn}", $emu_log_file);

        continue;
    }

    $emu_master_info = array();
    $emu_master_file = array();

    // Order multimedia files ascending by their IRN
    // This means we always consider the first multimedia file (ie. lowest IRN of a multimedia record) the original file
    $multimedia_irns = array();
    foreach ($emu_record_fields['multimedia'] as $emu_multimedia_record_key => $emu_multimedia_record) {
        $multimedia_irns[$emu_multimedia_record_key] = $emu_multimedia_record['irn'];
    }
    array_multisort($multimedia_irns, SORT_ASC, $emu_record_fields['multimedia']);

    foreach ($emu_record_fields['multimedia'] as $emu_multimedia_record) {
        // Once we found a master file, we don't need to look for others
        if (0 !== count($emu_master_info)) {
            break;
        }

        $emu_master_info = $emu_api->getObjectMultimediaByIrn($emu_multimedia_record['irn'], array('master'));
    }

    if (0 === count($emu_master_info) || !isset($emu_master_info['master'])) {
        emu_script_log("Could not get any of the multimedia master information for IRN {$emu_record_irn}. Found multimedia files were: "
            . PHP_EOL
            . print_r($emu_record_fields['multimedia'], true), $emu_log_file);

        continue;
    }

    // New file is being used as master, add it as an alternative for this resource
    if ($existing_file_checksum != $emu_master_info['master']['md5Checksum']) {
        emu_script_log('Checksums are not the same. Adding new file as alternative...', $emu_log_file);

        if ($emu_test_mode) {
            emu_script_log('Test mode: cannot move forward and create alternatives.'
                . PHP_EOL
                . 'Skipping to next step...', $emu_log_file);

            continue;
        }

        $emu_master_file           = $emu_api->getObjectMultimediaByIrn($emu_master_info['irn']);
        $emu_master_file_extension = pathinfo($emu_master_file['resource']['identifier'], PATHINFO_EXTENSION);
        $alternative_ref           = add_alternative_file(
            $existing_resource_ref,
            'New file found',
            ($date_d_m_y ? date('d/m/Y @ H:m') : date('m/d/Y @ H:m')),
            $emu_master_file['resource']['identifier'],
            $emu_master_file_extension,
            $emu_master_file['resource']['size'],
            $emu_master_file['resource']['mimeType']
        );

        if (!$alternative_ref) {
            emu_script_log("Could not create an alternative record for media file with IRN {$emu_master_info['irn']}", $emu_log_file);
            continue;
        }

        $rs_emu_file_path = get_resource_path($existing_resource_ref, true, '', true, $emu_master_file_extension, -1, 1, false, '', $alternative_ref);

        if (EMuAPI::getMediaFile($emu_master_file, $rs_emu_file_path)) {
            emu_script_log("New media file downloaded as an alternative to {$rs_emu_file_path}", $emu_log_file);

            create_previews($existing_resource_ref, false, $emu_master_file_extension, false, false, $alternative_ref);
        }
    }
} /* end of Process new/ existing resources */

emu_script_log(PHP_EOL . sprintf("EMu Script completed in %01.2f seconds.", microtime(true) - $emu_script_start_time), $emu_log_file);
fclose($emu_log_file);

clear_process_lock(EMU_SCRIPT_SYNC_LOCK);

ps_query('DELETE FROM sysvars WHERE name = "last_emu_import"', []);
ps_query('INSERT INTO sysvars VALUES ("last_emu_import", NOW())', []);
clear_query_cache("sysvars");
