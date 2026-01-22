<?php

// Don't do this more than once a day
$last_copy_hitcount  = get_sysvar('last_copy_hitcount', '1970-01-01');

if (time() - strtotime($last_copy_hitcount) > 24 * 60 * 60) {
    if (is_process_lock("copy_hitcount")) {
        echo " - copy_hitcount process lock is in place. Skipping.\n";
        return;
    }

    set_process_lock("copy_hitcount");
    copy_hitcount_to_live();
    clear_process_lock("copy_hitcount");
    set_sysvar("last_copy_hitcount", date("Y-m-d H:i:s"));
} else {
    echo " - Skipping copy_hitcount_to_live  - last run: " . $last_copy_hitcount . $LINE_END;
}
