<?php
include_once __DIR__ . '/../include/simplesaml_functions.php';


function HookSimplesamlAllInitialise()
{
    $simplesaml_config = get_plugin_config('simplesaml');
    if (isset($simplesaml_config["simplesaml_lib_path"])) {
        // Legacy config  - remove from plugin settings
        save_removed_ui_config('simplesaml_lib_path');
        unset($simplesaml_config["simplesaml_lib_path"]);
        set_plugin_config('simplesaml', $simplesaml_config);
    }
    check_removed_ui_config("simplesaml_lib_path");

    if (simplesaml_use_idp_metadata_url_mode()) {
        global $simplesamlconfig;

        $latestdata = get_saml_metadata();
        if(!$latestdata) {
            return false;
        }
        $simplesamlconfig['metadata'] = $latestdata;
        $simplesamlconfig['authsources']['admin'] = ['core:AdminPassword'];
        $simplesamlconfig['authsources']['resourcespace-sp'] = [
            'saml:SP',
            'entityID' => sprintf(
                '%s/plugins/simplesaml/lib/%s/module.php/saml/sp/metadata.php/resourcespace-sp',
                $GLOBALS['baseurl'],
                $GLOBALS['simplesaml_use_www'] ? 'www' : 'public',
            ),
            'idp' =>  array_key_first($simplesamlconfig['metadata']),
            'discoURL' => null,
        ];
    }
}

function HookSimplesamlAllPreheaderoutput()
{
    if (!simplesaml_php_check() || get_sysvar(SYSVAR_CURRENT_UPGRADE_LEVEL) != SYSTEM_UPGRADE_LEVEL) {
        // If a new version then allow upgrade scripts to run first
        return false;
    }

    global $simplesaml_site_block, $simplesaml_allow_public_shares, $simplesaml_allowedpaths, $simplesaml_login,
        $simplesaml_allow_standard_login, $anonymous_login, $pagename, $baseurl;

    if ($simplesaml_login && simplesaml_is_authenticated()) {
        // Prevent password change if SAML authenticated and signed in to RS with SAML
        global $allow_password_change;
        $allow_password_change = false;
        return true;
    }

    if (
        $pagename == "login"
        && !$simplesaml_allow_standard_login
        && ($simplesaml_login || trim($anonymous_login) !== '')
    ) {
        // Shouldn't be able to see the login page, unless misconfigured in which case show to avoid a redirect loop
        // and allow user to log in and recover
        debug("simplesaml: blocking access to login page");
        redirect($baseurl);
        exit();
    }

    // If normal user is logged in and allowing standard logins do nothing and return
    if ($simplesaml_allow_standard_login && isset($_COOKIE["user"])) {
        $session_hash = $_COOKIE["user"];

        $user_select_sql = new PreparedStatementQuery();
        $user_select_sql->sql = "u.session = ?";
        $user_select_sql->parameters = ["s",$session_hash];
        if (validate_user($user_select_sql, false) === false) {
            debug("simplesaml: standard user login - invalid user session");
            rs_setcookie('user', '', 0);
        }

        debug("simplesaml: standard user login - no action required");
        return true;
    }

    if (!$simplesaml_allow_standard_login) {
        global $show_anonymous_login_panel;
        $show_anonymous_login_panel = false;
    }

    // If not blocking site completely and allowing standard logins but not on login page, do nothing and return
    if (!$simplesaml_site_block && $simplesaml_allow_standard_login) {
        debug("simplesaml: standard user login - no action required");
        return true;
    }

    // Check for exclusions
    $k = getval('k', '');
    $resource = getval('ref', '');
    $search = getval('search', '');
    $collection_from_search = str_replace('!collection', '', $search);
    $collection_add = getval('collection_add', '');
    $c = getval('c', '');
    $parent = getval('parent', '');

    $collection_from_search = is_numeric($collection_from_search) ? (int)$collection_from_search : null;

    $collection_add = is_numeric($collection_add) ? (int)$collection_add : null;

    $c = is_numeric($c) ? (int)$c : null;

    $parent = is_numeric($parent) ? (int) $parent : null;

    $resource = is_numeric($resource) ? (int)$resource : null;

    if ($simplesaml_allow_public_shares && '' !== $k) {
        // Hard to determine at this stage what we consider a collection/ resource ID so we
        // use the most general ones
        if ($collection_from_search && check_access_key_collection($collection_from_search, $k)) {
            return true;
        }
        if ($collection_add && check_access_key_collection($collection_add, $k)) {
            return true;
        }
        if ($c && check_access_key_collection($c, $k)) {
            return true;
        }
        if ($resource && check_access_key($resource, $k)) {
            return true;
        }
        // External sharing of a featured collection category
        if ($parent && check_access_key_collection($parent, $k)) {
            return true;
        }
    }

    $url = str_replace("\\", "/", $_SERVER["PHP_SELF"]);
    if ($simplesaml_allow_public_shares) {
        // Allow redirect for password protected external shares
        $simplesaml_allowedpaths[] = '/pages/share_access.php';
    }

    foreach ($simplesaml_allowedpaths as $simplesaml_allowedpath) {
        if ('' == trim($simplesaml_allowedpath)) {
            continue;
        }

        $samlexempturl = strpos($url, $simplesaml_allowedpath);
        if ($samlexempturl !== false && $samlexempturl == 0) {
            return true;
        }
    }

    simplesaml_authenticate();
    return true;
}


function HookSimplesamlAllProvideusercredentials()
{
    if (!simplesaml_php_check()) {
        return false;
    }

    global $pagename, $simplesaml_allow_standard_login, $simplesaml_prefer_standard_login, $baseurl, $path,
    $default_res_types, $scramble_key, $simplesaml_username_suffix, $simplesaml_username_attribute,
    $simplesaml_fullname_attribute, $simplesaml_email_attribute, $simplesaml_group_attribute,
    $simplesaml_fallback_group, $simplesaml_groupmap, $user_select_sql, $session_hash,
    $simplesaml_fullname_separator,$simplesaml_username_separator, $simplesaml_custom_attributes,$lang,
    $simplesaml_login, $simplesaml_site_block, $anonymous_login,$allow_password_change,
    $simplesaml_create_new_match_email, $simplesaml_allow_duplicate_email, $simplesaml_multiple_email_notify,
    $simplesaml_authorisation_claim_name, $simplesaml_authorisation_claim_value, $usercredentialsprovided;

    // Don't authenticate if this hook has already been handled by another higher priority plugin
    if (isset($usercredentialsprovided) && $usercredentialsprovided) {
        return false;
    }

    // Allow anonymous logins outside SSO if simplesaml is not configured to block access to site.
    // NOTE: if anonymous_login is set to an invalid user, then use SSO otherwise it goes in an indefinite loop
    if (
        !$simplesaml_site_block
        && isset($anonymous_login)
        && trim($anonymous_login) !== ''
        && getval("usesso", "") == ""
    ) {
        debug("simplesaml: checking for anonymous user");
        $anonymous_login_found = ps_value(
            "SELECT username AS `value` FROM user WHERE username = ?",
            array("s",$anonymous_login),
            ''
        );

        // If anonymous_login is not set to a real username then use SSO to authenticate
        if ($anonymous_login_found == '') {
            simplesaml_authenticate();
        }

        if (!simplesaml_is_authenticated()) {
            return true;
        } elseif (!$simplesaml_login) {
            global $show_anonymous_login_panel;
            $show_anonymous_login_panel = false;
        }
    }

    // If user is logged in or if SAML is not being used to login to ResourceSpace (just as a simple barrier,
    // usually with anonymous access configured) then use standard authentication if available
    if ($simplesaml_site_block && !simplesaml_is_authenticated()) {
        debug("simplesaml: site block enabled, performing SAML authentication");
        simplesaml_authenticate();
    }

    if (
        (isset($_COOKIE['user']) && $simplesaml_allow_standard_login)
        || (!$simplesaml_login && simplesaml_is_authenticated())
    ) {
        return true;
    }

    // Return false if not already authenticated and local login option is preferred
    if (
            !simplesaml_is_authenticated()
            && $simplesaml_allow_standard_login
            && $simplesaml_prefer_standard_login
            && getval("usesso", "") == ""
    ) {
        return false;
    }

    if (!simplesaml_is_authenticated()) {
        if ($pagename == "done" && !isset($_COOKIE["SimpleSAMLAuthToken"])) {
            // Don't attempt to authenticate when on done.php if user is not already authenticated
            return false;
        } elseif (getval("ajax", "") != "") {
            // Ajax loads can't be redirected. Need a full reload if session has timed out
            $url_alt = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"] : $baseurl;

            $reload_url = isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : $url_alt;
            debug("simplesaml: ajax request - reloading page " . $reload_url);
            ?>
                <script>
                top.location.href="<?php echo str_replace(array("modal=true","ajax=true"), "", $reload_url); ?>";
                </script>   
            <?php
            exit();
        }
        debug("simplesaml: authenticating");
        simplesaml_authenticate();
    }

        $attributes = simplesaml_getattributes();

        // Construct username
        $username = "";
    if (strlen($simplesaml_username_attribute) > 0) {
        $username_attributes = explode(",", $simplesaml_username_attribute);
        $username_parts = [];
        foreach ($username_attributes as $username_attribute) {
            if (isset($attributes[$username_attribute][0])) {
                if (is_object($attributes[$username_attribute][0])) {
                    $username_parts[] = $attributes[$username_attribute][0]->getValue();
                } elseif (is_string($attributes[$username_attribute][0])) {
                    $username_parts[] = $attributes[$username_attribute][0];
                }
            }
        }
        if (count($username_parts) > 0) {
            $username = implode($simplesaml_username_separator, $username_parts);
        }
    }
    if ($username == '') {
        debug("simplesaml: WARNING: no username found, attempting to use NameID");
        // Attempt to fall back to NameID, truncated as necessary
        $username = simplesaml_getauthdata("saml:sp:NameID");
    }

    if ($username == '') {
        // no username, can't continue
        debug("simplesaml: WARNING: no username found, aborting");
        return false;
    }

        // truncate if necessary
    if (strlen($username) > 50) {
        $username = mb_substr($username, 0, 15) . "_" . md5($username);
    }

        $username = $username . $simplesaml_username_suffix;

    // If local authorisation based on assertion/ claim is needed, check now and make sure we don't process any further!
    if (
            (trim($simplesaml_authorisation_claim_name) != '' && trim($simplesaml_authorisation_claim_value) != '')
            && (
                !array_key_exists($simplesaml_authorisation_claim_name, $attributes)
                || !in_array($simplesaml_authorisation_claim_value, $attributes[$simplesaml_authorisation_claim_name])
            )
    ) {
        debug("simplesaml: WARNING: Unauthorised login attempt recorded for username '{$username}'!");
        ?>
            <script>
            top.location.href = "<?php echo generateURL("{$baseurl}/login.php", array('error' => 'simplesaml_authorisation_login_error')); ?>";
            </script>
            <?php
            return false;
    }

    if (strpos($simplesaml_fullname_attribute, ",") !== false) {
        // Do we have to join two fields together?
        $fullname_attributes = explode(",", $simplesaml_fullname_attribute);
    } else {
        // Previous version used semi-colons
        $fullname_attributes = explode(";", $simplesaml_fullname_attribute);
    }

    $displayname = "";
    foreach ($fullname_attributes as $fullname_attribute) {
        if ($displayname != "") {
            $displayname .= $simplesaml_fullname_separator;
        }
        if (!isset($attributes[$fullname_attribute][0])) {
            debug("simplesaml: error - invalid fullname attribute: " . $fullname_attribute . ". Please check your configuration");
            return false;
        }
        debug("simplesaml: constructing fullname FROM attribute " . $fullname_attribute . ": "  . $attributes[$fullname_attribute][0]);
        $displayname .=  $attributes[$fullname_attribute][0];
    }

        $displayname = trim($displayname);
        debug("simplesaml: constructed fullname : "  . $displayname);

    if (isset($attributes[$simplesaml_email_attribute][0])) {
        $email = $attributes[$simplesaml_email_attribute][0];
    }

        $groups = array();
    if (trim($simplesaml_group_attribute) != '' && isset($attributes[$simplesaml_group_attribute])) {
        $groups = $attributes[$simplesaml_group_attribute];
    }

    $userid = 0;
    $update_hash = false; // Only update password hash if necessary as computationally intensive
    $legacy_username_used = false;
    
    if (isset($GLOBALS['saml_current_user_cache'][$username])) {
        // Prevent multiple queries of db for getting user information when initialising page.
        $currentuser = $GLOBALS['saml_current_user_cache'][$username];
    } else {
        $currentuser = ps_query('SELECT ref, usergroup, last_active, origin, username, `password`, fullname, email, comments, simplesaml_custom_attributes FROM user WHERE username = ?', array('s', $username));
        $GLOBALS['saml_current_user_cache'][$username] = $currentuser;
    }

    // Attempt one more time with ".sso" suffix. Legacy way of distinguishing between SSO accounts and normal accounts
    if (is_array($currentuser) && count($currentuser) == 0) {
        $legacy_username = "{$username}.sso";
        $currentuser = ps_query(
            "SELECT ref, usergroup, last_active FROM user WHERE username = ?",
            array("s",$legacy_username)
        );
        $legacy_username_used = true;
    }

    if (count($currentuser) > 0) {
        $userid = $currentuser[0]["ref"];

        if ($legacy_username_used) {
            ps_query("UPDATE user SET username = ? WHERE ref = ?", array("s",$username,"i",$userid));
        }

        // Update hash if not logged on in last day
        $lastactive = strtotime((string)$currentuser[0]["last_active"]);
        if ($lastactive < date(time() - (60 * 60 * 24))) {
            $update_hash = true;
        }
    }

        debug("simplesaml - got user details username=" . $username . ", email: " . (isset($email) ? $email : "(not received)"));

    if (!isset($email)) {
        // No email - may be a test account?
        $email = "";
    }

        // figure out group
        $group = $simplesaml_fallback_group;
        $currentpriority = 0;
    if (count($simplesaml_groupmap) > 0) {
        for ($i = 0; $i < count($simplesaml_groupmap); $i++) {
            for ($g = 0; $g < count($groups); $g++) {
                if (
                    ($groups[$g] == $simplesaml_groupmap[$i]['samlgroup'])
                    && is_numeric($simplesaml_groupmap[$i]['rsgroup'])
                    && $simplesaml_groupmap[$i]['priority'] > $currentpriority
                ) {
                    $group = $simplesaml_groupmap[$i]['rsgroup'];
                    $currentpriority = $simplesaml_groupmap[$i]['priority'];
                    debug("simplesaml  - found mapping for SAML group: " . $groups[$g] . ", group #" . $simplesaml_groupmap[$i]['rsgroup'] . ". priority :"  . $simplesaml_groupmap[$i]['priority']);
                }
            }
        }
    }
        debug("simplesaml  - using RS group #" . $group);

        // If custom attributes need to be recorded against a user record, do it now
        $custom_attributes = array();
    if ('' != $simplesaml_custom_attributes) {
        $search_custom_attributes = explode(',', $simplesaml_custom_attributes);

        foreach ($attributes as $attribute => $attribute_value) {
            if (!in_array($attribute, $search_custom_attributes)) {
                continue;
            }

            // For now, we only allow one value per attribute
            $custom_attributes[$attribute] = $attribute_value[0];
        }
    }

    if ($userid <= 0) {
        // User authenticated, but does not exist
        // First see if there is a matching account
        $email_matches = ps_query("SELECT ref, username, fullname, origin FROM user WHERE email=?", array("s",$email));

        if (count($email_matches) > 0 && trim($email) != "") {
            if (count($email_matches) == 1 && $simplesaml_create_new_match_email) {
                // We want adopt this matching account - update the username and details to match the new login credentials
                debug("simplesaml - user authenticated with matching email for existing user . " . $email . ", updating user account '" . $email_matches[0]["username"] . "' (id #" . $email_matches[0]["ref"] . ") to new username " . $username);
                $userid = $email_matches[0]["ref"];
                $origin = $email_matches[0]["origin"];
                $comment = $lang["simplesaml_usermatchcomment"];
                $update_hash = true;
            } else {
                if (!$simplesaml_allow_duplicate_email) {
                    if (filter_var($simplesaml_multiple_email_notify, FILTER_VALIDATE_EMAIL) && getval("usesso", "") != "") {
                        // Already account(s) with this email address, notify the administrator (provided it is an actual attempt to pevent unnecessary duplicates)
                        simplesaml_duplicate_notify($username, $group, $email, $email_matches, $userid);
                    }
                    // We are blocking accounts with the same email
                    if ($simplesaml_allow_standard_login) {
                        ?>
                            <script>
                            top.location.href="<?php echo $baseurl; ?>/login.php?error=simplesaml_duplicate_email_error";
                            </script>
                            <?php
                            exit();
                    } else {
                        return false;
                    }
                } else {
                    // Create the user
                    $userid = new_user($username, $group);
                    if (!$userid) {
                        debug("simplesaml - unable to create user: " . $userid);
                        return false;
                    }
                    if ($userid === -2) {
                        debug("simplesaml - Error - User could not be created, user limit exceeded");
                        ?>
                        <script>
                        top.location.href="<?php echo $baseurl; ?>/login.php?error=simplesaml_userlimit_error";
                        </script>
                        <?php
                        exit();
                    }
                    if (filter_var($simplesaml_multiple_email_notify, FILTER_VALIDATE_EMAIL) && getval("usesso", "") != "") {
                        // Already account(s) with this email address, notify the administrator (provided it is an actual attempt to pevent unnecessary duplicates)
                        simplesaml_duplicate_notify($username, $group, $email, $email_matches, $userid);
                    }
                    include_once __DIR__ . '/../../../include/dash_functions.php';
                    build_usergroup_dash($group, $userid);
                    $update_hash = true;
                }
            }
        } else {
            // Create the user
            $userid = new_user($username, $group);
            if ($userid === -2) {
                debug("simplesaml - Error - User could not be created, user limit exceeded");
                ?>
                <script>
                top.location.href="<?php echo $baseurl; ?>/login.php?error=simplesaml_userlimit_error";
                </script>
                <?php
                exit();
            }
            include_once __DIR__ . '/../../../include/dash_functions.php';
            build_usergroup_dash($group, $userid);
            $update_hash = true;
        }
        $currentuser = ps_query('SELECT ref, usergroup, last_active, origin, username, `password`, fullname, email, comments, simplesaml_custom_attributes FROM user WHERE ref = ?', array('i', $userid));
    }

    if ($userid > 0) {
        global $simplesaml_update_group, $session_autologout;
        // Update user info only for items which have changed.
        $update_user_info_sql = array();
        $update_user_info_params = array();

        if ($currentuser[0]['origin'] !== 'simplesaml') {
            $update_user_info_sql[] = 'origin = ?';
            $update_user_info_params[] = 's';
            $update_user_info_params[] = 'simplesaml';
        }

        if ($currentuser[0]['username'] !== $username) {
            $update_user_info_sql[] = 'username = ?';
            $update_user_info_params[] = 's';
            $update_user_info_params[] = $username;
        }

        if ($update_hash) {
            $password_hash = rs_password_hash('RSSAML' . generateSecureKey(64) . $username);
            $update_user_info_sql[] = 'password = ?';
            $update_user_info_params[] = 's';
            $update_user_info_params[] = $password_hash;
        }

        if ($currentuser[0]['fullname'] !== $displayname) {
            $update_user_info_sql[] = 'fullname = ?';
            $update_user_info_params[] = 's';
            $update_user_info_params[] = $displayname;
        }

        if (isset($email) && $email != "" && $currentuser[0]['email'] !== $email) {
            // Only set email if provided. Allows accounts without an email address to have one set by the admin without it getting overwritten
            $update_user_info_sql[] = 'email = ?';
            $update_user_info_params[] = 's';
            $update_user_info_params[] = $email;
        }

        if (isset($comment) && $currentuser[0]['comments'] !== $comment) {
            $update_user_info_sql[] = 'comments = concat(comments, ?)';
            $update_user_info_params[] = 's';
            $update_user_info_params[] = "\n" . date("Y-m-d") . " " . $comment;

            log_activity($comment, LOG_CODE_UNSPECIFIED, 'simplesaml', 'user', 'origin', $userid, null, (isset($origin) ? $origin : null), $userid);
        }

        if ($simplesaml_update_group || (isset($currentuser[0]["usergroup"]) && $currentuser[0]["usergroup"] == "")) {
            $update_user_info_sql[] = 'usergroup = ?';
            $update_user_info_params[] = 'i';
            $update_user_info_params[] = $group;
        }

        if (0 < count($custom_attributes) && $currentuser[0]['simplesaml_custom_attributes'] !== json_encode($custom_attributes)) {
            $custom_attributes = json_encode($custom_attributes);
            $update_user_info_sql[] = 'simplesaml_custom_attributes = ?';
            $update_user_info_params[] = 's';
            $update_user_info_params[] = $custom_attributes;
        }

        if (count($update_user_info_sql) > 0) {
            $sql = 'UPDATE user SET ';
            $sql .= implode(', ', $update_user_info_sql);
            $sql .= " WHERE ref = ?";
            $update_user_info_params[] = 'i';
            $update_user_info_params[] = $userid;
            ps_query($sql, $update_user_info_params);
            unset($GLOBALS['saml_current_user_cache'][$username]);
        }

        $user_select_sql = new PreparedStatementQuery();
        $user_select_sql->sql = "u.username = ?";
        $user_select_sql->parameters = ["s",$username];

        $allow_password_change = false;
        $session_autologout = false;
        return true;
    }
        return false;
}

function HookSimplesamlAllLoginformlink()
{
    // Add a link to login.php, as this page may still be seen if $simplesaml_allow_standard_login is set to true
    global $baseurl, $lang, $simplesaml_login;
    if (!simplesaml_php_check() || !$simplesaml_login) {
        return false;
    }

    // Include URL redirect for RelayState
    $requested = parse_url(getval("url", ""));
    $relpath = trim($requested["path"] ?? "/");

    if (file_exists(dirname(__DIR__, 3) . str_replace("../", "", $relpath))) {
        // Only add if this is a valid file
        parse_str($requested["query"] ?? "", $params);
        if (!is_string($params[array_key_first($params)] ?? false)) {
            unset($params);
        }
    }

    if (!isset($params)) {
        $relpath = "/";
    }
    $params['usesso'] = 'true';

    ?>
    <p class="external-login-link">
        <a href="<?php echo generateURL($baseurl . $relpath, $params); ?>">
            <i class="icon-key-round"></i><?php echo strip_tags_and_attributes($lang['simplesaml_use_sso']); ?>
        </a>
        <br/>
    </p>
    <?php
}

function HookSimplesamlLoginPostlogout()
{
    if (!simplesaml_php_check()) {
        return false;
    }
        global $simplesaml_login;

    if ($simplesaml_login && simplesaml_is_authenticated()) {
        simplesaml_signout();
    }
}

function HookSimplesamlLoginPostlogout2()
{
    if (!simplesaml_php_check()) {
        return false;
    }
        global $baseurl,$simplesaml_login;
    if (getval("logout", "") != "" && $simplesaml_login && simplesaml_is_authenticated()) {
        simplesaml_signout();
        header('Location: ' . $baseurl);
    }
}


function HookSimplesamlAllCheckuserloggedin()
{
    return simplesaml_is_authenticated();
}


/**
* Render header navigation links in anonymous mode based on simplasaml configuration
*
*
*/
function HookSimplesamlAllReplaceheadernav1anon()
{
    if (!simplesaml_php_check()) {
        return false;
    }

    global $baseurl, $lang, $contact_link, $simplesaml_prefer_standard_login, $simplesaml_site_block, $simplesaml_allow_standard_login, $simplesaml_login;

    // Don't show any link if signed in via SAML already and standard logins have been disabled
    if (!$simplesaml_allow_standard_login && !$simplesaml_login && simplesaml_is_authenticated()) {
        return true;
    }

    if ($simplesaml_prefer_standard_login || $simplesaml_site_block) {
        return false;
    }

    ?>
    <ul>
        <li>
            <a href="<?php echo $baseurl; ?>/?usesso=true"><?php echo escape($lang['login']); ?></a>
        </li>
    <?php
    if ($contact_link) {
        ?>
        <li>
            <a href="<?php echo $baseurl?>/pages/contact.php" onClick="return CentralSpaceLoad(this, true);"><?php echo escape($lang['contactus']); ?></a>
        </li>
        <?php
    }
    ?>
    </ul>
    <?php

    return true;
}

function HookSimplesamlCollection_emailReplacecollectionemailredirect()
{
    if (!simplesaml_php_check()) {
        return false;
    }
    global $baseurl_short, $userref;

    redirect($baseurl_short . "pages/done.php?text=collection_email");
}

function HookSimplesamlResource_emailReplaceresourceemailredirect()
{
    if (!simplesaml_php_check()) {
        return false;
    }
    global $baseurl_short, $userref, $ref, $search, $offset, $order_by, $sort, $archive;

    redirect($baseurl_short . "pages/done.php?text=resource_email&resource=" . urlencode($ref) . "&search=" . urlencode($search) . "&offset=" . urlencode($offset) . "&order_by=" . urlencode($order_by) . "&sort=" . urlencode($sort) . "&archive=" . urlencode($archive));
}

function HookSimplesamlAllCheck_access_key()
{
    if (!simplesaml_php_check()) {
        return false;
    }
    global $external_share_view_as_internal, $simplesaml_login;

    /*
    Handle "$external_share_view_as_internal = true;" case. This require us to set the user up as authenticate.php is not called
    at this stage on search.php page so we need to validate user and set it up in order to set $internal_share_access.
    */
    if ($external_share_view_as_internal && $simplesaml_login && simplesaml_is_authenticated()) {
        global $is_authenticated, $user_select_sql;

        HookSimplesamlAllProvideusercredentials();

        $validate_user = validate_user($user_select_sql);

        if (is_array($validate_user) && is_array($validate_user[0]) && !empty($validate_user[0])) {
            setup_user($validate_user[0]);
            $is_authenticated = true;
        }
    }

    // return false because check_access_key() returns true without doing any checks on the key if hook returns TRUE
    return false;
}

function HookSimplesamlAllExtra_checks()
{
    $return = [];  // Array containing any errors / warnings found.

    // Check if incompatible with PHP version
    $simplesaml_php_check = [
        'status' => 'FAIL',
        'info' => $GLOBALS['lang']['simplesaml_healthcheck_error'] . ' PHP',
        'severity' => SEVERITY_CRITICAL,
        'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL],
    ];

    $GLOBALS['use_error_exception'] = true;
    try {
        if (!simplesaml_php_check(with_config: false)) {
            $return['simplesaml_php'] = $simplesaml_php_check;
        }
    } catch (Exception $e) {
        $return['simplesaml_php_exception'] = $simplesaml_php_check;
    }
    unset($GLOBALS['use_error_exception']);

    // Check if SAML library needs updating (if pre-9.7 SP not using ResourceSpace config)
    $simplesaml_config_check = [
        'status' => 'FAIL',
        'info' => $GLOBALS['lang']['simplesaml_healthcheck_error'],
        'severity' => SEVERITY_NOTICE
    ];

    $GLOBALS['use_error_exception'] = true;
    try {
        $check_simplesamlphp_config = simplesaml_config_check();

        if (!$check_simplesamlphp_config['success']) {
            $return['saml_config_check'] = array_merge(
                $simplesaml_config_check,
                ['info' => $check_simplesamlphp_config['error']]
            );
        }
    } catch (Exception $e) {
        $return['saml_config_exception'] = $simplesaml_config_check;
    }
    unset($GLOBALS['use_error_exception']);

    // Check for expired certificates
    if (isset($GLOBALS["simplesamlconfig"]["metadata"]) && $GLOBALS['simplesaml_check_idp_cert_expiry']) {
        // Only possible to check if using ResourceSpace stored SAML config
        $idpindex = 1; // Some systems have multiple IdPs
        foreach ($GLOBALS["simplesamlconfig"]["metadata"] as $idpid => $idpdata) {
            $idpname = $idpid; // IdP may not have a friendly readable name configured
            $idpcheckname = "simplesaml_php_certificate_" . $idpindex;
            $latestexpiry = get_saml_metadata_expiry($idpid);
            if (isset($idpdata["name"])) {
                if (is_string($idpdata["name"])) {
                    $idpfriendlyname = $idpdata["name"];
                } else {
                    $idpfriendlyname = (string) ($idpdata["name"][$GLOBALS['language']] ?? reset($idpdata["name"]));
                }
                $idpname .= " (" . $idpfriendlyname . ")";
            }
            $placeholders = ["%idpname", "%expiretime"];
            $replace = [$idpname, $latestexpiry];

            // Return errors - each IdP must have a unique check identifier
            if ($latestexpiry < date("Y-m-d H:i")) {
                $return[$idpcheckname] = [
                    'status' => 'FAIL',
                    'info' => str_replace($placeholders, $replace, $GLOBALS['lang']['simplesaml_idp_cert_expired']),
                    'severity' => SEVERITY_WARNING,
                    'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
                ];
            } elseif ($latestexpiry < date("Y-m-d H:i", time() + 60 * 60 * 24 * 7)) {
                $return[$idpcheckname] = [
                    'status' => 'FAIL',
                    'info' => str_replace($placeholders, $replace, $GLOBALS['lang']['simplesaml_idp_cert_expiring']),
                    'severity' => SEVERITY_WARNING,
                    'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
                ];
            } else {
                $return[$idpcheckname] = [
                    'status' => 'OK',
                    'info' => str_replace($placeholders, $replace, $GLOBALS['lang']['simplesaml_idp_cert_expires']),
                ];
            }
            $idpindex++;
        }
    }

    if ($GLOBALS['simplesaml_rsconfig'] === 2) { 
        // Check automatic metadata updates
        $lastupdate = get_sysvar("saml_idp_metadata_last_updated");
        $errormessage = (string) get_sysvar("saml_idp_metadata_error");
        $lastupdatetext = $lastupdate != "" ? date("l F jS Y @ H:i:s", $lastupdate) : $GLOBALS['lang']["status-never"];
        if ($errormessage !== ''
            || $lastupdate == ''
            || time() - $lastupdate > 60*60*24*7
        ) {
            $return[$GLOBALS["lang"]['simplesaml_metadata_updates']] = [
                'status' => 'WARN',
                'info' => str_replace("%date%", $lastupdatetext, $GLOBALS['lang']['simplesaml_metadata_warning']) . ". " . $GLOBALS['lang']['errors'] . ": " . $errormessage,
                'severity' => SEVERITY_WARNING,
                'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            ];
        } elseif (!isset($GLOBALS["simplesamlconfig"]['metadata']) || count($GLOBALS["simplesamlconfig"]['metadata']) < 1) {
            // Metadata was not parsed successfully
            $return[$GLOBALS["lang"]['simplesaml_metadata_updates']] = [
                'status' => 'FAIL',
                'info' => $GLOBALS['lang']['simplesaml_metadata_invalid'],
                'severity' => SEVERITY_WARNING,
                'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            ];
        } else {
            $return[$GLOBALS["lang"]['simplesaml_metadata_updates']] = [
                'status' => 'OK',
                'info' => $lastupdatetext,
            ];
        }
    }

    return count($return) > 0 ? $return : false;
}

function HookSimplesamlAllSsologindefault()
{
    return !$GLOBALS["simplesaml_prefer_standard_login"];
}

function HookSimplesamlAllCron()
{
    $storedsamlconfig = get_saml_metadata();
    if (!$storedsamlconfig) {
        return false;
    }
    $simplesamlconfig['metadata'] = $storedsamlconfig;
    $errormessage = get_sysvar("saml_idp_metadata_error", '');

    // Check when last updated
    $lastupdate = get_sysvar("saml_idp_metadata_last_updated");

    if (trim($errormessage) !== ""
        || time() - $lastupdate > 60*60*12 // Don't update if valid and updated in last 12 hours
    ) {
        // Invalid metadata
        debug("simplesaml - invalid metadata");
        echo " - Updating SAML metadata" . PHP_EOL;
        $result = simplesaml_update_metadata();
        if($result !== true) {
            $message = " - Failed to update SAML metadata: " .$result;
            echo $message . PHP_EOL;
            return;
        }
        echo " - Successfully updated SAML metadata" . PHP_EOL;
    }
}