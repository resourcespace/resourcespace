<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

if (
    !(
        (checkperm('h') && !checkperm('hdta')) ||
        (checkperm('dta') && !checkperm('h')) ||
        (!checkperm('h') && !checkperm('hdt_ug'))
    )
) {
    exit($lang['error-permissiondenied']);
}

include "../../include/dash_functions.php";

$show_usergroups_dash = ('true' == getval('show_usergroups_dash', '')) && checkperm('h') && checkperm('hdt_ug');

if ($show_usergroups_dash) {
    $user_groups         = get_usergroups(false, '', true);
    // Get selected user group or default to first user group found
    $selected_user_group = getval('selected_user_group', key($user_groups), true);
}

include "../../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo $show_usergroups_dash ? $lang['manage_user_group_dash_tiles'] . ' - ' . escape($user_groups[$selected_user_group]) : $lang["manage_all_dash"]; ?></h1>

    <?php
    $links_trail = array(
        array(
            'title' => $lang["teamcentre"],
            'href'  => $baseurl_short . "pages/team/team_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $show_usergroups_dash ? $lang['manage_user_group_dash_tiles'] . ' - ' . $user_groups[$selected_user_group] : $lang["manage_all_dash"],
            'help'  => "user/manage-dash-tile"
        )
    );

    renderBreadcrumbs($links_trail);

    $href = "{$baseurl_short}pages/team/team_dash_admin.php";
    if ($show_usergroups_dash) {
        $href .= "?show_usergroups_dash=true&selected_user_group={$selected_user_group}";
    }
    ?>

    <p>
        <a href="<?php echo $href; ?>" onClick="return CentralSpaceLoad(this,true);">
            <?php echo LINK_CARET . escape($lang["dasheditmodifytiles"]);?>
        </a>
    </p>

    <?php
    if (!$show_usergroups_dash) {
        ?>
        <p>
            <a href="<?php echo $baseurl_short?>pages/team/team_dash_tile_special.php" onClick="return CentralSpaceLoad(this,true);">
                <?php echo LINK_CARET . escape($lang["specialdashtiles"]);?>
            </a>
        </p>
        <?php
    }

    if ($show_usergroups_dash) { ?>
        <div id="HomePanelContainer" class="manage-all-user-tiles">
        <?php
        get_default_dash($selected_user_group);
    } else {
        ?>
        <p><?php echo escape($lang['manage_all_user_dash_tiles_note']); ?></p>
        <div id="HomePanelContainer" class="manage-all-user-tiles">
        <?php
        get_default_dash(null, true);
    }
    ?>
    </div>
</div>
<?php
include "../../include/footer.php";
