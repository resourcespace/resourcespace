<?php

/**
 * Authenticate to directory by binding and performing LDAP search
 *
 * @param string $username   Username
 * @param string $password   Password
 * 
 * @return array | bool     Array of user data or false if failed to authenticate
 * 
 */
function simpleldap_authenticate(string $username,string $password)
{
    if (!function_exists('ldap_connect')) {
        return false;
    }
    // given a username and password, return false if not authenticated, or
    // associative array of displayname, username, e-mail, group if valid
    global $simpleldap;

    $email_attribute = mb_strtolower($simpleldap['email_attribute']);
    $phone_attribute = mb_strtolower($simpleldap['phone_attribute']);
    $loginfield = $simpleldap['loginfield'];
    $userdomain = $simpleldap['domain'];
    $searchdns = explode(";",$simpleldap['basedn']); // These can be searched in parallel

    if (!(strlen($password) > 0 && strlen($username) > 0)){
        return false;
    }

    if(isset($simpleldap['LDAPTLS_REQCERT_never']) && $simpleldap['LDAPTLS_REQCERT_never']) {
        putenv('LDAPTLS_REQCERT=never');
    }
    // ldap escape username
    $ldap_username = (function_exists('ldap_escape')) ? ldap_escape($username, '', LDAP_ESCAPE_DN) : $username;

    // Set LDAP options for all connections
    ldap_set_option(null, LDAP_OPT_NETWORK_TIMEOUT, 2);
    ldap_set_option(null, LDAP_OPT_PROTOCOL_VERSION, 3);
    if (!isset($simpleldap['ldaptype']) || $simpleldap['ldaptype'] == 1) {
       // AD - need to set this
       ldap_set_option(null, LDAP_OPT_REFERRALS, 0);
    }

    // Set up first connection
    if (substr(strtolower($simpleldap['ldapserver']),0,4) == "ldap") {
        $connstring = $simpleldap['ldapserver'];
    } elseif ($simpleldap['port'] == 636) {
        $connstring = 'ldaps://' . $simpleldap['ldapserver'] . ':636';
    } else {
        $connstring = 'ldap://' . $simpleldap['ldapserver'] . ':' . $simpleldap['port'];
    }

    $ds = ldap_connect($connstring);
    if ($ds === false) {
        debug("LDAP - Invalid connection URL: '" . $connstring . "'");
        return false;
    }

    // Bind to server
    // Set up array of different username formats to try and bind with
    $binddomains = explode(";",$simpleldap['basedn']);
    $binduserstrings[] = $ldap_username;
    if (
        (!isset($simpleldap['ldaptype']) || $simpleldap['ldaptype'] == 1)
        && strpos($ldap_username, "@" .  $userdomain) === false
        ) {
        // Ad and not in username@domain format, add that as well as cn
        $binduserstrings[] = $ldap_username . "@" . ldap_escape($userdomain, '', LDAP_ESCAPE_DN);
        foreach ($binddomains as $binddomain) {
            $binduserstrings[] = $loginfield . "=" . $ldap_username . "," . $binddomain;
            $binduserstrings[] = "cn=" . $ldap_username . "," . $binddomain;
        }
    } else {
        foreach ($binddomains as $binddomain) {
            $binduserstrings[] = $loginfield . "=" . str_replace("@" .  $userdomain,"",$ldap_username) . "," . $binddomain;
            $binduserstrings[] = "cn=" . str_replace("@" .  $userdomain,"",$ldap_username) . "," . $binddomain;
        }
    }
    // Try binding with each
    $login = false;
    foreach (array_unique($binduserstrings) as $binduserstring) {
        debug("LDAP - Attempting to bind to LDAP server as : " . $binduserstring);
        $GLOBALS["use_error_exception"] = true;
        try {
            $login = ldap_bind($ds, $binduserstring, $password);
            debug("LDAP bind success");
            break;
        } catch (Exception $e) {
            $message = $e->getMessage();
            debug("LDAP ERROR: LDAP bind failed " . $message);
            if (strpos($message, "Can't contact") !== false) {
                // Server is not accesssible, no point in trying different bind formats
                return false;
            }
        }
        unset($GLOBALS["use_error_exception"]);
    }
    if (!$login) {
        debug("LDAP - failed to bind to LDAP server");
        return false;
    }

    // Search
    $ldapgroupfield = $simpleldap['ldapgroupfield'];
    $attributes = array("displayname",$ldapgroupfield,$email_attribute,$phone_attribute);
    if (strpos($ldap_username, "@" .  $userdomain) !== false) {
        // Remove domain suffix for search
        $ldap_username = str_replace("@" .  $userdomain,"",$ldap_username);
    }
    $filter = "(&(objectClass=person)(". $loginfield . "=" . ldap_escape($ldap_username,'',LDAP_ESCAPE_FILTER) . "))";

    debug("LDAP - performing search: filter=" . $filter);
    debug("LDAP - retrieving attributes: " . implode(",",$attributes));

    $foundmatch = false;
    foreach ($searchdns as $searchdn)
        {
        debug("LDAP - preparing search DN: " . $searchdn);
        $ldapresult = ldap_search($ds, $searchdn, $filter, $attributes);
        if ($ldapresult) {
            $resultcount = ldap_count_entries($ds,$ldapresult);
            debug("LDAP - found " . $resultcount . " entries");
            if ($resultcount > 0) {
                $foundmatch = true;
                break;
            }
        }
    }
    if (!$foundmatch) {
        debug("LDAP - search returned no values");
        return false;
    }

    $entries = ldap_get_entries($ds, $ldapresult);
    if ($entries["count"] > 0) {
        debug("LDAP - search returned values");
        if (isset($entries[0]['displayname']) && count($entries[0]['displayname']) > 0) {
            $displayname = simpleldap_to_utf8($entries[0]['displayname'][0]);
        } else {
            $displayname = '';
        }

        $department = '';
        debug("LDAP - checking for group attribute - " . $ldapgroupfield);

        $usermemberof=array();

        if (isset($entries[0][$ldapgroupfield]) && count($entries[0][$ldapgroupfield]) > 0) {
            debug("LDAP - found group attribute - checking against configured mappings");
            $usermemberofgroups=$entries[0][$ldapgroupfield];
            $deptresult = ps_query('SELECT ldapgroup, rsgroup FROM simpleldap_groupmap ORDER BY priority ASC');

            // Go through each configured ldap->RS group mapping, adding each to the array of groups that user is a member of. Update $department with each match so we end up with the highest priority dept
            foreach ($deptresult as $thedeptresult) {
                $deptname = $thedeptresult['ldapgroup'];
                $deptmap = $thedeptresult['rsgroup'];
                $knowndept[strtolower($deptname)] = $deptmap;
                if (
                    (isset($deptmap) && !empty($deptmap))
                    && in_array(strtolower($deptname), array_map('strtolower', $usermemberofgroups))
                    ) {
                    $department=$deptname;
                    $usermemberof[]=$deptname;
                }
            }
            // Go through all mappings and add any unknown groups to the list of mappings so that it can be easily used (LDAP group names can be hard to remember)
            foreach ($usermemberofgroups as $usermemberofgroup) {
                if (
                    !isset($knowndept[strtolower($usermemberofgroup)]) // This group is not in the current list
                    && !is_numeric($usermemberofgroup)
                    ) {
                    // Ignore numbers; some ldap servers return a result count as the first value
                    $newdept = simpleldap_to_utf8($usermemberofgroup);
                    $usermemberof[]=$newdept;
                    ps_query("REPLACE INTO simpleldap_groupmap (ldapgroup, rsgroup) VALUES (?, NULL)", ['s', $newdept]);
                }
            }
        }
        // Extract email info
        if (isset($entries[0][$email_attribute]) && count($entries[0][$email_attribute]) > 0) {
            $email = simpleldap_to_utf8($entries[0][$email_attribute][0]);
        } elseif (strpos($username, "@" . $simpleldap['emailsuffix']) === false) {
            $email = $username . '@' . $simpleldap['emailsuffix'];
        } else {
            $email = $username;
        }

        // Extract phone info
        if (isset($entries[0][$phone_attribute]) && count($entries[0][$phone_attribute]) > 0) {
            $phone = simpleldap_to_utf8($entries[0][$phone_attribute][0]);
        } else {
            $phone = $GLOBALS["lang"]['unknown'];
        }

        $return['domain'] = $userdomain;
        $return['username'] = $username;
        $return['binduser'] = $binduserstring;
        $return['displayname'] = $displayname;
        $return['group'] = $department;
        $return['email'] = $email;
        $return['phone'] = $phone;
        $return['memberof'] = $usermemberof;
    } else {
        $return = false;
    }

    ldap_unbind($ds);
    return $return;
}


/**
* Helper function to convert received data from LDAP server to UTF-8
*
* @param string $str String to convert to UTF8
*
* @return string
*/
function simpleldap_to_utf8($str)
{
    global $simpleldap;

    if (!is_string($str) || !isset($simpleldap['ldap_encoding']) || trim($simpleldap['ldap_encoding']) == "") {
        return $str;
    }
    $converted_str = iconv($simpleldap['ldap_encoding'], "UTF-8", $str);
    return $converted_str !== false ? $converted_str : $str;
}