<?php

// Clean up old anonymous collections
$last_delete_anonymous_cols  = get_sysvar('last_delete_anonymous_collections', '1970-01-01');

# No need to run if already run in last 24 hours.
if (time() - strtotime($last_delete_anonymous_cols) < 24 * 60 * 60) {
    if ('cli' == PHP_SAPI) {
        echo " - Skipping delete_anonymous_collections job   - last run: " . $last_delete_anonymous_cols . $LINE_END;
    }
    return false;
}

cleanup_anonymous_collections(0);

# Update last run date/time.
set_sysvar("last_delete_anonymous_collections", date("Y-m-d H:i:s"));
