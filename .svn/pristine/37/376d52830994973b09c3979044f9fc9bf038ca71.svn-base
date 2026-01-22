<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php"; if (!checkperm("u")) {exit ("Permission denied.");}



$simpleldap['domain']                = getval('domain', '');
$simpleldap['ldapserver']            = getval('ldapserver', '');
$simpleldap['ldapuser']              = getval('ldapuser', '');
$simpleldap['ldappassword']          = getval('ldappassword', '');
$userdomain                          = getval('userdomain', '');
$simpleldap['port']                  = getval('port', '');
$simpleldap['ldaptype']              = getval('ldaptype', 1);
$simpleldap['basedn']                = getval('basedn', '');
$simpleldap['loginfield']            = getval('loginfield', '');
$simpleldap['ldapgroupfield']        = getval('ldapgroupfield', '');
$simpleldap['email_attribute']       = getval('email_attribute', '');
$simpleldap['phone_attribute']       = getval('phone_attribute', '');
$simpleldap['emailsuffix']           = getval('emailsuffix','');
$simpleldap['LDAPTLS_REQCERT_never'] = getval('LDAPTLS_REQCERT_never', 0,true) != 0;

$escaped_ldapuser = (function_exists('ldap_escape')) ? ldap_escape($simpleldap['ldapuser'], '', LDAP_ESCAPE_DN) : $simpleldap['ldapuser'];

// Test we can connect to domain
$bindsuccess=false; 

if($simpleldap['LDAPTLS_REQCERT_never'])
    {
    putenv('LDAPTLS_REQCERT=never');
    }
// Set LDAP options for all connections
ldap_set_option(null, LDAP_OPT_NETWORK_TIMEOUT, 2);
ldap_set_option(null, LDAP_OPT_PROTOCOL_VERSION, 3);

if (substr(strtolower($simpleldap['ldapserver']),0,4) == "ldap") {
    $connstring = $simpleldap['ldapserver'];
} elseif ($simpleldap['port'] == 636) {
    $connstring = 'ldaps://' . $simpleldap['ldapserver'] . ':636';
} else {
    $connstring = 'ldap://' . $simpleldap['ldapserver'] . ':' . $simpleldap['port'];
}

$userdetails = false;
$ds = ldap_connect($connstring);
if ($ds !== false) {
    if(!isset($simpleldap['ldaptype']) || $simpleldap['ldaptype'] == 1)
        {
        if(strpos($escaped_ldapuser, $userdomain) !== false)
            {
            $binduserstring = $escaped_ldapuser;
            }
        else
            {
            $binduserstring = "{$escaped_ldapuser}@{$userdomain}";
            }

        debug("LDAP - Attempting to bind to AD server as : " . $binduserstring);
        $GLOBALS["use_error_exception"] = true;
        try 
            {
            $login = ldap_bind( $ds, ldap_escape($binduserstring, "", LDAP_ESCAPE_DN), $simpleldap['ldappassword'] );
            }
        catch(Exception $e)
            {
            debug("ERROR: LDAP bind failed " . $e->getMessage());
            $login=false;
            }
        unset($GLOBALS["use_error_exception"]);
        if ($login)
            {
            debug("LDAP - Success binding to AD server as : " . $binduserstring);
            $bindsuccess=true;
            }
        else
            {
            debug("LDAP - Failed binding to AD server as : " . $binduserstring);
            }
        }
    else
        {
        $searchdns=explode(";",$simpleldap['basedn']);
        foreach($searchdns as $searchdn)
            {
            $binduserstring = $simpleldap['loginfield'] . "=" . $escaped_ldapuser . "," . $searchdn;
            debug("LDAP - Attempting to bind to AD server as : " . $binduserstring);
            $GLOBALS["use_error_exception"] = true;
            try 
                {
                $login = ldap_bind( $ds, $binduserstring, $simpleldap['ldappassword'] );
                }
            catch(Exception $e)
                {
                debug("ERROR: LDAP bind failed " . $e->getMessage());
                $login=false;
                }
            unset($GLOBALS["use_error_exception"]);
            if (!$login)
                {
                debug("LDAP bind failed: " . $searchdn);
                }
            else
                {
                $bindsuccess=true;
                break;
                }
            }
        }

    ldap_get_option($ds, LDAP_OPT_ERROR_STRING, $last_ldap_error);
    $response['bindsuccess'] = $bindsuccess ? $lang['status-ok'] : "{$lang['status-fail']} - " . ldap_error($ds) . " ( {$last_ldap_error} )";
    $response['memberof']    = array();

    $userdetails=simpleldap_authenticate($simpleldap['ldapuser'],$simpleldap['ldappassword']);

    unset($GLOBALS["use_error_exception"]);

    if ($userdetails) {
        $response['success'] = true;
        $response['message'] = $lang["status-ok"];
        $response['domain'] = $userdetails['domain'];
        $response['binduser'] = $userdetails['binduser'];
        $response['username'] = $userdetails['username'];
        $response['displayname'] = $userdetails['displayname'];
        $response['group'] = $userdetails['group'];
        $response['email'] = $userdetails['email'];
        $response['phone'] = $userdetails['phone'];
        $response['memberof'] = $userdetails['memberof'];
    } else {
        $response['success'] = false;
        $response['message'] = $lang["status-fail"];
    }
} else {
    $response['success'] = false;
    $response['message'] = "LDAP - Invalid connection URL: '" . $connstring . "'";
}

$response['complete'] = true;

header('Content-Type: application/json');
echo json_encode($response);
exit();
