<?php

// Send email notification of new actions

// Record the time at the start so as not to miss actions that may be created during processing
$this_run_start = date("Y-m-d H:i:s");

if ((int)$new_action_email_interval == 0) {
    if ('cli' == PHP_SAPI) {
        echo "{$baseurl} - {$this_run_start} - Action notifications - \$new_action_email_interval not set. Skipping" . PHP_EOL;
    }
    return;
}

$new_action_email_interval = ceil(min($new_action_email_interval, ACTIONS_EMAIL_MAX_AGE));

$last_action_notifications = get_sysvar('last_action_notification_emails', '1970-01-01');
$action_notifications_elapsed_sec = time() - strtotime($last_action_notifications);

// Don't run if the elapsed time since last run is shorter than the configured value in hours
if ($action_notifications_elapsed_sec < $new_action_email_interval * 60 * 60) {
    logScript(" - Skipping action email notifications - last run: " . $last_action_notifications);
    return;
}

$action_notify_users = get_users_by_preference("user_pref_new_action_emails", "1");

// If cron hasn't been run for a long time only go back a maximum of 7 days
$action_notifications_check_minutes = floor(min($action_notifications_elapsed_sec, 7 * 24 * 60 * 60) / 60);

logScript(" - Finding actions created in the last $action_notifications_check_minutes minutes");
$recentactions = get_user_actions_recent(ceil($action_notifications_check_minutes) + 1, true);
foreach ($recentactions as $notifyuser => $user_actions) {
    if (!in_array($notifyuser, $action_notify_users)) {
        // User not set to receive action emails
        logScript(" - Skipping action notification email for user ref " . $notifyuser . " as not configured");
    }
    $actionuser = get_user($notifyuser);
    $usermail = $actionuser["email"];

    // Set timezone if required
    $default_user_local_timezone = $user_local_timezone;
    get_config_option(['user' => $notifyuser, 'usergroup' => $actionuser['usergroup']], 'user_local_timezone', $user_local_timezone, $default_user_local_timezone);

    if (!filter_var($usermail, FILTER_VALIDATE_EMAIL)) {
        logScript(" - Skipping action notification email for user ref " . $notifyuser . " due to invalid email:  " . $usermail);
        continue;
    }

    // Construct email notification
    logScript(" - Checking action email notification for user " . $usermail);

    $usernotification = new ResourceSpaceUserNotification();
    $usernotification->set_subject($applicationname . ": ");
    $usernotification->append_subject('lang_actions_email_new_actions_title');

    $usernotification->set_text('<div id="CentralSpaceContainer"><div id="CentralSpace"><div class="BasicsBox">');
    $usernotification->append_text('lang_actions_email_new_actions_intro');
    $usernotification->append_text('<br /><br />');
    $usernotification->append_text('<div class="Listview"><table class="ListviewStyle" style="min-width: 70%">');
    $usernotification->append_text('<tr class="ListviewTitleStyle"><td>');
    $usernotification->append_text('lang_date');
    $usernotification->append_text('</td><td>');
    $usernotification->append_text('lang_property-reference');
    $usernotification->append_text('</td><td>');
    $usernotification->append_text('lang_user');
    $usernotification->append_text('</td><td>');
    $usernotification->append_text('lang_description');
    $usernotification->append_text('</td><td>');
    $usernotification->append_text('lang_type');
    $usernotification->append_text('</td><td>');
    $usernotification->append_text('lang_tools');
    $usernotification->append_text('</td></tr>');
    foreach ($user_actions as $actiontype => $type_actions) {
        foreach ($type_actions as $user_action) {
            $actionlinks = hook("actioneditlink", '', array($user_action));
            if ($actionlinks) {
                $actioneditlink = $actionlinks["editlink"];
                $actionviewlink = $actionlinks["viewlink"];
            } else {
                $actioneditlink = '';
                $actionviewlink = '';
            }

            switch ($user_action["type"]) {
                case "resourcereview":
                    $actioneditlink = $baseurl . "/pages/edit.php";
                    $actionviewlink = $baseurl . "/pages/view.php";
                    break;
                case "resourcerequest":
                    $actioneditlink = $baseurl . "/pages/team/team_request_edit.php";
                    break;
                case "userrequest":
                    $actioneditlink = $baseurl . "/pages/team/team_user_edit.php";
                    break;
                default:
                    break;
            }

            $linkparams["ref"] = $user_action["ref"];
            $editlink = ($actioneditlink == '') ? '' : generateURL($actioneditlink, $linkparams);
            $viewlink = ($actionviewlink == '') ? '' : generateURL($actionviewlink, $linkparams);

            $usernotification->append_text('<tr><td>' . nicedate($user_action["date"], true, true, true) . '</td>');
            $usernotification->append_text('<td><a href="' . $editlink . '" >' . $user_action["ref"] . '</a></td>');

            $actionfromuser = get_user($user_action["user"]);
            if ($actionfromuser === false) {
                // User may have been deleted
                $actionusername = $user_action["user"];
            } else {
                $actionusername = isset($actionfromuser["fullname"]) ? $actionfromuser["fullname"] : $actionfromuser["username"];
            }
            $usernotification->append_text('<td>' . escape($actionusername) . '</td>');
            $usernotification->append_text('<td>' . escape(tidy_trim((string) $user_action["description"], 200)) . '</td>');
            $usernotification->append_text('<td>');
            $langtype = 'actions_type_' . $user_action['type'];
            $usernotification->append_text('lang_' . $langtype);
            $usernotification->append_text('</td>');
            $usernotification->append_text('<td><div class="ListTools">');

            if ($editlink != "") {
                $usernotification->append_text('<a href="' . $editlink . '">');
                $usernotification->append_text('lang_action-edit');
                $usernotification->append_text('</a>&nbsp;&nbsp;');
            }
            if ($viewlink != "") {
                $usernotification->append_text('<a href="' . $viewlink . '">');
                $usernotification->append_text('lang_view');
                $usernotification->append_text('</a>');
            }
            $usernotification->append_text('</div></td></tr>');
        }
    }
    $usernotification->append_text('</table></div><!-- End of Listview -->');
    $userprefurl = $baseurl . "/pages/user/user_preferences.php#UserPreferenceEmailSection";
    $usernotification->append_text('<br /><br />');
    $usernotification->append_text('lang_actions_introtext');
    $usernotification->append_text('<br /><a href="' . $userprefurl  . '">' . $userprefurl . '</a>');
    $usernotification->append_text('</div><!-- End of Listview -->');
    $usernotification->append_text('</div><!-- End of BasicsBox -->');
    $usernotification->append_text('</div><!-- End of CentralSpace -->');
    $usernotification->append_text('</div><!-- End of CentralSpaceContainer -->');
    if (count($user_actions) > 0) {
        // Send the email
        logScript(" - Sending summary to user ref " . $notifyuser . ", email " . $usermail);
        send_user_notification([$notifyuser], $usernotification, true);
    }
    // End of each user's actions
}

# Update last run date/time.
set_sysvar("last_action_notification_emails", $this_run_start);
