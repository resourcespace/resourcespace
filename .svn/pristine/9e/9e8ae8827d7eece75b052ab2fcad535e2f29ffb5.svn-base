<?php

# display collection title if option set.
$search_title = "";
$search_title_links = "";

global $baseurl_short, $filename_field, $archive_standard,$related_search_searchcrumb_field;

# Display a title of the search (if there is a title)
$searchcrumbs = "";
// Get search URL, resetting pager
$search_params = get_search_params();
$search_url = generateURL($baseurl_short . 'pages/search.php', $search_params, array("offset" => 0, "go" => ""));

if ($search_titles_searchcrumbs && $use_refine_searchstring) {
    $refinements = str_replace(" -", ",-", rawurldecode($search));
    $refinements = explode(",", $search);

    if (substr($search, 0, 1) == "!" && substr($search, 0, 6) != "!empty") {
        $startsearchcrumbs = 1;
    } else {
        $startsearchcrumbs = 0;
    }

    if ($refinements[0] != "") {
        for ($n = $startsearchcrumbs; $n < count($refinements); $n++) {
            # strip the first semi-colon so it's not swapped with an " OR "
            $semi_pos = strpos($refinements[$n], ":;");

            if ($semi_pos !== false) {
                $refinements[$n] = substr_replace($refinements[$n], ": ", $semi_pos, strlen(":;"));
            }

            $search_title_element = str_replace(";", " OR ", $refinements[$n]);
            $search_title_element = search_title_node_processing($search_title_element);
            if ($n != 0 || !$archive_standard) {
                $searchcrumbs .= " > </count> </count> </count> ";
            }

            $searchcrumbs .= "<a href=\"" . $baseurl_short . "pages/search.php?search=";

            for ($x = 0; $x <= $n; $x++) {
                $searchcrumbs .= urlencode($refinements[$x]);
                if ($x != $n && substr($refinements[$x + 1], 0) != "-") {
                    $searchcrumbs .= ",";
                }
            }


            $search_title_element = explode(":", search_title_node_processing($refinements[$n]));
            if (isset($search_title_element[1])) {
                $datefieldinfo = ps_query("select ref from resource_type_field where name=? and type IN (4,6,10)", array("s",trim($search_title_element[0])), "schema");

                if (count($datefieldinfo)) {
                    $search_title_element[1] = str_replace("|", "-", $search_title_element[1]);
                    $search_title_element[1] = str_replace("nn", "??", $search_title_element[1]);
                }

                if (!isset($cattreefields)) {
                    $cattreefields = array();
                }

                if (in_array($search_title_element[0], $cattreefields)) {
                    $search_title_element = $lang['fieldtype-category_tree'];
                } else {
                    $search_title_element = str_replace(";", " OR ", $search_title_element[1]);
                }
            } else {
                $search_title_element = $search_title_element[0];
            }


            if (substr(trim($search_title_element), 0, 6) == "!empty") {// superspecial !empty search
                $search_title_elementq = trim(str_replace("!empty", "", rawurldecode($search_title_element)));
                if (is_numeric($search_title_elementq)) {
                    $fref = $search_title_elementq;
                    $ftitle = ps_value("select title value from resource_type_field where ref=?", array("i",$search_title_elementq), "", "schema");
                } else {
                    $ftitleref = ps_query("select title,ref from resource_type_field where name=?", array("s",$search_title_elementq), "schema");
                    if (!isset($ftitleref[0])) {
                        exit("invalid !empty search. No such field: $search_title_elementq");
                    }
                    $ftitle = $ftitleref[0]['title'];
                    $fref = $ftitleref[0]['ref'];
                }
                if ($ftitle == "") {
                    exit("invalid !empty search");
                }
                $search_title_element = str_replace("%field", lang_or_i18n_get_translated($ftitle, "fieldtitle-"), $lang["untaggedresources"]);
            }

            $searchcrumbs .= "&amp;order_by=" . urlencode($order_by) . "&amp;sort=" . urlencode($sort) . "&amp;offset=" . urlencode($offset) . "&amp;archive=" . urlencode($archive) . "&amp;sort=" . urlencode($sort) . "\" onClick='return CentralSpaceLoad(this,true);'>" . $search_title_element . "</a>";
        }
    }
}

if ($search_titles) {
    if (substr($search, 0, 11) == "!collection") {
        $col_title_ua = "";
        // add a tooltip to Smart Collection titles (which provides a more detailed view of the searchstring.
        $alt_text = '';
        if ($pagename == "search" && isset($collectiondata['savedsearch']) && $collectiondata['savedsearch'] != '') {
            $smartsearch = ps_query("select " . columns_in("collection_savedsearch") . " from collection_savedsearch where ref=?", array("i",$collectiondata['savedsearch']));
            if (isset($smartsearch[0])) {
                $alt_text = "title='search=" . $smartsearch[0]['search'] . "&restypes=" . $smartsearch[0]['restypes'] . "&archive=" . $smartsearch[0]['archive'] . "'";
            }
        }

        hook("collectionsearchtitlemod");

        $collection_trail = array();
        $branch_trail = array();

        global $enable_themes;

        if (
            $enable_themes
            && isset($collectiondata) && $collectiondata !== false
            && $collectiondata["type"] == COLLECTION_TYPE_FEATURED
        ) {
            $general_url_params = ($k == "" ? array() : array("k" => $k));

            $collection_trail[] = array(
                "title" => $lang["themes"],
                "href"  => generateURL("{$baseurl_short}pages/collections_featured.php", $general_url_params)
            );

            $fc_branch_path = move_featured_collection_branch_path_root(
                // We ask for the branch up from the parent as we want to generate a different link for the actual collection.
                // If we were to use the $collectiondata["ref"] then the generated link for the collection would've pointed at
                // collections_featured.php which we don't want
                get_featured_collection_category_branch_by_leaf((int) $collectiondata["parent"], [])
            );

            $branch_trail = array_map(function ($branch) use ($baseurl_short, $general_url_params) {
                return array(
                    "title" => i18n_get_translated($branch["name"]),
                    "href"  => generateURL("{$baseurl_short}pages/collections_featured.php", $general_url_params, array("parent" => $branch["ref"])));
            }, $fc_branch_path);
        }

        $full_collection_trail = array_merge($collection_trail, $branch_trail);
        $full_collection_trail[] = array(
            "title" => i18n_get_collection_name($collectiondata) . $col_title_ua,
            "href"  => generateURL($baseurl_short . "pages/search.php", $search_params),
            "attrs" => array($alt_text),
        );

        ob_start();
        renderBreadcrumbs($full_collection_trail, '', 'BreadcrumbsBoxSlim BreadcrumbsBoxTheme');
        $renderBreadcrumbs = ob_get_contents();
        ob_end_clean();

        $renderBreadcrumbs = str_replace("</div></div>", "{$searchcrumbs}</div></div>", $renderBreadcrumbs);

        $search_title .= $renderBreadcrumbs;
    } elseif ($search == "" && $archive_standard) {
        # Which resource types (if any) are selected?
        $searched_types_refs_array = explode(",", $restypes); # Searched resource types and collection types
        $resource_types_array = get_resource_types("", false, false, true); # Get all resource types, untranslated
        $searched_resource_types_names_array = array();
        for ($n = 0; $n < count($resource_types_array); $n++) {
            if (in_array($resource_types_array[$n]["ref"], $searched_types_refs_array)) {
                $searched_resource_types_names_array[] = lang_or_i18n_get_translated($resource_types_array[$n]["name"], "resourcetype-", "-2");
            }
        }
        if (count($searched_resource_types_names_array) == count($resource_types_array)) {
            # All resource types are selected, don't list all of them
            unset($searched_resource_types_names_array);
            $searched_resource_types_names_array[0] = $lang["all-resourcetypes"];
        }

        # Which collection types (if any) are selected?
        $searched_collection_types_names_array = array();
        if (in_array("mycol", $searched_types_refs_array)) {
            $searched_collection_types_names_array[] = $lang["mycollections"];
        }
        if (in_array("pubcol", $searched_types_refs_array)) {
            $searched_collection_types_names_array[] = $lang["publiccollections"];
        }
        if (in_array("themes", $searched_types_refs_array)) {
            $searched_collection_types_names_array[] = $lang["themes"];
        }
        if (count($searched_collection_types_names_array) == 3) {
            # All collection types are selected, don't list all of them
            unset($searched_collection_types_names_array);
            $searched_collection_types_names_array[0] = $lang["all-collectiontypes"];
        }

        if (count($searched_resource_types_names_array) > 0 && count($searched_collection_types_names_array) == 0) {
            # Only (one or more) resource types are selected
            $searchtitle = $lang["all"] . " " . implode($lang["resourcetypes_separator"] . " ", $searched_resource_types_names_array);
        } elseif (count($searched_resource_types_names_array) == 0 && count($searched_collection_types_names_array) > 0) {
            # Only (one or more) collection types are selected
            $searchtitle = str_replace_formatted_placeholder("%collectiontypes%", $searched_collection_types_names_array, $lang["no_resourcetypes-collections"], false, $lang["collectiontypes_separator"]);
        } elseif (count($searched_resource_types_names_array) > 0 && count($searched_collection_types_names_array) > 0) {
            # Both resource types and collection types are selected
            # Step 1: Replace %resourcetypes%
            $searchtitle = $lang["all"] . " " . implode($lang["resourcetypes_separator"] . " ", $searched_resource_types_names_array);
            //$searchtitle = str_replace_formatted_placeholder("%resourcetypes%", $searched_resource_types_names_array, $lang["resourcetypes-collections"], false, //$lang["resourcetypes_separator"]);

            # Step 2: Replace %collectiontypes%
            $searchtitle = str_replace_formatted_placeholder("%collectiontypes%", $searched_collection_types_names_array, $searchtitle, false, $lang["collectiontypes_separator"]);
        } else {
            # No resource types and no collection types are selected � show all resource types and all collection types
            # Step 1: Replace %resourcetypes%
            $searchtitle = str_replace_formatted_placeholder("%resourcetypes%", $lang["all-resourcetypes"], $lang["resourcetypes-collections"], false, $lang["resourcetypes_separator"]);
            # Step 2: Replace %collectiontypes%
            $searchtitle = str_replace_formatted_placeholder("%collectiontypes%", $lang["all-collectiontypes"], $searchtitle, false, $lang["collectiontypes_separator"]);
        }

        $search_title = '<div class="BreadcrumbsBox BreadcrumbsBoxSlim BreadcrumbsBoxTheme"><div class="SearchBreadcrumbs"><a href="' . $baseurl_short . 'pages/search.php?search=" onClick="return CentralSpaceLoad(this,true);">' . escape($searchtitle) . '</a></div></div> ';
    } elseif (substr($search, 0, 1) == "!") {
        // Special searches
        if (substr($search, 0, 8) == "!related") {
            $resource = substr($search, 8);
            $resource = explode(",", $resource);
            $resource = $resource[0];
            $displayfield = get_data_by_field($resource, $related_search_searchcrumb_field);
            if ($displayfield == '') {
                $displayfield = get_data_by_field($resource, $filename_field);
            }
            $title_string = str_replace('%id%', $displayfield, $lang["relatedresources-id"]);
        } elseif (substr($search, 0, 7) == "!unused") {
            $title_string = $lang["uncollectedresources"];
        } elseif (substr($search, 0, 11) == "!duplicates") {
            $ref = explode(" ", $search);
            $ref = str_replace("!duplicates", "", $ref[0]);
            $ref = explode(",", $ref);// just get the number
            $ref = $ref[0];
            $filename = get_data_by_field($ref, $filename_field);
            if ($ref != "") {
                $title_string = $lang["duplicateresourcesfor"] . $filename ;
            } else {
                $title_string = $lang["duplicateresources"];
            }
        } elseif (substr($search, 0, 5) == "!list") {
            $resources = substr($search, 5);
            $resources = explode(",", $resources);
            $resources = $resources[0];
            $title_string = $lang["listresources"] . " " . $resources;
        } elseif (substr($search, 0, 15) == "!archivepending") {
            $title_string = $lang["resourcespendingarchive"];
        } elseif (substr($search, 0, 12) == "!userpending") {
            $title_string = $lang["userpending"];
        } elseif (substr($search, 0, 10) == "!nopreview") {
            $title_string = $lang["nopreviewresources"];
        } elseif (substr($search, 0, 4) == "!geo") {
            $title_string = "";
        } elseif (substr($search, 0, 14) == "!contributions") {
            $cuser = substr($search, 14);
            $cuser = explode(",", $cuser);
            $cuser = $cuser[0];

            if ($cuser == $userref) {
                switch ($archive) {
                    case -2:
                        $title_string = $lang["contributedps"];
                        break;
                    case -1:
                        $title_string = $lang["contributedpr"];
                        break;
                    case 0:
                        $title_string = $lang["contributedsubittedl"];
                        break;
                    default:
                        $title_string = $lang["mycontributions"];
                        break;
                }
            } else {
                $udata = get_user($cuser);
                if ($udata) {
                    $udisplayname = trim((string)$udata["fullname"]) != "" ? $udata["fullname"] : $udata["username"];
                    $title_string = $lang["contributedby"] . " " . $udisplayname . ((strpos($archive, ",") === false && !$archive_standard) ? " - " . $lang["status" . intval($archive)] : "");
                }
            }
        } elseif (substr($search, 0, 8) == "!hasdata") {
            $fieldref = intval(trim(substr($search, 8)));
            $fieldinfo = get_resource_type_field($fieldref);
            if (!$fieldinfo) { 
                if (isset($_GET['search'])) {
                    error_alert($lang['error_fieldidnotfound'],true);
                    exit;
                }
                error_alert($lang['error_fieldidnotfound'],false);
                exit;
            }
            $fdisplayname = trim((string)$fieldinfo["title"]) != "" ? $fieldinfo["title"] : $fieldref;
            $title_string = $lang["search_title_hasdata"] . " " . $fdisplayname;
        } elseif (substr($search, 0, 6) == "!empty") {
            $fieldref = intval(trim(substr($search, 6)));
            $fieldinfo = get_resource_type_field($fieldref);
            if (!$fieldinfo) { 
                if (isset($_GET['search'])) {
                    error_alert($lang['error_fieldidnotfound'],true);
                    exit;
                }
                error_alert($lang['error_fieldidnotfound'],false);
                exit;
            }
            $displayname = i18n_get_translated($fieldinfo["title"]);
            if (trim($displayname) == "") {
                $displayname = $fieldinfo["ref"];
            }
            $title_string = $lang["search_title_empty"] . ' ' . $displayname;
        } elseif (substr($search, 0, 14) == "!integrityfail") {
            $title_string = $lang["file_integrity_fail_search"];
        } elseif (substr($search, 0, 7) == "!locked") {
            $title_string = $lang["locked_resource_search"];
        }
        if (isset($title_string) && $title_string != "") {
            $search_title = '<div class="BreadcrumbsBox BreadcrumbsBoxSlim BreadcrumbsBoxTheme"><div class="SearchBreadcrumbs"><a href="' . $search_url . '" onClick="return CentralSpaceLoad(this,true);">' . escape($title_string) . '</a> ' . $searchcrumbs . '</div></div> ';
        }
    } elseif (!$archive_standard) {
        $title_strings = [];
        $wfstates = explode(",", $archive);
        foreach ($wfstates as $wfstate) {
            $title_strings[] = $lang["status" . $wfstate] ?? $lang["archive"] . ": " . $wfstate;
        }
        $search_title = '<div class="BreadcrumbsBox BreadcrumbsBoxSlim BreadcrumbsBoxTheme"><div class="SearchBreadcrumbs"><a href="' . $search_url . '" onClick="return CentralSpaceLoad(this,true);">' . escape(implode(", ", $title_strings)) . '</a>' . escape($searchcrumbs) . '</div></div> ';
    }

    hook("addspecialsearchtitle");
}

hook('add_search_title_links');

if (
    !hook("replacenoresourcesfoundsearchtitle")
    && !is_array($result)
    && empty($collections)
    && getval("addsmartcollection", "") == ''
) {
        $search_title = '<div class="BreadcrumbsBox BreadcrumbsBoxSlim BreadcrumbsBoxTheme"><div class="SearchBreadcrumbs"><a href="' . $search_url . '">' . $lang["noresourcesfound"] . '</a></div></div>';
}
