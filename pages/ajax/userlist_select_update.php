<?php

# Update the user select element

include "../../include/boot.php";
include "../../include/authenticate.php";

if (is_anonymous_user()) {
    exit(escape($lang['error-permissiondenied']));
}

$userstring = getval("userstring", "");
?>

<?php $user_userlists = ps_query("select " . columns_in('user_userlist') . " from user_userlist where user= ?", ['i', $userref]);?>

<option value=""><?php echo escape($lang['loadasaveduserlist']); ?></option>
<?php
if (count($user_userlists) > 0) {
    foreach ($user_userlists as $user_userlist) { ?>
        <option
            id="<?php echo escape($user_userlist['ref']); ?>"
            value="<?php echo escape($user_userlist['userlist_string']); ?>"
            <?php if ($userstring == $user_userlist['userlist_string']) {
                ?>selected<?php
            } ?>
        ><?php echo escape($user_userlist['userlist_name']); ?></option>
    <?php }
}

