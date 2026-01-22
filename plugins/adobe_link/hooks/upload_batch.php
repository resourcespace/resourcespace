<?php

function HookAdobe_linkUpload_batchProvideusercredentials()
{
    global $user_select_sql, $scramble_key, $ref, $session_hash, $usercredentialsprovided;
    return adobe_link_check_credentials();
}

// Added to bypass cookies_notification check
function HookAdobe_linkUpload_batchCookies_notification_bypass()
{
    return true;
}

function HookAdobe_linkUpload_batchModify_upload_file($filename, $filepath)
{
    global $username,$scramble_key, $usersession, $upfilepath;
    if ($_FILES && isset($_FILES['file']['tmp_name'])) {
        debug("adobe_link - receiving file from user " . $username . ",  filename " . $_FILES['file']['name']);
        $upfilepath = get_temp_dir() . DIRECTORY_SEPARATOR . md5(uniqid() . $scramble_key . $usersession);
        move_uploaded_file($_FILES['file']['tmp_name'], $upfilepath);
    }
    return false;
}
