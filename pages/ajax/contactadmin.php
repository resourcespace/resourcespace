<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

# Is this an ajax call from the view page?
$insert = getval("insert", "");
$ref = getval("ref", "", true);

# Load access level
$access = get_resource_access($ref);
# check permissions (error message is not pretty but they shouldn't ever arrive at this page unless entering a URL manually)
if ($access == 2) {
        exit("This is a confidential resource.");
}

# Fetch resource data
$resource = get_resource_data($ref);
if ($resource === false) {
    exit($lang['resourcenotfound']);
}

$imagename = i18n_get_translated($resource["field" . $view_title_field]);

if (getval("send", "") != "" && enforcePostRequest(false)) {
    # If an anonymous user is trying to send a message
    # validate that the anti-spam code has been filled in
    if (isset($anonymous_login) && $anonymous_login == $username) {
        $errors = false;
        $spamcode = getval("antispamcode", "");
        $usercode = getval("antispam", "");
        $spamtime = getval("antispamtime", 0);

        if ($spamtime < (time() - 180) || $spamtime > time()) {
            $errors = true;
            $antispam_error = $lang["expiredantispam"];
        } elseif (!hook('replaceantispam_check') && !verify_antispam($spamcode, $usercode, $spamtime)) {
            $errors = true;
            $antispam_error = $lang["requiredantispam"];
        }

        if ($errors) {
            exit(escape($antispam_error));
        }
    }

    $messagetext = getval("messagetext", "");
    $templatevars['url'] = $baseurl . "/?r=" . $ref;
    $templatevars['fromusername'] = ($userfullname == "" ? $username : $userfullname);
    $templatevars['resourcename'] = $imagename;
    $templatevars['emailfrom'] = $useremail;
    $subject = $templatevars['fromusername'] . $lang["contactadminemailtext"];
    $templatevars['message'] = $messagetext;
    $message = $templatevars['fromusername'] . ($useremail != "" ? " (" . $useremail . ")" : "") . $lang["contactadminemailtext"] . "\n\n" . $messagetext . "\n\n" . $lang["clicktoviewresource"] . "\n\n" . $templatevars['url'];
    $notification_message = $templatevars['fromusername'] . ($useremail != "" ? " (" . $useremail . ")" : "") . $lang["contactadminemailtext"] . "\n\n" . $messagetext . "\n\n" . $lang["clicktoviewresource"];

    global $watermark;
    $templatevars['thumbnail'] = get_resource_path($ref, true, "thm", false, "jpg", $scramble = -1, $page = 1, ($watermark) ? (($access == 1) ? true : false) : false);
    if (!file_exists($templatevars['thumbnail'])) {
            $templatevars['thumbnail'] = "../gfx/no_preview/default.png";
    }

    # Build message and send.
    $admin_notify_emails = array();
    $admin_notify_users = array();
    $notify_users = get_notification_users("RESOURCE_ADMIN");
    foreach ($notify_users as $notify_user) {
        get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'user_pref_resource_notifications', $send_message);
        if (!$send_message) {
            continue;
        }
        get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'email_user_notifications', $send_email);
        if ($send_email && $notify_user["email"] != "") {
            $admin_notify_emails[] = $notify_user['email'];
        } else {
            $admin_notify_users[] = $notify_user["ref"];
        }
    }
    foreach ($admin_notify_emails as $admin_notify_email) {
        send_mail($admin_notify_email, $subject, unescape($message), $applicationname, $email_from, "emailcontactadmin", $templatevars, $applicationname);
    }

    if (count($admin_notify_users) > 0) {
        message_add($admin_notify_users, $notification_message, $templatevars['url']);
    }

    exit("SUCCESS");
}

if ($insert == "") {
    # Fetch search details (for next/back browsing and forwarding of search params)
    $search = getval("search", "");
    $order_by = getval("order_by", "relevance");
    $offset = getval("offset", 0, true);
    $default_sort_direction = "DESC";

    if (substr($order_by, 0, 5) == "field") {
        $default_sort_direction = "ASC";
    }

    $sort = getval("sort", $default_sort_direction);
    $archive = getval("archive", 0, true);

    include "../../include/header.php";
    ?>
    <p>
        <a href="<?php echo $baseurl ?>/pages/view.php?ref=<?php echo urlencode($ref) ?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset) ?>&order_by=<?php echo urlencode($order_by) ?>&sort=<?php echo urlencode($sort) ?>&archive=<?php echo urlencode($archive) ?>" onClick="return CentralSpaceLoad(this,true);">
            <?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?>
        </a>
    </p>
    <h1><?php echo escape($lang["contactadmin"]); ?></h1>             
    <div>       
        <?php
        if ((int) $resource["has_image"] != RESOURCE_PREVIEWS_NONE) { ?>
            <img
                align="top"
                src="<?php echo get_resource_path($ref, false, ($edit_large_preview ? "pre" : "thm"), false, $resource["preview_extension"], -1, 1, checkperm("w"))?>"
                alt="<?php echo $imagename ?>" class="Picture"/>
            <br />
            <?php
        } else {
            # Show the no-preview icon
            ?>
            <img src="<?php echo $baseurl_short ?>gfx/no_preview/default.png" alt="<?php echo $imagename ?>" class="Picture"/>
            <?php
        } ?>
    </div>  
    <?php
} ?>

<script>
function sendResourceMessage() {
    if (!jQuery('#messagetext').val() || (jQuery('#antispam').length && !jQuery('#antispam').val())) {
        alert('<?php echo escape($lang["requiredfields-general"]); ?>');
        return false;
    }

    jQuery.ajax({
        type: "POST",
        data: jQuery('#contactadminform').serialize(),
        url: baseurl_short+"pages/ajax/contactadmin.php?ref="+<?php echo $ref ?>+"&insert=true&send=true",
        success: function(html) {                        
                //jQuery('#RecordDownload li:last-child').after(html);
                if (html=="SUCCESS") {
                    alert('<?php echo escape($lang["emailsent"]); ?>');
                    jQuery('#contactadminboxcontainer').remove();
                } else {
                    alert('<?php echo escape($lang["error"]); ?>: ' + html);
                }
            },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            alert('<?php echo escape($lang["error"]); ?>\n' + textStatus);
        }
    });
}
</script>

<div class="clearerleft"></div>
<div id="contactadminbox" style="display: none">
    <p><?php echo escape($lang["contactadmin"]); ?></p>
    <form name="contactadminform" method=post id="contactadminform" action="<?php echo $baseurl_short?>pages/ajax/contactadmin.php?ref=<?php echo $ref ?>">
        <?php generateFormToken("contactadminform"); ?>
        <input type=hidden name=ref value="<?php echo urlencode($ref) ?>">

        <div>
            <p><?php echo escape($lang["contactadminintro"]); ?><sup>*</sup></p>
            <textarea rows=6 name="messagetext" id="messagetext"></textarea>
            <div class="clearerleft"></div>

            <div id="contactadminbuttons">
                <?php if (isset($anonymous_login) && $anonymous_login == $username && !hook("replaceantispam")) {
                    if (isset($antispam_error)) {
                        error_alert($antispam_error, false);
                    }
                    render_antispam_question();
                } ?>
                <input
                    name="send"
                    type="submit"
                    class="contactadminbutton"
                    value="&nbsp;&nbsp;<?php echo escape($lang["send"]); ?>&nbsp;&nbsp;"
                    onClick="sendResourceMessage();return false;"
                />
                <input
                    name="cancel"
                    type="submit"
                    class="contactadminbutton"
                    value="&nbsp;&nbsp;<?php echo escape($lang["cancel"]); ?>&nbsp;&nbsp;"
                    onClick="jQuery('#contactadminbox').slideUp();return false;"
                />
            </div>
        </div>
    </form>
</div>

<?php
if ($insert == "") {
    include "../../include/footer.php";
}
