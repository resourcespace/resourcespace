<?php

$last_disable_inactive_users  = get_sysvar('last_disable_inactive_users', '1970-01-01');

# Skip if run within last 24 hours
if (time() - strtotime($last_disable_inactive_users) < 24 * 60 * 60) {
    echo " - Skipping disable_inactive_users job" . $LINE_END;
    return false;
}

# Disable inactive users
if (is_numeric($inactive_user_disable_days) && $inactive_user_disable_days > 0) {
    ps_query("UPDATE user SET approved = 2 WHERE (created is null OR created < date_sub(now(), interval ? day)) 
            AND(last_active is null OR last_active < date_sub(now(), interval ? day))
            AND approved = 1", array("i", $inactive_user_disable_days, "i", $inactive_user_disable_days));
    echo " - " . sql_affected_rows() . " users disabled" . $LINE_END;
}

# Update last sent date/time.
set_sysvar("last_disable_inactive_users", date("Y-m-d H:i:s"));
