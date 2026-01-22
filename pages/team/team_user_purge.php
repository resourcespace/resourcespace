<?php

/**
 * User purge form display page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!$user_purge || !checkperm("u")) {
    exit("Permission denied.");
}

$months = getval("months", "");

if ($months != "") {
    if (!is_numeric($months) || $months < 0) {
        $error = $lang["pleaseenteravalidnumber"];
    } else {
        $condition = "(created IS NULL OR created<date_sub(now(), interval {$months} month)) AND 
                        (last_active IS NULL OR last_active<date_sub(now(), interval {$months} month))";
        if (checkperm("U")) {
            $condition .= " AND (usergroup = ? OR usergroup IN (SELECT ref FROM usergroup g WHERE g.parent = ?))";
            $params = array("i", $usergroup, "i", $usergroup);
        }
        $count = ps_value("SELECT COUNT(*) value FROM user WHERE $condition", $params ?? [], 0);
    }
}

if (isset($condition) && getval("purge2", "") != "" && enforcePostRequest(false)) {
    if ($user_purge_disable) {
        ps_query("UPDATE user SET approved=2 WHERE $condition AND approved=1", $params ?? []);
    } else {
        ps_query("DELETE FROM user WHERE $condition", $params ?? []);
    }
    redirect("pages/team/team_user.php");
}

include "../../include/header.php";
?>

<div class="BasicsBox">
    <?php
    // Breadcrumbs links
    $links_trail = array(
        array(
            'title' => $lang["teamcentre"],
            'href'  => $baseurl_short . "pages/team/team_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["manageusers"],
            'href'  => $baseurl_short . "pages/team/team_user.php"
        ),
        array(
            'title' => $lang["purgeusers"],
            'href'  => $baseurl_short . "pages/team/team_user_purge.php"
        )
    );

    renderBreadcrumbs($links_trail);

    if (isset($error)) { ?>
        <div class="FormError">!! <?php echo $error?> !!</div>
    <?php } ?>

    <form method=post action="<?php echo $baseurl_short?>pages/team/team_user_purge.php">
        <?php
        generateFormToken("team_user_purge");

        if (isset($count) && $count == 0) {
            ?>
            <p><?php echo escape($lang["purgeusersnousers"]); ?></p>

            <?php
        } elseif (isset($count)) { ?>
            <p>
                <?php echo escape(str_replace("%", $count, ($user_purge_disable ? $lang["purgeusersconfirmdisable"] : $lang["purgeusersconfirm"] ))); ?>
                <br />
                <br />
                <input type="hidden" name="months" value="<?php echo escape($months); ?>">
                <input name="purge2" type="submit" value="<?php echo escape($lang["purgeusers"]); ?>" />
            </p>
            <?php $users = ps_query("SELECT " . columns_in("user") . " FROM user WHERE $condition", $params ?? []); ?>
            <table class="InfoTable">
                <tr>
                    <td><strong><?php echo escape($lang["username"]); ?></strong></td>
                    <td><strong><?php echo escape($lang["fullname"]); ?></strong></td>
                    <td><strong><?php echo escape($lang["email"]); ?></strong></td>
                    <td><strong><?php echo escape($lang["created"]); ?></strong></td>
                    <td><strong><?php echo escape($lang["lastactive"]); ?></strong></td>
                </tr>
                <?php foreach ($users as $user) { ?>
                    <tr>
                        <td><?php echo $user["username"]; ?></td>
                        <td><?php echo $user["fullname"]; ?></td>
                        <td><?php echo $user["email"]; ?></td>
                        <td><?php echo nicedate($user["created"]); ?></td>
                        <td><?php echo nicedate($user["last_active"]); ?></td>
                    </tr>
                    <?php
                } ?>
            </table>
        <?php } else { ?>
            <p>
                <?php echo str_replace("%", "<input type='number' class='PurgeUsersMonths' name=months value=12 min=1>", escape($lang["purgeuserscommand"])); ?>
                <br />
                <br />
                <input name="purge1" type="submit" value="<?php echo escape($lang["purgeusers"]); ?>" />
            </p>
        <?php } ?>
    </form>
</div>

<?php
include "../../include/footer.php";
?>
