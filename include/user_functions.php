<?php
# User functions
# Functions to create, edit and generally deal with user accounts

include_once __DIR__ . '/login_functions.php';

/**
* Validate user - check we have a valid user based on SQL criteria e.g. session that is passed in as $user_select_sql
* Will always return false if matches criteria but the user account is not approved or has expired
*
* $user_select_sql example u.session=$variable.
* Joins to usergroup table as g  which can be used in criteria
*
* @param    object  $user_select_sql        PreparedStatementQuery instance - to validate user usually session hash or key
* @param    boolean $getuserdata            default true. Return user data as required by authenticate.php
*
* @return boolean|array
*/
function validate_user($user_select_sql, $getuserdata = true)
{
    if (!is_a($user_select_sql, 'PreparedStatementQuery')) {
        return false;
    }

    $validatesql    = $user_select_sql->sql;
    $validateparams = $user_select_sql->parameters;

    $full_user_select_sql = "
        approved = 1
        AND (
                account_expires IS NULL 
                OR account_expires = '0000-00-00 00:00:00' 
                OR account_expires > now()
            ) "
        . ((strtoupper(trim(substr($validatesql, 0, 4))) == 'AND') ? ' ' : ' AND ')
        . $validatesql;

    if ($getuserdata) {
        return ps_query(
            "   SELECT u.ref,
                       u.username,
                       u.origin,
                       if(find_in_set('permissions',g.inherit_flags) AND pg.permissions IS NOT NULL,pg.permissions,g.permissions) permissions,
                       g.parent,
                       u.usergroup,
                       u.current_collection,
					   (select count(*) from collection where ref=u.current_collection) as current_collection_valid,
                       u.last_active,
                       timestampdiff(second, u.last_active, now()) AS idle_seconds,
                       u.email,
                       u.email_rate_limit_active,
                       u.password,
                       u.fullname,
                       g.search_filter,
                       g.edit_filter,
                       g.ip_restrict ip_restrict_group,
                       g.name groupname,
                       u.ip_restrict ip_restrict_user,
                       u.search_filter_override,
                       u.search_filter_o_id,
                       g.resource_defaults,
                       u.password_last_change,
                       if(find_in_set('config_options',g.inherit_flags) AND pg.config_options IS NOT NULL,pg.config_options,g.config_options) config_options,
                       g.request_mode,
                       g.derestrict_filter,
                       u.hidden_collections,
                       u.accepted_terms,
                       u.session,
                       g.search_filter_id,
                       g.download_limit,
                       g.download_log_days,
                       g.edit_filter_id,
                       g.derestrict_filter_id,
                       u.processing_messages processing_messages
                  FROM user AS u
             LEFT JOIN usergroup AS g on u.usergroup = g.ref
			 LEFT JOIN usergroup AS pg ON g.parent=pg.ref
                 WHERE {$full_user_select_sql}",
            $validateparams
        );
    } else {
        $validuser = ps_value(
            "      SELECT u.ref AS `value`
                     FROM user AS u 
                LEFT JOIN usergroup g ON u.usergroup = g.ref
                    WHERE {$full_user_select_sql}",
            $validateparams,
            ''
        );

        if ('' != $validuser) {
            debug("[validate_user()] User #{$validuser} is valid!");
            return true;
        }
    }

    return false;
}

/**
*
* Given an array of user data loaded from the user table, set up all necessary global variables for this user
* including permissions, current collection, config overrides and so on.
*
* @param  array  $userdata  Array of user data obtained by validate_user() from user/usergroup tables
*
* @return boolean           success/failure flag - used for example to prevent certain users from making API calls
*/
function setup_user(array $userdata)
{
    global $userpermissions, $usergroup, $usergroupname, $usergroupparent, $useremail, $useremail_rate_limit_active, $userpassword, $userfullname,
           $ip_restrict_group, $ip_restrict_user, $rs_session, $global_permissions, $userref, $username, $useracceptedterms,
           $anonymous_user_session_collection, $global_permissions_mask, $user_preferences, $userrequestmode,
           $usersearchfilter, $usereditfilter, $userderestrictfilter, $hidden_collections, $userresourcedefaults,
           $userrequestmode, $request_adds_to_collection, $usercollection, $lang, $validcollection,
           $userorigin, $actions_enable, $actions_permissions, $actions_on, $usersession, $anonymous_login, $resource_created_by_filter,
           $user_dl_limit,$user_dl_days, $USER_SELECTION_COLLECTION, $plugins, $userprocessing_messages,
           $search_includes_themes;

    # Hook to modify user permissions
    if (hook("userpermissions")) {
        $userdata["permissions"] = hook("userpermissions");
    }

    $userref           = $userdata['ref'];
    $username          = $userdata['username'];
    $useracceptedterms = $userdata['accepted_terms'];

    # Create userpermissions array for checkperm() function
    $userpermissions = array_diff(
        array_merge(
            explode(",", trim($global_permissions ?? "")),
            explode(",", trim($userdata["permissions"] ?? ""))
        ),
        explode(",", trim($global_permissions_mask ?? ""))
    );
    $userpermissions = array_values($userpermissions);# Resequence array as the above array_diff() causes out of step keys.

    $actions_on = $actions_enable;
    # Enable actions functionality if based on user permissions
    if (!$actions_enable && count($actions_permissions) > 0) {
        foreach ($actions_permissions as $actions_permission) {
            if (in_array($actions_permission, $userpermissions)) {
                $actions_on = true;
                break;
            }
        }
    }

    $usergroup = $userdata["usergroup"];
    $usergroupname = $userdata["groupname"];
    $usergroupparent = $userdata["parent"];
    $useremail = $userdata["email"];
    $userpassword = $userdata["password"];
    $userfullname = $userdata["fullname"];
    $userorigin = $userdata["origin"];
    $usersession = $userdata["session"];
    $userprocessing_messages = $userdata["processing_messages"];

    if (isset($userdata["email_rate_limit_active"])) {
        $useremail_rate_limit_active = $userdata["email_rate_limit_active"];
    }

    $ip_restrict_group = trim((string) $userdata["ip_restrict_group"]);
    $ip_restrict_user = trim((string) $userdata["ip_restrict_user"]);

    if (isset($anonymous_login) && $username == $anonymous_login && isset($rs_session) && !checkperm('b')) { // This is only required if anonymous user has collection functionality
        if ($anonymous_user_session_collection) {
            // Get all the collections that relate to this session
            $sessioncollections = get_session_collections($rs_session, $userref, true);
            // Just get the first one if more
            $usercollection = $sessioncollections[0];
        } else {
            // Unlikely scenario, but maybe we do allow anonymous users to change the selected collection for all other anonymous users
            $usercollection = $userdata["current_collection"];
        }
    } else {
        $usercollection = $userdata["current_collection"];
        // Check collection actually exists
        $validcollection = $userdata["current_collection_valid"];
        if ($validcollection == 0 || $usercollection == 0 || !is_numeric($usercollection)) {
            // Not a valid collection - switch to user's primary collection if there is one
            $usercollection = get_default_user_collection(true);
        }
    }

    if ($search_includes_themes && in_array(compute_featured_collections_access_control(), [false, []], true)) {
        // Check at least one featured collection exists and is visible to user
        $search_includes_themes = false;
    }

    $USER_SELECTION_COLLECTION = get_user_selection_collection($userref);
    if (is_null($USER_SELECTION_COLLECTION) && !(isset($anonymous_login) && $username == $anonymous_login)) {
        // Don't create a new collection on every anonymous page load, it will be created when an action is performed
        $USER_SELECTION_COLLECTION = create_collection($userref, "Selection Collection (for batch edit)", 0, 1);
        update_collection_type($USER_SELECTION_COLLECTION, COLLECTION_TYPE_SELECTION, false);
        clear_query_cache('user_selection_collection' . $userref);
    }

    $newfilter = false;

    if (isset($userdata["search_filter_o_id"]) && is_numeric($userdata["search_filter_o_id"]) && $userdata['search_filter_o_id'] > 0) {
        // User search filter override
        $usersearchfilter = $userdata["search_filter_o_id"];
        $newfilter = true;
    } elseif (isset($userdata["search_filter_id"]) && is_numeric($userdata["search_filter_id"]) && $userdata['search_filter_id'] > 0) {
        // Group search filter
        $usersearchfilter = $userdata["search_filter_id"];
        $newfilter = true;
    }

    if (!$newfilter) {
        // Old style search filter that hasn't been migrated
        $usersearchfilter = isset($userdata["search_filter_override"]) && $userdata["search_filter_override"] != '' ? $userdata["search_filter_override"] : $userdata["search_filter"];
    }

    $usereditfilter         = (isset($userdata["edit_filter_id"]) && is_numeric($userdata["edit_filter_id"]) && $userdata['edit_filter_id'] > 0) ? $userdata['edit_filter_id'] : $userdata["edit_filter"];
    $userderestrictfilter   = (isset($userdata["derestrict_filter_id"]) && is_numeric($userdata["derestrict_filter_id"]) && $userdata['derestrict_filter_id'] > 0) ? $userdata['derestrict_filter_id'] : $userdata["derestrict_filter"];

    $hidden_collections = explode(",", (string) $userdata["hidden_collections"]);
    $userresourcedefaults = $userdata["resource_defaults"];
    $userrequestmode = trim((string) $userdata["request_mode"]);
    $user_dl_limit = trim((string) $userdata["download_limit"]);
    $user_dl_days = trim((string) $userdata["download_log_days"]);

    if (
        (int)$user_dl_limit > 0
        && defined("API_CALL") // API cannot be used by these users as would open up opportunities to bypass limits
    ) {
            return false;
    }

    # Apply config override options
    $config_options = trim((string) $userdata["config_options"]);
    override_rs_variables_by_eval($GLOBALS, $config_options, 'usergroup');

    // Set default workflow states to show actions for, if not manually set by user
    get_config_option(['user' => $userref, 'usergroup' => $usergroup], 'actions_notify_states', $user_actions_notify_states, false);

    // Check if user has already explicitly asked not to see these
    get_config_option(['user' => $userref, 'usergroup' => $usergroup], 'actions_resource_review', $legacy_resource_review, true); // Deprecated option
    if ($user_actions_notify_states === false && $legacy_resource_review) {
        $default_notify_states = get_default_notify_states();
        $GLOBALS['actions_notify_states'] = implode(",", $default_notify_states);
    } elseif ($legacy_resource_review) {
        $GLOBALS['actions_notify_states'] = $user_actions_notify_states;
    }

    $plugins = register_group_access_plugins($usergroup, $plugins);
    hook('after_setup_user');
    return true;
}

/**
 * Returns a user list. Group or search term is optional. The standard user group names are translated using $lang. Custom user group names are i18n translated.
 *
 * @param  integer        $group                  Can be a single group, or a comma separated list of groups used to limit the results
 *                                                If blank, zero or NULL then all users will be returned irrespective of their group
 * @param  string         $find                   Search string to filter returned results
 * @param  string         $order_by
 * @param  boolean        $usepermissions
 * @param  integer        $fetchrows
 * @param  string         $approvalstate
 * @param  boolean        $returnsql               Return prepared statement object containing sql query and parameters.
 * @param  string         $selectcolumns
 * @param  boolean        $exact_username_match    Denotes $find must be an exact username
 *
 * @return array|object   Matching user records    Returns an array of user information or prepared statement object containing sql query and parameters.
 */
function get_users($group = 0, $find = "", $order_by = "u.username", $usepermissions = false, $fetchrows = -1, $approvalstate = "", $returnsql = false, $selectcolumns = "", $exact_username_match = false)
{
    global $usergroup, $usergroupparent;

    $order_by_parts = explode(" ", ($order_by ?? ""));
    $order_by       = $order_by_parts[0] ?? "u.username";
    $sort           = strtoupper(($order_by_parts[1] ?? "ASC")) == "DESC" ? "DESC" : "ASC";
    if (!in_array($order_by, array("u.created", "u.username", "approved", "u.fullname", 'g.name', 'email', 'created', 'last_active'))) {
        $order_by = "u.username";
    }

    $sql = "";
    $sql_params = array();
    $find = strtolower($find);
    # Sanitise the incoming group(s), stripping out any which are non-numeric
    $grouparray = array_filter(explode(",", $group), 'ctype_digit');

    if ($group != 0 && count($grouparray) > 0) {
        $sql = "where usergroup IN (" . ps_param_insert(count($grouparray)) . ")";
        $sql_params = ps_param_fill($grouparray, "i");
    }

    if ($exact_username_match) {
        # $find is an exact username
        if ($sql == "") {
            $sql = "where ";
        } else {
            $sql .= " and ";
        }
        $sql .= "LOWER(username) = ?";
        $sql_params = array_merge($sql_params, array("s", $find));
    } else {
        if (strlen($find) > 1) {
            if ($sql == "") {
                $sql = "where ";
            } else {
                $sql .= " and ";
            }
            $sql .= "(LOWER(username) like ? or LOWER(fullname) like ? or LOWER(email) like ? or LOWER(comments) like ?)";
            $sql_params = array_merge($sql_params, array("s", "%" . $find . "%", "s", "%" . $find . "%", "s", "%" . $find . "%", "s", "%" . $find . "%"));
        }
        if (strlen($find) == 1) {
            if ($sql == "") {
                $sql = "where ";
            } else {
                $sql .= " and ";
            }
            $sql .= "LOWER(username) like ?";
            $sql_params = array_merge($sql_params, array("s", $find . "%"));
        }
    }

    $approver_groups = get_approver_usergroups($usergroup);

    if ($usepermissions && checkperm('E')) {
        # Return users in child, parent and own groups
        if ($sql == "") {
            $sql = "where ";
        } else {
            $sql .= " and ";
        }

        $parent_own_sql = " g.ref = ? or g.ref = ? or ";
        $sql_params  = array_merge($sql_params, ['i', $usergroup, 'i', $usergroupparent]);

        if (count($approver_groups) > 0) {
            $sql .= "(" . $parent_own_sql . "find_in_set(?, g.parent) or g.ref in (" . ps_param_insert(count($approver_groups)) . "))";
            $sql_params = array_merge($sql_params, array("i", $usergroup), ps_param_fill($approver_groups, "i"));
        } else {
            $sql .= "(" . $parent_own_sql . "find_in_set(?, g.parent))";
            $sql_params = array_merge($sql_params, array("i", $usergroup));
        }

        $sql_hook_return = hook("getuseradditionalsql");
        if (is_a($sql_hook_return, 'PreparedStatementQuery')) {
            $sql .= $sql_hook_return->sql;
            $sql_params = array_merge($sql_params, $sql_hook_return->parameters);
        }
    }

    if (is_numeric($approvalstate)) {
        if ($sql == "") {
            $sql = "where ";
        } else {
            $sql .= " and ";
        }
        $sql .= "u.approved = ?";
        $sql_params = array_merge($sql_params, array("i", $approvalstate));
    }

    // Return users in both user's user group and children groups
    if ($usepermissions && (checkperm('U') || count($approver_groups) > 0)) {
        if (count($approver_groups) > 0) {
            $sql .= sprintf('%1$s (g.ref = ? OR find_in_set(?, g.parent) OR g.ref IN (' . ps_param_insert(count($approver_groups)) . '))', ($sql == '') ? 'WHERE' : ' AND');
            $sql_params = array_merge($sql_params, array("i", $usergroup, "i", $usergroup), ps_param_fill($approver_groups, "i"));
        } else {
            $sql .= sprintf('%1$s (g.ref = ? OR find_in_set(?, g.parent))', ($sql == '') ? 'WHERE' : ' AND');
            $sql_params = array_merge($sql_params, array("i", $usergroup, "i", $usergroup));
        }
    }

    if ($selectcolumns != "") {
        $selectcolumns = explode(",", $selectcolumns);
        $selectcolumns = array_map('trim', $selectcolumns);
        $selectcolumns = array_unique(array_merge($selectcolumns, array('u.created', 'u.fullname', 'u.email', 'u.username', 'u.comments', 'u.ref', 'u.usergroup', 'u.approved')));
        $select = implode(",", $selectcolumns);
    } else {
        $select = "u.*, g.name groupname, g.ref groupref, g.parent groupparent";
    }

    $query = "SELECT " . $select . " from user u left outer join usergroup g on u.usergroup = g.ref $sql order by $order_by $sort";

    # Executes query.
    if ($returnsql) {
        $return_sql = new PreparedStatementQuery();
        $return_sql->sql = $query;
        $return_sql->parameters = $sql_params;
        return $return_sql;
    }

    $r = ps_query($query, $sql_params, '', $fetchrows);

    # Translates group names in the newly created array.
    for ($n = 0; $n < count($r); $n++) {
        if (strpos($select, "groupname") === false || !is_array($r[$n])) {
            break;
        } # The padded rows can't be and don't need to be translated.
        $r[$n]["groupname"] = lang_or_i18n_get_translated($r[$n]["groupname"], "usergroup-");
    }

    return $r;
}

/**
 * Returns all the users who have the permission $permission.
 * The standard user group names are translated using $lang. Custom user group names are i18n translated.
 *
 * @param  string $permission The permission code to search for
 * @return array    Matching user records
 */
function get_users_with_permission($permission)
{
    # First find all matching groups.
    $groups = ps_query("SELECT ref,permissions FROM usergroup");
    $matched = array();
    for ($n = 0; $n < count($groups); $n++) {
        $perms = trim_array(explode(",", (string) $groups[$n]["permissions"]));
        if (in_array($permission, $perms)) {
            $matched[] = $groups[$n]["ref"];
        }
    }
    if (count($matched) < 1) {
        return array();
    }
    # Executes query.
    $r = ps_query(
        "SELECT u.*, g.name groupname, g.ref groupref, g.parent groupparent FROM user u
                   LEFT OUTER JOIN usergroup g ON u.usergroup = g.ref 
                   WHERE (g.ref IN (" . ps_param_insert(count($matched)) . ") OR (find_in_set('permissions', g.inherit_flags) > 0 
                   AND g.parent IN (" . ps_param_insert(count($matched)) . "))) ORDER BY username",
        array_merge(ps_param_fill($matched, "i"), ps_param_fill($matched, "i"))
    );

    # Translates group names in the newly created array.
    $return = array();
    for ($n = 0; $n < count($r); $n++) {
        $r[$n]["groupname"] = lang_or_i18n_get_translated($r[$n]["groupname"], "usergroup-");
        $return[] = $r[$n]; # Adds to return array.
    }

    return $return;
}

/**
 * Retrieve user records by e-mail address
 *
 * @param  string $email    The e-mail address to search for
 * @return array Matching user records
 */
function get_user_by_email($email)
{
    $r = ps_query("SELECT " . columns_in('user', 'u') . ", g.name groupname, g.ref groupref, g.parent groupparent FROM user u LEFT OUTER JOIN usergroup g ON u.usergroup = g.ref WHERE u.email LIKE ? ORDER BY username", array("s", "%" . $email . "%"));

    # Translates group names in the newly created array.
    $return = array();
    for ($n = 0; $n < count($r); $n++) {
        $r[$n]["groupname"] = lang_or_i18n_get_translated($r[$n]["groupname"], "usergroup-");
        $return[] = $r[$n]; # Adds to return array.
    }

    return $return;
}

/**
 * Retrieve user ID by username
 *
 * @param  string $username The username to search for (will match email if not found)
 * @return mixed  The matching user ID or false if not found
 */
function get_user_by_username($username)
{
    if (!is_string($username)) {
        return false;
    }
    $params = ["s",$username];
    $usermatch = ps_value("SELECT ref value FROM user WHERE username = ?", $params, 0);
    if ($usermatch == 0 && filter_var($username, FILTER_VALIDATE_EMAIL)) {
        // Check if trying to use email address
        $emailmatches = ps_array("SELECT ref value FROM user WHERE email = ?", $params);
        if (count($emailmatches) == 1) {
            debug("Matched user to email address");
            $usermatch = $emailmatches[0];
        }
    }
    return $usermatch > 0 ? $usermatch : false;
}

/**
 * Returns a list of user groups. The standard user groups are translated using $lang. Custom user groups are i18n translated.
 * Puts anything starting with 'General Staff Users' - in the English default names - at the top (e.g. General Staff).
 *
 * @param  boolean $usepermissions Use permissions (user access)
 * @param  string $find Search string
 * @param  boolean $id_name_pair_array  Return an array of ID->name instead of full records
 * @return array    Matching user group records
 */
function get_usergroups($usepermissions = false, $find = '', $id_name_pair_array = false)
{
    # Creates a query, taking (if required) the permissions  into account.
    global $usergroup;
    $approver_groups = get_approver_usergroups($usergroup);
    $sql = "";
    $sql_params = array();
    if ($usepermissions && (checkperm("U") || count($approver_groups) > 0)) {
        # Only return users in children groups to the user's group
        if ($sql == "") {
            $sql = "where ";
        } else {
            $sql .= " and ";
        }

        if (count($approver_groups) > 0) {
            $sql .= "(ref = ? or find_in_set(?, parent) or ref in (" . ps_param_insert(count($approver_groups)) . "))";
            $sql_params = array_merge(array("i", $usergroup, "i", $usergroup), ps_param_fill($approver_groups, "i"));
        } else {
            $sql .= "(ref = ? or find_in_set(?, parent))";
            $sql_params = array("i", $usergroup, "i", $usergroup);
        }
    }

    # Executes query.
    global $default_group;
    $r = ps_query("select ref, `name`, permissions, parent, search_filter, edit_filter, derestrict_filter, ip_restrict, resource_defaults, config_options,
        welcome_message, request_mode, allow_registration_selection, group_specific_logo, inherit_flags, search_filter_id, download_limit, download_log_days,
        edit_filter_id, derestrict_filter_id, group_specific_logo_dark from usergroup $sql order by (ref = ?) desc, name", array_merge($sql_params, array("i", $default_group)));

    # Translates group names in the newly created array.
    $return = array();
    for ($n = 0; $n < count($r); $n++) {
        $r[$n]["name"] = lang_or_i18n_get_translated($r[$n]["name"], "usergroup-");
        $return[] = $r[$n]; # Adds to return array.
    }

    if (strlen($find) > 0) {
        # Searches for groups with names which contains the string defined in $find.
        $initial_length = count($return);
        for ($n = 0; $n < $initial_length; $n++) {
            if (strpos(strtolower($return[$n]["name"]), strtolower($find)) === false) {
                unset($return[$n]); # Removes this group.
            }
        }
        $return = array_values($return); # Reassigns the indices.
    }

    // Return only an array with ref => name pairs
    if ($id_name_pair_array) {
        $return_id_name_array = array();

        foreach ($return as $user_group) {
            $return_id_name_array[$user_group['ref']] = $user_group['name'];
        }

        return $return_id_name_array;
    }

    return $return;
}

/**
 * Returns the user group corresponding to the $ref. A standard user group name is translated using $lang. A custom user group name is i18n translated.
 *
 * @param  integer $ref User group ID
 * @return mixed False if not found, or the user group record if found.
 */
function get_usergroup($ref)
{
    $return = ps_query("SELECT ref, name, permissions, parent, search_filter, search_filter_id, edit_filter, ip_restrict, resource_defaults, config_options, welcome_message, 
            request_mode, allow_registration_selection, derestrict_filter, group_specific_logo, inherit_flags, download_limit, download_log_days, edit_filter_id, derestrict_filter_id, group_specific_logo_dark "
            . hook('get_usergroup_add_columns') . " FROM usergroup WHERE ref = ?", array("i", $ref), 'usergroup');
    if (count($return) == 0) {
        return false;
    } else {
        $return[0]["name"] = lang_or_i18n_get_translated($return[0]["name"], "usergroup-");
        $return[0]["inherit"] = explode(",", trim($return[0]["inherit_flags"] ?? ""));
        return $return[0];
    }
}

/**
 * Return the user group record matching $ref
 *
 * @param  integer $ref
 * @return array|bool
 */
function get_user($ref)
{
    global $udata_cache;
    if (!isset($udata_cache[$ref])) {
        $user_columns = columns_in("user", "u");
        $user_columns = str_replace("ip_restrict`", "ip_restrict` ip_restrict_user", $user_columns);

        $udata_cache[$ref] = ps_query(
            "SELECT 
                {$user_columns},
                if(find_in_set('permissions',g.inherit_flags)>0 
                        AND pg.permissions IS NOT NULL,
                    pg.permissions,
                    g.permissions) permissions, 
                g.parent, 
                g.search_filter, 
                g.edit_filter, 
                g.ip_restrict ip_restrict_group, 
                g.name groupname,
                (select count(*) from collection where ref=u.current_collection) as current_collection_valid,
                g.resource_defaults,
                if(find_in_set('config_options',g.inherit_flags)>0 
                        AND pg.config_options IS NOT NULL,
                    pg.config_options,
                    g.config_options) config_options,
                g.request_mode,
                g.derestrict_filter,
                g.search_filter_id,
                g.download_limit,
                g.download_log_days,
                g.edit_filter_id,
                g.derestrict_filter_id
            FROM user u 
                LEFT JOIN usergroup g ON u.usergroup = g.ref 
                LEFT JOIN usergroup pg ON g.parent = pg.ref 
            WHERE u.ref = ?",
            array("i", $ref)
        );
    }

    # Return a user's credentials.
    if (count($udata_cache[$ref]) > 0) {
        return $udata_cache[$ref][0];
    } else {
        return false;
    }
}

/**
* Function used to update or delete a user.
* Note: data is taken from the submitted form
*
* @param string $ref ID of the user
* @param array  $data Data to save (optional, will use posted data otherwise)
*
* @return boolean|string True if successful or a descriptive string if there's an issue
*/
function save_user($ref, array $data=[])
{
    global $lang, $home_dash;

    if (!checkperm_user_edit($ref)) {
        return $lang['error-permissiondenied'];
    }

    $current_user_data = get_user($ref);

    if (!$current_user_data) {
        return $lang['accountdoesnotexist'];
    }

    // Save user details, data is taken from the submitted form.
    if ('' != getval('deleteme', '')) {
        delete_profile_image($ref);
        ps_query("DELETE FROM user WHERE ref = ?", array("i", $ref));

        hook('on_delete_user', "", array($ref));

        include_once __DIR__ . "/dash_functions.php";
        empty_user_dash($ref);

        log_activity("{$current_user_data['username']} ({$ref})", LOG_CODE_DELETED, null, 'user', null, $ref);

        return true;
    } else {
        // Get submitted values from either $data (if passed) or the POSTed form.
        $use_post_data = empty($data);

        $username               = $use_post_data ? trim(getval('username', '')) : trim($data['username'] ?? $current_user_data['username'] ?? '');
        $password               = $use_post_data ? trim(getval('password', '')) : trim($data['password'] ?? $lang['hidden']);
        $fullname               = $use_post_data ? str_replace("\t", ' ', trim(getval('fullname', ''))) : str_replace("\t", ' ', trim($data['fullname'] ?? $current_user_data['fullname'] ?? ''));
        $email                  = $use_post_data ? trim(getval('email', '')) : trim($data['email'] ?? $current_user_data['email'] ?? '');
        $usergroup              = $use_post_data ? trim(getval('usergroup', '', false, 'is_int_loose')) : trim($data['usergroup'] ?? $current_user_data['usergroup'] ?? '');
        $ip_restrict            = $use_post_data ? trim(getval('ip_restrict', '')) : trim($data['ip_restrict'] ?? $current_user_data['ip_restrict'] ?? '');
        $search_filter_override = $use_post_data ? trim(getval('search_filter_override', '')) : trim($data['search_filter_override'] ?? $current_user_data['search_filter_override'] ?? '');
        $search_filter_o_id     = $use_post_data ? trim(getval('search_filter_o_id', 0, true)) : trim($data['search_filter_o_id'] ?? $current_user_data['search_filter_o_id'] ?? '');
        $comments               = $use_post_data ? trim(getval('comments', '')) : trim($data['comments'] ?? $current_user_data['comments'] ?? '');
        $suggest                = $use_post_data ? getval('suggest', '') : ($data['suggest'] ?? '');
        $emailresetlink         = $use_post_data ? getval('emailresetlink', '') : ($data['emailresetlink'] ?? '');
        $approved               = $use_post_data ? getval('approved', 0, true) : ($data['approved'] ?? $current_user_data['approved'] ?? 0);
        $expires                = $use_post_data ? getval('account_expires', '') : ($data['account_expires'] ?? $current_user_data['account_expires'] ?? '');

        // Create SQL to check for username or e-mail address conflict
        $conditions = "username = ? OR email = ?";
        $params = ["s", $username, "s", $username];

        // Add code to set different values depending on what has conflicted
        $typecheck =  "WHEN (username = ?) THEN 1 "; // username matches another username
        $typecheck .= "WHEN (email = ?) THEN 2 "; // username matches another account's email
        $typeparams = ["s", $username, "s", $username];

        if ($email !== "") {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $lang['error_invalid_email'];
            }
            // Add checks for email conflicts
            $conditions .= " OR username = ? OR email = ?";
            $params = array_merge($params, ["s", $email, "s", $email]);
            $typecheck .= "WHEN (email = ?) THEN 3 "; // email matches another account's email
            $typecheck .= "WHEN (username = ?) THEN 4 "; // email matches the username of another account
            $typeparams = array_merge($typeparams, ["s", $email, "s", $email]);
        }
        $matchsql = " SELECT MIN(CASE $typecheck END) AS value
                         FROM user
                        WHERE ref <> ? AND ($conditions)";
        $c = ps_value($matchsql, array_merge($params, ["i",$ref], $typeparams), 0);
        if ($c > 0 && checkperm("U")) {
            // Return an ambiguous message if delegated user admin
            return $lang["useralreadyexists"];
        }
        switch ($c) {
            case 1:
                return $lang["useralreadyexists"]; // An account with that username already exists
                break;
            case 2:
                return $lang["username_conflicts_email"]; // Username matches another account's e-mail
                break;
            case 3:
                return $lang["useremailalreadyexists"]; // An account with that e-mail address exists
                break;
            case 4:
                return $lang["email_conflicts_username"]; // Email matches another account's username
                break;
            case 0:
            default:
                // All ok
                break;
        }

        // Enabling a disabled account but at the user limit?
        if (user_limit_reached() && $current_user_data["approved"] != 1 && $approved == 1) {
            return $lang["userlimitreached"]; // Return error message
        }

        // Password checks:
        if ($suggest != '' || ($password == '' && $emailresetlink != '')) {
            $password = make_password();
        } elseif ($password != $lang['hidden']) {
            $message = check_password($password);
            if ($message !== true) {
                return $message;
            }
        }

        # Validate expiry date
        if ($expires != "" && (preg_match("/^\d{4}-\d{2}-\d{2}$/", $expires) === 0 || strtotime($expires) === false)) {
            return str_replace('[value]', $expires, $lang['error_invalid_date_format']);
        }

        if ($expires == "") {
            $expires = null;
        } else {
            $expires = date("Y-m-d", strtotime($expires));
        }

        $passsql = '';
        $sql_params = array();
        if ($password != $lang['hidden']) {
            # Save password.
            if ($suggest == '') {
                $password = rs_password_hash("RS{$username}{$password}");
            }

            $passsql = ", password = ?, password_last_change = now()";
            $sql_params = array("s", $password);
        }

        // Full name checks
        if ('' == $fullname && '' == $suggest) {
            return $lang['setup-admin_fullname_error'];
        }

        /*Make sure IP restrict filter is a proper IP, otherwise make it blank
        Note: we do this check only when wildcards are not used*/
        if (false === strpos($ip_restrict, '*')) {
            $ip_restrict = (false === filter_var($ip_restrict, FILTER_VALIDATE_IP) ? '' : $ip_restrict);
        }

        $additional_sql = '';
        $additional_sql_params = array();
        $sql_hook_return =  hook('additionaluserfieldssave');
        if (is_a($sql_hook_return, 'PreparedStatementQuery')) {
            $additional_sql = $sql_hook_return->sql;
            $additional_sql_params = $sql_hook_return->parameters;
        }

        log_activity(null, LOG_CODE_EDITED, $username, 'user', 'username', $ref);
        log_activity(null, LOG_CODE_EDITED, $fullname, 'user', 'fullname', $ref);
        log_activity(null, LOG_CODE_EDITED, $email, 'user', 'email', $ref);

        if ((isset($current_user_data['usergroup']) && '' != $current_user_data['usergroup']) && $current_user_data['usergroup'] != $usergroup) {
            if (can_set_admin_usergroup($usergroup) && can_set_admin_usergroup($current_user_data['usergroup'])) {
                log_activity(null, LOG_CODE_EDITED, $usergroup, 'user', 'usergroup', $ref);
                ps_query("DELETE FROM resource WHERE ref = -?", array("i", $ref));
            } else {
                # User cannot set $usergroup to one with "super admin" level permissions - "a". Make no changes.
                # Check on current user group prevents permission de-escalation of "super admin" level user by user with less permissions.
                global $userref;
                debug("User $userref unable to change user group for user $ref from user group {$current_user_data['usergroup']} to user group $usergroup as this would involve granting or revoking the 'a' permission which they do not them self have.");
                $usergroup = $current_user_data['usergroup'];
            }
        }

        if ($email != $current_user_data["email"]) {
            $additional_sql .= ",email_invalid=0 ";
        }

        log_activity(null, LOG_CODE_EDITED, $ip_restrict, 'user', 'ip_restrict', $ref, null, '');
        log_activity(null, LOG_CODE_EDITED, $search_filter_override, 'user', 'search_filter_override', $ref, null, '');
        log_activity(null, LOG_CODE_EDITED, $expires, 'user', 'account_expires', $ref);
        log_activity(null, LOG_CODE_EDITED, $comments, 'user', 'comments', $ref);
        log_activity(null, LOG_CODE_EDITED, $approved, 'user', 'approved', $ref);

        $sql_params = array_merge(
            array("s", $username),
            $sql_params,
            array("s", $fullname,
                                        "s", $email,
                                        "i", $usergroup,
                                        "s", $expires,
                                        "s", $ip_restrict,
                                        "s", $search_filter_override,
                                        "i", $search_filter_o_id,
                                        "s", $comments,
                                        "i", $approved),
            $additional_sql_params,
            array("i", $ref)
        );
        ps_query("update user set username = ?" . $passsql . ", fullname = ?, email = ?, usergroup = ?, account_expires = ?, ip_restrict = ?,
            search_filter_override = ?, search_filter_o_id = ?, comments = ?, approved = ? " . $additional_sql . " where ref = ?", $sql_params);
    }

        // Add user group dash tiles as soon as we've changed the user group
    if (
            $home_dash
            && $current_user_data['usergroup'] != $usergroup
    ) {
            // If user group has changed, remove all user dash tiles that were valid for the old user group
            ps_query("DELETE FROM user_dash_tile WHERE user = ? AND dash_tile IN (SELECT dash_tile FROM usergroup_dash_tile WHERE usergroup = ?)", array("i", $ref, "i", $current_user_data['usergroup']));

            include_once __DIR__ . '/dash_functions.php';
            build_usergroup_dash($usergroup, $ref);
    }

    if ($emailresetlink != '') {
        $result = email_reset_link($email, empty($current_user_data["last_active"])); // Message differs if new user
        if ($result !== true) {
            return $result;
        }
    }

    if (getval('approved', '') != '') {
        # Clear any user request messages
        message_remove_related(USER_REQUEST, $ref);
    }

    return true;
}

/**
 * E-mail the user the welcome message on account creation.
 *
 * @param  string $email
 * @param  string $username
 * @param  integer $usergroup
 */
function email_user_welcome(string $email, string $username, int $usergroup): void
{
    global $applicationname, $baseurl,$lang;

    load_site_text_for_usergroup($usergroup);
    # Fetch any welcome message for this user group
    $welcome = (string) ps_value("SELECT welcome_message value FROM usergroup WHERE ref = ?", ["i",$usergroup], "");
    if (trim($welcome) === "") {
        $welcome = str_replace("%applicationname", $applicationname, $lang["welcome_generic"]);
    }

    $templatevars['welcome']  = i18n_get_translated($welcome) . "\n\n";
    $templatevars['username'] = $username;
    $templatevars['url'] = $baseurl . "/login.php";
    $message = $templatevars['welcome'] . $lang["newlogindetails"] . "\n\n" . $lang["username"] . ": " . $templatevars['username'] . "\n\n" . $templatevars['url'];
    send_mail($email, $applicationname . ": " . $lang["youraccountdetails"], $message, "", "", "emaillogindetails", $templatevars);
    load_site_text_for_usergroup(null);
}

/**
 * Email password reset link to the user
 *
 * @param string $email     Email address of user
 * @param string $newuser   Is this a new user account? If so a welcome message template will be used
 *
 * @return bool|string  true if success or error message
 *
 */
function email_reset_link(string $email, bool $newuser = false)
{
    global $applicationname, $baseurl, $lang;
    debug("password_reset - checking for email: " . $email);
    # Send a link to reset password
    global $password_brute_force_delay, $scramble_key, $lang;

    if ($email == '') {
        // Password reset link not sent because the account has no email address
        return $lang["accountnoemail-reset-not-emailed"];
    }
    # The reset link is sent after the principal user update has completed
    # It will only be sent if (after the user update) there is an approved and unexpired user with the specified email address
    $details = ps_query(
        "SELECT ref, username, usergroup, origin, approved,
	                CASE
                        WHEN isnull(account_expires) THEN false
                        WHEN account_expires > NOW() THEN false
                        ELSE true
                    END
                    AS has_expired
               FROM user WHERE email = ?;",
        ["s", $email]
    );

    if ($GLOBALS["pagename"] !== "team_user_edit") {
        // Don't add delay if admin is resetting password
        sleep($password_brute_force_delay);
    }

    if (count($details) == 0) {
        return $lang["accountnotfound-reset-not-emailed"]; # Password reset link was not sent because there is no account with that email
    }

    # Process the user with the email address
    $details = $details[0];

    if ($details["has_expired"]) {
        // Password reset link was not sent because the account has expired
        return $lang["accountexpired-reset-not-emailed"];
    }
    if ($details["approved"] != 1) {
        // Password reset link was not sent because the account is not approved
        return $lang["accountnotapproved-reset-not-emailed"];
    }

    // Don't send password reset links if we don't control the password
    $blockreset = isset($details["origin"]) && trim($details["origin"]) != "";

    if (!$blockreset) {
        $password_reset_url_key = create_password_reset_key($details['username']);
        $templatevars['url'] = $baseurl . '/?rp=' . $details['ref'] . $password_reset_url_key;
    }

    load_site_text_for_usergroup($details['usergroup']);
    $templatevars['username'] = $details["username"];
    $email_subject = $applicationname . ": " . $lang["youraccountdetails"];

    if ($newuser) {
        // Fetch any welcome message for this user group
        $welcome = ps_value('SELECT welcome_message AS value FROM usergroup WHERE ref = ?', ["i",$details['usergroup']], '');

        if (trim((string) $welcome) === "") {
            $welcome = str_replace("%applicationname", $applicationname, $lang["welcome_generic"]);
        }

        if (hook("ssologindefault")) {
            $loginurl = $baseurl . "/login.php";
        } else {
            $loginurl = $baseurl;
        }
        $templatevars['welcome'] = i18n_get_translated($welcome) . "\n\n";
        if ($blockreset) {
            $message = $templatevars['welcome'] . "\n\n" . $lang["passwordresetexternalauth"] . "\n\n" . $loginurl . "\n\n" . $lang["username"] . ": " . $templatevars['username'];
            $result = send_mail($email, $email_subject, $message);
        } else {
            $message = $templatevars['welcome'] . $lang["newlogindetails"] . "\n\n" . $loginurl . "\n\n" . $lang["username"] . ": " . $templatevars['username'] . "\n\n" .  $lang["passwordnewemail"] . "\n" . $templatevars['url'];
            $result = send_mail($email, $email_subject, $message, "", "", "passwordnewemailhtml", $templatevars);
        }
    } else {
        // Existing user
        $message = $lang["username"] . ": " . $templatevars['username'];
        if ($blockreset) {
            $message .=  "\n\n" . $lang["passwordresetnotpossible"] . "\n\n" . $lang["passwordresetexternalauth"] . "\n\n" . $baseurl;
            $result = send_mail($email, $email_subject, $message);
        } else {
            $message .= "\n\n" . $lang["passwordresetemail"] . "\n\n" . $templatevars['url'];
            $result = send_mail($email, $applicationname . ": " . $lang["resetpassword"], $message, "", "", "password_reset_email_html", $templatevars);
        }
    }

    global $usergroup;
    load_site_text_for_usergroup($usergroup);

    // Pass any e-mail errors back, or return true if successful
    return $result;
}

/**
 * Automatically creates a user account
 *   The request can be auto approved if $auto_approve_accounts is true
 *   Otherwise the approval is managed by admins via notification messages and/or emails
 *
 * @param  string $hash
 * @return boolean Success?
 */
function auto_create_user_account($hash = "")
{
    global $user_email, $baseurl, $lang, $user_account_auto_creation_usergroup, $registration_group_select,
           $auto_approve_accounts, $auto_approve_domains, $customContents, $language, $home_dash, $applicationname,
           $username, $userref;

    // Feature disabled if user limit reached.
    if (user_limit_reached()) {
        return false;
    }

    # Work out which user group to set. Allow a hook to change this, if necessary.
    $altgroup = hook("auto_approve_account_switch_group");
    if ($altgroup !== false) {
        $usergroup = $altgroup;
    } else {
        $usergroup = $user_account_auto_creation_usergroup;
    }

    if ($registration_group_select) {
        $usergroup = getval("usergroup", 0, true);
        # Check this is a valid selectable usergroup (should always be valid unless this is a hack attempt)
        if (ps_value("SELECT allow_registration_selection value FROM usergroup WHERE ref = ?", ["i",$usergroup], 0) != 1) {
            exit("Invalid user group selection");
        }
    }

    // Check valid email
    if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        return $lang['setup-emailerr'];
    }

    $newusername = make_username(getval("name", ""), $user_email);

    // Check if account already exists
    $emailmatches = ps_value("SELECT COUNT(*) value FROM user WHERE email = ?", ["s",$user_email], 0);
    if ($emailmatches > 0) {
        // Return an ambiguous message if delegated user admin
        return checkperm("U") ? $lang["useralreadyexists"] : $lang["useremailalreadyexists"];
    }

    # Prepare to create the user.
    $password = make_password();
    $password = rs_password_hash("RS{$newusername}{$password}");

    # Work out if we should automatically approve this account based on $auto_approve_accounts or $auto_approve_domains
    $approve = false;

    if ($auto_approve_accounts) {
        // No longer taken direct to password page as this can be used by bots. User must click on an email link
        $approve = true;
    } elseif (count($auto_approve_domains) > 0) {
        # Check e-mail domain.
        foreach ($auto_approve_domains as $domain => $set_usergroup) {
            // If a group is not specified the variables don't get set correctly so we need to correct this
            if (is_numeric($domain)) {
                $domain = $set_usergroup;
                $set_usergroup = "";
            }
            if (substr(strtolower($user_email), strlen($user_email) - strlen($domain) - 1) == ("@" . strtolower($domain))) {
                # E-mail domain match.
                $approve = true;
                # If user group is supplied, set this
                if (is_numeric($set_usergroup)) {
                    $usergroup = $set_usergroup;
                }
            }
        }
    }

    # Create the user
    $name = getval("name", "");
    $comment = getval("userrequestcomment", "");
    $newparams = [
        "s",$newusername,
        "s",$password,
        "s",$name,
        "s",$user_email,
        "i",$usergroup,
        "s",$customContents . (trim($comment) != "" ? "\n" . $comment : ""),
        "i",($approve ? 1 : 0),
        "s",$language,
        "s",($hash != "" ? $hash : null),
        ];

    ps_query("INSERT INTO user (username,password,fullname,email,usergroup,comments,approved,lang,unique_hash) VALUES (?,?,?,?,?,?,?,?,?)", $newparams);

    $new = sql_insert_id();

    // Create dash tiles for the new user
    if ($home_dash) {
        include_once __DIR__ . '/dash_functions.php';
        create_new_user_dash($new);
        build_usergroup_dash($usergroup, $new);
    }

    global $user_registration_opt_in;
    if ($user_registration_opt_in && getval("login_opt_in", "") == "yes") {
        log_activity($lang["user_registration_opt_in_message"], LOG_CODE_USER_OPT_IN, null, "user", null, null, null, null, $new, false);
    }

    hook("afteruserautocreated", "all", array("new" => $new));
    global $anonymous_login;
    if (isset($anonymous_login)) {
        global $rs_session;
        $rs_session = get_rs_session_id();
        if ($rs_session !== false) {
            # Copy any anonymous session collections to the new user account
            if (is_array($anonymous_login) && array_key_exists($baseurl, $anonymous_login)) {
                $anonymous_login = $anonymous_login[$baseurl];
            }

            $username = $anonymous_login;
            $userref = ps_value("SELECT ref value FROM user where username=?", array("s",$anonymous_login), "");
            $sessioncollections = get_session_collections($rs_session, $userref, false);
            if (count($sessioncollections) > 0) {
                foreach ($sessioncollections as $sessioncollection) {
                    update_collection_user($sessioncollection, $new);
                }
                ps_query("UPDATE user SET current_collection = ? WHERE ref = ?", array("i", $sessioncollection, "i", $new));
            }
        }
    }
    if ($approve) {
        email_reset_link($user_email, true);
    } else {
        # Managed approving
        # Build a message to send to an admin notifying of unapproved user (same as email_user_request(),
        # but also adds the new user name to the mail)
        global $user_pref_user_management_notifications;

        $templatevars['name'] = getval("name", "");
        $templatevars['email'] = $user_email;
        $templatevars['userrequestcomment'] = strip_tags(getval("userrequestcomment", ""));
        $templatevars['userrequestcustom'] = strip_tags($customContents);
        $url = $baseurl . "?u=" . $new;
        $templatevars['linktouser'] = "<a href='" . $url . "'>" . $url . "</a>";

        $approval_notify_users = get_notification_users("USER_ADMIN", $usergroup);

        $message = new ResourceSpaceUserNotification();
        $eventdata = [
            "type"  => USER_REQUEST,
            "ref"   => $new,
            "extra" => ["usergroup" => $usergroup],
            ];
        $message->set_subject("lang_requestuserlogin");
        $message->set_text("lang_userrequestnotification1");
        $message->append_text("<br/><br/>");
        $message->append_text("lang_name");
        $message->append_text(": " . $templatevars['name'] . "<br/><br/>");
        $message->append_text("lang_email");
        $message->append_text(": " . $templatevars['email'] . "<br/><br/>");
        $message->append_text("lang_comment");
        $message->append_text(": " . $templatevars['userrequestcomment'] . "<br/><br/>");
        $message->append_text("lang_ipaddress");
        $message->append_text(": " . get_ip() . "<br/><br/>");
        if (trim($customContents) != "") {
            $message->append_text($customContents . "<br/><br/>");
        }
        $message->append_text("lang_userrequestnotification3");
        $message->append_text("<br/><br/>" . $templatevars['linktouser']);
        $message->user_preference =  [
            "user_pref_user_management_notifications" => ["requiredvalue" => true, "default" => $user_pref_user_management_notifications],
            "actions_account_requests" => ["requiredvalue" => false,"default" => true],
            ];
        $message->url = $url;
        $message->template = "account_request";
        $message->templatevars = $templatevars;
        $message->eventdata = $eventdata;
        send_user_notification($approval_notify_users, $message);
    }

    // Send a confirmation e-mail to requester
    send_mail(
        $user_email,
        "{$applicationname}: {$lang['account_request_label']}",
        $lang['account_request_confirmation_email_to_requester']
    );

    return true;
}

/**
* Send user request to admins in form of notification messages and/or emails
* Send email confirmation to requesting user
*
* @return boolean
*/
function email_user_request()
{
    // E-mails the submitted user request form to the team.
    global $applicationname, $baseurl, $lang, $customContents, $account_email_exists_notify, $user_registration_opt_in, $user_account_auto_creation, $user_pref_user_management_notifications;

    // Get posted vars sanitized:
    $name               = strip_tags(getval('name', ''));
    $email              = strip_tags(getval('email', ''));
    $userrequestcomment = strip_tags(getval('userrequestcomment', ''));

    $user_limit_reached = user_limit_reached();

    $usergroup = 0;
    if ($account_email_exists_notify) {
        $usergroup = ps_value("SELECT usergroup AS `value` FROM user WHERE email = ?", ["s", $email], 0);
    }

    $user_registration_opt_in_message = "";
    if ($user_registration_opt_in && getval("login_opt_in", "") == "yes") {
        $user_registration_opt_in_message .= $lang["user_registration_opt_in_message"];
    }
    $requestedgroup = getval("usergroup", 0, true);
    $approval_notify_users = get_notification_users("USER_ADMIN", $usergroup ?: $requestedgroup);
    $message = new ResourceSpaceUserNotification();
    $message->set_subject($applicationname . ": ");
    $message->append_subject("lang_requestuserlogin");
    $message->append_subject(" - " . $name);
    $message->set_text($account_email_exists_notify && !$user_limit_reached ? "lang_userrequestnotificationemailprotection1" :  "lang_userrequestnotification1");
    $message->append_text("<br/><br/>");
    $message->append_text("lang_name");
    $message->append_text(": " . $name . "<br/><br/>");
    $message->append_text("lang_email");
    $message->append_text(": " . $email . "<br/><br/>");
    $message->append_text($user_registration_opt_in_message . "<br/><br/>");
    $message->append_text("lang_comment");
    $message->append_text(": " . $userrequestcomment . "<br/><br/>");
    $message->append_text("lang_ipaddress");
    $message->append_text(": " .  get_ip()  . "<br/><br/>");
    if (trim($customContents) != "") {
        $message->append_text($customContents . "<br/><br/>");
    }
    // User limit reached? Add a message explaining.
    if ($user_limit_reached) {
        $message->append_text("lang_userlimitreached");
    } else {
        $message->append_text($account_email_exists_notify ? "lang_userrequestnotificationemailprotection2" : "lang_userrequestnotification2");
    }
    $message->user_preference = ["user_pref_user_management_notifications" => ["requiredvalue" => true, "default" => $user_pref_user_management_notifications]];
    $message->url = $baseurl . "/pages/team/team_user.php";
    send_user_notification($approval_notify_users, $message);

    // Send a confirmation e-mail to requester
    send_mail(
        $email,
        "{$applicationname}: {$lang['account_request_label']}",
        $lang['account_request_confirmation_email_to_requester']
    );

    return true;
}

/**
* Check to see if the user limit has been reached.
* *
* @return boolean  - true if user limit has been reached or exceeded
*/

function user_limit_reached()
{
    global $user_limit;
    if (
        isset($user_limit)
        && get_total_approved_users() >= $user_limit
    ) {
            return true;
    }
    return false;
}

/**
* Create a new user
* *
* @param string $newuser  - username to create
* @param integer $usergroup  - optional usergroup to assign
*
* @return boolean|integer  - id of new user or false if user already exists / permission denied, or -2 if user limit reached
*/
function new_user($newuser, $usergroup = 0)
{
    global $lang,$home_dash,$user_limit;

    # Username already exists?
    $c = ps_value("SELECT COUNT(*) value FROM user WHERE username = ?", ["s",$newuser], 0);
    if ($c > 0) {
        return false;
    }

    # User limit reached?
    if (user_limit_reached()) {
        return -2;
    }

    $cols = array("username");
    $sqlparams = ["s",$newuser];
    $cols[] = 'password';
    $sqlparams[] = 's';
    $sqlparams[] = rs_password_hash("RS{$newuser}" . make_password());

    if ($usergroup > 0) {
        $cols[] = "usergroup";
        $sqlparams[] = "i";
        $sqlparams[] = $usergroup;
    }

    $sql = "INSERT INTO user (" . implode(",", $cols) . ") VALUES (" . ps_param_insert(count($cols)) . ")";
    ps_query($sql, $sqlparams);

    $newref = sql_insert_id();

    #Create Default Dash for the new user
    if ($home_dash) {
        include_once __DIR__ . "/dash_functions.php";
        create_new_user_dash($newref);
    }

    # Create a collection for this user, the collection name is translated when displayed!
    $new = create_collection($newref, "Default Collection", 0, 1); # Do not translate this string!
    # set this to be the user's current collection
    ps_query("UPDATE user SET current_collection=? WHERE ref=?", ["i",$new,"i",$newref]);
    log_activity($lang["createuserwithusername"], LOG_CODE_CREATED, $newuser, 'user', 'ref', $newref, null, '');

    return $newref;
}

/**
 * Returns a list of active users
 *
 * @return array
 */
function get_active_users()
{
    global $usergroup;
    // Establish which user groups the supplied user group acts as an approver for
    $approver_groups = get_approver_usergroups($usergroup);
    $sql = "where logged_in = 1 and unix_timestamp(now()) - unix_timestamp(last_active) < (3600*2)";
    $sql_params = array();
    if (checkperm("U") || count($approver_groups) > 0) {
        if (count($approver_groups) > 0) {
            $sql .= "and (find_in_set(?, g.parent) or usergroup in (" . ps_param_insert(count($approver_groups)) . "))";
            $sql_params = array_merge(array("i", $usergroup), ps_param_fill($approver_groups, "i"));
        } else {
            $sql .= " and find_in_set(?, g.parent) ";
            $sql_params = array("i", $usergroup);
        }
    }

    # Returns a list of all active users, i.e. users still logged on with a last-active time within the last 2 hours.
    return ps_query("SELECT u.ref, u.username, round((unix_timestamp(now()) - unix_timestamp(u.last_active)) / 60, 0) t 
                    from user u left outer join usergroup g on u.usergroup = g.ref 
                    $sql order by t;", $sql_params);
}

/**
 * Sets a new password for the current user.
 *
 * @param  string $password
 * @return mixed True if a success or a descriptive string if there's an issue.
 */
function change_password($password)
{
    global $userref,$username,$lang,$userpassword, $password_reset_mode;

    # Check password
    $message = check_password($password);
    if ($message !== true) {
        return $message;
    }

    # Generate new password hash
    $password_hash = rs_password_hash("RS{$username}{$password}");

    # Check password is not the same as the current
    if ($userpassword == $password_hash) {
        return $lang["password_matches_existing"];
    }

    ps_query("update user set password = ?, password_reset_hash = NULL, login_tries = 0, password_last_change = now() where ref = ? limit 1", array("s", $password_hash, "i", $userref));
    return true;
}

/**
 * Generate a password using the configured settings.
 *
 * @return string The generated password
 */
function make_password()
{
    global $password_min_length, $password_min_alpha, $password_min_uppercase, $password_min_numeric, $password_min_special;

    $lowercase = "abcdefghijklmnopqrstuvwxyz";
    $uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $alpha = $uppercase . $lowercase;
    $numeric = "0123456789";
    $special = "!@$%^&*().?";

    $password = "";

    # Add alphanumerics
    for ($n = 0; $n < $password_min_alpha; $n++) {
        $password .= substr($alpha, rand(0, strlen($alpha) - 1), 1);
    }

    # Add upper case
    for ($n = 0; $n < $password_min_uppercase; $n++) {
        $password .= substr($uppercase, rand(0, strlen($uppercase) - 1), 1);
    }

    # Add numerics
    for ($n = 0; $n < $password_min_numeric; $n++) {
        $password .= substr($numeric, rand(0, strlen($numeric) - 1), 1);
    }

    # Add special
    for ($n = 0; $n < $password_min_special; $n++) {
        $password .= substr($special, rand(0, strlen($special) - 1), 1);
    }

    # Pad with lower case
    $padchars = $password_min_length - strlen($password);
    for ($n = 0; $n < $padchars; $n++) {
        $password .= substr($lowercase, rand(0, strlen($lowercase) - 1), 1);
    }

    # Shuffle the password.
    $password = str_shuffle($password);

    # Check the password
    $check = check_password($password);
    if ($check !== true) {
        exit("Error: unable to automatically produce a password that met the criteria. Please check the password criteria in config.php. Generated password was '$password'. Error was: " . $check);
    }

    return $password;
}

/**
 * Send a bulk e-mail using the bulk e-mail tool.
 *
 * @param  string $userlist
 * @param  string $subject
 * @param  string $text
 * @param  string $html
 * @param  integer $message_type
 * @param  string $url
 * @return string The empty string if all OK, a descriptive string if there's an issue.
 */
function bulk_mail($userlist, $subject, $text, $html = false, $message_type = MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL, $url = "")
{
    global $email_from,$lang,$applicationname;

    # Attempt to resolve all users in the string $userlist to user references.
    if (trim($userlist) == "") {
        return $lang["mustspecifyoneuser"];
    }
    $userlist = resolve_userlist_groups($userlist);
    $ulist = trim_array(explode(",", $userlist));

    $templatevars['text'] = stripslashes(str_replace("\\r\\n", "\n", $text));
    $body = $templatevars['text'];

    if ($message_type == MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL || $message_type == (MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL | MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN)) {
        $emails = resolve_user_emails($ulist);

        if (0 === count($emails)) {
            return $lang['email_error_user_list_not_valid'];
        }

        $emails = $emails['emails'];

        # Send an e-mail to each resolved user
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                send_mail($email, $subject, $body, $applicationname, $email_from, "emailbulk", $templatevars, $applicationname, "", $html);
            }
        }
    }
    if ($message_type == MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN || $message_type == (MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL | MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN)) {
        $user_refs = array();
        foreach ($ulist as $user) {
            $user_ref = ps_value("SELECT ref AS value FROM user WHERE username=?", array("s",$user), false);
            if ($user_ref !== false) {
                array_push($user_refs, $user_ref);
            }
        }
        if ($message_type == (MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL | MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN) && $html) {
            # strip the tags out
            $body = strip_tags($body);
        }
        message_add($user_refs, $body, $url, null, $message_type);
    }

    # Return an empty string (all OK).
    return "";
}

/**
 * Returns a user action log for $user.
 * Standard field titles are translated using $lang.  Custom field titles are i18n translated.
 *
 * @param  integer $user
 * @param  integer $fetchrows How many rows to fetch?
 * @return array
 */
function get_user_log($user, $fetchrows = -1)
{
    global $view_title_field;
    # Executes query.
    $r = ps_query("select r.ref resourceid, r.field" . (int) $view_title_field . " resourcetitle, l.date, l.type, f.title, l.notes, l.diff from resource_log l left outer join resource r on l.resource = r.ref left outer join resource_type_field f on f.ref = l.resource_type_field where l.user = ? order by l.date desc", array("i", $user), '', $fetchrows);

    # Translates field titles in the newly created array.
    $return = array();
    for ($n = 0; $n < count($r); $n++) {
        if (is_array($r[$n])) {
            $r[$n]["title"] = lang_or_i18n_get_translated($r[$n]["title"], "fieldtitle-");
        }
        $return[] = $r[$n];
    }
    return $return;
}

/**
 * Given an array or comma separated user list (from the user select include file) turn all Group: entries into fully resolved list of usernames.
 * Note that this function can't decode default groupnames containing special characters.
 *
 * @param  string|array $userlist
 * @return string The resolved list
 */
function resolve_userlist_groups($userlist)
{
    global $lang;
    if (!is_array($userlist)) {
        $userlist = explode(",", $userlist);
    }
    $newlist = "";
    for ($n = 0; $n < count($userlist); $n++) {
        $u = trim($userlist[$n]);
        if (strpos($u, $lang["group"] . ": ") === 0) {
            # Group entry, resolve

            # Find the translated groupname.
            $translated_groupname = trim(substr($u, strlen($lang["group"] . ": ")));
            # Search for corresponding $lang indices.
            $default_group = false;
            $langindices = array_keys($lang, $translated_groupname);
            if (count($langindices) > 0) {
                foreach ($langindices as $langindex) {
                    # Check if it is a default group
                    if (strstr($langindex, "usergroup-") !== false) {
                        # Decode the groupname by using the code from lang_or_i18n_get_translated the other way around (it could be possible that someone have renamed the English groupnames in the language file).
                        $untranslated_groupname = trim(substr($langindex, strlen("usergroup-")));
                        $untranslated_groupname = str_replace(array("_", "and"), array(" "), $untranslated_groupname);
                        $groupref = ps_value("select ref as value from usergroup where lower(name)=?", array("s",$untranslated_groupname), false);
                        if ($groupref !== false) {
                            $default_group = true;
                            break;
                        }
                    }
                }
            }
            if (!$default_group) {
                # Custom group
                # Decode the groupname
                $untranslated_groups = ps_query("select ref, name from usergroup");
                foreach ($untranslated_groups as $group) {
                    if (i18n_get_translated($group['name']) == $translated_groupname) {
                        $groupref = $group['ref'];
                        break;
                    }
                }
            }

            # Find and add the users.
            if (isset($groupref)) {
                $users = ps_array("SELECT username AS `value` FROM user WHERE usergroup = ?", array("i", $groupref));
                if ($newlist != "") {
                    $newlist .= ",";
                }
                $newlist .= join(",", $users);
            }
        } else {
            # Username, just add as-is
            if ($newlist != "") {
                $newlist .= ",";
            }
            $newlist .= $u;
        }
    }
    return $newlist;
}

/**
 * Given a comma separated user list (from the user select include file) turn all Group: entries into fully resolved list of usernames.
 * Note that this function can't decode default groupnames containing special characters.
 *
 * @param  string $userlist
 * @param  boolean $return_usernames
 * @return string The resolved list
 */
function resolve_userlist_groups_smart($userlist, $return_usernames = false)
{
    global $lang;
    if (!is_array($userlist)) {
        $userlist = explode(",", $userlist);
    }
    $newlist = "";
    for ($n = 0; $n < count($userlist); $n++) {
        $u = trim($userlist[$n]);
        if (strpos($u, $lang["groupsmart"] . ": ") === 0) {
            # Group entry, resolve

            # Find the translated groupname.
            $translated_groupname = trim(substr($u, strlen($lang["groupsmart"] . ": ")));
            # Search for corresponding $lang indices.
            $default_group = false;
            $langindices = array_keys($lang, $translated_groupname);
            if (count($langindices) > 0) {
                foreach ($langindices as $langindex) {
                    # Check if it is a default group
                    if (strstr($langindex, "usergroup-") !== false) {
                        # Decode the groupname by using the code from lang_or_i18n_get_translated the other way around (it could be possible that someone have renamed the English groupnames in the language file).
                        $untranslated_groupname = trim(substr($langindex, strlen("usergroup-")));
                        $untranslated_groupname = str_replace(array("_", "and"), array(" "), $untranslated_groupname);
                        $groupref = ps_value("select ref as value from usergroup where lower(name)=?", array("s",$untranslated_groupname), false);
                        if ($groupref !== false) {
                            $default_group = true;
                            break;
                        }
                    }
                }
            }
            if (!$default_group) {
                # Custom group
                # Decode the groupname
                $untranslated_groups = ps_query("select ref, name from usergroup");

                foreach ($untranslated_groups as $group) {
                    if (i18n_get_translated($group['name']) == $translated_groupname) {
                        $groupref = $group['ref'];
                        break;
                    }
                }
            }
            if (isset($groupref)) {
                if ($return_usernames) {
                    $users = ps_array("select username value from user where usergroup = ?", array("i", $groupref));
                    if ($newlist != "") {
                        $newlist .= ",";
                    }
                    $newlist .= join(",", $users);
                } else {
                    # Find and add the users.
                    if ($newlist != "") {
                        $newlist .= ",";
                    }
                    $newlist .= $groupref;
                }
            }
        }
    }
    return $newlist;
}

/**
 * Remove smart lists from the provided user lists.
 *
 * @param  string|array $ulist    Comma separated list of user list names
 * @return string   The updated list with smart groups removed.
 */
function remove_groups_smart_from_userlist($ulist)
{
    global $lang;

    if (!is_array($ulist)) {
        $ulist = explode(",", $ulist);
    }

    $new_ulist = '';
    foreach ($ulist as $option) {
        if (strpos($option, $lang["groupsmart"] . ": ") === false) {
            if ($new_ulist != "") {
                $new_ulist .= ",";
            }
            $new_ulist .= $option;
        }
    }
    return $new_ulist;
}

/**
 * Checks that a password conforms to the configured paramaters.
 *
 * @param  string $password The password
 * @return mixed True if OK, or a descriptive string if it isn't
 */
function check_password($password)
{
    global $lang, $password_min_length, $password_min_alpha, $password_min_uppercase, $password_min_numeric, $password_min_special;

    $password = trim($password);
    if (strlen($password) < $password_min_length) {
        return str_replace("?", $password_min_length, $lang["password_not_min_length"]);
    }

    $uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $alpha = $uppercase . "abcdefghijklmnopqrstuvwxyz";
    $numeric = "0123456789";

    $a = 0;
    $u = 0;
    $n = 0;
    $s = 0;
    for ($m = 0; $m < strlen($password); $m++) {
        $l = substr($password, $m, 1);
        if (strpos($uppercase, $l) !== false) {
            $u++;
        }

        if (strpos($alpha, $l) !== false) {
            $a++;
        } elseif (strpos($numeric, $l) !== false) {
            $n++;
        } else {
            $s++;
        } # Not alpha/numeric, must be a special char.
    }

    if ($a < $password_min_alpha) {
        return str_replace("?", $password_min_alpha, $lang["password_not_min_alpha"]);
    }
    if ($u < $password_min_uppercase) {
        return str_replace("?", $password_min_uppercase, $lang["password_not_min_uppercase"]);
    }
    if ($n < $password_min_numeric) {
        return str_replace("?", $password_min_numeric, $lang["password_not_min_numeric"]);
    }
    if ($s < $password_min_special) {
        return str_replace("?", $password_min_special, $lang["password_not_min_special"]);
    }

    return true;
}

/**
 * For a given comma-separated list of user refs (e.g. returned from a group_concat()), return a string of matching usernames.
 *
 * @param  string $users User list - caution, used directly in SQL so must not contain user input
 * @return string Matching usernames.
 */
function resolve_users($users)
{
    if (trim($users) == "") {
        return "";
    }
    $resolved = ps_array("select concat(fullname, ' (',username,')') value from user where ref in (?)", array("s", $users));
    return join(", ", $resolved);
}

/**
 * Verify a supplied external access key
 *
 * @param  array | integer $resources   Resource ID | Array of resource IDs
 * @param  string $key                  The external access key
 * @param  boolean $checkcollection     Check collection access key? true by default but required to prevent infinite recursion
 * @return boolean Valid?
 */
function check_access_key($resources, $key, $checkcollection = true)
{
    global $anonymous_login;

    if (trim($key) == '') {
        return false;
    }

    if (!is_array($resources)) {
        $resources = array($resources);
    }
    array_filter($resources, 'is_int_loose');

    foreach ($resources as $resource) {
        $resource = (int)$resource;
        # Option to plugin in some extra functionality to check keys
        if (hook("check_access_key", "", array($resource, $key)) === true) {
            return true;
        }
    }
    hook("external_share_view_as_internal_override");

    global $external_share_view_as_internal, $baseurl, $baseurl_short;

    if ($external_share_view_as_internal && isset($_COOKIE["user"])) {
        $user_select_sql = new PreparedStatementQuery();
        $user_select_sql->sql = "u.session = ?";
        $user_select_sql->parameters = ["s",$_COOKIE["user"]];
        if (validate_user($user_select_sql, false) && !is_authenticated()) {
            // Authenticate the user if not already authenticated so page can appear as internal
            return false;
        }
    }

    $anon_sql = '';
    $anon_params = [];
    if (isset($anonymous_login) && trim($anonymous_login) !== '') {
        $anon_sql    = ' AND u.username != ?';
        $anon_params = ['s', $anonymous_login];
    }

    $keys = ps_query(
        "
            SELECT k.user,
                   u.username,
                   k.usergroup,
                   k.expires,
                   k.password_hash, 
                   k.access,
                   k.resource
            FROM external_access_keys k 
            LEFT JOIN user u ON u.ref = k.user
            WHERE k.access_key = ?
               AND k.resource IN (" . ps_param_insert(count($resources)) . ")
               AND (k.expires IS NULL OR k.expires > now())
               AND u.approved = 1 {$anon_sql}
               ORDER BY k.access",
        array_merge(array("s", $key), ps_param_fill($resources, "i"), $anon_params)
    );

    if (count($keys) == 0 || count(array_diff($resources, array_column($keys, "resource"))) > 0) {
        // Check if this is a request for a resource uploaded to an upload_share
        $upload_sharecol = upload_share_active();
        if ($checkcollection && $upload_sharecol && check_access_key_collection($upload_sharecol, $key, false)) {
            $uploadsession = get_rs_session_id();
            $uploadcols = get_session_collections($uploadsession);
            foreach ($uploadcols as $uploadcol) {
                $sessioncol_resources = get_collection_resources($uploadcol);
                if (!array_diff($sessioncol_resources, $resources)) {
                    return true;
                }
            }
        }
        return false;
    }

    if ($keys[0]["access"] == -1) {
        // If the resources have -1 as access they may have been added without the correct expiry etc.
        ps_query("UPDATE external_access_keys ak
            LEFT JOIN (SELECT * FROM external_access_keys ake WHERE access_key = ? ORDER BY access DESC, expires ASC LIMIT 1) ake
                ON ake.access_key = ak.access_key
                AND ake.collection = ak.collection
            SET ak.expires = ake.expires, 
                ak.access = ake.access,
                ak.usergroup = ake.usergroup,
                ak.email = ake.email,
                ak.password_hash = ake.password_hash
            WHERE ak.access_key = ?
            AND ak.access = '-1'
            AND ak.expires IS NULL", array("s", $key, "s", $key));
        return false;
    }

    if ($keys[0]["password_hash"] != "" && PHP_SAPI != "cli") {
        // A share password has been set. Check if user has a valid cookie set
        $share_access_cookie = isset($_COOKIE["share_access"]) ? $_COOKIE["share_access"] : "";
        $check = check_share_password($key, "", $share_access_cookie);
        if (!$check) {
            $url = generateURL($baseurl . "/pages/share_access.php", array("k" => $key,"resource" => $resources[0],"return_url" => $baseurl . (isset($_SERVER["REQUEST_URI"]) ? urlencode(str_replace($baseurl_short, "/", $_SERVER["REQUEST_URI"])) : "/r=" . $resource . "&k=" . $key)));
            redirect($url);
            exit();
        }
    }

    $user       = $keys[0]["user"];
    $group      = $keys[0]["usergroup"];
    $expires    = $keys[0]["expires"];

    # Has this expired?
    if ($expires != "" && strtotime($expires) < time()) {
        if (is_authenticated()) {
            return false;
        }
        global $lang;
        ?>
        <script type="text/javascript">
        alert("<?php echo escape($lang["externalshareexpired"]); ?>");
        history.go(-1);
        </script>
        <?php
        exit();
    }
    # "Emulate" the user that e-mailed the resource by setting the same group and permissions
    emulate_user($user, $group);

    global $usergroup,$userpermissions,$userrequestmode,$usersearchfilter,$external_share_groups_config_options;
            $groupjoin = "u.usergroup = g.ref";
            $permissionselect = "g.permissions";
            $groupjoin_params = array();
    if ($keys[0]["usergroup"] != "") {
        # Select the user group from the access key instead.
        $groupjoin = "g.ref = ? LEFT JOIN usergroup pg ON g.parent = pg.ref";
        $groupjoin_params = array("i", $keys[0]["usergroup"]);
        $permissionselect = "if(find_in_set('permissions', g.inherit_flags) AND pg.permissions IS NOT NULL, pg.permissions, g.permissions) permissions";
    }
    $userinfo = ps_query("select g.ref usergroup," . $permissionselect . " , g.search_filter, g.config_options, g.search_filter_id, g.derestrict_filter_id, u.search_filter_override, u.search_filter_o_id from user u join usergroup g on $groupjoin where u.ref = ?", array_merge($groupjoin_params, array("i", $user)));
    if (count($userinfo) > 0) {
        $usergroup = $userinfo[0]["usergroup"]; # Older mode, where no user group was specified, find the user group out from the table.
        // Usergroup that the key is trying to emulate has no permissions
        if (trim((string) $userinfo[0]['permissions']) == '') {
            return false;
        }
        $userpermissions = explode(",", $userinfo[0]["permissions"]);

        if (isset($userinfo[0]["search_filter_o_id"]) && is_numeric($userinfo[0]["search_filter_o_id"]) && $userinfo[0]['search_filter_o_id'] > 0) {
            // User search filter override
            $usersearchfilter = $userinfo[0]["search_filter_o_id"];
        } elseif (isset($userinfo[0]["search_filter_id"]) && is_numeric($userinfo[0]["search_filter_id"]) && $userinfo[0]['search_filter_id'] > 0) {
            // Group search filter
            $usersearchfilter = $userinfo[0]["search_filter_id"];
        }

        if (hook("modifyuserpermissions")) {
            $userpermissions = hook("modifyuserpermissions");
        }
        $userrequestmode = 0; # Always use 'email' request mode for external users

        # Load any plugins specific to the group of the sharing user, but only once as may be checking multiple keys
        global $emulate_plugins_set;
        if ($emulate_plugins_set !== true) {
            global $plugins;
            $enabled_plugins = (ps_query("SELECT name,enabled_groups, config, config_json FROM plugins WHERE inst_version>=0 AND length(enabled_groups)>0  ORDER BY priority"));
            foreach ($enabled_plugins as $plugin) {
                $s = explode(",", $plugin['enabled_groups']);
                if (in_array($usergroup, $s)) {
                    include_plugin_config($plugin['name'], $plugin['config'], $plugin['config_json']);
                    register_plugin($plugin['name']);
                    $plugins[] = $plugin['name'];
                }
            }
            for ($n = count($plugins) - 1; $n >= 0; $n--) {
                if (!isset($plugins[$n])) {
                    continue;
                }
                register_plugin_language($plugins[$n]);
            }
            $emulate_plugins_set = true;
        }

        if ($external_share_groups_config_options || stripos(trim(isset($userinfo[0]["config_options"])), "external_share_groups_config_options=true") !== false) {
            # Apply config override options
            $config_options = trim($userinfo[0]["config_options"] ?? "");

            // We need to get all globals as we don't know what may be referenced here
            override_rs_variables_by_eval($GLOBALS, $config_options, 'usergroup');
        }
    }

    # Special case for anonymous logins.
    # When a valid key is present, we need to log the user in as the anonymous user so they will be able to browse the public links.
    global $anonymous_login;
    if (isset($anonymous_login)) {
        global $username,$baseurl;
        if (is_array($anonymous_login)) {
            foreach ($anonymous_login as $key => $val) {
                if ($baseurl == $key) {
                    $anonymous_login = $val;
                }
            }
        }
        $username = $anonymous_login;
    }

    # Set the 'last used' date for this key
    ps_query("UPDATE external_access_keys SET lastused = now() WHERE resource IN (" . ps_param_insert(count($resources)) . ") AND access_key = ?", array_merge(ps_param_fill($resources, "i"), array("s", $key)));

    return true;
}

/**
* Check access key for a collection. For a featured collection category, the check will be done on all sub featured collections.
*
* @param integer $collection        Collection ID
* @param string  $key               Access key
* @param  boolean $checkresource    Check for resource access key? true by default but required to prevent infinite recursion
*
* @return boolean
*/
function check_access_key_collection($collection, $key, $checkresource = true)
{
    global $anonymous_login;

    if (!is_int_loose($collection)) {
        return false;
    }
    hook("external_share_view_as_internal_override");
    global $external_share_view_as_internal, $baseurl, $baseurl_short, $pagename;

    if ($external_share_view_as_internal && isset($_COOKIE["user"])) {
        $user_select_sql = new PreparedStatementQuery();
        $user_select_sql->sql = "u.session = ?";
        $user_select_sql->parameters = ["s",$_COOKIE["user"]];
        if (validate_user($user_select_sql, false) && !is_authenticated()) {
            // Authenticate the user if not already authenticated so page can appear as internal
            return false;
        }
    }

    $collection = get_collection($collection);
    if ($collection === false) {
        return false;
    }

    $anon_sql = '';
    $anon_params = [];
    if (isset($anonymous_login) && trim($anonymous_login) !== '') {
        $anon_sql    = ' AND u.username != ?';
        $anon_params = ['s', $anonymous_login];
    }

    // Get key info
    $keyinfo = ps_query("
                    SELECT eak.user,
                           u.username,
                           eak.usergroup,
                           eak.expires,
                           eak.upload,
                           eak.password_hash,
                           eak.collection
                      FROM external_access_keys eak
                      LEFT JOIN user u ON eak.user = u.ref
                     WHERE access_key = ? {$anon_sql}
                       AND (expires IS NULL OR expires > now())", array_merge(array("s", $key), $anon_params));

    if (count($keyinfo) == 0) {
        return false;
    }
    $collection_resources = get_collection_resources($collection["ref"]);
    $collection["has_resources"] = (is_array($collection_resources) && !empty($collection_resources) ? 1 : 0);
    $is_featured_collection_category = is_featured_collection_category($collection);

    if (!$is_featured_collection_category && (!$collection["has_resources"] && !(bool)$keyinfo[0]["upload"])) {
        return false;
    }

    // From this point all collections should have resources. For FC categories, its sub FCs will have resources because
    // get_featured_collection_categ_sub_fcs() does the check internally
    $collections = (!$is_featured_collection_category ? array($collection["ref"]) : get_featured_collection_categ_sub_fcs($collection, array("access_control" => false)));

    if ($keyinfo[0]["password_hash"] != "" && PHP_SAPI != "cli") {
        // A share password has been set. Check if user has a valid cookie set
        $share_access_cookie = isset($_COOKIE["share_access"]) ? $_COOKIE["share_access"] : "";
        $check = check_share_password($key, "", $share_access_cookie);
        if (!$check) {
            $url = generateURL($baseurl . "/pages/share_access.php", array("k" => $key,"return_url" => $baseurl . (isset($_SERVER["REQUEST_URI"]) ? urlencode(str_replace($baseurl_short, "/", $_SERVER["REQUEST_URI"])) : "/c=" . $collection["ref"] . "&k=" . $key)));
            redirect($url);
            exit();
        }
    }

    $sql = "UPDATE external_access_keys SET lastused = NOW() WHERE collection = ? AND access_key = ?";

    if (in_array($collection["ref"], array_column($keyinfo, "collection")) && (bool)$keyinfo[0]["upload"] === true) {
        // External upload link -set session to use for creating temporary collection
        $shareopts = array(
            "collection"    => $collection["ref"],
            "usergroup"     => $keyinfo[0]["usergroup"],
            "user"          => $keyinfo[0]["user"],
            );
        upload_share_setup($key, $shareopts);
        return true;
    }

    foreach ($collections as $collection_ref) {
        $resources_alt = hook("GetResourcesToCheck", "", array($collection));
        $resources = (is_array($resources_alt) && !empty($resources_alt) ? $resources_alt : get_collection_resources($collection_ref));
        if (!check_access_key($resources, $key, false)) {
            return false;
        }

        ps_query($sql, array("i", $collection_ref, "s", $key));
    }

    if ($is_featured_collection_category) {
        // Update the last used for the dummy record we have for the featured collection category (ie. no resources since
        // a category contains only collections)
        ps_query($sql, array("i", $collection["ref"], "s", $key));
    }

    return true;
}

/**
 * Generates a unique username for the given name
 *
 * @param string    $name       The user's full name
 * @param string    $email      Optional email address
 *
 * @return string   The username to use
 */
function make_username(string $name, string $email = ""): string
{
    if ($GLOBALS["username_from_email"] && trim($email) !== "") {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $c = ps_value("SELECT COUNT(*) value FROM user WHERE username = ?", ["s",$email], 0);
            if ($c === 0) {
                return trim($email);
            }
        }
        debug("make_username() - Unable to use invalid e-mail address: " . $email);
    }

    # First compress the various name parts
    $s = trim_array(explode(" ", $name));

    $name = $s[count($s) - 1];
    for ($n = count($s) - 2; $n >= 0; $n--) {
        $name = substr($s[$n], 0, 1) . $name;
    }
    $name = safe_file_name(strtolower($name));

    # Create fullname usernames:
    global $user_account_fullname_create;
    if ($user_account_fullname_create) {
        $name = '';

        foreach ($s as $name_part) {
            $name .= '_' . $name_part;
        }

        $name = substr($name, 1);
        $name = safe_file_name($name);
    }

    # Check for uniqueness... append an ever-increasing number until unique.
    $unique = false;
    $num = -1;
    while (!$unique) {
        $num++;
        $c = ps_value("select count(*) value from user where username=?", array("s",($name . (($num == 0) ? "" : $num))), 0);
        $unique = ($c == 0);
    }
    return $name . (($num == 0) ? "" : $num);
}

/**
 * Returns a list of user groups selectable in the registration . The standard user groups are translated using $lang. Custom user groups are i18n translated.
 *
 * @return array
 */
function get_registration_selectable_usergroups()
{
    # Executes query.
    $r = ps_query("select ref,name from usergroup where allow_registration_selection=1 order by name");

    # Translates group names in the newly created array.
    $return = array();
    for ($n = 0; $n < count($r); $n++) {
        $r[$n]["name"] = lang_or_i18n_get_translated($r[$n]["name"], "usergroup-");
        $return[] = $r[$n]; # Adds to return array.
    }

    return $return;
}

/**
 * Give the user full access to the given resource. Used when approving requests.
 *
 * @param  integer $user
 * @param  integer $resource
 * @param  string $expires
 * @return boolean
 */
function open_access_to_user($user, $resource, $expires)
{
    # Delete any existing custom access
    ps_query("delete from resource_custom_access where user = ? and resource = ?", array("i", $user, "i", $resource));

    # Insert new row
    ps_query("insert into resource_custom_access (resource, access, user, user_expires) values (?, '0', ?, ?)", array("i", $resource, "i", $user, "s", $expires == "" ? null : $expires));

    return true;
}

/**
 * Give the user full access to the given resource. Used when approving requests.
 *
 * @param  integer $group
 * @param  integer $resource
 * @param  string $expires
 * @return boolean
 */
function open_access_to_group($group, $resource, $expires)
{
    # Delete any existing custom access
    ps_query("delete from resource_custom_access where usergroup = ? and resource = ?", array("i", $group, "i", $resource));

    # Insert new row
    ps_query("insert into resource_custom_access (resource, access, usergroup, user_expires) values (?, '0', ?, ?)", array("i", $resource, "i", $group, "s", $expires == "" ? null : $expires));

    return true;
}

/**
 * Grants open access to the user list for the specified resource.
 *
 * @param  string $userlist
 * @param  integer $resource
 * @param  string $expires
 * @return void
 */
function resolve_open_access($userlist, $resource, $expires)
{
    global $open_internal_access,$lang;

    $groupids = resolve_userlist_groups_smart($userlist);
    debug("smart_groups: list=" . $groupids);
    if ($groupids != '') {
        $groupids = explode(",", $groupids);
        foreach ($groupids as $group) {
            open_access_to_group($group, $resource, $expires);
        }
        $userlist = remove_groups_smart_from_userlist($userlist);
    }
    if ($userlist != '') {
        $userlist_array = explode(",", $userlist);
        debug("smart_groups: userlist=" . $userlist);
        foreach ($userlist_array as $option) {
            #user
            $userid = ps_value("select ref value from user where username=?", array("s",$option), "");
            if ($userid != "") {
                open_access_to_user($userid, $resource, $expires);
            }
        }
    }
}

/**
 * Remove any user-specific access granted by an 'approve'. Used when declining requests.
 *
 * @param  integer $user
 * @param  integer $resource
 * @return boolean
 */
function remove_access_to_user($user, $resource)
{
    # Delete any existing custom access
    ps_query("delete from resource_custom_access where user = ? and resource = ?", array("i", $user, "i", $resource));

    return true;
}

/**
 * Returns true if a user account exists with e-mail address $email
 *
 * @param  string $email
 * @return boolean
 */
function user_email_exists($email)
{
    return ps_value("SELECT COUNT(*) value FROM user WHERE email LIKE ?", ["s", trim(strtolower($email))], 0) > 0;
}

/**
* Return an array of emails from a list of usernames and email addresses.
* with 'key_required' sibling array preserving the intent of internal/external sharing
*
* @param array $user_list
*
* @return array
*/
function resolve_user_emails($user_list)
{
    global $lang, $user_select_internal;

    $emails_key_required = array();

    foreach ($user_list as $user) {
        $email_details = ps_query("SELECT ref, email, approved, account_expires FROM user WHERE username = ?", ['s', $user]);
        if (
            isset($email_details[0])
            && !(is_null($email_details[0]['account_expires']) || trim($email_details[0]['account_expires']) === '')
            && (time() > strtotime($email_details[0]['account_expires']))
        ) {
            debug('EMAIL: ' . __FUNCTION__ . '() Username ' . $user . ' skipped as their user account has expired.');
            continue;
        }

        // Not a recognised user, if @ sign present, assume e-mail address specified
        if (0 === count($email_details)) {
            if (false === strpos($user, '@') || (isset($user_select_internal) && $user_select_internal)) {
                error_alert(escape("{$lang['couldnotmatchallusernames']}: {$user}"));
                die();
            }

            $emails_key_required['unames'][]       = $user;
            $emails_key_required['emails'][]       = $user;
            $emails_key_required['key_required'][] = true;

            continue;
        }

        // Skip internal, not approved/disabled accounts
        if ($email_details[0]['approved'] != 1) {
            debug('EMAIL: ' . __FUNCTION__ . '() skipping e-mail "' . $email_details[0]['email'] . '" because it belongs to user account which is not approved');
            continue;
        }

        if (!filter_var($email_details[0]['email'], FILTER_VALIDATE_EMAIL)) {
            debug("Skipping invalid e-mail address: " . $email_details[0]['email']);
            continue;
        }

        // Internal unexpired approved user account - add e-mail address from user account
        $emails_key_required['unames'][]       = $user;
        $emails_key_required['emails'][]       = $email_details[0]['email'];
        $emails_key_required['refs'][]         = $email_details[0]['ref'];
        $emails_key_required['key_required'][] = false;
    }

    return $emails_key_required;
}

/**
 * Finds all users with matching email and marks them as having an invalid email
 *
 * @param  string  $email
 * @return boolean
 */
function mark_email_as_invalid(string $email)
{
    if ($email == "") {
        return false;
    }

    $users = get_user_by_email($email);
    $matched_user = false;

    foreach ($users as $user) {
        if (strtolower($email) == strtolower($user["email"])) {
            $matched_user = true;
            ps_query("UPDATE user SET email_invalid = 1 WHERE ref = ?", ["i",$user["ref"]]);
        }
    }

    return $matched_user;
}

/**
 * Checks if the email entered is marked as invalid for any users
 *
 * @param  string $email
 * @return boolean true if email is marked invalid for any users with matching email address
 */
function check_email_invalid(string $email)
{
    if ($email == "") {
        return false;
    }
    $users = get_user_by_email($email);
    $email_invalid = false;

    foreach ($users as $user) {
        # Check email is exact match
        if (strtolower($email) == strtolower($user["email"]) && $user["email_invalid"] == 1) {
            $email_invalid = true;
        }
    }

    return $email_invalid;
}

/**
 * Creates a reset key for password reset e-mails
 *
 * @param  string $username The user's username
 * @return string The reset key
 */
function create_password_reset_key($username)
{
    global $scramble_key;
    $resetuniquecode = make_password();
    $password_reset_hash = hash('sha256', date("Ymd") . md5("RS" . $resetuniquecode . $username . $scramble_key));
    ps_query("update user set password_reset_hash = ? where username = ?", array("s", $password_reset_hash, "s", $username));
    return substr(hash('sha256', date("Ymd") . $password_reset_hash . $username . $scramble_key), 0, 15);
}

/**
 * For anonymous access - a unique session key to identify the user (e.g. so they can still have their own collections)
 *
 * @param  boolean $create Create one if it doesn't already exist
 * @return mixed    False on failure, the key on success
 */
function get_rs_session_id($create = false)
{
    global $baseurl, $anonymous_login, $usergroup, $rs_session;
    // Note this is not a PHP session, we are using this to create an ID so we can distinguish between anonymous users or users accessing external upload links
    $existing_session = (string) $rs_session !== "" ? $rs_session : ($_COOKIE["rs_session"] ?? "");
    if ($existing_session != "") {
        if (!headers_sent()) {
            rs_setcookie("rs_session", $existing_session, 7, "", "", substr($baseurl, 0, 5) == "https", true); // extend the life of the cookie
        }
        return $existing_session;
    }
    if ($create) {
        // Create a new ID - numeric values only so we can search for it easily
        $rs_session = rand();
        global $baseurl;
        if (!headers_sent()) {
            rs_setcookie("rs_session", $rs_session, 7, "", "", substr($baseurl, 0, 5) == "https", true);
        }

        if (!upload_share_active()) {
            if (is_array($anonymous_login)) {
                foreach ($anonymous_login as $key => $val) {
                    if ($baseurl == $key) {
                        $anonymous_login = $val;
                    }
                }
            }

            $valid = ps_query("select ref, usergroup, account_expires from user where username = ?", array("s", $anonymous_login));

            if (count($valid) >= 1) {
                // setup_user hasn't been called yet, we just need the usergroup
                $usergroup = $valid[0]["usergroup"];

                // Log this in the daily stats
                daily_stat("User session", $valid[0]["ref"]);
            }
        }

        return $rs_session;
    }
    return false;
}

/**
 * Returns an array of users (refs and emails) for use when sending email notifications (messages that in the past went
 *  to $email_notify, which can be emulated by using $email_notify_usergroups)
 *
 * Can be passed a specific user type or an array of permissions
 * Types supported:-
 *      SYSTEM_ADMIN
 *      RESOURCE_ACCESS
 *      RESEARCH_ADMIN
 *      USER_ADMIN
 *      RESOURCE_ADMIN
 *
 * @param   string      $userpermission     Permission string
 * @param   int|null    $usergroup          Optional id of usergroup to find notification users for e.g. the parent group of
 *                                          new user or as defined in $usergroup_approval_mappings
 *
 * @return array
 */
function get_notification_users($userpermission = "SYSTEM_ADMIN", $usergroup = null)
{
    global $notification_users_cache, $email_notify_usergroups;

    if (is_null($usergroup)) {
        global $usergroup;
    }

    $userpermissionindex = is_array($userpermission) ? implode("_", $userpermission) : $userpermission;
    if (isset($notification_users_cache[$userpermissionindex])) {
        return $notification_users_cache[$userpermissionindex];
    }

    if (is_array($email_notify_usergroups) && count($email_notify_usergroups) > 0) {
        // If email_notify_usergroups is set we use these over everything else, as long as they have an email address set
        $notification_users_cache[$userpermissionindex] = ps_query("select ref, email, lang, usergroup from user where usergroup in (" . ps_param_insert(count($email_notify_usergroups)) . ") and email <> '' AND approved = 1 AND (account_expires IS NULL OR account_expires > NOW())", ps_param_fill($email_notify_usergroups, "i"));
        return $notification_users_cache[$userpermissionindex];
    }

    if (!is_array($userpermission)) {
        // We have been passed a specific type of administrator to find
        switch ($userpermission) {
            case "USER_ADMIN":
                $sql_approver_groups = '';
                $sql_approver_groups_params = array();
                global $usergroup_approval_mappings;

                if (is_numeric($usergroup)) {
                    $sql_approver_groups = " and ((FIND_IN_SET(BINARY 'U', ug.permissions) = 0 OR (FIND_IN_SET('permissions', ug.inherit_flags) <> 0 AND FIND_IN_SET(BINARY 'U', pg.permissions) = 0)) or ug.ref = (select parent from usergroup where ref = ?)) ";
                    $sql_approver_groups_params = array("i", $usergroup);
                    if (isset($usergroup_approval_mappings)) {
                        // Determine which user groups should be excluded from notifications. If mapping exists it must be valid to send notification.
                        $approver_groups = array_keys($usergroup_approval_mappings);
                        $defined_approvers_for_group = get_usergroup_approvers($usergroup);
                        $affective_approver_groups = array_diff($approver_groups, $defined_approvers_for_group);
                        if (count($affective_approver_groups) > 0) {
                            $sql_approver_groups .= 'and ug.ref not in (' . ps_param_insert(count($affective_approver_groups)) . ')';
                            $sql_approver_groups_params = array_merge($sql_approver_groups_params, ps_param_fill($affective_approver_groups, "i"));
                        }
                    }
                }

            // Return all users in groups with u permissions AND either no 'U' restriction, or with 'U' but in appropriate group
                $notification_users_cache[$userpermissionindex] = ps_query("select u.ref, u.email, u.lang, u.usergroup from usergroup ug join user u on u.usergroup = ug.ref left join usergroup pg on ug.parent = pg.ref where (FIND_IN_SET(BINARY 'u', ug.permissions) <> 0 OR (FIND_IN_SET('permissions', ug.inherit_flags) <> 0 AND FIND_IN_SET(BINARY 'u', pg.permissions) <> 0)) and u.ref <> '' and u.approved = 1 AND (u.account_expires IS NULL OR u.account_expires > NOW())" . $sql_approver_groups, $sql_approver_groups_params);
                return $notification_users_cache[$userpermissionindex];
            break;

            case "RESOURCE_ACCESS":
            // Notify users who can grant access to resources, get all users in groups with R permissions without Rb permissions
                $notification_users_cache[$userpermissionindex] =
                ps_query("select u.ref, u.email, u.usergroup from usergroup ug 
                        join user u on u.usergroup=ug.ref 
                        left join usergroup pg on ug.parent = pg.ref 
                        where (FIND_IN_SET('permissions', coalesce(ug.inherit_flags,'')) = 0 
                                AND FIND_IN_SET(BINARY 'R', ug.permissions) <> 0 
                                AND FIND_IN_SET(BINARY 'Rb', ug.permissions) = 0) 
                        OR (FIND_IN_SET('permissions', coalesce(ug.inherit_flags,'')) <> 0 
                                AND FIND_IN_SET(BINARY 'R', pg.permissions) <> 0 
                                AND FIND_IN_SET(BINARY 'Rb', pg.permissions) = 0) 
                        AND u.approved=1 AND (u.account_expires IS NULL OR u.account_expires > NOW())");
                return $notification_users_cache[$userpermissionindex];
            break;

            case "RESEARCH_ADMIN":
            // Notify research admins, get all users in groups with r permissions
                $notification_users_cache[$userpermissionindex] = ps_query("select u.ref, u.email, u.usergroup from usergroup ug join user u on u.usergroup=ug.ref left join usergroup pg on ug.parent = pg.ref where (FIND_IN_SET(BINARY 'r', ug.permissions) <> 0 OR (FIND_IN_SET('permissions', ug.inherit_flags) <> 0 AND FIND_IN_SET(BINARY 'r', pg.permissions) <> 0)) AND u.approved=1 AND (u.account_expires IS NULL OR u.account_expires > NOW())");
                return $notification_users_cache[$userpermissionindex];
            break;

            case "RESOURCE_ADMIN":
            // Get all users in groups with t and e0 permissions
                $notification_users_cache[$userpermissionindex] = ps_query("select u.ref, u.email, u.usergroup from usergroup ug join user u on u.usergroup=ug.ref left join usergroup pg on ug.parent = pg.ref where (FIND_IN_SET(BINARY 't', ug.permissions) <> 0 OR (FIND_IN_SET('permissions', ug.inherit_flags) <> 0 AND FIND_IN_SET(BINARY 't', pg.permissions) <> 0)) AND (FIND_IN_SET(BINARY 'e0', ug.permissions) OR (FIND_IN_SET('permissions', ug.inherit_flags) <> 0 AND FIND_IN_SET(BINARY 'e0', pg.permissions))) and u.approved=1 AND (u.account_expires IS NULL OR u.account_expires > NOW())");
                return $notification_users_cache[$userpermissionindex];
            break;

            case "SYSTEM_ADMIN":
            default:
            // Get all users in groups with a permission (default if incorrect admin type has been passed)
                $notification_users_cache[$userpermissionindex] = ps_query("select u.ref, u.email, u.usergroup from usergroup ug join user u on u.usergroup=ug.ref left join usergroup pg on ug.parent = pg.ref where (FIND_IN_SET(BINARY 'a', ug.permissions) <> 0 OR (FIND_IN_SET('permissions', ug.inherit_flags) <> 0 AND FIND_IN_SET(BINARY 'a', pg.permissions) <> 0)) AND u.approved=1 AND (u.account_expires IS NULL OR u.account_expires > NOW())");
                return $notification_users_cache[$userpermissionindex];
            break;
        }
    } else {
        // An array has been passed, find all users with these permissions
        $condition = "";
        $condition_sql_params = array();
        foreach ($userpermission as $permission) {
            if ($condition != "") {
                $condition .= " and ";
            }
            $condition .= "find_in_set(binary ?, ug.permissions) <> 0 AND u.approved = 1 AND (u.account_expires IS NULL OR u.account_expires > NOW())";
            $condition_sql_params = array_merge($condition_sql_params, array("s", $permission));
        }
        $notification_users_cache[$userpermissionindex] = ps_query("select u.ref, u.email, u.usergroup from usergroup ug join user u on u.usergroup = ug.ref where $condition", $condition_sql_params);
        return $notification_users_cache[$userpermissionindex];
    }
}

/**
 * Validates the user entered antispam code
 *
 * @param  string               $spamcode The antispam hash to check against
 * @param  string               $usercode The antispam code the user entered
 * @param  string               $spamtime The antispam timestamp
 * @return boolean              Return true if the code was successfully validated, otherwise false
 */
function verify_antispam($spamcode = "", $usercode = "", $spamtime = 0)
{
    global $scramble_key, $password_brute_force_delay;

    $data_valid = ($usercode !== '' || $spamcode !== '' || (int) $spamtime > 0);
    $honeypot_intact = (isset($_REQUEST['antispam_user_code']) && $_REQUEST['antispam_user_code'] === '');
    $antispam_code_valid = ($spamcode === hash("SHA256", strtoupper($usercode) . $scramble_key . $spamtime));
    $previous_hash = in_array(
        md5($usercode . $spamtime),
        ps_array("SELECT unique_hash AS `value` FROM user WHERE unique_hash IS NOT null", array(), '')
    );

    if ($data_valid && $honeypot_intact && $antispam_code_valid && !$previous_hash) {
        return true;
    }

    if (!$antispam_code_valid) {
        $dbgr = 'invalid code entered';
    } elseif ($previous_hash) {
        $dbgr = 'code has previously been used';
    } else {
        $dbgr = 'unknown';
    }
    debug(sprintf('antispam failed: Reason: %s. IP: %s', $dbgr, get_ip()));

    sleep($password_brute_force_delay);
    return false;
}

/**
* Check that access for given external share key is correct
*
* @param array  $key       External access key
* @param string $password  Share password to check
* @param string $cookie    Share session cookie that has been set previously
*
* @return boolean
*/
function check_share_password($key, $password, $cookie)
{
    global $scramble_key, $baseurl;
    $sharehash = ps_value("SELECT password_hash value FROM external_access_keys WHERE access_key=?", array("s",$key), "");
    if ($password != "") {
        $hashcheck = hash('sha256', $key . $password . $scramble_key);
        $valid = $hashcheck === $sharehash;
        debug("checking share access password for key: " . $key);
    } else {
        $hashcheck = hash('sha256', date("Ymd") . $key . $sharehash . $scramble_key);
        $valid = $hashcheck === $cookie;
        debug("checking share access cookie for key: " . $key);
    }

    if (!$valid) {
        debug("failed share access password for key: " . $key);
        return false;
    }

    if ($cookie == "") {
        // Set a cookie for this session so password won't be required again
        $sharecookie = hash('sha256', date("Ymd") . $key . $sharehash . $scramble_key);
        rs_setcookie("share_access", $sharecookie, 0, "", "", substr($baseurl, 0, 5) == "https", true);
    }

    return true;
}

/**
* Offset a datetime to user local time zone
*
* IMPORTANT: the offset is fixed, there is no calculation for summertime!
*
* @param string $datetime A date/time string. @see https://www.php.net/manual/en/datetime.formats.php
* @param string $format   The format of the outputted date string. @see https://www.php.net/manual/en/function.date.php
*
* @return string The date in the specified format
*/
function offset_user_local_timezone($datetime, $format)
{
    global $user_local_timezone;

    $server_dtz = new DateTimeZone(date_default_timezone_get());
    $user_local_dtz = new DateTimeZone($user_local_timezone);

    // Create two DateTime objects that will contain the same Unix timestamp, but have different timezones attached to them
    $server_dt = new DateTime($datetime, $server_dtz);
    $user_local_dt = new DateTime($datetime, $user_local_dtz);

    $time_offset = $user_local_dt->getOffset() - $server_dt->getOffset();

    $user_local_dt->add(DateInterval::createFromDateString((string) $time_offset . ' seconds'));

    return $user_local_dt->format($format);
}

/**
 * Returns whether a user is anonymous or not
 *
 * @return boolean
 */
function checkPermission_anonymoususer()
{
    global $baseurl, $anonymous_login, $username, $usergroup;

    return isset($anonymous_login)
        && (
            (is_string($anonymous_login) && '' != $anonymous_login && $anonymous_login == $username)
            || (
                is_array($anonymous_login)
                && array_key_exists($baseurl, $anonymous_login)
                && $anonymous_login[$baseurl] == $username
                )
            );
}

/**
 * Does the current user have the ability to administer the dash (the tiles for all users)
 *
 * @return boolean
 */
function checkPermission_dashadmin()
{
    return (checkperm("h") && !checkperm("hdta")) || (checkperm("dta") && !checkperm("h"));
}

/**
 * Can the user manage their own dash tiles.
 *
 * @return boolean
 */
function checkPermission_dashuser()
{
    return !checkperm("dtu");
}

/**
 * Can the user manage their dash?
 *
 * Logic:
 * Home_dash is on, And not the Anonymous user with default dash, And (Dash tile user (Not with a managed dash) || Dash Tile Admin)
 *
 * @return boolean
 */
function checkPermission_dashmanage()
{
    global $managed_home_dash;
    return (!checkPermission_anonymoususer()) && (!$managed_home_dash && (checkPermission_dashuser() || checkPermission_dashadmin()));
}

/**
 * Can the user create tiles?
 *
 * Logic:
 * Home_dash is on, And not Anonymous use, And (Dash tile user (Not with a managed dash) || Dash Tile Admin)
 *
 * @return boolean
 */
function checkPermission_dashcreate()
{
    global $managed_home_dash, $system_read_only;
    return !checkPermission_anonymoususer()
            &&
            !$system_read_only
            &&
                (
                    (!$managed_home_dash && (checkPermission_dashuser() || checkPermission_dashadmin()))
                ||
                    ($managed_home_dash && checkPermission_dashadmin())
                );
}

/**
 * Check that the user has the $perm permission
 *
 * @param  string $perm
 * @return boolean Do they have the permission?
 */
function checkperm($perm)
{
    #
    global $userpermissions;
    if (!(isset($userpermissions))) {
        return false;
    }
    return in_array($perm, $userpermissions);
}

/**
 * Check if the current user is allowed to edit user with passed reference
 *
 * @param  integer $user The user to be edited
 * @return boolean
 */
function checkperm_user_edit($user)
{
    if (!checkperm('u')) {    // does not have edit user permission
        return false;
    }
    if (!is_array($user)) {       // allow for passing of user array or user ref to this function.
        $user = get_user($user);
    }
    $editusergroup = $user['usergroup'];
    global $usergroup;
    $approver_groups = get_approver_usergroups($usergroup);

    if ((!checkperm('U') && count($approver_groups) == 0) || $editusergroup == '') {    // no user editing restriction, or is not defined so return true
        return true;
    }

    // Get all the groups that the logged in user can manage
    $sql = "SELECT `ref` AS  'value' FROM `usergroup` WHERE ";
    $sql_params = array();
    if (count($approver_groups) > 0) {
        $sql .= "ref in (" . ps_param_insert(count($approver_groups)) . ") or ";
        $sql_params = array_merge($sql_params, ps_param_fill($approver_groups, "i"));
    }
    $sql .= "`ref` = ? OR FIND_IN_SET(?, parent)";
    $sql_params = array_merge($sql_params, array("i", $usergroup, "i", $usergroup));

    $validgroups = ps_array($sql, $sql_params);

    // Return true if the target user we are checking is in one of the valid groups
    return in_array($editusergroup, $validgroups);
}

/**
 * Check if the current user has sufficient permissions to log in as the specified user
 *
 * The regex used is to check if the a permission is present in the permission string of the target user
 *
 * @param  mixed $user   Either a user reference or user array
 * @return bool
 */
function checkperm_login_as_user($user): bool
{
    if (!checkperm('u')) {    // does not have edit user permission
        return false;
    }
    if (!is_array($user)) {       // allow for passing of user array or user ref to this function.
        $user = get_user($user);
    }

    if (!checkperm("a") && preg_match("/(?:^|\\W|,)a(?:$|\\W|,)/", (string) $user['permissions'])) {
        // Target user has 'a' permission but current user does not
        return false;
    }

    return true;
}

/**
* Determine if this is an internal share access request
*
* @return boolean
*/
function internal_share_access()
{
    global $k, $external_share_view_as_internal;
    return $k != "" && $external_share_view_as_internal && is_authenticated();
}

/**
 * Save changes to a usergroup or create usergroup
 *
 * @param  int              $ref    Group ref. Set to 0 to create a new group
 * @param  array            $groupoptions array of options to set for group in the form array("columnname" => $value)
 *
 * @return mixed bool|int   True to indicate existing group has been updated or ID of newly created group
 */
function save_usergroup($ref, $groupoptions)
{
    $validcolumns = array(
        "name",
        "permissions",
        "parent",
        "search_filter",
        "search_filter_id",
        "edit_filter",
        "edit_filter_id",
        "derestrict_filter",
        "derestrict_filter_id",
        "resource_defaults",
        "config_options",
        "welcome_message",
        "ip_restrict",
        "request_mode",
        "allow_registration_selection",
        "inherit_flags",
        "download_limit",
        "download_log_days"
        );

    $sqlcols = array();
    $sqlvals = array();
    $n = 0;
    foreach ($validcolumns as $column) {
        if (isset($groupoptions[$column])) {
            $sqlcols[$n] = $column;
            $sqlvals[$n] = $groupoptions[$column];
            $n++;
        }
    }

    if ($ref > 0) {
        $sqlsetvals = array();
        for ($n = 0; $n < count($sqlcols); $n++) {
            $sqlsetvals[] = $sqlcols[$n] . " = ?";
        }
        $sql = "UPDATE usergroup SET " . implode(",", $sqlsetvals) . " WHERE ref = ?";
        ps_query($sql, array_merge(ps_param_fill($sqlvals, "s"), array("i", (int) $ref)));
        clear_query_cache('usergroup');
        return true;
    } else {
        $sqlsetvals = array();
        for ($n = 0; $n < count($sqlcols); $n++) {
            $sqlsetvals[] = $sqlcols[$n] . " = ?";
        }
        $sql = "INSERT INTO usergroup (" . implode(",", $sqlcols) . ") VALUES (" . ps_param_insert(count($sqlvals)) . ")";
        ps_query($sql, ps_param_fill($sqlvals, "s"));
        clear_query_cache('usergroup');
        return sql_insert_id();
    }
}

/**
 * Copy the permissions string from another usergroup
 *
 * @param  int $src_id    The group ID to copy from
 * @param  int $dst_id    The group ID to copy to
 * @return mixed          bool|int True to indicate existing group has been updated or ID of newly created group
 */
function copy_usergroup_permissions(int $src_id, int $dst_id)
{
    $src_group = get_usergroup($src_id);
    $dst_group = get_usergroup($dst_id);

    if (!$src_group || !$dst_group) {
        return false;
    }

    $dst_group = ["permissions" => $src_group["permissions"]];
    return save_usergroup($dst_id, $dst_group);
}

/**
 * Set user's profile image and profile description (bio). Used by ../pages/user/user_profile_edit.php to setup user's profile.
 *
 * @param  int     $user_ref         User id of user who's profile is being set.
 * @param  string  $profile_text     User entered profile description text (bio).
 * @param  string  $image_path       Path to temp file created if user chose to upload a profile image.
 *
 * @return boolean     If an error is encountered saving the profile image return will be false.
 */
function set_user_profile($user_ref, $profile_text, $image_path)
{
    global $storagedir,$imagemagick_path, $scramble_key, $config_windows;

    # Check for presence of filestore/user_profiles directory - if it doesn't exist, create it.
    if (!is_dir($storagedir . '/user_profiles')) {
        mkdir($storagedir . '/user_profiles', 0777);
    }

    # Locate imagemagick.
    $convert_fullpath = get_utility_path("im-convert");
    if (!$convert_fullpath) {
        debug("ERROR: Could not find ImageMagick 'convert' utility at location '$imagemagick_path'.");
        return false;
    }

    if ($image_path != "" && file_exists($image_path)) {
        # Work out the extension.
        $extension = explode(".", $image_path);
        $extension = trim(strtolower($extension[count($extension) - 1]));
        if ($extension != 'jpg' && $extension != 'jpeg') {
            return false;
        }

        # Remove previous profile image.
        delete_profile_image($user_ref);

        # Create profile image filename .
        $profile_image_name = $user_ref . "_" . md5($scramble_key . $user_ref . time()) . "." . $extension;
        $profile_image_path = $storagedir . '/user_profiles' . '/' . $profile_image_name;

        # Create profile image - cropped to square from centre.
        $command = $convert_fullpath . ' ' . escapeshellarg((!$config_windows && strpos($image_path, ':') !== false ? $extension . ':' : '') . $image_path) . " -resize 400x400 -thumbnail 200x200^^ -gravity center -extent 200x200" . " " . escapeshellarg($profile_image_path);
        run_command($command);

        # Store reference to user image.
        ps_query("update user set profile_image = ? where ref = ?", array("s", $profile_image_name, "i", $user_ref));

        # Remove temp file.
        if (file_exists($profile_image_path)) {
            unlink($image_path);
        } else {
            return false;
        }
    }

    # Update user to set user.profile
    ps_query("update user set profile_text = ? where ref = ?", array("s", substr(strip_tags($profile_text), 0, 500), "i", $user_ref));

    return true;
}

/**
 * Delete a user's profile image. This will first remove the file and then update the db to clear the existing value.
 *
 * @param  mixed  $user_ref   User id of the user who's profile image is to be deleted.
 *
 * @return void
 */
function delete_profile_image($user_ref)
{
    global $storagedir;

    $profile_image_name = ps_value("select profile_image value from user where ref = ?", array("i",$user_ref), "");

    if ($profile_image_name != "") {
        $path_to_file = $storagedir . '/user_profiles' . '/' . $profile_image_name;

        if (file_exists($path_to_file)) {
            unlink($path_to_file);
        }

        ps_query("update user set profile_image = '' where ref = ?", array("i", $user_ref));
    }
}

/**
 * Generate the url to the user's profile image. Fetch the url by the user's id or by the profile image filename.
 *
 * @param  int     $user_ref   User id of the user who's profile image is requested.
 * @param  string  $by_image   The filename of the profile image to fetch having been collected from the db separately: user.profile_image
 *
 * @return string     The url to the user's profile image if available or blank if not set.
 */
function get_profile_image($user_ref = "", $by_image = "")
{
    global $storagedir, $storageurl, $baseurl;

    if (is_dir($storagedir . '/user_profiles')) {
        # Only check the db if the profile image name has not been provided.
        if ($by_image == "" && $user_ref != "") {
            $profile_image_name = ps_value("select profile_image value from user where ref = ?", array("i",$user_ref), "");
        } else {
            $profile_image_name = $by_image;
        }

        if ($profile_image_name != "") {
            return $storageurl . '/user_profiles/' . $profile_image_name;
        } else {
            return "";
        }
    }
    return "";
}

/**
 * Return user profile for a defined user.
 *
 * @param  int     $user_ref   User id to fetch profile details for.
 *
 * @return string     Profile details for the requested user.
 */
function get_profile_text($user_ref)
{
    return ps_value("select profile_text value from user where ref = ?", array("i",$user_ref), "");
}

/**
* load language files for all users that need to be notified into an array - use for message and email notification
* load in default language strings first and then overwrite with preferred language strings
*
* @param  array $languages - array of language strings
* @return array $language_strings_all
* */
function get_languages_notify_users(array $languages = array())
{
    global $applicationname,$defaultlanguage;

    $language_strings_all   = array();
    $lang_file_en           = __DIR__ . "/../languages/en.php";
    $lang_file_default      = __DIR__ . "/../languages/" . safe_file_name($defaultlanguage) . ".php";

     // add en and default language lang array values - always need en as some lang arrays do not contain all strings
    include $lang_file_en;
    $language_strings_all["en"] = $lang;

    include $lang_file_default;
    $language_strings_all[$defaultlanguage] = $lang;

    // remove en and default language from array of languages to use
    $langs2remove = array_unique(array("en", $defaultlanguage));
    foreach ($langs2remove as $lang2remove) {
        if (in_array($lang2remove, $languages)) {
            unset($languages[$lang2remove]);
        }
    }

    // load lang array values into array for each language
    foreach ($languages as $language) {
        $lang = array();

        // include en and default lang array values
        include $lang_file_en;
        include $lang_file_default;

        $lang_file = __DIR__ . "/../languages/" . safe_file_name($language) . ".php";

        if (file_exists($lang_file)) {
            include $lang_file;
        }

        $language_strings_all[$language] = $lang; // append $lang array
    }

    return $language_strings_all;
}

/**
 * Generate upload URL - alters based on $upload_then_edit setting and external uploads
 *
 * @param  string $collection - optional collection
 * @param  string $accesskey - used for external users
 * @return string
 */
function get_upload_url($collection = "", $k = "")
{
    global $upload_then_edit, $userref, $baseurl,$terms_upload;
    if ($upload_then_edit || $k != "" || !isset($userref)) {
        $url = generateURL($baseurl . "/pages/upload_batch.php", array("k" => $k,"collection_add" => $collection));
    } elseif (isset($userref)) {
        $url = generateURL($baseurl . "/pages/edit.php", array("ref" => "-" . $userref,"collection_add" => $collection));
    }
    if ($terms_upload && !check_upload_terms((int) $collection, $k)) {
        $url = generateURL($baseurl . "/pages/terms.php", array("k" => $k,"collection" => $collection,"url" => $url,"upload" => true));
    }
    return $url;
}

/**
 * Used to emulate system users when accessing system anonymously or via external shares
 * Sets global array such as $userpermissions, $username and sets any relevant config options
 *
 * @param  int $user            User ID
 * @param  int $usergroup       usergroup ID
 * @return void
 */
function emulate_user($user, $usergroup = "")
{
    debug_function_call("emulate_user", func_get_args());
    global $userref, $userpermissions, $userrequestmode, $usersearchfilter, $userresourcedefaults;
    global $external_share_groups_config_options, $emulate_plugins_set, $plugins;
    global $username,$baseurl, $anonymous_login, $upload_link_workflow_state;

    if (!is_numeric($user) || ($usergroup != "" && !is_numeric($usergroup))) {
        return false;
    }

    $groupjoin = "u.usergroup = g.ref";
    $permissionselect = "g.permissions";
    $groupjoin_params = array();
    if ($usergroup != "") {
        # Select the user group from the access key instead.
        $groupjoin = "g.ref = ? LEFT JOIN usergroup pg ON g.parent = pg.ref";
        $groupjoin_params = array("i", $usergroup);
        $permissionselect = "if(find_in_set('permissions', g.inherit_flags) AND pg.permissions IS NOT NULL, pg.permissions, g.permissions) permissions";
    }
    $userinfo = ps_query(
        "select g.ref usergroup," . $permissionselect . " , g.search_filter, g.config_options, g.search_filter_id, g.derestrict_filter_id, u.search_filter_override,
        u.search_filter_o_id, g.resource_defaults from user u join usergroup g on $groupjoin where u.ref = ?",
        array_merge($groupjoin_params, array("i", $user))
    );
    if (count($userinfo) > 0) {
        $usergroup = $userinfo[0]["usergroup"]; # Older mode, where no user group was specified, find the user group out from the table.
        $userpermissions = explode(",", $userinfo[0]["permissions"] ?? "");

        if (upload_share_active()) {
            // Disable some permissions for added security
            $addperms = array('D','b','p');
            $removeperms = array('v','q','i','A','h','a','t','r','m','u','exup');

            // add access to the designated workflow state
            $addperms[] = "e" . $upload_link_workflow_state;

            $userpermissions = array_merge($userpermissions, $addperms);
            $userpermissions = array_diff($userpermissions, $removeperms);
            $userpermissions = array_values($userpermissions);
            $userref = $user;
        }

        if (isset($userinfo[0]["resource_defaults"])) {
            $userresourcedefaults = $userinfo[0]["resource_defaults"];
        }

        if (isset($userinfo[0]["search_filter_o_id"]) && is_numeric($userinfo[0]["search_filter_o_id"]) && $userinfo[0]['search_filter_o_id'] > 0) {
            // User search filter override
            $usersearchfilter = $userinfo[0]["search_filter_o_id"];
        } elseif (isset($userinfo[0]["search_filter_id"]) && is_numeric($userinfo[0]["search_filter_id"]) && $userinfo[0]['search_filter_id'] > 0) {
            // Group search filter
            $usersearchfilter = $userinfo[0]["search_filter_id"];
        }

        if (hook("modifyuserpermissions")) {
            $userpermissions = hook("modifyuserpermissions");
        }
        $userrequestmode = 0; # Always use 'email' request mode for external users

        # Load any plugins specific to the group of the sharing user, but only once as may be checking multiple keys
        if ($emulate_plugins_set !== true) {
            $enabled_plugins = (ps_query("SELECT name, enabled_groups, config, config_json FROM plugins WHERE inst_version >= 0 AND length(enabled_groups) > 0 ORDER BY priority"));
            foreach ($enabled_plugins as $plugin) {
                $s = explode(",", $plugin['enabled_groups']);
                if (in_array($usergroup, $s) && !in_array($plugin['name'], $plugins)) {
                    include_plugin_config($plugin['name'], $plugin['config'], $plugin['config_json']);
                    register_plugin($plugin['name']);
                    $plugins[] = $plugin['name'];
                }
            }
            foreach (array_reverse($plugins) as $plugin) {
                register_plugin_language($plugin);
            }
            $emulate_plugins_set = true;
        }

        if ($external_share_groups_config_options || stripos(trim(isset($userinfo[0]["config_options"])), "external_share_groups_config_options=true") !== false) {
            # Apply config override options
            $config_options = trim($userinfo[0]["config_options"] ?? "");

            // We need to get all globals as we don't know what may be referenced here
            override_rs_variables_by_eval($GLOBALS, $config_options, 'usergroup');
        }
    }

    # Special case for anonymous logins.
    # When a valid key is present, we need to log the user in as the anonymous user so they will be able to browse the public links.
    if (isset($anonymous_login)) {
        if (is_array($anonymous_login)) {
            foreach ($anonymous_login as $key => $val) {
                if ($baseurl == $key) {
                    $anonymous_login = $val;
                }
            }
        }
        $username = $anonymous_login;
    }
}

function is_authenticated()
{
    global $is_authenticated;
    return isset($is_authenticated) && $is_authenticated;
}

/**
 * Returns an array of the user groups the supplied user group acts as an approver for.
 * Uses config $usergroup_approval_mappings.
 *
 * @param  int  $usergroup   Approving user group
 *
 * @return  array   Array of subordinate user group ids.
 */
function get_approver_usergroups($usergroup = "")
{
    if ($usergroup == "" || !is_numeric($usergroup)) {
        return array();
    }

    global $usergroup_approval_mappings;

    $approval_groups = array();
    if (
        isset($usergroup_approval_mappings)
        && array_key_exists((int)$usergroup, $usergroup_approval_mappings)
    ) {
           $approval_groups = $usergroup_approval_mappings[(int)$usergroup];
    }

    return $approval_groups;
}

/**
 * Returns an array of user groups who act as user request approvers to the user group supplied.
 * Uses config $usergroup_approval_mappings.
 *
 * @param  int  $usergroup   Subordinate user group who's approval user group we need to find.
 *
 * @return  array   Approval user group ids for supplied user group. Likely one value but its possible to have multiple approving groups.
 */
function get_usergroup_approvers($usergroup = "")
{
    if ($usergroup == "" || !is_numeric($usergroup)) {
        return array();
    }

    global $usergroup_approval_mappings;

    $approver_groups = array();
    if (isset($usergroup_approval_mappings)) {
        foreach ($usergroup_approval_mappings as $approver => $groups) {
            if (in_array($usergroup, $groups)) {
                $approver_groups[] = $approver;
            }
        }
    }

    return $approver_groups;
}

/**
 * Retrieve all user records in groups with/without the specified permissions
 *
 * @param  array $permissions      array of permission strings to check
 *
 * @return array Matching user records (only returns a subset of columns)
 *
 * Note that this can't use a straight FIND_IN_SET for permissions since that is case insensitive
 *
 **/
function get_users_by_permission(array $permissions)
{
    global $usergroup;
    if (!(checkperm("a") || checkperm("u"))) {
        return [];
    }

    $groupsql_filter = "";
    $groupsql_params = [];
    if (checkperm("U")) {
        # Only return users in children groups to the user's group
        $groupsql_filter = "WHERE (g.ref = ? or find_in_set(?, g.parent))";
        $groupsql_params = array("i", $usergroup, "i", $usergroup);
    }

    $usergroups = ps_query(
        "SELECT g.ref,
                                   IF(FIND_IN_SET('permissions',g.inherit_flags) AND pg.permissions IS NOT NULL,pg.permissions,g.permissions) permissions
                              FROM usergroup g
                         LEFT JOIN usergroup AS pg ON g.parent=pg.ref " .
                                    $groupsql_filter,
        $groupsql_params
    );

    $validgroups = [];
    foreach ($usergroups as $usergroup) {
        $groupperms = explode(",", (string) $usergroup["permissions"]);
        if (count(array_diff($permissions, $groupperms)) == 0) {
            $validgroups[] = $usergroup["ref"];
        }
    }
    if (count($validgroups) == 0) {
        return [];
    }

    $r = ps_query("SELECT " . columns_in('user', 'u') . ", IF(FIND_IN_SET('permissions',g.inherit_flags) AND pg.permissions IS NOT NULL,pg.permissions,g.permissions) permissions, g.name groupname, g.ref groupref, g.parent groupparent FROM user u LEFT OUTER JOIN usergroup g ON u.usergroup = g.ref LEFT JOIN usergroup AS pg ON g.parent=pg.ref WHERE g.ref IN (" . ps_param_insert(count($validgroups)) . ") ORDER BY username", ps_param_fill($validgroups, "i"));

    $return = [];
    for ($n = 0; $n < count($r); $n++) {
        # Translates group names in the newly created array.
        $r[$n]["groupname"] = lang_or_i18n_get_translated($r[$n]["groupname"], "usergroup-");

        $return[] = array_filter($r[$n], function ($k) {
            return in_array($k, ["ref","username","fullname","email","groupname","usergroup","approved","comments","simplesaml_custom_attributes","origin","profile_image","profile_text","last_ip","account_expires","created","last_active"]);
        }, ARRAY_FILTER_USE_KEY);
    }

    return $return;
}

/**
 * Determine whether user is anonymous user
 */
function is_anonymous_user(): bool
{
    global $anonymous_login, $username;
    return isset($anonymous_login) && trim($anonymous_login) !== '' && $username == $anonymous_login;
}

/**
 * Retrieve all user records with the user preference specified
 *
 * @param  string $preference   Preference to check
 * @param  string $value        Preference value to check for
 *
 * @return array                Array of user refs with the preference set as specified
 *
 *
 **/
function get_users_by_preference(string $preference, string $value): array
{
    $sql = "SELECT up.user value
              FROM user_preferences up 
        RIGHT JOIN user u 
                ON u.ref=up.user
             WHERE u.approved=1
               AND parameter = ? 
               AND value = ?";
    $params = ["s",$preference,"s", $value];

    return ps_array($sql, $params);
}

/**
 * Get the default notification workflow states for the current user. Used by setup_user() and get_user_actions() if no user preference has been set
 *
 * @return array    Array of workflow state references
 *
 */
function get_default_notify_states(): array
{
    $default_notify_states = [];
    // Add action for users who can submit 'pending submission' resources for review
    if (checkperm("e-2") && checkperm("e-1") && checkperm('d')) {
        $default_notify_states[] = -2;
    }
    if (checkperm("e-1") && checkperm("e0")) {
        // Add action for users who can make pending resources active
        $default_notify_states[] = -1;
    }
    return $default_notify_states;
}

/**
 * Generate a temporary download key for user. Used to enable temporary resource access to a file via download.php so that API can access resources after calling get_resource_path()
 *
 * @param int     $user         User ID
 * @param int     $resource     Resource ID
 * @param string  $size         Download size to access.
 *
 * @return string           Access key - empty if not permitted
 */
function generate_temp_download_key(int $user, int $resource, string $size): string
{
    if (!in_array($size, array('col', 'thm', 'pre')) && (($GLOBALS["userref"] != $user && !checkperm_user_edit($user)) || get_resource_access($resource) != 0)) {
        return "";
    }

    $data = $user
        . ":" . $resource
        . ":" . $size
        . ":" .  time();
    return base64_encode(
        rsEncrypt($data, hash_hmac('sha256', 'dld_key', $GLOBALS['scramble_key']), 32)
    );
}

/**
 * Validate the provided download key to authenticate a download or override an access check.
 *
 * @param int     $ref              Resource ID
 * @param string  $keystring        Key string - includes a nonce prefix
 * @param string  $size             Download size to access.
 * @param int     $expire_seconds   Optional parameter to set specified expiry time in seconds. Use 0 to set system default.
 * @param bool    $setup_user       Set to false where there is no need to initialise the user.
 *
 * @return bool
 *
 */
function validate_temp_download_key(int $ref, string $keystring, string $size, int $expire_seconds = 0, bool $setup_user = true): bool
{
    if ($expire_seconds < 1) {
        global $api_resource_path_expiry_hours;
        $expiry_time_limit = 60 * 60 * $api_resource_path_expiry_hours;
    } else {
        $expiry_time_limit = $expire_seconds;
    }

    $decoded_keystring = mb_strpos($keystring, '@@', 0, 'UTF-8') !== false ? $keystring : base64_decode($keystring);

    if (strlen($keystring) > 300) {
        // Support older keys that used the combined scramble keys
        $usekey = hash_hmac('sha512', 'dld_key', $GLOBALS['api_scramble_key'] . $GLOBALS['scramble_key']);
    } else {
        $usekey = hash_hmac('sha256', 'dld_key', $GLOBALS['scramble_key']);
    }

    $keydata = rsDecrypt($decoded_keystring, $usekey);
    if ($keydata != false) {
        $download_key_parts = explode(":", $keydata);

        if (count($download_key_parts) == 6) {
            // Support the old longer keys - multiple nonces are no longer used
            array_shift($download_key_parts);
        }

        if ($download_key_parts[1] == $ref && $download_key_parts[2] == $size) {
            $ak_user = $download_key_parts[0];
            $ak_userdata = get_user($ak_user);
            $key_time = $download_key_parts[3];
            if (
                $ak_userdata !== false
                && ((time() - $key_time) < $expiry_time_limit)
            ) {
                if ($setup_user) {
                    setup_user($ak_userdata);
                }
                return true;
            }
        }
    } else {
        debug("Failed to decrypt temp_download_key");
    }
    return false;
}

/**
 * Set up a dummy user with required permissions etc. to pass permission checks if running scripts from the command line
 *
 * @param array     $options[]]         Array of optional user options. Will default to generic system admin permissions if not set
 *                                      e.g.
 *                                         ["username"          => "My Application",
 *                                          "permissions"       => "h,v,e0",
 *                                          "groupname          => "My Application",
 *                                          "resource_defaults  => "region=EMEA",
 *                                         ]
 *
 * @return bool
 *
 */
function setup_command_line_user(array $setoptions = []): bool
{
    global $lang;

    $defaultusername = $lang["system_user_default"];

    // Set defaults, these can then be overidden by $setoptions
    $dummyuserdata = [];
    $dummyuserdata["ref"] = 0;
    $dummyuserdata["username"] = $defaultusername;
    $dummyuserdata["fullname"] = $defaultusername;
    $dummyuserdata["groupname"] = $defaultusername;
    # Command line user needs permission to update resources in all workflow states.
    $dummyuserdata["permissions"] = 'c,a,t,v,e' . implode(',e', get_workflow_states());
    $dummyuserdata["accepted_terms"] = 1;
    $dummyuserdata["ip_restrict_user"] = "";
    $dummyuserdata["ip_restrict_group"] = "";
    $dummyuserdata["current_collection_valid"] = 1;
    $dummyuserdata["usergroup"] = 0;

    // Add any columns from user table, plus any extra array
    // elements normally obtained from get_user()
    $requiredelements = columns_in("user", null, null, true);
    $requiredelements = array_merge($requiredelements, columns_in("usergroup", null, null, true));

    foreach ($requiredelements as $requiredelement) {
        if (!isset($dummyuserdata[$requiredelement])) {
            $dummyuserdata[$requiredelement] = "";
        }
    }

    // Override with any settings passed
    foreach ($setoptions as $setoption => $setvalue) {
        $dummyuserdata[$setoption] = $setvalue;
    }
    return setup_user($dummyuserdata);
}

/**
 * Update user table to record access by a user
 *
 * @param int $user             User ID
 * @param array $set_values     Optional array of column names and values to set
*/
function update_user_access(int $user = 0, array $set_values = []): bool
{
    $user = $user > 0 ? $user : ($GLOBALS["userref"] ?? 0);
    if ($user == 0) {
        return false;
    }
    $validcolumns = [
        "lang" => ["s",$GLOBALS["language"] ?? $GLOBALS["defaultlanguage"]],
        "last_browser" => ["s",isset($_SERVER["HTTP_USER_AGENT"]) ? substr($_SERVER["HTTP_USER_AGENT"], 0, 250) : false],
        "last_ip" => ["s",get_ip()],
        "logged_in" => ["i",0],
        "session" => ["s"],
        "login_tries" => ["i"],
    ];
    $col_sql = [];
    $update_params = [];
    foreach ($validcolumns as $column => $setparams) {
        $setval = $set_values[$column] ?? ($setparams[1] ?? false);
        if ($setval !== false) {
            // Only update if a value has been passed or we have a default - so session is not accidentally wiped
            $col_sql[] = $column . " = ?";
            $update_params = array_merge(
                $update_params,
                [$setparams[0],$setval] // Override the default if passed
            );
        }
    }
    $update_sql = "UPDATE user SET last_active = NOW(), " . implode(",", $col_sql) . " WHERE ref = ?";
    $update_params = array_merge($update_params, ["i",$user]);
    ps_query($update_sql, $update_params, '', -1, true, 0);
    return true;
}

/**
 * Check if the user can manage users.
 *
 * @return boolean
 */
function checkPermission_manage_users(): bool
{
    return ((checkperm('t') && checkperm('u')));
}

/**
 * Get the processing status message for the current user.
 *
 * @return false|array
 */
function get_processing_message()
{
    global $userref,$userprocessing_messages;

    if ($userprocessing_messages != "") {
        debug('Clearing out all processing messages.');
        ps_query("UPDATE user SET processing_messages='' WHERE ref=?", ["i",$userref]); // Clear out messages as now collected.
        return explode(";;", $userprocessing_messages);
    } else {
        debug('No processing messages available');
        return false;
    }
}

/**
 * Set a new processing message for the current user.
 *
 * @param string $message   The processing status message to add.
 *
 * @return void
 */
$set_processing_message_first_call = true;
function set_processing_message(string $message)
{
    debug_function_call(__FUNCTION__, func_get_args());
    global $userref,$userprocessing_messages,$set_processing_message_first_call;
    if (PHP_SAPI === "cli" ||  defined("API_CALL")) {
        // Messages don't work unless using browser
        return;
    }

    if (isset($GLOBALS['processing_message_override'])) {
        # Allow messages supplied at a lower level to be replaced at a higher level.
        # For example, when recreating previews for a collection, show message indicating progress
        # within the collection rather than messages showing which preview size is being created.
        $message = $GLOBALS['processing_message_override'];
        $set_processing_message_first_call = true;
    }

    // Fetch fresh from the DB as it may have been cleared by get_processing_message() since we started processing.
    $userprocessing_messages = ps_value("SELECT processing_messages value FROM user WHERE ref=?", ["i",$userref], '');
    if ($set_processing_message_first_call || trim($message) === "") {
        // Blank existing messages if present for first command this page load
        // Passing an empty string should always clear out any messages
        $userprocessing_messages = "";
        $set_processing_message_first_call = false;
    }
    if ($userprocessing_messages != "") {
         // Add delimiter
        $userprocessing_messages .= ";;";
    }
    $userprocessing_messages .= $message;

    ps_query("UPDATE user SET processing_messages=? WHERE ref=?", ["s",$userprocessing_messages,"i",$userref]);
}

/**
 * Consider if the current user is able to escalate the permissions of a user to the level of a "super admin".
 * Only users with "a" permission should be able to make other users super admins (user groups with "a" permission).
 * Also used to determine if "super admin" level user groups should be displayed.
 *
 * @param  int  $new_usergroup   ID of user group to be set
 */
function can_set_admin_usergroup(?int $new_usergroup): bool
{
    if ($new_usergroup == 0) {
        # No usergroup supplied e.g. when creating new user account.
        return true;
    }

    global $userpermissions;

    if (in_array('a', $userpermissions)) {
        return true;
    }

    global $can_set_admin_usergroup_perms_array;
    if (!isset($can_set_admin_usergroup_perms_array)) {
        # Get permissions once and store in global "cache" variable to avoid multiple queries to db as multiple groups maybe checked.
        $usergroup_permissions = ps_query("SELECT ug.ref as `ref`, IF(FIND_IN_SET('permissions', ug.inherit_flags) AND pg.permissions IS NOT NULL, pg.permissions, ug.permissions) AS `permissions` FROM usergroup ug LEFT JOIN usergroup pg ON pg.ref = ug.parent;");
        foreach ($usergroup_permissions as $usergroup_permission) {
            $can_set_admin_usergroup_perms_array[$usergroup_permission['ref']] = $usergroup_permission['permissions'];
        }
        $GLOBALS['can_set_admin_usergroup_perms_array'] = $can_set_admin_usergroup_perms_array;
    }

    $new_usergroup_permissions = $can_set_admin_usergroup_perms_array[$new_usergroup];
    $new_usergroup_permissions = explode(',', str_replace(' ', '', (string) $new_usergroup_permissions));
    if (!in_array('a', $new_usergroup_permissions)) {
        // New usergroup doesn't have 'a' permission.
        return true;
    }

    return false;
}

/**
 * Checks if the origin matches a whitelist entry, supporting wildcards like "*.example.com".
 * 
 *  @param  string  $origin      The URL to check.
 *  @param  array   $whitelist   Array of valid URLs - can include wildcards.
 *  @return bool                 True if the origin is allowed, false otherwise.
 */
function cors_is_origin_allowed(string $origin, array $whitelist): bool  {
    foreach ($whitelist as $allowed) {
        // Escape dots and replace wildcard '*' with regex '.*'
        $pattern = preg_quote($allowed, '/');
        $pattern = str_replace('\*', '.*', $pattern); // Convert '*' to '.*' for wildcard matching
        
        // Ensure it matches the entire string (^...$)
        if (preg_match("/^{$pattern}$/i", $origin)) {
            return true;
        }
    }
    return false;
}

/**
 * Delete a user group and associated records.
 *
 * @param  int   $usergroup_ref
 */
function delete_usergroup(int $usergroup_ref): bool
{
    $dependant_user_count = ps_value("SELECT COUNT(*) AS `value` FROM user WHERE usergroup = ?", array("i", $usergroup_ref), 0);
    $dependant_groups = ps_value("SELECT COUNT(*) AS `value` FROM usergroup WHERE parent = ?", array("i", $usergroup_ref), 0);
    $has_dependants = $dependant_user_count + $dependant_groups > 0;

    if ($has_dependants) {
        return false;
    }

    ps_query("DELETE FROM usergroup WHERE ref = ?", array("i", $usergroup_ref));
    log_activity('', LOG_CODE_DELETED, null, 'usergroup', null, $usergroup_ref);

    # No need to keep any records of language content for this user group
    ps_query('DELETE FROM site_text WHERE specific_to_group = ?', array("i", $usergroup_ref));

    # Remove dash tiles related to deleted user group. Don't delete from dash_tile as they maybe in use elsewhere.
    ps_query("DELETE FROM usergroup_dash_tile WHERE usergroup = ?", ['i', $usergroup_ref]);

    clear_query_cache('usergroup');

    return true;
}

/**
 * Check that this is a real browser by executing JS to set an expected cookie.
 */
function browser_check()
{
    global $browser_check_key, $applicationname, $disable_browser_check, $browser_check_message;
    $webRoot = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__); // Calculate web root. This runs early so $baseurl can't be used.
    $ajax=getval("ajax","")=="true"; // AJAX request?

     // Exceptions
    if (PHP_SAPI == 'cli') {return;}
    if (isset($disable_browser_check) && $disable_browser_check) {return;} // e.g. API/IIIF

    if (!isset($_SERVER["HTTP_USER_AGENT"])) {exit();} // Terminate requests that do not specify a user agent
    $question_key=hash_hmac("sha512", $_SERVER["HTTP_USER_AGENT"] . date('Ymd'), $browser_check_key);
    $answer_key=xor_base64_encode($question_key);

    // Look for the answer already set as a cookie
    if (getval("browser_check_cookie","")==$answer_key) {return;} // We're good

    // Output the JS to calculate the answer and set the cookie
    
    if (!$ajax) { 
    ?>
    <html><head><title><?php echo escape($applicationname) ?></title>
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: ubuntu,arial,sans-serif;
            background: #f0f0f0;
            color: #333;
            text-align: center;
        }
        h1 {
            font-size: 1.5em;margin-top: 2em;
        }
    </style>
    </head>
    <body>
        <div>
        <div class="logo"><img src="<?php echo $webRoot ?>/../gfx/titles/title-black.svg" /></div> 
    <?php } ?>
            <script>
            function x9Zq(str){var a=[90,51,127],b='',c=0;for(var d=0;d<str.length;d++)b+=String.fromCharCode(str.charCodeAt(d)^a[c++%3]);return btoa(b);}
            document.cookie = "browser_check_cookie=" + x9Zq(<?php echo json_encode($question_key) ?>) + "; path=/; max-age=172800";
            setTimeout(function() {
            window.location.reload(true);
            }, 1000);
            </script>
            <h1><?php echo escape($browser_check_message) ?></h1><?php /* Note - can't be translated - language files not loaded, this is intentionally very early in the process */ ?>
    <?php if (!$ajax) {  ?>
        </div>
    </body>
    </html>
    <?php
    }
    exit();    
}

/**
 * Obfuscates a string using a fixed XOR pattern and encodes it in Base64.
 *
 * This function performs a basic transformation by XOR-ing each character of the input
 * with a repeating fixed byte pattern, then encodes the result in Base64.
 * Designed to be mirrored easily in JavaScript for lightweight bot detection.
 *
 * @param string $str The input string to obfuscate.
 * @return string The Base64-encoded, XOR-obfuscated string.
 */
function xor_base64_encode($str) {
    $pattern = [0x5A, 0x33, 0x7F]; // Fixed XOR byte pattern
    $out = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $xor_byte = $pattern[$i % count($pattern)];
        $out .= chr(ord($str[$i]) ^ $xor_byte);
    }
    return base64_encode($out);
}

/**
 * Load site text for a user group. To preserve the original state of global language values, this function will always revert previous changes first.
 *
 * @param  int  $group   $usergroup value. Normally int however maybe null for activity before login e.g. load user group site text for an activity by
 *                       supplying a user group id and then return to the defaults by supplying null.
 */
function load_site_text_for_usergroup(int|null $group): void
{
    global $language, $pagename, $lang;

    // Restore original values each time this function loads so it starts from the default values.
    if (isset($GLOBALS['load_site_text_for_usergroup'])) {
        $results = $GLOBALS['load_site_text_for_usergroup'];
        for ($n = 0; $n < count($results); $n++) {
            if (isset($results[$n]['unset'])) {
                unset($lang[$results[$n]['unset']]);
            } elseif ($results[$n]['page'] == '') {
                $lang[$results[$n]['name']] = $results[$n]['text'];
                $customsitetext[$results[$n]['name']] = $results[$n]['text'];
            } else {
                $lang[$results[$n]['page'] . '__' . $results[$n]['name']] = $results[$n]['text'];
            }
        }
    }

    // Apply new values and store previous for reset the next time this function loads (above).
    if (is_int($group)) {
        $site_text_query = "SELECT `name`, `text`, `page` FROM site_text WHERE language = ? AND specific_to_group = ?";
        $parameters = array("s", $language, "i", $group);

        if ($pagename != "admin_content") { // Load all content on the admin_content page to allow management.
            $site_text_query .= " AND (page = ? OR page = 'all' OR page = '' " .  (($pagename == "dash_tile") ? " OR page = 'home'" : "") . ")";
            $parameters[] = "s";
            $parameters[] = $pagename;
        }

        $results = ps_query($site_text_query, $parameters, "sitetext", -1, true, 0);

        $original_values = array();
        for ($n = 0; $n < count($results); $n++) {
            if ($results[$n]['page'] == '') {
                if (isset($lang[$results[$n]['name']])) {
                    $original_values[] = array('name' => $results[$n]['name'], 'text' => $lang[$results[$n]['name']], 'page' => $results[$n]['page']);
                    $lang[$results[$n]['name']] = $results[$n]['text'];
                    $customsitetext[$results[$n]['name']] = $results[$n]['text'];
                } else {
                    $lang[$results[$n]['name']] = $results[$n]['text'];
                    $customsitetext[$results[$n]['name']] = $results[$n]['text'];
                    $original_values[] = array('unset' => $results[$n]['name']);
                }
            } else {
                if (isset($lang[$results[$n]['page'] . '__' . $results[$n]['name']])) {
                    $original_values[] = array('name' => $results[$n]['name'], 'text' => $lang[$results[$n]['page'] . '__' . $results[$n]['name']], 'page' => $results[$n]['page']);
                    $lang[$results[$n]['page'] . '__' . $results[$n]['name']] = $results[$n]['text'];
                } else {
                    $lang[$results[$n]['page'] . '__' . $results[$n]['name']] = $results[$n]['text'];
                    $original_values[] = array('unset' => $results[$n]['page'] . '__' . $results[$n]['name']);
                }
            }
        }

        if (count($original_values) > 0) {
            // Store the original values to restore the next time this function is called.
            $GLOBALS['load_site_text_for_usergroup'] = $original_values;
        }
    }
}

