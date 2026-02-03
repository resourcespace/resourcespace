<?php
# Collections functions
# Functions to manipulate collections

use Montala\ResourceSpace\CommandPlaceholderArg;

/**
 * Return all collections belonging to or shared with $user
 *
 * @param  integer $user
 * @param  string $find A search string
 * @param  string $order_by Column to sort by
 * @param  string $sort ASC or DESC sort order
 * @param  integer $fetchrows   How many rows to fetch
 * @param  boolean $auto_create Create a standard "Default Collection" if one doesn't exist
 * @return array
 */
function get_user_collections($user, $find = "", $order_by = "name", $sort = "ASC", $fetchrows = -1, $auto_create = true)
{
    global $usergroup, $themes_in_my_collections, $rs_session;
    global $anonymous_login,$username,$anonymous_user_session_collection;

    $condsql = "";
    $condparams = [];
    $keysql = "";
    $keyparams = [];
    $extrasql = "";
    $extraparams = [];
    $sort = strtoupper($sort) == "ASC" ? "ASC" : "DESC";

    if ($find == "!shared") {
        # only return shared collections
        $condsql = " WHERE (c.`type` = ? OR c.ref IN (SELECT DISTINCT collection FROM user_collection WHERE user<>? UNION SELECT DISTINCT collection FROM external_access_keys))";
        $condparams = array("i",COLLECTION_TYPE_PUBLIC,"i",$user);
    } elseif (strlen($find) == 1 && !is_numeric($find)) {
        # A-Z search
        $condsql = " WHERE c.name LIKE ?";
        $condparams = array("s",$find . "%");
    } elseif (strlen($find) > 1 || is_numeric($find)) {
        $keywords = split_keywords($find);
        $keysql = "";
        $keyparams = array();
        for ($n = 0; $n < count($keywords); $n++) {
            $keyref = resolve_keyword($keywords[$n], false);
            if ($keyref === false) {
                continue;
            }

            $keysql .= " JOIN collection_keyword k" . $n . " ON k" . $n . ".collection=ref AND (k" . $n . ".keyword=?)";
            $keyparams = array_merge($keyparams, ['i', $keyref]);
        }
    }

    $validtypes = [COLLECTION_TYPE_STANDARD, COLLECTION_TYPE_PUBLIC, COLLECTION_TYPE_REQUEST];
    if ($themes_in_my_collections) {
        $validtypes[] = COLLECTION_TYPE_FEATURED;
    }
    $condsql .= $condsql == "" ? "WHERE" : " AND";
    $condsql .= " c.`type` IN (" . ps_param_insert(count($validtypes)) . ")";
    $condparams =  array_merge($condparams, ps_param_fill($validtypes, "i"));

    if ($themes_in_my_collections) {
        // If we show featured collections, remove the categories
        $keysql .= " WHERE (clist.`type` IN (?,?,?) OR (clist.`type` = ? AND clist.`count` > 0))";
        $keyparams[] = "i";
        $keyparams[] = COLLECTION_TYPE_STANDARD;
        $keyparams[] = "i";
        $keyparams[] = COLLECTION_TYPE_PUBLIC;
        $keyparams[] = "i";
        $keyparams[] = COLLECTION_TYPE_REQUEST;
        $keyparams[] = "i";
        $keyparams[] = COLLECTION_TYPE_FEATURED;
    }

    if (isset($anonymous_login) && ($username == $anonymous_login) && $anonymous_user_session_collection) {
        // Anonymous user - only get the user's own collections that are for this session - although we can still join to
        // get collections that have been specifically shared with the anonymous user
        if ('' == $condsql) {
            $extrasql = " WHERE ";
        } else {
            $extrasql .= " AND ";
        }

        $extrasql .= " (c.session_id=?)";
        $extraparams = array("i",$rs_session);
    }

    $order_sort = "";
    $validsort =  array("name","ref","user","created","public","home_page_publish","type","parent");
    if ($order_by != "name" && in_array(strtolower($order_by), $validsort)) {
        $order_sort = " ORDER BY $order_by $sort";
    }

    // Control the selected columns. $query_select_columns is for the outer SQL and $collection_select_columns
    // is for the inner one. Both have some extra columns from the user & resource table.
    $collection_table_columns = [
        'ref',
        'name',
        'user',
        'created',
        'public',
        'allow_changes',
        'cant_delete',
        'keywords',
        'savedsearch',
        'home_page_publish',
        'home_page_text',
        'home_page_image',
        'session_id',
        'description',
        'type',
        'parent',
        'thumbnail_selection_method',
        'bg_img_resource_ref',
        'order_by',
    ];
    $query_select_columns = implode(', ', $collection_table_columns) . ', username, fullname, count';
    $collection_select_columns = [];
    foreach ($collection_table_columns as $column_name) {
        $collection_select_columns[] = "c.{$column_name}";
    }
    $collection_select_columns = implode(', ', $collection_select_columns) . ', u.username, u.fullname, count(r.resource) AS count';

    $query = "SELECT {$query_select_columns}
                FROM (
                         SELECT {$collection_select_columns}
                           FROM user AS u
                           JOIN collection AS c ON u.ref = c.user AND c.user = ?
                LEFT OUTER JOIN collection_resource AS r ON c.ref = r.collection
                       $condsql 
                       $extrasql
                       GROUP BY c.ref
        
                          UNION
                         SELECT {$collection_select_columns}
                           FROM user_collection AS uc
                           JOIN collection AS c ON uc.collection = c.ref AND uc.user = ? AND c.user <> ?
                LEFT OUTER JOIN collection_resource AS r ON c.ref = r.collection
                      LEFT JOIN user AS u ON c.user = u.ref
                       $condsql
                       GROUP BY c.ref
        
                          UNION
                         SELECT {$collection_select_columns}
                           FROM usergroup_collection AS gc
                           JOIN collection AS c ON gc.collection = c.ref AND gc.usergroup = ? AND c.user <> ?
                LEFT OUTER JOIN collection_resource AS r ON c.ref = r.collection
                      LEFT JOIN user AS u ON c.user = u.ref
                       $condsql
                        GROUP BY c.ref
        ) AS clist
        $keysql
        GROUP BY ref $order_sort";

    $queryparams = array_merge(
        array("i",$user),
        $condparams,
        $extraparams,
        array("i", $user,"i", $user),
        $condparams,
        array("i", $usergroup,"i",$user),
        $condparams,
        $keyparams
    );

    $return = ps_query($query, $queryparams, 'collection_access' . $user);

    if ($order_by == "name") {
        if ($sort == "ASC") {
            usort($return, 'collections_comparator');
        } elseif ($sort == "DESC") {
            usort($return, 'collections_comparator_desc');
        }
    }

    // To keep Default Collection creation consistent: Check that user has at least one collection of his/her own  (not if collection result is empty, which may include shares),
    $hasown = false;
    for ($n = 0; $n < count($return); $n++) {
        if ($return[$n]['user'] == $user) {
            $hasown = true;
        }
    }

    if (!$hasown && $auto_create && $find == "") { # User has no collections of their own, and this is not a search. Make a new 'Default Collection'
        # No collections of one's own? The user must have at least one Default Collection
        global $usercollection;
        $usercollection = create_collection($user, "Default Collection", 0, 1); // make not deletable
        set_user_collection($user, $usercollection);

        # Recurse to send the updated collection list.
        return get_user_collections($user, $find, $order_by, $sort, $fetchrows, false);
    }

    return $return;
}

$GLOBALS['get_collection_cache'] = array();
/**
 * Returns all data for collection $ref.
 *
 * @param  int  $ref        Collection ID
 * @param bool  $usecache   Optionally retrieve from cache
 *
 * @return array|boolean
 */
function get_collection($ref, $usecache = false)
{
    global $lang, $userref,$k;
    if (isset($GLOBALS['get_collection_cache'][$ref]) && $usecache) {
        return $GLOBALS['get_collection_cache'][$ref];
    }

    $columns = ", u.fullname, u.username";

    $return = ps_query("SELECT " . columns_in('collection', 'c') . $columns . " FROM collection c LEFT OUTER JOIN user u ON u.ref = c.user WHERE c.ref = ?", array("i",$ref));

    if (count($return) == 0) {
        return false;
    } else {
        $return = $return[0];
        $users = ps_array("SELECT u.username value FROM user u,user_collection c WHERE u.ref=c.user AND c.collection = ? ORDER BY u.username", array("i",$ref));
        $return["users"] = join(", ", $users);

        $groups = ps_array("SELECT concat('" . $lang["groupsmart"] . ": ',u.name) value FROM usergroup u,usergroup_collection c WHERE u.ref = c.usergroup AND c.collection = ? ORDER BY u.name", array("i",$ref));
        $return["groups"] = join(", ", $groups);


        $request_feedback = 0;
        if ($return["user"] != $userref) {
            # If this is not the user's own collection, fetch the user_collection row so that the 'request_feedback' property can be returned.
            $request_feedback = ps_value("SELECT request_feedback value FROM user_collection WHERE collection = ? AND user = ?", array("i",$ref,"i",$userref), 0);
            if (!$request_feedback && $k == "") {
                # try to set via usergroup_collection
                global $usergroup;
                $request_feedback = ps_value("SELECT request_feedback value FROM usergroup_collection WHERE collection = ? AND usergroup = ?", array("i",$ref,"i",$usergroup), 0);
            }
        }
        if ($k != "") {
            # If this is an external user (i.e. access key based) then fetch the 'request_feedback' value from the access keys table
            $request_feedback = ps_value("SELECT request_feedback value FROM external_access_keys WHERE access_key = ? AND request_feedback = 1", array("s",$k), 0);
        }

        $return["request_feedback"] = $request_feedback;

        // Legacy property which is now superseded by types. FCs need to be public before they can be put under a category by an admin (perm h)
        global $COLLECTION_PUBLIC_TYPES;
        $return["public"] = (int) in_array($return["type"], $COLLECTION_PUBLIC_TYPES);

        $GLOBALS['get_collection_cache'][$ref] = $return;
        return $return;
    }
}

/**
 * Returns all resources in collection
 *
 * @param  int  $collection   ID of collection being requested
 *
 * @return array|boolean
 */
function get_collection_resources($collection)
{
    global $userref;

    # For many cases (e.g. when displaying a collection for a user) a search is used instead so permissions etc. are honoured.
    if (!is_int_loose($collection)) {
        return false;
    }

    # Check if review collection if so delete any resources moved out of users archive status permissions by other users
    if ((string)$collection == "-" . $userref) {
        collection_cleanup_inaccessible_resources($collection);
    }

    $plugin_collection_resources = hook('replace_get_collection_resources', "", array($collection));
    if (is_array($plugin_collection_resources)) {
        return $plugin_collection_resources;
    }

    return ps_array("SELECT resource value FROM collection_resource WHERE collection = ? ORDER BY sortorder ASC, date_added DESC, resource ASC", array("i",$collection));
}

/**
* Get all resources in a collection without checking permissions or filtering by workflow states.
* This is useful when you want to get all the resources for further subprocessing (@see render_selected_collection_actions()
* as an example)
*
* @param integer $ref Collection ID
*
* @return array
*/
function get_collection_resources_with_data($ref)
{
    if (!is_numeric($ref)) {
        return array();
    }

    $result = ps_query(
        "
            SELECT r.*
              FROM collection_resource AS cr
        RIGHT JOIN resource AS r ON cr.resource = r.ref
             WHERE cr.collection = ?
          ORDER BY cr.sortorder ASC , cr.date_added DESC , cr.resource DESC
    ",
        array("i",$ref)
    );

    if (!is_array($result)) {
        return array();
    }

    return $result;
}

/**
 * Add resource $resource to collection $collection
 *
 * @param  integer  $resource
 * @param  integer  $collection
 * @param  boolean  $smartadd
 * @param  string   $size
 * @param  string   $addtype
 * @param  boolean  $col_access_control     Collection access control. Is user allowed to add to it? You can leave it null
 *                                          to allow this function to determine it but it may have performance issues.
 * @param  array    $external_shares        List of external share keys. {@see get_external_shares()}. You can leave it null
 *                                          to allow this function to determine it but it will affect performance.
 * @param  string   $search                 Optionsl search string. Used to update resource_node hit count
 *
 * @param  integer  $sort_order             Sort order of resource in collection
 *
 * @return boolean | string
 */
function add_resource_to_collection(
    $resource,
    $collection,
    $smartadd = false,
    $size = "",
    $addtype = "",
    ?bool $col_access_control = null,
    ?array $external_shares = null,
    string $search = '',
    ?int $sort_order = null
) {
    global $lang;

    if (!is_int_loose($collection) || !is_int_loose($resource)) {
        return $lang["cantmodifycollection"];
    }

    $resource_data = ps_query('SELECT resource_type, archive FROM `resource` WHERE ref = ?;', array('i', $resource));
    if (count($resource_data) !== 1) {
        return $lang["cantmodifycollection"] . ' ' . $lang["resourcenotfound"];
    }

    global $collection_allow_not_approved_share, $collection_block_restypes;
    $addpermitted = $col_access_control ?? (
        (collection_writeable($collection) && !is_featured_collection_category_by_children($collection))
        || $smartadd
    );

    if ($addpermitted && !$smartadd && (count($collection_block_restypes) > 0)) { // Can't always block adding resource types since this may be a single resource managed request
        if ($addtype == "") {
            $addtype = $resource_data[0]['resource_type'];
        }
        if (in_array($addtype, $collection_block_restypes)) {
            $addpermitted = false;
        }
    }

    if ($addpermitted) {
        $collection_data = get_collection($collection, true);

        // If this is a featured collection apply all the external access keys from the categories which make up its
        // branch path to prevent breaking existing shares for any of those featured collection categories.
        $fc_branch_path_keys = [];
        if ($collection_data !== false && $collection_data['type'] === COLLECTION_TYPE_FEATURED) {
            $branch_category_ids = array_column(
                // determine the branch from the parent because the keys for the collection in question will be done below
                get_featured_collection_category_branch_by_leaf((int)$collection_data['parent'], []),
                'ref'
            );
            foreach ($branch_category_ids as $fc_category_id) {
                $fc_branch_path_keys = array_merge(
                    $fc_branch_path_keys,
                    get_external_shares([
                        'share_collection' => $fc_category_id,
                        'share_type' => 0,
                        'ignore_permissions' => true
                    ])
                );
            }
        }


        # Check if this collection has already been shared externally. If it has, we must fail if not permitted or add a further entry
        # for this specific resource, and warn the user that this has happened.
        $keys = array_merge(
            $external_shares ?? get_external_shares(array("share_collection" => $collection,"share_type" => 0,"ignore_permissions" => true)),
            $fc_branch_path_keys
        );
        if (count($keys) > 0) {
            $archivestatus = $resource_data[0]['archive'];
            if ($archivestatus < 0 && !$collection_allow_not_approved_share) {
                global $lang;
                $lang["cantmodifycollection"] = $lang["notapprovedresources"] . $resource;
                return false;
            }

            // Check if user can share externally and has open access. We shouldn't add this if they can't share externally, have restricted access or only been granted access
            if (!can_share_resource($resource)) {
                return false;
            }

            # Set the flag so a warning appears.
            global $collection_share_warning;
            # Check to see if all shares have expired
            $expiry_dates = ps_array("SELECT DISTINCT expires value FROM external_access_keys WHERE collection = ?", ["i",$collection]);
            $datetime = time();
            $collection_share_warning = true;
            foreach ($expiry_dates as $date) {
                if ($date != "" && $date < $datetime) {
                    $collection_share_warning = false;
                }
            }

            for ($n = 0; $n < count($keys); $n++) {
                # Insert a new access key entry for this resource/collection.
                global $userref;
                ps_query(
                    'INSERT INTO external_access_keys(resource, access_key, user, collection, `date`, expires, access, usergroup, password_hash) VALUES (?, ?, ?, ?, now(), ?, ?, ?, ?)',
                    [
                        'i', $resource,
                        's', $keys[$n]['access_key'],
                        'i', $userref,
                        'i', $collection ?: null,
                        's', $keys[$n]['expires'] ?: null,
                        'i', $keys[$n]['access'],
                        'i', $keys[$n]['usergroup'] ?: null,
                        's', $keys[$n]['password_hash'] ?: null,
                    ]
                );
                collection_log($collection, LOG_CODE_COLLECTION_SHARED_RESOURCE_WITH, $resource, $keys[$n]["access_key"]);
            }
        }

        hook("Addtocollectionsuccess", "", array("resourceId" => $resource, "collectionId" => $collection));

        ps_query('DELETE FROM collection_resource WHERE collection = ? AND resource = ?', ['i', $collection, 'i', $resource]);
        ps_query(
            'INSERT INTO collection_resource(collection, resource, sortorder) VALUES (?, ?, ?)',
            ['i', $collection, 'i', $resource, 'i', $sort_order ?: null]
        );

        # Update the hitcounts for the search nodes (if search specified)
        if (strpos($search, NODE_TOKEN_PREFIX) !== false) {
            update_node_hitcount_from_search($resource, $search);
        }

        if ($collection_data !== false && $collection_data['type'] != COLLECTION_TYPE_SELECTION) {
            collection_log($collection, LOG_CODE_COLLECTION_ADDED_RESOURCE, $resource);
        }

        // Clear theme image cache
        clear_query_cache("themeimage");
        clear_query_cache('col_total_ref_count_w_perm');

        return true;
    } else {
        return $lang["cantmodifycollection"];
    }
}

/**
 * Remove resource $resource from collection $collection
 *
 * @param  integer $resource
 * @param  integer $collection
 * @param  boolean $smartadd
 * @return boolean | string
 */
function remove_resource_from_collection($resource, $collection, $smartadd = false)
{
    global $lang;

    if ((string)(int)$collection != (string)$collection || (string)(int)$resource != (string)$resource) {
        return $lang["cantmodifycollection"];
    }

    if ($smartadd || collection_writeable($collection)) {
        hook("Removefromcollectionsuccess", "", array("resourceId" => $resource, "collectionId" => $collection));
        $delparams = ["i",$resource,"i",$collection];
        ps_query("DELETE FROM collection_resource WHERE resource = ? AND collection = ?", $delparams);
        ps_query("DELETE FROM external_access_keys WHERE resource = ? AND collection = ?", $delparams);

        // log this
        collection_log($collection, LOG_CODE_COLLECTION_REMOVED_RESOURCE, $resource);

        // Clear theme image cache
        clear_query_cache("themeimage");
        clear_query_cache('col_total_ref_count_w_perm');

        return true;
    } else {
        return $lang["cantmodifycollection"];
    }
}

/**
 * Add resource(s) $resources to collection $collection
 *
 * @param  mixed $resources
 * @param  mixed $collection
 * @return boolean | string
 */
function collection_add_resources($collection, $resources = '', $search = '', $selected = false)
{
    global $USER_SELECTION_COLLECTION,$lang;
    if (
            !is_int_loose($collection)
        ||  ($resources == '' && $search == '')
        ||  !collection_writeable($collection)
        ||  is_featured_collection_category_by_children($collection)
    ) {
        return $lang["cantmodifycollection"];
    }
    $access_control = true;
    $external_share_keys = get_external_shares([
        'share_collection' => $collection,
        'share_type' => 0,
        'ignore_permissions' => true,
    ]);

    if ($selected) {
        $resources = get_collection_resources($USER_SELECTION_COLLECTION);
    } elseif ($resources == '') {
        $resources = do_search($search);
    }

    if ($resources === false) {
        return $lang["noresourcesfound"];
    }
    if (!is_array($resources)) {
        $resources = explode(",", $resources);
    }

    if (count($resources) == 0) {
        return $lang["noresourcesfound"];
    }
    $collection_resources       = get_collection_resources($collection);
    $refs_to_add = array_diff($resources, $collection_resources);

    $errors = 0;
    foreach ($refs_to_add as $ref) {
        if (!add_resource_to_collection($ref, $collection, false, '', '', $access_control, $external_share_keys)) {
            $errors++;
        }
    }

    if ($errors == 0) {
        return true;
    } else {
        return $lang["cantaddresourcestocolection"];
    }
}

/**
 * collection_remove_resources
 *
 * @param  mixed $collection
 * @param  mixed $resources
 * @param  mixed $removeall
 * @return boolean | string
 */
function collection_remove_resources($collection, $resources = '', $removeall = false, $selected = false)
{
    global $USER_SELECTION_COLLECTION,$lang;

    if (
        (string)(int)$collection != (string)$collection
        || ($resources == '' && !$removeall && !$selected)
        || (!collection_writeable($collection))
        || is_featured_collection_category_by_children($collection)
    ) {
        return $lang["cantmodifycollection"];
    }

    if ($removeall) {
        foreach (get_collection_resources($collection) as $ref) {
            remove_resource_from_collection($ref, $collection);
        }
        return true;
    }

    if ($selected) {
        $resources = get_collection_resources($USER_SELECTION_COLLECTION);
    }
    if ($resources === false) {
        return $lang["noresourcesfound"];
    }

    $collection_resources       = get_collection_resources($collection);

    if (!is_array($resources)) {
        $resources = explode(",", $resources);
    }
    $refs_to_remove = array_intersect($collection_resources, $resources);

    $errors = 0;
    foreach ($refs_to_remove as $ref) {
        if (!remove_resource_from_collection($ref, $collection)) {
            $errors++;
        }
    }

    if ($errors == 0) {
        return true;
    } else {
        return $lang["cantremoveresourcesfromcollection"];
    }
}

/**
 * Is the collection $collection writable by the current user?
 * Returns true if the current user has write access to the given collection.
 *
 * @param  integer $collection
 * @return boolean
 */
function collection_writeable($collection)
{
    $collectiondata = get_collection($collection, true);
    if ($collectiondata === false) {
        return false;
    }

    global $userref,$usergroup, $allow_smart_collections;
    if (
        $allow_smart_collections && !isset($userref)
        && isset($collectiondata['savedsearch']) && $collectiondata['savedsearch'] != null
    ) {
            return false; // so "you cannot modify this collection"
    }
    if ($collectiondata['type'] == COLLECTION_TYPE_REQUEST && !checkperm('R')) {
        return false;
    }

    # Load a list of attached users
    $attached = ps_array("SELECT user value FROM user_collection WHERE collection = ?", ["i",$collection]);
    $attached_groups = ps_array("SELECT usergroup value FROM usergroup_collection WHERE collection = ?", ["i",$collection]);

    // Can edit if
    // - The user owns the collection (if we are anonymous user and are using session collections then this must also have the same session id )
    // - The user has system setup access (needs to be able to sort out user issues)
    // - Collection changes are allowed and :-
    //    a) User is attached to the collection or
    //    b) Collection is public or a theme and the user either has the 'h' permission or the collection is editable

    global $usercollection,$username,$anonymous_login,$anonymous_user_session_collection, $rs_session;
    debug("collection session : " . $collectiondata["session_id"]);
    debug("collection user : " . $collectiondata["user"]);
    debug("anonymous_login : " . isset($anonymous_login) && is_string($anonymous_login) ? $anonymous_login : "(no)");
    debug("userref : " . $userref);
    debug("username : " . $username);
    debug("anonymous_user_session_collection : " . (($anonymous_user_session_collection) ? "TRUE" : "FALSE"));

    $writable =
        // User either owns collection AND is not the anonymous user, or is the anonymous user with a matching/no session
        ($userref == $collectiondata["user"] && (!isset($anonymous_login) || $username != $anonymous_login || !$anonymous_user_session_collection || $collectiondata["session_id"] == $rs_session))
        // Collection is public AND either they have the 'h' permission OR allow_changes has been set
        || ((checkperm("h") || $collectiondata["allow_changes"] == 1) && $collectiondata["public"] == 1)
        // Collection has been shared but is not public AND user is either attached or in attached group
        || ($collectiondata["allow_changes"] == 1 && $collectiondata["public"] == 0 && (in_array($userref, $attached) || in_array($usergroup, $attached_groups)))
        // System admin
        || checkperm("a")
        // Adding to active upload_share
        || upload_share_active() == $collection
        // This is a request collection and user is an admin user who can approve requests
        || (checkperm("R") && $collectiondata['type'] == COLLECTION_TYPE_REQUEST && checkperm("t"));

    // Check if user has permission to manage research requests. If they do and the collection is research request allow writable.
    if ($writable === false && checkperm("r")) {
        include_once 'research_functions.php';
        $research_requests = get_research_requests();
        $collections = array();
        foreach ($research_requests as $research_request) {
            $collections[] = $research_request["collection"];
        }
        if (in_array($collection, $collections)) {
            $writable = true;
        }
    }

    return $writable;
}

/**
 * Returns true if the current user has read access to the given collection.
 *
 * @param  integer $collection
 * @return boolean
 */
function collection_readable($collection)
{
    global $userref, $usergroup, $ignore_collection_access, $collection_commenting;

    $k = getval('k', '');

    # Fetch collection details.
    if (!is_numeric($collection)) {
        return false;
    }
    $collectiondata = get_collection($collection, true);
    if ($collectiondata === false) {
        return false;
    }

    # Load a list of attached users
    $attached = ps_array("SELECT user value FROM user_collection WHERE collection = ?", ["i",$collection]);
    $attached_groups = ps_array("SELECT usergroup value FROM usergroup_collection WHERE collection = ?", ["i",$collection]);

    # Access if collection_commenting is enabled and request feedback checked
    # Access if it's a public collection (or featured collection to which user has access to)
    # Access if k is not empty or option to ignore collection access is enabled and k is empty
    if (
        ($collection_commenting && $collectiondata['request_feedback'] == 1)
        || $collectiondata['type'] == COLLECTION_TYPE_PUBLIC
        || ($collectiondata['type'] == COLLECTION_TYPE_FEATURED && featured_collection_check_access_control($collection))
        || $k != ""
        || ($k == "" && $ignore_collection_access)
    ) {
        return true;
    }

        # Perform these checks only if a user is logged in
        # Access if:
        #   - It's their collection
        #   - It's a public collection (or featured collection to which user has access to)
        #   - They have the 'access and edit all collections' admin permission
        #   - They are attached to this collection
        #   - Option to ignore collection access is enabled and k is empty
    if (
            is_numeric($userref)
            && ($userref == $collectiondata["user"]
            || $collectiondata['type'] == COLLECTION_TYPE_PUBLIC
            || ($collectiondata['type'] == COLLECTION_TYPE_FEATURED && featured_collection_check_access_control($collection))
            || checkperm("h")
            || in_array($userref, $attached)
            || in_array($usergroup, $attached_groups)
            || checkperm("R")
            || $k != ""
            || ($k == "" && $ignore_collection_access))
    ) {
        return true;
    }

    return false;
}

/**
 * Sets the current collection of $user to be $collection
 *
 * @param  integer $user
 * @param  integer $collection
 * @return void
 */
function set_user_collection($user, $collection)
{
    global $usercollection,$username,$anonymous_login,$anonymous_user_session_collection;
    if (!(isset($anonymous_login) && $username == $anonymous_login) || !$anonymous_user_session_collection) {
        ps_query("UPDATE user SET current_collection = ? WHERE ref = ?", ["i",$collection,"i",$user]);
    }
    $usercollection = $collection;
}

/**
 * Creates a new collection for user $userid called $name
 *
 * @param  integer $userid
 * @param  string $name
 * @param  boolean $allowchanges
 * @param  boolean $cant_delete
 * @param  integer $ref
 * @param  boolean $public
 * @return integer
 */
function create_collection($userid, $name, $allowchanges = 0, $cant_delete = 0, $ref = 0, $public = false, $extraparams = array())
{
    debug_function_call("create_collection", func_get_args());

    global $username,$anonymous_login,$rs_session, $anonymous_user_session_collection;
    if (($username == $anonymous_login && $anonymous_user_session_collection) || upload_share_active()) {
        // We need to set a collection session_id for the anonymous user. Get session ID to create collection with this set
        $rs_session = get_rs_session_id(true);
    } else {
        $rs_session = "";
    }

    $setcolumns = array();
    $extracolopts = array("type",
                        "keywords",
                        "saved_search",
                        "session_id",
                        "description",
                        "savedsearch",
                        "parent",
                        "thumbnail_selection_method",
                    );
    foreach ($extracolopts as $coloption) {
        if (isset($extraparams[$coloption])) {
            $setcolumns[$coloption] = $extraparams[$coloption];
        }
    }

    $setcolumns["name"]             = mb_strcut($name, 0, 100);
    $setcolumns["user"]             = is_numeric($userid) ? $userid : 0;
    $setcolumns["allow_changes"]    = $allowchanges;
    $setcolumns["cant_delete"]      = $cant_delete;
    $setcolumns["public"]           = $public ? COLLECTION_TYPE_PUBLIC : COLLECTION_TYPE_STANDARD;
    if ($ref != 0) {
        $setcolumns["ref"] = (int)$ref;
    }
    if (is_int_loose(trim($rs_session))) {
        $setcolumns["session_id"]   = $rs_session;
    }
    if ($public) {
        $setcolumns["type"]         = COLLECTION_TYPE_PUBLIC;
    }

    $insert_columns = array_keys($setcolumns);
    $insert_values  = array_values($setcolumns);

    $sql = "INSERT INTO collection
            (" . implode(",", $insert_columns) . ", created)
            VALUES
            (" . ps_param_insert(count($insert_values)) . ",NOW())";

    ps_query($sql, ps_param_fill($insert_values, 's'));

    $ref = sql_insert_id();
    index_collection($ref);

    clear_query_cache('collection_access' . $userid);

    return $ref;
}

/**
 * Deletes the collection with reference $ref
 *
 * @param  integer $collection
 * @return boolean|void
 */
function delete_collection($collection)
{
    global $home_dash, $lang;
    if (!is_array($collection)) {
        $collection = get_collection($collection);
    }
    if (!$collection) {
        return false;
    }
    $ref = $collection["ref"];
    $type = $collection["type"];

    if (!collection_writeable($ref) || is_featured_collection_category_by_children($ref)) {
        return false;
    }

    ps_query("DELETE FROM collection WHERE ref=?", array("i",$ref));
    ps_query("DELETE FROM collection_resource WHERE collection=?", array("i",$ref));
    ps_query("DELETE FROM collection_keyword WHERE collection=?", array("i",$ref));
    ps_query("DELETE FROM external_access_keys WHERE collection=?", array("i",$ref));

    if ($home_dash) {
        // Delete any dash tiles pointing to this collection
        $collection_dash_tiles = ps_array("SELECT ref value FROM dash_tile WHERE link LIKE ?", array("s","%search.php?search=!collection" . $ref . "&%"));
        if (count($collection_dash_tiles) > 0) {
            ps_query("DELETE FROM dash_tile WHERE ref IN (" .  ps_param_insert(count($collection_dash_tiles)) . ")", ps_param_fill($collection_dash_tiles, "i"));
            ps_query("DELETE FROM user_dash_tile WHERE dash_tile IN (" .  ps_param_insert(count($collection_dash_tiles)) . ")", ps_param_fill($collection_dash_tiles, "i"));
        }
    }

    collection_log($ref, LOG_CODE_COLLECTION_DELETED_COLLECTION, 0, $collection["name"] . " (" . $lang["owner"] . ":" . $collection["username"] . ")");

    if ($type === COLLECTION_TYPE_FEATURED) {
        clear_query_cache("featured_collections");
    } else {
        /** {@see create_collection()} */
        clear_query_cache("collection_access{$collection['user']}");
    }
}

/**
 * Adds script to page that refreshes the Collection bar
 *
 * @param  integer $collection  Collection id
 * @return void
 */
function refresh_collection_frame($collection = "")
{
    # Refresh the CollectionDiv
    global $baseurl, $headerinsert;

    if (getval("ajax", false)) {
        echo "<script  type=\"text/javascript\">
        CollectionDivLoad(\"" . $baseurl . "/pages/collections.php" . ((getval("k", "") != "") ? "?collection=" . urlencode(getval("collection", $collection)) . "&k=" . urlencode(getval("k", "")) . "&" : "?") . "nc=" . time() . "\");	
        </script>";
    } else {
        $headerinsert .= "<script  type=\"text/javascript\">
        CollectionDivLoad(\"" . $baseurl . "/pages/collections.php" . ((getval("k", "") != "") ? "?collection=" . urlencode(getval("collection", $collection)) . "&k=" . urlencode(getval("k", "")) . "&" : "?") . "nc=" . time() . "\");
        </script>";
    }
}

/**
 * Performs a search for featured collections / public collections.
 *
 * @param  string $search
 * @param  string $order_by
 * @param  string $sort
 * @param  boolean $exclude_themes
 * @param  boolean $exclude_public
 * @param  boolean $include_resources
 * @param  boolean $override_group_restrict
 * @param  integer $fetchrows
 * @return array
 */
function search_public_collections($search = "", $order_by = "name", $sort = "ASC", $exclude_themes = true, $exclude_public = false, $include_resources = false, $override_group_restrict = false, $fetchrows = -1)
{
    global $userref,$public_collections_confine_group,$userref,$usergroup;

    $keysql = "";
    $sql = "";
    $sql_params = [];
    $select_extra = "";
    debug_function_call("search_public_collections", func_get_args());
    // Validate sort & order_by
    $sort = validate_sort_value($sort) ? $sort : 'ASC';
    $valid_order_bys = array("fullname", "name", "ref", "count", "type", "created");
    $order_by = (in_array($order_by, $valid_order_bys) ? $order_by : "name");

    if (strpos($search, "collectiontitle:") !== false) {
        // This includes a specific title search from the advanced search page.
        $searchtitlelength  = 0;
        $searchtitleval     = "";
        $origsearch         = $search;

        // Force quotes around any collectiontitle: search to support old behaviour
        // i.e. to allow split_keywords() to work
        // collectiontitle:*ser * collection* simpleyear:2022
        //  - will be changed to -
        // "collectiontitle:*ser * collection*" simpleyear:2022
        $searchstart = mb_substr($search, 0, strpos($search, "collectiontitle:"));
        $titlepos = strpos($search, "collectiontitle:") + 16;
        $searchend = mb_substr($search, $titlepos);
        if (strpos($searchend, ":") !== false) {
            // Remove any other parts of the search with xxxxx: prefix that relate to other search aspects
            $searchtitleval = explode(":", $searchend)[0];
            $searchtitleparts = explode(" ", $searchtitleval);
            if (count($searchtitleparts) > 1) {
                // The last string relates to the next searched field name/attribute
                array_pop($searchtitleparts);
            }
            // Build new string for searched value
            $searchtitleval = implode(" ", $searchtitleparts);
            $searchtitlelength = strlen($searchtitleval);
            if (substr($searchtitleval, -1, 1) == ",") {
                $searchtitleval = substr($searchtitleval, 0, -1);
            }
            // Add quotes
            $search = $searchstart . ' "' . "collectiontitle:" . $searchtitleval . '"';
            // Append the other search strings
            $search .= substr($origsearch, $titlepos + $searchtitlelength);
        } else {
            // nothing to remove
            $search = $searchstart . ' "' . "collectiontitle:" . $searchend . '"';
        }
        debug("New search: " . $search);
    }

    $keywords = split_keywords($search, false, false, false, false, true);
    if (strlen($search) == 1 && !is_numeric($search)) {
        # A-Z search
        $sql = "AND c.name LIKE ?";
        $sql_params[] = "s";
        $sql_params[] = $search . "%";
    }
    if (strlen($search) > 1 || is_numeric($search)) {
        $keyrefs = array();
        $keyunions = array();
        $unionselect = "SELECT kunion.collection";
        for ($n = 0; $n < count($keywords); $n++) {
            if (substr($keywords[$n], 0, 1) == "\"" && substr($keywords[$n], -1, 1) == "\"") {
                $keywords[$n] = substr($keywords[$n], 1, -1);
            }

            if (substr($keywords[$n], 0, 16) == "collectiontitle:") {
                $newsearch = explode(":", $keywords[$n])[1];
                $newsearch = strpos($newsearch, '*') === false ? '%' . trim($newsearch) . '%' : str_replace('*', '%', trim($newsearch));
                $sql = "AND c.name LIKE ?";
                $sql_params[] = "s";
                $sql_params[] = $newsearch;
            } elseif (substr($keywords[$n], 0, 16) == "collectionowner:") {
                $keywords[$n] = substr($keywords[$n], 16);
                $keyref = $keywords[$n];
                $sql .= " AND (u.username RLIKE ? OR u.fullname RLIKE ?)";
                $sql_params[] = "s";
                $sql_params[] = $keyref;
                $sql_params[] = "s";
                $sql_params[] = $keyref;
            } elseif (substr($keywords[$n], 0, 19) == "collectionownerref:") {
                $keywords[$n] = substr($keywords[$n], 19);
                $keyref = $keywords[$n];
                $sql .= " AND (c.user=?)";
                $sql_params[] = "i";
                $sql_params[] = $keyref;
            } elseif (substr($keywords[$n], 0, 10) == "basicyear:" || substr($keywords[$n], 0, 11) == "basicmonth:") {
                $dateparts = explode(":", $keywords[$n]);
                $yearpart = $dateparts[0] == "basicyear" ? $dateparts[1] :  "____";
                $monthpart = $dateparts[0] == "basicmonth" ?  $dateparts[1] : "__";
                $sql .= " AND c.created LIKE ?";
                $sql_params[] = "s";
                $sql_params[] = $yearpart . "-" . $monthpart . "%";
            } else {
                if (substr($keywords[$n], 0, 19) == "collectionkeywords:") {
                    $keywords[$n] = substr($keywords[$n], 19);
                }
                # Support field specific matching - discard the field identifier as not appropriate for collection searches.
                if (strpos($keywords[$n], ":") !== false) {
                    $keywords[$n] = substr($keywords[$n], strpos($keywords[$n], ":") + 1);
                }
                $keyref = resolve_keyword($keywords[$n], false);
                if ($keyref !== false) {
                    $keyrefs[] = $keyref;
                }
            }
        }

        if ($sql == "" && count($keyrefs) == 0) {
            // Not a recognised collection search syntax and no matching keywords
            return [];
        }

        for ($n = 0; $n < count($keyrefs); $n++) {
            $select_extra .= ", k.key" . $n;
            $unionselect .= ", BIT_OR(key" . $n . "_found) AS key" . $n;
            $unionsql = "SELECT collection ";
            for ($l = 0; $l < count($keyrefs); $l++) {
                $unionsql .= $l == $n ? ",TRUE" : ",FALSE";
                $unionsql .= " AS key" . $l . "_found";
            }
            $unionsql .= " FROM collection_keyword WHERE keyword=" . $keyrefs[$n];
            $keyunions[] = $unionsql;
            $sql .= " AND key" .  $n;
        }
        if (count($keyunions) > 0) {
            $keysql .= " LEFT OUTER JOIN (" . $unionselect . " FROM (" . implode(" UNION ", $keyunions) . ") kunion GROUP BY collection) AS k ON c.ref = k.collection";
        }
    }

    # Restrict to parent, child and sibling groups?
    if ($public_collections_confine_group && !$override_group_restrict) {
        # Form a list of all applicable groups
        $groups = array($usergroup); # Start with user's own group
        $usergroupparams = ["i",$usergroup];
        $groups = array_merge($groups, ps_array("SELECT ref value FROM usergroup WHERE parent=?", $usergroupparams, 'usergroup')); # Children
        $groups = array_merge($groups, ps_array("SELECT parent value FROM usergroup WHERE ref=?", $usergroupparams, 'usergroup')); # Parent
        $groups = array_merge($groups, ps_array("SELECT ref value FROM usergroup WHERE parent<>0 AND parent=(SELECT parent FROM usergroup WHERE ref=?)", $usergroupparams, 'usergroup')); # Siblings (same parent)

        $sql .= " AND u.usergroup IN (" . ps_param_insert(count($groups)) . ")";
        $sql_params = array_merge($sql_params, ps_param_fill($groups, "i"));
    }

    // Add extra elements to the SELECT statement if needed
    if ($include_resources) {
        $select_extra .= ", COUNT(DISTINCT cr.resource) AS count";
    }

    // Filter by type (public/featured collections)
    $public_type_filter_sql = "c.`type` = ?";
    $public_type_filter_sql_params = ["i",COLLECTION_TYPE_PUBLIC];


    if ($exclude_themes) {
        $featured_type_filter_sql = "";
        $featured_type_filter_sql_params = [];
    } else {
        $featured_type_filter_sql = "(c.`type` = ?)";
        $featured_type_filter_sql_params = ["i",COLLECTION_TYPE_FEATURED];
        $fcf_sql = featured_collections_permissions_filter_sql("AND", "c.ref");
        if (is_array($fcf_sql)) {
            // Update with the extra condition
            $featured_type_filter_sql = "(c.`type` = ? " . $fcf_sql[0] . ")";
            $featured_type_filter_sql_params = array_merge(["i",COLLECTION_TYPE_FEATURED], $fcf_sql[1]);
        }
        if ($exclude_public) {
            $public_type_filter_sql = "";
            $public_type_filter_sql_params = [];
        }
    }

    if ($public_type_filter_sql != "" && $featured_type_filter_sql != "") {
        $type_filter_sql = "(" . $public_type_filter_sql . " OR " . $featured_type_filter_sql . ")";
        $type_filter_sql_params = array_merge($public_type_filter_sql_params, $featured_type_filter_sql_params);
    } else {
        $type_filter_sql = $public_type_filter_sql . $featured_type_filter_sql;
        $type_filter_sql_params = array_merge($public_type_filter_sql_params, $featured_type_filter_sql_params);
    }

    $where_clause_osql = 'col.`type` = ' . COLLECTION_TYPE_PUBLIC;
    if ($featured_type_filter_sql !== '') {
        $where_clause_osql .= ' OR (col.`type` = ' . COLLECTION_TYPE_FEATURED . ' AND col.is_featured_collection_category = false)';
    }

    $main_sql = sprintf(
        "SELECT *
           FROM (
                         SELECT DISTINCT c.*,
                                u.username,
                                u.fullname,
                                IF(c.`type` = %s AND COUNT(DISTINCT cc.ref)>0, true, false) AS is_featured_collection_category
                                %s
                           FROM collection AS c
                LEFT OUTER JOIN collection AS cc ON c.ref = cc.parent
                LEFT OUTER JOIN collection_resource AS cr ON c.ref = cr.collection
                LEFT OUTER JOIN user AS u ON c.user = u.ref
                          %s # keysql
                          WHERE %s # type_filter_sql
                            %s
                       GROUP BY c.ref
                       ORDER BY %s
           ) AS col
          WHERE %s",
        COLLECTION_TYPE_FEATURED,
        $select_extra,
        $keysql,
        $type_filter_sql,
        $sql, # extra filters
        "{$order_by} {$sort}",
        $where_clause_osql
    );

    return ps_query($main_sql, array_merge($type_filter_sql_params, $sql_params), '', $fetchrows);
}

/**
 * Search within available collections
 *
 * @param  string $search
 * @param  string $restypes
 * @param  integer $archive
 * @param  string $order_by
 * @param  string $sort
 * @param  integer $fetchrows
 * @return array
 */
function do_collections_search($search, $restypes, $archive = 0, $order_by = '', $sort = "DESC", $fetchrows = -1)
{
    global $search_includes_themes, $default_collection_sort;
    if ($order_by == '') {
        $order_by = $default_collection_sort;
    }
    $result = array();

    # Recognise a quoted search, which is a search for an exact string
    if (substr($search, 0, 1) == "\"" && substr($search, -1, 1) == "\"") {
        $search = substr($search, 1, -1);
    }

    $search_includes_themes_now = $search_includes_themes;
    if ($restypes != "") {
        $restypes_x = explode(",", $restypes);
        $search_includes_themes_now = in_array("FeaturedCollections", $restypes_x);
    }

    if ($search_includes_themes_now) {
        # Same search as when searching within public collections.
        $result = search_public_collections($search, "name", "ASC", !$search_includes_themes_now, false, true, false, $fetchrows);
    }
    return $result;
}

/**
 * Add a collection to a user's 'My Collections'
 *
 * @param  integer  $user         ID of user
 * @param  integer  $collection   ID of collection
 *
 * @return boolean
 */
function add_collection($user, $collection)
{
    // Don't add if we are anonymous - we can only have one collection
    global $anonymous_login,$username,$anonymous_user_session_collection;
    if (isset($anonymous_login) && ($username == $anonymous_login) && $anonymous_user_session_collection) {
        return false;
    }

    remove_collection($user, $collection);
    ps_query("insert into user_collection(user,collection) values (?,?)", array("i",$user,"i",$collection));
    clear_query_cache('col_total_ref_count_w_perm');
    clear_query_cache('collection_access' . $user);
    collection_log($collection, LOG_CODE_COLLECTION_SHARED_COLLECTION, 0, ps_value("select username as value from user where ref = ?", array("i",$user), ""));

    return true;
}

/**
 * Remove someone else's collection from a user's My Collections
 *
 * @param  integer $user
 * @param  integer $collection
 */
function remove_collection($user, $collection)
{
    ps_query("delete from user_collection where user=? and collection=?", array("i",$user,"i",$collection));
    clear_query_cache('col_total_ref_count_w_perm');
    collection_log($collection, LOG_CODE_COLLECTION_STOPPED_SHARING_COLLECTION, 0, ps_value("select username as value from user where ref = ?", array("i",$user), ""));
}

/**
 * Update the keywords index for this collection
 *
 * @param  integer $ref
 * @param  string $index_string
 * @return integer  How many keywords were indexed?
 */
function index_collection($ref, $index_string = '')
{
    # Remove existing indexed keywords
    ps_query("delete from collection_keyword where collection=?", array("i",$ref)); # Remove existing keywords
    # Define an indexable string from the name, themes and keywords.

    global $index_collection_titles;

    if ($index_collection_titles) {
            $indexfields = 'c.ref,c.name,c.keywords,c.description';
    } else {
        $indexfields = 'c.ref,c.keywords';
    }
    global $index_collection_creator;
    if ($index_collection_creator) {
            $indexfields .= ',u.fullname';
    }


    // if an index string wasn't supplied, generate one
    if (!strlen($index_string) > 0) {
        $indexarray = ps_query("select $indexfields from collection c left join user u on u.ref=c.user where c.ref = ?", array("i",$ref));
        for ($i = 0; $i < count($indexarray); $i++) {
            $index_string = "," . implode(',', $indexarray[$i]);
        }
    }

    $keywords = split_keywords($index_string, true);
    for ($n = 0; $n < count($keywords); $n++) {
        if (trim($keywords[$n]) == "") {
            continue;
        }
        $keyref = resolve_keyword($keywords[$n], true);
        ps_query("insert into collection_keyword values (?,?)", array("i",$ref,"i",$keyref));
    }
    // return the number of keywords indexed
    return $n;
}

/**
 * Process the save action when saving a collection
 *
 * @param  integer $ref
 * @param  array $coldata
 *
 * @return false|void
 */
function save_collection($ref, $coldata = array())
{
    if (!is_numeric($ref) || !collection_writeable($ref)) {
        return false;
    }

    if (count($coldata) == 0) {
        // Old way
        $coldata["name"]                = getval("name", "");
        $coldata["allow_changes"]       = getval("allow_changes", "") != "" ? 1 : 0;
        $coldata["public"]              = getval('public', 0, true);
        $coldata["keywords"]            = getval("keywords", "");
        $coldata["result_limit"]        = getval("result_limit", 0, true);
        $coldata["relateall"]           = getval("relateall", "") != "";
        $coldata["removeall"]           = getval("removeall", "") != "";
        $coldata["users"]               = getval("users", "");

        if (checkperm("h")) {
            $coldata["home_page_publish"]   = (getval("home_page_publish", "") != "") ? "1" : "0";
            $coldata["home_page_text"]      = getval("home_page_text", "");
            $home_page_image = getval("home_page_image", 0, true);
            if ($home_page_image > 0) {
                $coldata["home_page_image"] = $home_page_image;
            }
        }
    }

    $oldcoldata = get_collection($ref);
    $sqlset = array();
    foreach ($coldata as $colopt => $colset) {
        // skip data that is not a collection property (e.g result_limit) otherwise the $sqlset will have an
        // incorrect SQL query for the update statement.
        if (in_array($colopt, ['result_limit', 'relateall', 'removeall', 'users'])) {
            continue;
        }

        // Set type to public unless explicitly passed
        if ($colopt == "public" && $colset == 1 && !isset($coldata["type"])) {
            $sqlset["type"] = COLLECTION_TYPE_PUBLIC;
        }

        // "featured_collections_changes" is determined by collection_edit.php page
        // This is meant to override the type if collection has a parent. The order of $coldata elements matters!
        if ($colopt == "featured_collections_changes" && !empty($colset)) {
            $sqlset["type"] = COLLECTION_TYPE_FEATURED;
            $sqlset["parent"] = null;

            if (isset($colset["update_parent"])) {
                $force_featured_collection_type = isset($colset["force_featured_collection_type"]);

                // A FC root category is created directly from the collections_featured.php page so not having a parent, means it's just public
                if ($colset["update_parent"] == 0 && !$force_featured_collection_type) {
                    $sqlset["type"] = COLLECTION_TYPE_PUBLIC;
                } else {
                    $sqlset["parent"] = (int) $colset["update_parent"];
                }
            }

            if (isset($colset["thumbnail_selection_method"])) {
                $sqlset["thumbnail_selection_method"] = $colset["thumbnail_selection_method"];
            }

            if (isset($colset["thumbnail_selection_method"]) || isset($colset["name"])) {
                // Prevent the parent from being changed if user only modified the thumbnail_selection_method or name
                $sqlset["parent"] = (!isset($colset["update_parent"]) ? $oldcoldata["parent"] : $sqlset["parent"]);
            }

            // Prevent unnecessary changes
            foreach (array("type", "parent", "thumbnail_selection_method") as $puc_to_prop) {
                if (isset($sqlset[$puc_to_prop]) && $oldcoldata[$puc_to_prop] == $sqlset[$puc_to_prop]) {
                    unset($sqlset[$puc_to_prop]);
                }
            }

            continue;
        }
        if (!isset($oldcoldata[$colopt]) || $colset != $oldcoldata[$colopt]) {
            $sqlset[$colopt] = $colset;
        }
    }

    // If collection is set as private by caller code, disable incompatible properties used for COLLECTION_TYPE_FEATURED (set by the user or exsting)
    if (isset($sqlset["public"]) && $sqlset["public"] == 0) {
        $sqlset["type"] = COLLECTION_TYPE_STANDARD;
        $sqlset["parent"] = null;
        $sqlset["thumbnail_selection_method"] = null;
        $sqlset["bg_img_resource_ref"] = null;
    }

    /*
    Order by is applicable only to featured collections.
    Determine if we have to reset and, if required, re-order featured collections at the tree level

    ----------------------------------------------------------------------------------------------------------------
                                                    |     Old       |        Set        |
                                                    |---------------|-------------------|
    Use cases                                       | Type | Parent | Type    | Parent  | Reset order_by? | Re-order?
    ------------------------------------------------|------|--------|-----------------------------------------------
    Move FC to private                              | 3    | null   | 0       | null    | yes             | no
    Move FC to public                               | 3    | any    | 4       | null    | yes             | no
    Move FC to new parent                           | 3    | null   | not set | X       | yes             | yes
    Save FC but don’t change type or parent         | 3    | null   | not set | null    | no              | no
    Save a child FC but don’t change type or parent | 3    | X      | not set | not set | no              | no
    Move public to private                          | 4    | null   | 0       | null    | no              | no
    Move public to FC (root)                        | 4    | null   | 3       | not set | yes             | yes
    Move public to FC (others)                      | 4    | null   | 3       | X       | yes             | yes
    Save public but don’t change type or parent     | 4    | null   | 4       | not set | no              | no
    Create FC at root                               | 0    | null   | 3       | not set | yes             | yes
    Create FC at other level                        | 0    | null   | 3       | X       | yes             | yes
    ----------------------------------------------------------------------------------------------------------------
    */
    // Saving a featured collection without changing its type or parent
    $rob_cond_fc_no_change = (
        isset($oldcoldata['type']) && $oldcoldata['type'] === COLLECTION_TYPE_FEATURED
        && !isset($sqlset['type'])
        && (!isset($sqlset['parent']) || is_null($sqlset['parent']))
    );
    // Saving a public collection without changing it into a featured collection
    $rob_cond_public_col_no_change = (
        isset($oldcoldata['type'], $sqlset['type'])
        && $oldcoldata['type'] === COLLECTION_TYPE_PUBLIC
        && $sqlset["type"] !== COLLECTION_TYPE_FEATURED
    );
    if (!($rob_cond_fc_no_change || $rob_cond_public_col_no_change)) {
        $sqlset['order_by'] = 0;

        if (
            // Type changed to featured collection
            (isset($sqlset['type']) && $sqlset['type'] === COLLECTION_TYPE_FEATURED)

            // Featured collection moved in the tree (ie parent changed)
            || ($oldcoldata['type'] === COLLECTION_TYPE_FEATURED && !isset($sqlset['type']) && isset($sqlset['parent']))
        ) {
            $reorder_fcs = true;
        }
    }


    // Update collection record
    if (count($sqlset) > 0) {
        $sqlupdate = "";
        $clear_fc_query_cache = false;
        $collection_columns = [
            'name',
            'user',
            'created',
            'public',
            'allow_changes',
            'cant_delete',
            'keywords',
            'savedsearch',
            'home_page_publish',
            'home_page_text',
            'home_page_image',
            'session_id',
            'description',
            'type',
            'parent',
            'thumbnail_selection_method',
            'bg_img_resource_ref',
            'order_by',
        ];
        $params = [];
        foreach ($sqlset as $colopt => $colset) {
            // Only valid collection columns should be processed
            if (!in_array($colopt, $collection_columns)) {
                continue;
            }

            if ($sqlupdate != "") {
                $sqlupdate .= ", ";
            }

            if (in_array($colopt, array("type", "parent", "thumbnail_selection_method", "bg_img_resource_ref"))) {
                $clear_fc_query_cache = true;
            }

            if (in_array($colopt, array("parent", "thumbnail_selection_method", "bg_img_resource_ref"))) {
                $sqlupdate .= $colopt . " = ";
                if ($colset == 0) {
                    $sqlupdate .= 'NULL';
                } else {
                    $sqlupdate .= '?';
                    $params = array_merge($params, ['i', $colset]);
                }

                continue;
            }

            if ($colopt == 'allow_changes') {
                $colset = (int) $colset;
            }

            $sqlupdate .= $colopt . " = ? ";
            $params = array_merge($params, ['s', $colset]);
        }
        if ($sqlupdate !== '') {
            $sql = "UPDATE collection SET {$sqlupdate} WHERE ref = ?";
            ps_query($sql, array_merge($params, ['i', $ref]));

            if ($clear_fc_query_cache) {
                clear_query_cache("featured_collections");
            }

            // Log the changes
            foreach ($sqlset as $colopt => $colset) {
                switch ($colopt) {
                    case "public":
                        collection_log($ref, LOG_CODE_COLLECTION_ACCESS_CHANGED, 0, $colset ? 'public' : 'private');
                    break;
                    case "allow_changes":
                        collection_log($ref, LOG_CODE_UNSPECIFIED, 0, $colset ? 'true' : 'false');
                    break;
                    default:
                        collection_log($ref, LOG_CODE_EDITED, 0, $colopt  . " = " . $colset);
                    break;
                }
            }
        }
    }


    index_collection($ref);

    # If 'users' is specified (i.e. access is private) then rebuild users list
    if (isset($coldata["users"])) {
        $old_attached_users = ps_array("SELECT user value FROM user_collection WHERE collection=?", array("i",$ref));

        $new_attached_users = array();
        $removed_users = array();

        $collection_owner_ref = ps_value(
            "SELECT u.ref value FROM collection c LEFT JOIN user u ON c.user=u.ref WHERE c.ref=?",
            array("i",$ref),
            ""
        );
        global $userref;
        $collection_owner = get_user(($collection_owner_ref == '' ? $userref : $collection_owner_ref));

        if ($collection_owner_ref != "") {
            $old_attached_users[] = $collection_owner["ref"]; # Collection Owner is implied as attached already
        }

        ps_query("delete from user_collection where collection=?", array("i",$ref));

        $old_attached_groups = ps_array("SELECT usergroup value FROM usergroup_collection WHERE collection=?", array("i",$ref));
        ps_query("delete from usergroup_collection where collection=?", array("i",$ref));

        # Build a new list and insert
        $users = resolve_userlist_groups($coldata["users"]);
        $ulist = array_unique(trim_array(explode(",", $users)));
        $urefs = ps_array("select ref value from user where username in (" . ps_param_insert(count($ulist)) . ")", ps_param_fill($ulist, "s"));
        if (count($urefs) > 0) {
            $params = [];
            foreach ($urefs as $uref) {
                $params[] = $ref;
                $params[] = $uref;
            }
            ps_query("insert into user_collection(collection,user) values " . trim(str_repeat('(?, ?),', count($urefs)), ','), ps_param_fill($params, 'i'));
            $new_attached_users = array_diff($urefs, $old_attached_users);
            $removed_users = array_diff($old_attached_users, $urefs, $collection_owner_ref != "" ? array($collection_owner["ref"]) : array());
        }

        # log this only if a user is being added
        if ($coldata["users"] != "") {
            collection_log($ref, LOG_CODE_COLLECTION_SHARED_COLLECTION, 0, join(", ", $ulist));
        }

        # log the removal of users / smart groups
        $was_shared_with = array();
        if (count($old_attached_users) > 0) {
            $was_shared_with = ps_array("select username value from user where ref in (" . ps_param_insert(count($old_attached_users)) . ")", ps_param_fill($old_attached_users, "i"));
        }
        if (count($old_attached_groups) > 0) {
            foreach ($old_attached_groups as $old_group) {
                $was_shared_with[] = "Group (Smart): " . ps_value("SELECT name value FROM usergroup WHERE ref = ?", array("i", $old_group), "");
            }
        }
        if (count($urefs) == 0 && count($was_shared_with) > 0) {
            collection_log($ref, LOG_CODE_COLLECTION_STOPPED_SHARING_COLLECTION, 0, join(", ", $was_shared_with));
        }

        $groups = resolve_userlist_groups_smart($users);
        $groupnames = '';
        if ($groups != '') {
            $groups = explode(",", $groups);
            if (count($groups) > 0) {
                foreach ($groups as $group) {
                    ps_query("insert into usergroup_collection(collection,usergroup) values (?,?)", array("i",$ref,"i",$group));
                    // get the group name
                    if ($groupnames != '') {
                        $groupnames .= ", ";
                    }
                    $groupnames .= ps_value("select name value from usergroup where ref=?", array("i",$group), "");
                }

                $new_attached_groups = array_diff($groups, $old_attached_groups);
                if (!empty($new_attached_groups)) {
                    foreach ($new_attached_groups as $newg) {
                        $group_users = ps_array("SELECT ref value FROM user WHERE usergroup=?", array("i",$newg));
                        $new_attached_users = array_merge($new_attached_users, $group_users);
                    }
                }
            }
            #log this
            collection_log($ref, LOG_CODE_COLLECTION_SHARED_COLLECTION, 0, $groupnames);
        }

        # Clear user specific collection cache if user was added or removed.
        if (count($new_attached_users) >  0 || count($removed_users) > 0) {
            $user_caches = array_unique(array_merge($new_attached_users, $removed_users));
            foreach ($user_caches as $user_cache) {
                clear_query_cache('collection_access' . $user_cache);
            }
        }
    }

    # Send a message to any new attached user
    if (!empty($new_attached_users)) {
        global $baseurl, $lang;

        $new_attached_users = array_unique($new_attached_users);
        $message_text = str_replace(
            array('%user%', '%colname%'),
            array($collection_owner["fullname"] ?? $collection_owner["username"],getval("name", "")),
            $lang['collectionprivate_attachedusermessage']
        );
        $message_url = $baseurl . "/?c=" . $ref;
        message_add($new_attached_users, $message_text, $message_url);
    }

    # Relate all resources?
    if (
        isset($coldata["relateall"]) && $coldata["relateall"] != ""
        && allow_multi_edit($ref)
    ) {
            relate_all_collection($ref);
    }

    # Remove all resources?
    if (isset($coldata["removeall"]) && $coldata["removeall"] != "") {
        remove_all_resources_from_collection($ref);
    }

    # Update limit count for saved search
    if (isset($coldata["result_limit"]) && (int)$coldata["result_limit"] > 0) {
        ps_query("update collection_savedsearch set result_limit=? where collection=?", array("i",$coldata["result_limit"],"i",$ref));
    }

    // Re-order featured collections tree at the level of this collection (if applicable - only for featured collections)
    if (isset($reorder_fcs)) {
        $new_fcs_order = reorder_all_featured_collections_with_parent($sqlset['parent'] ?? null);
        log_activity("via save_collection({$ref})", LOG_CODE_REORDERED, implode(', ', $new_fcs_order), 'collection');
    }

    // When a collection is now saved as a Featured Collection (must have resources) under an existing branch, apply all
    // the external access keys from the categories which make up that path to prevent breaking existing shares.
    if (
        isset($sqlset['parent']) && $sqlset['parent'] > 0
        && !empty($fc_resources = array_filter((array) get_collection_resources($ref)))
    ) {
        // Delete old branch path external share associations as they are no longer relevant
        $old_branch_category_ids = array_column(get_featured_collection_category_branch_by_leaf((int) $oldcoldata['parent'], []), 'ref');
        foreach ($old_branch_category_ids as $fc_category_id) {
            $old_keys = get_external_shares([
                    'share_collection' => $fc_category_id,
                    'share_type' => 0,
                    'ignore_permissions' => true
                ]);
            foreach ($old_keys as $old_key_data) {
                // IMPORTANT: we delete the keys associated with the collection we've just saved. The key may still be valid for the rest of the branch categories.
                delete_collection_access_key($ref, $old_key_data['access_key']);
            }
        }


        // Copy associations of all branch parents and apply to this collection and its resources
        $all_branch_path_keys = [];
        $branch_category_ids = array_column(get_featured_collection_category_branch_by_leaf($sqlset['parent'], []), 'ref');
        foreach ($branch_category_ids as $fc_category_id) {
            $all_branch_path_keys = array_merge(
                $all_branch_path_keys,
                get_external_shares([
                    'share_collection' => $fc_category_id,
                    'share_type' => 0,
                    'ignore_permissions' => true
                ])
            );
        }

        foreach ($all_branch_path_keys as $external_key_data) {
            foreach ($fc_resources as $fc_resource_id) {
                if (!can_share_resource($fc_resource_id)) {
                    continue;
                }

                ps_query(
                    'INSERT INTO external_access_keys(resource, access_key, collection, `user`, usergroup, email, `date`, access, expires, password_hash) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)',
                    [
                        'i', $fc_resource_id,
                        's', $external_key_data['access_key'],
                        'i', $ref,
                        'i', $GLOBALS['userref'],
                        'i', $external_key_data['usergroup'],
                        's', $external_key_data['email'],
                        'i', $external_key_data['access'],
                        's', $external_key_data['expires'] ?: null,
                        's', $external_key_data['password_hash'] ?: null
                    ]
                );
                collection_log($ref, LOG_CODE_COLLECTION_SHARED_RESOURCE_WITH, $fc_resource_id, $external_key_data['access_key']);
            }
        }
    }
    global $userref;
    clear_query_cache('collection_access' . $userref);
    refresh_collection_frame();
}

/**
* Case insensitive string comparisons using a "natural order" algorithm for collection names
*
* @param string $a
* @param string $b
*
* @return integer < 0 if $a is less than $b > 0 if $a is greater than $b, and 0 if they are equal.
*/
function collections_comparator($a, $b)
{
    return strnatcasecmp(i18n_get_collection_name($a), i18n_get_collection_name($b));
}

/**
* Case insensitive string comparisons using a "natural order" algorithm for collection names
*
* @param string $b
* @param string $a
*
* @return integer < 0 if $a is less than $b > 0 if $a is greater than $b, and 0 if they are equal.
*/
function collections_comparator_desc($a, $b)
{
    return strnatcasecmp(i18n_get_collection_name($b), i18n_get_collection_name($a));
}

/**
 * Returns a list of smart theme headers, which are basically fields with a 'smart theme name' set.
 *
 * @return array
 */
function get_smart_theme_headers()
{
    return ps_query("SELECT ref, name, smart_theme_name, type FROM resource_type_field WHERE length(smart_theme_name) > 0 ORDER BY smart_theme_name", array(), "featured_collections");
}

/**
 * get_smart_themes_nodes
 *
 * @param  integer $field
 * @param  boolean $is_category_tree
 * @param  integer $parent
 * @param  array   $field_meta - resource type field metadata
 * @return array
 */
function get_smart_themes_nodes($field, $is_category_tree, $parent = null, array $field_meta = array())
{
    $return = array();

    // Determine if this should cascade onto children for category tree type
    $recursive = false;
    if ($is_category_tree) {
        $recursive = true;
    }

    $nodes = get_nodes($field, ((0 == $parent) ? null : $parent), $recursive);

    if (isset($field_meta['automatic_nodes_ordering']) && (bool) $field_meta['automatic_nodes_ordering']) {
                $nodes = reorder_nodes($nodes);
                $nodes = array_values($nodes); // reindex nodes array
    }

    if (0 === count($nodes)) {
        return $return;
    }

    /*
    Tidy list so it matches the storage format used for keywords
    The translated version is fetched as each option will be indexed in the local language version of each option
    */
    $options_base = array();
    for ($n = 0; $n < count($nodes); $n++) {
        $options_base[$n] = trim(mb_convert_case(i18n_get_translated($nodes[$n]['name']), MB_CASE_LOWER, 'UTF-8'));
    }

    // For each option, if it is in use, add it to the return list
    for ($n = 0; $n < count($nodes); $n++) {
        $cleaned_option_base = preg_replace('/\W/', ' ', $options_base[$n]);      // replace any non-word characters with a space
        $cleaned_option_base = trim($cleaned_option_base);      // trim (just in case prepended / appended space characters)

        $tree_node_depth    = 0;
        $parent_node_to_use = 0;
        $is_parent          = false;

        if (is_parent_node($nodes[$n]['ref'])) {
            $parent_node_to_use = $nodes[$n]['ref'];
            $is_parent          = true;

            $tree_node_depth = get_tree_node_level($nodes[$n]['ref']);

            if (!is_null($parent) && is_parent_node($parent)) {
                $tree_node_depth--;
            }
        }

        $c                       = count($return);
        $return[$c]['name']      = trim(i18n_get_translated($nodes[$n]['name']));
        $return[$c]['indent']    = $tree_node_depth;
        $return[$c]['node']      = $parent_node_to_use;
        $return[$c]['is_parent'] = $is_parent;
        $return[$c]['ref'] = $nodes[$n]['ref'];
    }

    return $return;
}

/**
 * E-mail a collection to users
 *
 *  - Attempt to resolve all users in the string $userlist to user references.
 *  - Add $collection to these user's 'My Collections' page
 *  - Send them an e-mail linking to this collection
 *  - Handle multiple collections (comma separated list)
 *
 * @param  mixed $colrefs
 * @param  string $collectionname
 * @param  string $fromusername
 * @param  string $userlist
 * @param  string $message
 * @param  string $feedback
 * @param  integer $access
 * @param  string $expires
 * @param  string $useremail
 * @param  string $from_name
 * @param  string $cc
 * @param  boolean $themeshare
 * @param  string $themename
 * @param  string $themeurlsuffix
 * @param  boolean $list_recipients
 * @param  boolean $add_internal_access
 * @param  string $group
 * @param  string $sharepwd
 */
function email_collection($colrefs, $collectionname, $fromusername, $userlist, $message, $feedback, $access = -1, $expires = "", $useremail = "", $from_name = "", $cc = "", $themeshare = false, $themename = "", $themeurlsuffix = "", $list_recipients = false, $add_internal_access = false, $group = "", $sharepwd = ""): string
{
    global $baseurl,$email_from,$applicationname,$lang,$userref,$usergroup;
    if ($useremail == "") {
        $useremail = $email_from;
    }
    if ($group == "") {
        $group = $usergroup;
    }

    if (trim($userlist) == "") {
        return $lang["mustspecifyoneusername"];
    }
    $userlist = resolve_userlist_groups($userlist);

    if (strpos($userlist, $lang["groupsmart"] . ": ") !== false) {
        $groups_users = resolve_userlist_groups_smart($userlist, true);
        if ($groups_users != '') {
            if ($userlist != "") {
                $userlist = remove_groups_smart_from_userlist($userlist);
                if ($userlist != "") {
                    $userlist .= ",";
                }
            }
            $userlist .= $groups_users;
        }
    }

    $ulist = trim_array(explode(",", $userlist));
    $emails = array();
    $key_required = array();
    if ($feedback) {
        $feedback = 1;
    } else {
        $feedback = 0;
    }

    $reflist = trim_array(explode(",", $colrefs));
    // Take out the FC category from the list as this is more of a dummy record rather than a collection we'll be giving
    // access to users. See generate_collection_access_key() when collection is a featured collection category.
    $fc_category_ref = ($themeshare ? array_shift($reflist) : null);

    $emails_keys = resolve_user_emails($ulist);
    if (0 === count($emails_keys)) {
        return $lang['email_error_user_list_not_valid'];
    }

    # Make an array of all emails, whether internal or external
    $emails = $emails_keys['emails'];
    # Make a corresponding array stating whether keys are necessary for the links
    $key_required = $emails_keys['key_required'];

    # Make an array of internal userids which are unexpired approved with valid emails
    $internal_user_ids = $emails_keys['refs'] ?? array();

    if (count($internal_user_ids) > 0) {
        # Delete any existing collection entries
        ps_query("DELETE FROM user_collection WHERE collection IN (" . ps_param_insert(count($reflist)) . ") 
                AND user IN (" . ps_param_insert(count($internal_user_ids)) . ")", array_merge(ps_param_fill($reflist, "i"), ps_param_fill($internal_user_ids, "i")));

        # Insert new user_collection row(s)
        #loop through the collections
        for ($nx1 = 0; $nx1 < count($reflist); $nx1++) {
            #loop through the users
            for ($nx2 = 0; $nx2 < count($internal_user_ids); $nx2++) {
                ps_query("INSERT INTO user_collection(collection,user,request_feedback) VALUES (?,?,?)", ["i",$reflist[$nx1],"i",$internal_user_ids[$nx2],"i",$feedback ]);
                if ($add_internal_access) {
                    foreach (get_collection_resources($reflist[$nx1]) as $resource) {
                        if (get_edit_access($resource)) {
                            open_access_to_user($internal_user_ids[$nx2], $resource, $expires);
                        }
                    }
                }

                #log this
                clear_query_cache('collection_access' . $internal_user_ids[$nx2]);
                collection_log($reflist[$nx1], LOG_CODE_COLLECTION_SHARED_COLLECTION, 0, ps_value("select username as value from user where ref = ?", array("i", $internal_user_ids[$nx2]), ""));
            }
        }
    }

    # Send an e-mail to each resolved email address

    # htmlbreak is for composing list
    $htmlbreak = "\r\n";
    global $use_phpmailer;
    if ($use_phpmailer) {
        $htmlbreak = "<br/><br/>";
        $htmlbreaksingle = "<br/>";
    }

    if ($fromusername == "") {
        $fromusername = $applicationname;
    } // fromusername is used for describing the sender's name inside the email
    if ($from_name == "") {
        $from_name = $applicationname;
    } // from_name is for the email headers, and needs to match the email address (app name or user name)

    // Remove links from the sharing message entered by the user to prevent inclusion in the email or system message.
    $message = strip_tags_and_attributes($message);

    $templatevars['message'] = str_replace(array("\\n","\\r","\\"), array("\n","\r",""), $message);
    if (trim($templatevars['message']) == "") {
        $templatevars['message'] = $lang['nomessage'];
        $message = "lang_nomessage";
    }

    $templatevars['fromusername'] = $fromusername;
    $templatevars['from_name'] = $from_name;

    // Create notification message
    $notifymessage     = new ResourceSpaceUserNotification();
    if (count($reflist) > 1) {
        $notifymessage->set_subject($applicationname . ": ");
        $notifymessage->append_subject("lang_mycollections");
    } else {
        $notifymessage->set_subject($applicationname . ": " . $collectionname);
    }

    if ($fromusername == "") {
        $fromusername = $applicationname;
    }

    $externalmessage = str_replace('[applicationname]', $applicationname, $lang["emailcollectionmessageexternal"]);
    $internalmessage = "lang_emailcollectionmessage";

    $viewlinktext = "lang_clicklinkviewcollection";
    if ($themeshare) { // Change the text if sharing a theme category
        $externalmessage    = str_replace('[applicationname]', $applicationname, $lang["emailthemecollectionmessageexternal"]);
        $internalmessage    = "lang_emailthememessage";
        $viewlinktext       = "lang_clicklinkviewcollections";
    }

    ##  loop through recipients
    for ($nx1 = 0; $nx1 < count($emails); $nx1++) {
        ## loop through collections
        debug('email_collection : Processing email for ' . $emails[$nx1]);
        $list = "";
        $list2 = "";
        $origviewlinktext = $viewlinktext; // Save this text as we may change it for internal theme shares for this user
        if ($themeshare && !$key_required[$nx1]) { # don't send a whole list of collections if internal, just send the theme category URL
            debug('email_collection : no key required to view theme internally');
            $notifymessage->set_subject($applicationname . ": " . $themename);
            $url = $baseurl . "/pages/collections_featured.php" . $themeurlsuffix;
            $viewlinktext = "lang_clicklinkviewthemes";
            $notifymessage->url = $url;
            $emailcollectionmessageexternal = false;
            if ($use_phpmailer) {
                $link = '<a href="' . $url . '">' . $themename . '</a>';
                $list .= $htmlbreak . $link;
                // alternate list style
                $list2 .= $htmlbreak . $themename . ' -' . $htmlbreaksingle . $url;
                $templatevars['list2'] = $list2;
            } else {
                $list .= $htmlbreak . $url;
            }
            for ($nx2 = 0; $nx2 < count($reflist); $nx2++) {
                #log this
                collection_log($reflist[$nx2], LOG_CODE_COLLECTION_EMAILED_COLLECTION, 0, $emails[$nx1]);
            }
        } else {
            $fc_key = "";
            // E-mail external share, generate the access key based on the FC category. Each sub-collection will have the same key.
            debug('email_collection : is a key needed for this theme share? $key_required : ' . $key_required[$nx1] ? 'true' : 'false');
            if ($key_required[$nx1] && $themeshare && !is_null($fc_category_ref)) {
                $k = generate_collection_access_key($fc_category_ref, $feedback, $emails[$nx1], $access, $expires, $group, $sharepwd, $reflist);
                if ($k !== false) {
                    $fc_key = "&k={$k}";
                }
            }

            for ($nx2 = 0; $nx2 < count($reflist); $nx2++) {
                debug('email_collection : generating access keys for collection ref : ' . $reflist[$nx2] . ' if needed');
                $key = "";
                $fc_key = "";
                $emailcollectionmessageexternal = false;

                # Do we need to add an external access key for this user (e-mail specified rather than username)?
                if ($key_required[$nx1] && !$themeshare) {
                    debug('email_collection : key required for ' . $emails[$nx1]);
                    $k = generate_collection_access_key($reflist[$nx2], $feedback, $emails[$nx1], $access, $expires, $group, $sharepwd);
                    if ($k !== false) {
                        $key = "&k={$k}";
                    }
                    $emailcollectionmessageexternal = true;
                }
                // If FC category, the key is valid across all sub-featured collections. See generate_collection_access_key()
                elseif ($key_required[$nx1] && $themeshare && !is_null($fc_category_ref)) {
                    $key = $fc_key;
                    $emailcollectionmessageexternal = true;
                }
                $url = $baseurl .   "/?c=" . $reflist[$nx2] . $key;
                $collection = array();
                $collection = ps_query("SELECT name,savedsearch FROM collection WHERE ref = ?", ["i",$reflist[$nx2]]);
                if ($collection[0]["name"] != "") {
                    $collection_name = i18n_get_collection_name($collection[0]);
                } else {
                    $collection_name = $reflist[$nx2];
                }
                if ($use_phpmailer) {
                    $link = '<a href="' . $url . '">' . escape($collection_name) . '</a>';
                    $list .= $htmlbreak . $link;
                    // alternate list style
                    $list2 .= $htmlbreak . $collection_name . ' -' . $htmlbreaksingle . $url;
                    $templatevars['list2'] = $list2;
                } else {
                    $list .= $htmlbreak . $collection_name . $htmlbreak . $url . $htmlbreak;
                }
                #log this
                collection_log($reflist[$nx2], LOG_CODE_COLLECTION_EMAILED_COLLECTION, 0, $emails[$nx1]);
            }
        }
        $templatevars['list'] = $list;
        $templatevars['from_name'] = $from_name;
        if (isset($k)) {
            if ($expires == "") {
                $templatevars['expires_date'] = $lang["email_link_expires_never"];
                $templatevars['expires_days'] = $lang["email_link_expires_never"];
            } else {
                $day_count = round((strtotime($expires) - strtotime('now')) / (60 * 60 * 24));
                $templatevars['expires_date'] = $lang['email_link_expires_date'] . nicedate($expires);
                $templatevars['expires_days'] = $lang['email_link_expires_days'] . $day_count;
                if ($day_count > 1) {
                    $templatevars['expires_days'] .= " " . $lang['expire_days'] . ".";
                } else {
                    $templatevars['expires_days'] .= " " . $lang['expire_day'] . ".";
                }
            }
        } else {
            # Set empty expiration templatevars
            $templatevars['expires_date'] = '';
            $templatevars['expires_days'] = '';
        }
        $body = "";
        if ($emailcollectionmessageexternal) {
            $template = ($themeshare) ? "emailthemeexternal" : "emailcollectionexternal";
            // External - send email
            if (is_array($emails) && (count($emails) > 1) && $list_recipients === true) {
                $body = $lang["list-recipients"] . "\n" . implode("\n", $emails) . "\n\n";
                $templatevars['list-recipients'] = $lang["list-recipients"] . "\n" . implode("\n", $emails) . "\n\n";
            }
            if (substr($viewlinktext, 0, 5) == "lang_") {
                $langkey = substr($viewlinktext, 5);
                if (isset($lang[$langkey])) {
                    $viewlinktext = $lang[$langkey];
                }
            }
            $body .= $templatevars['fromusername'] . " " . $externalmessage . "\n\n" . $templatevars['message'] . "\n\n" . $viewlinktext . "\n\n" . $templatevars['list'];

            $emailsubject = $notifymessage->get_subject();
            $send_result = send_mail($emails[$nx1], $emailsubject, $body, $fromusername, $useremail, $template, $templatevars, $from_name, $cc);
            if ($send_result !== true) {
                return $send_result;
            }
        } else {
            $template = ($themeshare) ? "emailtheme" : "emailcollection";
        }
        $viewlinktext = $origviewlinktext;
    }

    if (count($internal_user_ids) > 0) {
        // Internal share, send notifications
        $notifymessage->append_text($templatevars['fromusername'] . "&nbsp;");
        $notifymessage->append_text($internalmessage);
        $notifymessage->append_text("<br/><br/>" . $templatevars['message'] . "<br/><br/>");
        $notifymessage->append_text($viewlinktext);
        $notifymessage->url = $url;
        send_user_notification($internal_user_ids, $notifymessage);
    }

    hook("additional_email_collection", "", array($colrefs,$collectionname,$fromusername,$userlist,$message,$feedback,$access,$expires,$useremail,$from_name,$cc,$themeshare,$themename,$themeurlsuffix,$template,$templatevars));

    # Identify user accounts which have been skipped
    $candidate_users = ps_query("SELECT ref, username FROM user 
       WHERE username IN ("  . ps_param_insert(count($ulist)) . ")", ps_param_fill($ulist, "s"));
    $skipped_usernames = array();
    if (count($candidate_users) != count($internal_user_ids)) {
        foreach ($candidate_users as $candidate_user) {
            if (!in_array($candidate_user['ref'], $internal_user_ids)) {
                $skipped_usernames[] = $candidate_user['username'];
            }
        }
    }

    # Report skipped accounts
    if (count($skipped_usernames) > 0) {
        return $lang['email_error_user_list_some_skipped'] . ' ' . implode(', ', $skipped_usernames);
    }

    # Return an empty string (all OK).
    return "";
}

/**
 * Generate an external access key to allow external people to view the resources in this collection.
 *
 * @param  integer  $collection  Collection ref -or- collection data structure
 * @param  integer  $feedback
 * @param  string   $email
 * @param  integer  $access
 * @param  string   $expires
 * @param  string   $group
 * @param  string   $sharepwd
 * @param  array    $sub_fcs     List of sub-featured collections IDs (collection_email.php page has logic to determine
 *                               this which is carried forward to email_collection())
 *
 * @return string   The generated key used for external sharing
 */
function generate_collection_access_key($collection, $feedback = 0, $email = "", $access = -1, $expires = "", $group = "", $sharepwd = "", array $sub_fcs = array())
{
    global $userref, $usergroup, $scramble_key, $username, $anonymous_login;

    if (is_anonymous_user()) {
        // Block anon users from generating keys as they're unneeded.
        return false;
    }

    // Default to sharing with the permission of the current usergroup if not specified OR no access to alternative group selection.
    if ($group == "" || !checkperm("x")) {
        $group = $usergroup;
    }

    if (!is_array($collection)) {
        $collection = get_collection($collection);
    }

    if (!empty($collection) && $collection["type"] == COLLECTION_TYPE_FEATURED && !isset($collection["has_resources"])) {
        $collection_resources = get_collection_resources($collection["ref"]);
        $collection["has_resources"] = (is_array($collection_resources) && !empty($collection_resources) ? 1 : 0);
    }
    $is_featured_collection_category = is_featured_collection_category($collection);

    // We build a collection list to allow featured collections children that are externally shared as part of a parent,
    // to all be shared with the same parameters (e.g key, access, group). When the collection is not COLLECTION_TYPE_FEATURED
    // this will hold just that collection
    $collections = array($collection["ref"]);
    if ($is_featured_collection_category) {
        $collections = (!empty($sub_fcs) ? $sub_fcs : get_featured_collection_categ_sub_fcs($collection));
    }

    // Generate the key based on the original collection. For featured collection category, all sub featured collections
    // will share the same key
    $k = generate_share_key($collection["ref"]);

    if ($expires != '') {
        $expires = date_format(date_create($expires), 'Y-m-d') . ' 23:59:59';
    }

    $main_collection = $collection; // keep record of this info as we need it at the end to record the successful generation of a key for a featured collection category
    $created_sub_fc_access_key = false;
    foreach ($collections as $collection) {
        $r = get_collection_resources($collection);
        $shareable_resources = array_filter($r, function ($resource_ref) {
            return can_share_resource($resource_ref);
        });
        foreach ($shareable_resources as $resource_ref) {
            $sql = '';
            $params = [];
            if ($expires == '') {
                $sql = 'NULL, ';
            } else {
                $sql = '?, ';
                $params[] = 's';
                $params[] = $expires;
            }
            if (!($sharepwd != "" && $sharepwd != "(unchanged)")) {
                $sql .= 'NULL';
            } else {
                $sql .= '?';
                $params[] = 's';
                $params[] = hash("sha256", $k . $sharepwd . $scramble_key);
            }
            ps_query(
                "INSERT INTO external_access_keys(resource, access_key, collection, `user`, usergroup, request_feedback, email, `date`, access, expires, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, {$sql})",
                array_merge(
                    [
                    'i', $resource_ref,
                    's', $k,
                    'i', $collection,
                    'i', $userref,
                    'i', $group,
                    's', $feedback,
                    's', $email,
                    'i', $access
                    ],
                    $params
                )
            );
            $created_sub_fc_access_key = true;
        }
    }

    if ($is_featured_collection_category && $created_sub_fc_access_key) {
        $sql = '';
        $params = [];
        if ($expires == '') {
            $sql = 'NULL, ';
        } else {
            $sql = '?, ';
            $params[] = 's';
            $params[] = $expires;
        }
        if (!($sharepwd != "" && $sharepwd != "(unchanged)")) {
            $sql .= 'NULL';
        } else {
            $sql .= '?';
            $params[] = 's';
            $params[] = hash("sha256", $k . $sharepwd . $scramble_key);
        }
        // add for FC category. No resource. This is a dummy record so we can have a way to edit the external share done
        // at the featured collection category level
        ps_query(
            "INSERT INTO external_access_keys(resource, access_key, collection, `user`, usergroup, request_feedback, email, `date`, access, expires, password_hash) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW(), ?, {$sql})",
            array_merge(
                [
                's', $k,
                'i', $main_collection["ref"],
                'i', $userref,
                'i', $group,
                's', $feedback,
                's', $email,
                'i', $access
                ],
                $params
            )
        );
    }

    return $k;
}

/**
 * Returns all saved searches in a collection
 *
 * @param  integer $collection
 */
function get_saved_searches($collection): array
{
    return ps_query("select " . columns_in("collection_savedsearch") . " from collection_savedsearch where collection= ? order by created", ['i', $collection]);
}

/**
 * Add a saved search to a collection
 *
 * @param  integer $collection
 * @return void
 */
function add_saved_search($collection)
{
    ps_query("insert into collection_savedsearch(collection,search,restypes,archive) values (?,?,?,?)", array("i",$collection,"s",getval("addsearch", ""),"s",getval("restypes", ""),"s",getval("archive", "")));
}

/**
 * Remove a saved search from a collection
 *
 * @param  integer $collection
 * @param  integer $search
 * @return void
 */
function remove_saved_search($collection, $search)
{
    ps_query("delete from collection_savedsearch where collection=? and ref=?", array("i",$collection,"i",$search));
}

/**
 * Greate a new smart collection using submitted values
 *
 * @return void
 */
function add_smart_collection()
{
    global $userref, $search_all_workflow_states, $lang;

    $search = getval("addsmartcollection", "");
    $restypes = getval("restypes", "");
    if ($restypes == "Global") {
        $restypes = "";
    }
    # archive can be a string of values
    $archive = getval('archive', 0, false);
    if ($search_all_workflow_states && $archive == "") {
        $archive = 'all';
    }

    if ($archive == "") {
        $archive = 0;
    }

    // more compact search strings should work with get_search_title
    $searchstring = array();
    if ($search != "") {
        $searchstring[] = "search=$search";
    }
    if ($restypes != "") {
        $searchstring[] = "restypes=$restypes";
    }
    if ($archive !== 0) {
        if ($archive === 'all') {
            $archive_label = $lang['all_workflow_states'];
        } else {
            $archive_label = $archive;
        }
        $searchstring[] = "archive=$archive_label";
    }
    $searchstring = implode("&", $searchstring);

    $newcollection = create_collection($userref, get_search_title($searchstring), 1);

    ps_query("insert into collection_savedsearch(collection,search,restypes,archive,starsearch) 
        values (?,?,?,?,?)", array("i",$newcollection,"s",$search,"s",$restypes,"s",$archive,"i",DEPRECATED_STARSEARCH));
    $savedsearch = sql_insert_id();
    ps_query("update collection set savedsearch=? where ref=?", array("i",$savedsearch,"i",$newcollection));
    set_user_collection($userref, $newcollection);
    refresh_collection_frame($newcollection);
}

/**
 * Get a display friendly name for the given search string
 * Takes a full searchstring of the form 'search=restypes=archive=' and
 * uses search_title_processing to autocreate a more informative title
 *
 * @param  string $searchstring     Search string
 *
 * @return string Friendly name for search
 */
function get_search_title($searchstring)
{
    $order_by = "";
    $sort = "";
    $offset = "";
    $k = getval("k", "");

    $search_titles = true;
    $search_titles_searchcrumbs = true;
    $use_refine_searchstring = true;

    global $lang,$userref,$baseurl,$collectiondata,$result,$display,$pagename,$collection,$userrequestmode;

    parse_str($searchstring, $searchvars);
    if (isset($searchvars["archive"])) {
        $archive = $searchvars["archive"];
    } else {
        $archive = 0;
    }
    if (isset($searchvars["search"])) {
        $search = $searchvars["search"];
    } else {
        $search = "";
    }
    if (isset($searchvars["restypes"])) {
        $restypes = $searchvars["restypes"];
    } else {
        $restypes = "";
    }

    include __DIR__ . "/search_title_processing.php";

    if ($restypes != "") {
        $resource_types = get_resource_types($restypes, true, false, true);
        foreach ($resource_types as $type) {
            $typenames[] = $type['name'];
        }
        $search_title .= " [" . implode(', ', $typenames) . "]";
    }

    return str_replace(">", "", strip_tags(htmlspecialchars_decode($search_title)));
}

/**
 * Adds all the resources in the provided search to $collection
 *
 * @param  integer $collection
 * @param  string  $search
 * @param  string  $restypes
 * @param  string  $archivesearch
 * @param  string  $order_by
 * @param  string  $sort
 * @param  string  $daylimit
 * @param  int     $res_access          The ID of the resource access level
 * @param  boolean $editable_only       If true then only editable resources will be added
 * @return boolean
 */
function add_saved_search_items(
    $collection,
    $search = "",
    $restypes = "",
    $archivesearch = "",
    $order_by = "relevance",
    $sort = "desc",
    $daylimit = "",
    $res_access = "",
    $editable_only = false
) {
    if ((string)(int)$collection != $collection) {
        // Not an integer
        return false;
    }

    global $collection_share_warning, $collection_allow_not_approved_share, $userref, $collection_block_restypes, $search_all_workflow_states;

    # Adds resources from a search to the collection.
    if ($search_all_workflow_states && trim($archivesearch) !== "" && $archivesearch != 0) {
        $search_all_workflow_states = false;
    }

    $results = do_search($search, $restypes, $order_by, $archivesearch, [0,-1], $sort, false, DEPRECATED_STARSEARCH, false, false, $daylimit, false, true, false, $editable_only, false, $res_access);

    if (!is_array($results) || (isset($results["total"]) && $results["total"] == 0)) {
        return false;
    }

    // To maintain current collection order but add the search items in the correct order we must first move the existing collection resources out the way
    $searchcount = $results["total"];
    if ($searchcount > 0) {
        ps_query(
            "UPDATE collection_resource SET sortorder = if(isnull(sortorder), ?,sortorder + ?) WHERE collection= ?",
            [
            'i', $searchcount,
            'i', $searchcount,
            'i', $collection
            ]
        );
    }

    // If this is a featured collection apply all the external access keys from the categories which make up its
    // branch path to prevent breaking existing shares for any of those featured collection categories.
    $fc_branch_path_keys = [];
    $collection_data = get_collection($collection, true);
    if ($collection_data !== false && $collection_data['type'] === COLLECTION_TYPE_FEATURED) {
        $branch_category_ids = array_column(
            // determine the branch from the parent because the keys for the collection in question will be done below
            get_featured_collection_category_branch_by_leaf((int)$collection_data['parent'], []),
            'ref'
        );
        foreach ($branch_category_ids as $fc_category_id) {
            $fc_branch_path_keys = array_merge(
                $fc_branch_path_keys,
                get_external_shares([
                    'share_collection' => $fc_category_id,
                    'share_type' => 0,
                    'ignore_permissions' => true,
                ])
            );
        }
    }

    # Check if this collection has already been shared externally. If it has, we must add a further entry
    # for this specific resource, and warn the user that this has happened.
    $keys = array_merge(
        get_external_shares([
            'share_collection' => $collection,
            'share_type' => 0,
            'ignore_permissions' => true,
        ]),
        $fc_branch_path_keys
    );
    $resourcesnotadded = array(); # record the resources that are not added so we can display to the user
    $blockedtypes = array();# Record the resource types that are not added

    foreach ($results["data"] as $result) {
        $resource = $result["ref"];
        $archivestatus = $result["archive"];

        if (in_array($result["resource_type"], $collection_block_restypes)) {
            $blockedtypes[] = $result["resource_type"];
            continue;
        }

        if (count($keys) > 0) {
            if (($archivestatus < 0 && !$collection_allow_not_approved_share) || !can_share_resource($resource)) {
                $resourcesnotadded[$resource] = $result;
                continue;
            }

            for ($n = 0; $n < count($keys); $n++) {
                $sql = '';
                $params = [];
                if ($keys[$n]["expires"] == '') {
                    $sql .= 'NULL, ';
                } else {
                    $sql .= '?, ';
                    $params[] = 's';
                    $params[] = $keys[$n]["expires"];
                }
                if ($keys[$n]["usergroup"] == '') {
                    $sql .= 'NULL';
                } else {
                    $sql .= '?';
                    $params[] = 'i';
                    $params[] = $keys[$n]["usergroup"];
                }
                # Insert a new access key entry for this resource/collection.
                ps_query(
                    "INSERT INTO external_access_keys(resource,access_key,user,collection,date,access,password_hash,expires,usergroup) VALUES (?, ?, ?, ?,NOW(), ?, ?, {$sql})",
                    array_merge([
                    'i', $resource,
                    's', $keys[$n]["access_key"],
                    'i', $userref,
                    'i', $collection,
                    's', $keys[$n]["access"],
                    's', $keys[$n]["password_hash"]
                    ], $params)
                );
                #log this
                collection_log($collection, LOG_CODE_COLLECTION_SHARED_RESOURCE_WITH, $resource, $keys[$n]["access_key"]);

                # Set the flag so a warning appears.
                $collection_share_warning = true;
            }
        }
    }

    if (is_array($results["data"])) {
        $n = 0;
        foreach ($results["data"] as $result) {
            $resource = $result["ref"];
            if (!isset($resourcesnotadded[$resource]) && !in_array($result["resource_type"], $collection_block_restypes)) {
                ps_query("DELETE FROM collection_resource WHERE resource=? AND collection=?", array("i",$resource,"i",$collection));
                ps_query("INSERT INTO collection_resource(resource,collection,sortorder) VALUES (?,?,?)", array("i",$resource,"i",$collection,"s",$n));

                #log this
                collection_log($collection, LOG_CODE_COLLECTION_ADDED_RESOURCE, $resource);
                $n++;
            }
        }
    }

    // Clear theme image cache
    clear_query_cache('themeimage');
    clear_query_cache('col_total_ref_count_w_perm');

    if (!empty($resourcesnotadded) || count($blockedtypes) > 0) {
        # Translate to titles only for displaying them to the user
        global $view_title_field;
        $titles = array();
        foreach ($resourcesnotadded as $resource) {
            $titles[] = i18n_get_translated($resource['field' . $view_title_field]);
        }
        if (count($blockedtypes) > 0) {
            $blocked_restypes = array_unique($blockedtypes);
            // Return a list of blocked resouce types
            $titles["blockedtypes"] = $blocked_restypes;
        }
        return $titles;
    }

    return array();
}

/**
 * Returns true or false, can all resources in this collection be edited by the user?
 *
 * @param  array|int  $collection     Collection IDs
 * @param  array      $collectionid
 *
 * @return boolean
 */
function allow_multi_edit($collection, $collectionid = 0)
{
    global $resource;

    if (is_array($collection) && $collectionid == 0) {
        // Do this the hard way by checking every resource for edit access
        for ($n = 0; $n < count($collection); $n++) {
            $resource = $collection[$n];
            if (!get_edit_access($collection[$n]["ref"], $collection[$n]["archive"], $collection[$n])) {
                return false;
            }
        }
        # All have edit access
        return true;
    } else {
        // Instead of checking each resource we can do a comparison between a search for all resources in collection and a search for editable resources
        $resultcount = 0;
        $all_resource_refs = array();
        if (!is_array($collection)) {
            // Need the collection resources so need to run the search
            $collectionid = $collection;
            # Editable_only=false (so returns resources whether editable or not)
            $collection = do_search("!collection{$collectionid}", '', '', 0, -1, '', false, 0, false, false, '', false, false, true, false);
        }
        if (is_array($collection)) {
            $resultcount = count($collection);
        }
        $editcount = 0;
        # Editable_only=true (so returns editable resources only)
        $editresults =  do_search("!collection{$collectionid}", '', '', 0, -1, '', false, 0, false, false, '', false, false, true, true);
        if (is_array($editresults)) {
            $editcount = count($editresults);
        }

        if ($resultcount == $editcount) {
            return true;
        }

        # Counts differ meaning there are non-editable resources
        $all_resource_refs = array_column($collection, "ref");
        $editable_resource_refs = array_column($editresults, "ref");
        $non_editable_resource_refs = array_diff($all_resource_refs, $editable_resource_refs);

        # Is grant edit present for all non-editables?
        foreach ($non_editable_resource_refs as $non_editable_ref) {
            if (!hook('customediteaccess', '', array($non_editable_ref))) {
                return false;
            }
        }

        # All non_editables have grant edit
        return true;
    }
}

/**
* Get featured collection resources (including from child nodes). For normal FCs this is using the collection_resource table.
* For FC categories, this will check within normal FCs contained by that category. Normally used in combination with
* generate_featured_collection_image_urls() but useful to determine if a FC category is full of empty FCs.
*
* @param array $c   Collection data structure similar to the one returned by {@see get_featured_collections()}
* @param array $ctx Extra context used to get FC resources (e.g smart FC?, limit on number of resources returned). Context
*                   information should take precedence over internal logic (e.g determining the result limit)
*
* @return array
*/
function get_featured_collection_resources(array $c, array $ctx)
{
    global $usergroup, $userref, $CACHE_FC_RESOURCES, $themes_simple_images,$collection_allow_not_approved_share;
    global $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS, $theme_images_number;

    if (!isset($c["ref"]) || !is_int((int) $c["ref"])) {
        return array();
    }

    $CACHE_FC_RESOURCES = (!is_null($CACHE_FC_RESOURCES) && is_array($CACHE_FC_RESOURCES) ? $CACHE_FC_RESOURCES : array());
    // create a unique ID for this result set as the context for the same FC may differ
    $cache_id = $c["ref"] . md5(json_encode($ctx));
    if (isset($CACHE_FC_RESOURCES[$cache_id])) {
        return $CACHE_FC_RESOURCES[$cache_id];
    }

    $limit = (isset($ctx["limit"]) && (int) $ctx["limit"] > 0 ? (int) $ctx["limit"] : null);
    $use_thumbnail_selection_method = (isset($ctx["use_thumbnail_selection_method"]) ? (bool) $ctx["use_thumbnail_selection_method"] : false);

    // Smart FCs
    if (isset($ctx["smart"]) && $ctx["smart"] === true) {
        // Root smart FCs don't have an image (legacy reasons)
        if (is_null($c["parent"])) {
            return array();
        }

        $node_search = NODE_TOKEN_PREFIX . $c['ref'];
        $limit = (!is_null($limit) ? $limit : 1);

        // Access control is still in place (i.e. permissions are honoured)
        $smart_fc_resources = do_search($node_search, '', 'hit_count', 0, $limit, 'desc', false, 0, false, false, '', true, false, true);
        $smart_fc_resources = (is_array($smart_fc_resources) ? array_column($smart_fc_resources, "ref") : array());

        $CACHE_FC_RESOURCES[$cache_id] = $smart_fc_resources;
        return $smart_fc_resources;
    }

    // Access control
    $rca_where = '';
    $rca_where_params = array();
    $rca_joins = array();
    $rca_join_params = array();
    $fc_permissions_where = '';
    $fc_permissions_where_params = [];
    $union = "";
    $unionparams = [];
    if (!checkperm("v")) {
        // Add joins for user and group custom access
        $rca_joins[] = 'LEFT JOIN resource_custom_access AS rca_u ON r.ref = rca_u.resource AND rca_u.user = ? AND (rca_u.user_expires IS NULL OR rca_u.user_expires > now())';
        $rca_join_params [] = "i";
        $rca_join_params [] = $userref;

        $rca_joins[] = 'LEFT JOIN resource_custom_access AS rca_ug ON r.ref = rca_ug.resource AND rca_ug.usergroup = ?';
        $rca_join_params [] = "i";
        $rca_join_params [] = $usergroup;

        $rca_where = 'AND (r.access < ? OR (r.access IN (?, ?) AND ((rca_ug.access IS NOT NULL AND rca_ug.access < ?) OR (rca_u.access IS NOT NULL AND rca_u.access < ?))))';
        $rca_where_params = array("i", RESOURCE_ACCESS_CONFIDENTIAL, "i", RESOURCE_ACCESS_CONFIDENTIAL, "i", RESOURCE_ACCESS_CUSTOM_GROUP, "i", RESOURCE_ACCESS_CONFIDENTIAL, "i", RESOURCE_ACCESS_CONFIDENTIAL);

        $fcf_sql = featured_collections_permissions_filter_sql("AND", "c.ref");
        if (is_array($fcf_sql)) {
            $fc_permissions_where = "AND (c.`type` = ? " . $fcf_sql[0] . ")";
            $fc_permissions_where_params = array_merge(["i",COLLECTION_TYPE_FEATURED], $fcf_sql[1]);
        }
    }

    if ($use_thumbnail_selection_method && isset($c["thumbnail_selection_method"])) {
        if ($c["thumbnail_selection_method"] == $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["no_image"]) {
            return array();
        } elseif ($c["thumbnail_selection_method"] == $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["manual"] && isset($c["bg_img_resource_ref"])) {
            $limit = 1;
            $union = sprintf(
                "
                UNION SELECT ref, 1 AS use_as_theme_thumbnail, r.hit_count FROM resource AS r %s WHERE r.ref = ? %s",
                implode(" ", $rca_joins),
                $rca_where
            );

            $unionparams = array_merge($rca_join_params, ["i",$c["bg_img_resource_ref"]], $rca_where_params);
        }
        // For most_popular_image & most_popular_images we change the limit only if it hasn't been provided by the context.
        elseif (in_array($c["thumbnail_selection_method"], [$FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"],$FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_recent_image"]]) && is_null($limit)) {
            $limit = 1;
        } elseif ($c["thumbnail_selection_method"] == $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_images"] && is_null($limit)) {
            $limit = $theme_images_number;
        }
    }

    $resource_join = "JOIN resource AS r ON r.ref = cr.resource AND r.ref > 0";
    if (!$collection_allow_not_approved_share) {
        $resource_join .= " AND r.archive = 0";
    }
    // A SQL statement. Each array index represents a different SQL clause.
    $subquery = array(
        "select" => "SELECT r.ref, cr.use_as_theme_thumbnail, r.hit_count",
        "from" => "FROM collection AS c",
        "join" => array_merge(
            array(
                "JOIN collection_resource AS cr ON cr.collection = c.ref",
                $resource_join,
            ),
            $rca_joins
        ),
        "where" => "WHERE c.ref = ? AND c.`type` = ?",
    );
    $subquery_params = array_merge($rca_join_params, array("i", $c["ref"], "i", COLLECTION_TYPE_FEATURED), $rca_where_params);

    if (is_featured_collection_category($c)) {
        $all_fcs = ps_query("SELECT ref, parent FROM collection WHERE `type`=?", array("i",COLLECTION_TYPE_FEATURED), "featured_collections");
        $all_fcs_rp = array_column($all_fcs, 'parent', 'ref');

        // Array to hold resources
        $fcresources = array();

        // Create stack of collections to search
        // (not a queue as we want to get to the lowest child collections first where the resources are)
        $colstack = new SplStack(); //
        $children = array_keys($all_fcs_rp, $c["ref"]);
        foreach ($children as $child_fc) {
            $colstack->push($child_fc);
        }

        while ((is_null($limit) || count($fcresources) < $limit) && !$colstack->isEmpty()) {
            $checkfc = $colstack->pop();
            if (!in_array($checkfc, $all_fcs_rp)) {
                $subfcimages = get_collection_resources($checkfc);
                if (is_array($subfcimages) && count($subfcimages) > 0) {
                    // The join defined above specifically excludes any resources that are not in the active archive state,
                    // for the limiting via $ctx to function correctly we'll need to check for each resources state before adding it  to fcresources
                    $resources = get_resource_data_batch($subfcimages);
                    if (!$collection_allow_not_approved_share) {
                        $resources = array_filter($resources, function ($r) {
                            return $r['archive'] == "0";
                        });
                    }
                    $fcresources = array_merge($fcresources, array_column($resources, 'ref'));
                }
                continue;
            }

            // Either a parent FC or no results, add sub fcs to stack
            $children = array_keys($all_fcs_rp, $checkfc);
            foreach ($children as $child_fc) {
                $colstack->push($child_fc);
            }
        }
        $fcrescount = count($fcresources);
        if ($fcrescount > 0) {
            $chunks = [$fcresources];
            // Large numbers of query parameters can cause errors so chunking may be required for larger collections.
            if ($fcrescount > 20000) {
                $chunks = array_chunk($fcresources, 20000);
            }
            $fc_resources = [];
            $subquery["join"] = implode(" ", $subquery["join"]);
            foreach ($chunks as $fcresources) {
                $subquery["where"] = " WHERE r.ref IN (" . ps_param_insert(count($fcresources)) . ")";
                $subquery_params = array_merge($rca_join_params, ps_param_fill($fcresources, "i"), $rca_where_params);
                $subquery["where"] .= " {$rca_where} {$fc_permissions_where}";
                $subquery_params = array_merge($subquery_params, $fc_permissions_where_params);

                $sql = sprintf(
                    "SELECT DISTINCT ti.ref AS `value`, ti.use_as_theme_thumbnail, ti.hit_count FROM (%s %s) AS ti ORDER BY ti.use_as_theme_thumbnail DESC, ti.hit_count DESC, ti.ref DESC %s",
                    implode(" ", $subquery),
                    $union,
                    sql_limit(null, $limit)
                );
                $fc_resources = array_merge($fc_resources, ps_array($sql, array_merge($subquery_params, $unionparams), "themeimage"));
            }
                $CACHE_FC_RESOURCES[$cache_id] = $fc_resources;
                return $fc_resources;
        }
    }

    $subquery["join"] = implode(" ", $subquery["join"]);
    $subquery["where"] .= " {$rca_where} {$fc_permissions_where}";
    $subquery_params = array_merge($subquery_params, $fc_permissions_where_params);

    $order_by = "ti.use_as_theme_thumbnail DESC, ti.hit_count DESC, ti.ref DESC";
    if ($c["thumbnail_selection_method"] == $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_recent_image"]) {
        $order_by = "ti.ref DESC";
    }
    $sql = sprintf(
        "SELECT DISTINCT ti.ref AS `value`, ti.use_as_theme_thumbnail, ti.hit_count FROM (%s %s) AS ti ORDER BY %s %s",
        implode(" ", $subquery),
        $union,
        $order_by,
        sql_limit(null, $limit)
    );

    $fc_resources = ps_array($sql, array_merge($subquery_params, $unionparams), "themeimage");
    $CACHE_FC_RESOURCES[$cache_id] = $fc_resources;
    return $fc_resources;
}

/**
* Get a list of featured collections based on a higher level featured collection category. This returns all direct/indirect
* collections under that category.
*
* @param array $c   Collection data structure
* @param array $ctx Contextual data (e.g disable access control). This param MUST NOT get exposed over the API
*
* @return array
*/
function get_featured_collection_categ_sub_fcs(array $c, array $ctx = array())
{
    global $CACHE_FC_CATEG_SUB_FCS;
    $CACHE_FC_CATEG_SUB_FCS = (!is_null($CACHE_FC_CATEG_SUB_FCS) && is_array($CACHE_FC_CATEG_SUB_FCS) ? $CACHE_FC_CATEG_SUB_FCS : array());
    if (isset($CACHE_FC_CATEG_SUB_FCS[$c["ref"]])) {
        return $CACHE_FC_CATEG_SUB_FCS[$c["ref"]];
    }

    $access_control = (isset($ctx["access_control"]) && is_bool($ctx["access_control"]) ? $ctx["access_control"] : true);
    $all_fcs = (isset($ctx["all_fcs"]) && is_array($ctx["all_fcs"]) && !empty($ctx["all_fcs"]) ? $ctx["all_fcs"] : get_all_featured_collections());

    $collections = array();

    $allowed_fcs = ($access_control ? compute_featured_collections_access_control() : true);
    if ($allowed_fcs === false) {
        $CACHE_FC_CATEG_SUB_FCS[$c["ref"]] = $collections;
        return $collections;
    } elseif (is_array($allowed_fcs)) {
        $allowed_fcs_flipped = array_flip($allowed_fcs);

        // Collection is not allowed
        if (!isset($allowed_fcs_flipped[$c['ref']])) {
            $CACHE_FC_CATEG_SUB_FCS[$c["ref"]] = $collections;
            return $collections;
        }
    }

    $all_fcs_rp = reshape_array_by_value_keys($all_fcs, 'ref', 'parent');
    $all_fcs = array_flip_by_value_key($all_fcs, 'ref');

    $queue = new SplQueue();
    $queue->setIteratorMode(SplQueue::IT_MODE_DELETE);
    $queue->enqueue($c['ref']);

    while (!$queue->isEmpty()) {
        $fc = $queue->dequeue();
        $fc_children = array();

        if (
            $all_fcs[$fc]['has_resources'] > 0
            && (
                $allowed_fcs === true
                || (is_array($allowed_fcs) && isset($allowed_fcs_flipped[$fc]))
            )
        ) {
            $collections[] = $fc;
        } elseif ($all_fcs[$fc]['has_children'] > 0) {
            $fc_children = array_keys($all_fcs_rp, $fc);
        }

        foreach ($fc_children as $fc_child_ref) {
            $queue->enqueue($fc_child_ref);
        }
    }

    $CACHE_FC_CATEG_SUB_FCS[$c["ref"]] = $collections;

    debug("get_featured_collection_categ_sub_fcs(ref = {$c["ref"]}): returned collections: " . implode(", ", $collections));
    return $collections;
}

/**
* Get preview URLs for a list of resource IDs
*
* @param array  $resource_refs  List of resources
* @param string $size           Preview size
*
* @return array List of resource refs and corresponding images URLs
*/
function generate_featured_collection_image_urls(array $resource_refs, string $size)
{
    global $baseurl;

    $images = array();

    $refs_list = array_filter($resource_refs, 'is_numeric');
    if (empty($refs_list)) {
        return $images;
    }

    $refs_rtype = ps_query("SELECT ref, resource_type, file_extension FROM resource WHERE ref IN (" . ps_param_insert(count($refs_list)) . ")", ps_param_fill($refs_list, "i"), 'featured_collections');

    foreach ($refs_rtype as $ref_rt) {
        $ref = $ref_rt['ref'];
        $resource_type = $ref_rt['resource_type'];

        if (file_exists(get_resource_path($ref, true, $size, false)) && resource_download_allowed($ref, $size, $resource_type, -1, true)) {
            $images[] = ["ref" => $ref, "path" => get_resource_path($ref, false, $size, false)];
        }
    }

    if (count($images) == 0 && count($refs_rtype) != 0) {
        $images[] = $baseurl . '/gfx/no_preview/default.png';
    }

    return $images;
}

/**
 * Inserts $resource1 into the position currently occupied by $resource2
 *
 * @param  integer $resource1
 * @param  integer $resource2
 * @param  integer $collection
 * @return void
 */
function swap_collection_order($resource1, $resource2, $collection)
{

    // sanity check -- we should only be getting IDs here
    if (!is_numeric($resource1) || !is_numeric($resource2) || !is_numeric($collection)) {
        exit("Error: invalid input to swap collection function.");
    }

    $query = "select resource,date_added,sortorder  from collection_resource where collection=? and resource in (?,?)  order by sortorder asc, date_added desc";
    $existingorder = ps_query($query, array("i",$collection,"i",$resource1,"i",$resource2));

    $counter = 1;
    foreach ($existingorder as $record) {
        $rec[$counter]['resource'] = $record['resource'];
        $rec[$counter]['date_added'] = $record['date_added'];
        if (strlen($record['sortorder']) == 0) {
            $rec[$counter]['sortorder'] = "NULL";
        } else {
            $rec[$counter]['sortorder'] = "'" . $record['sortorder'] . "'";
        }

        $counter++;
    }

    ps_query(
        "update collection_resource set date_added = ?, sortorder = ? where collection = ? and resource = ?",
        [
        's', $rec[1]['date_added'],
        'i', $rec[1]['sortorder'],
        'i', $collection,
        'i', $rec[2]['resource']
        ]
    );
    ps_query(
        "update collection_resource set date_added = ?, sortorder = ? where collection = ? and resource = ?",
        [
        's', $rec[2]['date_added'],
        'i', $rec[2]['sortorder'],
        'i', $collection,
        'i', $rec[1]['resource']
        ]
    );
}

/**
 * Reorder the items in a collection using $neworder as the order by metric
 *
 * @param  array $neworder  Array of columns to order by
 * @param  integer $collection
 * @param  integer $offset
 * @return void
 */
function update_collection_order($neworder, $collection, $offset = 0)
{
    if (!is_array($neworder)) {
        exit("Error: invalid input to update collection function.");
    }

    $neworder = array_filter($neworder, 'is_numeric');
    if (count($neworder) > 0) {
        $updatesql = "update collection_resource set sortorder=(case resource ";
        $counter = 1 + $offset;
        $params = [];
        foreach ($neworder as $colresource) {
            $updatesql .= "when ? then ? ";
            $params = array_merge($params, ['i', $colresource, 'i', $counter]);
            $counter++;
        }
        $updatesql .= "else sortorder END) WHERE collection= ?";
        ps_query($updatesql, array_merge($params, ['i', $collection]));
    }
    $updatesql = "update collection_resource set sortorder=99999 WHERE collection= ? and sortorder is NULL";
    ps_query($updatesql, ['i', $collection]);
}

/**
 * Return comments and other columns stored in the collection_resource join.
 *
 * @param  integer $resource
 * @param  integer $collection
 * @return array|bool Returns found record data, false otherwise
 */
function get_collection_resource_comment($resource, $collection)
{
    $data = ps_query("select " . columns_in("collection_resource") . " from collection_resource where collection=? and resource=?", array("i",$collection,"i",$resource), "");
    if (!isset($data[0])) {
        return false;
    }
    return $data[0];
}

/**
 * Save a comment and/or rating for the instance of a resource in a collection.
 *
 * @param  integer $resource
 * @param  integer $collection
 * @param  string $comment
 * @param  integer $rating
 * @return boolean
 */
function save_collection_resource_comment($resource, $collection, $comment, $rating)
{
    # get data before update so that changes can be logged.
    $data = ps_query(
        "select comment,rating from collection_resource where resource= ? and collection= ?",
        [
        'i', $resource,
        'i', $collection
        ]
    );
    $params = [];
    if ($rating  != "") {
        $sql = '?';
        $params = ['i', $rating];
    } else {
        $sql = 'null';
    }
    ps_query(
        "update collection_resource set rating= {$sql},comment= ?,use_as_theme_thumbnail= ? where resource= ? and collection= ?",
        array_merge(
            $params,
            [
            's', $comment,
            'i', (getval("use_as_theme_thumbnail", "") == "" ? 0 : 1),
            'i', $resource,
            'i', $collection
            ]
        )
    );

    # log changes
    if ($comment != $data[0]['comment']) {
        collection_log($collection, LOG_CODE_COLLECTION_ADDED_RESOURCE_COMMENT, $resource);
    }
    if ($rating != $data[0]['rating']) {
        collection_log($collection, LOG_CODE_COLLECTION_ADDED_RESOURCE_RATING, $resource);
    }
    return true;
}

/**
 * Relates every resource in $collection to $ref
 *
 * @param  integer $ref
 * @param  integer $collection
 * @return void
 */
function relate_to_collection($ref, $collection)
{
    $colresources = get_collection_resources($collection);
    ps_query("delete from resource_related where resource= ? and related in (" . ps_param_insert(count($colresources)) . ")", array_merge(['i', $ref], ps_param_fill($colresources, 'i')));
    $params = [];
    foreach ($colresources as $colresource) {
        $params = array_merge($params, ['i', $ref, 'i', $colresource]);
    }
    ps_query(
        "INSERT INTO resource_related (resource,related) 
            VALUES " . implode(', ', array_fill(0, count($colresources), '(?, ?)')),
        $params
    );
}

/**
 * Fetch all the comments for a given collection.
 *
 * @param  integer $collection
 * @return array
 */
function get_collection_comments($collection)
{
    return ps_query("select " . columns_in("collection_resource") . " from collection_resource where collection=? and length(comment)>0 order by date_added", array("i",$collection));
}

/**
 * Sends the feedback to the owner of the collection
 *
 * @param  integer $collection  Collection ID
 * @param  string  $comment     Comment text
 * @return array|void
 */
function send_collection_feedback($collection, $comment)
{
    global $applicationname,$lang,$userfullname,$userref,$k,$feedback_resource_select,$regex_email;
    global $userref;

    $cinfo = get_collection($collection);
    if ($cinfo === false) {
        error_alert($lang["error-collectionnotfound"]);
        exit();
    }
    $user = get_user($cinfo["user"]);
    $body = $lang["collectionfeedbackemail"] . "\n\n";

    if (isset($userfullname)) {
        $body .= $lang["user"] . ": " . $userfullname . "\n";
    } else {
        # External user.
        if (!preg_match("/{$regex_email}/", getval("email", ""))) {
            $errors[] = $lang["youremailaddress"] . ": " . $lang["requiredfield"];
            return $errors;
        }
        $body .= $lang["fullname"] . ": " . getval("name", "") . "\n";
        $body .= $lang["email"] . ": " . getval("email", "") . "\n";
    }
    $body .= $lang["message"] . ": " . stripslashes(str_replace("\\r\\n", "\n", trim($comment)));

    $f = get_collection_comments($collection);
    for ($n = 0; $n < count($f); $n++) {
        $body .= "\n\n" . $lang["resourceid"] . ": " . $f[$n]["resource"];
        $body .= "\n" . $lang["comment"] . ": " . trim($f[$n]["comment"]);
        if (is_numeric($f[$n]["rating"])) {
            $body .= "\n" . $lang["rating"] . ": " . substr("**********", 0, $f[$n]["rating"]);
        }
    }

    if ($feedback_resource_select) {
        $body .= "\n\n" . $lang["selectedresources"] . ": ";
        $file_list = "";
        $result = do_search("!collection" . $collection);
        for ($n = 0; $n < count($result); $n++) {
            $ref = $result[$n]["ref"];
            if (getval("select_" . $ref, "") != "") {
                global $filename_field;
                $filename = get_data_by_field($ref, $filename_field);
                $body .= "\n" . $ref . " : " . $filename;

                # Append to a file list that is compatible with Adobe Lightroom
                if ($file_list != "") {
                    $file_list .= ", ";
                }
                $s = explode(".", $filename);
                $file_list .= $s[0];
            }
        }
        # Append Lightroom compatible summary.
        $body .= "\n\n" . $lang["selectedresourceslightroom"] . "\n" . $file_list;
    }
    $cc = getval("email", "");
    get_config_option(['user' => $user['ref'], 'usergroup' => $user['usergroup']], 'email_user_notifications', $send_email);
    // Always send a mail for the feedback whatever the user preference, since the  feedback may be very long so can then refer to the CC'd email
    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
        send_mail($user["email"], $applicationname . ": " . $lang["collectionfeedback"] . " - " . $cinfo["name"], $body, "", "", "", null, "", $cc);
    } else {
        send_mail($user["email"], $applicationname . ": " . $lang["collectionfeedback"] . " - " . $cinfo["name"], $body);
    }

    // Add a system notification message as well
    message_add($user["ref"], $lang["collectionfeedback"] . " - " . $cinfo["name"] . "<br />" . $body, "", (isset($userref)) ? $userref : $user['ref'], MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN, 60 * 60 * 24 * 30);
}

/**
 * Copy a collection contents
 *
 * @param  integer $copied    The collection to copy from
 * @param  integer $current   The collection to copy to
 * @param  boolean $remove_existing   Should existing items be removed?
 * @return void
 */
function copy_collection($copied, $current, $remove_existing = false)
{
    # Get all data from the collection to copy.
    $copied_collection = ps_query("select cr.resource, r.resource_type, cr.sortorder from collection_resource cr join resource r on cr.resource=r.ref where collection=?", array("i",$copied), "");

    if ($remove_existing) {
        #delete all existing data in the current collection
        ps_query("delete from collection_resource where collection=?", array("i",$current));
        collection_log($current, LOG_CODE_COLLECTION_REMOVED_ALL_RESOURCES, 0);
    }

    #put all the copied collection records in
    foreach ($copied_collection as $col_resource) {
        # Use correct function so external sharing is honoured.
        add_resource_to_collection($col_resource['resource'], $current, true, "", $col_resource['resource_type'], null, null, '', $col_resource['sortorder']);
    }
}

/**
 * Returns true if a collection is a research request
 *
 * @param  int  $collection   Collection ID
 *
 * @return boolean
 */
function collection_is_research_request($collection)
{
    return ps_value("SELECT count(*) value FROM research_request WHERE collection=?", array("i", $collection), 0) > 0;
}

/**
 * Generates a HTML link for adding a resource to a collection
 *
 * @param  integer  $resource   ID of resource
 * @param  string   $extracode  Additional code to be run when link is selected
 *                              IMPORTANT: never use untrusted data here!
 * @param  string   $size       Resource size if appropriate
 * @param  string   $class      Class to be applied to link
 * @param  string   $view_title The title of the field, taken from $view_title_field
 *
 * @return string
 */
function add_to_collection_link($resource, $extracode = "", $size = "", $class = "", $view_title = ""): string
{
    $resource = (int) $resource;
    $size = escape($size);
    $class = escape($class);
    $title = escape($GLOBALS['lang']["addtocurrentcollection"] . (($view_title != "") ? " - " . $view_title : ""));

    return "<a class=\"addToCollection {$class}\" href=\"#\" title=\"{$title}\""
        . " onClick=\"AddResourceToCollection(event, {draggable: jQuery('div#ResourceShell{$resource}')},'{$resource}','{$size}'); {$extracode} return false;\""
        . " data-resource-ref=\"{$resource}\""
        . generate_csrf_data_for_api_native_authmode('add_resource_to_collection')
        . ">";
}

/**
 * Render a "remove from collection" link wherever such a function is shown in the UI
 *
 * @param  integer  $resource
 * @param  string   $class
 * @param  string   $onclick    Additional onclick code to call before returning false.
 * @param  bool     $notused    No longer used
 * @param  string   $view_title The title of the field, taken from $view_title_field
 *
 */
function remove_from_collection_link($resource, $class = "", string $onclick = '', $notused = false, $view_title = ""): string
{
    # Generates a HTML link for removing a resource from a collection
    global $lang, $pagename;

    $resource = (int) $resource;
    $class = escape($class);
    $pagename = escape($pagename);
    $title = escape($lang["removefromcurrentcollection"] . (trim($view_title) != "" ? " - " . $view_title : ""));

    return "<a class=\"removeFromCollection {$class}\" href=\"#\" title=\"{$title}\" "
        . "onClick=\"RemoveResourceFromCollection(event,'{$resource}','{$pagename}'); {$onclick} return false;\""
        . "data-resource-ref=\"{$resource}\""
        . generate_csrf_data_for_api_native_authmode('remove_resource_from_collection')
        . ">";
}

/**
 * Generates a HTML link for adding a changing the current collection
 *
 * @param  integer $collection
 * @return string
 */
function change_collection_link($collection)
{
    global $lang;
    return '<a onClick="ChangeCollection(' . $collection . ',\'\');return false;" href="collections.php?collection=' . $collection . '">' . LINK_CARET . $lang["selectcollection"] . '</a>';
}

/**
 * Return all external access given to a collection.
 * Users, emails and dates could be multiple for a given access key, an in this case they are returned comma-separated.
 *
 * @param  integer $collection
 * @return array
 */
function get_collection_external_access($collection)
{
    global $userref;

    # Restrict to only their shares unless they have the elevated 'v' permission
    $condition = "AND upload=0 ";
    $params = array("i",$collection);
    if (!checkperm("v")) {
        $condition .= "AND user=?";
        $params[] = "i";
        $params[] = $userref;
    }
    return ps_query("SELECT access_key,GROUP_CONCAT(DISTINCT user ORDER BY user SEPARATOR ', ') users,GROUP_CONCAT(DISTINCT email ORDER BY email SEPARATOR ', ') emails,MAX(date) maxdate,MAX(lastused) lastused,access,expires,usergroup,password_hash,upload from external_access_keys WHERE collection=? $condition group by access_key order by date", $params);
}

/**
 * Delete a specific collection access key, withdrawing access via that key to the collection in question
 *
 * @param  integer $collection
 * @param  string $access_key
 * @return void
 */
function delete_collection_access_key($collection, $access_key)
{
    # Get details for log
    $users = ps_value("SELECT group_concat(DISTINCT email ORDER BY email SEPARATOR ', ') value FROM external_access_keys WHERE collection=? AND access_key = ? group by access_key ", array("i",$collection,"s",$access_key), "");
    # Deletes the given access key.
    $params = array("s",$access_key);
    $sql = "DELETE FROM external_access_keys WHERE access_key=?";
    if ($collection != 0) {
        $sql .= " AND collection=?";
        $params[] = "i";
        $params[] = $collection;
    }
    ps_query($sql, $params);
    # log changes
    collection_log($collection, LOG_CODE_COLLECTION_STOPPED_RESOURCE_ACCESS, "", $users . " (" . $access_key . ")");
}

/**
 * Add a new row to the collection log (e.g. after an action on that collection)
 *
 * @param  integer $collection
 * @param  string $type Action type
 * @param  integer $resource
 * @param  string $notes
 * @return void
 */
function collection_log($collection, $type, $resource, $notes = "")
{
    global $userref;

    if (!is_numeric($collection)) {
        return false;
    }

    $user = ($userref ?: null);
    $resource = ($resource ?: null);
    $notes = mb_strcut($notes, 0, 255);

    ps_query("INSERT INTO collection_log (date, user, collection, type, resource, notes) VALUES (now(), ?, ?, ?, ?, ?)", array("i",$user,"i",$collection,"s",$type,"i",$resource,"s",$notes));
}

/**
 * Return the log for $collection
 *
 * @param  integer $collection
 * @param  integer $fetchrows   How many rows to fetch
 * @return array
 */
function get_collection_log($collection, $fetchrows = -1)
{
    debug_function_call("get_collection_log", func_get_args());

    global $view_title_field;

    $extra_fields = hook("collection_log_extra_fields");
    if (!$extra_fields) {
        $extra_fields = "";
    }

    $log_query = new PreparedStatementQuery(
        "SELECT c.ref,
                        c.date,
                        u.username,
                        u.fullname,
                        c.type,
                        r.field{$view_title_field} AS title,
                        c.resource,
                        c.notes
                        {$extra_fields}
                   FROM collection_log AS c
        LEFT OUTER JOIN user AS u ON u.ref = c.user
        LEFT OUTER JOIN resource AS r ON r.ref = c.resource
                  WHERE collection = ?
               ORDER BY c.ref DESC",
        array("i",$collection)
    );

    return sql_limit_with_total_count($log_query, $fetchrows, 0, false, null);
}

/**
 * Returns the maximum access (the most permissive) that the current user has to the resources in $collection.
 *
 * @param  integer $collection
 * @return integer
 */
function collection_max_access($collection)
{
    $maxaccess = 2;
    $result = do_search("!collection" . $collection);
    if (!is_array($result)) {
        $result = array();
    }
    for ($n = 0; $n < count($result); $n++) {
        # Load access level
        $access = get_resource_access($result[$n]);
        if ($access < $maxaccess) {
            $maxaccess = $access;
        }
    }
    return $maxaccess;
}

/**
 * Returns the minimum access (the least permissive) that the current user has to the resources in $collection.
 *
 *  Can be passed a collection ID or the results of a collection search, the result will be the most restrictive
 *  access that is found.
 *
 * @param  integer|array $collection    Collection ID as an integer or the result of a search as an array
 *
 * @return integer                      0 - Open, 1 - restricted, 2 - Confidential
 */

function collection_min_access($collection)
{
    global $k, $internal_share_access, $usersearchfilter;
    if (is_array($collection)) {
        $result = $collection;
    } else {
        $result = do_search("!collection{$collection}", '', 'relevance', 0, -1, 'desc', false, '', false, '', '', false, false, true);
    }
    if (!is_array($result) || empty($result)) {
        return 2;
    }

    if (checkperm("v")) {
        // Always has open access
        return 0;
    }

    if (isset($result[0]["resultant_access"])) {
        $minaccess = max(array_column($result, "resultant_access"));
    } else {
        # Reset minaccess and allow get_resource_access to determine the min access for the collection
        $minaccess = 0;
        $usersearchfilter_original = $usersearchfilter;
        # Performance improvement - Don't check search filters again in get_resource_access as $result contains only resources allowed by the search filter.
        $usersearchfilter = '';
        for ($n = 0; $n < count($result); $n++) {
            $access = get_resource_access($result[$n]); // Use the access already calculated if available
            if ($access > $minaccess) {
                $minaccess = $access;
            }
        }
        $usersearchfilter = $usersearchfilter_original;
    }

    if ($k != "") {
        # External access - check how this was shared. If internal share access and share is more open than the user's access return that
        $params[] = "s";
        $params[] = $k;

        // Don't check each resource as an access key only ever has one level of access
        $minextaccess = ps_value("SELECT access value FROM external_access_keys WHERE access_key = ? AND (expires IS NULL OR expires > NOW()) LIMIT 1", $params, -1);
        if ($minextaccess != -1 && (!$internal_share_access || ($internal_share_access && ($minextaccess < $minaccess)))) {
            return $minextaccess;
        }
    }
    return $minaccess;
}

/**
 * Set an existing collection to be public
 *
 * @param  integer  $collection   ID of collection
 *
 * @return boolean
 */
function collection_set_public($collection)
{
    if (is_numeric($collection)) {
        $sql = "UPDATE collection SET `type` = " . COLLECTION_TYPE_PUBLIC . " WHERE ref = ?";
        ps_query($sql, array("i",$collection));
        return true;
    } else {
        return false;
    }
}

/**
 * Remove all resources from a collection
 *
 * @param  integer $ref The collection in question
 * @return void
 */
function remove_all_resources_from_collection($ref)
{

    $collection_type = ps_value("select type value from collection where ref=?", array("i",$ref), "");

    if ($collection_type != COLLECTION_TYPE_SELECTION) {
        $removed_resources = ps_array("SELECT resource AS value FROM collection_resource WHERE collection = ?", array("i",$ref));
        collection_log($ref, LOG_CODE_COLLECTION_REMOVED_ALL_RESOURCES, 0);

        foreach ($removed_resources as $removed_resource_id) {
            collection_log($ref, LOG_CODE_COLLECTION_REMOVED_RESOURCE, $removed_resource_id, ' - Removed all resources from collection ID ' . $ref);
        }
    }

    ps_query("DELETE FROM collection_resource WHERE collection = ?", array("i",$ref));
    ps_query("DELETE FROM external_access_keys WHERE collection = ? AND upload!=1", array("i",$ref));
}

/**
 * Retrieve promoted collections to be displayed on the home page.
 *
 * This function fetches public collections that are marked for publishing to the home page.
 * It returns an array of collection data, including metadata and thumbnail information for the
 * home page image if one is assigned.
 *
 * @return array An array of associative arrays representing each promoted collection, with keys:
 *               - 'ref' (int): The unique identifier for the collection.
 *               - 'type' (int): The type identifier for the collection.
 *               - 'name' (string): The name of the collection.
 *               - 'home_page_publish' (int): Indicates if the collection is published on the home page.
 *               - 'home_page_text' (string): Display text for the collection for the home page.
 *               - 'home_page_image' (int): Resource ID for the image displayed on the home page.
 *               - 'thumb_height' (int): Thumbnail height of the associated image.
 *               - 'thumb_width' (int): Thumbnail width of the associated image.
 *               - 'resource_type' (int): The type of the associated resource.
 *               - 'file_extension' (string): File extension of the associated resource.
 */
function get_home_page_promoted_collections()
{
    global $COLLECTION_PUBLIC_TYPES;
    $public_types = join(", ", $COLLECTION_PUBLIC_TYPES); // Note this is a constant and not user input - does not need to be a a parameter in the next line.
    return ps_query("select collection.ref, collection.`type`,collection.name,collection.home_page_publish,collection.home_page_text,collection.home_page_image,resource.thumb_height,resource.thumb_width, resource.resource_type, resource.file_extension from collection left outer join resource on collection.home_page_image=resource.ref where collection.`type` IN ({$public_types}) and collection.home_page_publish=1 order by collection.ref desc");
}

/**
 * Return an array of distinct archive/workflow states for resources in $collection
 *
 * @param  integer $collection
 * @return array
 */
function is_collection_approved($collection)
{
    if (is_array($collection)) {
        $result = $collection;
    } else {
        $result = do_search("!collection" . $collection, "", "relevance", 0, -1, "desc", false, "", false, "");
    }
    if (!is_array($result) || count($result) == 0) {
        return true;
    }

        $collectionstates = array();
        global $collection_allow_not_approved_share;
    for ($n = 0; $n < count($result); $n++) {
        $archivestatus = $result[$n]["archive"];
        if ($archivestatus < 0 && !$collection_allow_not_approved_share) {
            return false;
        }
        $collectionstates[] = $archivestatus;
    }
        return array_unique($collectionstates);
}

/**
 * Update an existing external access share
 *
 * @param  string $key          External access key
 * @param  int $access          Share access level
 * @param  string $expires      Share expiration date
 * @param  int $group           ID of usergroup that share will emulate permissions for
 * @param  string $sharepwd     Share password
 * @param  array $shareopts     Array of additional share options
 *                              "collection"    - int   collection ID
 *                              "upload"        - bool  Set to true if share is an upload link (no visibility of existing resources)
 *
 * @return boolean
 */
function edit_collection_external_access($key, $access = -1, $expires = "", $group = "", $sharepwd = "", $shareopts = array())
{
    global $usergroup, $scramble_key, $lang;

    if ($key == "") {
        return false;
    }

    if (
        (!isset($shareopts['upload']) || !$shareopts['upload'] )
        && ($group == "" || !checkperm("x"))
    ) {
            // Default to sharing with the permission of the current usergroup if not specified OR no access to alternative group selection.
            $group = $usergroup;
    }
    // Ensure these are escaped as required here
    $setvals = array(
        "access"    => (int)$access,
        "usergroup" => (int)$group,
        );
    if (isset($shareopts['upload']) && $shareopts['upload']) {
        $setvals['upload'] = 1;
    }
    if ($expires != "") {
        $expires = date_format(date_create($expires), 'Y-m-d') . ' 23:59:59';
        $setvals["expires"] = $expires;
    } else {
        $setvals["expires"] = null;
    }
    if ($sharepwd != "(unchanged)") {
        $setvals["password_hash"] = ($sharepwd == "") ? "" : hash('sha256', $key . $sharepwd . $scramble_key);
    }
    $setsql = "";
    $params = [];
    foreach ($setvals as $setkey => $setval) {
        $setsql .= $setsql == "" ? "" : ",";
        $setsql .= $setkey . "= ?";
        $params = array_merge($params, ['s', $setval]);
    }
    $setsql .= ', date = now()';
    $params = array_merge($params, ['s', $key]);
    $condition = '';
    if (isset($shareopts['collection'])) {
        $condition = ' AND collection = ?';
        $params = array_merge($params, ['i', $shareopts['collection']]);
    }

    ps_query(
        "UPDATE external_access_keys
                  SET " . $setsql . "
                WHERE access_key= ?" . $condition,
        $params
    );
    hook("edit_collection_external_access", "", array($key,$access,$expires,$group,$sharepwd, $shareopts));
    if (isset($shareopts['collection'])) {
        $lognotes = array("access_key" => $key);
        foreach ($setvals as $column => $value) {
            if ($column == "password_hash") {
                $lognotes[] = trim($value) != "" ? "password=TRUE" : "";
            } else {
                $lognotes[] = $column . "=" .  $value;
            }
        }
        collection_log($shareopts['collection'], LOG_CODE_COLLECTION_EDIT_UPLOAD_SHARE, null, "(" . implode(",", $lognotes) . ")");
    }

    return true;
}

/**
 * Hide or show a collection from the My Collections area.
 *
 * @param  integer $colref
 * @param  boolean $show    Show or hide?
 * @param  integer $user
 * @return bool
 */
function show_hide_collection($colref, $show = true, $user = "")
{
    global $userref;
    if ($user == "" || $user == $userref) {
        // Working with logged on user, use global variable
        $user = $userref;
        global $hidden_collections;
    } else {
        if (!checkperm_user_edit($user)) {
            return false;
        }
        //Get hidden collections for user
        $hidden_collections = explode(",", ps_value("SELECT hidden_collections FROM user WHERE ref=?", array("i",$user), ""));
    }

    if ($show) {
        debug("Unhiding collection " . $colref . " from user " . $user);
        if (($key = array_search($colref, $hidden_collections)) !== false) {
            unset($hidden_collections[$key]);
        }
    } else {
        debug("Hiding collection " . $colref . " from user " . $user);
        if (array_search($colref, $hidden_collections) === false) {
            $hidden_collections[] = $colref;
        }
    }
    ps_query("UPDATE user SET hidden_collections = ? WHERE ref= ?", ['s', implode(',', $hidden_collections), 'i', $user]);
    return true;
}

/**
 * Get an array of collection IDs for the specified ResourceSpace session and user
 *
 * @param  string  $rs_session  Session id - as obtained by get_rs_session_id()
 * @param  integer $userref     User ID
 * @param  boolean $create      Create new collection?
 *
 * @return array Array of collection IDs for the specified sesssion
 */
function get_session_collections($rs_session, $userref = "", $create = false)
{
    $extrasql = "";
    $params = array("s",$rs_session);
    if ($userref != "") {
        $extrasql = "AND user=?";
        $params[] = "i";
        $params[] = $userref;
    } else {
        $userref = 'NULL';
    }
    $collectionrefs = ps_array("SELECT ref value FROM collection WHERE session_id=? AND type IN ('" . COLLECTION_TYPE_STANDARD . "','" . COLLECTION_TYPE_UPLOAD . "','" . COLLECTION_TYPE_SHARE_UPLOAD . "') " . $extrasql, $params, "");
    if (count($collectionrefs) < 1 && $create) {
        if (upload_share_active()) {
            $collectionrefs[0] = create_collection($userref, "New uploads", 0, 1, 0, false, array("type" => 5)); # Do not translate this string!
        } else {
            $collectionrefs[0] = create_collection($userref, "Default Collection", 0, 1); # Do not translate this string!
        }
    }
    return $collectionrefs;
}

/**
 * Update collection to belong to a new user
 *
 * @param  integer $collection  Collection ID
 * @param  integer $newuser     User ID to assign collection to
 *
 * @return boolean success|failure
 */
function update_collection_user($collection, $newuser)
{
    if (!collection_writeable($collection)) {
        debug("FAILED TO CHANGE COLLECTION USER " . $collection);
        return false;
    }

    ps_query("UPDATE collection SET user=? WHERE ref=?", array("i",$newuser,"i",$collection));
    return true;
}

/**
* Helper function for render_actions(). Compiles actions that are normally valid for collections
*
* @param array   $collection_data  Collection data
* @param boolean $top_actions      Set to true if actions are to be rendered in the search filter bar (above results)
* @param array   $resource_data    Resource data
*
* @return array
*/
function compile_collection_actions(array $collection_data, $top_actions, $resource_data = array())
{
    global $baseurl_short, $lang, $k, $userrequestmode, $zipcommand, $collection_download, $archiver_path,
           $manage_collections_share_link, $allow_share, $enable_collection_copy,
           $manage_collections_remove_link, $userref, $collection_purge, $result,
           $order_by, $sort, $archive, $contact_sheet_link_on_collection_bar,
           $show_searchitemsdiskusage, $emptycollection, $count_result,
           $download_usage, $home_dash, $top_nav_upload_type, $pagename, $offset, $col_order_by, $find, $default_sort,
           $default_collection_sort, $restricted_share, $hidden_collections, $internal_share_access, $search,
           $usercollection, $disable_geocoding, $collection_download_settings, $contact_sheet, $pagename,$upload_then_edit, $enable_related_resources,$list, $enable_themes,
           $system_read_only, $USER_SELECTION_COLLECTION;

    $is_selection_collection = isset($collection_data['ref']) && $collection_data['ref'] == $USER_SELECTION_COLLECTION;

    #This is to properly render the actions drop down in the themes page
    if (isset($collection_data['ref']) && $pagename != "collections") {
        if (!is_array($result)) {
            $result = get_collection_resources_with_data($collection_data['ref']);
        }

        if (('' == $k || $internal_share_access) && is_null($list)) {
            $list = get_user_collections($userref);
        }

        $count_result = count($result);
    }

    if (isset($search) && substr($search, 0, 11) == '!collection' && ($k == '' || $internal_share_access)) {
        # Extract the collection number - this bit of code might be useful as a function
        $search_collection = explode(' ', $search);
        $search_collection = str_replace('!collection', '', $search_collection[0]);
        $search_collection = explode(',', $search_collection); // just get the number
        $search_collection = $search_collection[0];
    }

    // Collection bar actions should always be a special search !collection[ID] (exceptions might arise but most of the
    // time it should be handled using the special search). If top actions then search may include additional refinement inside the collection

    if (isset($collection_data['ref']) && !$top_actions) {
        $search = "!collection{$collection_data['ref']}";
    }

    $urlparams = array(
        "search"      =>  $search,
        "collection"  =>  (isset($collection_data['ref']) ? $collection_data['ref'] : ""),
        "ref"         =>  (isset($collection_data['ref']) ? $collection_data['ref'] : ""),
        "restypes"    =>  isset($_COOKIE['restypes']) ? $_COOKIE['restypes'] : "",
        "order_by"    =>  $order_by,
        "col_order_by" =>  $col_order_by,
        "sort"        =>  $sort,
        "offset"      =>  $offset,
        "find"        =>  $find,
        "k"           =>  $k);

    $options = array();
    $o = 0;

    if (empty($collection_data)) {
        return $options;
    }

    if (empty($order_by)) {
        $order_by = $default_collection_sort;
    }

    // Check minimum access if we have all the data (i.e. not padded for search display), if not then render anyway and access will be checked on target page
    $lastresource = end($resource_data);
    if ($pagename == 'collection_manage') {
        $min_access = collection_min_access($collection_data['ref']);
    } elseif (isset($lastresource["ref"])) {
        $min_access = collection_min_access($resource_data);
    } else {
        $min_access = 0;
    }

    // View all resources
    if (
        !$top_actions // View all resources makes sense only from collection bar context
        && (
            ($k == "" || $internal_share_access)
            && (isset($collection_data["c"]) && $collection_data["c"] > 0)
            || (is_array($result) && count($result) > 0)
        )
    ) {
        $tempurlparams = array(
            'sort' => 'ASC',
            'search' => (isset($collection_data['ref']) ? "!collection{$collection_data['ref']}" : $search),
        );

        $data_attribute['url'] = generateURL($baseurl_short . "pages/search.php", $urlparams, $tempurlparams);
        $options[$o]['value'] = 'view_all_resources_in_collection';
        $options[$o]['label'] = $lang['view_all_resources'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_RESOURCE;
        $options[$o]['order_by'] = 10;
        $o++;
    }

    // Download option
    if ($min_access == 0) {
        if ($download_usage && ( isset($zipcommand) || $GLOBALS['use_zip_extension'] || ( isset($archiver_path) && isset($collection_download_settings) ) ) && $collection_download && $count_result > 0) {
            $download_url = generateURL($baseurl_short . "pages/download_usage.php", $urlparams);
            $data_attribute['url'] = generateURL($baseurl_short . "pages/terms.php", $urlparams, array("url" => $download_url));
            $options[$o]['value'] = 'download_collection';
            $options[$o]['label'] = $lang['action-download'];
            $options[$o]['data_attr'] = $data_attribute;
            $options[$o]['category'] = ACTIONGROUP_RESOURCE;
            $options[$o]['order_by'] = 20;
            $o++;
        } elseif ((isset($zipcommand) || $GLOBALS['use_zip_extension'] || ( isset($archiver_path) && isset($collection_download_settings) ) ) && $collection_download && $count_result > 0) {
            $download_url = generateURL($baseurl_short . "pages/collection_download.php", $urlparams);
            $data_attribute['url'] = generateURL($baseurl_short . "pages/terms.php", $urlparams, array("url" => $download_url));
            $options[$o]['value'] = 'download_collection';
            $options[$o]['label'] = $lang['action-download'];
            $options[$o]['data_attr'] = $data_attribute;
            $options[$o]['category'] = ACTIONGROUP_RESOURCE;
            $options[$o]['order_by'] = 20;
            $o++;
        }
    }

    // Upload to collection
    if (allow_upload_to_collection($collection_data)) {
        if ($upload_then_edit) {
            $data_attribute['url'] = generateURL(
                $baseurl_short . "pages/upload_batch.php", 
                [
                    "collection_add" => $collection_data['ref'], 
                    "entercolname"   => $collection_data['name']
                ]
            );
        } else {
            $data_attribute['url'] = generateURL(
                $baseurl_short . "pages/edit.php", 
                [
                    "uploader"       => $top_nav_upload_type,
                    "ref"            => -$userref,
                    "collection_add" => $collection_data['ref'],
                    "entercolname"   => $collection_data['name']
                ]
            );
        }

        $options[$o]['value'] = 'upload_collection';
        $options[$o]['label'] = $lang['action-upload-to-collection'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_RESOURCE;
        $options[$o]['order_by'] = 30;
        $o++;
    }

     // Remove all resources from collection
    if (!checkperm("b") && 0 < $count_result && ($k == "" || $internal_share_access) && isset($emptycollection) && !$system_read_only && collection_writeable($collection_data['ref'])) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/collections.php", $urlparams, array("emptycollection" => $collection_data['ref'],"removeall" => "true","ajax" => "true","submitted" => "removeall"));
        $options[$o]['value']     = 'empty_collection';
        $options[$o]['label']     = $lang['emptycollection'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category']  = ACTIONGROUP_RESOURCE;
        $options[$o]['order_by'] = 50;
        $o++;
    }

    if (!collection_is_research_request($collection_data['ref']) || !checkperm('r')) {
        if (
            !$top_actions && checkperm('s')
            && $pagename === 'collections'
            && isset($collection_data['request_feedback'])
            && $collection_data['request_feedback']
        ) {
            // Collection feedback
                $data_attribute['url'] = sprintf(
                    '%spages/collection_feedback.php?collection=%s&k=%s',
                    $baseurl_short,
                    urlencode($collection_data['ref']),
                    urlencode($k)
                );
                $options[$o]['value'] = 'collection_feedback';
                $options[$o]['label'] = $lang['sendfeedback'];
                $options[$o]['data_attr'] = $data_attribute;
                $options[$o]['category'] = ACTIONGROUP_RESOURCE;
                $options[$o]['order_by'] = 70;
                $o++;
        }
    } else {
        $research = ps_value('SELECT ref value FROM research_request WHERE collection=?', array("i",$collection_data['ref']), 0);

        // Manage research requests
        $data_attribute['url'] = generateURL($baseurl_short . "pages/team/team_research.php", $urlparams);
        $options[$o]['value'] = 'manage_research_requests';
        $options[$o]['label'] = $lang['manageresearchrequests'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_RESEARCH;
        $options[$o]['order_by'] = 80;
        $o++;

        // Edit research requests
        $data_attribute['url'] = generateURL($baseurl_short . "pages/team/team_research_edit.php", $urlparams, array("ref" => $research));
        $options[$o]['value'] = 'edit_research_requests';
        $options[$o]['label'] = $lang['editresearchrequests'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_RESEARCH;
        $options[$o]['order_by'] = 90;
        $o++;
    }

    // Select collection option - not for collection bar
    if (
        $pagename != 'collections' && ($k == '' || $internal_share_access) && !checkperm('b')
        && ($pagename == 'load_actions' || $pagename == 'themes' || $pagename === 'collection_manage' || $pagename === 'resource_collection_list' || $top_actions)
        && ((isset($search_collection) && isset($usercollection) && $search_collection != $usercollection) || !isset($search_collection))
        && collection_readable($collection_data['ref'])
    ) {
        $options[$o]['value'] = 'select_collection';
        $options[$o]['label'] = $lang['selectcollection'];
        $options[$o]['category'] = ACTIONGROUP_COLLECTION;
        $options[$o]['order_by'] = 100;
        $o++;
    }

    // Copy resources from another collection. Must be in top actions or have more than one collection available if on collections.php
    if (
        !checkperm('b')
        && ($k == '' || $internal_share_access)
        && collection_readable($collection_data['ref'])
        && ($top_actions || (is_array($list) && count($list) > 1))
        && $enable_collection_copy
    ) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/collection_copy_resources.php", array("ref" => $collection_data['ref']));
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['value'] = 'copy_collection';
        $options[$o]['label'] = $lang['copyfromcollection'];
        $options[$o]['category'] = ACTIONGROUP_RESOURCE;
        $options[$o]['order_by'] = 105;
        $o++;
    }

    // Edit Collection
    if ((($userref == $collection_data['user'] && !in_array($collection_data['type'], [COLLECTION_TYPE_REQUEST,COLLECTION_TYPE_SELECTION])) || (checkperm('h')))  && ($k == '' || $internal_share_access) && !$system_read_only) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/collection_edit.php", $urlparams);
        $options[$o]['value'] = 'edit_collection';
        $options[$o]['label'] = $lang['editcollection'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_EDIT;
        $options[$o]['order_by'] = 110;
        $o++;
    }

    if (isset($lastresource["ref"])) {
        // Work this out based on resource data
        $allow_multi_edit = allow_multi_edit($resource_data, $collection_data['ref']);
    } else {
        // Padded result set. It is too expensive to work this out every time for large result sets,
        // Show edit actions for logged in users and access will be checked once action has been selected.
        $allow_multi_edit = $k == "";
    }

    // Edit all
    # If this collection is (fully) editable, then display an edit all link
    if (
        ( $k == "" || $internal_share_access )
        && $count_result > 0
        && $allow_multi_edit
    ) {
            $extra_params = array(
                'editsearchresults' => 'true',
            );

            $data_attribute['url'] = generateURL($baseurl_short . "pages/edit.php", $urlparams, $extra_params);
            $options[$o]['value'] = 'edit_all_in_collection';
            $options[$o]['label'] = $lang['edit_all_resources'];
            $options[$o]['data_attr'] = $data_attribute;
            $options[$o]['category'] = ACTIONGROUP_EDIT;
            $options[$o]['order_by'] = 120;
            $o++;
    }


    // Edit Previews
    if (($k == "" || $internal_share_access) && $count_result > 0 && !(checkperm('F*')) && ($userref == $collection_data['user'] || $collection_data['allow_changes'] == 1 || checkperm('h')) && $allow_multi_edit) {
        $main_pages   = array('search', 'collection_manage', 'collection_public', 'themes');
        $back_to_page = (in_array($pagename, $main_pages) ? escape($pagename) : '');
        $data_attribute['url'] = generateURL($baseurl_short . "pages/collection_edit_previews.php", $urlparams, array("backto" => $back_to_page));
        $options[$o]['value']     = 'edit_previews';
        $options[$o]['label']     = $lang['editcollectionresources'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category']  = ACTIONGROUP_EDIT;
        $options[$o]['order_by']  = 130;
        $o++;
    }

    // Share
    if (allow_collection_share($collection_data)) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/collection_share.php", $urlparams);
        $options[$o]['value'] = 'share_collection';
        $options[$o]['label'] = $lang['share'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_SHARE;
        $options[$o]['order_by']  = 140;
        $o++;
    }

    // Share external link to upload to collection, not permitted if already externally shared for view access
    $eakeys = get_external_shares(array("share_collection" => $collection_data['ref'],"share_type" => 0));
    if (can_share_upload_link($collection_data) && count($eakeys) == 0) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/share_upload.php", array(), array("share_collection" => $collection_data['ref']));
        $options[$o]['value'] = 'share_upload';
        $options[$o]['label'] = $lang['action-share-upload-link'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_SHARE;
        $options[$o]['order_by'] = 30;
        $o++;
    }

    // Home_dash is on, AND NOT Anonymous use, AND (Dash tile user (NOT with a managed dash) || Dash Tile Admin)
    if (!$top_actions && $home_dash && ($k == '' || $internal_share_access) && checkPermission_dashcreate() && !$system_read_only && !in_array($collection_data['type'], [COLLECTION_TYPE_REQUEST,COLLECTION_TYPE_SELECTION])) {
        $is_smart_featured_collection = (isset($collection_data["smart"]) ? (bool) $collection_data["smart"] : false);
        $is_featured_collection_category = (is_featured_collection_category($collection_data) || is_featured_collection_category_by_children($collection_data["ref"]));
        $is_featured_collection = (!$is_featured_collection_category && !$is_smart_featured_collection);

        $tileparams = array(
                'create'            => 'true',
                'tltype'            => 'srch',
                'tlstyle'           => 'thmbs',
                'promoted_resource' => 'true',
                'freetext'          => 'true',
                'all_users'         => '1',
                'title'             => $collection_data["name"],
        );

        if ($is_featured_collection) {
            $tileparams['tltype'] = 'srch';
            $tileparams['link']   = generateURL($baseurl_short . 'pages/search.php', array('search' => '!collection' . $collection_data['ref']));
        } else {
            $tileparams['tltype'] = 'fcthm';
            $tileparams['link']   = generateURL($baseurl_short . 'pages/collections_featured.php', array('parent' => $collection_data['ref']));
        }

        $data_attribute['url'] = generateURL($baseurl_short . "pages/dash_tile.php", $urlparams, $tileparams);

        $options[$o]['value'] = 'save_collection_to_dash';
        $options[$o]['label'] = $lang['createnewdashtile'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_SHARE;
        $options[$o]['order_by']  = 150;
        $o++;
    }

    // Add option to publish as featured collection
    if ($enable_themes && ($k == '' || $internal_share_access) && checkperm("h") && !in_array($collection_data['type'], [COLLECTION_TYPE_REQUEST,COLLECTION_TYPE_SELECTION])) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/collection_set_category.php", $urlparams);
        $options[$o]['value'] = 'collection_set_category';
        $options[$o]['label'] = $lang['collection_set_theme_category'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_SHARE;
        $options[$o]['order_by'] = 160;
        $o++;
    }

    // Request all
    if ($count_result > 0 && checkperm("q")) {
        # Ability to request a whole collection

        # This option should only be rendered if at least one of the resources is not downloadable

        $data_attribute['url'] = generateURL($baseurl_short . "pages/collection_request.php", $urlparams);
        $options[$o]['value'] = 'request_all';
        $options[$o]['label'] = $lang['requestall'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_RESOURCE;
        $options[$o]['order_by']  = 170;
        $o++;
    }

    // Contact Sheet
    if (0 < $count_result && ($k == "" || $internal_share_access) && $contact_sheet && ($contact_sheet_link_on_collection_bar)) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/contactsheet_settings.php", $urlparams);
        $options[$o]['value'] = 'contact_sheet';
        $options[$o]['label'] = $lang['contactsheet'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 190;
        $o++;
    }

    // Remove
    if (
        ($k == "" || $internal_share_access)
        && $manage_collections_remove_link
        && $userref != $collection_data['user']
        && !checkperm('b')
        && collection_readable($collection_data['ref'])
    ) {
        $options[$o]['value'] = 'remove_collection';
        $options[$o]['label'] = $lang['action-remove'];
        $options[$o]['category'] = ACTIONGROUP_COLLECTION;
        $options[$o]['order_by']  = 200;
        $o++;
    }

    // Delete
    if (($k == "" || $internal_share_access) && (($userref == $collection_data['user']) || checkperm('h')) && ($collection_data['cant_delete'] == 0) && $collection_data['type'] != COLLECTION_TYPE_REQUEST) {
        $options[$o]['value'] = 'delete_collection';
        $options[$o]['label'] = $lang['action-deletecollection'];
        $options[$o]['category'] = ACTIONGROUP_EDIT;
        $options[$o]['order_by']  = 210;
        $o++;
    }

    // Collection Purge
    if (($k == "" || $internal_share_access) && $collection_purge && isset($collections) && checkperm('e0') && $collection_data['cant_delete'] == 0) {
        $options[$o]['value'] = 'purge_collection';
        $options[$o]['label'] = $lang['purgeanddelete'];
        $options[$o]['category'] = ACTIONGROUP_EDIT;
        $options[$o]['order_by']  = 220;
        $o++;
    }

    // Collection log
    if (can_view_collection_log($collection_data)) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/collection_log.php", $urlparams);
        $options[$o]['value'] = 'collection_log';
        $options[$o]['label'] = $lang['action-log'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 230;
        $o++;
    }

    // Delete all
    // Note: functionality moved from edit collection page
    if (
        ($k == "" || $internal_share_access)
        && (!$top_actions || $is_selection_collection)
        && ((is_array($result) && count($result) != 0) || $count_result != 0)
        && collection_writeable($collection_data['ref'])
        && $allow_multi_edit
        && !checkperm('D')
    ) {
        $options[$o]['value'] = 'delete_all_in_collection';
        $options[$o]['label'] = $lang['deleteallresourcesfromcollection'];
        $options[$o]['category'] = ACTIONGROUP_EDIT;
        $options[$o]['order_by']  = 240;
        $o++;
    }

    // Show disk usage
    if (($k == "" || $internal_share_access) && (checkperm('a') || checkperm('v')) && !$top_actions && $show_searchitemsdiskusage && 0 < $count_result) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/search_disk_usage.php", $urlparams);
        $options[$o]['value'] = 'search_items_disk_usage';
        $options[$o]['label'] = $lang['collection_disk_usage'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category']  = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 250;
        $o++;
    }

    // CSV export of collection metadata
    if (
        0 < $count_result
        && !$top_actions
        && ($k == '' || $internal_share_access)
        && collection_readable($collection_data['ref'])
    ) {
        $options[$o]['value']            = 'csv_export_results_metadata';
        $options[$o]['label']            = $lang['csvExportResultsMetadata'];
        $data_attribute['url'] = generateURL($baseurl_short . "pages/csv_export_results_metadata.php", $urlparams);
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category']  = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 260;
        $o++;

        if (!checkperm('b') && !$system_read_only) {
            // Hide Collection
            $user_mycollection = ps_value("select ref value from collection where user=? and name='Default Collection' order by ref limit 1", array("i",$userref), "");
            // check that this collection is not hidden. use first in alphabetical order otherwise
            if (in_array($user_mycollection, $hidden_collections)) {
                $sql = "select ref value from collection where user=?";
                $params = array("i",$userref);
                if (count($hidden_collections) > 0) {
                    $sql .= " and ref not in(" . ps_param_insert(count($hidden_collections)) . ")";
                    $params = array_merge($params, ps_param_fill($hidden_collections, "i"));
                }
                $user_mycollection = ps_value($sql . " order by ref limit 1", $params, "");
            }
            $extra_tag_attributes = sprintf(
                '
                    data-mycol="%s"
                ',
                urlencode($user_mycollection)
            );

            if ($pagename != "load_actions") {
                $options[$o]['value'] = 'hide_collection';
                $options[$o]['label'] = $lang['hide_collection'];
                $options[$o]['extra_tag_attributes'] = $extra_tag_attributes;
                $options[$o]['category']  = ACTIONGROUP_ADVANCED;
                $options[$o]['order_by']  = 270;
                $o++;
            }
        }
    }


    // Relate / Unrelate all resources
    if ($enable_related_resources && $allow_multi_edit && 0 < $count_result) {
        $options[$o]['value'] = 'relate_all';
        $options[$o]['label'] = $lang['relateallresources'];
        $options[$o]['category']  = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 280;
        $o++;

        $options[$o]['value'] = 'unrelate_all';
        $options[$o]['label'] = $lang['unrelateallresources'];
        $options[$o]['category']  = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 290;
        $o++;
    }

    // Annotations PDF
    if ($count_result > 0 && canSeeAnnotationsFields() !== []) {
        $options[$o]['value'] = 'annotations_pdf';
        $options[$o]['label'] = $lang['annotate_pdf_sheet_tool'];
        $options[$o]['data_attr'] = [
            'url' => generateURL("{$baseurl_short}pages/annotate_pdf_config.php", $urlparams)
        ];
        $options[$o]['category'] = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 300;
        $o++;
    }

    // Add extra collection actions and manipulate existing actions through plugins
    $modified_options = hook('render_actions_add_collection_option', '', array($top_actions,$options,$collection_data, $urlparams));
    if (is_array($modified_options) && !empty($modified_options)) {
        $options = $modified_options;
    }

    return $options;
}

/**
* Make a filename unique by appending a dupe-string.
*
* @param array $base_values
* @param string $filename
* @param string $dupe_string
* @param string $extension
* @param int $dupe_increment
*
* @return string Unique filename
*/
function makeFilenameUnique($base_values, $filename, $dupe_string, $extension, $dupe_increment = null)
{
    // Create filename to check if exist in $base_values
    $check_filename = $filename . ($dupe_increment ? $dupe_string . $dupe_increment : '') . '.' . $extension;

    if (!in_array($check_filename, $base_values)) {
        // Confirmed filename does not exist yet
        return $check_filename;
    }

    // Recursive call this function with incremented value
    // Doing $dupe_increment = null, ++$dupe_increment results in $dupe_increment = 1
    return makeFilenameUnique($base_values, $filename, $dupe_string, $extension, ++$dupe_increment);
}

/**
* Render the new featured collection form
*
* @param int $parent Featured collection parent. Use zero for root featured collection category
*
* @return void
*/
function new_featured_collection_form(int $parent)
{
    global $baseurl_short, $lang;

    if (!checkperm('h') || !can_create_collections()) {
        http_response_code(401);
        exit($lang['error-permissiondenied']);
    }

    $form_action = "{$baseurl_short}pages/collection_manage.php";
    ?>
    <div class="BasicsBox">
        <h1><?php echo escape($lang["createnewcollection"]); ?></h1>
        <form name="new_collection_form" id="new_collection_form" class="modalform"
              method="POST" action="<?php echo $form_action; ?>" onsubmit="return CentralSpacePost(this, true);">
            <?php generateFormToken("new_collection_form"); ?>
            <input type="hidden" name="call_to_action_tile" value="true"></input>
            <input type="hidden" name="parent" value="<?php echo $parent; ?>"></input>
            <div class="Question">
                <label for="newcollection" ><?php echo escape($lang["collectionname"]); ?></label>
                <input type="text" name="name" id="newcollection" maxlength="100" required="true"></input>
                <div class="clearleft"></div>
            </div>
            <div class="QuestionSubmit" >
                <input type="submit" name="create" value="<?php echo escape($lang["create"]); ?>"></input>
                <div class="clearleft"></div>
            </div>
        </form>
    </div>
    <?php
}

/**
* Get a themes array
*
* @param int $levels    Number of levels to parse from request
*
* @return array         Array containing names of themes matching the syntax used in the collection table i.e. theme, theme2, theme3
*/
function GetThemesFromRequest($levels)
{
    $themes = array();
    for ($n = 0; $n <= $levels; $n++) {
        $themeindex = ($n == 0 ? "" : $n);
        $themename = getval("theme$themeindex", "");
        if ($themename != "") {
            $themes[] = $themename;
        }
        // Legacy inconsistency when naming themes params. Sometimes the root theme was also named theme1. We check if theme
        // is found, but if not, we just go to theme1 rather than break.
        elseif (!($themeindex == 0 && $themename == "")) {
            break;
        }
    }
    return $themes;
}

/**
 * @param array     $dl_data            Array of collection download data from process_collection_download()
 *                                      (passed by reference so can be added to)
 * @param string    $filename           Filename (passed by reference)
 * @param int       $ref                Resource ID
 * @param string    $pextension         File extension
 * @param string    $p                  Path to download file = passed by reference as may be replaced with a copy file
 * @param bool      $copy               Copy the file from filestore rather than renaming?
 * 
 */
function collection_download_use_original_filenames_when_downloading (
    array &$dl_data,
    string &$filename,
    int $ref,
    string $pextension,
    string &$p,
    bool $copy
): void {
    if (trim($filename) === '') {
        return;
    }

    # Only perform the copy if an original filename is set.

    # now you've got original filename, but it may have an extension in a different letter case.
    # The system needs to replace the extension to change it to jpg if necessary, but if the original file
    # is being downloaded, and it originally used a different case, then it should not come from the file_extension,
    # but rather from the original filename itself.

    # do an extra check to see if the original filename might have uppercase extension that can be preserved.
    # also, set extension to "" if the original filename didn't have an extension (exiftool identification of filetypes)
    $pathparts = pathinfo($filename);
    if (
        isset($pathparts['extension'])
        && strtolower($pathparts['extension']) == $pextension
    ) {
        $pextension = $pathparts['extension'];
    }

    $fs = explode("/", $filename);
    $filename = $fs[count($fs) - 1];
    set_unique_filename($filename, $dl_data['filenames']);

    # Copy to tmp (if exiftool failed) or rename this file
    # this is for extra efficiency to reduce copying and disk usage

    if (!($dl_data['collection_download_tar'] || $GLOBALS['use_zip_extension'])) {
        // The copy or rename to the filename is not necessary using the zip extension since the archived filename can be specified.
        $newpath = get_temp_dir(false, $dl_data['id']) . '/' . $filename;
        if (!$copy && $GLOBALS['exiftool_write_option']) {
            rename($p, $newpath);
        } else {
            copy($p, $newpath);
        }

        # Add the temporary file to the post-archiving deletion list.
         $dl_data['deletion_array'][] = $newpath;

        # Set p so now we are working with this new file
        $p = $newpath;
    }
}

/**
 * Provide resource data/collection_resource data to add to text file during a collection download.
 *
 * @param  array    $dl_data            Array of collection download data passed from process_collection_download() 
 * @param  integer  $ref                Resource ID
 * @param  string   $filename           
 * @param  bool     $subbed_original    Has original file been substituted for unavailable size?
 * 
 * @return string   Text to append to file
 */
function collection_download_process_text_file(array $dl_data, int $ref, string $filename, bool $subbed_original): string
{
    $sizetext = $dl_data['sizetext'];
    if ($subbed_original) {
        $sizetext .= ' (' . $GLOBALS['lang']['substituted_original'] . ')';
    }
    $text = "";
    if ($dl_data['includetext'] ?? false) {
        if ((string) $dl_data['k'] === '') {
            $fields = get_resource_field_data($ref);
        } else {
            // External shares should take into account fields that are not meant to show in that case
            $fields = get_resource_field_data($ref, false, true, null, true);
        }
        $commentdata = get_collection_resource_comment($ref, $dl_data['collection']);
        $fields_count = count($fields);
        if ($fields_count > 0) {
            $hook_replace_text = hook('replacecollectiontext', '', array($text, $sizetext, $filename, $ref, $fields, $fields_count, $commentdata));
            if (!$hook_replace_text) {
                $text .= ($sizetext == '' ? '' : $sizetext) . ' ' . $filename . "\r\n-----------------------------------------------------------------\r\n";
                $text .= $GLOBALS["lang"]['resourceid'] . ': ' . $ref . "\r\n";

                for ($i = 0; $i < $fields_count; $i++) {
                    $value = $fields[$i]["value"];
                    $title = str_replace('Keywords - ', '', $fields[$i]["title"]);
                    if ((trim((string) $value) != "") && (trim((string) $value) != ',')) {
                        $text .= wordwrap('* ' . $title . ': ' . i18n_get_translated($value) . "\r\n", 65);
                    }
                }
                if (trim((string)$commentdata['comment']) != '') {
                    $text .= wordwrap($GLOBALS["lang"]['comment'] . ': ' . $commentdata['comment'] . "\r\n", 65);
                }
                if (trim((string)$commentdata['rating']) != '') {
                    $text .= wordwrap($GLOBALS["lang"]['rating'] . ': ' . $commentdata['rating'] . "\r\n", 65);
                }
                $text .= "-----------------------------------------------------------------\r\n\r\n";
            } else {
                $text = $hook_replace_text;
            }
        }
    }
    return $text;
}

/**
 * Update the resource log to show the download during a collection download.
 *
 * @param  array    $dl_data                Array of collection download data passed from process_collection_download() 
 * @param  string   $tmpfile                Temp download file path
 * @param  integer  $ref                    The resource ID
 * @param  string   $email                  Email address of downloader
 *
 * @return void
 */
function collection_download_log_resource_ready(array $dl_data, $tmpfile, $ref, string $email = "")
{
    // Build an array of paths so we can clean up any exiftool-modified files.
    if ($tmpfile !== false && file_exists($tmpfile)) {
         $dl_data['deletion_array'][] = $tmpfile;
    }

    daily_stat("Resource download", $ref);
    $email_add_to_log = ($email != "") ? ' Downloaded by ' . $email : "";
    resource_log($ref, LOG_CODE_DOWNLOADED, 0, (string) $dl_data['usagecomment'] . $email_add_to_log, "", "", (int) $dl_data['usage']);

    // Udate hit count if tracking downloads only
    if ($GLOBALS["resource_hit_count_on_downloads"]) {
        # greatest() is used so the value is taken from the hit_count column in the event that new_hit_count is zero to support installations that did not previously have a new_hit_count column (i.e. upgrade compatability).
        ps_query("UPDATE resource SET new_hit_count=GREATEST(hit_count,new_hit_count)+1 WHERE ref=?", ["i", $ref]);
    }
}

/**
 * Add PDFs for "data only" types to a zip file during creation.
 *
 * @param  array $dl_data                       Array of collection download data
 * @param  object  $zip                         Collection zip file
 * @return void
 */
function collection_download_process_data_only_types(array $dl_data, &$zip)
{
    $result = $dl_data['collection_resources'] ?? [];

    for ($n = 0; $n < count($result); $n++) {
        // Data-only type of resources should be generated and added in the archive
        if (in_array($result[$n]['resource_type'], $GLOBALS["data_only_resource_types"])) {
            $template_path = get_pdf_template_path($result[$n]['resource_type']);
            if ($template_path === false) {
                continue;
            }
            $pdf_filename = 'RS_' . $result[$n]['ref'] . '_data_only.pdf';
            $pdf_file_path = get_temp_dir(false, $dl_data['id']) . '/' . $pdf_filename;

            // Go through fields and decide which ones we add to the template
            $placeholders = array(
                'resource_type_name' => get_resource_type_name($result[$n]['resource_type'])
            );

            $metadata = get_resource_field_data($result[$n]['ref'], false, true, null, '' != $GLOBALS["k"]);

            foreach ($metadata as $metadata_field) {
                $metadata_field_value = trim(tidylist(i18n_get_translated($metadata_field['value'])));

                // Skip if empty
                if ('' == $metadata_field_value) {
                    continue;
                }

                $placeholders['metadatafield-' . $metadata_field['ref'] . ':title'] = $metadata_field['title'];
                $placeholders['metadatafield-' . $metadata_field['ref'] . ':value'] = $metadata_field_value;
            }
            generate_pdf($template_path, $pdf_file_path, $placeholders, true);

            // Go and add file to archive
            if ($dl_data['collection_download_tar']) {
                // Add a link to the pdf
                $usertempdir = get_temp_dir(false, "rs_" . $GLOBALS["userref"] . "_" . $dl_data['id']);
                symlink($pdf_file_path, $usertempdir . DIRECTORY_SEPARATOR  . $pdf_filename);
            } elseif ($GLOBALS["use_zip_extension"]) {
                debug("Adding $pdf_file_path to " . $zip->filename);
                $zip->addFile($pdf_file_path, $pdf_filename);
            } else {
                $dl_data['includefiles'][] = $pdf_file_path . "\r\n";
            }
            $dl_data['deletion_array'][] = $pdf_file_path;
            
            daily_stat('Resource download', $result[$n]['ref']);
            resource_log($result[$n]['ref'], 'd', 0, $GLOBALS['lang']['pdffile'] . " - " . $dl_data['usagecomment'], '', '',(int) $dl_data['usage']);

            if ($GLOBALS["resource_hit_count_on_downloads"]) {
                $resource_ref_escaped = $result[$n]['ref'];
                ps_query("UPDATE resource SET new_hit_count = GREATEST(hit_count, new_hit_count) + 1 WHERE ref = ?", array("i",$resource_ref_escaped));
            }
        }
    }
}

/**
 * @param array     $dl_data                    Array of collection download data from process_collection_download()
 *                                              (passed by reference so can be added to)
 * @param string    $filename                   Resource filename
 * @param mixed     $zip                        Collection zip file, false if using TAR
 * 
 */
function collection_download_process_summary_notes(array &$dl_data, string $filename, mixed &$zip)
{
    global $p;
    $size = $dl_data['size'];
    $text = $dl_data['text'];
    $subbed_original_resources = $dl_data['subbed_original_resources'];
    $used_resources = $dl_data['used_resources'];
    $available_sizes = $dl_data['available_sizes'];

    if (
        !hook('zippedcollectiontextfile', '', array($text))
        && $dl_data['includetext']
    ) {
        $qty_sizes = isset($available_sizes[$size]) ? count($available_sizes[$size]) : 0;
        $qty_total = count($dl_data['collection_resources']);
        $text .= $GLOBALS["lang"]["status-note"] . ": " . $qty_sizes . " " . $GLOBALS["lang"]["of"] . " " . $qty_total . " ";
        switch ($qty_total) {
            case 0:
                $text .= $GLOBALS["lang"]["resource-0"] . " ";
                break;
            case 1:
                $text .= $GLOBALS["lang"]["resource-1"] . " ";
                break;
            default:
                $text .= $GLOBALS["lang"]["resource-2"] . " ";
                break;
        }

        switch ($qty_sizes) {
            case 0:
                $text .= $GLOBALS["lang"]["were_available-0"] . " ";
                break;
            case 1:
                $text .= $GLOBALS["lang"]["were_available-1"] . " ";
                break;
            default:
                $text .= $GLOBALS["lang"]["were_available-2"] . " ";
                break;
        }
        $text .= $GLOBALS["lang"]["forthispackage"] . ".\r\n\r\n";

        foreach ($dl_data['collection_resources'] as $resource) {
            if (in_array($resource['ref'], $subbed_original_resources)) {
                $text .= $GLOBALS["lang"]["didnotinclude"] . ": " . $resource['ref'];
                $text .= " (" . $GLOBALS["lang"]["substituted_original"] . ")";
                $text .= "\r\n";
            } elseif (!in_array($resource['ref'], $used_resources)) {
                $text .= $GLOBALS["lang"]["didnotinclude"] . ": " . $resource['ref'];
                $text .= "\r\n";
            }
        }

        $textfile = get_temp_dir(false, $dl_data['id']) . "/" . (int) $dl_data['collection'] . "-" . safe_file_name(i18n_get_collection_name($dl_data['collectiondata'])) . $dl_data['sizetext'] . ".txt";
        $fh = fopen($textfile, 'w') or die("can't open file");
        fwrite($fh, $text);
        fclose($fh);
        if ($dl_data['collection_download_tar']) {
            $usertempdir = get_temp_dir(false, "rs_" . $GLOBALS["userref"] . "_" . $dl_data['id']);
            debug("collection_download adding symlink: " . $p . " - " . $usertempdir . DIRECTORY_SEPARATOR . $filename);
            
            $GLOBALS["use_error_exception"] = true;
            try {
                symlink(
                    $textfile,
                    $usertempdir 
                        . DIRECTORY_SEPARATOR . $dl_data['collection']
                        . "-" . safe_file_name(i18n_get_collection_name($dl_data['collectiondata']))
                        . $dl_data['sizetext'] . '.txt'
                );
            } catch (Throwable $e) {
                debug("collection_download_process_archive_command: Unable to create symlink {$e->getMessage()}");
                return false;
            }
            unset($GLOBALS["use_error_exception"]);
        } elseif ($GLOBALS['use_zip_extension']) {
            debug("Adding $textfile to " . $zip->filename);
            $zip->addFile($textfile, $dl_data['collection'] . "-" . safe_file_name(i18n_get_collection_name($dl_data['collectiondata'])) . $dl_data['sizetext'] . ".txt");
        } else {
            $dl_data['includefiles'][] = $textfile;
        }
         $dl_data['deletion_array'][] = $textfile;
    }
}

/**
 * Add a CSV containing resource metadata to a downloaded zip file during creation of the zip.
 *
 * @param   array   $dl_data        Array of collection download data from process_collection_download()
 *                                  (passed by reference so can be added to)
 * @param   object  $zip            Collection zip file
 * 
 * @return  void
 */
function collection_download_process_csv_metadata_file(array &$dl_data, &$zip)
{
    // Include the CSV file with the metadata of the resources found in this collection
    $result = $dl_data['collection_resources'];
    $csv_file    = get_temp_dir(false, $dl_data['id']) . '/Col-' . $dl_data['collection'] . '-metadata-export.csv';
    if (isset($result[0]["ref"])) {
        $result = array_column($result, "ref");
    }
    generateResourcesMetadataCSV($result, false, false, $csv_file);
    // Add link to file for use by tar to prevent full paths being included.
    if ($dl_data['collection_download_tar']) {
        $usertempdir = get_temp_dir(false, "rs_" . $GLOBALS["userref"] . "_" . $dl_data['id']);
        debug("collection_download adding symlink: " . $csv_file . " - " . $usertempdir . DIRECTORY_SEPARATOR . 'Col-' . $dl_data['collection'] . '-metadata-export.csv');
        $GLOBALS["use_error_exception"] = true;
        try {
            symlink($csv_file, $usertempdir . DIRECTORY_SEPARATOR . 'Col-' . $dl_data['collection'] . '-metadata-export.csv');
        } catch (Throwable $e) {
            debug("collection_download_process_csv_metadata_file(): Unable to create symlink for CSV {$e->getMessage()}");
            return;
        }
        unset($GLOBALS["use_error_exception"]);
    } elseif ($GLOBALS['use_zip_extension']) {
        debug("Adding $csv_file to " . $zip->filename);
        $zip->addFile($csv_file, 'Col-' . $dl_data['collection'] . '-metadata-export.csv');
    } else {
        debug("collection_download_process_csv_metadata_file: ". $csv_file);
        $dl_data['includefiles'][] = $csv_file;
    }
    $dl_data['deletion_array'][] = $csv_file;
}

/**
 * Modifies the filename for downloading as part of the specified collection
 *
 * @param  string &$filename        Filename (passed by reference)
 * @param  integer $collection      Collection ID
 * @param  string $size             Size code e.g scr,pre
 * @param  string $suffix           String suffix to add (before file extension)
 * @param  array $collectiondata    Collection data obtained by get_collection()
 * 
 * @return void
 */
function collection_download_process_collection_download_name(&$filename, $collection, $size, $suffix, array $collectiondata)
{
    global $use_collection_name_in_zip_name;

    $filename = hook('changecollectiondownloadname', null, array($collection, $size, $suffix));
    if (empty($filename)) {
        if ($use_collection_name_in_zip_name) {
            # Use collection name (if configured)
            $filename = $GLOBALS["lang"]["collectionidprefix"] . $collection . "-"
                    . safe_file_name(i18n_get_collection_name($collectiondata)) . "-" . $size
                    . $suffix;
        } else {
            # Do not include the collection name in the filename (default)
            $filename = $GLOBALS["lang"]["collectionidprefix"] . $collection . "-" . $size . $suffix;
        }
    }
}

/**
 * Executes the archiver command when downloading a collection.
 *
 * @param   array   $dl_data        Array of collection download data from process_collection_download()
 *                                  (passed by reference so can be added to)
 * @param   object  $zip            Collection zip file
 * @param   string  $filename       Download filename
 * @param   integer $settings_id    The index of the selected $collection_download_settings element as defined in config.php
 *
 * @return   bool  Will return true if there is no further work to be done as will be the case for a tar file.
 *                 False when further processing needed e.g. when producing a zip file.
 */
function collection_download_process_archive_command(array &$dl_data, &$zip, $filename, &$zipfile)
{

    $archiver_settings = $GLOBALS['collection_download_settings'][$dl_data['settings_id']] ?? "";

    # Execute the archiver command.
    # If $collection_download is true the $collection_download_settings are used if defined
    if ($GLOBALS['use_zip_extension'] && !$dl_data['collection_download_tar']) {
        set_processing_message($GLOBALS["lang"]["zipping"]);
        $GLOBALS["use_error_exception"] = true;
        try {
            debug("closing " . $zip->filename);
            $zip->close();
        } catch (Throwable $e) {
            debug("collection_download_process_archive_command: Unable to close zip file. Reason {$e->getMessage()}");
        }
        unset($GLOBALS["use_error_exception"]);
        set_processing_message($GLOBALS["lang"]["zipcomplete"]);
    } elseif ($dl_data['collection_download_tar']) {
        $usertempdir = get_temp_dir(false, "rs_" . $GLOBALS["userref"] . "_" . $dl_data['id']);
        header("Content-type: application/tar");
        header("Content-disposition: attachment; filename=" . $filename);
        debug("collection_download tar command: tar -cv -C " . $usertempdir . " . ");
        $cmdtempdir = escapeshellarg($usertempdir);

        debug("Calling tar command for filename " . $filename);
        passthru("find " . $cmdtempdir . ' -printf "%P\n" | tar -cv --no-recursion --dereference -C ' . $cmdtempdir . " -T -");
        return true;
    } elseif ($dl_data['archiver']) {
        set_processing_message($GLOBALS["lang"]["zipping"]);

        // Create a list of files to include
        $listfile = get_temp_dir(false, $dl_data['id']) . "/zipcmd" . $dl_data['collection'] . "-" . $dl_data['size'] . ".txt";
        //  Remove Windows line endings - fixes an issue with using tar command - somehow the file has got Windows line breaks

        $filepaths = implode(
            ($GLOBALS["config_windows"] ? "\n" : "\r\n"),
            $dl_data['includefiles']
        );
        file_put_contents($listfile, $filepaths);
        $dl_data['deletion_array'][] = $listfile;

        // Set up command line 
        $command = get_utility_path("archiver") . " [ARGUMENTS] %ZIPFILE %LISTFILEARG%LISTFILE";
        $cmdparams = [];
        // Likely be more than one argument e.g. 'a -tzip' so will need to be quoted individually
        $arguments = explode(" ", $archiver_settings["arguments"]);
        $arr_arguments = [];
        for($n = 0; $n < count($arguments); $n++) {
            $argumentstring = "%ARGUMENT{$n}";
            $arr_arguments[] = $argumentstring;
            $cmdparams[$argumentstring] = new CommandPlaceholderArg(
                $arguments[$n],
                "permitted_archiver_arguments"
            );
        }
        $command = str_replace("[ARGUMENTS]", implode(" ", $arr_arguments), $command);

        $cmdparams["%ZIPFILE"] = new CommandPlaceholderArg($zipfile, 'is_valid_rs_path');

        $cmdparams["%LISTFILEARG"] = new CommandPlaceholderArg(
            $GLOBALS["archiver_listfile_argument"],
            "permitted_archiver_arguments"
        );
        $cmdparams["%LISTFILE"] = new CommandPlaceholderArg($listfile, 'is_valid_rs_path');
        
        run_command($command, false, $cmdparams);
        set_processing_message($GLOBALS["lang"]["zipcomplete"]);
    }
    return false;
}

/**
 * Remove temporary files created during download by exiftool for adding metadata.
 *
 * @param  array $deletion_array    An array of file paths
 * @return void
 */
function collection_download_clean_temp_files(array $deletion_array)
{
    // Remove temporary files.
    foreach ($deletion_array as $tmpfile) {
        delete_exif_tmpfile($tmpfile);
    }
}

/**
 * Delete any resources from collection moved out of users archive status permissions by other users
 *
 * @param  integer  $collection   ID of collection
 *
 * @return void
 */
function collection_cleanup_inaccessible_resources($collection)
{
    global $userref;

    $editable_states = array_column(get_editable_states($userref), 'id');
    $count_editable_states = count($editable_states);

    if ($count_editable_states === 0) {
        return;
    }

    ps_query("DELETE a 
                FROM   collection_resource AS a 
                INNER JOIN resource AS b 
                ON a.resource = b.ref 
                WHERE  a.collection = ? 
                AND b.archive NOT IN (" . ps_param_insert($count_editable_states) . ")", array_merge(['i', $collection], ps_param_fill($editable_states, 'i')));
}

/**
* Relate all resources in a collection
*
* @param integer $collection ID of collection
*
* @return boolean
*/
function relate_all_collection($collection, $checkperms = true)
{
    if (!is_int_loose($collection) || ($checkperms && !allow_multi_edit($collection))) {
        return false;
    }

    $rlist = get_collection_resources($collection);
    for ($n = 0; $n < count($rlist); $n++) {
        for ($m = 0; $m < count($rlist); $m++) {
            if (
                $rlist[$n] != $rlist[$m] # Don't relate a resource to itself
                && count(ps_query("SELECT 1 FROM resource_related WHERE resource= ? and related= ? LIMIT 1", ['i', $rlist[$n], 'i', $rlist[$m]])) != 1
            ) {
                    ps_query("insert into resource_related (resource,related) values (?, ?)", ['i', $rlist[$n], 'i', $rlist[$m]]);
            }
        }
    }
    return true;
}

/**
* Un-relate all resources in a collection
*
* @param integer  $collection   ID of collection
*
* @return boolean
*/
function unrelate_all_collection($collection, $checkperms = true)
{
    if (!is_int_loose($collection) || ($checkperms && !allow_multi_edit($collection))) {
        return false;
    }

    ps_query('DELETE FROM resource_related WHERE `resource` IN (SELECT `resource` FROM collection_resource WHERE collection = ?) AND `related` IN (select `resource` FROM collection_resource WHERE collection = ?)', array('i', $collection, 'i', $collection));

    return true;
}

/**
* Update collection type for one collection or batch
*
* @param  integer|array  $cid   Collection ID -or- list of collection IDs
* @param  integer        $type  Collection type. @see include/definitions.php for available options
*
* @return boolean
*/
function update_collection_type($cid, $type, $log = true)
{
    debug_function_call("update_collection_type", func_get_args());

    if (!is_array($cid)) {
        $cid = array($cid);
    }

    $cid = array_filter($cid, "is_numeric");

    if (empty($cid)) {
        return false;
    }

    if (!in_array($type, definitions_get_by_prefix("COLLECTION_TYPE"))) {
        return false;
    }

    if ($log) {
        foreach ($cid as $ref) {
            collection_log($ref, LOG_CODE_EDITED, "", "Update collection type to '{$type}'");
        }
    }

    ps_query("UPDATE collection SET `type` = ? WHERE ref IN (" . ps_param_insert(count($cid)) . ")", array_merge(['i', $type], ps_param_fill($cid, 'i')));

    return true;
}

/**
* Update collection parent for this collection
*
* @param integer @cid    The collection ID
* @param integer @parent The featured collection ID that is the parent of this collection
*
* @return boolean
*/
function update_collection_parent(int $cid, int $parent)
{
    if ($cid <= 0 || $parent <= 0) {
        return false;
    }

    collection_log($cid, LOG_CODE_EDITED, "", "Update collection parent to '{$parent}'");
    ps_query("UPDATE collection SET `parent` = ? WHERE ref = ?", ['i', $parent, 'i', $cid]);

    return true;
}

/**
* Get a users' collection of type SELECTION.
*
* There can only be one collection of this type per user. If more, the first one found will be used instead.
*
* @param integer  $user  User ID
*
* @return null|integer  Returns NULL if none found or the collection ID
*/
function get_user_selection_collection($user)
{
    if (!is_numeric($user)) {
        return null;
    }

    global $username,$anonymous_login, $rs_session, $anonymous_user_session_collection;
    if (($username == $anonymous_login && $anonymous_user_session_collection) || upload_share_active()) {
        // We need to set a collection session_id for the anonymous user. Get session ID to create collection with this set
        $rs_session = get_rs_session_id(true);
        $cache = '';
    } else {
        $rs_session = "";
        $cache = 'user_selection_collection' . $user;
    }

    $params = [
        'i', $user,
        'i', COLLECTION_TYPE_SELECTION,
    ];

    $session_id_sql = '';
    if (isset($rs_session) && $rs_session !== '') {
        $session_id_sql = 'AND session_id = ?';
        $params[] = 'i';
        $params[] = $rs_session;
    }

    return ps_value("SELECT ref AS `value` FROM collection WHERE `user` = ? AND `type` = ? {$session_id_sql} ORDER BY ref ASC", $params, null, $cache);
}

/**
* Delete all collections that are not in use e.g. session collections for the anonymous user. Will not affect collections that are public.
*
* @param integer $userref - ID of user to delete collections for
* @param integer $days - minimum age of collections to delete in days
*
* @return integer - number of collections deleted
*/
function delete_old_collections($userref = 0, $days = 30)
{
    if ($userref == 0 || !is_numeric($userref)) {
        return 0;
    }

    $deletioncount = 0;
    $old_collections = ps_array("SELECT ref value FROM collection WHERE user = ? AND created < DATE_SUB(NOW(), INTERVAL ? DAY) AND `type` = " . COLLECTION_TYPE_STANDARD, array("i",$userref,"i",$days), 0);
    foreach ($old_collections as $old_collection) {
        delete_collection($old_collection);
        $deletioncount++;
    }
    return $deletioncount;
}

/**
* Get all featured collections
*
* @return array
*/
function get_all_featured_collections()
{
    return ps_query(
        "SELECT DISTINCT c.ref,
                      c.`name`,
                      c.`type`,
                      c.parent,
                      c.thumbnail_selection_method,
                      c.bg_img_resource_ref,
                      c.created,
                      count(DISTINCT cr.resource) > 0 AS has_resources,
                      count(DISTINCT cc.ref) > 0 AS has_children
                 FROM collection AS c
            LEFT JOIN collection_resource AS cr ON c.ref = cr.collection
            LEFT JOIN collection AS cc ON c.ref = cc.parent
                WHERE c.`type` = ?
             GROUP BY c.ref",
        array("i",COLLECTION_TYPE_FEATURED),
        "featured_collections"
    );
}

/**
* Get all featured collections by parent node
*
* @param integer $parent  The ref of the parent collection. When a featured collection contains another collection, it is
*                         then considered a featured collection category and won't have any resources associated with it.
* @param array   $ctx     Contextual data (e.g disable access control). This param MUST NOT get exposed over the API
*
* @return array List of featured collections (with data)
*/
function get_featured_collections(int $parent, array $ctx)
{
    if ($parent < 0) {
        return array();
    }
    $access_control = (isset($ctx["access_control"]) && is_bool($ctx["access_control"]) ? $ctx["access_control"] : true);


    $params = array("i",COLLECTION_TYPE_FEATURED);
    if ($parent == 0) {
        // When searching for parent '0' we're looking for a null value on the parent column denoting the top level of the featured collection tree.
        $parentquery = "IS NULL";
    } else {
        // Numeric parent value.
        $parentquery = "=?";
        $params[] = "i";
        $params[] = $parent;
    }

    $allfcs = ps_query("SELECT DISTINCT c.ref,
                      c.`name`,
                      c.`type`,
                      c.parent,
                      c.thumbnail_selection_method,
                      c.bg_img_resource_ref,
                      c.order_by,
                      c.created,
                      c.savedsearch,
                      count(DISTINCT cr.resource) > 0 AS has_resources,
                      count(DISTINCT cc.ref) > 0 AS has_children
                 FROM collection AS c
            LEFT JOIN collection_resource AS cr ON c.ref = cr.collection
            LEFT JOIN collection AS cc ON c.ref = cc.parent
                WHERE c.`type` = ?
                  AND c.parent $parentquery
             GROUP BY c.ref
             ORDER BY c.order_by", $params);

    if (!$access_control) {
        return $allfcs;
    }

    $validcollections = array();
    foreach ($allfcs as $fc) {
        if (featured_collection_check_access_control($fc["ref"])) {
            $validcollections[] = $fc;
        }
    }
    return $validcollections;
}

/**
* Build appropriate SQL (for WHERE clause) to filter out featured collections for the user. The function will use either an
* IN or NOT IN depending which list is smaller to increase performance of the search
*
* @param string $prefix SQL WHERE clause element. Mostly should be either WHERE, AND -or- OR depending on the SQL statement
*                       this is part of.
* @param string $column SQL column on which to apply the filter for

* @param bool $returnstring (temporary) Will return the legacy string version until do_search() and others are migrated to use prepared statements. This can be removed once all functions use prepared statements
*
* @return array|string Returns "" if user should see all featured collections or a SQL filter (e.g AND ref IN("32", "34") ) with the placholders as the first element and the collection IDs as params for the second - for use in e.g. ps_query(), ps_value()
*/
function featured_collections_permissions_filter_sql(string $prefix, string $column, bool $returnstring = false)
{
    global $CACHE_FC_PERMS_FILTER_SQL;
    $CACHE_FC_PERMS_FILTER_SQL = (!is_null($CACHE_FC_PERMS_FILTER_SQL) && is_array($CACHE_FC_PERMS_FILTER_SQL) ? $CACHE_FC_PERMS_FILTER_SQL : array());
    $cache_id = md5("{$prefix}-{$column}");
    if (
        (isset($CACHE_FC_PERMS_FILTER_SQL[$cache_id])
            && is_string($CACHE_FC_PERMS_FILTER_SQL[$cache_id])
            && $returnstring)
        || (isset($CACHE_FC_PERMS_FILTER_SQL[$cache_id])
            && is_array($CACHE_FC_PERMS_FILTER_SQL[$cache_id]))
    ) {
        return $CACHE_FC_PERMS_FILTER_SQL[$cache_id];
    }

    // $prefix & $column are used to generate the right SQL (e.g AND ref IN(list of IDs)). If developer/code, passes empty strings,
    // that's not this functions' responsibility. We could error here but the code will error anyway because of the bad SQL so
    // we might as well fix the problem at its root (ie. where we call this function with bad input arguments).
    $prefix = " " . trim($prefix);
    $column = trim($column);

    $computed_fcs = compute_featured_collections_access_control();

    if ($computed_fcs === true) {
        $return = ""; # No access control needed! User should see all featured collections
    } elseif (is_array($computed_fcs)) {
        if ($returnstring) {
            $fcs_list = "'" . join("', '", $computed_fcs) . "'";
            $return = "{$prefix} {$column} IN ({$fcs_list})";
        } else {
            $return = array("{$prefix} {$column} IN (" . ps_param_insert(count($computed_fcs)) . ")",ps_param_fill($computed_fcs, "i"));
        }
    } else {
        // User is not allowed to see any of the available FCs if($returnstring)
        if ($returnstring) {
            $return = "{$prefix} 1 = 0";
        } else {
            $return = [$prefix . " 1 = 0",[]];
        }
    }

    $CACHE_FC_PERMS_FILTER_SQL[$cache_id] = $return;
    return $return;
}

/**
* Access control function used to determine if a featured collection should be accessed by the user
*
* @param integer $c_ref Collection ref to be tested
*
* @return boolean Returns TRUE if user should have access to the featured collection (no parent category prevents this), FALSE otherwise
*/
function featured_collection_check_access_control(int $c_ref)
{
    if (checkperm("-j" . $c_ref)) {
        return false;
    } elseif (checkperm("j*") || checkperm("j" . $c_ref)) {
        return true;
    } else {
        // Get all parents. Query varies according to MySQL cte support
        $mysql_version = ps_query('SELECT LEFT(VERSION(), 3) AS ver');
        if (version_compare($mysql_version[0]['ver'], '8.0', '>=')) {
            $allparents = ps_query(
                "
                WITH RECURSIVE cte(ref,parent, level) AS
                        (
                        SELECT  ref,
                                parent,
                                1 AS level
                          FROM  collection
                         WHERE  ref= ?
                     UNION ALL
                        SELECT  c.ref,
                                c.parent,
                                level+1 AS LEVEL
                          FROM  collection c
                    INNER JOIN  cte
                            ON  c.ref = cte.parent
                        )
                SELECT ref,
                       parent,
                       level
                  FROM cte
              ORDER BY level DESC;",
                ['i', $c_ref],
                "featured_collections",
                -1,
                true,
                0
            );
        } else {
            $allparents = ps_query(
                "
                    SELECT  C2.ref, C2.parent
                    FROM  (SELECT @r AS p_ref,
                            (SELECT @r := parent FROM collection WHERE ref = p_ref) AS parent,
                            @l := @l + 1 AS lvl
                    FROM  (SELECT @r := ?, @l := 0) vars,
                            collection c
                    WHERE  @r <> 0) C1
                    JOIN  collection C2
                        ON  C1.p_ref = C2.ref
                ORDER BY  C1.lvl DESC",
                ['i', $c_ref],
                "featured_collections",
                -1,
                true,
                0
            );
        }

        foreach ($allparents as $parent) {
            if (checkperm("-j" . $parent["ref"])) {
                // Denied access to parent
                return false;
            } elseif (checkperm("j" . $parent["ref"])) {
                return true;
            }
        }
        return false; // No explicit permission given and user doesn't have f*
    }
}

/**
* Helper comparison function for ordering featured collections. It sorts using the order_by property, then based if the
* collection is a category (using the "has_resource" property), then by name (this takes into account the legacy
* use of '*' as a prefix to move to the start).
*
* @param array $a First featured collection data structure to compare
* @param array $b Second featured collection data structure to compare
*
* @return Return an integer less than, equal to, or greater than zero if the first argument is considered to be
*         respectively less than, equal to, or greater than the second.
*/
function order_featured_collections(array $a, array $b)
{
    global $descthemesorder;

    // Sort using the order_by property
    if ($a['order_by'] != $b['order_by'] && !($a['order_by'] == 0 || $b['order_by'] == 0)) {
        if ($descthemesorder) {
            return $a['order_by'] > $b['order_by'] ? -1 : 1;
        }
        return $a['order_by'] < $b['order_by'] ? -1 : 1;
    }

    // Order by showing categories first
    if ($a['has_resources'] != $b['has_resources']) {
        return $a['has_resources'] < $b['has_resources'] ? -1 : 1;
    }

    // Order by collection name
    if ($descthemesorder) {
        return strnatcasecmp($b['name'], $a['name']);
    }
    return strnatcasecmp($a['name'], $b['name']);
}

/**
* Get featured collection categories
*
* @param integer $parent  The ref of the parent collection.
* @param array   $ctx     Extra context for get_featured_collections(). Mostly used for overriding access control (e.g
*                         on the admin_group_permissions.php where we want to see all available featured collection categories).
*
* @return array
*/
function get_featured_collection_categories(int $parent, array $ctx)
{
    return array_values(array_filter(get_featured_collections($parent, $ctx), "is_featured_collection_category"));
}

/**
* Check if a collection is a featured collection category
*
* @param array $fc A featured collection data structure as returned by {@see get_featured_collections()}
*
* @return boolean
*/
function is_featured_collection_category(array $fc)
{
    if (!isset($fc["type"]) || !isset($fc["has_resources"])) {
        return false;
    }

    return $fc["type"] == COLLECTION_TYPE_FEATURED && $fc["has_resources"] == 0 && is_null($fc["savedsearch"] ?? null);
}

/**
* Check if a collection is a featured collection category by checking if the collection has been used as a parent. This
* function will make a DB query to find this out, it does not use existing structures.
*
* Normally a featured collection is a category if it has no resources. In some circumstances, when it's impossible to
* determine whether it should be or not, relying on children is another approach.
*
* @param integer $c_ref Collection ID
*
* @return boolean
*/
function is_featured_collection_category_by_children(int $c_ref)
{
    $found_ref = ps_value(
        "SELECT DISTINCT c.ref AS `value`
             FROM collection AS c
        LEFT JOIN collection AS cc ON c.ref = cc.parent
            WHERE c.`type` = ?
              AND c.ref = ?
         GROUP BY c.ref
           HAVING count(DISTINCT cc.ref) > 0",
        array("s",COLLECTION_TYPE_FEATURED,"i",$c_ref),
        0
    );

    return $found_ref > 0;
}

/**
* Validate a collection parent value
*
* @param int|array $c  Collection ref -or- collection data as returned by {@see get_collection()}
*
* @return null|integer
*/
function validate_collection_parent($c)
{
    if (!is_array($c) && !is_int($c)) {
        return null;
    }

    $collection = $c;
    if (!is_array($c) && is_int($c)) {
        $collection = get_collection($c);
        if ($collection === false) {
            return null;
        }
    }

    return is_null($collection["parent"]) ? null : (int) $collection["parent"];
}

/**
* Get to the root of the branch starting from the leaf featured collection
*
* @param  integer  $ref  Collection ref which is considered a leaf of the tree
* @param  array    $fcs  List of all featured collections
*
* @return array Branch path structure starting from root to the leaf
*/
function get_featured_collection_category_branch_by_leaf(int $ref, array $fcs)
{
    if (empty($fcs)) {
        $fcs = get_all_featured_collections();
    }

    return compute_node_branch_path($fcs, $ref);
}

/**
* Process POSTed featured collections categories data for a collection
*
* @param integer $depth       The depth from which to start from. Usually zero.
* @param array   $branch_path A full branch path of the collection. {@see get_featured_collection_category_branch_by_leaf()}
*
* @return array Returns changes done regarding the collection featured collection category structure. This information
*               then can be provided to {@see save_collection()} as: $coldata["featured_collections_changes"]
*/
function process_posted_featured_collection_categories(int $depth, array $branch_path)
{
    global $enable_themes, $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS;

    if (!($enable_themes && checkperm("h"))) {
        return array();
    }

    if ($depth < 0) {
        return array();
    }

    debug("process_posted_featured_collection_categories: Processing at \$depth = {$depth}");

    // For public collections, the branch path doesn't exist (why would it?) in which case only root categories are valid
    $current_lvl_parent = (!empty($branch_path) ? (int) $branch_path[$depth]["parent"] : 0);
    debug("process_posted_featured_collection_categories: \$current_lvl_parent: " . gettype($current_lvl_parent) . " = " . json_encode($current_lvl_parent));

    $selected_fc_category = getval("selected_featured_collection_category_{$depth}", null, true);
    debug("process_posted_featured_collection_categories: \$selected_fc_category: " . gettype($selected_fc_category) . " = " . json_encode($selected_fc_category));

    $force_featured_collection_type = (getval("force_featured_collection_type", "") == "true");
    debug("process_posted_featured_collection_categories: \$force_featured_collection_type: " . gettype($force_featured_collection_type) . " = " . json_encode($force_featured_collection_type));

    // Validate the POSTed featured collection category for this depth level
    $valid_categories = array_merge(array(0), array_column(get_featured_collection_categories($current_lvl_parent, array()), "ref"));
    if (
        !is_null($selected_fc_category)
        && isset($branch_path[$depth])
        && !in_array($selected_fc_category, $valid_categories)
    ) {
        return array();
    }

    $fc_category_at_level = (empty($branch_path) ? null : $branch_path[$depth]["ref"]);
    debug("process_posted_featured_collection_categories: \$fc_category_at_level: " . gettype($fc_category_at_level) . " = " . json_encode($fc_category_at_level));

    if ($selected_fc_category != $fc_category_at_level || $force_featured_collection_type) {
        $new_parent = ($selected_fc_category == 0 ? $current_lvl_parent : $selected_fc_category);
        debug("process_posted_featured_collection_categories: \$new_parent: " . gettype($new_parent) . " = " . json_encode($new_parent));

        $fc_update = array("update_parent" => $new_parent);

        if ($force_featured_collection_type) {
            $fc_update["force_featured_collection_type"] = true;
        }

        // When moving a public collection to featured, default to most popular image
        if ($depth == 0 && is_null($fc_category_at_level) && (int) $new_parent > 0) {
            $fc_update["thumbnail_selection_method"] = $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"];
        }

        return $fc_update;
    }

    if (is_null($selected_fc_category)) {
        return array();
    }

    return process_posted_featured_collection_categories(++$depth, $branch_path);
}

/**
* Find existing featured collection ref using its name and parent
*
* @param string $name Featured collection name to search by
* @param null|integer $parent The featured collection parent
*
* @return null|integer
*/
function get_featured_collection_ref_by_name(string $name, $parent)
{
    if (!is_null($parent) && !is_int($parent)) {
        return null;
    }

    $sql = "SELECT ref AS `value` FROM collection WHERE `name` = ? AND `type` = ? AND ";
    $params = array("s",trim($name),"s",COLLECTION_TYPE_FEATURED);

    if (is_null($parent)) {
        $sql .= "parent is null";
    } else {
        $sql .= "parent = ?";
        $params[] = "i";
        $params[] = $parent;
    }
    $ref = ps_value($sql, $params, null, "featured_collections");

    return is_null($ref) ? null : (int) $ref;
}

/**
 * Move a featured collection branch paths' root to the node determined by the global configuration option $featured_collections_root_collection.
 *
 * This temporarily moves the root of the featured collection branch, removing any nodes on the branch from the real root
 * up to the new root.
 *
 * @see $featured_collections_root_collection configuration option
 *
 * @param array $branch_path List of branch path nodes as returned by {@see compute_node_branch_path()}
 *
 * @return array
 */
function move_featured_collection_branch_path_root(array $branch_path)
{
    global $featured_collections_root_collection;

    if ($featured_collections_root_collection > 0) {
        $fc_root_col_position = array_search($featured_collections_root_collection, array_column($branch_path, 'ref'));
        if ($fc_root_col_position !== false) {
            $branch_path = array_slice($branch_path, ++$fc_root_col_position);
        }
    }

    return $branch_path;
}

/**
* Check if user is allowed to share collection
*
* @param array $c Collection data
*
* @return boolean Return TRUE if user is allowed to share the collection, FALSE otherwise
*/
function allow_collection_share(array $c)
{
    global $allow_share, $manage_collections_share_link, $k, $internal_share_access,
    $restricted_share, $system_read_only, $system_read_only, $collection_allow_empty_share;

    if (!isset($GLOBALS["count_result"])) {
        $collection_resources = get_collection_resources($c["ref"]);
        $collection_resources = (is_array($collection_resources) ? count($collection_resources) : 0);
    } else {
        $collection_resources = $GLOBALS["count_result"];
    }
    $internal_share_access = (!is_null($internal_share_access) && is_bool($internal_share_access) ? $internal_share_access : internal_share_access());

    if (!isset($c['type'])) {
        $c = get_collection($c['ref']);
    }

    if (
        $allow_share
        && !$system_read_only
        && $manage_collections_share_link
        && ($collection_resources > 0 || $collection_allow_empty_share)
        && ($k == "" || $internal_share_access)
        && !checkperm("b")
        && (checkperm("v")
            || checkperm("g")
            || collection_min_access($c["ref"]) <= RESOURCE_ACCESS_RESTRICTED
            || $restricted_share)
        && !in_array($c['type'], [COLLECTION_TYPE_REQUEST])
    ) {
        return true;
    }

    return false;
}

/**
* Check if user is allowed to share featured collection. If the featured collection provided is a category, then this
* function will return FALSE if at least one sub featured collection has no share access (this is kept consistent with
* the check for normal collections when checking resources).
*
* @param array $c Collection data. You can add "has_resources" and "sub_fcs" keys if you already have this information
*
* @return boolean Return TRUE if user is allowed to share the featured collection, FALSE otherwise
*/
function allow_featured_collection_share(array $c)
{
    if ($c["type"] != COLLECTION_TYPE_FEATURED) {
        return allow_collection_share($c);
    }

    if (!featured_collection_check_access_control($c["ref"])) {
        return false;
    }

    if (!isset($c["has_resources"])) {
        $collection_resources = get_collection_resources($c["ref"]);
        $c["has_resources"] = (is_array($collection_resources) && !empty($collection_resources) ? 1 : 0);
    }

    // Not a category, can be treated as a simple collection
    if (!is_featured_collection_category($c)) {
        return allow_collection_share($c);
    }

    $sub_fcs = (!isset($c["sub_fcs"]) ? get_featured_collection_categ_sub_fcs($c) : $c["sub_fcs"]);
    return array_reduce($sub_fcs, function ($carry, $item) {
        // Fake a collection data structure. allow_collection_share() only needs the ref
        $c = array("ref" => $item);
        $fc_allow_share = allow_collection_share($c);

        // FALSE if at least one collection has no share access (consistent with the check for normal collections when checking resources)
        return !is_bool($carry) ? $fc_allow_share : $carry && $fc_allow_share;
    }, null);
}

/**
* Filter out featured collections that have a different root path. The function builds internally the path to the root from
* the provided featured collection ref and then filters out any featured collections that have a different root path.
*
* @param array $fcs   List of featured collections refs to filter out
* @param int   $c_ref A root featured collection ref
* @param array $ctx   Contextual data
*
* @return array
*/
function filter_featured_collections_by_root(array $fcs, int $c_ref, array $ctx = array())
{
    if (empty($fcs)) {
        return array();
    }

    global $CACHE_FCS_BY_ROOT;
    $CACHE_FCS_BY_ROOT = (!is_null($CACHE_FCS_BY_ROOT) && is_array($CACHE_FCS_BY_ROOT) ? $CACHE_FCS_BY_ROOT : array());
    $cache_id = $c_ref . md5(json_encode($fcs));
    if (isset($CACHE_FCS_BY_ROOT[$cache_id][$c_ref])) {
        return $CACHE_FCS_BY_ROOT[$cache_id][$c_ref];
    }

    $all_fcs = (isset($ctx["all_fcs"]) && is_array($ctx["all_fcs"]) ? $ctx["all_fcs"] : array());
    $branch_path_fct = function ($carry, $item) {
        return "{$carry}/{$item["ref"]}";
    };

    $category_branch_path = get_featured_collection_category_branch_by_leaf($c_ref, $all_fcs);
    $category_branch_path_str = array_reduce($category_branch_path, $branch_path_fct, "");

    $collections = array_filter($fcs, function (int $ref) use ($branch_path_fct, $category_branch_path_str, $all_fcs) {
        $branch_path = get_featured_collection_category_branch_by_leaf($ref, $all_fcs);
        $branch_path_str = array_reduce($branch_path, $branch_path_fct, "");
        return substr($branch_path_str, 0, strlen($category_branch_path_str)) == $category_branch_path_str;
    });

    $CACHE_FCS_BY_ROOT[$cache_id][$c_ref] = $collections;

    return array_values($collections);
}

/**
* Get all featured collections branches where the specified resources can be found.
*
* @param array $r_refs List of resource IDs
*
* @return array Returns list of featured collections (categories included) that contain the specified resource(s).
*/
function get_featured_collections_by_resources(array $r_refs)
{
    $resources = array_filter($r_refs, "is_numeric");
    if (empty($resources)) {
        return array();
    }

    $featured_type_filter_sql = "";
    $featured_type_filter_sql_params = [];
    $fcf_sql = featured_collections_permissions_filter_sql("AND", "c.ref");
    if (is_array($fcf_sql)) {
        $featured_type_filter_sql = "(c.`type` = ? " . $fcf_sql[0] . ")";
        $featured_type_filter_sql_params = array_merge(["i",COLLECTION_TYPE_FEATURED], $fcf_sql[1]);
    }

    # Add chunking to avoid exceeding MySQL parameter limits
    $fcs = array();
    foreach (array_chunk($resources, 10000) as $resource_chunk) {
        $sql = sprintf(
            "SELECT c.ref, c.`name`, c.`parent`
            FROM collection_resource AS cr
            JOIN collection AS c ON cr.collection = c.ref AND c.`type` = %s
            WHERE cr.resource IN (%s)
                %s # access control filter (ok if empty - it means we don't want permission checks or there's nothing to filter out)",
            COLLECTION_TYPE_FEATURED,
            ps_param_insert(count($resource_chunk)),
            $featured_type_filter_sql
        );

        $fcs_chunk = ps_query($sql, array_merge(ps_param_fill($resource_chunk, 'i'), $featured_type_filter_sql_params));
        $fcs = array_merge($fcs, $fcs_chunk);
    }

    $fcs = array_unique($fcs, SORT_REGULAR);

    $results = array();
    foreach ($fcs as $fc) {
        $results[] = get_featured_collection_category_branch_by_leaf($fc["ref"], array());
    }

    return $results;
}

/**
* Verify if a featured collection can be deleted. To be deleted, it MUST not have any resources or children (if category).
*
* @param integer $ref Collection ID
*
* @return boolean Returns TRUE if the featured collection can be deleted, FALSE otherwise
*/
function can_delete_featured_collection(int $ref)
{
    $sql = "SELECT DISTINCT c.ref AS `value`
             FROM collection AS c
        LEFT JOIN collection AS cc ON c.ref = cc.parent
        LEFT JOIN collection_resource AS cr ON c.ref = cr.collection
            WHERE c.`type` = ?
              AND c.ref = ?
         GROUP BY c.ref
           HAVING count(DISTINCT cr.resource) = 0
              AND count(DISTINCT cc.ref) = 0";

    $params = array("s",COLLECTION_TYPE_FEATURED,"i",$ref);

    return ps_value($sql, $params, 0) > 0;
}

/**
 * Remove all instances of the specified character from start of string
 *
 * @param  string $string   String to update
 * @param  string $char     Character to remove
 * @return string
 */
function strip_prefix_chars($string, $char)
{
    while (strpos($string, $char) === 0) {
        $regmatch = preg_quote($char);
        $string = preg_replace("/" . $regmatch . '/', '', $string, 1);
    }
    return $string;
}

/**
* Check access control if user is allowed to upload to a collection.
*
* @param array $c Collection data structure
*
* @return boolean
*/
function allow_upload_to_collection(array $c)
{
    if (empty($c)) {
        return false;
    }

    if (
        in_array($c["type"], [COLLECTION_TYPE_SELECTION,COLLECTION_TYPE_REQUEST])
        // Featured Collection Categories can't contain resources, only other featured collections (categories or normal)
        || ($c["type"] == COLLECTION_TYPE_FEATURED && is_featured_collection_category_by_children($c["ref"]))
    ) {
        return false;
    }

    global $userref, $k, $internal_share_access;

    $internal_share_access = (!is_null($internal_share_access) && is_bool($internal_share_access) ? $internal_share_access : internal_share_access());

    if (
        ($k == "" || $internal_share_access)
        && ($c["savedsearch"] == "" || $c["savedsearch"] == 0)
        && ($userref == $c["user"] || $c["allow_changes"] == 1 || checkperm("h") || checkperm("a"))
        && (checkperm("c") || checkperm("d"))
    ) {
        return true;
    }

    return false;
}

/**
* Compute the featured collections allowed based on current access control
*
* @return boolean|array Returns FALSE if user should not see any featured collections (usually means misconfiguration) -or-
*                       TRUE if user has access to all featured collections. If some access control is in place, then the
*                       return will be an array with all the allowed featured collections
*/
function compute_featured_collections_access_control()
{
    global $CACHE_FC_ACCESS_CONTROL, $userpermissions;
    if (!is_null($CACHE_FC_ACCESS_CONTROL)) {
        return $CACHE_FC_ACCESS_CONTROL;
    }

    $all_fcs = ps_query("SELECT ref, parent FROM collection WHERE `type` = ?", ['i', COLLECTION_TYPE_FEATURED], "featured_collections");
    $all_fcs_rp = reshape_array_by_value_keys($all_fcs, 'ref', 'parent');
    // Set up arrays to store permitted/blocked featured collections
    $includerefs = array();
    $excluderefs = array();
    if (checkperm("j*")) {
        // Check for -jX permissions.
        foreach ($userpermissions as $userpermission) {
            if (substr($userpermission, 0, 2) == "-j") {
                $fcid = substr($userpermission, 2);
                if (is_int_loose($fcid)) {
                    // Collection access has been explicitly denied
                    $excluderefs[] = $fcid;
                    // Also deny access to child collections.
                    $excluderefs = array_merge($excluderefs, array_keys($all_fcs_rp, $fcid));
                }
            }
        }
        if (count($excluderefs) == 0) {
            return true;
        }
    } else {
        // No access to all, check for j{field} permissions that open up access
        foreach ($userpermissions as $userpermission) {
            if (substr($userpermission, 0, 1) == "j") {
                $fcid = substr($userpermission, 1);
                if (is_int_loose($fcid)) {
                    $includerefs[] = $fcid;
                    // Add children of this collection unless a -j permission has been added below it
                    $children = array_keys($all_fcs_rp, $fcid);
                    $queue = new SplQueue();
                    $queue->setIteratorMode(SplQueue::IT_MODE_DELETE);
                    foreach ($children as $child_fc) {
                        $queue->enqueue($child_fc);
                    }

                    while (!$queue->isEmpty()) {
                        $checkfc = $queue->dequeue();
                        if (!checkperm("-j" . $checkfc)) {
                            $includerefs[] = $checkfc;
                            // Also add children of this collection to queue to check
                            $fcs_sub = array_keys($all_fcs_rp, $checkfc);
                            foreach ($fcs_sub as $fc_sub) {
                                $queue->enqueue($fc_sub);
                            }
                        }
                    }
                }
            }
        }

        if (count($includerefs) == 0) {
            // Misconfiguration - user can only see specific FCs but none have been selected
            return false;
        }
    }

    $return = array();
    foreach ($all_fcs_rp as $fc => $fcp) {
        if ((in_array($fc, $includerefs) || checkperm("j*")) && !in_array($fc, $excluderefs)) {
            $return[] = $fc;
        }
    }

    $CACHE_FC_ACCESS_CONTROL = $return;
    return $return;
}

/**
 * Check if user is allowed to re-order featured collections
 * @return boolean
 */
function can_reorder_featured_collections()
{
    return checkperm('h') && compute_featured_collections_access_control() === true;
}

/**
 * Check if user is authorised to edit a featured collection category
 */
function can_edit_featured_collection_category(): bool
{
    return $GLOBALS['enable_theme_category_edit'] && checkperm('h') || checkperm('t');
}

/**
 * Remove all old anonymous collections
 *
 * @param  int $limit   Maximum number of collections to delete - if run from browser this is kept low to avoid delays
 * @return void
 */
function cleanup_anonymous_collections(int $limit = 100)
{
    global $anonymous_login;

    $sql_limit = "";
    $params = [];
    if ($limit != 0) {
        $sql_limit = 'LIMIT ?';
        $params = ['i', $limit];
    }

    if (!is_array($anonymous_login)) {
        $anonymous_login = array($anonymous_login);
    }
    foreach ($anonymous_login as $anonymous_user) {
        $user = get_user_by_username($anonymous_user);
        if (is_int_loose($user)) {
            ps_query("DELETE FROM collection WHERE user = ? AND created < (curdate() - interval '2' DAY) ORDER BY created ASC " . $sql_limit, array_merge(['i', $user], $params));
        }
    }
}

/**
 * Check if user is permitted to create an external upload link for the given collection
 *
 * @param  array $collection_data   Array of collection data
 * @return boolean
 */
function can_share_upload_link($collection_data)
{
    global $usergroup,$upload_link_usergroups;
    if (!is_array($collection_data) && is_numeric($collection_data)) {
        $collection_data = get_collection($collection_data);
    }
    return allow_upload_to_collection($collection_data) && (checkperm('a') || checkperm("exup"));
}

/**
 * Check if user can edit an existing upload share
 *
 * @param  int $collection          Collection ID of share
 * @param  string $uploadkey        External upload key
 *
 * @return bool
 */
function can_edit_upload_share($collection, $uploadkey)
{
    global $userref;
    if (checkperm('a')) {
        return true;
    }
    $share_details = get_external_shares(array("share_collection" => $collection,"share_type" => 1, "access_key" => $uploadkey));
    $details = isset($share_details[0]) ? $share_details[0] : array();
    return
        (isset($details["user"]) && $details["user"] == $userref)
        || (checkperm("ex") && array_key_exists("expires", $details) && empty($details["expires"]));
}

/**
 * Creates an upload link for a collection that can be shared
 *
 * @param  int      $collection  Collection ID
 * @param  array    $shareoptions - values to set
 *                      'usergroup'     Usergroup id to share as (must be in $upload_link_usergroups array)
 *                      'expires'       Expiration date in 'YYYY-MM-DD' format
 *                      'password'      Optional password for share access
 *                      'emails'        Optional array of email addresses to generate keys for
 *
 * @return string   Share access key
 */
function create_upload_link($collection, $shareoptions)
{
    global $upload_link_usergroups, $lang, $scramble_key, $usergroup, $userref;
    global $baseurl, $applicationname;

    $stdshareopts = array("user","usergroup","expires");

    if (!in_array($shareoptions["usergroup"], $upload_link_usergroups) && $shareoptions["usergroup"] != $usergroup) {
        return $lang["error_invalid_usergroup"];
    }

    if (strtotime($shareoptions["expires"]) < time()) {
        return $lang["error_invalid_date"];
    }
    // Generate as many new keys as required
    $newkeys = array();
    $numkeys = isset($shareoptions["emails"]) ? count($shareoptions["emails"]) : 1;
    for ($n = 0; $n < $numkeys; $n++) {
        $newkeys[$n] = generate_share_key($collection);
    }

    // Create array to store sql insert data
    $setcolumns = array(
        "collection"    => $collection,
        "user"          => $userref,
        "upload"        => '1',
        "date"          => date("Y-m-d H:i", time()),
        );
    foreach ($stdshareopts as $option) {
        if (isset($shareoptions[$option])) {
            $setcolumns[$option] = $shareoptions[$option];
        }
    }

    $newshares = array(); // Create array of new share details to return
    for ($n = 0; $n < $numkeys; $n++) {
        $setcolumns["access_key"] = $newkeys[$n];
        if (isset($shareoptions["password"]) && $shareoptions["password"] != "") {
            // Only set if it has actually been set to a string
            $setcolumns["password_hash"] = hash('sha256', $newkeys[$n] . $shareoptions["password"] . $scramble_key);
        }

        if (isset($shareoptions["emails"][$n])) {
            if (!filter_var($shareoptions["emails"][$n], FILTER_VALIDATE_EMAIL)) {
                $newshares[$n] = "";
                continue;
            }
            $setcolumns["email"] = $shareoptions["emails"][$n];
        }
        $insert_columns = array_keys($setcolumns);
        $insert_values  = array_values($setcolumns);


        $sql = "INSERT INTO external_access_keys
                (" . implode(",", $insert_columns) . ")
                VALUES  (" . ps_param_insert(count($insert_values)) . ")";
        ps_query($sql, ps_param_fill($insert_values, 's'));

        $newshares[$n] = $newkeys[$n];

        if (isset($shareoptions["emails"][$n])) {
            // Send email
            $url = $baseurl . "/?c=" . $collection . "&k=" . $newkeys[$n];
            $coldata = get_collection($collection, true);
            $userdetails = get_user($userref);
            $collection_name = i18n_get_collection_name($coldata);
            $link = "<a href='" . $url . "'>" . $collection_name . "</a>";
            $passwordtext = (isset($shareoptions["password"]) && $shareoptions["password"] != "") ? $lang["upload_share_email_password"] . " : '" . $shareoptions["password"] . "'" : "";
            $templatevars = array();
            $templatevars['link']           = $link;
            $templatevars['message']        = trim($shareoptions["message"]) != "" ? $shareoptions["message"] : "";
            $templatevars['from_name']      = $userdetails["fullname"] == "" ? $userdetails["username"] : $userdetails["fullname"];
            $templatevars['applicationname'] = $applicationname;
            $templatevars['passwordtext']   = $passwordtext;
            $expires = isset($shareoptions["expires"]) ? $shareoptions["expires"] : "";
            if ($expires == "") {
                $templatevars['expires_date'] = $lang["email_link_expires_never"];
                $templatevars['expires_days'] = $lang["email_link_expires_never"];
            } else {
                $day_count = round((strtotime($expires) - strtotime('now')) / (60 * 60 * 24));
                $templatevars['expires_date'] = $lang['email_link_expires_date'] . nicedate($expires);
                $templatevars['expires_days'] = $lang['email_link_expires_days'] . $day_count;
                if ($day_count > 1) {
                    $templatevars['expires_days'] .= " " . $lang['expire_days'] . ".";
                } else {
                    $templatevars['expires_days'] .= " " . $lang['expire_day'] . ".";
                }
            }
            $subject = $lang["upload_share_email_subject"] . $applicationname;

            $body = $templatevars['from_name'] . " " . $lang["upload_share_email_text"] . $applicationname;
            $body .= "<br/><br/>\n" . ($templatevars['message'] != "" ? $templatevars['message'] : "");
            $body .= "<br/><br/>\n" . $templatevars['link'];
            if ($passwordtext != "") {
                $body .= "<br/><br/>\n" . $passwordtext;
            }
            $send_result = send_mail($shareoptions["emails"][$n], $subject, $body, $templatevars['from_name'], "", "upload_share_email_template", $templatevars);
            if ($send_result !== true) {
                return $send_result;
            }
        }
        $lognotes = array();
        foreach ($setcolumns as $column => $value) {
            if ($column == "password_hash") {
                $lognotes[] = trim($value) != "" ? "password=TRUE" : "";
            } else {
                $lognotes[] = $column . "=" .  $value;
            }
        }
        collection_log($collection, LOG_CODE_COLLECTION_SHARED_UPLOAD, null, (isset($shareoptions["emails"][$n]) ? $shareoptions["emails"][$n] : "") . "(" . implode(",", $lognotes) . ")");
    }

    return $newshares;
}

/**
 * Generates an external share key based on provided string
 *
 * @param  string   $string
 * @return string   Generated key
 */
function generate_share_key($string)
{
    return substr(md5($string . "," . time() . rand()), 0, 10);
}

/**
 * Check if an external upload link is being used
 *
 * @return mixed false|int  ID of upload collection, or false if not active
 */
function upload_share_active()
{
    global $upload_share_active;
    if (isset($upload_share_active)) {
        return $upload_share_active;
    } elseif (isset($_COOKIE["upload_share_active"]) && getval("k", "") != "") {
        return (int) $_COOKIE["upload_share_active"];
    }
    return false;
}

/**
 * Set up external upload share
 *
 * @param  string $key          access key
 * @param  array $shareopts     Array of share options
 *                              "collection"    - (int) collection ID
 *                              "user"          - (int) user ID of share creator
 *                              "usergroup"     - (int) usergroup ID used for share
 * @return void
 */
function upload_share_setup(string $key, $shareopts = array())
{
    debug_function_call("upload_share_setup", func_get_args());
    global $baseurl, $pagename, $upload_share_active, $upload_then_edit;
    global $upload_link_workflow_state, $override_status_default,$usergroup;

    $rqdopts = array("collection", "usergroup", "user");
    foreach ($rqdopts as $rqdopt) {
        if (!isset($shareopts[$rqdopt])) {
            return false;
        }
    }
    $collection = (int) $shareopts['collection'];
    $usergroup = (int) $shareopts['usergroup'];

    emulate_user((int) $shareopts['user'], $usergroup);
    $upload_share_active = upload_share_active();
    $upload_then_edit = true;

    if (!$upload_share_active || $upload_share_active != $collection) {
        // Create a new session even if one exists to ensure a new temporary collection is created for this share
        rs_setcookie("rs_session", '', 7, "", "", substr($baseurl, 0, 5) == "https", true);
        rs_setcookie("upload_share_active", $collection, 1, "", "", substr($baseurl, 0, 5) == "https", true);
        $upload_share_active = true;
    }

    // Set default archive state
    if (in_array($upload_link_workflow_state, get_workflow_states())) {
        $override_status_default = $upload_link_workflow_state;
    }

    // Upload link key can only work on these pages
    $validpages = array(
        "upload_batch",
        "edit",
        "category_tree_lazy_load",
        "suggest_keywords",
        "add_keyword",
        "download", // Required to see newly created thumbnails if $hide_real_filepath=true;
        "terms",
        );

    if (!in_array($pagename, $validpages)) {
        $uploadurl = get_upload_url($collection, $key);
        redirect($uploadurl);
        exit();
    }
    return true;
}

/**
 * Notify the creator of an external upload share that resources have been uploaded
 *
 * @param  int $collection      Ref of external shared collection
 * @param  string $k            External upload access key
 * @param  int $tempcollection  Ref of temporay upload collection
 * @return void
 */
function external_upload_notify($collection, $k, $tempcollection)
{
    global $applicationname,$baseurl,$lang;

    $upload_share = get_external_shares(array("share_collection" => $collection,"share_type" => 1, "access_key" => $k));
    if (!isset($upload_share[0]["user"])) {
        debug("external_upload_notify() - unable to find external share details: " . func_get_args());
    }
    $user               = $upload_share[0]["user"];
    $usergroup          = $upload_share[0]["usergroup"];
    $templatevars       = array();
    $url                = $baseurl . "/?c=" . (int)$collection;
    $templatevars['url'] = $url;

    $message = $lang["notify_upload_share_new"] . "\n\n" . $lang["clicklinkviewcollection"] . "\n\n" . $url;
    $notificationmessage = $lang["notify_upload_share_new"];

    // Does the user want an email or notification?
    get_config_option(['user' => $user, 'usergroup' => $usergroup], 'email_user_notifications', $send_email);
    if ($send_email) {
        $notify_email = ps_value("select email value from user where ref=?", array("i",$user), "");
        if ($notify_email != '') {
            send_mail($notify_email, $applicationname . ": " . $lang["notify_upload_share_new_subject"], $message, "", "", "emailnotifyuploadsharenew", $templatevars);
        }
    } else {
        global $userref;
        message_add($user, $notificationmessage, $url, 0);
    }
}

/**
 * Purge all expired shares/**
 * @param  array $filteropts    Array of options to filter shares purged
 *                              "share_group"       - (int) Usergroup ref 'shared as'
 *                              "share_user"        - (int) user ID of share creator
 *                              "share_type"        - (int) 0=view, 1=upload
 *                              "share_collection"  - (int) Collection ID
 * @return string|int
 */
function purge_expired_shares($filteropts)
{
    global $userref;

    $share_group = $filteropts['share_group'] ?? null;
    $share_user = $filteropts['share_user'] ?? null;
    $share_type = $filteropts['share_type'] ?? null;
    $share_collection = $filteropts['share_collection'] ?? null;

    $conditions = array();
    $params = [];
    if ((int)$share_user > 0 && ($share_user == $userref || checkperm_user_edit($share_user))) {
        $conditions[] = "user = ?";
        $params = array_merge($params, ['i', $share_user]);
    } elseif (!checkperm('a') && !checkperm('ex')) {
        $conditions[] = "user = ?";
        $params = array_merge($params, ['i', $userref]);
    }

    if (!is_null($share_group) && (int)$share_group > 0  && checkperm('a')) {
        $conditions[] = "usergroup = ?";
        $params = array_merge($params, ['i', $share_group]);
    }
    if ($share_type == 0) {
        $conditions[] = "(upload=0 OR upload IS NULL)";
    } elseif ($share_type == 1) {
        $conditions[] = "upload=1";
    }
    if ((int)$share_collection > 0) {
        $conditions[] = "collection = ?";
        $params = array_merge($params, ['i', $share_collection]);
    }

    $conditional_sql = " WHERE expires < now()";
    if (count($conditions) > 0) {
        $conditional_sql .= " AND " . implode(" AND ", $conditions);
    }

    $purge_query = "DELETE FROM external_access_keys " . $conditional_sql;
    ps_query($purge_query, $params);
    return sql_affected_rows();
}

/**
 * Check if user has the appropriate access to delete a collection.
 *
 * @param   array     $collection_data   Array of collection details, typically from get_collection()
 * @param   int       $userref           Id of user
 * @param   int       $k                 External access key value
 *
 * @return  boolean   Returns true is the collection can be deleted or false if it cannot.
 */
function can_delete_collection(array $collection_data, $userref, $k = "")
{
    return ($k == ''
            && (($userref == $collection_data['user']) || checkperm('h'))
            && $collection_data['cant_delete'] == 0)
        && $collection_data['type'] != COLLECTION_TYPE_REQUEST;
}

/**
 * Send collection to administrators - used if $send_collection_to_admin is enabled
 *
 * @param  int $collection  Collection ID
 * @return boolean
 */
function send_collection_to_admin(int $collection)
{
    if (!is_int_loose($collection)) {
        return false;
    }

    global $lang, $userref, $applicationname, $baseurl, $admin_resource_access_notifications;

    // Get details about the collection:
    $collectiondata = get_collection($collection);
    $collection_name = $collectiondata['name'];
    $resources_in_collection = count(get_collection_resources($collection));

    // Only do this if it is the user's own collection
    if ($collectiondata['user'] != $userref) {
        return false;
    }

    $collectionsent = false;
    // Create a copy of the collection for admin:
    $admin_copy = create_collection(-1, $lang['send_collection_to_admin_emailedcollectionname']);
    copy_collection($collection, $admin_copy);
    $collection_id = $admin_copy;

    // Get the user (or username) of the contributor:
    $user = get_user($userref);
    if (isset($user) && trim($user['fullname']) != '') {
        $user = $user['fullname'];
    } else {
        $user = $user['username'];
    }

    // Build mail and send it:
    $subject = $applicationname . ': ' . $lang['send_collection_to_admin_emailsubject'] . $user;

    $message = $user . $lang['send_collection_to_admin_usercontributedcollection'] . "\n\n";
    $message .= $baseurl . '/pages/search.php?search=!collection' . $collection_id . "\n\n";
    $message .= $lang['send_collection_to_admin_additionalinformation'] . "\n\n";
    $message .= $lang['send_collection_to_admin_collectionname'] . $collection_name . "\n\n";
    $message .= $lang['send_collection_to_admin_numberofresources'] . $resources_in_collection . "\n\n";

    $notification_message = $lang['send_collection_to_admin_emailsubject'] . " " . $user;
    $notification_url = $baseurl . '/?c=' . $collection_id;
    $admin_notify_emails = array();
    $admin_notify_users = array();
    $notify_users = get_notification_users(array("e-1","e0"));
    foreach ($notify_users as $notify_user) {
        get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'user_pref_resource_notifications', $send_message, $admin_resource_access_notifications);
        if (!$send_message) {
            continue;
        }
        get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'email_user_notifications', $send_email);
        if ($send_email && $notify_user["email"] != "") {
            $admin_notify_emails[] = $notify_user['email'];
        } else {
            $admin_notify_users[] = $notify_user["ref"];
        }
    }
    foreach ($admin_notify_emails as $admin_notify_email) {
        send_mail($admin_notify_email, $subject, $message, '', '');
        $collectionsent = true;
    }
    if (count($admin_notify_users) > 0) {
        debug("sending collection to user IDs: " . implode(",", $admin_notify_users));
        message_add($admin_notify_users, $notification_message, $notification_url, $userref, MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN, MESSAGE_DEFAULT_TTL_SECONDS, SUBMITTED_COLLECTION, $collection_id);
        $collectionsent = true;
    }
    return $collectionsent;
}

/**
 * Get the user's default collection, creating one if necessary
 *
 * @param  bool $setactive  Set the collection as the user's active collection?
 * @return int  collection ID
 */
function get_default_user_collection($setactive = false)
{
    global $userref;
    $usercollection = ps_value("SELECT ref value FROM collection WHERE user=? AND name LIKE 'Default Collection%' ORDER BY created ASC LIMIT 1", array("i",$userref), 0);
    if ($usercollection == 0) {
        # Create a collection for this user
        # The collection name is translated when displayed!
        $usercollection = create_collection($userref, "Default Collection", 0, 1); # Do not translate this string!
    }
    if ($setactive) {
        # set this to be the user's current collection
        ps_query("UPDATE user SET current_collection=? where ref=?", array("i",$usercollection,"i",$userref));
        set_user_collection($userref, $usercollection);
    }
    return $usercollection;
}

/**
 * Update a smart collection with or without the $smart_collections_async option.
 *
 * @param  int   $smartsearch_ref   Id of 'savedsearch'.
 *
 * @return void
 */
function update_smart_collection(int $smartsearch_ref)
{
    if ($smartsearch_ref == 0) {
        return;
    }
    $smartsearch = ps_query("select search, collection, restypes, starsearch, archive, created, result_limit from collection_savedsearch where ref = ?", ['i', $smartsearch_ref]);
    global $smart_collections_async;

    if (isset($smartsearch[0]['search'])) {
        $smartsearch = $smartsearch[0];
        $collection = $smartsearch['collection'];
        $smartsearch_archives = $smartsearch['archive'];
        $search_all_archives = $smartsearch_archives === 'all';

        # Option to limit results;
        $result_limit = $smartsearch["result_limit"];
        if ($result_limit == "" || $result_limit == 0) {
            $result_limit = -1;
        }

        $startTime = microtime(true);
        global $smartsearch_accessoverride;

        $search_all_workflow_states_original =  $GLOBALS['search_all_workflow_states'];
        if ($search_all_archives) {
            # Search saved for all states when $search_all_workflow_states was true so make sure we always apply it for the search.
            $GLOBALS['search_all_workflow_states'] = true;
        }

        $results = do_search($smartsearch['search'], $smartsearch['restypes'], "relevance", $smartsearch_archives, $result_limit, "desc", $smartsearch_accessoverride, $smartsearch['starsearch'], false, false, "", false, true, false, false, false, null, true);

        $GLOBALS['search_all_workflow_states'] = $search_all_workflow_states_original;

        # results is a list of the current search without any restrictions
        # we need to compare against the current collection contents to minimize inserts and deletions
        $current_contents = ps_array("select resource value from collection_resource where collection= ?", ['i', $collection]);

        $results_contents = array();
        $counter = 0;
        if (!empty($results) && is_array($results)) {
            foreach ($results as $results_item) {
                if (isset($results_item['ref'])) {
                    $results_contents[] = $results_item['ref'];
                    $counter++;
                    if ($counter >= $result_limit && $result_limit != -1) {
                        break;
                    }
                }
            }
        }

        $results_contents_add = array_values(array_diff($results_contents, $current_contents));
        $current_contents_remove = array_values(array_diff($current_contents, $results_contents));

        $count_results = count($results_contents_add);
        if ($count_results > 0) {
            # Add any new resources
            debug("smart_collections" . (($smart_collections_async) ? "_async:" : ":") . " Adding $count_results resources to collection...");

            if ($smartsearch_archives !== '') {
                $smartsearch_archives = explode(",", $smartsearch_archives);

                for ($n = 0; $n < $count_results; $n++) {
                    if ($search_all_archives) {
                        add_resource_to_collection($results_contents_add[$n], $collection, true);
                    } else {
                        # Check the resource archive state
                        $archivestatus = ps_value("SELECT archive AS value FROM resource WHERE ref = ?", ["i",$results_contents_add[$n]], "");

                        if (in_array($archivestatus, $smartsearch_archives)) {
                            add_resource_to_collection($results_contents_add[$n], $collection, true);
                        }
                    }
                }
            }
        }

            $count_contents = count($current_contents_remove);
        if ($count_contents > 0) {
            # Remove any resources no longer present.
            debug("smart_collections" . (($smart_collections_async) ? "_async:" : ":") . " Removing $count_contents resources...");
            for ($n = 0; $n < $count_contents; $n++) {
                remove_resource_from_collection($current_contents_remove[$n], $collection, true);
            }
        }
            $endTime = microtime(true);
            $elapsed = $endTime - $startTime;
            debug("smart_collections" . (($smart_collections_async) ? "_async:" : ":") . " $elapsed seconds for " . $smartsearch['search']);
    }
}

/**
 * Check if the terms have been accepted for the given upload
 * Terms only need to be accepted when uploading through an upload share link
 * If uploading through an upload share link then the accepted terms have been stored in $_COOKIE["acceptedterms"]
 *
 * @param  int $collection  Collection ref
 * @param  string $k        Share key
 *
 * @return boolean          True if external upload share and terms have also been accepted
 *                          OR if not an external upload
 *                          False if external upload share and terms have NOT been accepted
 */
function check_upload_terms(int $collection, string $k): bool
{
    $keyinfo = ps_query(
        "SELECT collection,upload
            FROM external_access_keys
        WHERE access_key = ?
            AND (expires IS NULL OR expires > now())",
        array("s", $k)
    );

    $collection = get_collection($collection);

    if (
        !is_array($collection)                                                  // not uploading to collection
        || !in_array($collection["ref"], array_column($keyinfo, "collection"))    // share is not for this collection
        || (bool) $keyinfo[0]["upload"] !== true
    ) {                                 // share type not upload
        return true;
    } else {
        return array_key_exists("acceptedterms", $_COOKIE) && $_COOKIE["acceptedterms"] == 1;
    }
}

/**
 * Determines whether the current user has permission to create collections.
 *
 * This function checks for specific conditions that would prevent the user from creating collections.
 * It returns `false` if any of these conditions are met:
 * - The user has the "b" permission, which restricts collection creation.
 * - The user is anonymous and does not have a session collection.
 *
 * @return bool Returns `true` if the user can create collections; otherwise, `false`.
 */
function can_create_collections()
{
    global $anonymous_user_session_collection;
    return  !( // Return FALSE if any of these conditions are true
        checkperm("b")
         || (is_anonymous_user() && !$anonymous_user_session_collection) // User is an anonymous user
        );
}

/**
 * Determines whether the current user has permission to view the collection log.
 *
 * @param   array   $collection_data   Array of collection data, typically from get_collection()
 * 
 * @return  bool    Returns true if the collection log can be viewed by the current user.
 */
function can_view_collection_log(array $collection_data): bool
{
    global $userref, $k, $internal_share_access;
    return ($k == "" || $internal_share_access) && ($userref == $collection_data['user'] || checkperm('h'));
}

/**
 * Re-order all featured collections at a particular tree depth.
 *
 * @param null|integer parent ID of the featured collections' parent to target
 * @return array Featured collection IDs list, in the new order
 */
function reorder_all_featured_collections_with_parent(?int $parent): array
{
    $sql_where_parent = is_null($parent)
        ? new PreparedStatementQuery('IS NULL')
        : new PreparedStatementQuery('= ?', ['i', $parent]);
    $fcs_at_depth = ps_query(
        "SELECT DISTINCT c.ref,
                  c.`name`,
                  c.`type`,
                  c.parent,
                  c.order_by,
                  count(DISTINCT cr.resource) > 0 AS has_resources
             FROM collection AS c
        LEFT JOIN collection_resource AS cr ON c.ref = cr.collection
            WHERE c.`type` = ?
              AND c.parent {$sql_where_parent->sql}
         GROUP BY c.ref",
        array_merge(['i', COLLECTION_TYPE_FEATURED], $sql_where_parent->parameters)
    );

    if (!$GLOBALS['allow_fc_reorder']) {
        $fcs_at_depth = array_map('set_order_by_to_zero', $fcs_at_depth);
    }
    usort($fcs_at_depth, 'order_featured_collections');
    $new_fcs_order = array_column($fcs_at_depth, 'ref');
    sql_reorder_records('collection', $new_fcs_order);

    return $new_fcs_order;
}

/**
 * Generate a collection download ZIP file and the download filename
 *
 * @param  array $dl_data   Array of collection download options passed from collection_download.php or from the offline job
 *                          This array will be updated and passed to subsidiary functions to keep track of processed file, generate text etc.
 *                          Could be moved to an object later
 *                          [
 *                              "filename" => [the name of download file],
 *                              "collection" => [Collection ID],
 *                              "collection_resources" => [Resources to include in download],
 *                              "collectiondata" => [Collection data - from get_collection()],
 *                              "exiftool_write_option" => [Write exif data?],
 *                              "useoriginal" => [Use original if requested size not available?],
 *                              "size" => [Requested Download size ID],
 *                              "settings_id" => [Index of selected option from $collection_download_settings],
 *                              "deletion_array" => [Array of paths to delete],
 *                              "include_csv_file" => [Include metadata CSV file?],
 *                              "include_alternatives" => [Include alternative files?],
 *                              "includetext" => Include text file?,
 *                              "collection_download_tar" => [Generate a TAR file?],
 *                              "count_data_only_types" => [Count of data only resources],
 *                              "id" => [Optional unique identifier - [used to create a download.php link that is specific to the user],
 *                              "k" => External access key from download request if set
 *                          ];
 * 
 * @return array            Array of data about the created file and the download file nam, or the TAR status i.e. 
 *                          [
 *                              "filename" => [the name of download file],
 *                              "path" => [path to the zip file],
 *                              "completed" => [Set to true if a tar has been sent],
 *                          ];
 */
function process_collection_download(array $dl_data): array
{
    // Set elements that may not have been set e.g. a job created in an earlier version
    foreach (['archiver', 'collection_download_tar', 'include_alternatives', 'k'] as $unset_var) {
        if (!isset($dl_data[$unset_var])) {
            $dl_data[$unset_var] = false;
        }
    }

    $collection                 = (int) ($dl_data['collection'] ?? 0);
    $collectiondata             = $dl_data['collectiondata'] ?? [];
    // Please note re: collection_resources - the current collection resources are not retrieved here as
    // these may have changed since the download was requested. Used to be stored as "result" element
    $collection_resources       = $dl_data['collection_resources'] ?? ($dl_data['result'] ?? []);
    $size                       = (string) ($dl_data['size'] ?? "");
    $useoriginal                = (bool) ($dl_data['useoriginal'] ?? false);
    $id                         = (string) ($dl_data['id'] ?? uniqid("Col" . $collection));
    $includetext                = (bool) ($dl_data['includetext'] ?? false);
    $count_data_only_types      = (int) ($dl_data['count_data_only_types'] ?? 0);
    $settings_id                = (string) ($dl_data['settings_id'] ?? "");
    $include_csv_file           = (bool) ($dl_data['include_csv_file'] ?? false);
    $include_alternatives       = (bool) ($dl_data['include_alternatives'] ?? false);
    $collection_download_tar    = (bool) ($dl_data['collection_download_tar'] ?? false);
    $archiver                   = (bool) ($dl_data["archiver"] ?? false);
    // Set this as global -  required by write_metadata() and hooks
    global $exiftool_write_option, $p, $pextension;
    $saved_exiftool_write_option = $exiftool_write_option;
    $exiftool_write_option = $dl_data['exiftool_write_option'];

    if (empty($collectiondata) && $collection > 0) {
        $collectiondata = get_collection($collection);
    }
    if (
        empty($collectiondata)
        || empty($collection_resources)
    ) {
        debug("Missing collection data, Unable to proceed with collection download");
        return [];
    }

    $zip = false;
    if (!$collection_download_tar) {    
        // Generate a randomised path for zip file
        $extension = $archiver ? $GLOBALS["collection_download_settings"][$settings_id]["extension"] : "zip";
        $zipfile = get_temp_dir(false, 'user_downloads') . DIRECTORY_SEPARATOR . $GLOBALS["userref"] . "_" . md5($GLOBALS["username"] . $id . $GLOBALS["scramble_key"]) . "." . $extension;
        debug('Collection download : $zipfile =' . $zipfile);
        if ($GLOBALS['use_zip_extension']) {
            $zip = new ZipArchive();
            $zip->open($zipfile, ZIPARCHIVE::CREATE);
        }
    }

    $dl_data['includefiles'] = []; // Store array of files to include in download
    $dl_data['deletion_array'] = [];
    $dl_data['filenames'] = []; // Set up an array to store the filenames as they are found (to analyze dupes)
    $dl_data['used_resources'] = [];
    $dl_data['subbed_original_resources'] = [];
    $allsizes = get_all_image_sizes(true);
    $rescount = count($collection_resources);

    if ($includetext) { 
        // Initiate text file
        $dl_data['text'] = i18n_get_collection_name($collectiondata) . "\r\n" .
        $GLOBALS["lang"]["downloaded"] . " " . nicedate(date("Y-m-d H:i:s"), true, true) . "\r\n\r\n" .
        $GLOBALS["lang"]["contents"] . ":\r\n\r\n";
        if ($size == "") {
            $dl_data['sizetext'] = "";
        } else {
            $dl_data['sizetext'] = "-" . $size;
        }
    }

    db_begin_transaction("collection_download"); // Ensure all log updates are committed at once
    for ($n = 0; $n < $rescount; $n++) {
        // Set a flag to indicate whether file should be included
        $skipresource = false; 
        if (!isset($collection_resources[$n]['resource_type'])) {
            // Resource data is not present - e.g. an offline job
            $collection_resources[$n] = get_resource_data($collection_resources[$n]["ref"]);
            $dl_data['collection_resources'][$n] = $collection_resources[$n]; // Update so will be passed to other functions
        }
        resource_type_config_override($collection_resources[$n]['resource_type'], false); # False means execute override for every resource

        $copy = false;
        $ref = $collection_resources[$n]['ref'];
        $access = get_resource_access($collection_resources[$n]);
        $use_watermark = check_use_watermark();
        $subbed_original = false;

        // Do not download resources without proper access level
        if ($access > 1) {
            debug('Collection download : skipping resource ID ' . $ref . ' user ID ' . $GLOBALS["userref"] . ' does not have access to this resource');
            continue;
        }

        // Get all possible sizes for this resource. 
        // If largest available has been requested then include internal or user could end up with no file depite being able to see the preview
        $sizes = array_filter($allsizes, function ($availsize) use ($access, $size) {
            return 
                ($availsize["allow_restricted"] || $access === 0)
                && ((int) $availsize["internal"] === 0 || $size == "largest");
        });

        # Check availability of original file
        $p = get_resource_path($ref, true, "", false, $collection_resources[$n]["file_extension"]);
        if (
            file_exists($p) 
            && (($access == 0) || ($access == 1 && $GLOBALS["restricted_full_download"]))
            && resource_download_allowed($ref, '', $collection_resources[$n]['resource_type'], -1, true)
        ) {
            $dl_data['available_sizes']['original'][] = $ref;
        }

        // Check for the availability of each size and load it to the available_sizes array
        foreach ($sizes as $sizeinfo) {
            if (in_array($collection_resources[$n]['file_extension'], $GLOBALS["ffmpeg_supported_extensions"])) {
                $size_id = $sizeinfo['id'];
                // Video files only have a 'pre' sized derivative so flesh out the sizes array using that.
                $p = get_resource_path($ref, true, 'pre', false, $collection_resources[$n]['file_extension']);
                $size_id = 'pre';
                if (
                    resource_download_allowed($ref, $size_id, $collection_resources[$n]['resource_type'], -1, true)
                    &&
                    (
                        hook('size_is_available', '', array($collection_resources[$n], $p, $size_id))
                        || file_exists($p)
                    )
                ) {
                    $dl_data['available_sizes'][$sizeinfo['id']][] = $ref;
                }
            } elseif (in_array($collection_resources[$n]['file_extension'], array_merge($GLOBALS["ffmpeg_audio_extensions"], ['mp3']))) {
                // Audio files are ported to mp3 and do not have different preview sizes
                $p = get_resource_path($ref, true, '', false, 'mp3');
                if (
                    resource_download_allowed($ref, '', $collection_resources[$n]['resource_type'], -1, true)
                    &&
                    (
                        hook('size_is_available', '', array($collection_resources[$n], $p, ''))
                        || file_exists($p)
                    )
                ) {
                        $dl_data['available_sizes'][$sizeinfo['id']][] = $ref;
                }
            } else {
                $size_id = $sizeinfo['id'];
                $size_extension = get_extension($collection_resources[$n], $size_id);
                $p = get_resource_path($ref, true, $size_id, false, $size_extension);
                if (
                    resource_download_allowed($ref, $size_id, $collection_resources[$n]['resource_type'], -1, true)
                    &&
                    (
                        hook('size_is_available', '', array($collection_resources[$n], $p, $size_id))
                        || file_exists($p)
                    )
                ) {
                    $dl_data['available_sizes'][$size_id][] = $ref;
                }
            }
        }

        // Check which size to use
        if ($size == "largest") {
            foreach ($dl_data['available_sizes'] as $available_size => $resources) {
                if (in_array($ref, $resources)) {
                    $usesize = $available_size;
                    if ($available_size == 'original') {
                        $usesize = "";
                        // Has access to the original so no need to check previews
                        break;
                    }
                }
            }
        } else {
            $usesize = ($size == 'original') ? "" : $size;
        }

        if (in_array($collection_resources[$n]['file_extension'], $GLOBALS["ffmpeg_supported_extensions"]) && $usesize !== '') {
            // Supported video formats will only have a pre sized derivative
            $pextension = $GLOBALS["ffmpeg_preview_extension"];
            $p = get_resource_path($ref, true, 'pre', false, $pextension, -1, 1);
            $usesize = 'pre';
        } elseif (in_array($collection_resources[$n]['file_extension'], array_merge($GLOBALS["ffmpeg_audio_extensions"], ['mp3'])) && $usesize !== '') {
            // Supported audio formats are ported to mp3
            $pextension = 'mp3';
            $p = get_resource_path($ref, true, '', false, 'mp3', -1, 1);
            $usesize = '';
        } else {
            $pextension = get_extension($collection_resources[$n], $usesize);
            $p = get_resource_path($ref, true, $usesize, false, $pextension, -1, 1, $use_watermark);
        }

        $target_exists = file_exists($p);
        $replaced_file = false;

        $new_file = hook('replacedownloadfile', '', array($collection_resources[$n], $usesize, $pextension, $target_exists));
        if (
            $new_file != ''
            && $p != $new_file
        ) {
            $p = $new_file;
            $dl_data['deletion_array'][] = $p;
            $replaced_file = true;
            $target_exists = file_exists($p);
        } elseif (
            !$target_exists
            && $useoriginal
            && resource_download_allowed($ref, '', $collection_resources[$n]['resource_type'], -1, true)
        ) {
            // This size doesn't exist, so we'll try using the original instead
            $p = get_resource_path($ref, true, '', false, $collection_resources[$n]['file_extension'], -1, 1, $use_watermark);
            $pextension = $collection_resources[$n]['file_extension'];
            $subbed_original = true;
            $dl_data['subbed_original_resources'][] = $ref;
            $target_exists = file_exists($p);
        }

        if (!isset($pextension) || trim($pextension) == "") {
            $pextension = parse_filename_extension($p);
        }

        // Move to next resource if file doesn't exist or restricted access and user doesn't have access to the requested size
        if (
            !(
                (
                    ($target_exists && $access == 0)
                    || (
                        $target_exists
                        && $access == 1
                        && (image_size_restricted_access($size) || ($usesize == '' && $GLOBALS["restricted_full_download"]))
                    )
                )
                && resource_download_allowed($ref, $usesize, $collection_resources[$n]['resource_type'], -1, true)
            )
        ) {
            debug('Collection download : Skipping resource ID ' . (int) $ref
                . ' file inaccessible to user - $target_exists = ' . $target_exists
                . ', $access = ' . $access
                . ', image_size_restricted_access(' . $size . ') = ' . image_size_restricted_access($size)
                . ', $usesize = ' . $usesize
                . ', $restricted_full_download = ' . $GLOBALS["restricted_full_download"]
                . ', resource_download_allowed() = ' . resource_download_allowed($ref, $usesize, $collection_resources[$n]['resource_type'], -1, true)
            );
            // Set to skip, although alternative files may still be available
            $skipresource = true;
        }        
        
        $tmpfile = false;
        if (!$skipresource) {
            $dl_data['used_resources'][] = $ref;
            if ($exiftool_write_option && !$collection_download_tar) {
                $tmpfile = write_metadata($p, $ref, $id);
                if ($tmpfile !== false && file_exists($tmpfile)) {
                    // File already in tmp, just rename it
                    $p = $tmpfile; 
                } elseif (!$replaced_file) {
                    // Copy the file from filestore rather than renaming
                    $copy = true; 
                }
            }

            // If using original filenames when downloading, copy the file to new location so the name is included.
            $filename = get_download_filename($ref, $usesize, 0, $pextension);
            collection_download_use_original_filenames_when_downloading(
                $dl_data,
                $filename,
                $ref,
                $pextension,
                $p,
                $copy,
            );

            if (hook("downloadfilenamealt")) {
                $filename = hook("downloadfilenamealt");
            }
            if ($includetext) {
                $addtext = collection_download_process_text_file($dl_data, $ref, $filename, $subbed_original);
                $dl_data['text'] .= $addtext;
            }

            hook('modifydownloadfile', "", [$collection_resources[$n]]);
            if ($collection_download_tar) {
                $usertempdir = get_temp_dir(false, "rs_" . $GLOBALS["userref"] . "_" . $id);
                debug("collection_download adding symlink: " . $p . " - " . $usertempdir . DIRECTORY_SEPARATOR . $filename);
                $GLOBALS["use_error_exception"] = true;
                try {
                    symlink($p, $usertempdir . DIRECTORY_SEPARATOR . $filename);
                } catch (Throwable $e) {
                    debug("process_collection_download(): Unable to create symlink for resource $ref {$e->getMessage()}");
                    return [];
                }
                unset($GLOBALS["use_error_exception"]);
            } elseif ($GLOBALS['use_zip_extension']) {
                debug("Adding $p - ($filename) for ref " . $ref . " to " . $zip->filename);
                set_processing_message((string) ($n+1 . "/" . $rescount . " " . $GLOBALS["lang"]["filesaddedtozip"]));
                $success = $zip->addFile($p, $filename);
                debug('Collection download : Added resource ' . $ref . ' to zip archive = ' . ($success ? 'true' : 'false'));
            } else {
                $dl_data['includefiles'][] = $p;
            }
        }

        if ($include_alternatives) {
            debug("Processing alternative files for resource $ref");
            // Process alternatives
            $alternatives = get_alternative_files($ref);
            foreach ($alternatives as $alternative) {
                $pextension = get_extension($alternative, $usesize);
                debug("Processing alternative file {$alternative['ref']} for resource $ref, extension: $pextension");
                $p = get_resource_path($ref, true, $usesize, false, $pextension, true, 1, $use_watermark, '', $alternative["ref"]);
                $target_exists = file_exists($p);
                if (
                    !$target_exists
                    && ($useoriginal || in_array("format_chooser", $GLOBALS["plugins"]))
                    && resource_download_allowed($ref, '', $collection_resources[$n]['resource_type'], $alternative["ref"], true)
                ) {
                    debug("Using original alternative file for alternative file {$alternative['ref']}");
                    // This size doesn't exist, so we'll try using the original instead
                    // Always use original if using format chooser as the option is not then available and dynamically generating custom sizes is not supported for alternatives
                    $p = get_resource_path($ref, true, '', false, $alternative['file_extension'], -1, 1, $use_watermark, '', $alternative["ref"]);
                    $pextension = $alternative['file_extension'];
                    $target_exists = file_exists($p);
                    $usesize = "";
                }

                debug("Using filepath $p for alternative ref " . $alternative["ref"]);
                if ($target_exists) {
                    $download_filename_format_saved = $GLOBALS["download_filename_format"];
                    if (strpos($GLOBALS["download_filename_format"], "%alternative") === false) {
                        // To be safe, add in the alternative ID if not present in configured download filename format or
                        // it may conflict with the primary resource file
                        $GLOBALS["download_filename_format"] = str_replace(
                            ".%extension",
                            "%alternative.%extension",
                            $GLOBALS["download_filename_format"]
                        );
                    }
                    $filename = get_download_filename($ref, $usesize, $alternative["ref"], $pextension);
                    $GLOBALS["download_filename_format"] = $download_filename_format_saved;

                    collection_download_use_original_filenames_when_downloading(
                        $dl_data,
                        $filename,
                        $ref,
                        $pextension,
                        $p,
                        $copy,
                    );

                    debug("Adding $p ($filename) for alternative ref " . $alternative["ref"]);
                    set_processing_message((string) ($n+1 . "/" . $rescount . " " . $GLOBALS["lang"]["filesaddedtozip"]));
                    if ($collection_download_tar) {
                        debug("collection_download adding symlink: " . $p . " - " . $usertempdir . DIRECTORY_SEPARATOR . $filename);
                        $GLOBALS["use_error_exception"] = true;
                        try {
                            symlink($p, $usertempdir . DIRECTORY_SEPARATOR . $filename);
                        } catch (Throwable $e) {
                            debug("process_collection_download(): Unable to create symlink for ref {$ref},
                                alternative file {$alternative["ref"]}{$e->getMessage()}");
                            continue;
                        }
                        unset($GLOBALS["use_error_exception"]);
                    } elseif($archiver) {
                        $dl_data["includefiles"][] = $p;
                    } else {
                        $success = $zip->addFile($p, $filename);
                        debug('Collection download : Added resource ' . $ref . ' to zip archive = ' . ($success ? 'true' : 'false'));
                    }
                } else {
                    debug("No file found for alternative ref " . $alternative["ref"]);

                }
            }
        }
        collection_download_log_resource_ready($dl_data, $tmpfile, $ref);
    }

    if (0 < $count_data_only_types) {
        collection_download_process_data_only_types($dl_data, $zip);
    }
    collection_download_process_summary_notes($dl_data, $filename, $zip);
    if ($include_csv_file == 'yes') {
        collection_download_process_csv_metadata_file($dl_data, $zip);
    }

    if ($collection_download_tar) {
        $suffix = '.tar';
    } elseif ($archiver) {
        $suffix = '.' . $GLOBALS["collection_download_settings"][$settings_id]['extension'];
    } else {
        $suffix = '.zip';
    }
    $filename = "";
    collection_download_process_collection_download_name($filename, $collection, $size, $suffix, $collectiondata);
    $completed = collection_download_process_archive_command($dl_data, $zip, $filename, $zipfile);
    collection_download_clean_temp_files($dl_data['deletion_array']);

    db_end_transaction("collection_download");

    // Reset global 
    $exiftool_write_option = $saved_exiftool_write_option;

    return [
        "filename"  => $filename,
        "path"      => $zipfile,
        "completed" => $completed,
    ];
}