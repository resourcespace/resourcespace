<?php

include_once "../include/boot.php";
include "../include/authenticate.php";
include_once "../include/dash_functions.php";

# Fetch promoted collections ready for display later
$home_collections = get_home_page_promoted_collections();
$welcometext = false;

global $home_dash, $static_slideshow_image, $no_welcometext;

include "../include/header.php";

include "../include/home_slideshow.php";
if ($slideshow_configured || !$no_welcometext) {
    echo '<div id="hero_banner">';
    loadWelcomeText();
    echo '</div>';
}

function loadWelcomeText()
{
    global $no_welcometext, $home_dash, $productversion;
    if (!$no_welcometext) {
        ?>
        <div id="HomeSiteTextPanel">
            <div class=" <?php echo $home_dash ? 'dashtext' : ''; ?>" id="HomeSiteText">
                <div id="HomeSiteTextInner">
                    <h1>
                        <?php # Include version number
                        echo strip_tags_and_attributes(str_replace("[ver]", str_replace("SVN", "", $productversion), text("welcometitle")));
                        ?>
                    </h1>
                    <p><?php echo strip_tags_and_attributes(text("welcometext"), ['a'], ['href']); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
}

$welcometext = true;

if ($home_dash && !$welcometext) {
    loadWelcomeText();
    $welcometext = true;
}

hook('homeafterwelcometext');
hook("homebeforepanels");

?>

<div id="HomePanelContainer">
    <?php
    if ($home_themeheaders && $enable_themes) {
        if ($home_dash) {
            $title = "themeselector";
            $all_users = 1;
            $url = "pages/ajax/dash_tile.php?tltype=conf&tlstyle=thmsl";
            $link = "pages/collections_featured.php";
            $reload_interval = 0;
            $resource_count = 0;
            $default_order_by = 0;
            $delete = 0;
            if (!existing_tile($title, $all_users, $url, $link, $reload_interval, $resource_count)) {
                create_dash_tile($url, $link, $title, $reload_interval, $all_users, $default_order_by, $resource_count, "", $delete);
            }
        } else {
            $url = "{$baseurl_short}pages/collections_featured.php";
            ?>
            <div class="HomePanel">
                <div class="HomePanelIN HomePanelThemes <?php echo (count($home_collections) > 0) ?  "HomePanelMatchPromotedHeight" : ''; ?>">
                    <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/collections_featured.php">
                        <h2 style="padding: 0px 15px 0 44px;margin-top: 26px;margin-left: 15px;"><?php echo escape($lang["themes"]); ?></h2>
                    </a>
                    <p style="text-shadow: none;">
                        <select id="themeselect" onchange="CentralSpaceLoad(this.value,true);">
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php foreach (get_featured_collection_categories(0, array()) as $header) { ?>
                                <option value="<?php echo generateURL($url, array("parent" => $header["ref"])); ?>">
                                    <?php echo escape(i18n_get_translated($header["name"])); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <a id="themeviewall" onclick="return CentralSpaceLoad(this,true);" href="<?php echo $url; ?>">
                            <?php echo LINK_CARET . escape($lang["viewall"]); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php
        }
    }
    /* ------------ Customisable home page panels ------------------- */
    if (isset($custom_home_panels)) {
        for ($n = 0; $n < count($custom_home_panels); $n++) {
            if ($home_dash) {
                # Check Tile tile exists in dash already
                $title = i18n_get_translated($custom_home_panels[$n]["title"]);
                $all_users = 1;
                $url = "pages/ajax/dash_tile.php?tltype=conf&tlstyle=custm";
                $link = $custom_home_panels[$n]["link"];

                if (strpos($custom_home_panels[$n]['link'], 'pages/') === false) {
                    $link = 'pages/' . $custom_home_panels[$n]['link'];
                }

                if (strpos($custom_home_panels[$n]['link'], $baseurl . '/') !== false) {
                    $link = $custom_home_panels[$n]['link'];
                    $link = str_replace($baseurl . '/', '', $custom_home_panels[$n]['link']);
                }

                $text = $custom_home_panels[$n]["text"];
                $reload_interval = 0;
                $resource_count = 0;
                $default_order_by = 6;
                $delete = 0;

                if (!existing_tile($title, $all_users, $url, $link, $reload_interval, $resource_count, $text)) {
                    create_dash_tile($url, $link, $title, $reload_interval, $all_users, $default_order_by, $resource_count, $text, $delete);
                }
            } else {
                ?>
                <a
                    href="<?php echo $custom_home_panels[$n]["link"]; ?>"
                    <?php if (isset($custom_home_panels[$n]["additional"])) {
                        echo $custom_home_panels[$n]["additional"];
                    } ?>
                    class="HomePanel"
                >
                    <?php 
                        $tile_data = [
                            'title' => $custom_home_panels[$n]['title'],
                            'text'  => $custom_home_panels[$n]['text']
                        ];

                        tile_freetext($tile_data);
                    ?>
                </a>
                <?php
            }
        }
    }

    /* ------------ Collections promoted to the home page ------------------- */
    foreach ($home_collections as $home_collection) {
        if ($home_dash) {
            # Check Tile tile exists in dash already
            if (empty($home_collection["home_page_text"])) {
                $home_collection["home_page_text"] = $home_collection["name"];
            }

            if (strlen($home_collection["home_page_text"]) <= 12) {
                $title = ucfirst(i18n_get_translated($home_collection["home_page_text"]));
                $text = "";
            } else {
                $text = ucfirst(i18n_get_translated($home_collection["home_page_text"]));
                $title = "";
            }

            $all_users = 1;
            $url = "pages/ajax/dash_tile.php?tltype=srch&tlstyle=thmbs";
            $link = "/pages/search.php?search=!collection" . $home_collection["ref"] . "&order_by=relevance&sort=DESC";
            $reload_interval = 0;
            $resource_count = 0;
            $default_order_by = 7;
            $delete = 0;

            if (!existing_tile($title, $all_users, $url, $link, $reload_interval, $resource_count, $text)) {
                create_dash_tile($url, $link, $title, $reload_interval, $all_users, $default_order_by, $resource_count, $text, $delete);
                //Turn off the promoted collection
                ps_query("UPDATE collection SET home_page_publish = 0 WHERE ref = ?", array("i", $home_collection["ref"]));
            }
        } else {
            $defaultpreview = false;
            if (
                isset($home_collection["home_page_image"])
                && file_exists(get_resource_path($home_collection["home_page_image"], true, "pre", false))
            ) {
                $home_col_image = get_resource_path($home_collection["home_page_image"], false, "pre", false);
            } else {
                $defaultpreview = true;
                $home_col_image = $baseurl_short . "gfx/interface/dash_placeholder.svg";
            }

            $tile_height = 180;
            $tile_width = 250;
            $resource_data = get_resource_data($home_collection["home_page_image"]);
            ?>

            <a href="<?php echo $baseurl_short?>pages/search.php?search=!collection<?php echo $home_collection["ref"]; ?>" onclick="return CentralSpaceLoad(this,true);" class="HomePanel HomePanelPromoted">
                <div id="HomePanelPromoted<?php echo $home_collection["ref"]; ?>" class="HomePanelIN HomePanelPromotedIN">
                    <?php 
                        if ($defaultpreview) {
                            ?><div class="tile-placeholder"><?php
                        }
                    ?>
                    <img
                        alt="<?php echo escape(i18n_get_translated($resource_data['field' . $view_title_field] ?? "")); ?>"
                        src="<?php echo $home_col_image ?>" 
                        class="thmbs-tile-img"
                    />
                    <?php 
                        if ($defaultpreview) {
                            ?></div><?php
                        }
                    ?>
                    <div class="tile-desc">
                        <?php if (!empty($home_collection["home_page_text"])) { ?>
                            <p>
                                <?php echo i18n_get_translated($home_collection["home_page_text"]); ?>
                            </p>
                        <?php } else { ?>
                            <h2>
                                <?php echo i18n_get_translated($home_collection["name"]); ?>
                            </h2>
                        <?php } ?>
                    </div>
                </div>
            </a>
            <?php
        }
    }

    if ($home_dash && checkPermission_dashmanage()) {
        render_upgrade_available_tile($userref);
        get_user_dash($userref);
    } elseif ($home_dash && !checkPermission_dashmanage()) {
        get_managed_dash();
    }
    ?>

    <div style="clear:both;"></div>
</div> <!-- End HomePanelContainer -->

<div class="clearerleft"></div>

<?php
include "../include/footer.php";
