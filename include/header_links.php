
<nav aria-label="<?php echo escape($lang['mainmenu']) ?>">
    <ul id="HeaderLinksContainer">
        <?php if (!$use_theme_as_home && !$use_recent_as_home) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/home.php" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo  DASH_ICON . escape($lang["dash"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php hook("topnavlinksafterhome"); ?>

        <?php if ($advanced_search_nav) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/search_advanced.php" onClick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-zoom-in"></i>
                    <?php echo escape($lang["advancedsearch"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php if ($search_results_link) { ?>
            <li class="HeaderLink">
            <?php if ((checkperm("s")) &&  ((isset($_COOKIE["search_form_submit"]) )   || (isset($_COOKIE["search"]) && strlen($_COOKIE["search"]) > 0) || (isset($search) && (strlen($search) > 0) && (strpos($search, "!") === false)))) { # active search present ?>
                    <a href="<?php echo $baseurl?>/pages/search.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-search"></i>
                        <?php echo escape($lang["searchresults"]); ?>
                    </a>
            <?php } else { ?>
                <a class="SearchResultsDisabled">
                    <i aria-hidden="true" class="icon-search"></i>
                    <?php echo escape($lang["searchresults"]); ?>
                </a>
            <?php } ?>
            </li>
        <?php } ?>

        <?php if (checkperm("s") && $enable_themes && !$theme_direct_jump && $themes_navlink) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/collections_featured.php" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo FEATURED_COLLECTION_ICON . escape($lang["themes"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php if (checkperm("s") && ($public_collections_top_nav)) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/collection_public.php" onClick="return CentralSpaceLoad(this,true);">
                    <i class="icon-shopping-bag"></i>
                    <?php echo escape($lang["publiccollections"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php if (checkperm("s") && $mycollections_link && !checkperm("b")) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/collection_manage.php" onClick="return CentralSpaceLoad(this,true);">
                    <i class="icon-shopping-bag"></i>
                    <?php echo escape($lang["mycollections"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php if (checkperm("s") && $recent_link) {
            if ($recent_search_by_days) {
                $recent_url_params = [
                    "search"            => "",
                    "recentdaylimit"    => $recent_search_by_days_default,
                ];
            } else {
                $recent_url_params = [
                    "search"            => "!last" . $recent_search_quantity
                ];
            }
            $recent_url_params["order_by"]  = "resourceid";
            $recent_url_params["sort"]      = "desc";
            $recenturl = generateURL("$baseurl/pages/search.php", $recent_url_params);
            ?>
            <li class="HeaderLink">
                <a href="<?php echo $recenturl ?>" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo RECENT_ICON . escape($lang["recent"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php if (checkperm("s") && $myrequests_link && checkperm("q")) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/requests.php" onClick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-shopping-cart"></i>
                    <?php echo escape($lang["myrequests"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php if (checkperm("d") || ($mycontributions_link && checkperm("c"))) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/contribute.php" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo CONTRIBUTIONS_ICON . escape($lang["mycontributions"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php if (($research_request) && ($research_link) && (checkperm("s")) && (checkperm("q"))) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/research_request.php" onClick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-circle-question-mark"></i>
                    <?php echo escape($lang["researchrequest"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php
        /* ------------ Customisable top navigation ------------------- */
        if (isset($custom_top_nav)) {
            for ($n = 0; $n < count($custom_top_nav); $n++) {
                if (!is_safe_url($custom_top_nav[$n]['link'])) {
                    debug("Unsafe link detected in configuration - {$custom_top_nav[$n]['link']}");
                    continue;
                }

                // External links should open in a new tab
                if (!url_starts_with($baseurl, $custom_top_nav[$n]['link'])) {
                    $on_click = '';
                    $target   = ' target="_blank"';
                }
                    //Internal links can still open in the same tab
                else {
                    if (isset($custom_top_nav[$n]['modal']) && $custom_top_nav[$n]['modal']) {
                        $on_click = ' onClick="return ModalLoad(this, true);"';
                        $target   = '';
                    } elseif (!isset($custom_top_nav[$n]['modal']) || (isset($custom_top_nav[$n]['modal']) && !$custom_top_nav[$n]['modal'])) {
                        $on_click = ' onClick="return CentralSpaceLoad(this, true);"';
                        $target   = '';
                    }
                }
                if (strpos($custom_top_nav[$n]['title'], '(lang)') !== false) {
                    $custom_top_nav_title = str_replace("(lang)", "", $custom_top_nav[$n]["title"]);
                    $custom_top_nav[$n]["title"] = $lang[$custom_top_nav_title];
                }
                ?>
                <li class="HeaderLink">
                    <a href="<?php echo $custom_top_nav[$n]["link"]; ?>"<?php echo $target . $on_click; ?>><?php
                        // Not escaping to allow links to have an icon (if applicable)
                        echo strip_tags_and_attributes(i18n_get_translated($custom_top_nav[$n]["title"]));
                    ?></a>
                </li>
                <?php
            }
        } ?>

        <?php if ($help_link) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/help.php" onClick="return <?php if (!$help_modal) {
                    ?>CentralSpaceLoad(this,true);<?php
                        } else {
                                ?>ModalLoad(this,true);<?php
                        } ?>">
                    <?php echo HELP_ICON . escape($lang["helpandadvice"]); ?>
                </a>
            </li>
        <?php } ?>

        <?php global $nav2contact_link; if ($nav2contact_link) { ?>
            <li class="HeaderLink">
                <a href="<?php echo $baseurl?>/pages/contact.php" onClick="return CentralSpaceLoad(this,true);">
                    <?php echo escape($lang["contactus"]); ?>
                </a>
            </li>
        <?php }

        hook("toptoolbaradder"); ?>

    </ul><!-- close HeaderLinksContainer -->
</nav>

<script>
    jQuery(document).ready(function() {
        headerLinksDropdown();
    });
</script>