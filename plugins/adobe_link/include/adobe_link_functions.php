<?php

function adobe_link_genkey($user, $resource)
{
    global $scramble_key;
    return hash('sha256', $user . date('Ymd') . $scramble_key . $resource);
}

function adobe_link_check_credentials()
{
    global $user_select_sql, $scramble_key, $ref, $session_hash, $usercredentialsprovided;
    $adb_link_key = getval("adb_key", "");
    $adb_link_parts = explode(":", $adb_link_key);

    if (count($adb_link_parts) != 2) {
        return false;
    }

    $ref = getval('ref', '', true);
    $adb_link_user = $adb_link_parts[0];
    $adb_link_hash = $adb_link_parts[1];
    $keycheck = adobe_link_genkey($adb_link_user, $ref);
    if (!is_numeric($adb_link_user) || $keycheck != $adb_link_hash) {
        debug("adobe_link: Invalid key passed for user ref: " . $adb_link_user . ", resource ID#" . $ref . ". Received: '" . $adb_link_hash . "' .Expected: '" . $keycheck . "'");
        return false;
    }

    $user_select_sql = new PreparedStatementQuery();
    $user_select_sql->sql = "and u.ref = ?";
    $user_select_sql->parameters = array("i", $adb_link_user);

    $session_hash = "";
    $usercredentialsprovided = true;
    return true;
}
