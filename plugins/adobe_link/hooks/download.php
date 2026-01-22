<?php

function HookAdobe_linkDownloadProvideusercredentials()
{
    global $user_select_sql, $scramble_key, $ref, $session_hash, $usercredentialsprovided;
    return adobe_link_check_credentials();
}

// Added to bypass cookies_notification check
function HookAdobe_linkDownloadCookies_notification_bypass()
{
    return true;
}

// Added to bypass watermark on open
function HookAdobe_linkDownloadBlockwatermark()
{
    return adobe_link_check_credentials();
}

// Added to bypass cookies_notification check
function HookAdobe_linkDownloadAllow_in_browser($permitted_mime)
{
    global $adobe_link_asset_extensions;
    if (adobe_link_check_credentials()) {
        $addmime[] = "application/x-indesign";
        $addmime[] = "application/photoshop";
        $addmime[] = "image/vnd.adobe.photoshop";
        foreach ($adobe_link_asset_extensions as $adobe_link_asset_extension) {
            $addmime[] = "image/" . $adobe_link_asset_extension;
        }
        $permitted_mime = array_merge($permitted_mime, $addmime);
    }
    return $permitted_mime;
}
