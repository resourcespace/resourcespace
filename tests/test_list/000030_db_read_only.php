<?php

command_line_only();


if (!db_use_multiple_connection_modes()) {
    echo "INFO: System NOT configured for read-only connection mode! - ";
    return true;
}

// Check clearing connection mode
db_set_connection_mode("read_only");
db_clear_connection_mode();
if (isset($GLOBALS["db_connection_mode"])) {
    return false;
}

db_set_connection_mode("read_only");
if (db_get_connection_mode() !== "read_only") {
    echo "Set/ Get connection mode - ";
    return false;
}

$GLOBALS["use_error_exception"] = true;
$case0 = false;
try {
    ps_query("INSERT INTO sysvars (`name`, `value`) VALUES ('test_read_only_mode','true')");
    clear_query_cache("sysvars");
} catch (Throwable $e) {
    $case0 = true;
}
if (!$case0) {
    echo "FAIL - subtest 0 ";
    return false;
}

// Connection mode is cleared when running queries so must be reset
db_set_connection_mode("read_only");
$case1 = false;
try {
    ps_query("INSERT INTO sysvars (`name`, `value`) VALUES ('test_read_only_mode1','true')");
    clear_query_cache("sysvars");

    echo "POP";
} catch (Throwable $e) {
    $case1 = true;
}
if (!$case1) {
    echo "FAIL - subtest 1 ";
    return false;
}
$GLOBALS["use_error_exception"] = false;

return true;
