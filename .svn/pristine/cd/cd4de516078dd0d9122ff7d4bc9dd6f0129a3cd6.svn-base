<?php

error_reporting(1);

if (file_exists("../include/config.php")) {
    die(1);
}
if ($_REQUEST['mysqlserver'] == '') {
    echo '202';
    exit();
}
if ($_REQUEST['mysqlusername'] == '') {
    echo '201';
    exit();
}
if ((isset($_REQUEST['mysqlserver'])) && (isset($_REQUEST['mysqlusername'])) && (isset($_REQUEST['mysqlpassword']))) {
    $dbtest_connection = mysqli_connect(filter_var($_REQUEST['mysqlserver'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), filter_var($_REQUEST['mysqlusername'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), filter_var($_REQUEST['mysqlpassword'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (!$dbtest_connection) {
        if (mysqli_errno($dbtest_connection) == 1045) {
            echo '201';
        } else {
            echo '202';
        }
    } else {
        if (mysqli_select_db($dbtest_connection, (filter_var($_REQUEST['mysqldb'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)))) {
            echo '200';
        } else {
            echo '203';
        }
    }
}
