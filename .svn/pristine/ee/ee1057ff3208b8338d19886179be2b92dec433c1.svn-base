<?php

include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../include/header.php";

if (isset($anonymous_login) && $anonymous_login == $username) {
    die($lang["error-permissions-login"]);
}

$messages = array();
message_get($messages, $userref, true, true, "DESC", "created", 10);

?>
<div id="messages-list" class="messages-modal">
    <div class="message-list-header">
        <h1>
            <a href="<?php echo generateURL($baseurl . '/pages/user/user_messages.php'); ?>" onClick="return CentralSpaceLoad(this, true);"><?php echo escape($lang["mymessages"]); ?></a>
        </h1>
    </div>
    <div class="message-list-newmessage">
        <a href="<?php echo generateURL($baseurl . '/pages/user/user_message.php'); ?>" onClick="return CentralSpaceLoad(this, true);"><i class="icon-square-pen"></i> <?php echo escape($lang["new_message"]); ?></a>
    </div>
    <div id="message-list" class="message-list">
    </div>
</div>
<div id="message-detail" class="messages-modal" style="display: none;">
    <h2>
        <a href="#" onclick="show_list();"><?php echo LINK_CARET_BACK . escape($lang["backtomessages"])?></a>
    </h2>
    <div class="message-body">
        <div class="message-full-text"></div>
        <div class="message-reply">
            <a id="reply-link" href="">
                <i class="icon-reply"></i> <?php echo escape($lang["reply"]); ?>
            </a>
        </div>
    </div>
</div>
<script>

    var interval_id = null;

    function quick_message_poll(force_refresh = false) {
        if (jQuery('#messages-list').is(':visible')) {
            if (interval_id === null) {
                interval_id = setInterval(quick_message_poll, 3000);
            }
        } else {
            clearInterval(interval_id);
            interval_id = null;
        }

        jQuery.ajax({
            url: '<?php echo $baseurl; ?>/pages/ajax/messages_quick.php?ajax=true',
            type: 'GET',
            cache: !force_refresh,
            ifModified: !force_refresh,
            success: function(data, textStatus, xhr) {
                if (!force_refresh && textStatus === "notmodified") {
                    return;
                }
                update_message_list(data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error fetching messages:', errorThrown);
            }
        });
    }

    function parseLocalDateTime(s) {
        const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/.exec(s);
        if (!m) return NaN;
        const [, y, mo, d, h, mi, se = '0'] = m;
        return new Date(+y, +mo - 1, +d, +h, +mi, +se).getTime();
    }


    function normalize_messages(obj) {
        return Object.entries(obj).map(([id, m]) => ({
            id: Number(id),
            message_ref: Number(m.message_ref),
            text_preview: m.message_text_preview || '',
            displayname: m.user_details?.fullname || m.user_details?.user_name || '',
            user: m.user_details?.user || false,
            user_profile_image: m.user_profile_image || '',
            unread: m.unread,
            reply: Number(m.reply),
            created: m.created,
            age: m.age,
            ts: m.created?.includes('Z') ||
                m.created?.match(/[+-]\d{2}:\d{2}$/)
                    ? Date.parse(m.created)
                    : parseLocalDateTime(m.created)
        }))
        .sort((a, b) => (a.ts - b.ts) || (a.id - b.id)); // tie-break with id
    }

    function setMessageContents($el, m) {
        $el.toggleClass('unread', m.unread);
        $el.find('.message-username').html(m.displayname);
        $el.find('.message-age').html(m.age);
        $el.find('.message-text').html(m.text_preview);
    }

    function contentKeyOf(m) {
        return [
            m.age, m.unread ? 1 : 0
        ].join('|');
    }


    function update_message_list(message_list) {
        const list = jQuery('#message-list');
        const messages = normalize_messages(message_list);

        const new_ids = new Set(messages.map(m => 'message' + m.id));

        // Remove old/deleted messages
        list.children('.message').each(function () {
            if (!new_ids.has(this.id)) {
                jQuery(this).fadeOut(250, function () { jQuery(this).remove(); });
            }
        });

        if ( messages.length > 0 ) {

            jQuery('.message-nomessages').remove();
            messages.forEach((m, idx) => {
                const domId = 'message' + m.id;
                let $el = jQuery('#' + domId);

                if ($el.length === 0) {
                    // New message
                    $el = jQuery('<div/>')
                            .attr('id', domId)
                            .attr('onclick', 'show_quick_message(' + m.id + ',' + m.message_ref + ',' + m.reply + ')')
                            .addClass('message')
                            .hide();
                    $el.append(jQuery('<div class="message-icon">'));

                    if (m.user) {
                        if (m.user_profile_image != '') {
                            $el.find('.message-icon').append(jQuery('<img />', {id: 'UserProfileImage', src: m.user_profile_image, alt: 'Profile icon', class: 'ProfileImage'}));
                        } else {
                            $el.find('.message-icon').append('<span class="icon-user-round">')
                        }
                    } else {
                        $el.find('.message-icon').append('<span class="icon-settings">');
                    }

                    $el.append(
                        jQuery('<div class="message-container">')
                            .append(jQuery('<div class="message-username">'))
                            .append(jQuery('<div class="message-age">'))
                            .append(jQuery('<div class="message-text">'))
                    );
                    
                    setMessageContents($el, m);
                    $el.data('ckey', contentKeyOf(m));

                    list.prepend($el);

                    $el.fadeIn(250);

                } else {
                    // Existing message
                    var newKey = contentKeyOf(m);
                    var oldKey = $el.data('ckey');

                    if (newKey !== oldKey) {
                        setMessageContents($el, m);
                        $el.data('ckey', newKey);
                    }

                }

            });

            jQuery('.message').after(jQuery('<div class="message-divider">'));

            let viewall = jQuery('.message-viewall');

            if (viewall.length === 0) {
                // Append view all button to message list
                list.append(jQuery('<div class="message-viewall">' + 
                                '<a id="viewall-link" href="<?php echo generateURL($baseurl . '/pages/user/user_messages.php', ['per_page_list' => 99999]); ?>" onClick="return CentralSpaceLoad(this, true);">' + 
                                '<i class="icon-mail"></i> <?php echo escape($lang["viewallmessages"]); ?>' + 
                                '</a></div>'));
            }

        } else {
            list.html('<div class="message-nomessages"><?php echo escape($lang['mymessages_youhavenomessages']); ?></div>');
        }
    }

    function show_quick_message(message_ref, ref, reply) {
        // Show full message in modal
        api("get_user_message",{'ref': message_ref}, function(response)
            {
            console.debug(response);
            if(response.length != false) {
                msgtext   = response['message'];
                msgurl    = response['url'];
                msgowner  = response['owner'];

                if (typeof msgurl === "undefined") {
                    msgurl = "";
                }

                if (msgurl != "") {
                    msgurl = decodeURIComponent(msgurl);
                    msgurl = DOMPurify.sanitize(msgurl);
                    msgurl = "<br /><br /><a class='message_link' href='" + msgurl + "'><?php echo escape($lang['link']); ?></a>";
                }

                jQuery(".message-full-text").html(nl2br(DOMPurify.sanitize(msgtext)) + msgurl);

                if (reply === 1) {
                    jQuery("#reply-link").attr('href','<?php echo $baseurl_short . "pages/user/user_message.php?msgto="; ?>' + msgowner);
                    jQuery(".message-reply").show();
                } else {
                    jQuery("#reply-link").attr('href','');
                    jQuery(".message-reply").hide();
                }

                jQuery('#messages-list').fadeOut(200, function() {
                    jQuery('#message-detail').fadeIn(200, function() {
                        jQuery.get('<?php echo $baseurl; ?>/pages/ajax/message.php?ajax=true&seen=' + ref);                      
                    });
                });

            }
            },
            <?php echo generate_csrf_js_object('get_user_message'); ?>
        );
    }

    function show_list() {
        jQuery('#message-detail').fadeOut(200, function() {
            jQuery('#messages-list').fadeIn(200, function() {
                quick_message_poll(false);
                jQuery(".message-full-text").html("");
            });
        });
        
    }

    jQuery(document).ready(function () {
        quick_message_poll(true);
    });

</script>

<?php

include "../../include/footer.php";
