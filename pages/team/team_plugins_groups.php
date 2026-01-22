<?php

/**
 * Plugins management interface (part of team center) - Group access control
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

$plugin = getval("plugin", "");
$py = get_plugin_yaml($plugin, false);
if ($py['disable_group_select']) {
    $error = $lang['plugins-disabled-plugin-message'];
    error_alert($error);
    exit();
}

# Fetch current access level
$access = (string) ps_value("select enabled_groups value from plugins where name= ?", ['s', $plugin], "");

# Fetch user groups
$groups = get_usergroups();

# Save group activation options
if (getval("save", "") != "" && enforcePostRequest(false)) {
    $access = "";
    if (getval("access", "") == "some") {
        foreach ($groups as $group) {
            if (getval("group_" . $group["ref"], "") != "") {
                if ($access != "") {
                    $access .= ",";
                }
                $access .= $group["ref"];
            }
        }
    }
    # Update database
    log_activity(null, LOG_CODE_EDITED, $access, 'plugins', 'enabled_groups', $plugin, 'name');
    ps_query("update plugins set enabled_groups= ? where name= ?", ['s', $access, 's', $plugin], "");
    clear_query_cache("plugins");
    redirect("pages/team/team_plugins.php");
}

include "../../include/header.php";
$s = explode(",", $access);
?>

<div class="BasicsBox">
    <h1><?php echo escape("{$lang["groupaccess"]}: {$plugin}"); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["pluginmanager"],
            'href'  => $baseurl_short . "pages/team/team_plugins.php"
        ),
        array(
            'title' => $lang["groupaccess"] . ': ' . $plugin,
            'help'  => "systemadmin/managing_plugins"
        )
    );
    renderBreadcrumbs($links_trail);
    ?>

    <form onSubmit="return CentralSpacePost(this,true);" method="post" action="<?php echo $baseurl_short?>pages/team/team_plugins_groups.php?save=true">
        <?php generateFormToken("team_plugins_groups"); ?>
        <p>
            <input type="radio" name="access" value="all" <?php echo ($access == "") ? " checked" : ''; ?>>
            <?php echo escape($lang["plugin-groupsallaccess"]); ?>
            <br/>

            <input type="radio" name="access" value="some" id="some" <?php echo ($access != "") ? " checked" : ''; ?>>
            <?php echo escape($lang["plugin-groupsspecific"]); ?>

            <?php foreach ($groups as $group) { ?>
                <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input
                    type=checkbox
                    name="group_<?php echo $group["ref"]; ?>"
                    value="yes"
                    <?php echo (in_array($group["ref"], $s)) ? " checked " : ''; ?>
                    onClick="document.getElementById('some').checked=true;">
                    <?php echo escape($group["name"]); ?>
            <?php } ?>
        </p>

        <input type=hidden name="plugin" value="<?php echo escape(getval('plugin', ''))?>"/>
        <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>">
    </form>
</div>

<?php include "../../include/footer.php"; ?>
