<?php

include "../../include/boot.php";

$ref = getval("ref", "", true);
$k = getval("k", "");

if (($k == "") || (!check_access_key($ref, $k))) {
    include "../../include/authenticate.php";
}

// Get resource info and access, would usually be available in ../pages/view.php.
// Load resource data.
$resource = get_resource_data($ref);

if ($resource === false) {
    exit($lang['resourcenotfound']);
}

// Load resource field data.
$fields = get_resource_field_data($ref, false, !hook("customgetresourceperms"), null, $k != "", $use_order_by_tab_view);

$edit_access = get_edit_access($ref, $resource["archive"], $resource);

if ($k != "") {
    $edit_access = 0;
}

// If we are here, we have specifically requested it, so make sure it is displayed.
$geolocation_panel_only = true;

include "../../include/geocoding_view.php";
