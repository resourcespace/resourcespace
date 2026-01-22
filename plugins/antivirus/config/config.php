<?php

/*
IMPORTANT NOTES for developers when they have Sophos and ClamAV on their machine:
// run first "/opt/sophos-av/bin/savdctl disable" to disable on-access scanning which prevents ClamAV from working on EICAR file
*/
include_once __DIR__ . '/../include/antivirus_functions.php';

define('ANTIVIRUS_ACTION_DELETE', 0);
define('ANTIVIRUS_ACTION_QUARANTINE', 1);

// Configure in ResourceSpace config.php file, as applicable
// $antivirus_path = '';
// $antivirus_silent_options = '--suppress-ok-results -o --no-summary';

// General (default) config options
$antivirus_action           = ANTIVIRUS_ACTION_QUARANTINE;
$antivirus_quarantine_state = 2;
