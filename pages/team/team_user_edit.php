<?php

/**
 * User edit form display page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../include/api_functions.php";

$ref = getval("ref", "", true);
$backurl = getval("backurl", "");
$modal = (getval("modal", "") == "true");

$urlparams = array(
    'ref' => $ref,
    'backurl' => $backurl,
);

$pageurl = generateURL($baseurl_short . "pages/team/team_user_edit.php", $urlparams);

if (!checkPermission_manage_users()) {
    exit(escape($lang["error-permissiondenied"]));
}

$approval_state_text = array(0 => $lang["notapproved"],1 => $lang["approved"], 2 => $lang["disabled"]);

if (getval("unlock", "") != "" && enforcePostRequest(getval("ajax", false))) {
    # reset user lock
    ps_query("update user set login_tries='0' where ref= ?", ['i', $ref]);
} elseif (getval("suggest", "") != "") {
    echo make_password();
    exit();
} elseif (getval("save", "") != "" && enforcePostRequest(getval("ajax", false))) {
    # Save user data
    $result = save_user($ref);
    if ($result !== true) {
        $error = $result;
    } else {
        hook('aftersaveuser');
        if (getval("save", "") != "" && !$modal) {
            redirect($backurl != "" ? $backurl : $baseurl_short . "pages/team/team_user.php?nc=" . time());
            exit();
        }
        if (getval("save", "") != "" && $modal) {
            # close Modal and return to action list
            echo "<script>ModalClose()</script>";
            exit();
        }
    }
}

# Fetch user data
$user = get_user($ref);
if ($user === false) {
    $error = $lang['accountdoesnotexist'];
    if (getval("ajax", "") != "") {
        error_alert($error, false);
    } else {
        include __DIR__ . "/../../include/header.php";
        $onload_message = array("title" => $lang["error"],"text" => $error);
        include __DIR__ . "/../../include/footer.php";
    }
    exit();
}

if (!checkperm_user_edit($user)) {
    error_alert($lang["error-permissiondenied"], true);
    exit();
}

// Block this from running if we are logging in as a user because running this here will block boot.php from setting headers
if (getval('loginas', '') === '') {
    include "../../include/header.php";
}

// Log in as this user. A user key must be generated to enable login using a hash as the password.
if (getval('loginas', '') != '') {
    if (!checkperm_login_as_user(getval('ref', ''))) {
        exit("permission denied");
    }
    // Log user switch in the activity log for both sides (the user we moved from and the one we moved to)
    $log_activity_note = str_replace(
        array('%USERNAME_FROM', '%USERNAME_TO'),
        array($username, $user['username']),
        $lang['activity_log_admin_log_in_as']
    );
    log_activity($log_activity_note, LOG_CODE_LOGGED_IN, null, 'user', null, null, null, null, $userref, false);
    log_activity($log_activity_note, LOG_CODE_LOGGED_IN, null, 'user', null, null, null, null, $user['ref'], false);

    global $CSRF_token_identifier, $usersession;

    // userkey and CSRF tokens still need to be placed in post array as perform_login() references these directly
    $_POST = [];
    $_POST['username'] = $user['username'];
    $_POST['password'] = $user['password'];
    $_POST['userkey'] = hash_hmac("sha256", "login_as_user" . $user["username"] . date("Ymd"), $scramble_key, true);
    $_POST[$CSRF_token_identifier] = generateCSRFToken($usersession, 'autologin');

    hook('impersonateuser', "", [$user['ref']]);

    include '../../login.php';
    exit();
}
?>

<div class="BasicsBox">
    <div class="RecordHeader">
        <h1><?php echo escape($lang["edituser"]); ?></h1>

        <?php
        // Breadcrumbs links
        renderBreadcrumbs([
            [
                'title' => $lang["teamcentre"],
                'href'  => $baseurl_short . "pages/team/team_home.php",
                'menu' =>  true
            ],
            [
                'title' => $lang["manageusers"],
                'href'  => strpos($backurl, "team_user.php") !== false ? $backurl : $baseurl_short . "pages/team/team_user.php"
            ],
            [
                'title' => $lang["edituser"],
                'help' => 'systemadmin/creating-users'
            ]
        ]);
        ?>
    </div>

    <?php
    if (isset($error)) { ?>
        <div class="FormError"><?php echo escape($error); ?></div>
        <?php
    }

    if (isset($message)) { ?>
        <div class="PageInfoMessage"><?php echo $message?></div>
        <?php
    } ?>

    <form method=post action='<?php echo escape($pageurl); ?>' onsubmit='return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(this,true);'>
        <?php
        if ($modal) {
            ?>
            <input type=hidden name="modal" value="true">
            <?php
        }

        generateFormToken("team_user_edit");
        ?>
        <input type=hidden name=ref value="<?php echo urlencode($ref) ?>">
        <input type=hidden name=backurl value="<?php echo escape(getval("backurl", $baseurl_short . "pages/team/team_user.php?nc=" . time()))?>">
        <input type=hidden name="save" value="save" /><!-- to capture default action -->

        <?php
        if (
            ($user["login_tries"] >= $max_login_attempts_per_username)
            && (strtotime($user["login_last_try"]) > (time() - ($max_login_attempts_wait_minutes * 60)))
        ) { ?>
            <div class="Question">
                <label><strong><?php echo escape($lang["accountlockedstatus"])?></strong></label>
                <input class="medcomplementwidth" type=submit name="unlock" value="<?php echo escape($lang["accountunlock"])?>" onclick="jQuery('#unlockuser').val('true');"/>
                <input id="unlockuser" type=hidden name="unlock" value="" />
            </div>

            <div class="clearerleft"></div>
            <?php
        } ?>

        <div class="Question">
            <label for="reference"><?php echo escape($lang["property-reference"]); ?></label>
            <div class="Fixed"><?php echo escape($ref); ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["username"])?></label>
            <input id="user_edit_username" name="username" type="text" class="stdwidth" value="<?php echo form_value_display($user, "username") ?>">
            <div class="clearerleft"></div>
        </div>

        <?php if (!hook("password", "", array($user))) { ?>
            <div class="Question">
                <label for="password"><?php echo escape($lang["password"]); ?></label>
                <input
                    name="password"
                    id="password"
                    type="text"
                    class="medwidth"
                    value="<?php echo escape($lang["hidden"]); ?>"
                    autocomplete="new-password"
                >
                &nbsp;
                <input
                    class="medcomplementwidth"
                    type=submit
                    name="suggest"
                    value="<?php echo escape($lang["suggest"]); ?>"
                    onclick="jQuery.get(this.form.action + '&suggest=true', function(result) {
                        jQuery('#password').val(DOMPurify.sanitize(result));
                    });return false;">
                <div class="clearerleft"></div>
            </div>
        <?php } else { ?>
            <div>
                <input name="password" id="password" type="hidden" value="<?php echo escape($lang["hidden"]);?>" />
            </div>
        <?php } ?>

        <div class="Question">
            <label for="user_edit_fullname"><?php echo escape($lang["fullname"])?></label>
            <input name="fullname" id="user_edit_fullname" type="text" class="stdwidth" value="<?php echo form_value_display($user, "fullname") ?>">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["group"])?></label>
            <?php if (!can_set_admin_usergroup($user["usergroup"])) { ?>
                <div class="Fixed">
                    <?php echo escape($user["groupname"])?>
                    <input type="text" name="usergroup" value="<?php echo (int) $user["usergroup"];?>" style="display:none">
                </div>
            <?php } else { ?>
                <select class="stdwidth" name="usergroup">
                    <?php
                    $groups = get_usergroups(true);
                    for ($n = 0; $n < count($groups); $n++) {
                        if (can_set_admin_usergroup($groups[$n]["ref"])) {
                            ?>
                            <option
                                value="<?php echo $groups[$n]["ref"]; ?>"
                                <?php if (getval("usergroup", $user["usergroup"]) == $groups[$n]["ref"]) { ?>
                                    selected
                                <?php } ?>>
                                <?php echo escape($groups[$n]["name"]); ?>
                            </option>
                            <?php
                        }
                    }
                    ?>
                </select>
            <?php } ?>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="user_edit_email"><?php echo escape($lang["emailaddress"])?></label>
            <input 
                name="email" 
                id="user_edit_email" 
                type="text" 
                class="stdwidth <?php echo ($user["email_invalid"] ?? false) ? ' emailinvalid' : ''; ?>"
                value="<?php echo form_value_display($user, "email") ?>"
                <?php if ($user["email_invalid"] ?? false) {
                    echo "title='" . escape($lang["emailmarkedinvalid"]) . "'";
                } ?>>
            <div class="clearerleft"></div>
        </div>

        <?php
        $account_expires_datepart = form_value_display($user, "account_expires");
        // If no error, discard the time part
        if (!isset($error)) {
            $account_expires_datepart = substr($account_expires_datepart, 0, 10);
        }
        ?>

        <div class="Question">
            <label for="user_edit_expires">
                <?php echo escape($lang["accountexpiresoptional"]); ?>
                <br/>
                <?php echo escape($lang["format"]) . ": " . $lang["yyyy-mm-dd"]; ?>
            </label>
            <input name="account_expires" id="user_edit_expires" type="text" class="stdwidth" value="<?php echo $account_expires_datepart; ?>">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label>
                <?php echo escape($lang["ipaddressrestriction"]); ?>
                <br/>
                <?php echo escape($lang["wildcardpermittedeg"]); ?> 194.128.*
            </label>
            <input name="ip_restrict" type="text" class="stdwidth" value="<?php echo form_value_display($user, "ip_restrict_user") ?>">
            <div class="clearerleft"></div>
        </div>

        <?php
        if (is_numeric($user['search_filter_o_id']) && $user['search_filter_o_id'] > 0) {
            //Filter is set and migrated
            $search_filter_migrated = true;
            $search_filter_set      = true;
        } elseif ($user['search_filter_override'] != "" && ($user['search_filter_o_id'] == 0 || $user['search_filter_o_id'] == null)) {
            // Filter requires migration
            $search_filter_migrated = false;
            $search_filter_set      = true;

            // Attempt to migrate filter
            $migrateresult = migrate_filter($user['search_filter_override']);
            $notification_users = get_notification_users();
            if (is_numeric($migrateresult)) {
                message_add(array_column($notification_users, "ref"), $lang["filter_migrate_success"] . ": '" . $user['search_filter_override'] . "'", generateURL($baseurl . "/pages/team/team_user_edit.php", array("ref" => $user['ref'])));

                // Successfully migrated - now use the new filter
                ps_query("UPDATE user SET search_filter_o_id= ? WHERE ref= ?", ['i', $migrateresult, 'i', $user['ref']]);

                $search_filter_migrated = true;
                $user['search_filter_o_id'] = $migrateresult;
                debug("FILTER MIGRATION: Migrated filter - new filter id#" . $usersearchfilter);
            }
        } elseif ($user['search_filter_override'] == "" && $user['search_filter_o_id'] == 0) {
            // Filter is not set (migrated by convention)
            $search_filter_migrated = true;
            $search_filter_set      = false;
        }

        // Show filter selector if already migrated or no filter has been set
        $search_filters = get_filters("name", "ASC");
        $filters[] = array("ref" => -1, "name" => $lang["disabled"]);
        ?>

        <div class="Question">
            <label for="search_filter_o_id"><?php echo escape($lang["searchfilteroverride"]); ?></label>
            <select id="user_edit_search_filter" name="search_filter_o_id" class="stdwidth">
                <?php
                echo "<option value='0' >" . escape($lang["filter_none"]) . "</option>";
                foreach ($search_filters as $search_filter) {
                    echo "<option value='" . $search_filter['ref'] . "' " . ($user['search_filter_o_id'] == $search_filter['ref'] ? " selected " : "") . ">" . i18n_get_translated($search_filter['name']) . "</option>";
                } ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <?php hook("additionaluserfields"); ?>

        <div class="Question">
            <label for="user_edit_comments"><?php echo escape($lang["comments"])?></label>
            <textarea id="user_edit_comments" name="comments" class="stdwidth" rows=5 cols=50><?php echo form_value_display($user, "comments")?></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["created"])?></label>
            <div class="Fixed"><?php echo nicedate($user["created"], true, true, true) ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["origin"]); ?></label>
            <div class="Fixed">
                <?php echo escape($user["origin"] != "" ? (isset($lang["origin_" . $user["origin"]]) ? $lang["origin_" . $user["origin"]] : $user["origin"]) : $applicationname); ?>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["lastactive"])?></label>
            <div class="Fixed"><?php echo nicedate($user["last_active"], true, true, true) ?></div>
            <div class="clearerleft"></div>
        </div>


        <div class="Question"><label><?php echo escape($lang["lastbrowser"])?></label>
            <div class="Fixed"><?php echo resolve_user_agent($user["last_browser"])?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["profile_image"])?></label>
            <?php
            $profile_image = get_profile_image($ref);

            if ($profile_image != "") { ?>
                <div class="Fixed">
                    <img src="<?php echo escape($profile_image); ?>" alt="<?php echo escape($lang['current_profile']); ?>">
                </div>
            <?php } else { ?>
                <div class="Fixed"><?php echo escape($lang["no_profile_image"]) ?></div>
            <?php } ?>
            <div class="clearerleft"></div>
        </div>

        <?php if (
            checkperm_login_as_user($user['ref'])
            && (checkperm('a') || $userref == $ref)
            && $enable_remote_apis
        ) {
            // Show the API key for this user or users that can be logged in as
            ?>
            <div class="Question"><label><?php echo escape($lang["private-api-key"]); ?></label>
                <div class="Fixed">
                    <button type="button" class="show-hidden-btn"><?php echo escape($lang["show_hidden_property"]); ?></button>
                    <span style="display:none;"><?php echo get_api_key($user["ref"]); ?></span>
                </div>
                <div class="clearerleft"></div>
            </div>
        <?php }

        if (!hook('ticktoemailpassword')) { ?>
            <div class="Question">
                <label><?php echo escape($lang["ticktoemaillink"])?></label>
                <input
                    name="emailresetlink"
                    type="checkbox"
                    value="yes"
                    <?php if ($user["approved"] == 0 || getval("emailresetlink", "") != "") { ?>
                        checked
                    <?php } ?>
                >
                <div class="clearerleft"></div>
            </div>
            <?php
        }
        ?>

        <div class="Question">
            <label><?php echo escape($lang["status"])?></label>
            <select name="approved" >
                <?php
                for ($n = 0; $n <= 2; $n++) {
                    echo "<option value=" . $n . " " . ($user["approved"] == $n ? " selected" : "") . " >" . $approval_state_text[$n] . "</option>";
                }
                ?>
            </select>
            <?php if ($user["approved"] != 1) { ?>
                <div class="FormError">!! <?php echo escape($lang["ticktoapproveuser"])?> !!</div>
                <?php
            } ?>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang['ticktodelete']); ?></label>
            <input type="checkbox" name="deleteme" value="yes" onclick="return confirm_delete_user(this);">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["team_user_contributions"])?></label>
            <div class="Fixed">
                <a href="<?php echo $baseurl_short?>pages/search.php?search=!contributions<?php echo (int)$ref?>">
                    <?php echo LINK_CARET . escape($lang["team_user_view_contributions"]) ?>
                </a>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["log"])?></label>
            <div class="Fixed">
                <a
                    href="<?php echo $baseurl_short ?>pages/admin/admin_system_log.php?actasuser=<?php echo (int)$ref ?>&backurl=<?php echo urlencode($pageurl) ?>" 
                    onClick="return CentralSpaceLoad(this,true);">
                    <?php echo LINK_CARET . escape($lang["clicktoviewlog"])?>
                </a>
            </div>
            <div class="clearerleft"></div>
        </div>

        <?php
        if ($userref != $ref) {
            // Add message link
            ?>
            <div class="Question">
                <label><?php echo escape($lang["new_message"])?></label>
                <div class="Fixed">
                    <a href="<?php echo $baseurl_short ?>pages/user/user_message.php?msgto=<?php echo (int)$ref ?>&backurl=<?php echo urlencode($pageurl) ?>" onClick="return CentralSpaceLoad(this,true);">
                        <?php echo LINK_CARET ?><?php echo escape($lang["message"])?>
                    </a>
                </div>
                <div class="clearerleft"></div>
            </div>
            <?php
        }

        if (
            $user["approved"] == 1
            && (checkperm_login_as_user($user["ref"]))
        ) {
            if (
                trim((string) $user["origin"]) != "" ||
                    (($user['account_expires'] == "" ||
                    strtotime((string)$user['account_expires']) > time()) &&
                    ($password_expiry == 0 ||
                    ($password_expiry > 0 &&
                    strtotime((string)$user['password_last_change']) != "" &&
                    (time() - strtotime((string)$user['password_last_change'])) < $password_expiry * 60 * 60 * 24))
                    )
            ) {
                ?>
                <div class="Question">
                    <label><?php echo escape($lang["login"])?></label>
                    <div class="Fixed">
                        <a href="<?php echo $baseurl_short?>pages/team/team_user_edit.php?ref=<?php echo $ref?>&loginas=true">
                            <?php echo LINK_CARET . escape($lang["clicktologinasthisuser"])?>
                        </a>
                    </div>
                    <div class="clearerleft"></div>
                </div>
                <?php
            } else {
                ?>
                <div class="Question">
                    <label><?php echo escape($lang["login"])?></label>
                    <div class="Fixed"><?php echo escape($lang["accountorpasswordexpired"])?></div>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }
        }
        ?>

        <div class="QuestionSubmit">            
            <input name="save" type="submit" id="user_edit_save" value="<?php echo escape($lang["save"])?>" />
        </div>
    </form>
</div>

<script>
    function confirm_delete_user(el) {
        if (jQuery(el).is(':checked') === false) {
            return true;
        }

        return confirm('<?php echo escape($lang['team_user__confirm-deletion']); ?>');
    }
</script>

<?php
include "../../include/footer.php";