<?php

/**
 * Plugins management interface (part of team center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm('a')) {
    header('HTTP/1.1 401 Unauthorized');
    exit(escape($lang['error-permissiondenied']));
}

if (isset($_REQUEST['activate']) && enforcePostRequest(false)) {
    $inst_name = trim(getval('activate', ''), '#');
    if ($inst_name != '' && !in_array($inst_name, $disabled_plugins)) {
        activate_plugin($inst_name);
    }
    redirect($baseurl_short . 'pages/team/team_plugins.php');    # Redirect back to the plugin page so plugin is actually activated.
} elseif (isset($_REQUEST['deactivate']) && enforcePostRequest(false)) { # Deactivate a plugin
    # Strip the leading hash mark added by javascript.
    $remove_name = trim(getval('deactivate', ''), "#");
    if ($remove_name != '') {
        deactivate_plugin($remove_name);
    }
    redirect($baseurl_short . 'pages/team/team_plugins.php');    # Redirect back to the plugin page so plugin is actually deactivated.
} elseif (isset($_REQUEST['purge']) && enforcePostRequest(false)) { # Purge a plugin's configuration (if stored in DB)
    # Strip the leading hash mark added by javascript.
    $purge_name = trim(getval('purge', ''), '#');
    if ($purge_name != '') {
        purge_plugin_config($purge_name);
    }
}

$inst_plugins = ps_query('SELECT name, config_url, descrip, author, ' .
    'inst_version, update_url, info_url, enabled_groups, disable_group_select, title, icon ' .
    'FROM plugins WHERE inst_version>=0 order by name');

/**
 * Ad hoc function for array_walk through plugins array.
 *
 * When called from array_walk, steps through each element of the installed
 * plugins array and checks to see if it was installed via config.php (legacy).
 * If so, sets an addition array key for template to display the link correctly.
 *
 * @param array $i_plugin Plugin array element.
 * @param string $key Array key.
 */
function legacy_check(&$i_plugin, $key)
{
    global $legacy_plugins;
    if (array_search($i_plugin['name'], $legacy_plugins) !== false) {
        $i_plugin['legacy_inst'] = true;
    }
}

for ($n = 0; $n < count($inst_plugins); $n++) {
    # Check if group access is permitted by YAML file. Needed because plugin may have been enabled before this development)
    $py = get_plugin_yaml($inst_plugins[$n]["name"], false);

    // Override YAML values (not config) with updated plugin YAML
    foreach (["title","name","icon","author","desc","version","category","config_url","icon-colour"] as $yaml_idx) {
        if (isset($py[$yaml_idx])) {
            $inst_plugins[$n][$yaml_idx] = $py[$yaml_idx];
            if (($yaml_idx) == "desc") { // Special case, "desc" in the YAML is "descrip" in the page/db.
                $inst_plugins[$n]["descrip"] = $py[$yaml_idx];
            }
        }
    }
}

array_walk($inst_plugins, 'legacy_check');
# Build an array of available plugins.
$plugins_avail = array();

function load_plugins($plugins_dir)
{
    global $plugins_avail;

    $dirh = opendir($plugins_dir);
    while (false !== ($file = readdir($dirh))) {
        if (is_dir($plugins_dir . $file) && $file[0] != '.') {
            #Check if the plugin is already activated.
            $status = ps_query('SELECT inst_version, config FROM plugins WHERE name=?', array("s",$file));
            if ((count($status) == 0) || ($status[0]['inst_version'] == null)) {
                # Look for a <pluginname>.yaml file.
                $plugin_yaml = get_plugin_yaml($file, false);
                foreach ($plugin_yaml as $key => $value) {
                    $plugins_avail[$file][$key] = $value ;
                }
                $plugins_avail[$file]['config'] = (ps_value("SELECT config AS value FROM plugins WHERE name=?", array("s",$file), '') != '');
                # If no yaml, or yaml file but no description present,
                # attempt to read an 'about.txt' file
                if ($plugins_avail[$file]["desc"] == "") {
                    $about = $plugins_dir . $file . '/about.txt';
                    if (file_exists($about)) {
                        $plugins_avail[$file]["desc"] = substr(file_get_contents($about), 0, 95) . "...";
                    }
                }
            }
        }
    }
    closedir($dirh);
}

load_plugins(__DIR__ . '/../../plugins/');
if (!file_exists($storagedir . '/plugins/')) {
    mkdir($storagedir . '/plugins/');
}
load_plugins($storagedir . '/plugins/');

ksort($plugins_avail);

// Search functionality
$searching          = ((getval("find", "") != "" && getval("clear_search", "") == "") ? true : false);
$find               = getval("find", "");
if (!$searching) {
    $find = "";
}

/**
* Find plugin which contains the searched text in the name/ description
*
* @param array   $plugin  Plugin data array (either the installed/ available version)
* @param string  $search  Searched text to find the plugin
*
* @return boolean Returns TRUE if plugin data matches the search (mostly name and description), FALSE otherwise
*/
function findPluginFromSearch(array $plugin, $search)
{
    // If we are not searching for anything in particular then
    if (trim($search) == "") {
        return true;
    }

    if (isset($plugin["name"]) && stripos($plugin["name"], $search) !== false) {
        return true;
    }

    if (isset($plugin["title"]) && stripos($plugin["title"], $search) !== false) {
        return true;
    }

    if (isset($plugin["descrip"]) && stripos($plugin["descrip"], $search) !== false) {
        return true;
    }

    if (isset($plugin["desc"]) && stripos($plugin["desc"], $search) !== false) {
        return true;
    }

    return false;
}

/*
 * Start Plugins Page Content
 */
include "../../include/header.php"; ?>

<script type="text/javascript">
    (function($) { 
        $(function() {
            function actionPost(action, value) {
                $('input#anc-input').attr({
                    name: action,
                    value: value
                });

                jQuery('form#anc-post').submit();
            }
            $('#BasicsBox').ready(function() {
                $('a.p-deactivate').click(function() {
                    $(this).parent().parent().fadeOut('fast');
                    actionPost('deactivate', $(this).attr('href'));
                    return false;
                });

                $('a.p-activate').click(function() {
                    $(this).parent().parent().fadeOut('fast');
                    var pname = $(this).attr('href');
                    actionPost('activate', $(this).attr('href'));
                    return false;
                });

                $('a.p-purge').click(function() {
                    actionPost('purge', $(this).attr('href'));
                    return false;                        
                });
            });
        });
    })(jQuery);
</script>

<div class="BasicsBox">
    <h1><?php echo escape($lang["pluginmanager"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["pluginmanager"]
        )
    );
    renderBreadcrumbs($links_trail);
    ?>

    <form id="SearchSystemPages" method="post" onSubmit="return CentralSpacePost(this);">
        <?php generateFormToken("plugin_search"); ?>
        <input type="text" name="find" id="pluginsearch" value="<?php echo escape($find); ?>">
        <input type="submit" name="searching" value="<?php echo escape($lang["searchbutton"]); ?>">
        <?php if ($searching) { ?>
            <input type="button" name="clear_search" value="<?php echo escape($lang["clearbutton"]); ?>" onClick="jQuery('#pluginsearch').val('');CentralSpacePost(document.getElementById('SearchSystemPages'));">
        <?php } ?>
    </form>

    <p>
        <?php
        echo escape($lang["plugins-headertext"]);
        render_help_link('systemadmin/managing_plugins');
        ?>
    </p>

    <h2><?php echo escape(!$searching ? $lang['plugins-installedheader'] : $lang['plugins-search-results-header']); ?></h2>
    <?php
    if ($searching) {
        $all_plugins = array_merge($inst_plugins, $plugins_avail);

        foreach ($all_plugins as $plugin) {
            if (!findPluginFromSearch($plugin, $find)) {
                continue;
            }
            RenderPlugin($plugin, isset($plugin["inst_version"]));
        }
        ?>
        <form id="anc-post" method="post" action="<?php echo $baseurl_short; ?>pages/team/team_plugins.php">
            <?php generateFormToken("anc_post"); ?>
            <input type="hidden" id="anc-input" name="" value="" />
        </form>
        </div> <!-- end of BasicBox -->
        <?php
        include "../../include/footer.php";
        exit();
    }

    if (count($inst_plugins) > 0) {
        foreach ($inst_plugins as $p) {
            if ($searching && !findPluginFromSearch($p, $find)) {
                continue;
            }
            RenderPlugin($p);
        }
    } else {
        echo "<p>" . escape($lang['plugins-noneinstalled']) . "</p>";
    } ?>

    <h2 class="pageline clearerleft"><?php echo escape($lang['plugins-availableheader']); ?></h2>
    <?php

    if (count($plugins_avail) > 0) {
        $plugin_categories = array();
        foreach ($plugins_avail as $p) {
            if ($searching && !findPluginFromSearch($p, $find)) {
                continue;
            }

            if (isset($p["category"])) {
                $p["category"] = trim($p["category"]);
                #Check for category lists
                if (preg_match("/.*,.*/", $p["category"])) {
                    $p_cats = explode(",", $p["category"]);
                    foreach ($p_cats as $p_cat) {
                        $p_cat = trim(strtolower($p_cat));
                        if (!isset($plugin_categories[$p_cat])) {
                            $plugin_categories[$p_cat] = array();
                        }
                        array_push($plugin_categories[$p_cat], $p);
                    }
                } else {
                    if (!isset($plugin_categories[$p["category"]])) {
                        $plugin_categories[$p["category"]] = array();
                    }

                    array_push($plugin_categories[$p["category"]], $p);
                }
            }
        }

        function display_plugin_category($plugins, $category)
        {
            global $lang;
            ?>
            <div class="plugin-category-container">
                <?php $category_name = isset($lang["plugin_category_{$category}"]) ? $lang["plugin_category_{$category}"] : $category; ?>
                <h3 class="CollapsiblePluginListHead collapsed clearerleft"><?php echo escape($category_name); ?></h3>
                <div class="CollapsiblePluginList" style="display: none;">
                    <?php foreach ($plugins as $plugin) {
                        RenderPlugin($plugin, isset($plugin["inst_version"]));
                    } ?>
                </div>
            </div>
            <?php
        }

        # Category Specific plugins
        ksort($plugin_categories);
        foreach ($plugin_categories as $category => $plugins_to_show) {
            display_plugin_category($plugins_to_show, $category);
        }

        ?>

        <script>
            jQuery(".CollapsiblePluginListHead").click(function() {
                if (jQuery(this).hasClass("collapsed")) {
                    jQuery(this).removeClass("collapsed");
                    jQuery(this).addClass("expanded");
                    jQuery(this).siblings(".CollapsiblePluginList").slideDown('fast');
                } else {
                    jQuery(this).removeClass("expanded");
                    jQuery(this).addClass("collapsed");
                    jQuery(this).siblings(".CollapsiblePluginList").slideUp('fast');
                }
            });
            jQuery(".CollapsiblePluginList").hide();
        </script>

        <?php
    } else {
        echo ",p>" . escape($lang['plugins-noneavailable']) . "</p>";
    }
    ?>
</div>

<form id="anc-post" method="post" action="<?php echo $baseurl_short?>pages/team/team_plugins.php">
    <?php generateFormToken("anc_post"); ?>
    <input type="hidden" id="anc-input" name="" value=""/>
</form>

<?php
include "../../include/footer.php";

