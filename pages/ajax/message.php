<?php

DEFINE("MESSAGE_POLLING_ABSENT_USER_TIMEOUT_SECONDS", 30);
DEFINE("MESSAGE_FADEOUT_SECONDS", 5);

// check for callback, i.e. this file being called directly to get any new messages
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    include_once __DIR__ . "/../../include/boot.php";
    include __DIR__ . "/../../include/authenticate.php";
    
    if ($actions_on) {
        include_once __DIR__ . "/../../include/action_functions.php";
        include_once __DIR__ . "/../../include/request_functions.php";
    }

    if (getval("check_upgrade_in_progress", "") != "") {
        $data["upgrade_in_progress"] = false;
        if (is_process_lock("process_lock_upgrade_in_progress")) {
            $data["upgrade_in_progress"] = true;
        }

        header('Content-Type: application/json');
        echo json_encode(array(
            "status" => "success",
            "data" => $data));
        exit();
    }

    $user            = getval('user', 0, true);
    $seen            = getval('seen', 0, true);
    $unseen          = getval('unseen', 0, true);
    $allseen         = getval('allseen', 0, true);
    $deleteselusrmsg = getval('deleteselusrmsg', "");
    $selectedseen    = getval('selectedseen', "");
    $selectedunseen  = getval('selectedunseen', "");
    $getrefs         = getval('getrefs', 0, true);

    if (0 < $user) {
        if (is_numeric($user) && !checkperm_user_edit($user)) {
            exit($lang['error-permissiondenied']);
        }
    } else {
        // no user specified so default to the current user
        $user = $userref;
    }

    // It is an acknowledgement so set as seen and get out of here
    if (0 < $seen) {
        message_seen($seen);
        return;
    }

    if (0 < $unseen) {
        message_unseen($unseen);
        return;
    }

    // Acknowledgement all messages then get out of here
    if (0 < $allseen) {
        message_seen_all($allseen);
        return;
    }

    // Purge messages that have an expired TTL then get out of here
    if ('' != getval('purge', '')) {
        message_purge();
        return;
    }

    // Delete all selected messages
    if ($deleteselusrmsg != "") {
        message_deleteselusrmsg($deleteselusrmsg);
        return;
    }

    // Mark all selected messages as seen
    if ($selectedseen != "") {
        message_selectedseen($selectedseen);
        return;
    }

    // Mark all selected messages as unseen
    if ($selectedunseen != "") {
        message_selectedunseen($selectedunseen);
        return;
    }

    // Return list of references of all messages
    if ($getrefs > 0) {
        message_getrefs($getrefs);
        return;
    }

    // Check if there are messages
    $messages = array();
    message_get($messages, $user, true, false);   // note: messages are passed by reference - include expired but not seen in count

    $extramessage = array('ref' => 0);
    $extramessages = false;
    if ($actions_on) {
        $actioncount = get_user_actions(true);
        if ($actioncount > 0) {
            $extramessage['actioncount'] = $actioncount;
            $extramessages = true;
        }
    }

    if (checkperm("R")) {
        $requestcondition = "";
        $requestparams = array();
        if (checkperm("Rb")) {
            $requestcondition = "and assigned_to=?";
            $requestparams[] = "i";
            $requestparams[] = $userref;
        }
        $requestcount = ps_value("SELECT COUNT(*) value FROM request WHERE status = 0 $requestcondition", $requestparams, 0);
        if ($requestcount > 0) {
            $extramessage['requestcount'] = $requestcount;
            $extramessages = true;
        }
    }

    if (checkperm("r") && $research_request) {
        $researchcount = ps_value("SELECT COUNT(*) value FROM research_request WHERE status = 0", array(), 0);
        if ($researchcount > 0) {
            $extramessage['researchcount'] = $researchcount;
            $extramessages = true;
        }
    }

    if ($offline_job_queue) {
        $userfailedjobs = count(job_queue_get_jobs("", STATUS_ERROR, (checkperm('a') ? 0 : $userref)));
        $allfailedjobs  = count(job_queue_get_jobs("", STATUS_ERROR));
        $jobcounts      = [];

        if ($userfailedjobs > 0) {
            $jobcounts['user'] = $userfailedjobs;
        }
        if ($allfailedjobs > 0) {
            $jobcounts['all'] = $allfailedjobs;
        }

        if (!empty($jobcounts)) {
            $extramessage['failedjobcount'] = $jobcounts;
            $extramessages = true;
        }
    }
    if ($extramessages) {
        $messages[] = $extramessage;
    }

    ob_clean(); // just in case we have any stray whitespace at the start of this file
    header('Content-Type: application/json');
    echo json_encode($messages);
    return;
}

?><script>

    var activeSeconds=<?php echo MESSAGE_POLLING_ABSENT_USER_TIMEOUT_SECONDS; ?>;

    var message_timer = null;
    var message_refs = new Array();
    var message_poll_first_run = true;

    var counter = 0;

    function message_poll()
    {

        if (message_timer != null)
        {
            clearTimeout(message_timer);
            message_timer = null;
        }
        activeSeconds-=<?php echo $message_polling_interval_seconds; ?>;
        <?php
        if ($message_polling_interval_seconds > 0) {
            ?>if(activeSeconds < 0)
            {
                message_timer = window.setTimeout(message_poll,<?php echo $message_polling_interval_seconds; ?> * 1000);
                return;
            }
            <?php
        }
        ?>
        jQuery.ajax({
            url: '<?php echo $baseurl; ?>/pages/ajax/message.php?ajax=true',
            type: 'GET',
            success: function(messages, textStatus, xhr) {
                if(xhr.status == 200 && messages && messages.length > 0) {
                    messagecount = messages.length;
                    if(messages[messages.length - 1]['ref'] == 0) {
                        // The last message is a dummy entry with a count of actions and failed jobs
                        messagecount = messagecount-1;
                    }
                    usertotalcount = 0;
                    actioncount = 0;
                    failedjobcount = 0;
                    admintotalcount = 0;
                    
                    if (typeof(messages[messages.length - 1]['actioncount']) !== 'undefined') {
                        // There are actions as well as messages
                        actioncount = parseInt(messages[messagecount]['actioncount']);
                        usertotalcount = usertotalcount + actioncount;
                    }
                    if (typeof(messages[messages.length - 1]['requestcount']) !== 'undefined') {
                        // There are actions as well as messages
                        requestcount = parseInt(messages[messagecount]['requestcount']);
                        admintotalcount = admintotalcount + requestcount;
                    }
                    if (typeof(messages[messages.length - 1]['researchcount']) !== 'undefined') {
                        // There are actions as well as messages
                        researchcount = parseInt(messages[messagecount]['researchcount']);
                        admintotalcount = admintotalcount + researchcount;
                    }
                    if (typeof(messages[messages.length - 1]['failedjobcount']) !== 'undefined') {
                        userfailedjobcount = parseInt(messages[messagecount]['failedjobcount']['user']);
                        usertotalcount     = usertotalcount + userfailedjobcount;
                        failedjobcount     = parseInt(messages[messagecount]['failedjobcount']['all']);
                        admintotalcount = admintotalcount + failedjobcount;
                    }
                    if (usertotalcount > 999 ) {
                        usertotalcountlabel = "999+";
                    } else {
                        usertotalcountlabel = usertotalcount.toString();
                    }

                    if (usertotalcountlabel == "0") {
                        jQuery('span.UserMenuCountPill').html(DOMPurify.sanitize(usertotalcountlabel)).hide();
                    } else {
                        jQuery('span.UserMenuCountPill').html(DOMPurify.sanitize(usertotalcountlabel)).fadeIn();
                    }                    

                    if (activeSeconds > 0 || message_poll_first_run) {
                        for(var i = 0; i < messagecount; i++) {
                            var ref = messages[i]['ref'];
                            if (message_poll_first_run)
                                {
                                message_refs.push(ref);
                                continue;
                                }
                            if (message_refs.indexOf(ref)!=-1)
                                {
                                continue;
                                }
                            message_refs.push(ref);
                            var message = nl2br(messages[i]['message']);
                            var url = messages[i]['url'];
                            if(messages[i]['type'] & <?php echo MESSAGE_ENUM_NOTIFICATION_TYPE_USER_MESSAGE ?> && pagename =='user_message' && parseInt(messages[i]['ownerid']) == parseInt(msgto))
                                {
                                // Show the message directly on the page if on user_message.php and communicating with this user
                                showUserMessage(messages[i]['message'],false);
                                }
                            else
                                {
                                // Show message popup if configured
                                <?php
                                if ($user_pref_show_notifications) {
                                    ?>
                                    message_display(message, url, ref, function (ref) {
                                    jQuery.get('<?php echo $baseurl; ?>/pages/ajax/message.php?ajax=true&seen=' + ref);
                                    });
                                    <?php
                                }
                                ?>                                
                                }                           
                            message_poll();
                        }
                    }

                    if (actioncount > 0) {
                        if (actioncount > 999 ) {
                            actioncountlabel = "999+";
                        } else {
                            actioncountlabel = actioncount.toString();
                        }
                        console.log(actioncount);
                        jQuery('span.ActionCountPill').html(DOMPurify.sanitize(actioncountlabel)).fadeIn();
                    } else {
                        jQuery('span.ActionCountPill').hide();
                    }

                    if (messagecount > 0) {
                        if (messagecount > 999 ) {
                            messagecountlabel = "999+";
                        } else {
                            messagecountlabel = messagecount.toString();
                        }
                        jQuery('span.MessageCountPill').html(DOMPurify.sanitize(messagecountlabel)).fadeIn();
                    } else {
                        jQuery('span.MessageCountPill').hide(); 
                    }

                    if (failedjobcount>0)
                        {
                        jQuery('span.FailedJobCountPill').html(failedjobcount).fadeIn();
                        let teampill = jQuery('#TeamMessages');
                        if(teampill.attr('data-value') != undefined)
                            {
                            failedjobcount = failedjobcount + teampill.attr('data-value');
                            }
                        teampill.html(DOMPurify.sanitize(failedjobcount)).fadeIn();
                        }
                    else
                        {
                        jQuery('span.FailedJobCountPill').hide();   
                        }

                    if (admintotalcount > 0) {
                        if (admintotalcount > 999 ) {
                            admintotalcountlabel = "999+";
                        } else {
                            admintotalcountlabel = admintotalcount.toString();
                        }
                        jQuery('span.AdminMenuCountPill').html(DOMPurify.sanitize(admintotalcountlabel)).fadeIn();
                    } else {
                        jQuery('span.AdminMenuCountPill').hide();
                    }

                } else {
                    jQuery('span.UserMenuCountPill').hide();
                    jQuery('span.MessageCountPill').hide();
                    jQuery('span.ActionCountPill').hide();
                    jQuery('span.AdminCountPill').hide();
                }
            }
        }).done(function() {
            <?php if ($message_polling_interval_seconds > 0) {
                ?>message_timer = window.setTimeout(message_poll,<?php echo $message_polling_interval_seconds; ?> * 1000);
                <?php
            }
            ?>
            message_poll_first_run = false;
        });

        check_upgrade_in_progress();
    }

    jQuery(document).bind("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error",
        function() {
            activeSeconds=<?php echo MESSAGE_POLLING_ABSENT_USER_TIMEOUT_SECONDS; ?>;
        });

    jQuery(document).ready(function () {
            message_poll();
        });

    function message_display(message, url, ref, callback)
    {
        if (typeof ref==="undefined")
        {
            ref=new Date().getTime();
        }
        if (typeof url==="undefined")
        {
            url="";
        }
        if (url!="")
        {
            url=decodeURIComponent(url);
            url="<a href='" + url + "'><?php echo escape($lang['link']); ?></a>";
        }
        var id='message' + ref;
        if (jQuery("#" + id).length)        // already being displayed
        {
            return;
        }

    jQuery('div#MessageContainer')
        .append("<div class='MessageBox' style='display: none;' id='" + id + "'>" + nl2br(DOMPurify.sanitize(message)) + "<br />" + url + "</div>")
        .after(function()
        {

            var t = window.setTimeout(function()
            {
                jQuery("div#" + id).fadeOut("fast",function()
                    {
                        this.remove()
                    }
                )
            },<?php echo MESSAGE_FADEOUT_SECONDS; ?>000);

            jQuery("div#" + id).show().bind("click",function()
            {
                jQuery("div#" + id).fadeOut("fast", function()
                {
                    jQuery("div#" + id).remove();
                    jQuery.get('<?php echo $baseurl; ?>/pages/ajax/message.php?ajax=true&seen=' + ref);
                    if (typeof callback === 'function')
                    {
                        callback();
                    }
                });
            });

            jQuery("div#" + id).bind("mouseenter",function()
            {
                window.clearTimeout(t);
                jQuery("div#" + id).fadeIn("fast");
            });

            jQuery("div#" + id).bind("mouseleave",function()
            {
                window.clearTimeout(t);
                t = window.setTimeout(function()
                {
                    jQuery("div#" + id).fadeOut("fast",function()
                        {
                            this.remove();
                        }
                    )},<?php echo ceil(MESSAGE_FADEOUT_SECONDS / 2); ?>000);
            });
        });
    }
    
    function message_modal(message, url, ref, owner)
        {
        if (typeof ref==="undefined")
            {
                ref=new Date().getTime();
            }
        if (typeof url==="undefined")
            {
                url="";
            }
        if (url!="")
            {
                url=decodeURIComponent(url);
                url=DOMPurify.sanitize(url);
                url="<a class='message_link' href='" + url + "'><?php echo escape($lang['link']); ?></a>";
            }
        if (typeof owner==="undefined" || owner=='')
            {
            owner = '<?php echo escape($applicationname); ?>';
            }

        jQuery("#modal_dialog").html("<div class='MessageText'>" + nl2br(DOMPurify.sanitize(message)) + "</div>" + url);
        jQuery("#modal_dialog").addClass('message_dialog');
        jQuery("#modal_dialog").dialog({
            title: '<?php echo escape($lang['message'] . " " . strtolower($lang["from"])) . " "; ?>' + owner,
            modal: true,
            resizable: false,
            buttons: [{text: "<?php echo escape($lang['ok']); ?>",
                        click: function() {
                        jQuery( this ).dialog( "close" );
                        }}],
            dialogClass: 'message',
            width: (jQuery(window).width() <= 1280) ? jQuery(window).width()*0.7 : 600,
            maxHeight: jQuery(window).height()*0.8,
            draggable: true,
            open: function(event, ui) {
                jQuery('.ui-widget-overlay').bind('click', function(){ jQuery("#modal_dialog").dialog('close'); });
                jQuery( ".ui-dialog-content" ).scrollTop(0);
                },
            close: function( event, ui ) {
                jQuery('#modal_dialog').html('');
                jQuery("#modal_dialog").removeClass('message_dialog');
                jQuery.get('<?php echo $baseurl; ?>/pages/ajax/message.php?ajax=true&seen=' + ref);
                },
            dialogClass: 'no-close'
            });
                
        }

</script>
