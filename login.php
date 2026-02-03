<?php
include_once "include/boot.php";
include_once "include/login_functions.php";

debug("[login.php] Reached login page...");
$url = getval("url", $baseurl . "/index.php");

if (is_array($url)) {
    $url = $baseurl . "/index.php";
}

$modal = getval("modal", "");

if ($modal || getval("ajax", "") != "") {
    # add the capslock lib because there's no header
    ?>
    <script type="text/javascript" src="<?php echo $baseurl; ?>/lib/js/jquery.capslockstate.js"></script>
    <?php
}

# process log in
$error = getval("error", "");
$error = isset($lang[$error]) ? $lang[$error] : "";

# Auto logged out? Set error message.
if (getval("auto", "") != "") {
    $error = str_replace("30", $session_length, $lang["sessionexpired"]);
}

# Display a no-cookies message
if (getval("nocookies", "") != "" && getval("cookiecheck", "") == "") {
    $error = $lang["nocookies"];
}

# First check that this IP address has not been locked out due to excessive attempts.
$ip = get_ip();
$lockouts = ps_value("select count(*) value from ip_lockout where ip = ? and tries >= ? and date_add(last_try, interval ? minute) > now()", array("s", $ip, "i", $max_login_attempts_per_ip, "i", $max_login_attempts_wait_minutes), 0);

$username = getval("username", "");
if (is_array($username)) {
    debug("[login.php] redirect to login because username is array");
    redirect($baseurl . "/login.php");
}

$username = trim($username);
if ($case_insensitive_username) {
    $username = ps_value("SELECT username value FROM user WHERE LOWER(username) = LOWER(?)", ["s", $username], $username);
}

# Also check that the username provided has not been locked out due to excessive login attempts.
$ulockouts = ps_value("select count(*) value from user where username = ? and login_tries >= ? and date_add(login_last_try, interval ? minute) > now()", array("s", $username, "i", $max_login_attempts_per_username, "i", $max_login_attempts_wait_minutes), 0);

if ($lockouts > 0 || $ulockouts > 0) {
    $error = str_replace("?", $max_login_attempts_wait_minutes, $lang["max_login_attempts_exceeded"]);
    if ($ulockouts > 0) {
        $log_message = 'Account locked';
    } else {
        $log_message = 'IP address locked';
    }
    $userref = get_user_by_username($username);
    log_activity(
        $log_message,                       # Note
        LOG_CODE_FAILED_LOGIN_ATTEMPT,      # Log Code
        $ip,                                # Value New
        ($userref != "" ? "user"    : null),  # Remote Table
        ($userref != "" ? "last_ip" : null),  # Remote Column
        ($userref != "" ? $userref  : null),  # Remote Ref
        null,                               # Ref Column Override
        null,                               # Value Old
        ($userref != "" ? $userref : null)  # User
    );
} elseif (array_key_exists("username", $_POST) && getval("langupdate", "") == "") {
    debug("[login.php] Process the submitted login details...");

    $password = trim(getval("password", ""));
    $result = perform_login($username, $password);
    if ($result['valid']) {
        debug("[login.php] Performed login - valid result");

        set_login_cookies($result["ref"], $session_hash, $language, $user_preferences);

        # Set 'user_local_timezone' in cookie like 'user preferences page' does
        $login_lang = getval("user_local_timezone", "");
        rs_setcookie('user_local_timezone', $login_lang, 365);

        # If the redirect URL is the collection frame, do not redirect to this as this will cause
        # the collection frame to appear full screen.
        if (strpos($url, "pages/collections.php") !== false) {
            $url = "index.php";
        }

        $accepted = ps_value("SELECT accepted_terms value FROM user WHERE ref = ?", array("i", (int)$result['ref']), 0);

        if (0 == $accepted && $terms_login && !checkperm('p')) {
            $redirect_url = 'pages/terms.php?url=' . urlencode($url);
        } else {
            $redirect_url = $url;
        }

        debug("[login.php] Redirecting to $redirect_url");

        if (!$modal) {
            ?>
            <script type="text/javascript">
                window.location.href = '<?php echo escape($redirect_url); ?>';
            </script>
            <?php
        } else {
            ?>
            <script type="text/javascript">
                CentralSpaceLoad('<?php echo $baseurl . "/" . escape($redirect_url); ?>',true);
            </script>
            <?php
        }
    } else {
        sleep($password_brute_force_delay);

        $error = $result['error'];
    }
}

if (getval("logout", "") != "" && array_key_exists("user", $_COOKIE)) {
    debug("[login.php] Logging user out...");

    $session = $_COOKIE["user"];

    // Check CSRF Token
    $csrf_token = getval($CSRF_token_identifier, "");
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !isValidCSRFToken($csrf_token, $session)) {
        http_response_code(400);
        debug("WARNING: CSRF verification failed!");
        trigger_error($lang["error-csrf-verification-failed"]);
    }

    // Clear out special "COLLECTION_TYPE_SELECTION" collection
    $user_selection_collection = get_user_selection_collection(ps_value("SELECT ref AS `value` FROM user WHERE session = ?", array("s", $session), null));
    if (!is_null($user_selection_collection) && count(get_collection_resources($user_selection_collection)) > 0) {
        remove_all_resources_from_collection($user_selection_collection);
    }

    ps_query("UPDATE user SET logged_in = 0, session = NULL, csrf_token = NULL WHERE session = ?", array("s", $session));
    hook("removeuseridcookie");
    #blank cookie
    rs_setcookie('user', '', 0);

    # Also blank search related cookies
    rs_setcookie('search', '');
    rs_setcookie('search_form_submit', '');
    rs_setcookie('saved_offset', '');
    rs_setcookie('saved_archive', '');
    rs_setcookie('restypes', '');

    // Blank cookies under /pages as well
    rs_setcookie('search', '', 0, $baseurl_short . 'pages');
    rs_setcookie('saved_offset', '', 0, $baseurl_short . 'pages');
    rs_setcookie('saved_archive', '', 0, $baseurl_short . 'pages');
    rs_setcookie('restypes', '', 0, $baseurl_short . 'pages');

    unset($username);

    hook("postlogout");

    if (isset($anonymous_login)) {
        # If the system is set up with anonymous access, redirect to the home page after logging out.
        redirect("pages/home.php");
    }
}

hook("postlogout2");

if (getval("langupdate", "") != "") {
    # Update language while remaining on this page.
    rs_setcookie("language", $language, 1000); # Only used if not global cookies
    rs_setcookie("language", $language, 1000, $baseurl_short . "pages/");
    redirect("login.php");
}

$autocomplete_attr = $login_autocomplete ? '' : ' autocomplete="off"';
$aria_describedby_attr = $error == '' ? '' : ' aria-describedby="LoginError"';

include "include/header.php";
include "include/login_background.php";
?>

<form
    id="loginform"
    method="post"
    action="<?php echo $baseurl_short; ?>login.php"<?php
        echo $autocomplete_attr;
    if ($modal) {
        ?>
            onsubmit="return ModalPost(this,true,true);"
        <?php
    } ?>
>
    <input type="hidden" name="langupdate" id="langupdate" value="">  
    <input type="hidden" name="url" value="<?php echo escape($url); ?>">
    <input type="hidden" name="modal" value="<?php echo $modal == "true" ? "true" : ""; ?>">

    <?php $header_img_src = get_header_image(for_login: true); ?>
    <div id="LoginHeader">
        <img src="<?php echo $header_img_src; ?>" class="LoginHeaderImg" alt="<?php echo $applicationname; ?>">
    </div>

    <h1><?php echo text("welcomelogin"); ?></h1>

    <p class="ExternalLoginLinks">
        <?php hook("loginformlink"); ?> 
    </p>

    <?php if ($error != "") { ?>
        <div class="FormIncorrect" id="LoginError" tabindex="-1"><?php echo strip_tags_and_attributes($error); ?></div>
        <script>window.onload = function() { document.getElementById("LoginError").focus(); }</script>
    <?php }?>

    <div class="Question">
        <label for="username"><?php echo escape($lang["email"] . " / " . $lang["username"]); ?></label>
        <input type="text" name="username" id="username" class="stdwidth"<?php echo $autocomplete_attr; ?> value="<?php echo escape(getval("username", "")); ?>"/>
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label for="password"><?php echo escape($lang["password"]); ?> </label>
        <input type="password" name="password" id="password" autocomplete="current-password" class="stdwidth"<?php echo $autocomplete_attr; ?>/>
        <div id="capswarning"><?php echo escape($lang["caps-lock-on"]); ?></div>
        <div class="clearerleft"></div>
    </div>


    <?php if ($allow_keep_logged_in) { ?>
        <div class="Question KeepLoggedIn">
            <label for="remember"><?php echo escape($lang["keepmeloggedin"]); ?></label>
            <input name="remember" id="remember" type="checkbox" value="yes" <?php echo ($remember_me_checked === true) ? "checked='checked'" : ""; ?>>
            <div class="clearer"> </div>
        </div>
    <?php } ?>

    <div class="QuestionSubmit">       
        <input name="Submit" type="submit" value="<?php echo escape($lang["login"]); ?>" />
    </div>

    <p class="LoginLinks">
        <?php if ($allow_account_request) { ?>
            <a id="account_apply" href="<?php echo $baseurl_short; ?>pages/user_request.php">
                <i class="icon-user-round-plus"></i>&nbsp;<?php echo escape($lang["nopassword"]); ?>
            </a>
        <?php }

        if ($allow_password_reset) { ?>
            <br/>
            <a id="account_pw_reset" href="<?php echo $baseurl_short; ?>pages/user_password.php">
                <i class="icon-lock"></i>&nbsp;<?php echo escape($lang["forgottenpassword"]); ?>
            </a>
            <?php
        } ?>
    </p>
    <div class="Question HalfWidth">
        <label for="user_local_tz"><?php echo escape($lang["local_tz"]); ?></label>
        <select id="user_local_tz" class="stdwidth" name="user_local_timezone">
            <?php
            $user_local_timezone = getval('user_local_timezone', '');

            foreach (timezone_identifiers_list() as $timezone) {
                if ($user_local_timezone == $timezone) {
                    ?>
                    <option value="<?php echo $timezone; ?>" selected><?php echo $timezone; ?></option>
                    <?php
                } else {
                    ?>
                    <option value="<?php echo $timezone; ?>"><?php echo $timezone; ?></option>
                    <?php
                }
            }
            ?>
        </select>
        <script>
            jQuery(document).ready(function() {
                var user_local_tz = detect_local_timezone();
                <?php if ($user_local_timezone === '') { ?>
                    jQuery('#user_local_tz').val(user_local_tz);
                <?php } ?>
            });
        </script>
        <div class="clearerleft"></div>
    </div>

    <?php if (!$disable_languages) { ?>
        <div class="Question HalfWidth">
            <label for="language"><?php echo escape($lang["language"]); ?></label>
            <select id="language" class="stdwidth" name="language" onblur="document.getElementById('langupdate').value='YES';document.getElementById('loginform').submit();">
                <?php
                reset($languages);
                foreach ($languages as $key => $value) { ?>
                    <option value="<?php echo escape($key); ?>" <?php echo ($language == $key) ? " selected" : ''; ?>>
                        <?php echo escape(get_display_language($key, $value)); ?>
                    </option>
                <?php } ?>
            </select>
            <div class="clearerleft"></div>
        </div> 
    <?php } ?>
</form>

<script type="text/javascript">
    // Default the focus to the username box
    jQuery('#username').focus();

    jQuery(document).ready(function() {
        /* 
        * Bind to capslockstate events and update display based on state 
        */
        jQuery(window).bind("capsOn", function(event) {
            if (jQuery("#password:focus").length > 0) {
                jQuery("#capswarning").show();
            }
        });

        jQuery(window).bind("capsOff capsUnknown", function(event) {
            jQuery("#capswarning").hide();
        });

        jQuery("#password").bind("focusout", function(event) {
            jQuery("#capswarning").hide();
        });

        jQuery("#password").bind("focusin", function(event) {
            if (jQuery(window).capslockstate("state") === true) {
                jQuery("#capswarning").show();
            }
        });

        /* 
        * Initialize the capslockstate plugin.
        * Monitoring is happening at the window level.
        */
        jQuery(window).capslockstate();
    });
</script>

<div><!-- end of login_box -->

<?php
include "include/footer.php";
