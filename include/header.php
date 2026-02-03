<?php
hook("preheaderoutput");

$k = getval("k", "");
if (!isset($internal_share_access)) {
    // Set a flag for logged in users if $external_share_view_as_internal is set and logged on user is accessing an external share
    $internal_share_access = internal_share_access();
}

$logout = getval("logout", "");
$loginas = getval("loginas", "");

# Do not display header / footer when dynamically loading CentralSpace contents.
$ajax = getval("ajax", "");

// Force full page reload if CSS or JS has been updated
$current_css_reload = getval("css_reload_key", 0, true);

if ($ajax != "" && $current_css_reload != 0 && $current_css_reload != $css_reload_key) {
    http_response_code(205);
    $return["error"] = array(
        "status" => 205,
        "title"  => escape($lang["error-reload-required"]),
    );
    echo json_encode($return);
    exit();
}

rs_setcookie("css_reload_key", $css_reload_key);

$noauth_page = in_array(
    $pagename,
    [
        "login",
        "user_change_password",
        "user_request",
        "done",
    ]
);

if ($ajax == "") {
    if (!isset($thumbs) && ($pagename != "login") && ($pagename != "user_password") && ($pagename != "user_request")) {
        $thumbs = getval("thumbs", "unset");
        if ($thumbs == "unset") {
            $thumbs = $thumbs_default;
            rs_setcookie("thumbs", $thumbs, 1000, "", "", false, false);
        }
    }

$page_title = get_page_title($pagename, pluginname());
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $language; ?>">   
    <!--
    ResourceSpace version <?php echo $productversion; ?>
    For copyright and license information see /documentation/licenses/resourcespace.txt
    https://www.resourcespace.com
    -->
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta http-equiv="CACHE-CONTROL" content="NO-CACHE">
        <meta http-equiv="PRAGMA" content="NO-CACHE">
        <?php if ($search_engine_noindex || (getval("k", "") != "" && $search_engine_noindex_external_shares)) { ?>
            <meta name="robots" content="noindex,nofollow">
        <?php } ?>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="pagename" content="<?php echo escape($pagename); ?>" />
        <meta name="pluginname" content="<?php echo escape(pluginname()); ?>" />
        <?php hook('extra_meta'); ?>

        <title id="page-title"><?php echo escape($page_title); ?></title>

        <link rel="icon" type="image/png" href="<?php echo get_favicon_url(); ?>" />

        <!-- Load jQuery and jQueryUI -->
        <script src="<?php echo $baseurl . $jquery_path; ?>?css_reload_key=<?php echo $css_reload_key; ?>"></script>
        <script src="<?php echo $baseurl . $jquery_ui_path; ?>?css_reload_key=<?php echo $css_reload_key; ?>" type="text/javascript"></script>
        <link type="text/css" href="<?php echo $baseurl; ?>/css/smoothness/jquery-ui.min.css?css_reload_key=<?php echo $css_reload_key; ?>" rel="stylesheet" />
        <script src="<?php echo $baseurl; ?>/lib/js/jquery.ui.touch-punch.min.js"></script>

        <?php if ($pagename == "login") { ?>
            <script type="text/javascript" src="<?php echo $baseurl; ?>/lib/js/jquery.capslockstate.js"></script>
        <?php } ?>

        <script type="text/javascript" src="<?php echo $baseurl; ?>/lib/js/jquery.tshift.min.js"></script>
        <script type="text/javascript" src="<?php echo $baseurl; ?>/lib/js/jquery-periodical-updater.js"></script>

        <script type="text/javascript">StaticSlideshowImage=<?php echo $static_slideshow_image ? "true" : "false"; ?>;</script>
        <script type="text/javascript" src="<?php echo $baseurl; ?>/js/slideshow_big.js?css_reload_key=<?php echo $css_reload_key; ?>"></script>

        <?php if ($contact_sheet) { ?>
            <script type="text/javascript" src="<?php echo $baseurl; ?>/js/contactsheet.js"></script>
            <script>contactsheet_previewimage_prefix = '<?php echo escape($storageurl); ?>';</script>
            <script type="text/javascript">jQuery.noConflict();</script>
        <?php } ?>

        <script type="text/javascript">
            var ProcessingCSRF=<?php echo generate_csrf_js_object('processing'); ?>;
            var ajaxLoadingTimer=<?php echo $ajax_loading_timer; ?>;
        </script>

        <script src="<?php echo $baseurl; ?>/js/ajax_collections.js?css_reload_key=<?php echo $css_reload_key; ?>" type="text/javascript"></script>
        <script src="<?php echo $baseurl; ?>/lib/tinymce/tinymce.min.js" referrerpolicy="origin"></script>

        <!--  UPPY -->
        <script type="text/javascript" src="<?php echo $baseurl_short;?>lib/js/uppy.js?<?php echo $css_reload_key; ?>"></script>
        <link rel="stylesheet" href="<?php echo $baseurl; ?>/css/uppy.min.css?css_reload_key=<?php echo $css_reload_key; ?>">

        <?php if ($keyboard_navigation_video_search || $keyboard_navigation_video_view || $keyboard_navigation_video_preview) { ?>
            <script type="text/javascript" src="<?php echo $baseurl_short?>js/videojs-extras.js?<?php echo $css_reload_key; ?>"></script>
            <?php
        }

        if ($simple_search_pills_view) { ?>
            <script src="<?php echo $baseurl_short; ?>lib/jquery_tag_editor/jquery.caret.min.js"></script>
            <script src="<?php echo $baseurl_short; ?>lib/jquery_tag_editor/jquery.tag-editor.min.js"></script>
            <link type="text/css" rel="stylesheet" href="<?php echo $baseurl_short; ?>lib/jquery_tag_editor/jquery.tag-editor.css" />
            <?php
        }
        ?>

        <!-- Chart.js for graphs -->
        <script language="javascript" type="module" src="<?php echo $baseurl_short; ?>lib/js/chartjs-4-4-0.js"></script>
        <script language="javascript" type="module" src="<?php echo $baseurl_short; ?>lib/js/date-fns.js"></script>
        <script language="javascript" type="module" src="<?php echo $baseurl_short; ?>lib/js/chartjs-adapter-date-fns.js"></script>

        <!-- jsTree -->
        <link rel="stylesheet" href="<?php echo $baseurl_short; ?>lib/jstree/themes/default-dark/style.min.css">
        <script src="<?php echo $baseurl_short; ?>lib/jstree/jstree.min.js"></script>
        <script src="<?php echo $baseurl_short; ?>js/category_tree.js?css_reload_key=<?php echo $css_reload_key; ?>"></script>

        <!-- DOMPurify -->
        <script src="<?php echo $baseurl; ?>/lib/js/purify.min.js?reload_key=<?php echo (int) $css_reload_key; ?>"></script>

        <!-- toastify-js -->
        <script src="<?php echo $baseurl; ?>/lib/toastify-js/src/toastify.js"></script>
        <link type="text/css" href="<?php echo $baseurl; ?>/lib/toastify-js/src/toastify.css" rel="stylesheet" />

        <?php
        global $not_authenticated_pages;
        $not_authenticated_pages = array('login', 'user_change_password','user_password','user_request');

        if (isset($GLOBALS['modify_header_not_authenticated_pages']) && is_array($GLOBALS['modify_header_not_authenticated_pages'])) {
            $not_authenticated_pages = array_filter($GLOBALS['modify_header_not_authenticated_pages']);
        }

        $browse_on = has_browsebar();
        if ($browse_on) {
            ?>
            <script src="<?php echo $baseurl_short; ?>js/browsebar_js.php" type="text/javascript"></script>
            <?php
        }
        $selected_search_tab = getval("selected_search_tab", "");
        ?>

        <script type="text/javascript">
            var baseurl_short="<?php echo $baseurl_short; ?>";
            var baseurl="<?php echo $baseurl; ?>";
            var pagename="<?php echo $pagename; ?>";
            var errorpageload = "<h1><?php echo escape($lang["error"]); ?></h1><p><?php echo escape(str_replace(array("\r","\n"), '', nl2br($lang["error-pageload"]))); ?></p>";
            var errortext = "<?php echo escape($lang["error"]); ?>";
            var applicationname = "<?php echo $applicationname; ?>";
            var pagetitle = "<?php echo escape($page_title); ?>";
            var plugintitle = "<?php echo escape(pluginname()); ?>";
            var branch_limit=false;
            var branch_limit_field = new Array();
            var global_trash_html = '<!-- Global Trash Bin (added through CentralSpaceLoad) -->';
            var TileNav = <?php echo $tilenav ? "true" : "false"; ?>;
            var errornotloggedin = '<?php echo escape($lang["error_not_logged_in"]); ?>';
            var login = '<?php echo escape($lang["login"]); ?>';
            <?php echo "global_trash_html += '" . render_trash("trash", "", true) . "';\n"; ?>
            oktext="<?php echo escape($lang["ok"]) ?>";
            var scrolltopElementCentral='#UICenter';
            var scrolltopElementContainer='body';
            var scrolltopElementCollection='#CollectionDiv';
            var scrolltopElementModal='#modal';
            <?php
            if ($browse_on) {
                echo "browse_clicked = false;";
            }
            ?>
        </script>

        <script src="<?php echo $baseurl_short?>js/global.js?css_reload_key=<?php echo $css_reload_key?>" type="text/javascript"></script>
        <script src="<?php echo $baseurl_short?>lib/js/polyfills.js?css_reload_key=<?php echo $css_reload_key; ?>"></script>

        <?php
        if ($keyboard_navigation) {
            include __DIR__ . "/keyboard_navigation.php";
        }

        hook("additionalheaderjs");

        echo $headerinsert;
        $extrafooterhtml = "";
        ?>

        <!--- Lucide for icons-->
        <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/lucide/lucide.css?css_reload_key=<?php echo $css_reload_key; ?>">
        <!-- Structure Stylesheet -->
        <link href="<?php echo $baseurl; ?>/css/global.css?css_reload_key=<?php echo $css_reload_key; ?>" rel="stylesheet" type="text/css" media="screen,projection,print" />
        <!-- Colour stylesheet -->
        <link href="<?php echo $baseurl; ?>/css/light.css?css_reload_key=<?php echo $css_reload_key; ?>" rel="stylesheet" type="text/css" media="screen,projection,print" />
        <!-- Override stylesheet -->
        <link href="<?php echo $baseurl; ?>/css/css_override.php?k=<?php echo escape($k); ?>&css_reload_key=<?php echo $css_reload_key; ?>&noauth=<?php echo $noauth_page; ?>" rel="stylesheet" type="text/css" media="screen,projection,print" />
        <!-- Load specified font CSS -->
        <?php if (!isset($custom_font) || $custom_font == '') { ?>
            <link id="global_font_link" href="<?php echo $baseurl; ?>/css/fonts/<?php echo $global_font; ?>.css?css_reload_key=<?php echo $css_reload_key; ?>" rel="stylesheet" type="text/css" />
        <?php } ?>
        <!-- Web app manifest -->
        <link rel="manifest" href="<?php echo $baseurl . escape($web_app_manifest_location); ?>">

        <?php
        if (!$disable_geocoding) {
            // Geocoding & leaflet maps
            // Load Leaflet and plugin files.
            ?>
            <!--Leaflet.js files-->
            <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/leaflet/leaflet.css?css_reload_key=<?php echo $css_reload_key; ?>"/>
            <script src="<?php echo $baseurl; ?>/lib/leaflet/leaflet.js?<?php echo $css_reload_key; ?>"></script>

            <?php
            if ($geo_leaflet_maps_sources) { ?>
                <!--Leaflet Providers v1.10.2 plugin files-->
                <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-providers-1.10.2/leaflet-providers.js"></script>
                <?php
            } else {
                header_add_map_providers();
            }
            ?>

            <!--Leaflet PouchDBCached v1.0.0 plugin file with PouchDB v7.1.1 file-->
            <?php if ($map_default_cache || $map_layer_cache) { ?>
                <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/pouchdb-7.1.1/pouchdb-7.1.1.min.js"></script>
                <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-PouchDBCached-1.0.0/L.TileLayer.PouchDBCached.min.js"></script>
            <?php } ?>

            <!--Leaflet MarkerCluster v1.4.1 plugin files-->
            <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-markercluster-1.4.1/dist/MarkerCluster.css"/>
            <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-markercluster-1.4.1/dist/MarkerCluster.Default.css"/>

            <!--Leaflet ColorMarkers v1.0.0 plugin file-->
            <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-colormarkers-1.0.0/js/leaflet-color-markers.js"></script>

            <!--Leaflet NavBar v1.0.1 plugin files-->
            <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-NavBar-1.0.1/src/Leaflet.NavBar.css"/>
            <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-NavBar-1.0.1/src/Leaflet.NavBar.min.js"></script>

            <!--Leaflet Omnivore v0.3.1 plugin file-->
            <?php if ($map_kml) { ?>
                <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-omnivore-0.3.4/leaflet-omnivore.min.js"></script>
            <?php } ?>

            <!--Leaflet EasyPrint v2.1.9 plugin file-->
            <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-easyPrint-2.1.9/dist/bundle.min.js"></script>

            <!--Leaflet StyledLayerControl v5/16/2019 plugin files-->
            <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-StyledLayerControl-5-16-2019/css/styledLayerControl.css"/>
            <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-StyledLayerControl-5-16-2019/src/styledLayerControl.min.js"></script>

            <!--Leaflet Zoomslider v0.7.1 plugin files-->
            <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-zoomslider-0.7.1/src/L.Control.Zoomslider.css"/>
            <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-zoomslider-0.7.1/src/L.Control.Zoomslider.min.js"></script>

            <!--Leaflet Shades v1.0.2 plugin files-->
            <link rel="stylesheet" href="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-shades-1.0.2/src/css/leaflet-shades.css"/>
            <script src="<?php echo $baseurl; ?>/lib/leaflet_plugins/leaflet-shades-1.0.2/leaflet-shades.js"></script>

            <?php
        }

        echo get_plugin_css();
        // after loading these tags we change the class on them so a new set can be added before they are removed (preventing flickering of overridden theme)
        ?>

        <script>jQuery('.plugincss').attr('class','plugincss0');</script>
    </head>

    <body lang="<?php echo escape($language); ?>">
        <div class="slide slide-active"></div>
        <div class="slide"></div>
        <a href="#UICenter" class="skip-to-main-content"><?php echo escape($lang["skip-to-main-content"]); ?></a>

        <!-- Processing graphic -->
        <div id='ProcessingBox' style='display: none'>
            <i aria-hidden="true" class="icon-settings lucide--spin"></i>
            <p id="ProcessingStatus"></p>
        </div>

        <!--Global Header-->
        <?php
        if (($pagename == "terms") && (getval("url", "") == "index.php")) {
            $loginterms = true;
        } else {
            $loginterms = false;
        }

        if ($pagename != "preview") {
            // Standard header
            $homepage_url = $baseurl . "/pages/home.php";
            if ($use_theme_as_home) {
                $homepage_url = $baseurl . "/pages/collections_featured.php";
            }

            if ($use_recent_as_home) {
                $homepage_url = $baseurl . "/pages/search.php?search=" . urlencode('!last' . $recent_search_quantity);
            }

            if ($pagename == "login" || $pagename == "user_request" || $pagename == "user_password") {
                $homepage_url = $baseurl . "/index.php";
            }

            # Calculate Header Image Display
            if (isset($usergroup)) {
                //Get group logo value
                $curr_group = get_usergroup($usergroup);
                if (!empty($curr_group["group_specific_logo"])) {
                    $linkedheaderimgsrc = (isset($storageurl) ? $storageurl : $baseurl . "/filestore") . "/admin/groupheaderimg/group" . $usergroup . "." . $curr_group["group_specific_logo"];
                }
                if (!empty($curr_group["group_specific_logo_dark"])) {
                    $linkedheaderimgsrc_dark = (isset($storageurl) ? $storageurl : $baseurl . "/filestore") . "/admin/groupheaderimg/group" . $usergroup . "_dark." . $curr_group["group_specific_logo_dark"];
                }
            }

            $linkUrl = isset($header_link_url) ? $header_link_url : $homepage_url;
            ?>

            <div id="Header" class="<?php
                echo in_array($pagename, $not_authenticated_pages) ? ' LoginHeader ' : '';
                echo isset($slimheader_darken) && $slimheader_darken ? 'slimheader_darken' : ''; ?>"
            >
                <div id="HeaderResponsive">
                    <?php
                    $header_img_src = get_header_image(false, true);

                    if ($header_link && ($k == "" || $internal_share_access)) { ?>
                        <a href="<?php echo $linkUrl; ?>" onclick="return CentralSpaceLoad(this,true);" class="HeaderImgLink">
                            <img src="<?php echo $header_img_src; ?>" id="HeaderImg" alt="<?php echo $applicationname; ?>">
                        </a>
                        <?php
                    } else {
                        ?>
                        <div class="HeaderImgLink">
                            <img src="<?php echo $header_img_src; ?>" id="HeaderImg" alt="<?php echo $applicationname; ?>">
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <?php
                hook("headertop");

                if (!isset($allow_password_change)) {
                    $allow_password_change = true;
                }

                if (isset($username) && !in_array($pagename, $not_authenticated_pages) && !$loginterms && '' == $k || $internal_share_access) {
                    ?>
                    <div id="HeaderNav2" class="HorizontalNav HorizontalWhiteNav">
                        <?php
                        if (!($pagename == "terms" && isset($_SERVER["HTTP_REFERER"]) && strpos($_SERVER["HTTP_REFERER"], "login") !== false && $terms_login)) {
                            include __DIR__ . "/header_links.php";
                        }
                        ?>
                    </div>

                    <div id="HeaderNav1" class="HorizontalNav">
                        <?php
                        if (checkPermission_anonymoususer()) {
                            if (!hook("replaceheadernav1anon")) {
                                ?>
                                <ul>
                                    <li>
                                        <a href="<?php echo $baseurl; ?>/login.php"><?php echo escape($lang["login"]); ?></a>
                                    </li>
                                    <?php
                                    hook("addtoplinksanon");
                                    
                                    if ($contact_link) {
                                        ?>
                                        <li>
                                            <a href="<?php echo $baseurl; ?>/pages/contact.php" onclick="return CentralSpaceLoad(this,true);">
                                                <?php echo escape($lang["contactus"]); ?>
                                            </a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                </ul>
                                <?php
                            } /* end replaceheadernav1anon */
                        } else {
                            ?>
                            <ul>
                                <?php
                                if (
                                    (
                                        ($top_nav_upload && checkperm("c"))
                                        || ($top_nav_upload_user && checkperm("d"))
                                    )
                                    && ($useracceptedterms == 1 || !$terms_login)
                                ) {
                                    $topuploadurl = get_upload_url("", $k);
                                    ?>
                                    <li class="HeaderLink UploadButton">
                                        <a href="<?php echo $topuploadurl; ?>" onclick="return CentralSpaceLoad(this,true);">
                                            <?php echo UPLOAD_ICON . escape($lang["upload"]); ?>
                                        </a>
                                    </li>
                                    <?php
                                }
                                ?>

                                <li title="<?php echo escape($lang["mymessages-tooltip"]); ?>">
                                    <a href="<?php echo $baseurl; ?>/pages/user/user_messages_quick.php" onclick="ModalClose(); return ModalLoad(this, true, false, 'rightnarrow');">
                                        <i aria-hidden="true" class="icon-mail lucide-lg"></i>
                                        <span class="MessageCountPill Pill" style="display: none;"></span>
                                    </a>
                                </li>

                                <li>
                                    <a href="<?php echo $baseurl; ?>/pages/user/user_home.php" onclick="ModalClose(); return ModalLoad(this, true, true, 'right');" alt="<?php echo escape($lang['myaccount']); ?>" title="<?php echo escape($lang['myaccount']); ?>">
                                        <?php
                                        $user_profile_image = get_profile_image($userref, false);

                                        if (isset($header_include_username) && $header_include_username) {
                                            if ($user_profile_image != "") {
                                                ?>
                                                <img src='<?php echo $user_profile_image; ?>' alt='Profile icon' class="ProfileImage" id='UserProfileImage'> &nbsp;<?php echo escape($userfullname == "" ? $username : $userfullname); ?>
                                                <?php
                                            } else {
                                                ?>
                                                <i aria-hidden="true" class="icon-user-round lucide-lg"></i>&nbsp;<?php echo escape($userfullname == "" ? $username : $userfullname) ?>
                                                <?php
                                            }
                                        } else {
                                            if ($user_profile_image != "") {
                                                ?>
                                                <img src='<?php echo $user_profile_image; ?>' alt='Profile icon' class="ProfileImage" id='UserProfileImage'>
                                                <?php
                                            } else {
                                                ?>
                                                <i aria-hidden="true" class="icon-user-round lucide-lg"></i>                                                
                                                <?php
                                            }
                                        }
                                        ?> 
                                        <span class="UserMenuCountPill Pill" style="display: none;"></span>
                                    </a>
                                    <div id="MessageContainer" style="position:absolute; "></div>
                                </li>

                                <!-- Admin menu link -->
                                <?php if (checkperm("t") && ($useracceptedterms == 1 || !$terms_login)) { ?>
                                    <li>
                                        <a href="<?php echo $baseurl; ?>/pages/team/team_home.php" onclick="ModalClose();return ModalLoad(this,true,true,'right');" alt="<?php echo escape($lang['teamcentre']); ?>" title="<?php echo escape($lang['teamcentre']); ?>">
                                            <i aria-hidden="true" class="icon-menu lucide-lg"></i>
                                            <?php
                                            if (!$actions_on && (checkperm("R") || checkperm("r"))) {
                                                # Show pill count if there are any pending requests
                                                $pending = ps_value("select sum(thecount) value from (select count(*) thecount from request where status = 0 union select count(*) thecount from research_request where status = 0) as theunion", array(), 0);
                                                ?>
                                                <span id="TeamMessages" class="Pill" <?php echo $pending > 0 ? 'data-value="' . $pending . '"' : 'style="display:none"'?>>
                                                    <?php echo $pending > 0 ? $pending : ''; ?>
                                                </span>
                                                <?php
                                            } else {
                                                ?>
                                                <span class="AdminMenuCountPill Pill" style="display:none"></span>
                                                <?php
                                            }
                                            ?>
                                        </a>
                                    </li>
                                    <?php
                                } ?>
                                <!-- End of Admin link -->
                            </ul>
                            <?php
                        }

                        include_once __DIR__ . '/../pages/ajax/message.php';
                        ?>
                    </div>

                    <?php
                } else {
                    # Empty Header
                    ?>
                    <div id="HeaderNav1" class="HorizontalNav ">&nbsp;</div>
                    <div id="HeaderNav2" class="HorizontalNav HorizontalWhiteNav">&nbsp;</div>
                    <?php
                }
        }
        ?>

        <div class="clearer"></div>

        <?php if ($pagename != "preview") { ?>
            </div>
        <?php } # End of header

        $omit_searchbar_pages = array(
            'index',
            'search_advanced',
            'preview',
            'admin_header',
            'login',
            'user_request',
            'user_password',
            'user_change_password',
            'document_viewer'
        );

        if ($pagename == "terms" && isset($_SERVER["HTTP_REFERER"]) && strpos($_SERVER["HTTP_REFERER"], "login") !== false && $terms_login) {
            array_push($omit_searchbar_pages, 'terms');
        }

        # if config set to display search form in header or (usergroup search permission omitted and anonymous login panel not to be displayed, then do not show simple search bar
        if (checkperm("s") || (is_anonymous_user() && $show_anonymous_login_panel)) {
            # Include simple search sidebar?

            if (isset($GLOBALS['modify_header_omit_searchbar_pages']) && is_array($GLOBALS['modify_header_omit_searchbar_pages'])) {
                $omit_searchbar_pages = array_filter($GLOBALS['modify_header_omit_searchbar_pages']);
            }

            if (!in_array($pagename, $omit_searchbar_pages) && !$loginterms && ($k == '' || $internal_share_access)) {
                ?>
                <div id="SearchBarContainer">
                    <?php include __DIR__ . "/searchbar.php"; ?>
                </div>
                <?php
            }
        }

        # Determine which content holder div to use
        if (
            $pagename == "login"
            || $pagename == "user_password"
            || $pagename == "user_request"
            || ($pagename == "user_change_password" && !is_authenticated())
        ) {
            $div = "CentralSpaceLogin";
            $uicenterclass = "NoSearch LoginPage";
        } else {
            $div = "CentralSpace";
            if (in_array($pagename, $omit_searchbar_pages)) {
                $uicenterclass = "NoSearch";
            } else {
                $uicenterclass = "Search";
            }
        }
        ?>

        <!--Main Part of the page-->

        <!-- Global Trash Bin -->
        <?php
        render_trash("trash", "");
        echo '<div id="UICenter" role="main" class="' . $uicenterclass . '">';
        hook('afteruicenter');

        if (!in_array($pagename, $not_authenticated_pages)) {
            echo '<div id="CentralSpaceContainer">';
        }
        ?>

        <div id="<?php echo $div; ?>">

        <?php
} // end if !ajax

// Update header links to add a class that indicates current location
// We parse URL for systems that are one level deep under web root
$parsed_url = parse_url($baseurl);

$scheme = @$parsed_url['scheme'];
$host   = @$parsed_url['host'];
$port   = (isset($parsed_url['port']) ? ":{$parsed_url['port']}" : "");

$activate_header_link = "{$scheme}://{$host}{$port}" . urlencode($_SERVER["REQUEST_URI"]);

if (!$disable_geocoding) {
    get_geolibraries();
}
?>

<script>
    // Set some vars for this page to enable/disable functionality
    linkreload = <?php echo ($k != "" || $internal_share_access) ? "false" : "true"; ?>;
    b_progressmsgs = <?php echo $noauth_page ? "false" : "true"; ?>;

    jQuery(document).ready(function() {
        ActivateHeaderLink(<?php echo json_encode($activate_header_link); ?>);

        <?php if (!$ajax) { ?>
            setThemePreference();
        <?php } ?>
        
        jQuery(document).mouseup(function(e)  {
            var linksContainer = jQuery("#DropdownCaret");
            if (linksContainer.has(e.target).length === 0 && !linksContainer.is(e.target))  {
                jQuery('#OverFlowLinks').hide();
            }
        });

        <?php if (isset($user_pref_appearance) && $user_pref_appearance == "device" && !$ajax) { ?>
            // Run on page load if using device default for appearance
            updateHeaderImage();

            // Listen for changes to colour scheme
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateHeaderImage);
        <?php } ?>
    });
    
    window.onresize = function() {
        ReloadLinks();
    }
</script>

<?php
// Non-ajax specific hook
if ($k != "" && !$internal_share_access) {
    ?>
    <style>
        #CentralSpaceContainer {
            padding-right: 0;
            margin: 0px 10px 20px 25px;
        }
    </style>
    <?php
}
