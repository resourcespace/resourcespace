<?php
function HookCookies_notificationAllHandleuserref()
{
    global $baseurl, $cookies_notification_allow_using_site_on_no_feedback;

    // Allow plugins to bypass this requirement
    if (hook("cookies_notification_bypass")) {
        return true;
    }

    // Ajax calls are handled by cookies_notification/pages/ajax/cookies_user_feedback.php
    if (getval('ajax', '') == 'true') {
        return;
    }

    // Update cookie use option first
    $accepted_cookies_use = getval('accepted_cookies_use', null, true);
    if (!is_null($accepted_cookies_use)) {
        rs_setcookie('accepted_cookies_use', $accepted_cookies_use, 365, '', '', substr($baseurl, 0, 5) == 'https', true);
    }

    /*
    * We redirect back to login in 2 cases:
    * - if plugin is set to now allow users to continue using ResourceSpace if users did not select one of the options for cookies use
    * - if user opted to NOT ACCEPT cookies use
    */
    if (is_null($accepted_cookies_use) && !$cookies_notification_allow_using_site_on_no_feedback) {
        redirect("{$baseurl}/login.php?logout=true&cookies_use=true&require_option=true");
    } elseif (!is_null($accepted_cookies_use) && (int) $accepted_cookies_use === 0) {
        rs_setcookie('accepted_cookies_use', '', -1, '', '', substr($baseurl, 0, 5) == 'https', true);
        redirect("{$baseurl}/login.php?logout=true&cookies_use=true");
    }
}

function HookCookies_notificationAllAfteruicenter()
{
    global $baseurl, $lang, $is_authenticated;

    $accepted_cookies_use = getval('accepted_cookies_use', null, true);

    // Don't show if user accepted the use of cookies
    if (!is_null($accepted_cookies_use) && (int) $accepted_cookies_use === 1) {
        return;
    }
    ?>

    <div id="CookiesUseWrapper">
        <p id="CookieUseMessage"><?php echo escape($lang['cookies_notification_use_cookies_message']); ?></p>
        <span id="CookiesUseActions">
            <input
                class="CookiesUseBtn"
                type="button"
                value="<?php echo escape($lang['cookies_notification_do_not_accept']); ?>"
                onclick="setCookiesUse('deny');"
            >
            <input
                class="CookiesUseBtn HighlightBtn"
                type="button"
                value="<?php echo escape($lang['cookies_notification_accept']); ?>"
                onclick="setCookiesUse('accept');"
            >
        </span>
        <div class="clearer"></div>
    </div>

    <script>
        function setCookiesUse(option) {
            var user_option = 0;

            if (option == 'accept') {
                user_option = 1;
            }

            SetCookie('accepted_cookies_use', user_option, 1);
            jQuery('#CookiesUseWrapper').slideUp();

            <?php if ($is_authenticated) { ?>
                var post_url  = '<?php echo $baseurl; ?>/plugins/cookies_notification/pages/ajax/cookies_user_feedback.php';
                var post_data =  {
                    ajax: true,
                    accepted_cookies_use: user_option,
                    <?php echo generateAjaxToken('setCookiesUse'); ?>
                };

                jQuery.post(post_url, post_data, function(response) {
                    if (typeof response.error !== 'undefined' && response.error.status == 307) {
                        window.location.replace(response.error.detail);
                    }
                }, 'json');
            <?php } ?>

            return;
        }
    </script>

    <?php
    if (getval('cookies_use', '') == 'true') {
        $error_msg = $lang['cookies_notification_cookies_use_error_msg'];

        if (getval('require_option', '') == 'true') {
            $error_msg = $lang['cookies_notification_cookies_use_require_option_error_msg'];
        }
        ?>
        <div id="modal_dialog" style="display: none;"></div>

        <script>
            jQuery(document).ready(function() {
                styledalert(<?php echo json_encode($lang['cookies_notification_cookies_use_title']); ?>, <?php echo json_encode($error_msg); ?>);
            });
        </script>

        <?php
    }
}

function HookCookies_notificationLoginPostlogout()
{
    global $baseurl;

    rs_setcookie('accepted_cookies_use', '', -1, '', '', substr($baseurl, 0, 5) == 'https', true);

    $cookies_use = getval('cookies_use', false);

    if (!$cookies_use) {
        redirect($baseurl);
    }
}

function HookCookies_notificationAllRemoveuseridcookie()
{
    global $baseurl;
    rs_setcookie('accepted_cookies_use', '', -1, '', '', substr($baseurl, 0, 5) == 'https', true);
}