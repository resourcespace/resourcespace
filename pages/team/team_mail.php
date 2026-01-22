<?php

/**
 * Create bulk mail page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("m") || is_anonymous_user()) {
    exit("Permission denied.");
}

$message_type = intval(getval("message_type", MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL));

if (getval("send", "") != "" && enforcePostRequest(false)) {
    $result = bulk_mail(getval("users", ""), getval("subject", ""), getval("text", ""), getval("html", "") == "yes", $message_type, getval("url", ""));
    if ($result == "") {
        switch ($message_type) {
            case MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL | MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN:
                $error = $lang["emailandmessagesent"];
                break;
            case MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL:
                $error = $lang["emailsent"];
                break;
            case MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN:
                $error = $lang["message_sent"];
                break;
        }
        log_activity($error, LOG_CODE_SYSTEM);
    } else {
        $error = "!! " . $result . " !!";
    }
}

$headerinsert .= "
<script src=\"$baseurl/lib/js/jquery.validate.min.js\" type=\"text/javascript\"></script><script type=\"text/javascript\">
jQuery(document).ready(function(){
	jQuery('#myform').validate({ 
		errorPlacement: function(error, element) {
		element.after('<span class=\"FormError\">'+error.html()+'</span>');
		},
   wrapper: 'div'});
});
</script>";

include "../../include/header.php";
switch ($message_type) {
    case MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL | MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN:
        $title = $lang["sendbulkmailandmessage"];
        break;
    case MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL:
        $title = $lang["sendbulkmail"];
        break;
    case MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN:
        $title = $lang["sendbulkmessage"];
        break;
}
?>

<div class="BasicsBox">
    <?php
    $links_trail = array(
        array(
            'title' => $lang["teamcentre"],
            'href'  => $baseurl_short . "pages/team/team_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $title,
            'help'  => "resourceadmin/user-communication"
        )
    );

    renderBreadcrumbs($links_trail);
    ?>

    <form id="myform" method="post" action="<?php echo $baseurl_short?>pages/team/team_mail.php">
        <?php
        generateFormToken("myform");

        if (isset($error)) {
            ?>
            <div class="FormError"><?php echo $error?></div>
            <?php
        } ?>

        <div class="Question">
            <label><?php echo escape($lang["emailrecipients"]); ?></label>
            <?php include "../../include/user_select.php"; ?>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["type"]); ?></label>
            <input
                type="radio"
                id="message_type_<?php echo MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL; ?>"
                name="message_type"
                value="<?php echo MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL; ?>"
                onclick="
                    jQuery('h1').closest('h1').html('<?php echo escape($lang["sendbulkmail"]); ?>');
                    jQuery('#message_email').slideDown();
                    jQuery('#message_screen').slideUp();"
                <?php if ($message_type == MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL) { ?>
                    checked='checked'
                <?php } ?>
            ><?php echo escape($lang['email']); ?>

            <input
                type="radio"
                id="message_type_<?php echo MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN; ?>"
                name="message_type"
                value="<?php echo MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN; ?>"
                onclick="
                    jQuery('h1').closest('h1').html('<?php echo escape($lang["sendbulkmessage"]); ?>');
                    jQuery('#message_email').slideUp(); jQuery('#message_screen').slideDown();"
                <?php if ($message_type == MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN) { ?>
                    checked='checked'
                <?php } ?>
            ><?php echo escape($lang['screen']); ?>
            
            <input
                type="radio"
                id="message_type_<?php echo MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN | MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL; ?>"
                name="message_type"
                value="<?php echo MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN | MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL; ?>"
                onclick="
                    jQuery('h1').closest('h1').html('<?php echo escape($lang["sendbulkmailandmessage"]); ?>');
                    jQuery('#message_email').slideDown(); jQuery('#message_screen').slideDown();"
                <?php if ($message_type == (MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN | MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL)) { ?>
                    checked='checked'
                <?php } ?>
            ><?php echo escape($lang['email_and_screen']); ?>

            <div class="clearerleft"></div>
        </div>

        <div
            id="message_screen"
            style="<?php
                if (
                    $message_type != MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN
                    && $message_type != (MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL | MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN)
                ) { ?>
                    display:none;
                <?php } ?>"
        >
            <div class="Question">
                <label><?php echo escape($lang["message_url"]); ?></label>
                <input name="url" type="text" class="stdwidth Inline required" value="<?php echo escape(getval("url", "")); ?>">
                <div class="clearerleft"></div>
            </div>
        </div>

        <div
            id="message_email"
            style="<?php
                if (
                    $message_type !== MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL
                    && $message_type != (MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL | MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN)
                ) { ?>
                    display:none;
                <?php } ?>"
        >
            <div class="Question">
                <label><?php echo escape($lang["emailhtml"]); ?></label>
                <input name="html" type="checkbox" value="yes" <?php echo (getval("html", "") == "yes") ? " checked" : ''; ?>>
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label><?php echo escape($lang["emailsubject"]); ?></label>
                <input name="subject" type="text" class="stdwidth Inline required" value="<?php echo escape(getval("subject", $applicationname))?>">
                <div class="clearerleft"></div>
            </div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["text"]); ?></label>
            <textarea name="text" class="stdwidth Inline required" rows=25 cols=50><?php echo escape(getval("text", ""))?></textarea>
            <div class="clearerleft"></div>
        </div>

        <?php hook("additionalemailfield");?>

        <div class="QuestionSubmit">        
            <input name="send" type="submit" value="<?php echo escape($lang["send"]); ?>"/>
        </div>
    </form>
</div>

<?php
include "../../include/footer.php";
?>
