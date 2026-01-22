<?php

include_once __DIR__ . "/../../include/boot.php";


if (isset($anonymous_login)) {
    $lastrun  = get_sysvar('last_remove_anonymous_collections', '1970-01-01');
    # Don't run if already run in last 24 hours.
    if (time() - strtotime($lastrun) < 24 * 60 * 60) {
        echo " - Skipping remove_anonymous_collections  - last run: " . $lastrun . "<br />\n";
        return false;
    }

    if (is_array($anonymous_login)) {
        foreach ($anonymous_login as $user) {
            $anonref = ps_value("SELECT ref value FROM user WHERE username=?", array("s",$user), 0);
            echo "Deleting old anonymous collections for user " . $user . "(ref: " . $anonref . ")\r\n";
            $dcols = delete_old_collections($anonref, 7);
            echo "Deleted " . $dcols . " collections for user " . $user . "\r\n";
        }
    } else {
        $anonref = ps_value("SELECT ref value FROM user WHERE username=?", array("s",$anonymous_login), 0);
        echo "Deleting old anonymous collections for user " . $anonymous_login . "(ref: " . $anonref . ")\r\n";
        $dcols = delete_old_collections($anonref, 7);
        echo "Deleted " . $dcols . " collections for user " . $anonymous_login . "\r\n";
    }

    # Update last sent date/time.
    set_sysvar("last_remove_anonymous_collections", date("Y-m-d H:i:s"));
}
