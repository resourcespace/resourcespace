<?php

command_line_only();

$ECHOFEEDBACK = false; # Whether or not to echo progress; for testing of this test script
$feedback = function ($buffer) use ($ECHOFEEDBACK) {
    if ($ECHOFEEDBACK) {
        echo $buffer;
    }
};

$verify_watermark_check = function ($expected, $wm_path, $wm_perm, $wm_access, $wm_open, $wm_open_search, $wm_page) use ($feedback) {

    global $userpermissions;
    global $access,$k,$watermark,$watermark_open,$pagename,$watermark_open_search;

    $watermark                           = $wm_path;
    $userpermissions                   = explode(",", $wm_perm);
    $access                                                  = $wm_access;
    $watermark_open                                                      = $wm_open;
    $watermark_open_search                                                         = $wm_open_search;
    $pagename                                                                                       = $wm_page;

    $test = "WATERMARK='$wm_path' PERM='$wm_perm' ACCESS='$wm_access' PAGE='$wm_page' OPEN='$wm_open' OPEN_SEARCH='$wm_open_search'";

    $result = check_use_watermark();

    if ($result) {
        $resulttext = "WATERMARK";
    } else {
        $resulttext = "NOWATERMARK";
    }

    if ($result == $expected) {
        $feedback("TEST " . $test . "    RESULT=$resulttext     PASSED" . PHP_EOL);
        return true;
    }
    # Error; not the expected outcome
    echo "TEST " . $test . "    RESULT=$resulttext     FAILED" . PHP_EOL;
    return false;
};

# Test conditions
define('WM_NOPATH', '');
define('WM_PATH', "watermark.png");

define('W_NOPERM', "x");
define('W_PERM', "w");

define('ACCESS_OPEN', 0);
define('ACCESS_RESTRICT', 1);

define('OPEN_FALSE', false);
define('OPEN_TRUE', true);

define('OPEN_SEARCH_FALSE', false);
define('OPEN_SEARCH_TRUE', true);

# Expected results
define('EXPECT_FALSE', false);
define('EXPECT_TRUE', true);

global $userpermissions;
global $access,$k,$watermark,$watermark_open,$pagename,$watermark_open_search;

# Save globals
$s_userpermissions = $userpermissions;
$s_access = $access;
$s_k = $k;
$s_watermark = $watermark;
$s_watermark_open = $watermark_open;
$s_pagename = $pagename;
$s_watermark_open_search = $watermark_open_search;

$feedback(PHP_EOL . PHP_EOL);

# TEST

$feedback("TEST 002400 - STARTED" . PHP_EOL);
$feedback("USERPERMISSIONS BEFORE= " . implode(",", $userpermissions) . PHP_EOL);

# VERIFY that the watermarking outcomes are as expected

# FIRST SET - COMBINATIONS WHERE WATERMARK AND PERMISSION-W ARE NOT BOTH PRESENT
#           - LIMITED TO VIEW PAGE ONLY

if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_NOPERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
$feedback(PHP_EOL);
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_NOPATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
$feedback(PHP_EOL);
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_NOPERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
$feedback(PHP_EOL);

# SECOND SET - COMBINATIONS WHERE WATERMARK AND PERMISSION-W ARE BOTH PRESENT
#            - VIEW, PREVIEW AND SEARCH PAGES

if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_FALSE, "view")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_TRUE, "view")) {
    return false;
}
$feedback(PHP_EOL);
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_FALSE, "preview")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_TRUE, "preview")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_FALSE, "preview")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_TRUE, "preview")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_FALSE, "preview")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_TRUE, "preview")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_FALSE, "preview")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_TRUE, "preview")) {
    return false;
}
$feedback(PHP_EOL);
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_FALSE, "search")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_FALSE, OPEN_SEARCH_TRUE, "search")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_FALSE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_FALSE, "search")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_OPEN, OPEN_TRUE, OPEN_SEARCH_TRUE, "search")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_FALSE, "search")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_FALSE, OPEN_SEARCH_TRUE, "search")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_FALSE, "search")) {
    return false;
}
if (!$verify_watermark_check(EXPECT_TRUE, WM_PATH, W_PERM, ACCESS_RESTRICT, OPEN_TRUE, OPEN_SEARCH_TRUE, "search")) {
    return false;
}

$feedback(PHP_EOL . PHP_EOL);

$feedback("TEST 002400 CHECK USE WATERMARK - PASSED" . PHP_EOL);

# Restore globals
$userpermissions = $s_userpermissions;
$access = $s_access;
$k = $s_k;
$watermark = $s_watermark;
$watermark_open = $s_watermark_open;
$pagename = $s_pagename;
$watermark_open_search = $s_watermark_open_search;

$feedback("USERPERMISSIONS AFTER= " . implode(",", $userpermissions) . PHP_EOL);
$feedback("TEST 002400 - FINISHED" . PHP_EOL);

$feedback(PHP_EOL . PHP_EOL);

// Teardown
unset($feedback);
unset($verify_watermark_check);

return true;
