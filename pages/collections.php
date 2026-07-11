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
$addsearch       = getval("addsearch", -1);
$pulse           = getval("pulse", false);

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

// No bar for anonymous users
if($k != "" && !$internal_share_access) {
    exit;
}

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

$thumbs_shown = getval("thumbs_shown", "unset");
if ($thumbs_shown == "unset") {
    rs_setcookie("thumbs_shown", ($thumbs === "show") ? "true" : "false", 1000, "", "", false, false);
}

switch ($thumbs) {
    case "show":
        $collection_holder_state = "state-thumbs";
        break;
    case "actions":
        $collection_holder_state = "state-actions";
        break;
    default:
        $collection_holder_state = "state-closed";
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

                    if ((results !== null) &&
                        ('<?php echo urlencode("!collection" . $usercollection); ?>' === results[1]
                        ||
                        '<?php echo "!collection" . (int) $usercollection; ?>' === results[1])) {
                            CentralSpaceLoad('<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!collection" . $usercollection); ?>',true);
                    }
                }
            });
        }

        jQuery(document).ready(function() {
            if (is_touch_device()) {
                return false;
            }

            jQuery('.collection-bar-thumbnail-row').sortable({
                distance: 20,
                appendTo: 'body',
                zIndex: 99000,
                forcePlaceholderSize: true,
                helper: function(event, ui) {
                    //Hack to append the element to the body (visible above others divs), 
                    //but still belonging to the scrollable container
                    jQuery('.collection-bar-thumbnail-row').append('<div id="CollectionSpaceClone" class="ui-state-default">' + ui[0].outerHTML + '</div>');   
                    jQuery('#CollectionSpaceClone').hide();
                    setTimeout(function() {
                        jQuery('#CollectionSpaceClone').appendTo('body'); 
                        jQuery('#CollectionSpaceClone').show();
                    }, 1);
                    return jQuery('#CollectionSpaceClone');
                },
                items: '.collection-bar-resource-card[data-draggable="yes"]',

                start: function (event, ui) {
                    InfoBoxEnabled=false;
                    if (jQuery('#InfoBoxCollection')) {
                        jQuery('#InfoBoxCollection').hide();
                    }
                },

                stop: function(event, ui) {
                    InfoBoxEnabled=true;
                    var idsInOrder = jQuery('.collection-bar-thumbnail-row').sortable("toArray");
                    ReorderResourcesInCollection(idsInOrder);
                }
            });

            jQuery('.CollectionPanelShell').disableSelection();

            jQuery('.collection-bar-resource-card:not([data-draggable="yes"]) a')
                .on('dragstart', function(e){
                    e.preventDefault();
            });
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
            accept: function(draggable) {
                return (
                    jQuery(draggable).hasClass('resource-card') &&
                    jQuery('#CollectionDiv .collection-bar-holder').hasClass('state-thumbs')
                );
            },

            drop: function(event, ui) {
                var query_strings = getQueryStrings();
                if (is_special_search('!collection', 11) && !is_empty(query_strings) && query_strings.search.substring(11) == usercollection) {
                    // No need to re-add this resource since we are looking at the same collection in both CentralSpace and CollectionDiv
                    return false;
                }

                var resource_id = jQuery(ui.draggable).attr("id");
                resource_id = resource_id.replace('ResourceShell', '');

                // AddResourceToCollection includes a reload of CollectionDiv 
                AddResourceToCollection(event, ui, resource_id, '');
            }
        });
    });

    jQuery(document).ready(function() {
        jQuery('#CentralSpace').trigger('prepareDragDrop');
    });

    registerResourceSelectDeselectHandlers();
</script>
<!-- End of Drag and Drop -->
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
            // add to current collection
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

                    if (isset($resourcesnotadded["blockedshares"])) {
                        // There are resources blocked from being added due to share permissions
                        if ($warningtext != "") {
                            $warningtext .= "<br />";
                        }
                        $warningtext .= $lang["notsharableresources"] . implode(", ", $resourcesnotadded["blockedshares"]) . "<br />";
                        unset($resourcesnotadded["blockedshares"]);
                    }
                    
                    if (!empty($resourcesnotadded)) {
                        // There are resources blocked from being added due to archive state
                        if ($warningtext != "") {
                            $warningtext .= "<br />";
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

$result  = do_search("!collection{$usercollection}", '', $default_collection_sort, 0, [0, $max_collection_thumbs], "ASC", false, 0, false, false, '', false, true, false);
$count_result = $result["total"];
$feedback = $cinfo ? $cinfo["request_feedback"] : 0;

?>
<script type="text/javascript">
jQuery(function () {

    // Collection bar scroll buttons
    const $scroller = jQuery(".collection-bar-thumbnail-row");
    const $leftBtn = jQuery(".thumbnail-row-arrow.left");
    const $rightBtn = jQuery(".thumbnail-row-arrow.right");

    function getCards() {
        return $scroller.find(".collection-bar-resource-card");
    }

    function getScrollerPaddingLeft() {
        const scrollerEl = $scroller[0];
        return parseFloat(window.getComputedStyle(scrollerEl).paddingLeft) || 0;
    }

    function getCardPositions() {
        const scrollerEl = $scroller[0];
        const paddingLeft = getScrollerPaddingLeft();
        let positions = [];

        getCards().each(function (index) {
            let extraOffset = index === 0 ? 0 : 6;
            positions.push(this.offsetLeft - paddingLeft + extraOffset);
        });

        return {
            positions: positions,
            maxScroll: scrollerEl.scrollWidth - scrollerEl.clientWidth
        };
    }

    function getFullyVisibleIndexes() {
        const scrollerEl = $scroller[0];
        const scrollerLeft = scrollerEl.scrollLeft;
        const scrollerRight = scrollerLeft + scrollerEl.clientWidth;
        const paddingLeft = getScrollerPaddingLeft();
        let visibleIndexes = [];

        getCards().each(function (index) {
            let cardLeft = this.offsetLeft - paddingLeft + (index === 0 ? 0 : 6);
            let cardRight = cardLeft + this.offsetWidth;

            if (cardLeft >= scrollerLeft && cardRight <= scrollerRight) {
                visibleIndexes.push(index);
            }
        });

        return visibleIndexes;
    }

    function getFirstVisibleIndex(positions) {
        const currentScroll = $scroller.scrollLeft();
        const tolerance = 2;

        for (var i = 0; i < positions.length; i++) {
            if (positions[i] >= currentScroll - tolerance) {
                return i;
            }
        }

        return positions.length - 1;
    }

    function updateScrollButtons() {
        const el = $scroller[0];
        if (!el) return;

        let maxScrollLeft = el.scrollWidth - el.clientWidth;
        let tolerance = 2;

        if (el.scrollLeft <= tolerance) {
            $leftBtn.hide();
        } else {
            $leftBtn.show();
        }

        if (el.scrollLeft >= maxScrollLeft - tolerance) {
            $rightBtn.hide();
        } else {
            $rightBtn.show();
        }
    }

    function scrollPage(direction) {
        let metrics = getCardPositions();
        if (!metrics.positions.length) return;

        let fullyVisibleIndexes = getFullyVisibleIndexes();
        let visibleCount = Math.max(1, fullyVisibleIndexes.length);
        let moveBy = Math.max(1, visibleCount - 1);

        let currentIndex = getFirstVisibleIndex(metrics.positions);
        let nextIndex = direction === "right"
            ? currentIndex + moveBy
            : currentIndex - moveBy;

        nextIndex = Math.max(0, Math.min(nextIndex, metrics.positions.length - 1));

        let target = metrics.positions[nextIndex];
        target = Math.max(0, Math.min(target, metrics.maxScroll));

        $scroller.stop().animate({ scrollLeft: target }, 300, updateScrollButtons);
    }

    $leftBtn.on("click", function () {
        scrollPage("left");
    });

    $rightBtn.on("click", function () {
        scrollPage("right");
    });

    $scroller.on("scroll", updateScrollButtons);
    jQuery(window).on("resize", updateScrollButtons);

    // New collection name entry
    const $input = jQuery("#collection_name_input");
    const $cancel_button = jQuery("#cancel_creating_collection_button");
    const $accept_button = jQuery("#accept_creating_collection_button");

    function updateButtons() {
        if ($input.val().trim().length > 0) {
            $accept_button.removeClass("inactive");
        } else {
            $accept_button.addClass("inactive");
        }
    }

    $input.on("input", updateButtons);

    $cancel_button.on("click", function () {
        $input.val("");
        updateButtons();
        document.getElementById("collection").value = jQuery("#currentusercollection").text().trim();
        document.getElementById('collection').style.display = 'block';
        document.getElementById('entername').style.display = 'none';
        document.getElementById('collection').focus();
    });

    $accept_button.on("click", function () {
        jQuery("#colselect").submit();
    });

    // Collection bar state toggle
    const $bar = jQuery('.collection-bar-holder');
    const $toggleThumbsBtn = jQuery('.show-thumbs-button');

    function getThumbsShown() {

        const value = getCookie("thumbs_shown");

        if (value === undefined) {
            const returnVal = <?php echo $thumbs_default == "show" ? "true" : "false"; ?>;;
            SetCookie('thumbs_shown', returnVal, 1000);
            return returnVal;
        }

        return value === "true";
    }

    function setBarState(state) {
        $bar.removeClass('state-closed state-actions state-thumbs').addClass(state);

        if (state == 'state-thumbs') {
            SetCookie('thumbs',"show",1000);
            $toggleThumbsBtn.find('span').text('<?php echo escape($lang["hidethumbnails"]); ?>');
        } else if (state == 'state-actions') {
            SetCookie('thumbs',"actions",1000);
            $toggleThumbsBtn.find('span').text('<?php echo escape($lang["showthumbnails"]); ?>');
        } else {
            SetCookie('thumbs',"hide",1000);
            $cancel_button.trigger("click");
            $toggleThumbsBtn.find('span').text('<?php echo escape($lang["showthumbnails"]); ?>');
        }
    }
    
    jQuery('.collection-bar-toggle-button').on('click', function () {
        if ($bar.hasClass('state-closed')) {
            if (getThumbsShown()) {
                setBarState('state-thumbs');
            } else {
                setBarState('state-actions');
            }
        } else {
            setBarState('state-closed');
        }
    });

    jQuery('.show-thumbs-button').on('click', function () {
        if ($bar.hasClass('state-thumbs')) {
            setBarState('state-actions');
            SetCookie('thumbs_shown',"false",1000);
        } else {
            setBarState('state-thumbs');
            SetCookie('thumbs_shown',"true",1000);
        }
    });
    
    // Update scroll and name entry buttons on load
    updateScrollButtons();
    updateButtons();

});
</script>
<div class="collection-bar-holder <?php echo $collection_holder_state; ?>">
    <div style="display:none;" id="currentusercollection"><?php echo (int) $usercollection; ?></div>
    <script>usercollection='<?php echo escape($usercollection); ?>';</script>
    <div class="collection-bar-toggle-button <?php echo ($collection_holder_state == "state-closed" && $pulse == true) ? "pulse-animation" : ""; ?>"><i class="icon-layout-dashboard"></i></div>
    <div class="collection-bar-actions-section">
        <div class="collection-bar-actions-form">
            <div class="field current-collections-field">
                <div class="label-holder">
                    <label for="collection">
                        <?php echo escape($lang["currentcollection"]); ?>
                    </label>
                    <div class="meta">
                        <?php echo $count_result . " " . ($count_result == 1 ? escape($lang["item"]) : escape($lang["items"])); ?>
                    </div>
                </div>
                <div class="wrapper">
                    <form method="get" id="colselect" onsubmit="newcolname=encodeURIComponent(jQuery('#collection_name_input').val());CollectionDivLoad('<?php echo $baseurl_short; ?>pages/collections.php?collection=new&search=<?php echo urlencode($search); ?>&k=<?php echo urlencode($k); ?>&entername='+newcolname);return false;">
                        <div id="entername" class="collection-bar-name-input">
                            <input id="collection_name_input" type="text" class="SearchWidth"></input>
                            <div class="collection-bar-name-button-group">
                                <button id="cancel_creating_collection_button" class="cancel-button" type="button" aria-label="Cancel creating collection">
                                </button>
                                <button id="accept_creating_collection_button" class="accept-button inactive" type="button" aria-label="Create new collection">
                                </button>
                            </div>
                        </div>
                    </form>
                    <select 
                        name="collection"
                        id="collection"
                        aria-label="<?php echo escape($lang["collections"]); ?>"
                        onchange="if (document.getElementById('collection').value == 'new') {
                            document.getElementById('collection').style.display = 'none';
                            document.getElementById('entername').style.display = 'flex';
                            document.getElementById('collection_name_input').focus();
                            return false;
                            } 
                            <?php if (!checkperm('b')) { ?>
                                ChangeCollection( jQuery(this).val(), 
                                    '<?php echo urlencode($k); ?>', 
                                    '<?php echo urlencode($usercollection); ?>',
                                    '<?php echo $change_col_url; ?>' );
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
                                    <?php echo i18n_get_collection_name($list[$n]); ?>
                                </option>
                                <?php
                            }
                        }

                        if (!$found) {
                            # Add this one at the end, it can't be found
                            $notfound = $cinfo;

                            if ($notfound !== false) {
                                ?>
                                <option value="<?php echo escape($notfound['ref']); ?>" selected><?php echo i18n_get_collection_name($notfound); ?></option>
                                <?php
                            } elseif ($validcollection == 0) {
                                ?>
                                <option selected><?php echo escape($lang["error-collectionnotfound"]); ?></option>
                                <?php
                            }
                        }

                        if (can_create_collections()) {
                            ?>
                            <option value="new">(<?php echo escape($lang["createnewcollection"]); ?>)</option>
                            <?php
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="field actions-field">
                <div class="label-holder">
                    <label for="collections_action_selection_bottom_<?php echo (int) $usercollection; ?>">
                        <?php echo escape($lang["actions"]); ?>
                    </label>
                </div>
                <div class="wrapper">
                    <?php
                    // Render dropdown actions
                    $resources_count = $count_result;
                    render_actions($cinfo, false, false, '', $result);
                    ?>
                </div>
            </div>
            <button class="show-thumbs-button">
                <i class="icon-images"></i>
                <span><?php echo escape(($collection_holder_state == "state-thumbs") ? $lang["hidethumbnails"] : $lang["showthumbnails"]); ?></span>
            </button>
        </div>
    </div>
    <div class="collection-bar-thumbs-section">
        <script>
            var collection_resources = <?php echo json_encode(array_column($result, 'ref'));?>; 
        </script>
        <div class="collection-bar-thumbnails">
            <button class="thumbnail-row-arrow left" aria-label="Scroll left">
            </button>
            <div class="collection-bar-thumbnail-row">
            <?php
            # Loop through saved searches
            if (is_null($cinfo['savedsearch']) && ($k == '' || $internal_share_access)) {
                for ($n = 0; $n < count($searches); $n++) {
                    $ref = $searches[$n]["ref"];
                    $url = $baseurl_short . "pages/search.php?search=" . urlencode($searches[$n]["search"]) . "&restypes=" . urlencode($searches[$n]["restypes"]) . "&archive=" . urlencode($searches[$n]["archive"]);
                    ?>
                     <!--Resource Panel-->
                    <div class="collection-bar-resource-card" data-saved-search="yes" data-draggable="no">
                        <a class="collection-bar-resource-card-link" 
                               onclick="return CentralSpaceLoad(this,true);" 
                               href="<?php echo $url?>"
                               aria-label="Open saved search">
                        </a>
                        <div class="collection-bar-resource-card-image">
                            <i class="icon-search"></i>
                            <div class="collection-bar-resource-card-image-overlay"></div>
                        </div>
                        <div class="collection-bar-resource-card-remove">
                            <a
                                class="removeFromCollection icon-circle-minus"
                                onclick="return CollectionDivLoad(this);"
                                href="<?php echo $baseurl_short?>pages/collections.php?removesearch=<?php echo urlencode($ref) ?>&nc=<?php echo time()?>"
                            >
                            </a>
                        </div>
                        <div class="collection-bar-resource-card-title">
                            <?php echo escape(substr($lang["savedsearch"], 6)) . ($n + 1); ?>
                        </div>
                    </div>
                    <?php
                }
            }

            # Display thumbnails for standard display
            if ($count_result > 0) {
                # Loop through resources for thumbnails for standard display
                for ($n = 0; $n < count($result["data"]); $n++) {
                    if (!isset($result["data"][$n]) || !is_array($result["data"][$n])) {
                        # $result can be a list of suggested searches, in this case do not process this item.
                        continue;
                    }

                    $ref = $result["data"][$n]["ref"];
                    $resource_view_title = i18n_get_translated($result["data"][$n]["field" . $view_title_field]);

                    $resource_url = generateURL($baseurl_short . "pages/view.php", [
                        "ref" => $ref,
                        "search" => "!collection" . $usercollection,
                        "order_by" => $order_by,
                        "sort" => $sort,
                        "k" => $k,
                        "curpos" => $n,
                    ]);

                    ?>
                    <!--Resource Panel-->
                    <div class="collection-bar-resource-card" id="ResourceShell<?php echo urlencode($ref); ?>"  data-draggable="yes">

                        <a
                            class="collection-bar-resource-card-link"
                            onclick="return <?php echo $resource_view_modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);"
                            href="<?php echo $resource_url; ?>"
                            aria-label="<?php echo escape(i18n_get_translated($result["data"][$n]["field" . $view_title_field])); ?>"
                        ></a>
                        
                        <?php
                        if (!hook("rendercollectionthumb")) {
                            if (isset($result["data"][$n]["access"]) && $result["data"][$n]["access"] == 0 && !checkperm("g") && !$internal_share_access) {
                                # Resource access is open but user does not have the 'g' permission. Set access to restricted. If they have been granted specific access this will be added next
                                $result["data"][$n]["access"] = 1;
                            }

                            $access = isset($result["data"][$n]["access"]) ? $result["data"][$n]["access"] : get_resource_access($result["data"][$n]);
                            $use_watermark = check_use_watermark();

                            ?>

                            <div class="collection-bar-resource-card-image">
                                <?php
                                $colimgpath = get_resource_preview($result["data"][$n], ['thm'], $access, $use_watermark);
                                if ($colimgpath !== false && is_safe_url($colimgpath['url'])) {
                                    ?>
                                    <img border="0"
                                        src="<?php echo $colimgpath['url']; ?>"
                                        title="<?php echo escape(i18n_get_translated($result["data"][$n]["field" . $view_title_field])); ?>"
                                        alt="<?php echo escape(i18n_get_translated($result["data"][$n]["field" . $view_title_field])); ?>"                                   
                                    />
                                <?php
                                } else {
                                    echo get_nopreview_html((string) $result["data"][$n]["file_extension"], $result["data"][$n]["resource_type"]);
                                }
                                hook("aftersearchimg", "", array($result["data"][$n]));
                                ?>
                                <div class="collection-bar-resource-card-image-overlay"></div>
                            </div>
                        <?php 
                        } 
                        ?>


                        <div class="collection-bar-resource-card-remove">
                            <?php
                            // Remove from collection icon
                            if (!checkperm('b') && ($k == '' || $internal_share_access)) {
                                $col_link_class = ['icon-circle-minus'];

                                if (
                                    isset($usercollection_resources)
                                    && is_array($usercollection_resources)
                                    && !in_array($ref, $usercollection_resources)
                                ) {
                                    $col_link_class[] = 'DisplayNone';
                                }

                                $onclick = 'toggle_addremove_to_collection_icon(this);';
                                echo remove_from_collection_link($ref, implode(' ', $col_link_class), $onclick, 0, $resource_view_title) . '</a>';
                            }

                            ?>
                        </div>
                        <?php

                        $title = $result["data"][$n]["field" . $view_title_field];
                        $title_field = $view_title_field;

                        if (
                            isset($metadata_template_title_field)
                            && isset($metadata_template_resource_type)
                            && $result["data"][$n]['resource_type'] == $metadata_template_resource_type
                        ) {
                            $title = $result["data"][$n]["field" . $metadata_template_title_field];
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
                        <div class="collection-bar-resource-card-title">
                            <?php echo escape(i18n_get_translated($title)); ?>
                        </div>
                    </div>
                <?php
                }
            } else {
                echo escape($lang['noresources_collection']);
            }

            if ($count_result > $max_collection_thumbs) {
                ?>
                <div class="collection-bar-resource-card" data-draggable="no">
                    <a class="collection-bar-resource-card-link" 
                        onclick="return CentralSpaceLoad(this,true);" 
                        href="<?php echo $baseurl_short?>pages/search.php?search=!collection<?php echo escape($usercollection); ?>&k=<?php echo urlencode($k); ?>"
                        aria-label="<?php echo escape($lang['viewall'])?>">
                    </a>
                    <div class="collection-bar-resource-card-image">
                        <i class="icon-eye"></i>
                        <span><?php echo escape($lang['viewall']); ?></span>
                        <div class="collection-bar-resource-card-image-overlay"></div>
                    </div>
                    <div class="collection-bar-resource-card-title">
                    </div>
                </div>
                <?php
            }

            ?>
            </div>
            <button class="thumbnail-row-arrow right" aria-label="Scroll right">
            </button>
        </div>
    </div>
</div>