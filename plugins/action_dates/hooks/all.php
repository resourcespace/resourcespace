<?php

function HookAction_datesAllInitialise()
{
    global $action_dates_fieldvars;
    config_register_core_fieldvars("Action dates plugin", $action_dates_fieldvars);
}

function HookAction_datesCronCron()
{
    global $lang, $action_dates_restrictfield,$action_dates_deletefield, $resource_deletion_state,
           $action_dates_reallydelete, $action_dates_email_admin_days, $email_notify, $email_from,
           $applicationname, $action_dates_new_state, $action_dates_remove_from_collection,
           $action_dates_extra_config, $DATE_FIELD_TYPES, $action_dates_email_for_state,
           $action_dates_email_for_restrict, $action_dates_eligible_states, $action_dates_weekdays, $action_dates_workflow_actions;

    global $baseurl,$baseurl_short;

    $validfieldtypes = [FIELD_TYPE_DATE_AND_OPTIONAL_TIME,FIELD_TYPE_EXPIRY_DATE,FIELD_TYPE_DATE];

    # Save the resource_deletion_state because it can be manipulated during primary action processing
    $saved_resource_deletion_state = $resource_deletion_state;

    if (PHP_SAPI == "cli") {
        echo "action_dates: Running cron tasks on " . date("Y-m-d h:i:s") . PHP_EOL;
    }

    // Don't run more than once every 24 hours
    $last_action_dates_cron  = get_sysvar('last_action_dates_cron', '1970-01-01');

    # No need to run if already run in last 24 hours.
    if (strtotime($last_action_dates_cron) > strtotime("today")) {
        if ('cli' == PHP_SAPI) {
            echo " - Skipping action date cron - last run: " . $last_action_dates_cron . PHP_EOL;
        }
        return false;
    }

    # Store time to update last run date/time after completion
    $this_run_start = date("Y-m-d H:i:s");

    // Check for correct day of week
    if (!in_array(date("w"), $action_dates_weekdays)) {
        if ('cli' == PHP_SAPI) {
            echo "action_dates: not correct weekday to run" . PHP_EOL;
        }
        return true;
    }

    # Reset any residual userref from earlier cron tasks
    global $userref;
    $userref = 0;

    $eligible_states = array();
    if (isset($action_dates_eligible_states) && is_array($action_dates_eligible_states)) {
        $eligible_states = $action_dates_eligible_states;
    }

    $allowable_fields = ps_array("SELECT ref AS value FROM resource_type_field WHERE type in (" . ps_param_insert(count($validfieldtypes)) . ")", ps_param_fill($validfieldtypes, "i"), "schema");
    $email_state_refs    = array();   # List of refs which are due to undergo state change (including full deletion) in n days
    $email_state_days    = array();   # List of days due to undergo state change (including full deletion) in n days
    $email_restrict_refs = array();   # List of refs which are due to be restricted in n days
    $email_restrict_days = array();   # List of days due to be restricted in n days
    $state_change_notify = array();   # List of refs whose state has changed (excluding full deletion)

    $action_date_current = date_create(date("Y-m-d")); # Date of this run

    # Process resource access restriction if a restriction date has been configured
    # The restriction date will be processed if it is full date or a partial date because either will yield viable timestamps
    if (in_array($action_dates_restrictfield, $allowable_fields)) {
        if (PHP_SAPI == "cli") {
            echo "action_dates: Checking restrict action field $action_dates_restrictfield." . PHP_EOL;
        }

        $sql = "SELECT rn.resource, n.name AS value FROM resource_node rn LEFT JOIN node n ON n.ref=rn.node LEFT JOIN resource r ON r.ref=rn.resource ";
        $sql_params = array();
        if (!empty($eligible_states)) {
            $sql .= "AND r.archive IN (" . ps_param_insert(count($eligible_states)) . ") ";
            $sql_params = array_merge($sql_params, ps_param_fill($eligible_states, "i"));
        }

        $sql .= "WHERE r.ref > 0 AND r.access=0 AND n.resource_type_field = ?";
        $sql_params = array_merge($sql_params, array('i',$action_dates_restrictfield));

        $restrict_resources = ps_query($sql, $sql_params);
        foreach ($restrict_resources as $resource) {
            $ref = $resource["resource"];

            $restrict_date_target = date_create($resource["value"]);   # Value of the restrict date from metadata

            # Candidate restriction date reached or passed
            if ($action_date_current >= $restrict_date_target) {
                # Restrict access to the resource
                $existing_access = ps_value("SELECT access AS value FROM resource WHERE ref = ?", ["i",$ref], "");
                if ($existing_access == 0) { # Only apply to resources that are currently open
                    if (PHP_SAPI == "cli") {
                        echo " - Restricting resource {$ref}" . PHP_EOL;
                    }
                    ps_query("UPDATE resource SET access=1 WHERE ref = ?", ["i",$ref]);
                    resource_log($ref, 'a', '', $lang['action_dates_restrict_logtext'], $existing_access, 1);
                }
            } else {
                # Due to restrict in n days
                if ($action_dates_email_admin_days != "") { # Set up email notification to admin of expiring resources
                    $restrict_interval = date_diff($action_date_current, $restrict_date_target);
                    $days_before_restrict = (int) $restrict_interval->format('%R%a');
                    # Check due number of days within range for notification
                    if ($days_before_restrict <= $action_dates_email_admin_days) {
                        $email_restrict_refs[] = $ref;
                        $email_restrict_days[] = $days_before_restrict;
                    }
                }
            }
        }
    }

    # Process resource deletion or statechange if designated date has been configured
    # The designated date will be processed if it is a valid date field
    if (in_array($action_dates_deletefield, $allowable_fields)) {
        $change_archive_state = false;

        $fieldinfo = get_resource_type_field($action_dates_deletefield);
        if (PHP_SAPI == "cli") {
            echo "action_dates: Checking state action field $action_dates_deletefield." . PHP_EOL;
        }

        $validrestypes = false;
        if ($fieldinfo["global"] == 0) {
            $validrestypes = ps_array("SELECT resource_type value FROM resource_type_field_resource_type WHERE resource_type_field = ?", ["i",$action_dates_deletefield]);
            if (count($validrestypes) === 0) {
                if ('cli' == PHP_SAPI) {
                    echo 'action_dates: Error - $action_dates_deletefield applies to no resource types.' . PHP_EOL;
                }
                set_sysvar("last_action_dates_cron", $this_run_start);
                return false;
            }
        }

        if ($action_dates_reallydelete) {
            # FULL DELETION - Build candidate list of resources which have the deletion date field populated
            $sql = "SELECT rn.resource, n.name AS value FROM resource_node rn LEFT JOIN node n ON n.ref=rn.node LEFT JOIN resource r ON r.ref=rn.resource ";
            $sql_params = array();
            if (!empty($eligible_states)) {
                $sql .= "AND r.archive IN (" . ps_param_insert(count($eligible_states)) . ") ";
                $sql_params = array_merge($sql_params, ps_param_fill($eligible_states, "i"));
            }
            $sql .= "WHERE r.ref > 0 AND n.resource_type_field = ?";
            $sql_params = array_merge($sql_params, array("i",$action_dates_deletefield));

            // Filter resource types that shouldn't have access to the field
            if (is_array($validrestypes)) {
                $sql .= " AND r.resource_type IN (" . ps_param_insert(count($validrestypes)) . ") ";
                $sql_params = array_merge($sql_params, ps_param_fill($validrestypes, "i"));
            }
            $candidate_resources = ps_query($sql, $sql_params);
        } else {
            # NOT FULL DELETION - If not already configured, establish the default resource deletion state
            if (!isset($resource_deletion_state)) {
                $resource_deletion_state = 3;
            }
            # NOT FULL DELETION - Build candidate list of resources which have the deletion date field populated
            #                     and which are neither in the resource deletion state nor in the action dates new state
            $sql = "SELECT rn.resource, n.name AS value FROM resource_node rn LEFT JOIN node n ON n.ref=rn.node LEFT JOIN resource r ON r.ref=rn.resource WHERE r.ref > 0 AND n.resource_type_field = ? ";
            $sql_params = ["i",$action_dates_deletefield];


            if (!empty($eligible_states)) {
                $sql .= "AND r.archive IN (" . ps_param_insert(count($eligible_states)) . ") ";
                $sql_params = array_merge($sql_params, ps_param_fill($eligible_states, "i"));
            }

            $sql .= " AND r.archive NOT IN (?,?) ";
            $sql_params = array_merge($sql_params, ["i",$resource_deletion_state,"i",$action_dates_new_state]);

            // Filter resource types that shouldn't have access to the field
            if (is_array($validrestypes)) {
                $sql .= " AND r.resource_type IN (" . ps_param_insert(count($validrestypes)) . ") ";
                $sql_params = array_merge($sql_params, ps_param_fill($validrestypes, "i"));
            }
            $candidate_resources = ps_query($sql, $sql_params);

            # NOT FULL DELETION - Resolve the target archive state to which candidate resources are to be moved
            # If the new state differs from the default resource deletion state, it means we only want to move resources to that state
            if ($action_dates_new_state != $resource_deletion_state) {
                $resource_deletion_state = $action_dates_new_state;
                $change_archive_state    = true;
            }
            # The resource deletion state now represents the target archive state
        }

        # Process the list of candidates
        foreach ($candidate_resources as $resource) {
            $ref = $resource['resource'];
            $action_date_target = date_create($resource["value"]);   # Value of the restrict date from metadata

            # Candidate deletion date reached or passed
            if ($action_date_current >= $action_date_target) {
                if (PHP_SAPI == "cli") {
                    if (!$change_archive_state) {
                        // Delete the resource as date has been reached
                        echo " - Deleting resource {$ref}" . PHP_EOL;
                    } else {
                        echo " - Moving resource {$ref} to archive state '{$resource_deletion_state}'" . PHP_EOL;
                    }
                }
                if ($action_dates_reallydelete) {
                    # FULL DELETION
                    # Unset deletion state to force the resource to be fully deleted
                    $original_deletion_state = $GLOBALS['resource_deletion_state'];
                    unset($GLOBALS['resource_deletion_state']);
                    delete_resource($ref);
                    $GLOBALS['resource_deletion_state'] = $original_deletion_state;
                } else {
                    # NOT FULL DELETION - Update resources to the target archive state
                    ps_query("UPDATE resource SET archive = ? WHERE ref = ?", ["i",$resource_deletion_state,"i",$ref]);
                    $state_change_notify[] = $ref;
                    if ($action_dates_remove_from_collection) {
                        // Remove the resource from any collections
                        $ref_containing_collections = ps_array("SELECT collection AS `value` FROM collection_resource where resource = ?", array('i', $ref));
                        foreach ($ref_containing_collections as $collection) {
                            collection_log($collection, LOG_CODE_COLLECTION_REMOVED_RESOURCE, $ref, $lang['action_dates_delete_logtext']);
                        }
                        ps_query("DELETE FROM collection_resource WHERE resource=?", ["i",$ref]);
                    }
                }

                resource_log($ref, 'x', '', $lang['action_dates_delete_logtext']);
            } else {
                # Due to be deleted (or ortherwise actioned) in n days
                if ($action_dates_email_admin_days != "") { # Set up email notification to admin of resources changing state
                    $action_interval = date_diff($action_date_current, $action_date_target);
                    $days_before_action = (int) $action_interval->format('%R%a');
                    if ($days_before_action <= $action_dates_email_admin_days) {
                        $email_state_refs[] = $ref;
                        $email_state_days[] = $days_before_action;
                    }
                }
            }
        }
        if ($action_dates_workflow_actions == "1" && count($state_change_notify) > 0) {
            hook('after_update_archive_status', '', array($state_change_notify, $resource_deletion_state,""));
        }
    }

    // Only allow email for actions configured.
    if ($action_dates_restrictfield == "") {
        $action_dates_email_for_restrict = "0";
    }
    if ($action_dates_deletefield == "") {
        $action_dates_email_for_state = "0";
    }

    $message_state = "";
    $message_restrict = "";
    $message_combined = "";
    $subject_state = "";
    $subject_restrict = "";
    $subject_combined = "";

    $subject_state = $lang['action_dates_email_subject_state'];
    if (count($email_state_days) == 0 || (min($email_state_days) == max($email_state_days))) {
        $message_state = str_replace("[days]", (count($email_state_days) > 0 ? min($email_state_days) : $action_dates_email_admin_days), $lang['action_dates_email_text_state']);
    } else {
        $message_state = str_replace("[days_min]", (count($email_state_days) > 0 ? min($email_state_days) : $action_dates_email_admin_days), $lang['action_dates_email_range_state']);
        $message_state = str_replace("[days_max]", (count($email_state_days) > 0 ? max($email_state_days) : $action_dates_email_admin_days), $message_state);
    }

    $subject_restrict = $lang['action_dates_email_subject_restrict'];
    if (count($email_restrict_days) == 0 || (min($email_restrict_days) == max($email_restrict_days))) {
        $message_restrict = str_replace("[days]", (count($email_restrict_days) > 0 ? min($email_restrict_days) : $action_dates_email_admin_days), $lang['action_dates_email_text_restrict']) . "\r\n";
    } else {
        $message_restrict = str_replace("[days_min]", (count($email_restrict_days) > 0 ? min($email_restrict_days) : $action_dates_email_admin_days), $lang['action_dates_email_range_restrict']);
        $message_restrict = str_replace("[days_max]", (count($email_restrict_days) > 0 ? max($email_restrict_days) : $action_dates_email_admin_days), $message_restrict);
    }

    # Determine how and to whom notifications are to be sent
    $admin_notify_emails = array();
    $admin_notify_users = array();
    $notify_users = get_notification_users("RESOURCE_ADMIN");

    foreach ($notify_users as $notify_user) {
        get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'user_pref_resource_notifications', $send_message);
        if (!$send_message) {
            # If this user doesn't want notifications they won't get any messages or emails
            continue;
        }

        # Notification is required; it will either be sent as an email only or as a message with a possible additional email
        get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'email_user_notifications', $send_email);
        if ($send_email && $notify_user["email"] != "") {
            $admin_notify_emails[] = $notify_user['email'];
        } else {
            $admin_notify_users[] = $notify_user["ref"];
        }
    }

    # Prepare and send combined restrict and/or state change notifications if necessary
    if (
        (count($email_restrict_refs) > 0 && count($email_state_refs) > 0)
        && ($action_dates_email_for_restrict == "1" && $action_dates_email_for_state == "1")
    ) {
        # Notification is for the resources whose dates are within the specified number of days
        $subject_combined = $lang['action_dates_email_subject'];

        $action_combined_days = array_merge($email_restrict_days, $email_state_days);

        if (min($action_combined_days) == max($action_combined_days)) {
            $message_combined = str_replace("[days]", min($action_combined_days), $lang['action_dates_email_text']) . "\r\n";
        } else {
            $message_combined = str_replace("[days_min]", min($action_combined_days), $lang['action_dates_email_range']);
            $message_combined = str_replace("[days_max]", max($action_combined_days), $message_combined) . "\r\n";
        }

        $notification_restrict = $message_restrict;
        $notification_state = $message_state;

        # Reconstruct combined message for purposes of emailing
        $message_combined = $message_restrict . $baseurl . "?r=" . implode("\r\n" . $baseurl . "?r=", $email_restrict_refs) . "\r\n";
        $message_combined .= $message_state . $baseurl . "?r=" . implode("\r\n" . $baseurl . "?r=", $email_state_refs) . "\r\n";
        $templatevars['message'] = $message_combined;

        # Construct url lists for message_add function
        $url_restrict = build_specialsearch_list_urls($email_restrict_refs);
        $url_state = build_specialsearch_list_urls($email_state_refs);

        foreach ($admin_notify_emails as $admin_notify_email) {
            send_mail($admin_notify_email, $applicationname . ": " . $subject_combined, $message_combined, "", "", "emailproposedchanges", $templatevars);
        }

        if (count($admin_notify_users) > 0) {
            if (PHP_SAPI == "cli") {
                echo "Sending notification to user refs: " . implode(",", $admin_notify_users) . PHP_EOL;
            }
            # Note that message_add can also send an additional email
            message_add($admin_notify_users, $notification_restrict . $url_restrict['multiple'], $url_restrict['single'], 0);
            message_add($admin_notify_users, $notification_state . $url_state['multiple'], $url_state['single'], 0);
        }

        # Now empty the arrays to prevent separate notifications because they have already been dealt with here
        $email_state_refs = array();
        $email_restrict_refs = array();
    }

    # Prepare and send separate state change notifications
    if (count($email_state_refs) > 0 && $action_dates_email_for_state == "1") {
        # Send a notification for the resources whose date is within the specified number of days
        $notification_state = $message_state;
        $message_state .= $baseurl . "?r=" . implode("\r\n" . $baseurl . "?r=", $email_state_refs) . "\r\n";
        $url = build_specialsearch_list_urls($email_state_refs);
        $templatevars['message'] = $message_state;

        foreach ($admin_notify_emails as $admin_notify_email) {
            send_mail($admin_notify_email, $applicationname . ": " . $subject_state, $message_state, "", "", "emailproposedchanges", $templatevars);
        }

        if (count($admin_notify_users) > 0) {
            if (PHP_SAPI == "cli") {
                echo "Sending notification to user refs: " . implode(",", $admin_notify_users) . PHP_EOL;
            }
            # Note that message_add can also send an additional email
            message_add($admin_notify_users, $notification_state . $url['multiple'], $url['single'], 0);
        }
    }

    # Prepare and send separate access restrict notifications
    if (count($email_restrict_refs) > 0 && $action_dates_email_for_restrict == "1") {
        # Send a notification for the resources whose date is within the specified number of days
        $notification_restrict = $message_restrict;
        $message_restrict .= $baseurl . "?r=" . implode("\r\n" . $baseurl . "?r=", $email_restrict_refs) . "\r\n";
        $url = build_specialsearch_list_urls($email_restrict_refs);
        $templatevars['message'] = $message_restrict;

        foreach ($admin_notify_emails as $admin_notify_email) {
            send_mail($admin_notify_email, $applicationname . ": " . $subject_restrict, $message_restrict, "", "", "emailproposedchanges", $templatevars);
        }

        if (count($admin_notify_users) > 0) {
            if (PHP_SAPI == "cli") {
                echo "Sending notification to user refs: " . implode(",", $admin_notify_users) . PHP_EOL;
            }
            # Note that message_add can also send an additional email
            message_add($admin_notify_users, $notification_restrict . $url['multiple'], $url['single'], 0);
        }
    }

    # Restore the resource_deletion_state which may have been manipulated during primary action processing
    $resource_deletion_state = $saved_resource_deletion_state;

    # Perform additional actions if configured
    foreach ($action_dates_extra_config as $action_dates_extra_config_setting) {
        $datefield = get_resource_type_field($action_dates_extra_config_setting["field"]);
        $field = $datefield["ref"];

        $validrestypes = false;
        if ($datefield["global"] == 0) {
            $validrestypes = ps_array("SELECT resource_type value FROM resource_type_field_resource_type WHERE resource_type_field = ?", ["i",$field]);
            if (count($validrestypes) === 0) {
                if ('cli' == PHP_SAPI) {
                    echo 'action_dates: Error - Date field set in additional actions applies to no resource types.' . PHP_EOL;
                }
                set_sysvar("last_action_dates_cron", $this_run_start);
                return false;
            }
        }

        $newstatus = $action_dates_extra_config_setting["status"];
        if (in_array($datefield['type'], $DATE_FIELD_TYPES)) {
            if (PHP_SAPI == "cli") {
                echo "action_dates: Checking extra action dates for field " . $datefield["ref"] . "." . PHP_EOL;
            }
            $sql = "SELECT 
                rn.resource, 
                n.name AS value 
            FROM resource_node rn 
                LEFT JOIN resource r ON r.ref=rn.resource 
                LEFT JOIN node n ON n.ref=rn.node
            WHERE r.ref > 0 
                AND n.resource_type_field = ?
                AND r.archive<> ?";

            $sql_params = array(
                "i",$field,
                "i",$newstatus,
            );

            if (isset($resource_deletion_state)) {
                $sql .= " AND r.archive <> ?";
                $sql_params[] = "i";
                $sql_params[] = $resource_deletion_state;
            }

            // Filter resource types that shouldn't have access to the field
            if (is_array($validrestypes)) {
                $sql .= " AND r.resource_type IN (" . ps_param_insert(count($validrestypes)) . ") ";
                $sql_params = array_merge($sql_params, ps_param_fill($validrestypes, "i"));
            }

            $additional_resources = ps_query($sql, $sql_params);

            foreach ($additional_resources as $resource) {
                $ref = $resource["resource"];

                if (time() >= strtotime($resource["value"])) {
                    if (PHP_SAPI == "cli") {
                        echo "action_dates: Moving resource {$ref} to archive state " . $lang["status" . $newstatus] . PHP_EOL;
                    }
                    update_archive_status($ref, $newstatus);
                }
            }
        }
    }

    # Update last run date/time.
    set_sysvar("last_action_dates_cron", $this_run_start);
}

// This is required if cron task is run via pages/tools/cron_copy_hitcount.php
function HookAction_datesCron_copy_hitcountCron()
{
    HookAction_datesCronCron();
}
