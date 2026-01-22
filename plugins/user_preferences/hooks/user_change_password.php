<?php

function HookUser_preferencesuser_change_passwordReplaceuserpreferencesheader()
    {
    echo '<h1>' . $GLOBALS['lang']['user-preferences'] . '</h1>';
    return true;
    }

function HookUser_preferencesuser_change_passwordSaveadditionaluserpreferences()
    {
    global $user_preferences_change_username, $user_preferences_change_email,
            $user_preferences_change_name, $userref, $useremail, $username, $userfullname, $lang;

    $newUsername=trim(getval('username', $username));
    $newEmail=getval('email', $useremail);
    $newFullname=getval('fullname', $userfullname);

    # Check if a user with that username already exists
    if ($user_preferences_change_username && $username != $newUsername)
        {
        $existing = ps_query('SELECT ref FROM user WHERE username = ?', ['s', $newUsername]);
        if (!empty($existing))
            {
            $GLOBALS['errorUsername'] = $lang['useralreadyexists'];
            return false;
            }
        }

    # Check if a user with that email already exists
    if ($user_preferences_change_email && $useremail != $newEmail)
        {
        $existing = ps_query('SELECT ref FROM user WHERE email = ?', ['s', $newEmail]);
        if (!empty($existing))
            {
            $GLOBALS['errorEmail'] = $lang['useremailalreadyexists'];
            return false;
            }
        }

    # Store changed values in DB, and update the global variables as header.php is included next
    if ($user_preferences_change_username && $username != $newUsername)
        {
        ps_query("UPDATE user SET username = ? WHERE ref = ?", ['s', $newUsername, 'i', $userref]);
        $username = ps_value("SELECT username as `value` FROM user WHERE ref = ?", ['i', $userref], '');
        }
    if ($user_preferences_change_email && $useremail != $newEmail)
        {
        ps_query("UPDATE user SET email = ? WHERE ref = ?", ['s', $newEmail, 'i', $userref]);
        $useremail = $newEmail;
        }
    if ($user_preferences_change_name && $userfullname != $newFullname)
        {
        ps_query("UPDATE user SET fullname = ? WHERE ref = ?", ['s', $newFullname, 'i', $userref]);
        $userfullname = $newFullname;
        }

    return (getval('currentpassword', '')=='' && getval('rp', '')=='') || (getval('password', '')=='')
            && (getval('password2', '')=='');
    }

function HookUser_preferencesuser_change_passwordAdditionaluserpreferences()
    {
    global $user_preferences_change_username, $user_preferences_change_email,
            $user_preferences_change_name, $lang, $errorUsername, $errorEmail, $errorFullname;

    if ($user_preferences_change_username)
        {
        global $username;
    ?>
    <div class="Question">
    <label for="username"><?php echo escape($lang["username"]); ?></label>
    <input type="text" class="stdwidth" name="username" id="username" value="<?php echo escape($username) ?>"/>
    <div class="clearerleft"> </div>
    <?php if (isset($errorUsername)) { ?><div class="FormError">!! <?php echo $errorUsername ?> !!</div><?php } ?>
    </div>
    <?php
        }

    if ($user_preferences_change_email)
        {
        global $useremail;
    ?>
    <div class="Question">
    <label for="email"><?php echo escape($lang["email"]); ?></label>
    <input type="text" class="stdwidth" name="email" id="email" value="<?php echo escape($useremail) ?>"/>
    <div class="clearerleft"> </div>
    <?php if (isset($errorEmail)) { ?><div class="FormError">!! <?php echo $errorEmail ?> !!</div><?php } ?>
    </div>
    <?php
        }

    if ($user_preferences_change_name)
        {
        global $userfullname;
    ?>
    <div class="Question">
    <label for="fullname"><?php echo escape($lang["fullname"]); ?></label>
    <input type="text" class="stdwidth" name="fullname" id="fullname" value="<?php echo escape($userfullname) ?>"/>
    <div class="clearerleft"> </div>
    <?php if (isset($errorFullname)) { ?><div class="FormError">!! <?php echo $errorFullname ?> !!</div><?php } ?>
    </div>
    <?php
        }
    }

?>
