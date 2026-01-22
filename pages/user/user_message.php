<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

if (isset($anonymous_login) && $anonymous_login == $username) {
    die($lang["error-permissions-login"]);
}

$msgto  = getval("msgto", 0, true);
$msglimit   = getval("showcount", 3, true);
$text       = getval("messagetext", "");
if ($msgto != 0) {
    $messages   = message_get_conversation($userref, array($msgto), array("limit" => $msglimit,"sort_desc" => true));
    $to_user = get_user($msgto);
    if (!$to_user) {
        error_alert($lang["error_invalid_user"], true, 200);
        exit();
    }
    $to_username = isset($to_user["fullname"]) ? $to_user["fullname"] : $to_user["username"];
    $to_username = escape($to_username);
} else {
    $messages   = array();
    $to_username = "";
}


include "../../include/header.php";

$userimage      = get_profile_image($userref);
$usertoimage    = get_profile_image($msgto)
// The 'msgto' variable below is  used by message polling to detect and intercept messages from this user to show on this page
?>
<script>
msgto = <?php echo $msgto; ?>;

defaultimghtml =  jQuery("<i />", {
    title           : '<?php echo escape($userfullname); ?>',
    alt             : '<?php echo escape($userfullname); ?>',
    'class'         : 'icon-user lucide-lg ProfileImage',
    'aria-hidden'   : true
    });

<?php
if ($userimage != "") {
    ?>
    userimghtml =  jQuery("<img />", {
        title   : '<?php echo escape($userfullname); ?>',
        alt     : '<?php echo escape($userfullname); ?>',
        'class' : 'ProfileImage',
        src     : '<?php echo escape($userimage); ?>'
        });
    <?php
} else { ?>    
    userimghtml = defaultimghtml;
    <?php
}
if ($usertoimage != "") {
    ?>
    usertoimghtml =  jQuery("<img />", {
        title   : '<?php echo escape($userfullname); ?>',
        alt     : '<?php echo escape($userfullname); ?>',
        'class' : 'ProfileImage',
        src     : '<?php echo escape($usertoimage); ?>'
        });
    <?php
} else { ?>    
    usertoimghtml = defaultimghtml;
    <?php
}
?>

function sendMessage()
    {
    messagetext = jQuery('#messagetext').val();
    if(messagetext.trim() == '')
        {
        return false;
        }
    users = document.getElementById('message_users').value.replace(/"/g, "").split(",");
    postdata = {
        'users': users,
        'text': messagetext,
        }

    if(users.length == 1 && users[0].indexOf('<?php echo escape($lang["group"]); ?>: ') == -1 && msgto == 0)
        {
        // Get details of selected recipient to reload and show conversation
        touser = api("get_users",{'find':  users[0],'exact_username_match': true},function(response)
            {
            if(response.length == 1)
                {
                msgto = parseInt(response[0]['ref']);
                api(
                    "send_user_message",
                    postdata,
                    function(response) {
                        if (typeof response == 'string')
                            {
                            styledalert('<?php echo escape($lang['error']); ?>', response);
                            }
                        else
                            {
                            reloadMessages(response);
                            }
                        },
                    <?php echo generate_csrf_js_object('send_user_message'); ?>
                );
                return true;
                }
            },
            <?php echo generate_csrf_js_object('get_users'); ?>
            );
        }
    else
        {
        api(
            "send_user_message",
            postdata,
            function(response) {
                if (typeof response == 'string')
                    {
                    styledalert('<?php echo escape($lang['error']); ?>', response);
                    }
                else
                    {
                    showUserMessage(messagetext, true);
                    }
                },
            <?php echo generate_csrf_js_object('send_user_message'); ?>
        );
        }

    // Speed up message checking whilst on this page
    message_timer = window.setTimeout(message_poll,5);
    }

function showUserMessage(message,fromself)
    {
    // show new message in line
    msgtemp = document.getElementById("user_message_template").innerHTML;
    if(fromself)
        {
        classtxt = "user_message own_message";
        msgtemp = msgtemp.replace("%%PROFILEIMAGE%%", userimghtml[0].outerHTML);
        jQuery('#messagetext').val('');
        }
    else
        {
        classtxt = "user_message";
        msgtemp = msgtemp.replace("%%PROFILEIMAGE%%", usertoimghtml[0].outerHTML);
        }

    msgtemp = msgtemp.replace("%%CLASSES%%", classtxt);

    let msg = new DOMParser().parseFromString(msgtemp, 'text/html');
    msg.querySelector('div.user_message_text').textContent = message;
    
    jQuery('#message_conversation').append(msg.querySelector('div'));
    jQuery('#message_conversation').scrollTop(jQuery('#message_conversation').prop('scrollHeight'));
    }

function reloadMessages()
    {
    // Reload page with selected user set
    setTimeout(function(){CentralSpaceLoad(window.location + '?msgto=' + msgto);}, 1000);
    }
</script>
<?php

$links_trail = array();
if (isset($_SERVER["HTTP_REFERER"]) && strpos($_SERVER["HTTP_REFERER"], "team_user") !== false) {
    $links_trail[] = array(
        'title' => $lang["teamcentre"],
        'href'  => $baseurl_short . "pages/team/team_home.php",
        'menu' =>  true
    );
    $links_trail[] = array(
        'title' => $lang["manageusers"],
        'href'  => $baseurl_short . "pages/team/team_user.php",
    );
    if ($msgto != 0) {
        $links_trail_user = array("title" => $to_username,"href" => $baseurl_short . "pages/team/team_user_edit.php?ref=" . escape($to_user["ref"]));
        $links_trail[] = $links_trail_user;
    }
} else {
    $links_trail[] = array(
        'title' => $lang["mymessages"],
        'href'  => $baseurl_short . "pages/user/user_messages.php",
    );
    if ($msgto != 0) {
        $links_trail_user = array(
            "title" => $to_username,
            "href"  => $baseurl_short . "pages/user/user_profile.php?username=" . escape($to_user["username"]),
            "help"  => "user/messaging",
        );
        $links_trail[] = $links_trail_user;
    } else {
        $links_trail[] = array(
            'title' => $lang["new_message"],
            'help'  => "user/messaging",
        );
    }
}

?>
<div class="BasicsBox">
    <?php renderBreadcrumbs($links_trail); ?>
    <form id="user_message_form" method="post" class = "FormWide" action="<?php echo $baseurl_short?>pages/user/user_message.php">
        <?php
        generateFormToken("myform");

        if (isset($error)) {
            ?>
            <div class="FormError"><?php echo $error?></div>
            <?php
        }

        if ($msgto == 0) {
            echo "<div class='Question'><label>" . escape($lang["message_recipients"]) . "</label>";
            $user_select_internal = true;
            $user_select_class = "medwidth";
            $autocomplete_user_scope = "message_";
            $no_smart_groups = true;
            include "../../include/user_select.php";
            echo "<div class='clearerleft'> </div></div>";
        } else {
            echo "<input type='hidden' id='message_users' name='message_users' value='" . $msgto . "'/>";
        }

        // Show conversation
        echo "<div id='message_conversation' class='message_conversation'>";
        // Render in reverse order
        for ($n = count($messages) - 1; $n >= 0; $n--) {
            render_message($messages[$n]);
            if ($messages[$n]['user'] == $userref) {
                message_seen($messages[$n]['ref']);
            }
        }
        // Add template for new messages
        echo "\n<template id='user_message_template'>";
        render_message();
        echo "</template>";
        echo "<div class='clearer'> </div>";
        echo "</div>";
        ?>
        <div class="Question">
            <label for="messagetext"><?php echo escape($lang["message"]); ?></label>
            <textarea id="messagetext" name="messagetext" class="stdwidth Inline required" rows=5 cols=50></textarea>
            <div class="clearerleft"></div>
        </div>
        <div class="QuestionSubmit">        
            <input name="send" type="submit" value="<?php echo escape($lang["send"]); ?>" onclick="sendMessage();return false;"/>
        </div>
    </form>
</div>

<?php
include "../../include/footer.php";
