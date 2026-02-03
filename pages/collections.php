<?php
include_once __DIR__ . "/../include/boot.php";

# External access support (authenticate only if no key provided, or if invalid access key provided)
$k = getval("k", "");
if (($k == "") || (!check_access_key_collection(getval("collection", "", true), $k))) {
    include_once __DIR__ . "/../include/authenticate.php";
}

if (checkperm("b")) {
    exit($lang["error-permissiondenied"]);
}

include_once __DIR__ . "/../include/research_functions.php";

$sort            = getval('sort', 'ASC');
$search          = getval('search', '');
$last_collection = getval('last_collection', '');
$restypes        = getval('restypes', '');
$archive         = getval('archive', '');
$daylimit        = getval('daylimit', '');
$offset          = getval('offset', '');
$resources_count = getval('resources_count', '');
$collection      = getval('collection', '');
$entername       = getval('entername', '');
$res_access      = getval('access', '');

/*
IMPORTANT NOTE: Collections should always show their resources in the order set by a user (via sortorder column
in collection_resource table). This means that all pages order by 'relevance' and on search page only if we search
for this collection we can rely on the passed order by value.
*/
$order_by = $default_collection_sort;
if ('!collection' === substr($search, 0, 11) && "!collection{$collection}" == $search) {
    $order_by = getval('order_by', $default_collection_sort);
}

$change_col_url = "search=" . urlencode($search) . "&order_by=" . urlencode($order_by) . "&sort=" . urlencode($sort) . "&restypes=" . urlencode($restypes) . "&archive=" . urlencode($archive) . "&daylimit=" . urlencode($daylimit) . "&offset=" . urlencode($offset) . "&resources_count=" . urlencode($resources_count);

// Set a flag for logged in users if $external_share_view_as_internal is set and logged on user is accessing an external share
$internal_share_access = internal_share_access();

// Remove all from collection
$emptycollection = getval("emptycollection", "", true);
if ($emptycollection != '' && getval("submitted", "") == 'removeall' && getval("removeall", "") != "" && collection_writeable($emptycollection)) {
    remove_all_resources_from_collection($emptycollection);
}

if (!isset($thumbs)) {
    $thumbs = getval("thumbs", "unset");
    if ($thumbs == "unset") {
        $thumbs = $thumbs_default;
        rs_setcookie("thumbs", $thumbs, 1000, "", "", false, false);
    }
}

    # ------------ Change the collection, if a collection ID has been provided ----------------
if ($collection != "" && $collection != "undefined") {
    hook("prechangecollection");
    
    # Change current collection
    if (($k == "" || $internal_share_access) && $collection == "new") {
        # Create new collection
        if ($entername != "") {
            $name = $entername;
        } else {
            $name = "Default Collection";
        }

        $new = create_collection($userref, $name);
        set_user_collection($userref, $new);

        # Log this
        daily_stat("New collection", $userref);
    } elseif ((!isset($usercollection) || $collection != $usercollection) && $collection != 'false') {
        $validcollection = ps_value("select ref value from collection where ref=?", array("i",$collection), 0);
        # Switch the existing collection
        if ($k == "" || $internal_share_access) {
            set_user_collection($userref, $collection);
        }
        $usercollection = $collection;
    }

    hook("postchangecollection");
}

// Load collection info.
// get_user_collections moved before output as function may set cookies
$cinfo = get_collection($usercollection);
if (!is_array($cinfo)) {
    $cinfo = get_collection(get_default_user_collection(true));
}

if ('' == $k || $internal_share_access) {
    $list = get_user_collections($userref);
}

# if the old collection or new collection is being displayed as search results, we'll need to update the search actions so "save results to this collection" is properly displayed
if (substr($search, 0, 11) == '!collection' && ($k == '' || $internal_share_access)) {
    # Extract the collection number - this bit of code might be useful as a function
    $search_collection = explode(' ', $search);
    $search_collection = str_replace('!collection', '', $search_collection[0]);
    $search_collection = explode(',', $search_collection); // just get the number
    $search_collection = $search_collection[0];
    if ($search_collection == $last_collection || ($last_collection !== '' && $search_collection == $usercollection)) {
        ?>
        <script>            
            jQuery('.ActionsContainer.InpageNavLeftBlock').load(baseurl + "/pages/ajax/update_search_actions.php?<?php echo $change_col_url?>&collection=<?php echo $search_collection?>", function() {
                jQuery(this).children(':first').unwrap();
            });
        </script>
        <?php
    }
}

# Check to see if the user can edit this collection.
$allow_reorder = false;
if (($k == "" || $internal_share_access) && (($userref == $cinfo["user"]) || ($cinfo["allow_changes"] == 1) || (checkperm("h")))) {
    $allow_reorder = true;
}

# Reordering capability
if ($allow_reorder) {
    # Also check for the parameter and reorder as necessary.
    $reorder = getval("reorder", false);
    if ($reorder) {
        $neworder = json_decode(getval("order", false));
        update_collection_order($neworder, $usercollection);
        exit("SUCCESS");
    }
}

# Include function for reordering
if ($allow_reorder) {
    global $usersession;
    ?>

    <script type="text/javascript">
        function ReorderResourcesInCollection(idsInOrder) {
            var newOrder = [];
            jQuery.each(idsInOrder, function() {
                newOrder.push(this.substring(13));
            }); 

            jQuery.ajax({
                type: 'POST',
                url: '<?php echo $baseurl_short?>pages/collections.php?collection=<?php echo urlencode($usercollection) ?>&search=<?php echo urlencode($search)?>&reorder=true',
                data: {
                    order:JSON.stringify(newOrder),
                    <?php echo generateAjaxToken('reorder_collection'); ?>
                },
                success: function() {
                    /*
                    * Reload the top results if we're looking at the user's current collection.
                    * The !collectionX part may be urlencoded, or not, depending on how the page was reached.
                    */
                    var results = new RegExp('[\\?&amp;]' + 'search' + '=([^&amp;#]*)').exec(window.location.href);
                    var ref = new RegExp('[\\?&amp;]' + 'ref' + '=([^&amp;#]*)').exec(window.location.href);

                    if ((ref == null) && (results !== null) &&
                        ('<?php echo urlencode("!collection" . $usercollection); ?>' === results[1]
                        ||
                        '<?php echo "!collection" . (int) $usercollection; ?>' === results[1])

                        ) CentralSpaceLoad('<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!collection" . $usercollection); ?>',true);
                }
            });     
        }

        jQuery(document).ready(function() {
            if (is_touch_device()) {
                return false;
            }

            jQuery('#CollectionSpace').sortable({
                distance: 20,
                connectWith: '#CentralSpaceResources',
                appendTo: 'body',
                zIndex: 99000,
                helper: function(event, ui) {
                    //Hack to append the element to the body (visible above others divs), 
                    //but still belonging to the scrollable container
                    jQuery('#CollectionSpace').append('<div id="CollectionSpaceClone" class="ui-state-default">' + ui[0].outerHTML + '</div>');   
                    jQuery('#CollectionSpaceClone').hide();
                    setTimeout(function() {
                        jQuery('#CollectionSpaceClone').appendTo('body'); 
                        jQuery('#CollectionSpaceClone').show();
                    }, 1);
                    return jQuery('#CollectionSpaceClone');
                },
                items: '.CollectionPanelShell',

                start: function (event, ui) {
                    InfoBoxEnabled=false;
                    if (jQuery('#InfoBoxCollection')) {
                        jQuery('#InfoBoxCollection').hide();
                    }
                    jQuery('#trash_bin').show();
                },

                stop: function(event, ui) {
                    InfoBoxEnabled=true;
                    var idsInOrder = jQuery('#CollectionSpace').sortable("toArray");
                    ReorderResourcesInCollection(idsInOrder);
                    jQuery('#trash_bin').hide();
                }
            });

            jQuery('.CollectionPanelShell').disableSelection();
        });
    </script>

    <?php
} else {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery('.ui-sortable').sortable('disable');
            jQuery('.CollectionPanelShell').enableSelection();          
        }); 
    </script>
    <?php
}
?>

<!-- Drag and Drop -->
<script>
    jQuery('#CentralSpace').on('prepareDragDrop', function() {
        jQuery('#CollectionDiv').droppable({
            accept: '.ResourcePanel',

            drop: function(event, ui) {
                var query_strings = getQueryStrings();
                if (is_special_search('!collection', 11) && !is_empty(query_strings) && query_strings.search.substring(11) == usercollection) {
                    // No need to re-add this resource since we are looking at the same collection in both CentralSpace and CollectionDiv
                    return false;
                }

                var resource_id = jQuery(ui.draggable).attr("id");
                resource_id = resource_id.replace('ResourceShell', '');

                jQuery('#trash_bin').hide();
                // AddResourceToCollection includes a reload of CollectionDiv 
                AddResourceToCollection(event, ui, resource_id, '');
            }
        });

        jQuery('#trash_bin').droppable({
            accept: '.CollectionPanelShell, .ResourcePanel',
            activeClass: "ui-droppable-active ui-state-hover",
            hoverClass: "ui-state-active",

            drop: function(event, ui) {
                var resource_id = ui.draggable.attr("id");
                resource_id = resource_id.replace('ResourceShell', '');
                jQuery('#trash_bin_delete_dialog').dialog({
                    autoOpen: false,
                    modal: true,
                    resizable: false,
                    dialogClass: 'delete-dialog no-close',
                    open: function(event,ui) {
                        jQuery(this)
                            .closest(".ui-dialog")
                            .find(".ui-dialog-title")
                            .html("<?php echo escape($lang["trash_bin_delete_dialog_title"]) . "<br>(" . $lang["from"]; ?> " + jQuery(this).data('collection_name') + ")");
                    },
                    buttons: {
                        // Confirm removal of this resource from the resolved collection
                        "<?php echo escape($lang['yes']); ?>": function() {
                            var class_of_drag = jQuery(this).data('class_of_drag');
                            var resource_id = jQuery(this).data("resource_id");
                            var collection_id = jQuery(this).data('collection_id');
                            var collection_name = jQuery(this).data('collection_name');

                            if (collection_id == "") {
                                console.error('RS_debug: Unable to resolve from which collection drag and drop resource removal is being requested.');
                                jQuery(this).dialog('close');
                            }
                            // RemoveResourceFromCollection includes call to CollectionDivLoad

                            RemoveResourceFromCollection(event, resource_id, '<?php echo $pagename; ?>', collection_id);
                            // Remove resource from search results if this is not a collection search   
                            if (is_special_search('!collection', 11)) {
                                jQuery('#ResourceShell' + resource_id).fadeOut();
                            }
                            jQuery(this).dialog('close');
                        },
                        // Cancel resource removal
                        "<?php echo escape($lang['no']); ?>": function() {
                            var class_of_drag = jQuery(this).data('class_of_drag');
                            var resource_id = jQuery(this).data("resource_id");
                            var collection_id = jQuery(this).data('collection_id');
                            var collection_name = jQuery(this).data('collection_name');

                            if (collection_id == "") {
                                console.error('RS_debug: Unable to resolve which collection to reload following cancellation of resource removal.');
                                jQuery(this).dialog('close');
                            }

                            // If resource was dragged from the CollectionPanelShell then refresh the current collection within the CollectionDiv
                            if (class_of_drag.indexOf("CollectionPanelShell") >= 0) {
                                collection_id = jQuery("#collection").val();
                                CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection=' + collection_id);
                            }
                            jQuery(this).dialog('close');
                        }
                    }
                });

                // Resolve the collection depending on the origin of the resource been dragged
                var class_of_drag = ui.draggable.attr("class");
                var collection_id = "";
                var collection_name = "";

                // If resource is dragged from the ResourcePanel (ie. CentralSpace) then collection is in querystring
                if (class_of_drag.indexOf("ResourcePanel") >= 0) {
                    var query_strings = getQueryStrings();
                    if (!is_empty(query_strings)) {
                        collection_id = query_strings.search.substring(11);
                        // Collection name now stored in custom attribute
                        collection_name = jQuery("#CentralSpaceResources").attr("collectionsearchname").trim();
                    }
                }

                // If resource is dragged from the CollectionPanelShell then use the collection from within the CollectionDiv
                if (class_of_drag.indexOf("CollectionPanelShell") >= 0) {
                    collection_id = jQuery("#collection").val();
                    collection_name = jQuery("#collection option:selected").text().trim();
                }

                jQuery('#trash_bin').hide();

                // Cancel re-order in case it was triggered
                if (jQuery('#CentralSpace').hasClass('ui-sortable')) {
                    jQuery('#CentralSpace').sortable('cancel');
                }

                if (jQuery('#CollectionSpace').hasClass('ui-sortable')) {
                    jQuery('#CollectionSpace').sortable('cancel');
                }

                // Process drop request without dialog when resource is being dragged from bottom collection panel
                if (class_of_drag.indexOf("CollectionPanelShell") >= 0) {
                    // Handle different cases such as Saved searches
                    if (ui.draggable.data('savedSearch') === 'yes') {
                        CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?removesearch=' + resource_id + '&nc=<?php echo time(); ?>');
                    } else {
                        RemoveResourceFromCollection(event, resource_id, '<?php echo $pagename; ?>', collection_id, <?php echo htmlspecialchars(generate_csrf_js_object('remove_resource_from_collection'), ENT_NOQUOTES); ?>);
                        // Remove resource from search results if this is not a collection search   
                        if (is_special_search('!collection', 11)) {
                            jQuery('#ResourceShell' + resource_id).fadeOut();
                        }
                    }
                } else {
                    // Show confirmation dialog when resource is being dragged from top (ie. CentralSpace)
                    jQuery('#trash_bin_delete_dialog').data('class_of_drag',class_of_drag)
                        .data('resource_id',resource_id)
                        .data('collection_id',collection_id)
                        .data('collection_name',collection_name);

                    jQuery('#trash_bin_delete_dialog').dialog('open');
                }
            }
        });
    });

    jQuery(document).ready(function() {
        jQuery('#CentralSpace').trigger('prepareDragDrop');
    });

    registerResourceSelectDeselectHandlers();
</script>
<!-- End of Drag and Drop -->

</head>
<body class="CollectBack" id="collectbody">
<div style="display:none;" id="currentusercollection"><?php echo (int) $usercollection; ?></div>

<script>usercollection='<?php echo escape($usercollection) ?>';</script>

<?php

$addarray = array();
$add = getval("add", "");

if ($add != "") {
    $allowadd = true;
    // If we provide a collection ID use that one instead
    $to_collection = getval('toCollection', '');

    if (strpos($add, ",") > 0) {
        $addarray = explode(",", $add);
    } else {
        $addarray[0] = $add;
        unset($add);
    }

    // If collection has been shared externally need to check access and permissions
    $externalkeys = get_collection_external_access(($to_collection === '') ? $usercollection : $to_collection);
    if (count($externalkeys) > 0) {
        if (checkperm("noex")) {
            $allowadd = false;
        } else {
            foreach ($addarray as $add) {
                $resaccess = get_resource_access($add);
                // Not permitted if share is open and access is restricted
                if (min(array_column($externalkeys, "access")) < $resaccess) {
                    $allowadd = false;
                }
            }
        }
        if (!$allowadd) {
            ?>
            <script language="Javascript">styledalert("<?php echo escape($lang['error'])?>", "<?php echo escape($lang["sharedcollectionaddblocked"])?>");</script>
            <?php
        }
    } else {
        foreach ($addarray as $add) {
            $resaccess = get_resource_access($add);
            if ($resaccess > 1) {
                $allowadd = false;
            }
        }
    }

    if ($allowadd) {
        foreach ($addarray as $add) {
            #add to current collection
            if (
                $usercollection == -$userref
                || $to_collection == -$userref
                || !add_resource_to_collection($add, ($to_collection === '') ? $usercollection : $to_collection, false, getval("size", ""))
            ) {
                ?>
                <script language="Javascript">styledalert("<?php echo escape($lang['error'])?>","<?php echo escape($lang["cantmodifycollection"])?>");</script>
                <?php
            } else {
                # Log this
                daily_stat("Add resource to collection", $add);
            }
        }

        # Show warning?
        if (isset($collection_share_warning) && $collection_share_warning) {
            ?>
            <script language="Javascript">styledalert("<?php echo escape($lang['status-warning'])?>", "<?php echo escape($lang["sharedcollectionaddwarning"])?>");</script>
            <?php
        }
    } else {
        ?>
        <script language="Javascript">alert("<?php echo escape($lang["error-permissiondenied"])?>");</script>
        <?php
    }
}

$remove = getval("remove", "");

if ($remove != "") {
    // If we provide a collection ID use that one instead
    $from_collection = getval('fromCollection', '');

    if (strpos($remove, ",") > 0) {
        $removearray = explode(",", $remove);
    } else {
        $removearray[0] = $remove;
        unset($remove);
    }

    foreach ($removearray as $remove) {
        #remove from current collection
        if (!remove_resource_from_collection($remove, ($from_collection === '') ? $usercollection : $from_collection)) {
            ?>
            <script language="Javascript">styledalert("<?php echo escape($lang['error'])?>","<?php echo escape($lang["cantmodifycollection"])?>");</script>
            <?php
        } else {
            # Log this
            daily_stat("Removed resource from collection", $remove);
        }
    }
}

$addsearch = getval("addsearch", -1);

if ($addsearch != -1) {
    /*
    When adding search default collection sort should be relevance to address multiple types of searches. If collection
    is used then it will error if user did a simple search and not a !collection search since there is no collection
    sortorder
    */
    $default_collection_sort = 'relevance';
    $order_by = getval('order_by', getval('saved_order_by', $default_collection_sort));

    if ($usercollection == -$userref || !collection_writeable($usercollection)) {
        ?>
        <script language="Javascript">styledalert("<?php echo escape($lang['error'])?>","<?php echo escape($lang["cantmodifycollection"])?>");</script>
        <?php
    } else {
        $externalkeys = get_collection_external_access($usercollection);
        if (checkperm("noex") && count($externalkeys) > 0) {
            // If collection has been shared externally users with this permission can't add resources
            ?>
            <script language="Javascript">styledalert("<?php echo escape($lang['error'])?>", "<?php echo escape($lang["sharedcollectionaddblocked"])?>");</script>
            <?php
        } else {
            if (getval("mode", "") == "") {
                #add saved search
                add_saved_search($usercollection);

                # Log this
                daily_stat("Add saved search to collection", 0);
            } else {
                $foredit = (getval("foredit", false) == "true" ? true : false);
                #add saved search (the items themselves rather than just the query)
                $resourcesnotadded = add_saved_search_items($usercollection, $addsearch, $restypes, $archive, $order_by, $sort, $daylimit, $res_access, $foredit);

                if (!empty($resourcesnotadded)) {
                    $warningtext = "";
                    if (isset($resourcesnotadded["blockedtypes"])) {
                        // There are resource types blocked due to $collection_block_restypes
                        $warningtext = $lang["collection_restype_blocked"] . "<br /><br />";
                        $blocked_types = get_resource_types(implode(",", $resourcesnotadded["blockedtypes"]));

                        foreach ($blocked_types as $blocked_type) {
                            if ($warningtext == "") {
                                $warningtext .= "<ul>";
                            }
                            $warningtext .= "<li>" . $blocked_type["name"] . "</li>";
                        }

                        $warningtext .= "</ul>";
                        unset($resourcesnotadded["blockedtypes"]);
                    }

                    if (!empty($resourcesnotadded)) {
                        // There are resources blocked from being added due to archive state
                        if ($warningtext == "") {
                            $warningtext .= "<br /><br />";
                        }
                        $warningtext .= $lang["notapprovedresources"] . implode(", ", $resourcesnotadded);
                    }

                    ?>
                    <script language="Javascript">styledalert("<?php echo escape($lang["status-warning"]); ?>","<?php echo $warningtext; ?>",600);</script>
                    <?php
                }
                # Log this
                daily_stat("Add saved search items to collection", 0);
            }
        }
    }
}

$removesearch = getval("removesearch", "");
if ($removesearch != "") {
    if (!collection_writeable($usercollection)) {
        ?>
        <script language="Javascript">styledalert("<?php echo escape($lang['error'])?>", "<?php echo escape($lang["cantmodifycollection"])?>");</script>
        <?php
    } else {
        #remove saved search
        remove_saved_search($usercollection, $removesearch);
    }
}

$addsmartcollection = getval("addsmartcollection", -1);
if ($addsmartcollection != -1) {
    # add collection which autopopulates with a saved search
    add_smart_collection();

    # Log this
    daily_stat("Added smart collection", 0);
}

$research = getval("research", "");
if ($research != "") {
    $col = get_research_request_collection($research);

    if (!$col) {
        $rr = get_research_request($research);
        $name = "Research: " . $rr["name"];  # Do not translate this string, the collection name is translated when displayed!
        $new = create_collection($rr["user"], $name, 1);
        set_user_collection($userref, $new);
        set_research_collection($research, $new);
    } else {
        set_user_collection($userref, $col);
        # Add research request collection for collection bar actions and name fields.
        $cinfo = get_collection($col);
        $collection_refs = array();
        foreach ($list as $col_ref) {
            $collection_refs[] = $col_ref["ref"];
        }
        if (!in_array($col, $collection_refs)) {
            $list[] = $cinfo;
        }
    }
}

$searches = get_saved_searches($usercollection);

# When loading the collection bar from a collection just saved, then use the "collection" order established during that save
if ($addsearch != -1) {
    $default_collection_sort = 'collection';
}

$result  = do_search("!collection{$usercollection}", '', $default_collection_sort, 0, -1, "ASC", false, 0, false, false, '', false, true, false);
$count_result = count($result);
$feedback = $cinfo ? $cinfo["request_feedback"] : 0;
?>

<div>
<script>
    var collection_resources = <?php echo json_encode(array_column($result, 'ref'));?>; 
</script>

<div id="CollectionMaxDiv" style="display:<?php echo ($thumbs == 'show') ? 'block' : 'none'; ?>">
    <?php
    # ---------------------------- Maximised view ---------------------------------------------------------------------
    if ($k != "" && !$internal_share_access) {
        # ------------- Anonymous access, slightly different display ------------------
        $tempcol = $cinfo;
        ?>
        <div id="CollectionMenu">
            <h2><?php echo i18n_get_collection_name($tempcol)?></h2>
            <br />
            <div class="CollectionStatsAnon">
                <?php echo ($tempcol) ?  escape($lang["created"] . " " . nicedate($tempcol["created"])) : "" ?>
                <br />
                <?php echo escape($count_result . " " . $lang["youfoundresources"]); ?>
                <br />
            </div>

            <?php
            $min_access = collection_min_access($result);
            # Ability to download only if minimum access allows it
            if (
                (
                    isset($zipcommand)
                    || $collection_download
                )
                && $count_result > 0
                && count($result) > 0
                && $min_access == 0
            ) {
                if ($terms_download) {
                    if ($download_usage) {
                        $download_url = generateURL(
                            $baseurl_short . '/pages/terms.php',
                            [
                                'k' => $k,
                                'collection' => $usercollection,
                                'url' => generateURL($baseurl_short . 'pages/download_usage.php', ['collection' => $usercollection, 'k' => $k])
                            ]
                        );
                    } else {
                        $download_url = generateURL(
                            $baseurl_short . '/pages/terms.php',
                            [
                                'k' => $k,
                                'collection' => $usercollection,
                                'url' => generateURL($baseurl_short . 'pages/collection_download.php', ['collection' => $usercollection, 'k' => $k])
                            ]
                        );
                    }
                } elseif ($download_usage) {
                    $download_url = generateURL($baseurl_short . 'pages/download_usage.php', ['collection' => $usercollection, 'k' => $k]);
                } else {
                    $download_url = generateURL($baseurl_short . 'pages/collection_download.php', ['collection' => $usercollection, 'k' => $k]);
                }
                ?>

                <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $download_url;?>">
                    <?php echo LINK_CARET . escape($lang["action-download"])?>
                </a>
                <br />
                <?php
            }

            if ($feedback) { ?>
                <br>
                <br>
                <a
                    onclick="return CentralSpaceLoad(this);" 
                    href="<?php echo generateURL($baseurl_short . 'pages/collection_feedback.php', ['collection' => $usercollection, 'k' => $k]); ?>"
                >
                    <?php echo LINK_CARET . escape($lang["sendfeedback"]); ?>
                </a>
                <br>
                <?php
            }

            if (
                $count_result > 0
                && checkperm("q")
                && $min_access != 0 # Ability to request a whole collection (only if user has restricted access to any of these resources)
            ) {
                ?>
                <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/collection_request.php?ref=<?php echo urlencode($usercollection) ?>&k=<?php echo urlencode($k) ?>">
                    <?php echo LINK_CARET . escape($lang["requestall"]); ?>
                </a>
                <br />
                <?php
            }
            ?>

            <a id="toggleThumbsLink" href="#" onclick="ToggleThumbs();return false;"><i class="icon-chevron-down"></i><?php echo escape($lang["hidethumbnails"])?></a>
        </div>
        <?php
    } else {
        # -------------------------- Standard display --------------------------------------------
        ?>
        <div id="CollectionMenu">
            <h2 id="CollectionsPanelHeader">
                <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/collection_manage.php">
                    <?php echo escape($lang["mycollections"]); ?>
                </a>
            </h2>

            <form method="get" id="colselect" onsubmit="newcolname=encodeURIComponent(jQuery('#entername').val());CollectionDivLoad('<?php echo $baseurl_short?>pages/collections.php?collection=new&search=<?php echo urlencode($search)?>&k=<?php echo urlencode($k) ?>&entername='+newcolname);return false;">
                <div style="padding:0;margin:0;">
                     <label for="collection"><?php echo escape($lang["currentcollection"]); ?>:</label>
                    <br />
                    <select
                        name="collection"
                        id="collection"
                        aria-label="<?php echo escape($lang["collections"]) ?>"
                        onchange="if (document.getElementById('collection').value == 'new') {
                            document.getElementById('entername').style.display = 'block';
                            document.getElementById('entername').focus();
                            return false;
                            } 
                            <?php if (!checkperm('b')) { ?>
                                ChangeCollection( jQuery(this).val(), 
                                    '<?php echo urlencode($k)  ?>', 
                                    '<?php echo urlencode($usercollection) ?>',
                                    '<?php echo $change_col_url?>' );
                            <?php } else { ?>
                                document.getElementById('colselect').submit();
                            <?php } ?>"
                        class="SearchWidth"
                    >
                        <?php
                        $found = false;
                        for ($n = 0; $n < count($list); $n++) {
                            if (in_array($list[$n]['ref'], $hidden_collections)) {
                                continue;
                            }

                            #show only active collections if a start date is set for $active_collections
                            if (
                                strtotime($list[$n]['created']) > ((isset($active_collections)) ? strtotime($active_collections) : 1)
                                || ($list[$n]['name'] == "Default Collection" && $list[$n]['user'] == $userref)
                            ) {
                                ?>
                                <option
                                    value="<?php echo $list[$n]["ref"]; ?>"
                                    <?php if ($usercollection == $list[$n]["ref"]) { ?>
                                        selected
                                        <?php
                                        $found = true;
                                    } ?>
                                >
                                    <?php echo i18n_get_collection_name($list[$n]) ?>
                                </option>
                                <?php
                            }
                        }

                        if (!$found) {
                            # Add this one at the end, it can't be found
                            $notfound = $cinfo;

                            if ($notfound !== false) {
                                ?>
                                <option value="<?php echo escape($notfound['ref']); ?>" selected><?php echo i18n_get_collection_name($notfound) ?></option>
                                <?php
                            } elseif ($validcollection == 0) {
                                ?>
                                <option selected><?php echo escape($lang["error-collectionnotfound"]) ?></option>
                                <?php
                            }
                        }

                        if (can_create_collections()) {
                            ?>
                            <option value="new">(<?php echo escape($lang["createnewcollection"])?>)</option>
                            <?php
                        }
                        ?>

                    </select>
                    <br />
                    <small>
                        <?php
                        echo $count_result . " ";

                        if ($count_result == 1) {
                            echo escape($lang["item"]);
                        } else {
                            echo escape($lang["items"]);
                        }
                        ?>
                    </small>
                    <input type=text id="entername" name="entername" style="display:none;" placeholder="<?php echo escape($lang['entercollectionname'])?>" class="SearchWidth">
                </div>
            </form>

            <?php
            // Render dropdown actions
            $resources_count = $count_result;
            render_actions($cinfo, false, !hook('renderactionsononeline', 'collections'), '', $result);
            ?>

            <ul>
                <li>
                    <a id="toggleThumbsLink" href="#" onclick="ToggleThumbs();return false;"><i class="icon-chevron-down"></i><?php echo escape($lang['hidethumbnails']); ?></a>
                </li>
            </ul>
        </div>

        <!--Resource panels-->
        <div id="CollectionSpace" class="CollectionSpace">
            <?php
            # Loop through saved searches
            if (is_null($cinfo['savedsearch']) && ($k == '' || $internal_share_access)) {
                for ($n = 0; $n < count($searches); $n++) {
                    $ref = $searches[$n]["ref"];
                    $url = $baseurl_short . "pages/search.php?search=" . urlencode($searches[$n]["search"]) . "&restypes=" . urlencode($searches[$n]["restypes"]) . "&archive=" . urlencode($searches[$n]["archive"]);
                    ?>
                    <!--Resource Panel-->
                    <div id="ResourceShell<?php echo $searches[$n]['ref']; ?>" class="CollectionPanelShell" data-saved-search="yes">
                        <table border="0" class="CollectionResourceAlign">
                            <tr>
                                <td>
                                    <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $url?>">
                                        <i class="icon-search"></i>
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div class="CollectionPanelInfo">
                            <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $url?>">
                                <?php echo escape(substr($lang["savedsearch"], 6)) . ($n + 1); ?>
                            </a>
                            &nbsp;
                        </div>

                        <div class="CollectionPanelTools">
                            <a
                                class="removeFromCollection icon-circle-minus"
                                onclick="return CollectionDivLoad(this);"
                                href="<?php echo $baseurl_short?>pages/collections.php?removesearch=<?php echo urlencode($ref) ?>&nc=<?php echo time()?>"
                            >
                            </a>
                        </div> 
                    </div>
                    <?php
                }
            }

            # Display thumbnails for standard display
            if ($count_result > 0) {
                # Loop through resources for thumbnails for standard display
                for ($n = 0; $n < count($result) && $n < $count_result && $n < $max_collection_thumbs; $n++) {
                    if (!isset($result[$n]) || !is_array($result[$n])) {
                        # $result can be a list of suggested searches, in this case do not process this item.
                        continue;
                    }

                    $ref = $result[$n]["ref"];
                    $resource_view_title = i18n_get_translated($result[$n]["field" . $view_title_field]);

                    ?>
                    <!--Resource Panel-->
                    <div
                        class="CollectionPanelShell ResourceType<?php echo (int) $result[$n]['resource_type']; ?>"
                        id="ResourceShell<?php echo urlencode($ref) ?>"
                        <?php if (in_array($ref, $addarray)) { ?>
                            style="display:none;"
                        <?php } # Hide new items by default then animate open ?>
                    >

                        <?php
                        if (!hook("rendercollectionthumb")) {
                            if (isset($result[$n]["access"]) && $result[$n]["access"] == 0 && !checkperm("g") && !$internal_share_access) {
                                # Resource access is open but user does not have the 'g' permission. Set access to restricted. If they have been granted specific access this will be added next
                                $result[$n]["access"] = 1;
                            }

                            $access = isset($result[$n]["access"]) ? $result[$n]["access"] : get_resource_access($result[$n]);
                            $use_watermark = check_use_watermark();
                            $thumb_url = generateURL($baseurl_short . "pages/view.php", [
                                "ref" => $ref,
                                "search" => "!collection" . $usercollection,
                                "order_by" => $order_by,
                                "sort" => $sort,
                                "k" => $k,
                                "curpos" => $n,
                            ]);
                            ?>

                            <table border="0" class="CollectionResourceAlign">
                                <tr>
                                    <td>
                                        <a
                                            style="position:relative;" 
                                            onclick="return <?php echo $resource_view_modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);"
                                            href="<?php echo $thumb_url ?>"
                                    >
                                            <?php
                                            $colimg_preview_size = $retina_mode ? 'thm' : 'col';
                                            $colimgpath = get_resource_preview($result[$n], [$colimg_preview_size]);
                                            if ($colimgpath !== false && is_safe_url($colimgpath['url'])) {
                                            ?>
                                            <img
                                                class="CollectionPanelThumb"
                                                border=0
                                                src="<?php echo $colimgpath['url']; ?>"
                                                title="<?php echo escape(i18n_get_translated($result[$n]["field" . $view_title_field]))?>"
                                                alt="<?php echo escape(i18n_get_translated($result[$n]["field" . $view_title_field]))?>"
                                                <?php if ($retina_mode) { ?>
                                                    onload="this.width/=2;this.onload=null;"
                                                <?php } ?>
                                            />
                                            <?php
                                            } else {
                                                echo get_nopreview_html((string) $result[$n]["file_extension"], $result[$n]["resource_type"]);
                                            }
                                            hook("aftersearchimg", "", array($result[$n]));
                                            ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <?php
                        } /* end hook rendercollectionthumb */

                        $title = $result[$n]["field" . $view_title_field];
                        $title_field = $view_title_field;

                        if (
                            isset($metadata_template_title_field)
                            && isset($metadata_template_resource_type)
                            && $result[$n]['resource_type'] == $metadata_template_resource_type
                        ) {
                            $title = $result[$n]["field" . $metadata_template_title_field];
                            $title_field = $metadata_template_title_field;
                        }

                        $field_type = ps_value(
                            "SELECT type value FROM resource_type_field WHERE ref=?",
                            array("i",$title_field),
                            "",
                            "schema"
                        );

                        if ($field_type == 8) {
                            $title = str_replace("&nbsp;", " ", $title);
                        }

                        $replace_resource_url = generateURL($baseurl_short . "pages/view.php", [
                            "ref" => $ref,
                            "search" => "!collection" . $usercollection,
                            "k" => $k
                        ]);
                        ?>

                        <div class="CollectionPanelInfo">
                            <a
                                onclick="return <?php echo $resource_view_modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);"
                                href=" <?php echo $replace_resource_url;?>"
                                title="<?php echo escape(i18n_get_translated($result[$n]["field" . $view_title_field]))?>"
                            >
                                <?php echo escape(tidy_trim(i18n_get_translated($title), 14)); ?>
                            </a>&nbsp;
                        </div>

                        <?php
                        if ($k != "" && $feedback) { # Allow feedback for external access key users
                            $comment_url = generateURL($baseurl_short . "pages/collection_comment.php", [
                                "ref" => $ref,
                                "collection" => $usercollection,
                                "k" => $k,
                                ]);
                            ?>
                            <div class="CollectionPanelInfo">
                                <span>
                                    <a
                                        aria-hidden="true"
                                        class="icon-message-circle"
                                        onclick="return ModalLoad(this,true);"
                                        href="<?php echo $comment_url?>"
                                    >
                                    </a>
                                </span>
                            </div>
                            <?php
                        }

                        if ($k == "" || $internal_share_access) {
                            ?>
                            <div class="CollectionPanelTools">
                                <?php
                                if (!isset($cinfo['savedsearch']) || (isset($cinfo['savedsearch']) && $cinfo['savedsearch'] == null)) {
                                    // add 'remove' link only if this is not a smart collection
                                    $rating = '';

                                    if (isset($rating_field)) {
                                        $rating = "field{$rating_field}";
                                    }

                                    $url = generateURL(
                                        $baseurl_short . "pages/view.php",
                                        ["ref" => $ref,
                                        "search" => '!collection' . $usercollection,
                                        "order_by" => $order_by,
                                        "sort" => $sort,
                                        "offset" => $offset,
                                        "archive" => $archive,
                                        "k" => $k,
                                        "curpos" => $n,
                                        "restypes" => $restypes,
                                        ]
                                    );

                                    # Include standard search views
                                    include "search_views/resource_tools.php";
                                } # End of remove link condition
                                ?>
                            </div>
                            <?php
                        } # End of k="" condition ?>
                    </div>
                    <?php
                } # End of loop through standard display thumbnails ?>

                <div class="clearerleft"></div>
                <?php
            } # End of display thumbnails for standard display


            if ($count_result > $max_collection_thumbs) {
                ?>
                <div class="CollectionPanelShell">
                    <table border="0" class="CollectionResourceAlign">
                        <tr>
                            <td><img alt=""/></td>
                        </tr>
                    </table>
                    <div class="CollectionPanelInfo">
                        <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/search.php?search=!collection<?php echo $usercollection?>&k=<?php echo urlencode($k) ?>">
                            <?php echo escape($lang['viewall'])?>...
                        </a>
                    </div>
                </div>
                <?php
            }

            if (count($addarray) > 0 && $addarray[0] != "") {
                # Animate the new item
                ?>
                <script type="text/javascript">
                    jQuery("#CollectionSpace #ResourceShell<?php echo escape($addarray[0]) ?>").slideDown('fast');
                </script>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
    } # End of standard display
    ?>
    
    <div id="CollectionMinDiv" style="display:<?php echo ($thumbs == 'hide') ? 'block' : 'none'; ?>">
        <!--Title-->    
        <?php
        # ------------------------- Minimised view
        if ($k != "" && !$internal_share_access) {
            # Anonymous access, slightly different display
            $tempcol = $cinfo;
            ?>
            <div id="CollectionMinTitle" class="ExternalShare">
                <h2><?php echo i18n_get_collection_name($tempcol)?></h2>
            </div>

            <div id="CollectionMinRightNav" class="ExternalShare">
                <?php
                if ((isset($zipcommand) || $collection_download) && $count_result > 0 && count($result) > 0) {
                    if ($terms_download) {
                        if ($download_usage) {
                            $download_url = generateURL(
                                $baseurl_short . '/pages/terms.php',
                                [
                                'k' => $k,
                                'collection' => $usercollection,
                                'url' => generateURL($baseurl_short . 'pages/download_usage.php', ['collection' => $usercollection, 'k' => $k])
                                ]
                            );
                        } else {
                            $download_url = generateURL(
                                $baseurl_short . '/pages/terms.php',
                                [
                                'k' => $k,
                                'collection' => $usercollection,
                                'url' => generateURL($baseurl_short . 'pages/collection_download.php', ['collection' => $usercollection, 'k' => $k])
                                ]
                            );
                        }
                    } elseif ($download_usage) {
                        $download_url = generateURL($baseurl_short . 'pages/download_usage.php', ['collection' => $usercollection, 'k' => $k]);
                    } else {
                        $download_url = generateURL($baseurl_short . 'pages/collection_download.php', ['collection' => $usercollection, 'k' => $k]);
                    }
                    ?>

                    <li>
                        <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $download_url; ?>">
                            <?php echo escape($lang["action-download"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if ($feedback) {
                    ?>
                    <li>
                        <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/collection_feedback.php?collection=<?php echo urlencode($usercollection) ?>&k=<?php echo urlencode($k) ?>">
                            <?php echo escape($lang["sendfeedback"])?>
                        </a>
                    </li>
                    <?php
                }

                if ($count_result > 0) {
                    # Ability to request a whole collection (only if user has restricted access to any of these resources)
                    $min_access = collection_min_access($result);
                    if ($min_access != 0) {
                        ?>
                        <li>
                            <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/collection_request.php?ref=<?php echo urlencode($usercollection) ?>&k=<?php echo urlencode($k) ?>">
                                <?php echo escape($lang["requestall"]); ?>
                            </a>
                        </li>
                        <?php
                    }
                }
                ?>

                <li>
                    <a id="toggleThumbsLink" href="#" onclick="ToggleThumbs();return false;">
                        <i class="icon-chevron-up"></i><?php echo escape($lang["showthumbnails"])?>
                    </a>
                </li>
            </div>
            <?php
        } else {
            ?>
            <div class="ToggleThumbsContainer">
                <a id="toggleThumbsLink" href="#" onclick="ToggleThumbs();return false;">
                    <i class="icon-chevron-up"></i><?php echo escape($lang['showthumbnails']); ?>
                </a>
            </div>

            <!--Menu--> 
            <div id="CollectionMinRightNav">
                <?php
                // Render dropdown actions
                render_actions($cinfo, false, false, "min", $result);
                ?>
            </div>

            <!--Collection Dropdown-->
            <div id="CollectionMinDropTitle"><?php echo escape($lang['currentcollection']); ?>:</div>

            <div id="CollectionMinDrop">
                <form
                    method="get"
                    id="colselect2" 
                    onsubmit="newcolname=encodeURIComponent(jQuery('#entername2').val());CollectionDivLoad('<?php echo $baseurl_short; ?>pages/collections.php?thumbs=hide&collection=new&search=<?php echo urlencode($search)?>&k=<?php echo urlencode($k); ?>&search=<?php echo urlencode($search)?>&entername='+newcolname);return false;"
                >
                    <div class="MinSearchItem" id="MinColDrop">
                        <input type=text id="entername2" name="entername" placeholder="<?php echo escape($lang['entercollectionname']); ?>" style="display:inline;display:none;" class="SearchWidthExp">
                    </div>

                    <script>
                        jQuery('#collection').clone().attr('id','collection2').attr('onChange',
                            "if (document.getElementById('collection2').value == 'new') {document.getElementById('entername2').style.display='inline';document.getElementById('entername2').focus();return false;}<?php
                                if (!checkperm('b')) { ?>
                                    ChangeCollection(jQuery(this).val(),'<?php echo urlencode($k) ?>','<?php echo urlencode($usercollection) ?>','<?php echo $change_col_url ?>');<?php
                                } else {
                                    ?>document.getElementById('colselect2').submit();<?php
                                } ?>").prependTo('#MinColDrop');
                    </script>
                </form>
            </div>
            <?php
        }
        ?>
        
        <!--Collection Count--> 
        <div id="CollectionMinitems">
            <strong><?php echo $count_result?></strong>&nbsp;
            <?php
            if ($count_result == 1) {
                echo escape($lang["item"]);
            } else {
                echo escape($lang["items"]);
            }
            ?>
        </div>
    </div>

<?php
draw_performance_footer();
