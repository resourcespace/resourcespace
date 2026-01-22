<?php

include "../../include/boot.php";
include_once dirname(__DIR__, 2) . '/include/login_functions.php';

$password_reset_mode = false;
$resetvalues = getval("rp", "");
if ($resetvalues != "") {
    if (substr($resetvalues, 0, 2) == "3D") {
        // Email may have encoded the = character
        $resetvalues = substr($resetvalues, 2);
    }

    $rplength = strlen($resetvalues);
    $resetuserref = substr($resetvalues, 0, $rplength - 15);
    $resetkey = substr($resetvalues, $rplength - 15);
    $valid_reset_link = false;
    $resetuser = get_user($resetuserref);

    if ($resetuser) {
        $keycheck = array();
        $keycheck[] = substr(hash('sha256', date("Ymd") .  $resetuser["password_reset_hash"] . $resetuser["username"] . $scramble_key), 0, 15);
        // We also need to check the entered key to see if valid in the last number of days ($password_reset_link_expiry), so add these as possible values to the array
        for ($n = 1; $n <= $password_reset_link_expiry; $n++) {
            $keycheck[] = substr(hash('sha256', date("Ymd", time() - 60 * 60 * 24 * $n) .  $resetuser["password_reset_hash"] . $resetuser["username"] . $scramble_key), 0, 15);
        }
        if (in_array($resetkey, $keycheck)) {
            $valid_reset_link = true;
        }
    }

    if ($valid_reset_link) {
        $userref = $resetuser["ref"];
        $username = $resetuser["username"];
        $userfullname = $resetuser["fullname"];
        $email = $resetuser["email"];
        $usergroup = $resetuser["usergroup"];
        $userpassword = $resetuser["password"];
        $last_active = $resetuser["last_active"];
        $password_reset_mode = true;
    } else {
        redirect($baseurl . "/login.php?error=passwordlinkexpired");
        exit();
    }
}

if (!$password_reset_mode) {
    include "../../include/authenticate.php";
    if (checkperm("p") || !$allow_password_change || (isset($anonymous_login) && $anonymous_login == $username)) {
        exit(escape($lang["not_allowed"]));
    }

    // Reset $not_authenticated_pages (used in header) when this runs in an authenticated context. This will allow showing
    // the header and search bar as normal.
    $GLOBALS['modify_header_not_authenticated_pages'] = [];
    $GLOBALS['modify_header_omit_searchbar_pages'] = [];
}
$is_authenticated_ctx = is_authenticated();

hook("preuserpreferencesform");

if (getval("save", "") != "" && enforcePostRequest(false)) {
    if ($case_insensitive_username) {
        $username = ps_value("SELECT username value FROM user WHERE LOWER(username)=LOWER(?)", ["s",$username], $username);
    }
    if (hook('saveadditionaluserpreferences')) {
        # The above hook may return true in order to prevent the password from being updated
    } elseif (
        !$password_reset_mode
        && !rs_password_verify(getval('currentpassword', ''), $userpassword, ['username' => $username])
    ) {
        $error3 = $lang['wrongpassword'];
    } else {
        if (getval("password", "") != getval("password2", "")) {
            $error2 = true;
        } else {
            $message = change_password(getval("password", ""));
            if ($message === true) {
                if ($password_reset_mode && empty($last_active) && $email != "") {
                    // This account has just been created, probably an auto approved account. Send the welcome email
                    email_user_welcome($email, $username, $usergroup);
                    redirect($baseurl_short . "pages/done.php?text=password_changed&notloggedin=true");
                    exit();
                }
                redirect($baseurl_short . "pages/" . ($use_theme_as_home ? 'collections_featured.php' : "home.php"));
                exit();
            } else {
                $error = true;
            }
        }
    }
}

include "../../include/header.php";

if ($is_authenticated_ctx) {
    ?><div class="BasicsBox"><?php
} else {
    include '../../include/login_background.php';
    ?>
    <div id="LoginHeader">
        <img alt="" src="<?php echo get_header_image(); ?>" class="LoginHeaderImg">
    </div>
    <?php
}

if ($userpassword == "b58d18f375f68d13587ce8a520a87919") {
    ?><div class="FormError" style="margin:0;"><?php echo escape($lang['secureyouradminaccount']);?></div><p></p><?php
}

if (!hook("replaceuserpreferencesheader")) { ?>
    <p>
    <h1><?php echo escape(empty($last_active) ? $lang["newpassword"] : $lang["changeyourpassword"]); ?></h1>
    <?php
}

if ($password_reset_mode && empty($last_active)) {
    // The user is a new account setting a password for the first time
    echo escape(text("introtext_new"));
} else {
    echo escape(text("introtext"));
} ?>
</p>

<?php if (getval("expired", "") != "") {
    ?><div class="FormError">!! <?php echo escape($lang["password_expired"]); ?> !!</div><?php
} ?>

<form method="post" action="<?php echo $baseurl_short?>pages/user/user_change_password.php">
    <input type="hidden" name="expired" value="<?php echo escape(getval("expired", ""))?>">
    <?php
    generateFormToken("user_change_password");

    if (!$password_reset_mode) {
        // Additional user preferences only available in use cases other than password reset
        hook('additionaluserpreferences');
        ?>
        <div class="Question">
            <label for="password"><?php echo escape($lang["currentpassword"]); ?></label>
            <input
                type="password"
                class="stdwidth"
                name="currentpassword"
                id="currentpassword"
                value="<?php echo ($userpassword == "b58d18f375f68d13587ce8a520a87919") ? 'admin' : ''; ?>"
            />
            <div class="clearerleft"></div>
            <?php if (isset($error3)) { ?>
                <div class="FormError">!! <?php echo $error3?> !!</div>
                <?php
            } ?>
        </div>
        <?php
    } else { ?>
        <input type="hidden" name="rp" id="resetkey" value="<?php echo escape($resetuserref) . escape($resetkey)  ?>" />
        <?php if ($auto_approve_accounts && empty($last_active)) {
            // Show the username for newly created accounts. A new user will get here before they get their welcome email.
            ?>
            <div class="Question">
                <label class="label" for="username"><?php echo escape($lang["username"]); ?></label>
                <div class="Fixed" ><?php echo escape($username); ?></div>
                <div class="clearerleft"></div>
            </div>
            <?php
        }
    }
    ?>
    <div class="Question">
        <label for="password"><?php echo escape($lang["newpassword"]); ?></label>
        <input type="password" name="password" id="password" class="stdwidth">
        <?php if (isset($error)) { ?>
            <div class="FormError">!! <?php echo $message?> !!</div>
            <?php
        } ?>
        <div class="clearerleft"> </div>
    </div>

    <div class="Question">
        <label for="password2"><?php echo escape($lang["newpasswordretype"]); ?></label>
        <input type="password" name="password2" id="password2" class="stdwidth">
        <?php if (isset($error2)) { ?>
            <div class="FormError">!! <?php echo escape($lang["passwordnotmatch"]); ?> !!</div>
            <?php
        } ?>
        <div class="clearerleft"></div>
    </div>

    <div class="QuestionSubmit">
        <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>" />
        <div class="clearerleft"></div>
    </div>
</form>

<?php
if (!$password_reset_mode) {
    hook("afteruserpreferencesform");
}

if ($is_authenticated_ctx) {
    // Close BasicsBox container
    ?></div><?php
}
include "../../include/footer.php";