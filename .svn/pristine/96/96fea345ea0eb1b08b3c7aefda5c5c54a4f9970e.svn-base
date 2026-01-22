<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

$force_appearance = getval("force_appearance", "");

# User group specific logo
if (isset($usergroup)) {
    $curr_group = get_usergroup($usergroup);
    
    if (!empty($curr_group["group_specific_logo"])) {
        $linkedheaderimgsrc = (isset($storageurl) ? $storageurl : $baseurl . "/filestore") . "/admin/groupheaderimg/group" . $usergroup . "." . $curr_group["group_specific_logo"];
    }

    if (!empty($curr_group["group_specific_logo_dark"])) {
        $linkedheaderimgsrc_dark = (isset($storageurl) ? $storageurl : $baseurl . "/filestore") . "/admin/groupheaderimg/group" . $usergroup . "_dark." . $curr_group["group_specific_logo_dark"];
    }
}

header('Content-Type: text/plain');
echo get_header_image(false, true, $force_appearance);
