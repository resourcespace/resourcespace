<?php

function HookFeedbackAllToptoolbaradder()
{
    global $target,$baseurl,$lang;
    ?>
    <li>
        <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl?>/plugins/feedback/pages/feedback.php">
            <?php echo escape($lang["feedback_user_survey"]); ?>
        </a>
    </li>
    <?php
}

function HookFeedbackAllHeadertop()
{
    global $baseurl,$pagename,$lang,$k,$feedback_prompt_text;

    $expages = array(
        "setup",
        "feedback",
        "login",
        "user_request",
        "user_password",
        );

    if (in_array($pagename, $expages) || $k != "") {
        return true;
    }

    $feedback_config = get_plugin_config('feedback');
    if (is_array($feedback_config)) {
        $feedback_prompt_text = $feedback_config['prompt_text'];
    }

    if (getval("feedback_completed", "") == "") {
        ?>
        <div id="feedback_prompt" style="border:1px solid #BBB;border-bottom-width:3px;border-bottom-color:#bbb;background-color:white;width:300px;height:auto;position:absolute;top:100px;left:300px;text-align:left;padding:10px;color:black;z-index:99999;">
            <?php echo $feedback_prompt_text; ?>
            <div style="text-align:right;">
                <input type="button" value="<?php echo escape($lang["yes"]); ?>" onclick="SetCookie('feedback_completed','yes',30);jQuery('#feedback_prompt').remove();CentralSpaceLoad('<?php echo $baseurl?>/plugins/feedback/pages/feedback.php',true);">
                <input type="button" value="<?php echo escape($lang["no"]); ?>" onclick="SetCookie('feedback_completed','yes',30);document.getElementById('feedback_prompt').style.display='none';">
                <input type="button" value="<?php echo escape($lang["feedback_remind_me_later"]); ?>" onclick="SetCookie('feedback_completed','yes',0.5);document.getElementById('feedback_prompt').style.display='none';">
            </div>
        </div>
        <?php
    }
}

