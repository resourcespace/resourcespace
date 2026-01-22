<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

$tms_test_dsn = getval("dsn","");
$tms_test_user = getval("tmsuser","");
$tms_test_password = getval("tmspass","");

// Attempt to connect
$GLOBALS["use_error_exception"] = true;
try {
    $conn=odbc_connect($tms_test_dsn, $tms_test_user, $tms_test_password);
    if(!$conn) {
        $error=odbc_errormsg();
        $return["result"]     = $lang["error"];
        $return["message"]    = $lang["tms_link_tms_link_failure"] . ": " . $error;
    } else {
        $return["result"]     = $lang["ok"];
        $return["message"]    = $lang["tms_link_tms_link_success"];
        odbc_close($conn);
    }
}
catch (Exception $e) {
    $returned_error = $e->getMessage();
    $return["result"]     = $lang["error"];
    $return["message"]    = $lang["tms_link_tms_link_failure"] . ": " . $returned_error;
}
unset($GLOBALS["use_error_exception"]);

header('Content-Type: application/json');
echo json_encode($return);
exit();
