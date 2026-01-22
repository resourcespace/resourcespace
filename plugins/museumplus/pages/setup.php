<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php';
if(!checkperm('a'))
    {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
    }

$plugin_name = 'museumplus';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
    check_removed_ui_config("museumplus_log_directory");
}

// Validate modules config (this is configurable on a different page - setup_module.php)
$museumplus_modules_config = plugin_decode_complex_configs($museumplus_modules_saved_config);
if(!is_array($museumplus_modules_config))
    {
    $error = $lang['museumplus_error_unknown_type_saved_config'];
    $museumplus_modules_config = array();
    $museumplus_modules_saved_config = '';
    }

// API settings
$page_def[] = config_add_section_header($lang['museumplus_api_settings_header']);
$page_def[] = config_add_text_input('museumplus_host', $lang['museumplus_host']);
$page_def[] = config_add_text_input('museumplus_host_api', $lang['museumplus_host_api']);
$page_def[] = config_add_text_input('museumplus_application', $lang['museumplus_application']);
$page_def[] = config_add_text_input('museumplus_api_user', $lang['museumplus_api_user']);
$page_def[] = config_add_text_input('museumplus_api_pass', $lang['museumplus_api_pass'], true);

// ResourceSpace settings
$page_def[] = config_add_section_header($lang['museumplus_RS_settings_header']);
$page_def[] = config_add_single_ftype_select(
    'museumplus_module_name_field',
    $lang['museumplus_module_name_field'],
    420,
    false,
    array(
        FIELD_TYPE_DROP_DOWN_LIST,
        FIELD_TYPE_RADIO_BUTTONS,
    )
);
$page_def[] = config_add_single_ftype_select(
    'museumplus_secondary_links_field',
    $lang['museumplus_secondary_links_field'],
    420,
    false,
    array(
        FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
        FIELD_TYPE_TEXT_BOX_MULTI_LINE,
        FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE,
    )
);

// Script settings
$page_def[] = config_add_section_header($lang['museumplus_script_header']);
$museumplus_script_last_ran = '';
check_script_last_ran(MPLUS_LAST_IMPORT, $museumplus_script_failure_notify_days, $museumplus_script_last_ran);
$script_last_ran_content = sprintf(
    "<div class=\"Question\">
    <label>
        <strong>%s</strong>
    </label>
    <input name=\"script_last_ran\" type=\"text\" value=\"%s\" disabled style=\"width: 420px;\">
</div>
<div class=\"clearerleft\"></div>",
escape($lang['museumplus_last_run_date']),
escape($museumplus_script_last_ran)
);

$page_def[] = config_add_html($script_last_ran_content);
$page_def[] = config_add_boolean_select('museumplus_enable_script', $lang['museumplus_enable_script']);
$page_def[] = config_add_text_input('museumplus_interval_run', $lang['museumplus_interval_run']);

// Removed from UI
$helptext = str_replace("%variable","\$museumplus_log_directory",$lang['ui_removed_config_message']);
$showval = $museumplus_log_directory !== "" ? $museumplus_log_directory : $lang["notavailableshort"];
$page_def[] = config_add_fixed_input($lang['museumplus_log_directory'], $showval, $helptext);

// $page_def[] = config_add_single_ftype_select('museumplus_integrity_check_field', $lang['museumplus_integrity_check_field'], 420); # not in use until we can reliably get integrity checks of the data from M+

// MuseumPlus - modules configuration
$page_def[] = config_add_section_header($lang['museumplus_modules_configuration_header']);
$museumplus_modules_conf_html = "<div class=\"Question\">
    <table id=\"MplusModulesTable\">
        <tr>
            <th><strong>{$lang['museumplus_module']}</strong></th>
            <th><strong>{$lang['museumplus_mplus_id_field']}</strong></th>
            <th><strong>{$lang['museumplus_rs_uid_field']}</strong></th>
            <th><strong>{$lang['museumplus_applicable_resource_types']}</strong></th>
            <th><strong>{$lang['tools']}</strong></th>
        </tr>";
foreach($museumplus_modules_config as $module_conf_index => $module_conf)
    {
    $rs_uid_field = get_resource_type_field($module_conf['rs_uid_field']);
    $rs_uid_field = ($rs_uid_field !== false ? $rs_uid_field['title'] : '');

    $applicable_resource_types = $module_conf['applicable_resource_types'];
    if(!empty($applicable_resource_types))
        {
        $applicable_resource_types = get_resource_types(join(',', $applicable_resource_types));
        $applicable_resource_types = array_column($applicable_resource_types, 'name');
        }

    $museumplus_modules_conf_html .= sprintf(
        '<tr>
            <td><input type="text" class="shortwidth" value="%1$s" disabled></td>
            <td><input type="text" class="medwidth" value="%2$s" disabled></td>
            <td><input type="text" class="medwidth" value="%3$s" disabled></td>
            <td><input type="text" class="medwidth" value="%4$s" disabled></td>
            <td>
                <button type="button" onclick="museumplus_edit_module_conf(%5$s);">%6$s</button>
                <button type="button" onclick="museumplus_delete_module_conf(this, %5$s);">%7$s</button>
            </td>
        </tr>',
        escape($module_conf['module_name']),
        escape($module_conf['mplus_id_field']),
        escape($rs_uid_field),
        escape(join(', ', $applicable_resource_types)),
        $module_conf_index,
        $lang['action-edit'],
        $lang['action-delete']);
    }
$museumplus_modules_conf_html .= "</table>
    <a href=\"{$baseurl}/plugins/museumplus/pages/setup_module.php\" onclick=\"return CentralSpaceLoad(this, true);\">{$lang['museumplus_add_new_module']}</a>
";
$page_def[] = config_add_html($museumplus_modules_conf_html);
if(trim($museumplus_modules_saved_config) !== '')
    {
    $page_def[] = config_add_hidden('museumplus_modules_saved_config');
    }


if(get_utility_path("php") === false)
    {
    $error = $lang['museumplus_php_utility_not_found'];
    }

config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
if(isset($error))
    {
    render_top_page_error_style($error);
    }
config_gen_setup_html($page_def, $plugin_name, null, $lang['museumplus_configuration']);
?>
<script>
function museumplus_edit_module_conf(id)
    {
    var setup_module_url = '<?php echo $baseurl; ?>/plugins/museumplus/pages/setup_module.php?id=' + id;
    CentralSpaceLoad(setup_module_url, true);
    }

function museumplus_delete_module_conf(element, id)
    {
    if(confirm('<?php echo escape($lang["museumplus_confirm_delete_module_config"]); ?>') == false)
        {
        return;
        }

    CentralSpaceShowProcessing();
    jQuery.ajax(
        {
        type: 'POST',
        url: '<?php echo $baseurl; ?>/plugins/museumplus/pages/setup_module.php',
        data: {
            id: id,
            action: 'delete',
            <?php echo generateAjaxToken('MplusModuleConfigForm'); ?>
        },
        dataType: "json"
        })
        .done(function(response, textStatus, jqXHR)
            {
            // Remove config record
            var button = jQuery(element);
            var record = jQuery(button).closest('tr');
            record.remove();
            })
        .fail(function(data, textStatus, jqXHR)
            {
            if(typeof data.responseJSON === 'undefined')
                {
                return;
                }

            var response = data.responseJSON;
            styledalert(response.data.title, response.data.message);
            })
        .always(function()
            {
            CentralSpaceHideProcessing();
            });

    return;
    }
</script>
<?php
include '../../../include/footer.php';
