<?php
hook("before_footer_always");

if (getval("loginmodal", "")) {
    $login_url = $baseurl . "/login.php?url=" . urlencode(getval("url", "")) . "&api=" . urlencode(getval("api", "")) . "&error=" . urlencode(getval("error", "")) . "&auto=" . urlencode(getval("auto", "")) . "&nocookies=" . urlencode(getval("nocookies", "")) . "&logout=" . urlencode(getval("logout", true));
    ?><script>
        jQuery(document).ready(function(){
            ModalLoad('<?php echo $login_url?>',true);
        });
    </script>
    <?php
}

# Complete rendering of footer controlled elements and closure divs on full page load (ie. ajax is "")
# This rendering is bypassed when dynamically loading content into CentralSpace (ajax is "true")
if (getval("ajax", "") == "" && !hook("replace_footer")) {
    hook("beforefooter");
    if (!in_array($pagename, ["login","user_password"])) {
        ?>
        </div><!--End CentralSpaceFC-->
        </div><!--End CentralSpaceContainerFC-->
        <?php
    }
    ?>
    <!-- Footer closures -->
    <div class="clearer"></div>

    <!-- Use aria-live assertive for high priority changes in the content: -->
    <span role="status" aria-live="assertive" class="ui-helper-hidden-accessible"></span>
    <div class="clearerleft"></div>
    <div class="clearer"></div>
    <?php hook("footertop");

    if ($pagename == "login") {
        ?>
        <!--Global Footer-->
        <div id="Footer">
        <div class="ResponsiveViewFullSite">
            <a href="#" onClick="SetCookie('ui_view_full_site', true, 1, true); location.reload();"><?php echo escape($lang['responsive_view_full_site']); ?></a>
        </div>

        <?php
        if (!hook("replace_footernavrightbottom")) {
            ?>
            <div id="FooterNavRightBottom"><?php echo strip_tags_and_attributes(text("footer"), ['a'], ['href']); ?></div>
            <?php
        }
        ?>
        <div class="clearer"></div>
        </div>
        <?php
    }

    echo $extrafooterhtml;
} // end ajax

/* always include the below as they are perpage */
if (($pagename != "login") && ($pagename != "user_password") && ($pagename != "user_request")) {?>
    </div><!--End CentralSpacePP-->
    </div><!--End CentralSpaceContainerPP-->
    </div><!--End UICenterPP -->
    <?php
}

hook("footerbottom");
draw_performance_footer();

//titlebar modifications

if ($show_resource_title_in_titlebar) {
    $general_title_pages = array("admin_content","team_archive","team_resource","team_user","team_request","team_research","team_plugins","team_mail","team_export","team_stats","team_report","research_request","team_user_edit","admin_content_edit","team_request_edit","team_research_edit","requests","edit","themes","collection_public","collection_manage","team_home","help","home","tag","upload_java_popup","upload_java","contact","geo_search","search_advanced","about","contribute","user_preferences","view_shares","check","index");
    $search_title_pages = array("contactsheet_settings","search","collection_edit","edit","collection_download","collection_share","collection_request");
    $resource_title_pages = array("view","delete","log","alternative_file","alternative_files","resource_email","edit","preview");
    $additional_title_pages = array(hook("additional_title_pages_array"));
    $title = "";
    // clear resource or search title for pages that don't apply:
    if (!in_array($pagename, array_merge($general_title_pages, $search_title_pages, $resource_title_pages)) && (empty($additional_title_pages) || !in_array($pagename, $additional_title_pages))) {
        echo "<script language='javascript'>\n";
        echo "document.title = \"$applicationname\";\n";
        echo "</script>";
    }
    // place resource titles
    elseif (in_array($pagename, $resource_title_pages) && !isset($_GET['collection']) && !isset($_GET['java'])) { /* for edit page */
        if (isset($ref)) {
            $title =  str_replace('"', "''", i18n_get_translated(get_data_by_field($ref, $view_title_field)));
        }
        echo "<script type=\"text/javascript\" language='javascript'>\n";

        if ($pagename == "edit") {
            $title = $lang['action-edit'] . " - " . $title;
        }

        echo "document.title = \"$applicationname - $title\";\n";
        echo "</script>";
    }

    // place collection titles
    elseif (in_array($pagename, $search_title_pages)) {
        $collection = getval("ref", "");
        if (isset($search_title)) {
            $title = str_replace('"', "''", $lang["searchresults"] . " - " . html_entity_decode(strip_tags($search_title)));
        } else {
            $collectiondata = get_collection($collection);
            $title = strip_tags(str_replace('"', "''", i18n_get_collection_name($collectiondata)));
        }
        // add a hyphen if title exists
        if (strlen($title) != 0) {
            $title = "- $title";
        }
        if ($pagename == "edit") {
            $title = " - " . $lang['action-editall'] . " " . $title;
        }
        if ($pagename == "collection_share") {
            $title = " - " . $lang['share'] . " " . $title;
        }
        if ($pagename == "collection_edit") {
            $title = " - " . $lang['action-edit'] . " " . $title;
        }
        if ($pagename == "collection_download") {
            $title = " - " . $lang['download'] . " " . $title;
        }
        echo "<script language='javascript'>\n";
        echo "document.title = \"$applicationname $title\";\n";
        echo "</script>";
    }

      // place page titles
    elseif (in_array($pagename, $general_title_pages)) {
        if (isset($lang[$pagename])) {
            $pagetitle = $lang[$pagename];
        } elseif (isset($lang['action-' . $pagename])) {
            $pagetitle = $lang["action-" . $pagename];
            if (getval("java", "") != "") {
                $pagetitle = $lang['upload'] . " " . $pagetitle;
            }
        } elseif (isset($lang[str_replace("_", "", $pagename)])) {
            $pagetitle = $lang[str_replace("_", "", $pagename)];
        } elseif ($pagename == "admin_content") {
            $pagetitle = $lang['managecontent'];
        } elseif ($pagename == "collection_public") {
            $pagetitle = $lang["publiccollections"];
        } elseif ($pagename == "collection_manage") {
            $pagetitle = $lang["mycollections"];
        } elseif ($pagename == "team_home") {
            $pagetitle = $lang["teamcentre"];
        } elseif ($pagename == "help") {
            $pagetitle = $lang["helpandadvice"];
        } elseif (strpos($pagename, "upload") !== false) {
            $pagetitle = $lang["upload"];
        } elseif ($pagename == "contact") {
            $pagetitle = $lang["contactus"];
        } elseif ($pagename == "geo_search") {
            $pagetitle = $lang["geographicsearch"];
        } elseif ($pagename == "search_advanced") {
            $pagetitle = $lang["advancedsearch"];
            if (getval("archive", "") == 2) {
                $pagetitle .= " - " . $lang['archiveonlysearch'];
            }
        } elseif ($pagename == "about") {
            $pagetitle = $lang["aboutus"];
        } elseif ($pagename == "contribute") {
            $pagetitle = $lang["mycontributions"];
        } elseif ($pagename == "user_preferences") {
            $pagetitle = $lang["user-preferences"];
        } elseif ($pagename == "requests") {
            $pagetitle = $lang["myrequests"];
        } elseif ($pagename == "team_resource") {
            $pagetitle = $lang["manageresources"];
        } elseif ($pagename == "team_archive") {
            $pagetitle = $lang["managearchiveresources"];
        } elseif ($pagename == "view_shares") {
            $pagetitle = $lang["shared_collections"];
        } elseif ($pagename == "team_user") {
            $pagetitle = $lang["manageusers"];
        } elseif ($pagename == "team_request") {
            $pagetitle = $lang["managerequestsorders"];
        } elseif ($pagename == "team_research") {
            $pagetitle = $lang["manageresearchrequests"];
        } elseif ($pagename == "team_plugins") {
            $pagetitle = $lang["pluginmanager"];
        } elseif ($pagename == "team_mail") {
            $pagetitle = $lang["sendbulkmail"];
        } elseif ($pagename == "team_export") {
            $pagetitle = $lang["exportdata"];
        } elseif ($pagename == "team_stats") {
            $pagetitle = $lang["viewstatistics"];
        } elseif ($pagename == "team_report") {
            $pagetitle = $lang["viewreports"];
        } elseif ($pagename == "check") {
            $pagetitle = $lang["installationcheck"];
        } elseif ($pagename == "index") {
            $pagetitle = $lang["systemsetup"];
        } elseif ($pagename == "team_user_edit") {
            $pagetitle = $lang["edituser"];
        } elseif ($pagename == "admin_content_edit") {
            $pagetitle = $lang["editcontent"];
        } elseif ($pagename == "team_request_edit") {
            $pagetitle = $lang["editrequestorder"];
        } elseif ($pagename == "team_research_edit") {
            $pagetitle = $lang["editresearchrequest"];
        } else {
            $pagetitle = "";
        }
        if (strlen($pagetitle) != 0) {
            $pagetitle = "- $pagetitle";
        }
        echo "<script language='javascript'>\n";
        echo "document.title = \"$applicationname $pagetitle\";\n";
        echo "</script>";
    }
    hook("additional_title_pages");
}

if (isset($onload_message["text"])) {?>
    <script>
    jQuery(document).ready(function()
        {
        styledalert(<?php echo (isset($onload_message["title"]) ? json_encode($onload_message["title"]) : "''") . "," . json_encode($onload_message["text"]) ;?>);
        });
    </script>
    <?php
}
if (getval("ajax", "") == "") {
    // don't show closing tags if we're in ajax mode
    echo "<!--CollectionDiv-->";
    $omit_collectiondiv_load_pages = array("login","user_request","user_password","index");

    $more_omit_collectiondiv_load_pages = hook("more_omit_collectiondiv_load_pages");
    if (is_array($more_omit_collectiondiv_load_pages)) {
        $omit_collectiondiv_load_pages = array_merge($omit_collectiondiv_load_pages, $more_omit_collectiondiv_load_pages);
    }
    ?></div>

    <?php # Work out the current collection (if any) from the search string if external access

    if (
        isset($k)
        && $k != ""
        && isset($search)
        && !isset($usercollection)
        && substr($search, 0, 11) == "!collection"
    ) {
            // Search may include extra terms after a space so need to make sure we extract only the ID
            $searchparts = explode(" ", substr($search, 11));
            $usercollection = trim($searchparts[0]);
    }
    ?>
    <script>
    <?php
    if (!isset($usercollection)) {?>
        usercollection='';
        <?php
    } else {?>
        usercollection='<?php echo escape($usercollection) ?>';
        <?php
    } ?>
    </script><?php
    if (!hook("replacecdivrender")) {
        $col_on = !in_array($pagename, $omit_collectiondiv_load_pages) && !checkperm("b") && isset($usercollection);
        if ($col_on) {
            // Footer requires restypes as a string because it is urlencoding them
            if (isset($restypes) && is_array($restypes)) {
                $restypes = implode(',', $restypes);
            }
            ?>
            <div id="CollectionDiv" class="CollectBack AjaxCollect ui-layout-south"></div>

            <script type="text/javascript">
            var collection_frame_height=<?php echo COLLECTION_FRAME_HEIGHT ?>;
            var thumbs="<?php echo escape($thumbs); ?>";                                  
            function ShowThumbs()
                {
                myLayout.sizePane("south", collection_frame_height);
                jQuery('.ui-layout-south').animate({scrollTop:0}, 'fast');
                jQuery('#CollectionMinDiv').hide();
                jQuery('#CollectionMaxDiv').show();
                SetCookie('thumbs',"show",1000);
                ModalCentre();
                if(typeof chosenCollection !== 'undefined' && chosenCollection)
                    {
                    jQuery('#CollectionMaxDiv select').chosen({disable_search_threshold:chosenCollectionThreshold});
                    }
                }
            function HideThumbs()
                {
                myLayout.sizePane("south", 40);
                jQuery('.ui-layout-south').animate({scrollTop:0}, 'fast');          
                jQuery('#CollectionMinDiv').show();
                jQuery('#CollectionMaxDiv').hide();
                SetCookie('thumbs',"hide",1000);
                ModalCentre();

                if(typeof chosenCollection !== 'undefined' && chosenCollection)
                    {
                    jQuery('#CollectionMinDiv select').chosen({disable_search_threshold:chosenCollectionThreshold});
                    }
                }
            function ToggleThumbs()
                {
                thumbs = getCookie("thumbs");
                if (thumbs=="show")
                    {
                HideThumbs();
                    }
                else
                    { 
                    ShowThumbs();
                    }
                }
            function InitThumbs()
                {
                if(thumbs!="hide")
                    {
                    ShowThumbs();
                    }
                else if(thumbs=="hide")
                    {
                    HideThumbs();
                    }
                }

            jQuery(document).ready(function()
                {
                CollectionDivLoad('<?php echo generateURL($baseurl_short . 'pages/collections.php', ['thumbs' => $thumbs, 'k' => $k ?? '', 'order_by' => $order_by ?? '', 'sort' => $sort ?? '', 'search' => $search ?? '', 'archive' => $archive ?? '', 'daylimit' => $daylimit ?? '', 'offset' => $offset ?? '', 'resource_count' => $resource_count ?? '']) ?>&collection='+usercollection);
                InitThumbs();
                });

            </script>
            <?php
        } // end omit_collectiondiv_load_pages
        else {
            ?>
            <script>
            jQuery(document).ready(function()
                {
                ModalCentre();
                });
            </script>
            <?php
        }
        ?>
        <script type="text/javascript">
        var resizeTimer;
        myLayout=jQuery('body').layout(
            {
            livePaneResizing:true,
            triggerEventsDuringLiveResize: false,
            resizerTip: '<?php echo escape($lang["resize"]); ?>',

            east__spacing_open:0,
            east__spacing_closed:8,
            east_resizable: true,
            east__closable: false,
            east__size: 295,

            north_resizable: false,
            north__closable:false,
            north__spacing_closed: 0,
            north__spacing_open: 0,

            <?php
            if ($col_on) {?>
                south__resizable:true,
                south__minSize:40,
                south__spacing_open:8,
                south__spacing_closed:8, 
                south__togglerLength_open:"200",
                south__togglerTip_open: '<?php echo escape($lang["toggle"]); ?>',
                south__onclose_start: function(pane)
                    {
                    if (pane=="south" && (typeof colbarresizeon === "undefined" || colbarresizeon==true))
                        {
                        if(jQuery('.ui-layout-south').height()>40 && thumbs!="hide")
                            {
                            HideThumbs();
                            }
                        else if(jQuery('.ui-layout-south').height()<=40 && thumbs=="hide")
                            {
                            ShowThumbs();
                            }
                        return false;
                        }
                    ModalCentre();
                    },
                south__onresize: function(pane)
                    {
                    if (pane=="south" && (typeof colbarresizeon === "undefined" || colbarresizeon==true))
                        {
                        thumbs = getCookie("thumbs");
                        if(jQuery('.ui-layout-south').height() < collection_frame_height && thumbs!="hide")
                            {
                            HideThumbs();
                            }
                        else if(jQuery('.ui-layout-south').height()> 40 && thumbs=="hide")
                            {
                            ShowThumbs();
                            }
                        }
                    ModalCentre();
                    },
                <?php
            } else {?>                
                south__initHidden: true,
                <?php
            }

            ?>
            });
        </script>
        <?php
    }

    if (!hook("responsive_footer")) {
        ?>
        <!-- Responsive -->
        <script src="<?php echo $baseurl_short; ?>js/responsive.js?css_reload_key=<?php echo $css_reload_key; ?>"></script>
        <script>
        function toggleSimpleSearch()
            {
            if(jQuery("#searchspace").hasClass("ResponsiveSimpleSearch"))
                {
                jQuery("#searchspace").removeClass("ResponsiveSimpleSearch");
                jQuery("#SearchBarContainer").removeClass("FullSearch");
                jQuery("#Rssearchexpand").val("<?php echo escape($lang["responsive_more"]);?>");
                jQuery('#UICenter').show(0);
                search_show = false;
                }
            else
                {
                jQuery("#searchspace").addClass("ResponsiveSimpleSearch");
                jQuery("#SearchBarContainer").addClass("FullSearch");
                jQuery("#Rssearchexpand").val(" <?php echo escape($lang["responsive_less"]);?> ");
                jQuery('#UICenter').hide(0);
                search_show = true;
                }
            }

        /* Responsive Stylesheet inclusion based upon viewing device */
        if(document.createStyleSheet)
            {
            document.createStyleSheet('<?php echo $baseurl ;?>/css/responsive/slim-style.css?rcsskey=<?php echo $css_reload_key; ?>');
            }
        else
            {
            jQuery("head").append("<link rel='stylesheet' href='<?php echo $baseurl ;?>/css/responsive/slim-style.css?rcsskey=<?php echo $css_reload_key; ?>' type='text/css' media='screen' />");
            }

        if(!is_touch_device() && jQuery(window).width() <= 1280)
            {
            if(document.createStyleSheet)
                {
                document.createStyleSheet('<?php echo $baseurl; ?>/css/responsive/slim-non-touch.css?rcsskey=<?php echo $css_reload_key; ?>');
                }
            else
                {
                jQuery("head").append("<link rel='stylesheet' href='<?php echo $baseurl; ?>/css/responsive/slim-non-touch.css?rcsskey=<?php echo $css_reload_key; ?>' type='text/css' media='screen' />");
                }
            }

        var responsive_show = "<?php echo escape($lang['responsive_collectiontogglehide']);?>";
        var responsive_hide;
        var responsive_newpage = true;

        if(jQuery(window).width() <= 1100)
            {
            jQuery('.ResponsiveViewFullSite').css('display', 'block');
            SetCookie("selected_search_tab", "search");
            }
        else
            {
            jQuery('.ResponsiveViewFullSite').css('display', 'none');
            }

        if(jQuery(window).width()<=700)
            {
            touchScroll("UICenter");
            }

        var lastWindowWidth = jQuery(window).width();

        jQuery(window).resize(function()
            {
            // Check if already resizing
            if(typeof rsresize !== 'undefined')
                {
                return;
                }

            newwidth = jQuery( window ).width();

            if(lastWindowWidth > 1100 && newwidth < 1100)
                {
                // Set flag to prevent recursive loop
                rsresize = true;
                selectSearchBarTab('search');
                rsresize = undefined;
                }
            else if(lastWindowWidth > 900 && newwidth < 900)
                {
                rsresize = true;
                console.log("hiding collections");
                hideMyCollectionsCols();
                responsiveCollectionBar();
                jQuery('#CollectionDiv').hide(0);
                rsresize = undefined;
                }
            else if(lastWindowWidth < 900 && newwidth > 900)
                {
                rsresize = true;
                showResponsiveCollection();
                rsresize = undefined;
                }

            lastWindowWidth = newwidth;            
            });

        jQuery("#HeaderNav1Click").click(function(event)
            {
            event.preventDefault();
            if(jQuery(this).hasClass("RSelectedButton"))
                {
                jQuery(this).removeClass("RSelectedButton");
                jQuery("#HeaderNav1").slideUp(0);
                jQuery("#Header").removeClass("HeaderMenu");
                }
            else
                {
                jQuery("#HeaderNav2Click").removeClass("RSelectedButton");
                jQuery("#HeaderNav2").slideUp(80);              
                jQuery("#Header").addClass("HeaderMenu");               
                jQuery(this).addClass("RSelectedButton");
                jQuery("#HeaderNav1").slideDown(80);
                }
            if(jQuery("#searchspace").hasClass("ResponsiveSimpleSearch"))
                {
                toggleSimpleSearch();
                }      
            });

        jQuery("#HeaderNav2Click").click(function(event)
            {
            event.preventDefault();
            if(jQuery(this).hasClass("RSelectedButton"))
                {
                jQuery(this).removeClass("RSelectedButton");
                jQuery("#HeaderNav2").slideUp(0);
                jQuery("#Header").removeClass("HeaderMenu");

                }
            else
                {
                jQuery("#Header").addClass("HeaderMenu");
                jQuery("#HeaderNav1Click").removeClass("RSelectedButton");
                jQuery("#HeaderNav1").slideUp(80);
                jQuery(this).addClass("RSelectedButton");
                jQuery("#HeaderNav2").slideDown(80);
                } 
            if(jQuery("#searchspace").hasClass("ResponsiveSimpleSearch"))
                {
                toggleSimpleSearch();
                }  
            });

        jQuery("#HeaderNav2").on("click","a",function()
            {
            if(jQuery(window).width() <= 1200)
                {
                jQuery("#HeaderNav2").slideUp(0);
                jQuery("#HeaderNav2Click").removeClass("RSelectedButton");
                }
            });
        jQuery("#HeaderNav1").on("click","a",function()
            {
            if(jQuery(window).width() <= 1200)
                {
                jQuery("#HeaderNav1").slideUp(00);
                jQuery("#HeaderNav1Click").removeClass("RSelectedButton");
                }
            });
        jQuery("#SearchBarContainer").on("click","#Rssearchexpand",toggleSimpleSearch);

        if(jQuery(window).width() <= 700 && jQuery(".ListviewStyle").length && is_touch_device())
            {
            jQuery("td:last-child,th:last-child").hide();
            }
        </script>
        <!-- end of Responsive -->
        <?php
    }

    hook('afteruilayout');
    ?>
    <!-- Start of modal support -->
    <div id="modal_overlay" onClick="ModalClose();"></div>
    <div id="modal_outer">
    <div id="modal" tabindex="0">
    </div>
    </div>
    <div id="modal_dialog" style="display:none;"></div>
    <script type="text/javascript">
    jQuery(window).bind('resize.modal', ModalCentre);
    </script>
    <!-- End of modal support -->

    <script>

    try
        {
        top.history.replaceState(document.title+'&&&'+jQuery('#CentralSpace').html(), applicationname);
        }
    catch(e){console.log(e);
    }

    </script>

    <script>

        /* Destroy tagEditor if below breakpoint window size (doesn't work in responsize mode */

        window_width = jQuery(window).width();
        window_width_breakpoint = 1100;
        simple_search_pills_view = <?php echo $simple_search_pills_view ? "true" : "false"; ?>

        /* Page load */

        if(window_width <= window_width_breakpoint && simple_search_pills_view == true)
            {
            jQuery('#ssearchbox').tagEditor('destroy');
            }

        /* Page resized to below breakpoint */

        jQuery(window).resize(function() 
            {
            window_width = jQuery(window).width();
            if(window_width <= window_width_breakpoint && simple_search_pills_view == true)
                {
                jQuery('#ssearchbox').tagEditor('destroy');
                }
            });

    </script>

    </body>
    </html><?php
} // end if !ajax


