<?php

// Clean up old temp files. These can be left on the system as a result of cancelled or failed downloads/uploads
if ($purge_temp_folder_age == 0) {
    if ('cli' == PHP_SAPI) {
        echo " - Config purge_temp_folder_age is set to 0 and is considered deactivated. Skipping delete temp files - FAILED" . $LINE_END;
    }
    debug("Config purge_temp_folder_age is set to 0 and is considered deactivated. Skipping delete temp files - FAILED");
    return;
}

$last_delete_tmp_files  = get_sysvar('last_delete_tmp_files', '1970-01-01');

# No need to run if already run in last 24 hours.
if (time() - strtotime($last_delete_tmp_files) < 24 * 60 * 60) {
    if ('cli' == PHP_SAPI) {
        echo " - Skipping delete_tmp_files job   - last run: " . $last_delete_tmp_files . $LINE_END;
    }
    return false;
}

delete_temp_files();

# Update last sent date/time.
set_sysvar("last_delete_tmp_files", date("Y-m-d H:i:s"));
