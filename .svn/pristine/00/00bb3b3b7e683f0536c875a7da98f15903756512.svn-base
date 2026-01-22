<?php
include "../include/boot.php";
include "../include/authenticate.php";

if (checkperm("b")) {
    exit("Permission denied");
}

$k = getval("k", "");
$offset = getval("offset", 0, true);
$find = getval("find", getval("saved_find", ""));
rs_setcookie('saved_find', $find);
$col_order_by = getval("col_order_by", getval("saved_col_order_by", "created"));
rs_setcookie('saved_col_order_by', $col_order_by);
$sort = getval('sort', getval('saved_col_sort', 'ASC', false, 'validate_sort_value'), false, 'validate_sort_value');
rs_setcookie('saved_col_sort', $sort);
$revsort = ($sort == "ASC") ? "DESC" : "ASC";

# pager
$per_page = getval("per_page_list", get_per_page_cookie(), true);
set_per_page_cookie($per_page);

$collection_valid_order_bys = array("fullname","name","ref","count","type");
$modified_collection_valid_order_bys = hook("modifycollectionvalidorderbys");

if ($modified_collection_valid_order_bys) {
    $collection_valid_order_bys = $modified_collection_valid_order_bys;
}

if (!in_array($col_order_by, $collection_valid_order_bys)) {
    $col_order_by = "created";
} # Check the value is one of the valid values (SQL injection filter)

if (array_key_exists("find", $_POST)) {
    $offset = 0;
} # reset page counter when posting

$name = getval('name', '');

if ('' != $name && can_create_collections() && enforcePostRequest(false)) {
    // Create new collection
    $new = create_collection($userref, $name);
    $redirect_url = "pages/collection_edit.php?ref={$new}&reload=true";

    // This is used to create featured collections directly from the featured collections page
    if ($enable_themes && getval("call_to_action_tile", "") === "true" && checkperm("h")) {
        $parent = (int) getval("parent", 0, true);
        $coldata = array(
            "name" => $name,
            "featured_collections_changes" => array(
                "update_parent" => $parent,
                "force_featured_collection_type" => true,
                "thumbnail_selection_method" => $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_images"],
            ),
        );
        $redirect_params = ($parent == 0 ? array() : array("parent" => $parent));
        $redirect_url = generateURL("{$baseurl_short}pages/collections_featured.php", $redirect_params);

        save_collection($new, $coldata);
    }

    set_user_collection($userref, $new);
    daily_stat('New collection', $userref);
    redirect($redirect_url);
}

$delete_collections = array();

if (getval("delete", "") != "") {
    $delete_cols = explode(',', getval("delete", ""));
    foreach ($delete_cols as $col_ref) {
        $delete_collections[] = $col_ref;
    }
}

foreach ($delete_collections as $delete) {
    if ($delete != '' && enforcePostRequest(getval("ajax", false))) {
        // Check user is actually allowed to delete the collection first
        $collection_data = get_collection($delete);
        if (!is_array($collection_data) || !can_delete_collection($collection_data, $userref, $k)) {
            header('HTTP/1.1 401 Unauthorized');
            die('Permission denied!');
        }

        # Delete collection
        delete_collection($collection_data);

        # Get count of collections
        $c = get_user_collections($userref);

        # If the user has just deleted the collection they were using, select a new collection
        if ($usercollection == $delete && count($c) > 0) {
            # Select the first collection in the dropdown box.
            $usercollection = $c[0]["ref"];
            set_user_collection($userref, $usercollection);
        }

        # User has deleted their last collection? add a new one.
        if (count($c) == 0) {
            # No collections to select. Create them a new collection.
            $usercollection = create_collection($userref, "Default Collection");
            set_user_collection($userref, $usercollection);
        }

        # To update the page only when all collections have been deleted, remove from the array those already processed.
        $id_col_deleted = array_search($delete, $delete_collections);
        if ($id_col_deleted !== false) {
            unset($delete_collections[$id_col_deleted]);
        }

        if (getval('ajax', '') !== '' && getval('dropdown_actions', '') !== '' && count($delete_collections) == 0) {
            $response = array(
                'success'                => 'Yes',
                'redirect_to_collection' => $usercollection,
                'k'                      => getval('k', ''),
                'nc'                     => time()
            );

            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }
}
refresh_collection_frame($usercollection);

$removeall = getval("removeall", "");

if ($removeall != "" && enforcePostRequest(false)) {
    remove_all_resources_from_collection($removeall);
    refresh_collection_frame($usercollection);
}

$remove = getval("remove", "");

if ($remove != "" && enforcePostRequest(false)) {
    # Remove someone else's collection from your My Collections
    remove_collection($userref, $remove);

    # Get count of collections
    $c = get_user_collections($userref);

    # If the user has just removed the collection they were using, select a new collection
    if ($usercollection == $remove && count($c) > 0) {
        # Select the first collection in the dropdown box.
        $usercollection = $c[0]["ref"];
        set_user_collection($userref, $usercollection);
    }

    refresh_collection_frame();
}

$add = getval("add", "");

if ($add != "" && enforcePostRequest(false)) {
    # Add someone else's collection to your My Collections
    add_collection($userref, $add);
    set_user_collection($userref, $add);
    refresh_collection_frame();

    # Log this
    daily_stat("Add public collection", $userref);
}

$reload = getval("reload", "");

if ($reload != "") {
    # Refresh the collection frame (just edited a collection)
    refresh_collection_frame();
}

$purge = getval("purge", "");
$deleteall = getval("deleteall", "");

if (($purge != "" || $deleteall != "") && enforcePostRequest(false)) {
    if ($purge != "") {
        $deletecollection = $purge;
    }

    if ($deleteall != "") {
        $deletecollection = $deleteall;
    }

    # Delete all resources in collection
    if (!checkperm("D")) {
        $resources = do_search("!collection" . $deletecollection);
        for ($n = 0; $n < count($resources); $n++) {
            if (checkperm("e" . $resources[$n]["archive"])) {
                delete_resource($resources[$n]["ref"]);
                collection_log($deletecollection, "D", $resources[$n]["ref"]);
            }
        }
    }

    if ($purge != "") {
        # Delete collection
        delete_collection($purge);
        # Get count of collections
        $c = get_user_collections($userref);

        # If the user has just deleted the collection they were using, select a new collection
        if ($usercollection == $purge && count($c) > 0) {
            # Select the first collection in the dropdown box.
            $usercollection = $c[0]["ref"];
            set_user_collection($userref, $usercollection);
        }

        # User has deleted their last collection? add a new one.
        if (count($c) == 0) {
            # No collections to select. Create them a new collection.
            $usercollection = create_collection($userref, "Default Collection");
            set_user_collection($userref, $usercollection);
        }
    }
    refresh_collection_frame($usercollection);
}

$deleteempty = getval("deleteempty", "");

if ($deleteempty != "" && enforcePostRequest(false)) {
    $collections = get_user_collections($userref);
    $deleted_usercoll = false;

    for ($n = 0; $n < count($collections); $n++) {
        // if count is zero and not Default Collection and collection is owned by user:
        if ($collections[$n]['count'] == 0 && $collections[$n]['cant_delete'] != 1 && $collections[$n]['user'] == $userref) {
            delete_collection($collections[$n]['ref']);
            if ($collections[$n]['ref'] == $usercollection) {
                $deleted_usercoll = true;
            }
        }
    }

    # Get count of collections
    $c = get_user_collections($userref);

    # If the user has just deleted the collection they were using, select a new collection
    if ($deleted_usercoll && count($c) > 0) {
        # Select the first collection in the dropdown box.
        $usercollection = $c[0]["ref"];
        set_user_collection($userref, $usercollection);
    }

    # User has deleted their last collection? add a new one.
    if (count($c) == 0) {
        # No collections to select. Create them a new collection.
        $usercollection = create_collection($userref, "Default Collection");
        set_user_collection($userref, $usercollection);
    }

    refresh_collection_frame($usercollection);
}

$removeall = getval("removeall", "");

if ($removeall != "" && enforcePostRequest(false)) {
    remove_all_resources_from_collection($removeall);
    refresh_collection_frame($usercollection);
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["managemycollections"])?></h1>
    <p class="tight">
        <?php
        echo escape(text("introtext"));
        render_help_link("collections-public-and-themes");
        ?>
    </p>
    <br />
    <div class="BasicsBox">
        <form method="post" action="<?php echo $baseurl_short?>pages/collection_manage.php">
            <?php generateFormToken("find"); ?>
            <div class="Question">
                <div class="tickset">
                    <div class="Inline">
                        <input type=text name="find" id="find" value="<?php echo escape(unescape($find)); ?>" maxlength="100" class="shrtwidth" />
                    </div>
                    <div class="Inline">
                        <input name="Submit" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>" />
                    </div>
                    <div class="Inline">
                        <input name="Clear" type="button" onclick="document.getElementById('find').value='';submit();" value="<?php echo escape($lang["clearbutton"]); ?>" />
                    </div>
                </div>
                <div class="clearerleft"></div>
            </div>
        </form>
    </div>

    <?php
    $collections = get_user_collections($userref, $find, $col_order_by, $sort);

    $modified_collections = hook("modified_collections", "", array($userref,$find,$col_order_by,$sort));

    if (!empty($modified_collections)) {
        $collections = $modified_collections;
    }

    $results = count($collections);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $jumpcount = 1;

    # Create an a-z index
    $atoz = "<div class=\"InpageNavLeftBlock\">";
    if ($find == "") {
        $atoz .= "<span class='Selected'>";
    }

    $atoz .= "<a href=\"" . $baseurl_short . "pages/collection_manage.php?col_order_by=name&find=\" onClick=\"return CentralSpaceLoad(this);\">" . $lang["viewall"] . "</a>";
    if ($find == "") {
        $atoz .= "</span>";
    }

    $atoz .= "&nbsp;&nbsp;&nbsp;&nbsp;";
    for ($n = ord("A"); $n <= ord("Z"); $n++) {
        if ($find == chr($n)) {
            $atoz .= "<span class='Selected'>";
        }
        $atoz .= "<a href=\"" . $baseurl_short . "pages/collection_manage.php?col_order_by=name&find=" . chr($n) . "\" onClick=\"return CentralSpaceLoad(this);\">&nbsp;" . chr($n) . "&nbsp;</a> ";
        if ($find == chr($n)) {
            $atoz .= "</span>";
        }
        $atoz .= " ";
    }

    $atoz .= "</div>";

    $url = $baseurl_short . "pages/collection_manage.php?paging=true&col_order_by=" . urlencode($col_order_by) . "&sort=" . urlencode($sort) . "&find=" . urlencode($find) . "";
    ?>

    <div class="TopInpageNav">
        <div class="TopInpageNavLeft">
            <?php echo $atoz; ?>
            <div class="InpageNavLeftBlock">
                <?php echo escape($lang["resultsdisplay"]); ?>:
                <?php
                for ($n = 0; $n < count($list_display_array); $n++) {
                    if ($per_page == $list_display_array[$n]) {
                        ?>
                        <span class="Selected"><?php echo escape($list_display_array[$n]) ?></span>
                        <?php
                    } else {
                        ?>
                        <a href="<?php echo $url; ?>&per_page_list=<?php echo urlencode($list_display_array[$n])?>" onclick="return CentralSpaceLoad(this);">
                            <?php echo escape($list_display_array[$n]); ?>
                        </a>
                        <?php
                    } ?>&nbsp;|
                    <?php
                }

                if ($per_page == 99999) {
                    ?>
                    <span class="Selected"><?php echo escape($lang["all"])?></span>
                    <?php
                } else {
                    ?>
                    <a href="<?php echo $url; ?>&per_page_list=99999" onclick="return CentralSpaceLoad(this);">
                        <?php echo escape($lang["all"]); ?>
                    </a>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php pager(false, true, array("confirm_page_change" => "return promptBeforePaging();")); ?>
        <div class="clearerleft"></div>
    </div>

    <script>
        function check_delete_all(select_all) {
            var check_value = select_all.checked;
            var all_checkboxes = document.getElementsByClassName("check_delete");

            for (var i = 0; i < all_checkboxes.length; i++)  {
                all_checkboxes[i].checked = check_value;
            }
            show_delete();
        }

        function show_delete() {
            var display_opt = "hidden";
            var all_checkboxes = document.getElementsByClassName("check_delete");

            for (var i = 0; i < all_checkboxes.length; i++)  {
                if (all_checkboxes[i].checked == true) {
                    display_opt = "visible";
                    break;
                }
            }

            document.getElementById("collection_delete").style.visibility = display_opt;
        }

        function delete_collections() {
            var all_checkboxes = document.getElementsByClassName("check_delete");
            var to_delete = "";

            for (var i = 0; i < all_checkboxes.length; i++)  {
                if (all_checkboxes[i].checked == true) {
                    if (to_delete != "") {
                        to_delete += ",";
                    }
                    to_delete += all_checkboxes[i].value;
                }
            }

            if (to_delete != "") {
                if (confirm('<?php echo escape($lang["delete_multiple_collections"]) ?>')) {
                    var post_data =  {
                        ajax: true,
                        dropdown_actions: true,
                        delete: to_delete,
                        <?php echo generateAjaxToken("delete_collection"); ?>
                    };

                    jQuery.post('<?php echo $baseurl; ?>/pages/collection_manage.php', post_data, function(response) {
                        if (response.success === 'Yes') {
                            CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection=' + response.redirect_to_collection + '&k=' + response.k + '&nc=' + response.nc);
                            CentralSpaceLoad(document.URL);
                        }
                    }, 'json');    
                }
            }
        }

        jQuery(document).ready(function() {
            var collection_starting = null; // Regular click collection marks the start of a range
            var collection_ending = null; // Shifted click collection marks the end of a range
            var primary_action = null;

            // Process the clicked box
            jQuery(".check_delete").click(function(e) {
                var collection_selections = [];
                var input = e.target;
                var box_collection = jQuery(input).prop("value");
                var box_checked = jQuery(input).prop("checked");

                if (!e.shiftKey) {
                    // Regular click; note the action required if there is a range to be processed
                    primary_action = box_checked;
                    collection_starting = box_collection;
                    collection_ending = null;
                } else {
                    if (!collection_starting) {
                        styledalert('<?php echo escape($lang["range_no_start_header"]); ?>', '<?php echo escape($lang["range_no_start"]); ?>');
                        if (jQuery(input).prop("checked")) {
                            this.removeAttribute("checked");
                        } else {
                            this.setAttribute("checked", "checked");
                        }
                        return false;
                    }
                    collection_ending = box_collection; // Shifted click collection
                }

                // Process all clicked boxes
                jQuery(".check_delete").each(function() {
                    // Fetch the event and store it in the selection array
                    var toggle_event = jQuery.Event("click", { target: this });
                    var toggle_input = toggle_event.target;
                    var box_collection = jQuery(toggle_input).prop("value");
                    var box_checked = jQuery(toggle_input).prop("checked");
                    collection_selections.push({box_collection: box_collection, box_checked: box_checked});
                });

                // Process collections within a clicked range
                var res_list = [];

                if (collection_starting && collection_ending) {
                    console.log("PROCESS " + collection_starting + " TO " + collection_ending);
                    var found_start = false;
                    var found_end = false;

                    for (i = 0; i < collection_selections.length; i++) {
                        if (collection_selections[i].box_collection == collection_starting) {
                            // Range starting point is being processed; skip because already processed by single shot; move on
                            found_start = true;
                        } else if (collection_selections[i].box_collection == collection_ending) {
                            // Range ending point is being processed; process it and move on (because it may be before the startin point)
                            found_end = true;
                            res_list.push(collection_selections[i].box_collection); // collection to process
                        } else {
                            // Element is not at the starting point or ending point; check whether its within the range
                            if ( !found_start && !found_end ) {
                                // Range is not yet being processed; skip
                            } else if (found_start && found_end) {
                                // Both starting and ending points have been processed; quit loop
                                break;
                            } else {
                                // Process the element within the range
                                res_list.push(collection_selections[i].box_collection); // collection to process
                            }
                        }
                    }

                    collection_selections.forEach(function (collection) {
                        if (res_list.includes(collection.box_collection)) {
                            jQuery("#check_" + collection.box_collection).prop('checked', true);
                        }
                    });

                    // Reset processing points
                    collection_starting = null;
                    collection_ending = null;
                    primary_action = null;
                } else if (collection_starting) {
                    console.log("PROCESS " + collection_starting + " ONLY");
                } else if (collection_ending) {
                    console.log("ERROR - ENDING ONLY");
                }

                console.log("collection_LIST\n" + JSON.stringify(res_list));
            });

            // Load collection actions when dropdown is clicked
            jQuery('.collectionactions').on("mouseover", function(e) {
                var el = jQuery(this);

                if (el.attr('data-actions-populating') != '0') {
                    return false
                }

                el.attr('data-actions-populating','1');
                var action_selection_id = el.attr('id');
                var colref = el.attr('data-col-id');
                LoadActions('collections',action_selection_id,'collection',colref);
            });
        });

        // Add confirmation message to advise selected collections will be cleared on paging.
        function promptBeforePaging() {
            if (document.getElementById("collection_delete").style.visibility == "visible") {
                $proceed = confirm('<?php echo escape($lang["page_collections_message"]) ?>');
                return $proceed;
            }
        }
    </script>

    <a id="collection_delete" style="visibility:hidden; margin-left:10px" title="<?php echo escape($lang["delete_all_selected"]) ?>" onclick="delete_collections()">
        <i aria-hidden="true" class="icon-trash-2"></i>
    </a>

    <form method=post id="collectionform" action="<?php echo $baseurl_short?>pages/collection_manage.php">
        <?php generateFormToken("collectionform"); ?>
        <input type=hidden name="delete" id="collectiondelete" value="">
        <input type=hidden name="remove" id="collectionremove" value="">
        <input type=hidden name="add" id="collectionadd" value="">
        <input type=hidden name="collection_delete_multiple" id="collection_delete_multiple" value="">

        <div class="Listview">
            <table class="ListviewStyle">
                <tr class="ListviewTitleStyle">
                    <th>
                        <input type="checkbox" onclick="check_delete_all(this)">
                    </th>

                    <th class="name">
                        <?php if ($col_order_by == "name") { ?>
                            <span class="Selected">
                        <?php } ?>
                        <a href="<?php echo $baseurl_short?>pages/collection_manage.php?offset=0&col_order_by=name&sort=<?php echo urlencode($revsort)?>&find=<?php echo urlencode($find)?>" onclick="return CentralSpaceLoad(this);">
                            <?php echo escape($lang["collectionname"]); ?>
                        </a>
                        <?php if ($col_order_by == "name") { ?>
                            <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                        <?php } ?>
                    </th>

                    <th class="fullname">
                        <?php if ($col_order_by == "fullname") { ?>
                            <span class="Selected">
                        <?php } ?>
                        <a href="<?php echo $baseurl_short?>pages/collection_manage.php?offset=0&col_order_by=fullname&sort=<?php echo urlencode($revsort)?>&find=<?php echo urlencode($find)?>" onclick="return CentralSpaceLoad(this);">
                            <?php echo escape($lang["owner"]); ?>
                        </a>
                        <?php if ($col_order_by == "fullname") { ?>
                            <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                        <?php } ?>
                    </th>

                    <th class="ref">
                        <?php if ($col_order_by == "ref") { ?>
                            <span class="Selected">
                        <?php } ?>
                        <a href="<?php echo $baseurl_short?>pages/collection_manage.php?offset=0&col_order_by=ref&sort=<?php echo urlencode($revsort)?>&find=<?php echo urlencode($find)?>" onclick="return CentralSpaceLoad(this);">
                            <?php echo escape($lang["id"]); ?>
                        </a>
                        <?php if ($col_order_by == "ref") { ?>
                            <div class="<?php echo urlencode($sort)?>">&nbsp;</div>
                        <?php } ?>
                    </th>

                    <th class="created">
                        <?php if ($col_order_by == "created") { ?>
                            <span class="Selected">
                        <?php } ?>
                        <a href="<?php echo $baseurl_short?>pages/collection_manage.php?offset=0&col_order_by=created&sort=<?php echo urlencode($revsort)?>&find=<?php echo urlencode($find)?>" onclick="return CentralSpaceLoad(this);">
                            <?php echo escape($lang["created"]); ?>
                        </a>
                        <?php if ($col_order_by == "created") { ?>
                            <div class="<?php echo urlencode($sort); ?>">&nbsp;</div>
                        <?php } ?>
                    </th>

                    <th class="count">
                        <?php if ($col_order_by == "count") { ?>
                            <span class="Selected">
                        <?php } ?>
                        <a href="<?php echo $baseurl_short?>pages/collection_manage.php?offset=0&col_order_by=count&sort=<?php echo urlencode($revsort)?>&find=<?php echo urlencode($find)?>" onclick="return CentralSpaceLoad(this);">
                            <?php echo escape($lang["itemstitle"]); ?>
                        </a>
                        <?php if ($col_order_by == "count") { ?>
                            <div class="<?php echo urlencode($sort)?>">&nbsp;</div>
                        <?php } ?>
                    </th>

                    <th class="access">
                        <?php if ($col_order_by == "type") { ?>
                            <span class="Selected">
                        <?php } ?>
                        <a href="<?php echo $baseurl_short?>pages/collection_manage.php?offset=0&col_order_by=type&sort=<?php echo urlencode($revsort)?>&find=<?php echo urlencode($find)?>" onclick="return CentralSpaceLoad(this);">
                            <?php echo escape($lang["access"])?>
                        </a>
                        <?php if ($col_order_by == "type") { ?>
                            <div class="<?php echo urlencode($sort)?>">&nbsp;</div>
                        <?php } ?>
                    </th>

                    <th class="collectionin"><?php echo escape($lang["showcollectionindropdown"]) ?></th>

                    <th class="tools">
                        <div class="ListTools"><?php echo escape($lang['actions'])?></div>
                    </th>
                </tr>

                <form method="get" name="colactions" id="colactions" action="<?php echo $baseurl_short?>pages/collection_manage.php">
                    <?php
                    for ($n = $offset; (($n < count($collections)) && ($n < ($offset + $per_page))); $n++) {
                        if ($collections[$n]['ref'] < 0) {
                            // Don't show special 'new uploads' collection
                            continue;
                        }

                        $colusername = $collections[$n]['fullname'];
                        $count_result = $collections[$n]["count"];
                        ?>
                        <tr>
                            <td>
                                <?php if (can_delete_collection($collections[$n], $userref, $k)) {
                                    echo '<input type="checkbox" class="check_delete" id="check_' . $collections[$n]['ref'] . '" value="' . $collections[$n]['ref'] . '" onClick="show_delete()">';
                                } ?>
                            </td>
                            <td class="name">
                                <div class="ListTitle">
                                    <a
                                        <?php if ($collections[$n]["type"] == COLLECTION_TYPE_FEATURED) { ?>
                                            style="font-style: italic;"
                                        <?php } ?>
                                        href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!collection" . $collections[$n]["ref"])?>"
                                        onclick="return CentralSpaceLoad(this);"
                                    >
                                        <?php echo strip_tags_and_attributes(htmlspecialchars_decode(i18n_get_collection_name($collections[$n]))); ?>
                                    </a>
                                </div>
                            </td>
                            <td class="fullname"><?php echo strip_tags_and_attributes($colusername ?? ""); ?></td>
                            <td class="ref"><?php echo strip_tags_and_attributes($collections[$n]["ref"]); ?></td>
                            <td class="created"><?php echo nicedate($collections[$n]["created"], true); ?></td>
                            <td class="count"><?php echo $collections[$n]["count"]; ?></td>
                            <td class="access">
                                <?php
                                switch ($collections[$n]["type"]) {
                                    case COLLECTION_TYPE_PUBLIC:
                                        echo escape($lang["public"]);
                                        break;

                                    case COLLECTION_TYPE_FEATURED:
                                        echo escape($lang["theme"]);
                                        break;

                                    case COLLECTION_TYPE_STANDARD:
                                    default:
                                        echo escape($lang["private"]);
                                        break;
                                }
                                ?>
                            </td>
                            <td class="collectionin">
                                <input
                                    type="checkbox"
                                    onclick='UpdateHiddenCollections(this, "<?php echo $collections[$n]['ref']; ?>", {<?php echo generateAjaxToken("colactions"); ?>});'
                                    <?php if (!in_array($collections[$n]['ref'], $hidden_collections)) {
                                        echo "checked";
                                    } ?>
                                >
                            </td>

                            <?php $action_selection_id = 'collections_action_selection' . $collections[$n]['ref']  . "_bottom_" . $collections[$n]["ref"]; ?>
                            
                            <td class="tools">  
                                <div class="ListTools">
                                    <div class="ActionsContainer">
                                        <select class="collectionactions" id="<?php echo $action_selection_id ?>" data-actions-loaded="0" data-actions-populating="0" data-col-id="<?php echo $collections[$n]["ref"];?>" onchange="action_onchange_<?php echo $action_selection_id ?>(this.value);">
                                            <option><?php echo escape($lang["actions-select"]); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <input type=hidden name="deleteempty" id="collectiondeleteempty" value="">
                        <?php
                    }
                    ?>
                </form>
            </table>
        </div>
    </form>

    <div class="BottomInpageNav">
        <div class="BottomInpageNavLeft">
            <?php
            // count how many collections are owned by the user versus just shared, and show at top
            $mycollcount = 0;
            $othcollcount = 0;

            for ($i = 0; $i < count($collections); $i++) {
                if ($collections[$i]['user'] == $userref) {
                    $mycollcount++;
                } else {
                    $othcollcount++;
                }
            }

            $collcount = count($collections);
            echo strip_tags_and_attributes($collcount == 1 ? $lang["total-collections-1"] : str_replace("%number", $collcount, $lang["total-collections-2"]));
            echo " " . strip_tags_and_attributes($mycollcount == 1 ? $lang["owned_by_you-1"] : str_replace("%mynumber", $mycollcount, $lang["owned_by_you-2"])) . "<br />";
            # The number of collections should never be equal to zero.
            ?>
        </div>

        <?php pager(false, true, array("confirm_page_change" => "return promptBeforePaging();")); ?>
        <div class="clearerleft"></div>
    </div>
</div>

<!--Create a collection-->
<div class="BasicsBox">
    <h1><?php echo escape($lang["createnewcollection"])?></h1>
    <p class="tight"><?php echo escape(text("newcollection")); ?></p>
    <form method="post" action="<?php echo $baseurl_short?>pages/collection_manage.php">
        <?php generateFormToken("newcollection"); ?>
        <div class="Question">
            <label for="newcollection"><?php echo escape($lang["collectionname"])?></label>
            <div class="tickset">
                <div class="Inline">
                    <input type=text name="name" id="newcollection" value="" maxlength="100" class="shrtwidth">
                </div>
                <div class="Inline">
                    <input name="Submit" type="submit" value="<?php echo escape($lang["create"])?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<!--Find a collection-->
<?php if ($enable_public_collections) { ?>
    <div class="BasicsBox">
        <h1><?php echo escape($lang["findpubliccollection"])?></h1>
        <p class="tight"><?php echo escape(text("findpublic")); ?></p>
        <p>
            <?php echo LINK_CARET ?>
            <a href="<?php echo $baseurl_short?>pages/collection_public.php" onclick="return CentralSpaceLoad(this,true);">
                <?php echo escape($lang["findpubliccollection"]); ?>
            </a>
        </p>
    </div>
<?php } ?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["view_shared_collections"])?></h1>
    <p>
        <a href="<?php echo $baseurl_short?>pages/view_shares.php" onclick="return CentralSpaceLoad(this,true);">
            <?php echo LINK_CARET . escape($lang["view_shared_collections"]); ?>
        </a>
    </p>
</div>

<?php
include "../include/footer.php";
?>
