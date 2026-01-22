<?php
include "../include/boot.php";

$k = getval("k", "");
$ref = getval("ref", "", true);
$internal_share_access = false;

if ($k == "" || !check_access_key($ref, $k)) {
    include_once "../include/authenticate.php";
    $internal_share_access = true;
}

if (!checkperm('q')) {
    exit($lang["error-permissiondenied"]);
}

if ($k != "") {
    if ((!isset($internal_share_access) || !$internal_share_access) && $prevent_external_requests) {
        echo "<script>window.location = '" .  $baseurl . "/login.php?error="  . (($allow_account_request) ? "signin_required_request_account" : "signin_required") . "'</script>";
        exit();
    }

    if (internal_share_access()) {
        $internal_share_access = true;
    }
}

include "../include/request_functions.php";

$error = '';

if ($k == "" && isset($anonymous_login) && $username == $anonymous_login) {
    $user_is_anon = true;
} else {
    $user_is_anon = false;
}

$use_antispam = (!$internal_share_access || $user_is_anon);

# Allow alternative configuration settings for this resource type.
$resource            = get_resource_data($ref);
$resource_field_data = get_resource_field_data($ref);

if (!is_array($resource)) {
    if (getval("ajax", "") != "") {
        error_alert($lang['resourcenotfound'], false);
    } else {
        include "../include/header.php";
        $onload_message = array("title" => $lang["error"],"text" => $lang['resourcenotfound']);
        include "../include/footer.php";
    }
    exit();
}

if (!is_array($resource_field_data)) {
    if (getval("ajax", "") != "") {
        error_alert($lang['error_no_metadata'], false);
    } else {
        include "../include/header.php";
        $onload_message = array("title" => $lang["error"],"text" => $lang['error_no_metadata']);
        include "../include/footer.php";
    }
    exit();
}

resource_type_config_override($resource["resource_type"]);

$resource_title = '';

if (isset($user_dl_limit) && intval($user_dl_limit) > 0) {
    $download_limit_check = get_user_downloads($userref, $user_dl_days);
    if ($download_limit_check >= $user_dl_limit) {
        $userrequestmode = 0;
    }
}

// Get any metadata fields we may want to show to the user on this page
// Currently only title is showing
foreach ($resource_field_data as $resource_field) {
    if ($view_title_field != $resource_field['ref']) {
        continue;
    }

    $resource_title = $resource_field['value'];
}

if (getval("save", "") != "" && enforcePostRequest(false)) {
    debug('Starting the (submit) process for resource request.');
    $antispamcode = getval('antispamcode', '');
    $antispam = getval('antispam', '');
    $antispamtime = getval('antispamtime', 0);

    // Check the anti-spam time is recent
    if ($use_antispam && ($antispamtime < (time() - 180) ||  $antispamtime > time())) {
        $result = false;
        $error = $lang["expiredantispam"];
    } elseif ($use_antispam && !verify_antispam($antispamcode, $antispam, $antispamtime)) {
        // Check the anti-spam code is correct
        debug('[WARN] Incorrect anti-spam code');
        $result = false;
        $error = $lang["requiredantispam"];
    } elseif (!$internal_share_access || $user_is_anon || $userrequestmode == 0) {
        debug('Received a non-managed resource request (mode).');
        if ((!$internal_share_access || $user_is_anon) && (getval("fullname", "") == "" || getval("email", "") == "")) {
            $result = false; # Required fields not completed.
        } else {
            $tmp = hook("emailresourcerequest");
            if ($tmp) {
                $result = $tmp;
            } else {
                $result = email_resource_request($ref, getval("request", ""));
            }
        }
    } else {
        # Request mode 1 : "Managed" mode via Manage Requests / Orders
        debug('Received a managed resource request (mode).');
        $tmp = hook("manresourcerequest");
        if ($tmp) {
            $result = $tmp;
        } else {
            $result = managed_collection_request($ref, getval("request", ""), true);
        }
    }

    if ($result === false) {
        $error = ($error ?: $lang["requiredfields-general"]);
    } else {
        $return_url = generateURL($baseurl_short . "pages/view.php", ["ref" => (int)($ref),"k" => $k]);
        $doneurl = generateURL(
            $baseurl_short . "pages/done.php",
            ["text" => "resource_request","resource" => $ref,"k" => $k,"return_url" => $return_url]
        );
        ?>
        <script>
            CentralSpaceLoad("<?php echo $doneurl ?>",true);
        </script>
        <?php
    }
}

include "../include/header.php";

$back_url = generateURL(
    $baseurl_short . "pages/view.php",
    ["ref" => $ref,"k" => $k]
);
?>

<div class="BasicsBox">
    <p>
        <a href="<?php echo $back_url?>" onClick="return CentralSpaceLoad(this, true);">
            <?php echo LINK_CARET_BACK . escape($lang['backtoresourceview']); ?>
        </a>
    </p>

    <h1><?php echo escape(i18n_get_translated($lang["requestresource"])); ?></h1>

    <p>
        <?php
        echo escape(text("introtext"));
        render_help_link("resourceadmin/user-resource-requests");
        ?>
    </p>

    <?php render_top_page_error_style($error); ?>

    <form method="post" action="<?php echo $baseurl_short?>pages/resource_request.php" onsubmit="return CentralSpacePost(this,true);">
        <?php generateFormToken("resource_request"); ?>
        <input type="hidden" name="k" value="<?php echo escape($k); ?>">
        <input type="hidden" name="ref" value="<?php echo escape($ref)?>">
        
        <div class="Question">
            <label><?php echo escape($lang["resourceid"]); ?></label>
            <div class="Fixed"><?php echo escape($ref); ?></div>
            <div class="clearerleft"></div>
        </div>
        
        <div class="Question">
            <label><?php echo escape($lang['resourcetitle']); ?></label>
            <div class="Fixed"><?php echo escape(i18n_get_translated($resource_title)); ?></div>
            <div class="clearerleft"></div>
        </div>
        
        <?php if (!$internal_share_access || $user_is_anon) { ?>
            <div class="Question">
                <label><?php echo escape($lang["fullname"]); ?> <sup>*</sup></label>
                <input type="hidden" name="fullname_label" value="<?php echo escape($lang["fullname"]); ?>">
                <input name="fullname" type="text" class="stdwidth" value="<?php echo escape(getval("fullname", "")); ?>">
                <div class="clearerleft"></div>
            </div>
            
            <div class="Question">
                <label><?php echo escape($lang["emailaddress"]); ?> <sup>*</sup></label>
                <input type="hidden" name="email_label" value="<?php echo escape($lang["emailaddress"]); ?>">
                <input name="email" type="email" class="stdwidth" value="<?php echo escape(getval("email", "")); ?>">
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label><?php echo escape($lang["contacttelephone"]); ?></label>
                <input type="hidden" name="contact_label" value="<?php echo escape($lang["contacttelephone"]); ?>">
                <input name="contact" type="text" class="stdwidth" value="<?php echo escape(getval("contact", "")) ?>">
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <div class="Question">
            <label for="request">
                <?php
                echo escape($lang["requestreason"]);
                if ($resource_request_reason_required) {
                    ?>
                    <sup>*</sup>
                    <?php
                } ?>
            </label>
            <textarea class="stdwidth" name="request" id="request" rows=5 cols=50><?php echo escape(getval("request", "")) ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <?php # Add custom fields
        if (isset($custom_request_fields)) {
            $custom = explode(",", $custom_request_fields);
            $required = explode(",", $custom_request_required);

            for ($n = 0; $n < count($custom); $n++) {
                $type = 1;

                # Support different question types for the custom fields.
                if (isset($custom_request_types[$custom[$n]])) {
                    $type = $custom_request_types[$custom[$n]];
                }

                if ($type == 4) {
                    # HTML type - just output the HTML.
                    echo $custom_request_html[$custom[$n]];
                } else {
                    ?>
                    <div class="Question">
                        <label for="custom<?php echo $n?>">
                            <?php
                            echo escape(i18n_get_translated($custom[$n]));
                            if (in_array($custom[$n], $required)) {
                                ?>
                                <sup>*</sup>
                                <?php
                            } ?>
                        </label>
                    
                        <?php if ($type == 1) {  # Normal text box ?>
                            <input type=text name="custom<?php echo $n?>" id="custom<?php echo $n; ?>" class="stdwidth" value="<?php echo escape(getval("custom" . $n, "")); ?>">
                        <?php } ?>

                        <?php if ($type == 2) { # Large text box ?>
                            <textarea name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>" class="stdwidth" rows="5"><?php echo escape(getval("custom" . $n, "")); ?></textarea>
                        <?php } ?>

                        <?php if ($type == 3) { # Drop down box ?>
                            <select name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>" class="stdwidth">
                                <?php
                                $index = $custom[$n];
                                if (isset($custom_request_options[$index])) {
                                    foreach ($custom_request_options[$index] as $option) {
                                        $val = i18n_get_translated($option);
                                        ?>
                                        <option <?php echo (getval("custom" . $n, "") == $val) ? " selected" : ''; ?>>
                                            <?php echo escape($val); ?>
                                        </option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                        <?php } ?>
                    
                        <div class="clearerleft"></div>
                    </div>
                    <?php
                }
            }
        }

        if ($use_antispam) {
            render_antispam_question();
        }
        ?>

        <div class="QuestionSubmit">        
            <input name="save" value="true" type="hidden" />
            <input name="cancel" type="button" value="<?php echo escape($lang["cancel"]); ?>" onclick="document.location='view.php?ref=<?php echo escape($ref)?>';"/>&nbsp;
            <input name="save" type="submit" value="<?php echo escape(i18n_get_translated($lang["requestresource"])); ?>" />
        </div>
    </form>
</div>

<?php
include "../include/footer.php";