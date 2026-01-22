<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

$tms_link_modules_mappings = unserialize(base64_decode($tms_link_modules_saved_mappings));

$scriptlastran=ps_value("select value from sysvars where name='last_tms_import'",array(), "");

global $baseurl, $tms_link_field_mappings_saved;
// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'tms_link';
$plugin_page_heading = $lang['tms_link_configuration'];
// Build the $page_def array of descriptions of each configuration variable the plugin uses.

$page_def[] = config_add_section_header($lang['tms_link_database_setup']);

$page_def[] = config_add_text_input('tms_link_dsn_name',$lang['tms_link_dsn_name']);
$page_def[] = config_add_text_input('tms_link_user',$lang['tms_link_user']);
$page_def[] = config_add_text_input('tms_link_password',$lang['tms_link_password'],true);
$testhtml = "<input type='submit' name='testConn' onclick='tmsTest();return false;' value='" . $lang['tms_link_test_link'] ."' />";
$page_def[] = config_add_html($testhtml);
$page_def[] = config_add_text_input('tms_link_email_notify',$lang['tms_link_email_notify']);

$page_def[] = config_add_section_header($lang['tms_link_enable_update_script_info']);
$tmsscriptstatushtml = $lang["tms_link_last_run_date"] . (($scriptlastran!="")?date("l F jS Y @ H:i:s",strtotime($scriptlastran)):$lang["status-never"]) . "<br /><br />";
$page_def[] = config_add_html($tmsscriptstatushtml);
$page_def[] = config_add_boolean_select('tms_link_enable_update_script', $lang['tms_link_enable_update_script']);

$page_def[] = config_add_section_header($lang['tms_link_performance_options']);

$page_def[] = config_add_text_input('tms_link_script_failure_notify_days',$lang['tms_link_script_failure_notify_days']);
$page_def[] = config_add_text_input('tms_link_query_chunk_size',$lang['tms_link_query_chunk_size']);
$page_def[] = config_add_boolean_select('tms_link_test_mode', $lang['tms_link_test_mode']);
$page_def[] = config_add_text_input('tms_link_test_count',$lang['tms_link_test_count']);

// Removed from UI
$helptext = str_replace("%variable","\$tms_link_log_directory",$lang['ui_removed_config_message']);
$showval = $tms_link_log_directory !== "" ? $tms_link_log_directory : $lang["notavailableshort"];
$page_def[] =  config_add_fixed_input($lang['tms_link_log_directory'], $showval, $helptext);

$page_def[] = config_add_text_input('tms_link_log_expiry',$lang['tms_link_log_expiry']);
$page_def[] = config_add_boolean_select('tms_link_write_to_debug_log', $lang['tms_link_write_to_debug_log']);

$page_def[] = config_add_section_header($lang['tms_link_bidirectional_options']);
$page_def[] = config_add_boolean_select('tms_link_push_image', $lang['tms_link_push_image']);
$page_def[] = config_add_text_input('tms_link_push_condition',$lang['tms_link_push_condition']);
$page_def[] = config_add_text_input('tms_link_tms_loginid',$lang['tms_link_tms_loginid']);
$page_def[] = config_add_text_list_input('tms_link_push_image_sizes',$lang['tms_link_push_image_sizes']);
$page_def[] = config_add_text_input('tms_link_mediatypeid',$lang['tms_link_mediatypeid']);
$page_def[] = config_add_text_input('tms_link_formatid',$lang['tms_link_formatid']);
$page_def[] = config_add_text_input('tms_link_colordepthid',$lang['tms_link_colordepthid']);
$page_def[] = config_add_text_input('tms_link_media_path',$lang['tms_link_media_path']);
$page_def[] = config_add_text_input('tms_link_mediapaths_resource_reference_column',$lang['tms_link_mediapaths_resource_reference_column']);

$page_def[] = config_add_section_header($lang['tms_link_modules_mappings']);
$tms_modules_mappings_html = "
    <div class=\"Question\">
        <table id=\"tmsModulesMappingTable\">
            <tr>
                <th><strong>{$lang['tms_link_module']}</strong></th>
                <th><strong>{$lang['tms_link_tms_uid_field']}</strong></th>
                <th><strong>{$lang['tms_link_rs_uid_field']}</strong></th>
                <th><strong>{$lang['tms_link_applicable_rt']}</strong></th>
                <th><strong>{$lang['tms_link_modules_mappings_tools']}</strong></th>
            </tr>";

foreach ($tms_link_modules_mappings as $tms_link_module_index => $tms_link_module) {
    $tms_link_module_name = escape($tms_link_module['module_name']??"");
    $tms_link_tms_uid_field = escape($tms_link_module['tms_uid_field']??"");

    $tms_link_rs_uid_field = get_resource_type_field($tms_link_module['rs_uid_field']??"");
    if (false !== $tms_link_rs_uid_field) {
        $tms_link_rs_uid_field = escape((string) $tms_link_rs_uid_field['title']);
    }

    $tms_link_applicable_resource_types = '';
    if (!empty($tms_link_module['applicable_resource_types'])) {
        $tms_link_applicable_resource_types = get_resource_types(implode(',', $tms_link_module['applicable_resource_types']));
        $tms_link_applicable_resource_types = array_column($tms_link_applicable_resource_types, 'name');
        $tms_link_applicable_resource_types = escape(implode(', ', $tms_link_applicable_resource_types));
    }

    $tms_modules_mappings_html .= "
            <tr>
                <td>
                    <input type=\"text\" class=\"medwidth\" value=\"{$tms_link_module_name}\" disabled>
                </td>
                <td>
                    <input type=\"text\" class=\"medwidth\" value=\"{$tms_link_tms_uid_field}\" disabled>
                </td>
                <td>
                    <input type=\"text\" class=\"medwidth\" value=\"{$tms_link_rs_uid_field}\" disabled>
                </td>
                <td>
                    <input type=\"text\" class=\"medwidth\" value=\"{$tms_link_applicable_resource_types}\" disabled>
                </td>
                <td>
                    <button type=\"button\" id=\"edit_tms_module_{$tms_link_module_index}\" onclick=\"edit_tms_module_mapping('{$tms_link_module_index}');\">{$lang['action-edit']}</button>
                    <button type=\"button\" id=\"delete_tms_module_{$tms_link_module_index}\" onclick=\"delete_tms_module_mapping(this, '{$tms_link_module_index}');\">{$lang['action-delete']}</button>
                </td>
            </tr>";
}
$tms_modules_mappings_html .= "
        </table>
        <script>
        function edit_tms_module_mapping(id)
            {
            var edit_tms_module_link = '{$baseurl}/plugins/tms_link/pages/tms_module_config.php?id=' + id;
            window.location.href = encodeURI(edit_tms_module_link);
            }

        function delete_tms_module_mapping(element, id)
            {
            if(confirm('{$lang["tms_link_confirm_delete_module_config"]}') == false)
                {
                return;
                }

            CentralSpaceShowProcessing();

            jQuery.ajax(
                {
                type: 'POST',
                url: '{$baseurl}/plugins/tms_link/pages/tms_module_config.php',
                data: {
                    id: id,
                    action: 'delete',
                    " . generateAjaxToken('TmsModuleConfigForm') . "
                }
                }).done(function(response, textStatus, jqXHR) {
                    var button = jQuery(element);
                    var record = jQuery(button).closest('tr');
                    record.remove();
                }).fail(function(data, textStatus, jqXHR) {
                    styledalert('{$lang["tms_link_not_found_error_title"]}', '{$lang["tms_link_not_deleted_error_detail"]}');
                }).always(function() {
                    CentralSpaceHideProcessing();
                });

            return;
            }

        function tmsTest()
            {
            var post_url  = 'ajax_test.php';
            var post_data = {
                ajax: true,
                dsn: jQuery('#tms_link_dsn_name').val(),
                tmsuser: jQuery('#tms_link_user').val(),
                tmspass: jQuery('#tms_link_password').val(),
                " . generateAjaxToken("tms_test") . "
            };

            jQuery.ajax({
                type:'POST',
                url: post_url,
                data: post_data,
                dataType: 'json',
            }).done(function(response, status, xhr)
                {
                styledalert(response.result,response.message);
                return true;
                });
            }
        </script>
        <a href=\"{$baseurl}/plugins/tms_link/pages/tms_module_config.php\" onclick=\"return CentralSpaceLoad(this, true);\">{$lang['tms_link_add_new_tms_module']}</a>
    </div>";
$page_def[] = config_add_html($tms_modules_mappings_html);
$page_def[] = config_add_hidden("tms_link_modules_saved_mappings");


// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);

if (trim($tms_link_log_directory) != "") {
    // Test valid settings
    $GLOBALS['use_error_exception'] = true;
    if (!is_dir($tms_link_log_directory)) {
        try {
            mkdir($tms_link_log_directory, 0755, true);
        } catch (Exception $e) {
            $errortext = 'Invalid log directory: ' . escape($tms_link_log_directory);
            debug($errortext . " " . $e->getMessage());
            }
    } else {
        $logfilepath=$tms_link_log_directory . DIRECTORY_SEPARATOR . "tms_import_log_test.log";
        try {
            $logfile = fopen($logfilepath,'a');
            fclose($logfile);
            unlink($logfilepath);
        } catch (Exception $e) {
            $errortext = 'Unable to create log file in directory: ' . escape($tms_link_log_directory);
            debug($errortext . " " . $e->getMessage());
        }
    }
    unset($GLOBALS['use_error_exception']);
}

include __DIR__ . '/../../../include/header.php';
if (isset($errortext)) {
    echo "<div class=\"PageInformal\">" . escape($errortext) . "</div>";
}
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include __DIR__ . '/../../../include/footer.php';