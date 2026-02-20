<?php

include "../../include/boot.php";

include "../../include/authenticate.php";
include_once '../../include/config_functions.php';

// Do not allow access to anonymous users
if (isset($anonymous_login) && ($anonymous_login == $username)) {
    header('HTTP/1.1 401 Unauthorized');
    die('Permission denied!');
}

$enable_disable_options = array($lang['userpreference_disable_option'], $lang['userpreference_enable_option']);

include "../../include/header.php";
?>
<div class="BasicsBox"> 
    <h1 class="inline_config_search"><?php echo escape($lang["userpreferences"]); ?></h1>

    <?php render_config_filter_by_search(getval("filter", ""), getval("only_modified", "no")); ?>

    <p><?php echo escape($lang["modifyuserpreferencesintro"]); ?></p>
    
    <div class="CollapsibleSections">
        <?php
        // Retina mode
        $page_def[] = config_add_html('<h2 class="CollapsibleSectionHead">' . $lang['resultsdisplay'] . '</h2><div id="UserPreferenceResultsDisplaySection" class="CollapsibleSection">');
        $page_def[] = config_add_boolean_select('retina_mode', $lang['retina_mode'], $enable_disable_options, 300, '', true);

        // Result display section
        $all_field_info = get_fields_for_search_display(array_unique(array_merge(
            $sort_fields,
            $thumbs_display_fields,
            $list_display_fields
        )));

        // Create an array for the archive states
        $available_archive_states = array();
        $all_archive_states = array_merge(range(-2, 3), $additional_archive_states);
        foreach ($all_archive_states as $archive_state_ref) {
            if (checkperm("e" . $archive_state_ref)) {
                $available_archive_states[$archive_state_ref] = (isset($lang["status" . $archive_state_ref])) ? $lang["status" . $archive_state_ref] : $archive_state_ref;
            }
        }

        // Create a sort_fields array with information for sort fields
        $n  = 0;
        $sf = array();
        foreach ($sort_fields as $sort_field) {
            // Find field in selected list
            for ($m = 0; $m < count($all_field_info); $m++) {
                if ($all_field_info[$m]['ref'] == $sort_field) {
                    $field_info      = $all_field_info[$m];
                    $sf[$n]['ref']   = $sort_field;
                    $sf[$n]['title'] = $field_info['title'];

                    $n++;
                }
            }
        }

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

        // Add thumbs_display_fields to sort order links for thumbs views
        for ($x = 0; $x < count($sf); $x++) {
            if (!isset($metadata_template_title_field)) {
                $metadata_template_title_field = false;
            }

            if ($sf[$x]['ref'] != $metadata_template_title_field) {
                $sort_order_fields['field' . $sf[$x]['ref']] = escape($sf[$x]['title']);
            }
        }

        $page_def[] = config_add_single_select(
            'default_sort',
            $lang['userpreference_default_sort_label'],
            $sort_order_fields,
            true,
            300,
            '',
            true
        );
        $page_def[] = config_add_single_select('default_sort_direction', $lang['userpreference_default_sort_order_label'], ['ASC' => 'Ascending', 'DESC' => 'Descending'], true, 300, '', true);
        $page_def[] = config_add_single_select('default_perpage', $lang['userpreference_default_perpage_label'], array(24, 48, 72, 120, 240), false, 300, '', true);

        // Default Display
        $default_display_array = array();

        $default_display_array['thumbs'] = $lang['largethumbstitle'];
        if ($xlthumbs || $GLOBALS['default_display'] == 'xlthumbs') {
            $default_display_array['xlthumbs'] = $lang['xlthumbstitle'];
        }
        $default_display_array['list'] = $lang['listtitle'];
        $default_display_array['strip']  = $lang['striptitle'];

        $page_def[] = config_add_single_select(
            'default_display',
            $lang['userpreference_default_display_label'],
            $default_display_array,
            true,
            300,
            '',
            true
        );

        $page_def[] = config_add_boolean_select('resource_view_modal', $lang['userpreference_resource_view_modal_label'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_html('</div>');

        // User interface section
        $page_def[] = config_add_html('<h2 class="CollapsibleSectionHead">' . $lang['userpreference_user_interface'] . '</h2><div id="UserPreferenceUserInterfaceSection" class="CollapsibleSection">');
        $page_def[] = config_add_single_select('thumbs_default', $lang['userpreference_thumbs_default_label'], array('show' => $lang['showthumbnails'], 'hide' => $lang['hidethumbnails']), true, 300, '', true);
        $page_def[] = config_add_boolean_select('basic_simple_search', $lang['userpreference_basic_simple_search_label'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_boolean_select('hide_search_resource_types', $lang['userpreference_hide_search_resource_types'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_single_select('upload_then_edit', $lang['upload_sequence'], array(true => $lang['upload_first_then_set_metadata'], false => $lang['set_metadata_then_upload']), true, 300, '', true, '', false, true);
        $page_def[] = config_add_boolean_select('modal_default', $lang['userpreference_modal_default'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_boolean_select('keyboard_navigation', $lang['userpreference_keyboard_navigation'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_boolean_select('tilenav', $lang['userpreference_tilenav'], $enable_disable_options, 300, '', true, 'TileNav=(value==1);');

        $page_def[] = config_add_boolean_select('byte_prefix_mode_decimal', $lang['byte_prefix_mode_decimal'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_single_select(
            'user_local_timezone',
            $lang['systemconfig_user_local_timezone'],
            timezone_identifiers_list(),
            false,
            300,
            '',
            true
        );
        $page_def[] = config_add_single_select(
            'user_pref_appearance',
            $lang['user_pref_appearance'],
            array(
                'light' => $lang['user_pref_appearance_light'],
                'dark' => $lang['user_pref_appearance_dark'],
                'device' => $lang['user_pref_appearance_device']
            ),
            true,
            300,
            null,
            true,
            "setThemePreference();",
            false,
            true
        );
        $page_def[] = config_add_boolean_select(
            'high_contrast_mode',
            $lang['userpreference_high_contrast_mode'],
            $enable_disable_options,
            300,
            '',
            true,
            null,
            false,
            '',
            true
        );
        $page_def[] = config_add_html('</div>');

        // Email section, only show if user has got an email address
        if ($useremail != "") {
            $page_def[] = config_add_html('<h2 class="CollapsibleSectionHead">' . $lang['email'] . '</h2><div id="UserPreferenceEmailSection" class="CollapsibleSection">');
            $page_def[] = config_add_boolean_select('cc_me', $lang['userpreference_cc_me_label'], $enable_disable_options, 300, '', true);
            $page_def[] = config_add_boolean_select('email_user_notifications', $lang['userpreference_email_me_label'], $enable_disable_options, 300, '', true);
            $page_def[] = config_add_boolean_select('email_and_user_notifications', $lang['user_pref_email_and_user_notifications'], $enable_disable_options, 300, '', true);
            if ($actions_on && $new_action_email_interval > 0) {
                $page_def[] = config_add_boolean_select('user_pref_new_action_emails', $lang['user_pref_new_action_emails'], $enable_disable_options, 300, '', true);
            }
            $page_def[] = config_add_boolean_select('user_pref_daily_digest', $lang['user_pref_daily_digest'], $enable_disable_options, 300, '', true);
            $page_def[] = config_add_boolean_select('user_pref_inactive_digest', str_replace("[days]", (string)(int)$inactive_message_auto_digest_period, $lang['user_pref_inactive_digest']), $enable_disable_options, 300, '', true);
            $page_def[] = config_add_boolean_select('user_pref_daily_digest_mark_read', $lang['user_pref_daily_digest_mark_read'], $enable_disable_options, 300, '', true);
            $page_def[] = config_add_html('</div>');
        }

        // System notifications section - used to disable system generated messages
        $page_def[] = config_add_html('<h2 class="CollapsibleSectionHead">' . $lang['mymessages'] . '</h2><div id="UserPreferenceMessageSection" class="CollapsibleSection">');
        $page_def[] = config_add_boolean_select('user_pref_show_notifications', $lang['user_pref_show_notifications'], $enable_disable_options, 300, '', true);
        $page_def[] = config_add_boolean_select('user_pref_resource_notifications', $lang['userpreference_resource_notifications'], $enable_disable_options, 300, '', true);
        if (checkperm("a")) {
            $page_def[] = config_add_boolean_select('user_pref_system_management_notifications', $lang['userpreference_system_management_notifications'], $enable_disable_options, 300, '', true);
        }

        if (checkperm("u")) {
            $page_def[] = config_add_boolean_select('user_pref_user_management_notifications', $lang['userpreference_user_management_notifications'], $enable_disable_options, 300, '', true);
        }
        if (checkperm("R")) {
            $page_def[] = config_add_boolean_select('user_pref_resource_access_notifications', $lang['userpreference_resource_access_notifications'], $enable_disable_options, 300, '', true);
        }
        $page_def[] = "AFTER_MESSAGES_MARKER"; // Added so that hook add_user_preference_page_def can locate this position in array
        $page_def[] = config_add_html('</div>');

        // Actions section - used to configure the alerts that appear in 'My actions'
        if ($actions_on) {
            $page_def[] = config_add_html('<h2 class="CollapsibleSectionHead">' . $lang['actions_myactions'] . '</h2><div id="UserPreferenceActionSection" class="CollapsibleSection">');
            if (checkperm("R")) {
                $page_def[] = config_add_boolean_select('actions_resource_requests', $lang['actions_resource_requests'], $enable_disable_options, 300, '', true);
            }
            if (checkperm("u")) {
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
            get_config_option(['user' => $userref, 'usergroup' => $usergroup], 'actions_resource_review', $legacy_resource_review, true);
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

        // Metadata section
        if (!$force_exiftool_write_metadata) {
            $page_def[] = config_add_html('<h2 class="CollapsibleSectionHead">' . $lang['metadata'] . '</h2><div id="UserPreferenceMetadataSection" class="CollapsibleSection">');
            $page_def[] = config_add_boolean_select('exiftool_write_option', $lang['userpreference_exiftool_write_metadata_label'], $enable_disable_options, 300, '', true);
            $page_def[] = config_add_html('</div>');
        }

        // Upload options , only for autorotate at present

        if ($camera_autorotation) {
            $page_def[] = config_add_html('<h2 class="CollapsibleSectionHead">' . $lang["upload-options"] . '</h2><div id="UserPreferenceUploadOptionsSection" class="CollapsibleSection">');
            if (!isset($autorotation_preference)) {
                $autorotation_preference = $camera_autorotation_checked;
            }
            $page_def[] = config_add_boolean_select('autorotation_preference', $lang['user_pref_autorotate'], $enable_disable_options, 300, '', true);
            $page_def[] = config_add_html('</div>');
        }

        // Let plugins hook onto page definition and add their own configs if needed
        // or manipulate the list
        $plugin_specific_definition = hook('add_user_preference_page_def', '', array($page_def), true);
        if (is_array($plugin_specific_definition) && !empty($plugin_specific_definition)) {
            $page_def = $plugin_specific_definition;
        }

        // Process autosaving requests
        // Note: $page_def must be defined by now in order to make sure we only save options that we've defined
        if ('true' === getval('ajax', '') && 'true' === getval('autosave', '')) {
            // Get rid of any output we have so far as we don't need to return it
            ob_end_clean();
            header('Content-Type: application/json');

            $response['success'] = true;
            $response['message'] = '';

            $autosave_option_name  = getval('autosave_option_name', '');
            $autosave_option_value = getval('autosave_option_value', '');

            if ($autosave_option_name == 'user_local_timezone') { # If '$autosave_option_name' = 'user_local_timezone' - save to cookie
                rs_setcookie('user_local_timezone', $autosave_option_value, 365);
            }

            // Search for the option name within our defined (allowed) options
            // if it is not there, error and don't allow saving it
            $page_def_option_index = array_search($autosave_option_name, array_column($page_def, 1));
            if (false === $page_def_option_index) {
                $response['success'] = false;
                $response['message'] = $lang['systemconfig_option_not_allowed_error'];

                echo json_encode($response);
                exit();
            }

            if (!set_config_option($userref, $autosave_option_name, $autosave_option_value, 'User configuration change')) {
                $response['success'] = false;
            }

            echo json_encode($response);
            exit();
        }

        $page_def = config_filter_by_search($page_def, ['user' => $userref], getval("filter", ""), getval("only_modified", "no"));

        config_generate_html($page_def);
        ?>
    </div>
    <script>registerCollapsibleSections();</script>
    <?php config_generate_AutoSaveConfigOption_function($baseurl . '/pages/user/user_preferences.php'); ?>
</div>

<?php
include '../../include/footer.php';