<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

$ref = (int) getval('ref', 0, true, 'is_int_loose');
if ($ref === 0) {
    exit('No user group ref supplied.');
}

$offset = getval("offset", 0, true);
$order_by = getval("orderby", "");
$filter_by_parent = getval("filterbyparent", "");
$find = getval("find", "");
$filter_by_permissions = getval("filterbypermissions", "");

$url_params = [
    'ref' => $ref,
];
if ($offset) {
    $url_params['offset'] = $offset;
}
if ($order_by) {
    $url_params['orderby'] = $order_by;
}
if ($filter_by_parent) {
    $url_params['filterbyparent'] = $filter_by_parent;
}
if ($find) {
    $url_params['find'] = $find;
}
if ($filter_by_permissions) {
    $url_params['filterbypermissions'] = $filter_by_permissions;
}

$group = get_usergroup($ref);
$selected_usergroup_permissions = explode(',', $group['permissions']);
$enable_disable_options = array($lang['userpreference_disable_option'], $lang['userpreference_enable_option']);
$yes_no_options = array($lang['no'], $lang['yes']);

# Rendering of user group preferences area.
if ((int) $group['parent'] > 0 && in_array("preferences", $group['inherit'])) {
    $page_def[] = config_add_html('<p>' . $lang["group_config_inherit"] . '</p>');
} else {
    $page_def[] = config_add_html('<p>' . $lang["action-title_usergroup_override_detail"] . '</p>');

    // User interface section
    $page_def[] = config_add_html('<h3 class="CollapsibleSectionHead collapsed">' . $lang['userpreference_user_interface'] . '</h3><div id="UsergroupConfigUserInterfaceSection" class="CollapsibleSection">');
    $page_def[] = config_add_colouroverride_input('header_colour_style_override', $lang["setup-headercolourstyleoverride"], '', null, true);
    $page_def[] = config_add_colouroverride_input('header_link_style_override', $lang["setup-headerlinkstyleoverride"], '', null, true);
    $page_def[] = config_add_colouroverride_input('home_colour_style_override', $lang["setup-homecolourstyleoverride"], '', null, true);
    $page_def[] = config_add_colouroverride_input('collection_bar_background_override', $lang["setup-collectionbarbackground"], '', null, true);
    $page_def[] = config_add_colouroverride_input('collection_bar_foreground_override', $lang["setup-collectionbarforeground"], '', null, true);
    $page_def[] = config_add_colouroverride_input('button_colour_override', $lang["setup-buttoncolouroverride"], '', null, true);
    $page_def[] = config_add_single_select('thumbs_default', $lang['userpreference_thumbs_default_label'], array('show' => $lang['showthumbnails'], 'hide' => $lang['hidethumbnails']), true, 300, '', true);
    $page_def[] = config_add_boolean_select('basic_simple_search', $lang['userpreference_basic_simple_search_label'], $enable_disable_options, 300, '', true);
    $page_def[] = config_add_boolean_select('hide_search_resource_types', $lang['userpreference_hide_search_resource_types'], $enable_disable_options, 300, '', true);
    $page_def[] = config_add_single_select('upload_then_edit', $lang['upload_sequence'], array(true => $lang['upload_first_then_set_metadata'], false => $lang['set_metadata_then_upload']), true, 300, '', true);
    $page_def[] = config_add_boolean_select('modal_default', $lang['userpreference_modal_default'], $enable_disable_options, 300, '', true);
    $page_def[] = config_add_boolean_select('keyboard_navigation', $lang['userpreference_keyboard_navigation'], $enable_disable_options, 300, '', true);
    $page_def[] = config_add_boolean_select('tilenav', $lang['userpreference_tilenav'], $enable_disable_options, 300, '', true, 'TileNav=(value==1);');
    $page_def[] = config_add_boolean_select('byte_prefix_mode_decimal', $lang['byte_prefix_mode_decimal'], $enable_disable_options, 300, '', true);
    $page_def[] = config_add_single_select('user_local_timezone', $lang['systemconfig_user_local_timezone'], timezone_identifiers_list(), false, 300, '', true);
    $page_def[] = config_add_html('</div>');

    // Search section
    $page_def[] = config_add_html('<h3 class="CollapsibleSectionHead collapsed">' . $lang['searchcapability'] . '</h3><div id="SystemConfigSearchSection" class="CollapsibleSection">');

    $sort_order_fields = array('relevance' => $lang['relevance']);

    if ($popularity_sort) {
        $sort_order_fields['popularity'] = $lang['popularity'];
    }

    if ($orderbyrating) {
        $sort_order_fields['rating'] = $lang['rating'];
    }

    if ($date_column) {
        $sort_order_fields['date'] = $lang['date'];
    }

    if ($colour_sort) {
        $sort_order_fields['colour'] = $lang['colour'];
    }

    if ($order_by_resource_id) {
        $sort_order_fields['resourceid'] = $lang['resourceid'];
    }

    $sort_order_fields['resourcetype'] = $lang['type'];

    foreach ($sort_fields as $field) {
        $field_data = get_resource_type_field($field);
        if ($field_data !== false) {
            $sort_order_fields["field$field"] = $field_data["title"];
        }
    }

    $page_def[] = config_add_single_select('default_sort', $lang['userpreference_default_sort_label'], $sort_order_fields, true, 420, '', true);
    $page_def[] = config_add_single_select('default_sort_direction', $lang['userpreference_default_sort_order_label'], ['ASC' => 'Ascending', 'DESC' => 'Descending'], true, 420, '', true);

    $default_display_array = array();
    $default_display_array['thumbs'] = $lang['largethumbstitle'];
    if ($xlthumbs || $GLOBALS['default_display'] == 'xlthumbs') {
        $default_display_array['xlthumbs'] = $lang['xlthumbstitle'];
    }
    $default_display_array['list'] = $lang['listtitle'];
    $default_display_array['strip']  = $lang['striptitle'];

    $page_def[] = config_add_single_select('default_perpage', $lang['userpreference_default_perpage_label'], $results_display_array, false, 420, '', true);
    $page_def[] = config_add_single_select('default_display', $lang['userpreference_default_display_label'], $default_display_array, true, 420, '', true);
    $page_def[] = config_add_html('</div>');

    // System notifications section - used to disable system generated messages
    $page_def[] = config_add_html('<h3 class="CollapsibleSectionHead collapsed">' . $lang['mymessages'] . '</h3><div id="UsergroupMessageSection" class="CollapsibleSection">');
    $page_def[] = config_add_boolean_select('user_pref_show_notifications', $lang['user_pref_show_notifications'], $enable_disable_options, 300, '', true);
    $page_def[] = config_add_boolean_select('user_pref_resource_notifications', $lang['userpreference_resource_notifications'], $enable_disable_options, 300, '', true);
    if (in_array('a', $selected_usergroup_permissions)) {
        $page_def[] = config_add_boolean_select('user_pref_system_management_notifications', $lang['userpreference_system_management_notifications'], $enable_disable_options, 300, '', true);
    }
    if (in_array('u', $selected_usergroup_permissions)) {
        $page_def[] = config_add_boolean_select('user_pref_user_management_notifications', $lang['userpreference_user_management_notifications'], $enable_disable_options, 300, '', true);
    }
    if (in_array('R', $selected_usergroup_permissions)) {
        $page_def[] = config_add_boolean_select('user_pref_resource_access_notifications', $lang['userpreference_resource_access_notifications'], $enable_disable_options, 300, '', true);
    }
    $page_def[] = config_add_html('</div>');

    // Email section, only show if user has got an email address
    if ($useremail != "") {
        $page_def[] = config_add_html('<h3 class="CollapsibleSectionHead collapsed">' . $lang['email'] . '</h3><div id="UsergroupEmailSection" class="CollapsibleSection">');
        $page_def[] = config_add_boolean_select('email_user_notifications', $lang['userpreference_email_me_label'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_boolean_select('email_and_user_notifications', $lang['user_pref_email_and_user_notifications'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_boolean_select('user_pref_daily_digest', $lang['user_pref_daily_digest'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_html('</div>');
    }

    // Actions section - used to configure the alerts that appear in 'My actions'
    // Create an array for the archive states
    $available_archive_states = array();
    $all_archive_states = array_merge(range(-2, 3), $additional_archive_states);
    foreach ($all_archive_states as $archive_state_ref) {
        if (in_array('e' . $archive_state_ref, $selected_usergroup_permissions)) {
            $available_archive_states[$archive_state_ref] = (isset($lang["status" . $archive_state_ref])) ? $lang["status" . $archive_state_ref] : $archive_state_ref;
        }
    }
    if ($actions_on) {
        $page_def[] = config_add_html('<h3 class="CollapsibleSectionHead collapsed">' . $lang['actions_myactions'] . '</h3><div id="UsergroupActionSection" class="CollapsibleSection">');
        if (in_array('R', $selected_usergroup_permissions)) {
            $page_def[] = config_add_boolean_select('actions_resource_requests', $lang['actions_resource_requests'], $enable_disable_options, 300, '', true);
        }
        if (in_array('u', $selected_usergroup_permissions)) {
            $statesjs = "if(jQuery(this).val()==1){
                            jQuery('#question_actions_approve_groups').slideDown();
                            }
                        else {
                            jQuery('#question_actions_approve_groups').slideUp();
                            }";
            $page_def[] = config_add_boolean_select('actions_account_requests', $lang['actions_account_requests'], $enable_disable_options, 300, '', true, $statesjs);
            $page_def[] = config_add_checkbox_select('actions_approve_hide_groups', $lang['actions_approve_hide_groups'], get_usergroups(true, '', true), true, 300, 1, true, null, !$actions_account_requests);
        }

        // Make sure all states are unchecked if they had the deprecated option $actions_resource_review set to false.
        // Also only show this option if it is disabled
        get_config_option(['usergroup' => $ref], 'actions_resource_review', $legacy_resource_review, true);
        if (!$legacy_resource_review) {
            $page_def[] = config_add_boolean_select('actions_resource_review', $lang['actions_resource_review'], $enable_disable_options, 300, '', true);
        }
        $page_def[] = config_add_checkbox_select('actions_notify_states', $lang['actions_notify_states'], $available_archive_states, true, 300, 1, true, null);
        $rtypes = get_resource_types();
        foreach ($rtypes as $rtype) {
            $actionrestypes[$rtype["ref"]] = $rtype["name"];
        }
        $page_def[] = config_add_checkbox_select('actions_resource_types_hide', $lang['actions_resource_types_hide'], $actionrestypes, true, 300, 1, true, null);
        $page_def[] = config_add_boolean_select('actions_modal', $lang['actions_modal'], $enable_disable_options, 300, '', true);

        $page_def[] = "AFTER_ACTIONS_MARKER"; // Added so that hook add_user_preference_page_def can locate this position in array
        $page_def[] = config_add_html('</div>');

        // End of actions section
    }

    // Browse Bar section
    $page_def[] = config_add_html('<h3 class="CollapsibleSectionHead collapsed">' . $lang['systemconfig_browse_bar_section'] . '</h3><div id="UsergroupFeaturedCollectionSection" class="CollapsibleSection">');
    $page_def[] = config_add_boolean_select('browse_bar', $lang['systemconfig_browse_bar_enable'], $yes_no_options, 420, '', true);
    $page_def[] = config_add_boolean_select('browse_bar_workflow', $lang['systemconfig_browse_bar_workflow'], $yes_no_options, 420, '', true);
    $page_def[] = config_add_html('</div>');

    // Featured Collection section
    $page_def[] = config_add_html('<h3 class="CollapsibleSectionHead collapsed">' . $lang['systemconfig_featured_collections'] . '</h3><div id="UsergroupFeaturedCollectionSection" class="CollapsibleSection">');
    $page_def[] = config_add_boolean_select('enable_themes', $lang['systemconfig_enable_themes'], $yes_no_options, 420, '', true);
    $page_def[] = config_add_boolean_select('themes_simple_view', $lang['systemconfig_themes_simple_view'], $yes_no_options, 420, '', true);
    $page_def[] = config_add_html('</div>');
}

// Process autosaving requests
// Note: $page_def must be defined by now in order to make sure we only save options that we've defined
if ('true' === getval('ajax', '') && 'true' === getval('autosave', '')) {
    header('Content-Type: application/json');

    $response['success'] = true;
    $response['message'] = '';

    $autosave_option_name  = getval('autosave_option_name', '');
    $autosave_option_value = getval('autosave_option_value', '');

    // Search for the option name within our defined (allowed) options
    // if it is not there, error and don't allow saving it
    $page_def_option_index = array_search($autosave_option_name, array_column($page_def, 1));
    if (false === $page_def_option_index) {
        $response['success'] = false;
        $response['message'] = $lang['systemconfig_option_not_allowed_error'];

        echo json_encode($response);
        exit();
    }

    if (!set_usergroup_config_option($ref, $autosave_option_name, $autosave_option_value)) {
        $response['success'] = false;
    }

    echo json_encode($response);
    exit();
}

include "../../include/header.php";

?>
<div id="UsergroupConfig">
    <h1><?php echo escape($lang["page-title_usergroup_config"] . ' - ' . $group["name"]); ?></h1>
    <?php render_config_filter_by_search(getval("filter", ""), getval("only_modified", "no")); ?>
    <div class="CollapsibleSections">
        <?php
        $links_trail = array(
            array(
                'title' => $lang["systemsetup"],
                'href'  => $baseurl_short . "pages/admin/admin_home.php",
                'menu' =>  true
            ),
            array(
                'title' => $lang["page-title_user_group_management"],
                'href'  => $baseurl_short . "pages/admin/admin_group_management.php"
            ),
            array(
                'title' => $lang["page-title_user_group_management_edit"],
                'href'  => generateURL("{$baseurl_short}pages/admin/admin_group_management_edit.php", $url_params),
            ),
            array(
                'title' => $lang["page-title_usergroup_config"] . " - " . escape($group["name"])
            )
        );
        renderBreadcrumbs($links_trail);
        $page_def = config_filter_by_search($page_def, ['usergroup' => $ref], getval("filter", ""), getval("only_modified", "no"));

        config_remove_user_preferences($page_def);

        // Get user group config after page loads, header.php etc.
        process_config_options(array('usergroup' => $ref));

        config_generate_html($page_def);
        config_generate_AutoSaveConfigOption_function(generateURL($baseurl . "/pages/admin/admin_group_config_edit.php", $url_params));

        // Put back system / user preferences to avoid applying user group config for admin
        process_config_options(array());
        process_config_options(array('usergroup' => $usergroup));
        process_config_options(array('user' => $userref));
        ?>
    </div>
</div>
<script>
    registerCollapsibleSections();
</script>
<?php

include "../../include/footer.php";