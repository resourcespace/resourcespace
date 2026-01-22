<?php

function HookClipSearchSearch_header_after_actions()
{
    global $lang,$ref,$baseurl,$search;
    if (checkperm("clip-sb")) {
        return false;
    }

    // Don't display for search terms containing larger numbers - this is for natural search only
    if (preg_match('/\d{2,}/', $search)) {
        return false;
    }

    // Reject if it contains any symbols *except* comma, full stop, hyphen, and space
    if (preg_match('/[^a-zA-Z0-9 ,.\-]/', $search)) {
        return false;
    }

    $search_url = generateURL("{$baseurl}/pages/search.php", array("search" => "!clipsearch {$search}"));
    $icon = "<i class='icon-brain'></i> &nbsp;";

    render_filter_bar_button($lang["clip-natural-language-search"], 'onClick="return CentralSpaceLoad(\'' . $search_url . '\');"', $icon);
    return false; # Allow further custom panels
}
