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
            <div id="CollectionDiv" class="CollectBack AjaxCollect"></div>

            <script type="text/javascript">
            var collection_frame_height=<?php echo COLLECTION_FRAME_HEIGHT ?>;
            var thumbs="<?php echo escape($thumbs); ?>";

            function ShowThumbs() {
                jQuery('body').addClass("collection-bar--maximised");
                jQuery('body').removeClass("collection-bar--minimised");

                jQuery('#CollectionMinDiv').hide();
                jQuery('#CollectionMaxDiv').show();

                SetCookie('thumbs',"show",1000);
                ModalCentre();
            }

            function HideThumbs() {
                jQuery('body').addClass("collection-bar--minimised");
                jQuery('body').removeClass("collection-bar--maximised");

                jQuery('#CollectionMinDiv').show();
                jQuery('#CollectionMaxDiv').hide();

                SetCookie('thumbs',"hide",1000);
                ModalCentre();
            }

            function ToggleThumbs() {
                thumbs = getCookie("thumbs");
                if (thumbs == "show") {
                    HideThumbs();
                } else { 
                    ShowThumbs();
                }
            }

            function InitThumbs() {
                if (thumbs != "hide") {
                    ShowThumbs();
                } else if (thumbs == "hide") {
                    HideThumbs();
                }
            }

            jQuery(document).ready(function() {
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

    </body>
    </html><?php
} // end if !ajax


