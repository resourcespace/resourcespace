<?php

#
# action_dates setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

global $resource_deletion_state;

$plugin_name = 'action_dates';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'action_dates';
$plugin_page_heading = $lang['action_dates_configuration'];

$editable_states = array();
$editable_states_less_deleted = array();
foreach (get_editable_states($userref) as $archive_state) {
    $editable_states[$archive_state['id']] = $archive_state['name'];
    if ($archive_state['id'] != $resource_deletion_state) {
        $editable_states_less_deleted[$archive_state['id']] = $archive_state['name'];
    }
}

$allowable_fields = get_resource_type_fields('', 'order_by', 'asc', '', $DATE_FIELD_TYPES);
if (getval('submit', '') != '' || getval('save', '') != '' && enforcePostRequest(false)) {
    // Save the plugin config
    $action_dates_config["action_dates_deletefield"] = getval('action_dates_deletefield', '', true);
    $action_dates_config["action_dates_reallydelete"] = getval('action_dates_reallydelete', '');
    $action_dates_config["action_dates_new_state"] = getval('action_dates_new_state', '');
    $action_dates_config["action_dates_eligible_states"] = getval('action_dates_eligible_states', [], false, 'is_array');
    $action_dates_config["action_dates_email_for_state"] = getval('action_dates_email_for_state', '');
    $action_dates_config["action_dates_email_for_restrict"] = getval('action_dates_email_for_restrict', '');
    $action_dates_config["action_dates_email_admin_days"] = getval('action_dates_email_admin_days', '', true);
    $action_dates_config["action_dates_restrictfield"] = getval('action_dates_restrictfield', '', true);
    $action_dates_config["action_dates_remove_from_collection"] = getval('action_dates_remove_from_collection', '');
    $action_dates_config['action_dates_workflow_actions'] = getval('action_dates_workflow_actions', false);
    $action_dates_config["action_dates_weekdays"] = getval('action_dates_weekdays', [], false, 'is_array');

    // Get the extra rows fom the table
    $action_date_extra_fields     = getval('action_dates_extra_field', [], false, 'is_array');
    $action_date_extra_statuses   = getval('action_dates_extra_status', [], false, 'is_array');

    $action_dates_extra_config = array();
    $mappingcount = 0;

    // Store the extra config in a new array
    for ($i = 0; $i < count($action_date_extra_fields); $i++) {
        if ($action_date_extra_fields[$i] != '' && $action_date_extra_statuses[$i] != '') {
            $action_dates_extra_config[$mappingcount] = array();
            $action_dates_extra_config[$mappingcount]["field"] = (int)$action_date_extra_fields[$i];
            $action_dates_extra_config[$mappingcount]["status"] = (int)$action_date_extra_statuses[$i];
            $mappingcount++;
        }
    }
    $action_dates_config["action_dates_extra_config"] = $action_dates_extra_config;
    set_plugin_config("action_dates", $action_dates_config);
    // Refresh config
    include_plugin_config("action_dates", "", json_encode($action_dates_config));
}

if (getval('submit', '') != '') {
    redirect('pages/team/team_plugins.php');
}

// Build the $page_def array of descriptions of each configuration variable the plugin uses.
$page_def[] = config_add_section_header($lang['action_dates_deletesettings']);
$page_def[] = config_add_single_ftype_select('action_dates_deletefield', $lang['action_dates_delete'], 420, false, $DATE_FIELD_TYPES);
$page_def[] = config_add_boolean_select('action_dates_reallydelete', $lang['action_dates_reallydelete']);
$page_def[] = config_add_single_select('action_dates_new_state', $lang['action_dates_new_state'], $editable_states);
$page_def[] = config_add_multi_archive_select("action_dates_eligible_states", $lang["action_dates_eligible_states"], $editable_states_less_deleted);
$page_def[] = config_add_single_ftype_select('action_dates_restrictfield', $lang['action_dates_restrict'], 420, false, $DATE_FIELD_TYPES);
$page_def[] = config_add_boolean_select('action_dates_remove_from_collection', $lang['action_dates_remove_from_collection']);
$page_def[] = config_add_text_input('action_dates_email_admin_days', $lang['action_dates_email_admin_days']);
$page_def[] = config_add_boolean_select('action_dates_email_for_state', $lang['action_dates_email_for_state']);
$page_def[] = config_add_boolean_select('action_dates_email_for_restrict', $lang['action_dates_email_for_restrict']);
$page_def[] = config_add_boolean_select('action_dates_workflow_actions', $lang['action_dates_workflow_actions']);
$page_def[] = config_add_multi_archive_select('action_dates_weekdays', $lang['action_dates_weekdays'], array($lang["weekday-0"],$lang["weekday-1"],$lang["weekday-2"],$lang["weekday-3"],$lang["weekday-4"],$lang["weekday-5"],$lang["weekday-6"]));

$page_def[] = config_add_section_header($lang['action_dates_additional_settings']);

$action_dates_extra_config[] = array('field' => '', 'status' => '');

// Set up the table in HTML to add the extra config to the page
$page_def_extra = "<div class='Question'>
<label>" . $lang['action_dates_additional_settings_info'] . "</label>
<table id='action_dates_extra_table' class='ListviewStyle' style='width: 420px;'>
<tr>
    <th>
        <strong>" . $lang['action_dates_additional_settings_date'] . "</strong>
    </th>
    <th>
        <strong>" . $lang['action_dates_additional_settings_status'] . "</strong>
    </th>
</tr>";

foreach ($action_dates_extra_config as $action_dates_extra_config) {
    $page_def_extra .= "<tr" . (($action_dates_extra_config['field'] == '') ? " id='action_dates_empty' style='display: none'" : "" ) . ">
        <td>
        <select name='action_dates_extra_field[]' class='stdwidth'>
            <option value=''></option>";
    foreach ($allowable_fields as $allowable_field) {
        $page_def_extra .=   "<option value='" . $allowable_field['ref'] . "'";
        if ($action_dates_extra_config['field'] == $allowable_field['ref']) {
            $page_def_extra .=  " selected";
        }
        $page_def_extra .=  ">" . $allowable_field['title'] . "</option>\n";
    }

        $page_def_extra .= "
        </select>
        </td>
        <td>
            <select name='action_dates_extra_status[]'  class='stdwidth'>
            <option value=''></option>";

    foreach ($editable_states as $editable_state => $state_name) {
        $page_def_extra .= "<option value='" . $editable_state . "'";
        if ($action_dates_extra_config['status'] == $editable_state) {
            $page_def_extra .= " selected ";
        }
        $page_def_extra .= ">" . $state_name . "</option>\n";
    }
        $page_def_extra .= "</select>
        </td>
        </tr>";
}

$page_def_extra .= "</table>
    <div class='clearerleft' ></div>";
$page_def_extra .= "<a onclick='addActionDatesExtraRow()'><i aria-hidden='true' class='icon-circle-plus'></i></a>
</div>";
$page_def_extra .= "<script>
jQuery(document).ready(function() {
    var action_dates_reallydelete = jQuery('#action_dates_reallydelete').val();
    var action_dates_new_state;
    if (action_dates_reallydelete == 1) {
        // Real deletion means that the new state is irrelevant
        action_dates_new_state = '';
        jQuery('#action_dates_new_state').val(action_dates_new_state);
        jQuery('#action_dates_new_state').prop('disabled', true);
    }
    else {
        // New state is relevant
        action_dates_new_state = jQuery('#action_dates_new_state').val();
        jQuery('#action_dates_new_state').prop('disabled', false);
        jQuery('#action_dates_eligible_states'+action_dates_new_state).prop('checked', false);
        jQuery('#action_dates_eligible_states > #archivestate'+action_dates_new_state).hide();
    }

    jQuery('#action_dates_reallydelete').on('change', function() {
        action_dates_reallydelete = this.value;

        if (action_dates_reallydelete == 1) {
            // Real deletion means that the new state is irrelevant
            action_dates_new_state = '';
            jQuery('#action_dates_new_state').val(action_dates_new_state);
            jQuery('#action_dates_new_state').prop('disabled', true);
        }
        else {
            // New state is relevant
            action_dates_new_state = jQuery('#action_dates_new_state').val();
            jQuery('#action_dates_eligible_states'+action_dates_new_state).prop('checked', false);
            jQuery('#action_dates_eligible_states > #archivestate'+action_dates_new_state).hide();
            jQuery('#action_dates_new_state').prop('disabled', false).focus();
        }

    });

    jQuery('#action_dates_new_state').on('change', function() {
        action_dates_new_state = this.value;
        // Show all eligible states
        jQuery('#action_dates_eligible_states').children().show();
        // The new state is not eligible
        jQuery('#action_dates_eligible_states'+action_dates_new_state).prop('checked', false);
        jQuery('#action_dates_eligible_states> #archivestate'+action_dates_new_state).hide();
    });
});

function addActionDatesExtraRow() {
            var table = document.getElementById('action_dates_extra_table');
            var rowCount = table.rows.length;
            var row = table.insertRow(rowCount);
            row.innerHTML = document.getElementById('action_dates_empty').innerHTML;
        }
</script>";

$page_def[] = config_add_html($page_def_extra);

// Need to store the updated values to GLOBALS so that the latest values are shown
foreach ($page_def as $def) {
    $array_offset = array();
    if (preg_match("/\[[\"|']?\w+[\"|']?\]/", $def[1], $array_offset)) {
        $array = preg_replace("/\[[\"|']?\w+[\"|']?\]/", "", $def[1]);
        preg_match("/[\"|']?\w+[\"|']?/", $array_offset[0], $array_offset);
    }
    if (!empty($array_offset)) {
        $curr_post = getval($array, "");
        if ($curr_post == "") {
            continue;
        } //Ignore if Array already handled or blank
        foreach ($curr_post as $key => $val) {
            $config[$array][$key] = explode(',', $val);
            $GLOBALS[$array][$key] = explode(',', $val);
        }
        unset($_POST[$array]); //Unset once array has been handled to prevent duplicate changes
    }
}

// Do the page generation ritual -- don't change this section.

// Note that config_gen_setup_post() is not used as it cannot process $action_dates_extra_config array
//config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, true, $plugin_page_heading);

include '../../../include/footer.php';
