<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

# Get username.
$username = getval("username", "");
# Resolve the username and make sure the user has access.
$users = get_users(0, $username, "u.username", true, -1, "", false, "", true);

if (count($users) == 0) {
    $username = str_replace('_', ' ', $username);
    $users = get_users(0, $username, "u.username", true, -1, "", false, "", true);
    if (count($users) == 0) {
        exit("User not found.");
    }
}

$user = $users[0]["ref"];
$userdetails = $users[0];

$profile_text = get_profile_text($user);
$profile_image = get_profile_image($user);

include "../../include/header.php";
?><meta http-equiv="Cache-control" content="no-cache">

<div class="BasicsBox">
    <h1><?php echo escape($userdetails["fullname"]) ?></h1>

    <?php if ($profile_image) { ?>
        <p>
            <img src="<?php echo escape($profile_image); ?>" alt="<?php echo escape($lang['current_profile']); ?>">
        </p>
    <?php } ?>

    <p><?php echo nl2br(escape((string)$profile_text)); ?></p>
    <p>
        <a href="<?php echo $baseurl_short . 'pages/user/user_message.php?msgto=' . (int)$user; ?>">
            <?php echo LINK_CARET . escape($lang["new_message"]); ?>
        </a>
    </p>
</div>

<?php
include "../../include/footer.php";

