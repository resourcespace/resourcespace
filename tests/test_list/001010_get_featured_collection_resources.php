<?php

command_line_only();

// Setup
$collection_allow_not_approved_share_cache = $GLOBALS["collection_allow_not_approved_share"];

$original_user_data = $userdata;
$user_admin = new_user("test_001401_admin", 1);
if ($user_admin === false) {
    $user_admin = ps_value("SELECT ref AS `value` FROM user WHERE username = 'test_001408_admin'", array(), 0);
}
if ($user_admin === 0) {
    echo "Setup test: users - ";
    return false;
}
$user_admin_data = get_user($user_admin);
$orig_userpermissions = $userpermissions;
$userpermissions = ["s","j*","f*","e0","e3","c","D","h"];

$resources["active"][]  = create_resource(1, 0);
$resources["active"][]  = create_resource(1, 0);
$resources["deleted"][] = create_resource(1, 3);
$resources["deleted"][] = create_resource(1, 3);
$resources["all"] = array_merge($resources["active"], $resources["deleted"]);

$parent                 = create_collection($user_admin, "test_001408_parent");
$collections["all"]     = create_collection($user_admin, "test_001408_all");
$collections["active"]  = create_collection($user_admin, "test_001408_active");
$collections["deleted"] = create_collection($user_admin, "test_001408_deleted");

ps_query(
    "UPDATE collection 
        SET 
            public=1,
            type=3,
            thumbnail_selection_method=1,
            allow_changes=1
            WHERE ref IN (?," . ps_param_insert(count($collections)) . ")",
    array_merge(["i",$parent], ps_param_fill($collections, "i"))
);

ps_query(
    "UPDATE collection 
        SET 
            parent = ?
            WHERE ref IN (" . ps_param_insert(count($collections)) . ")",
    array_merge(["i",$parent], ps_param_fill($collections, "i"))
);

collection_add_resources($collections["all"], $resources["all"]);
collection_add_resources($collections["active"], $resources["active"]);
collection_add_resources($collections["deleted"], $resources["deleted"]);

$fcs = get_featured_collections($parent, []);
foreach ($fcs as $fc) {
    $fcs_sorted[$fc["ref"]] = $fc;
}

$use_cases = [
    [
        "name" => "01 - Only approved from all",
        "collection" => $fcs_sorted[$collections["all"]],
        "result"  => $resources["active"],
        "collection_allow_not_approved_share" => false,
    ],
    [
        "name" => "02 - All from all",
        "collection" => $fcs_sorted[$collections["all"]],
        "result"  => $resources["all"],
        "collection_allow_not_approved_share" => true,
    ],
    [
        "name" => "03 - Only approved from active",
        "collection" => $fcs_sorted[$collections["active"]],
        "result"  => $resources["active"],
        "collection_allow_not_approved_share" => false,
    ],
    [
        "name" => "04 - All from active",
        "collection" => $fcs_sorted[$collections["active"]],
        "result"  => $resources["active"],
        "collection_allow_not_approved_share" => true,
    ],
    [
        "name" => "05 - Only approved from deleted",
        "collection" => $fcs_sorted[$collections["deleted"]],
        "result"  => [],
        "collection_allow_not_approved_share" => false,
    ],
    [
        "name" => "06 - All from deleted",
        "collection" => $fcs_sorted[$collections["deleted"]],
        "result"  => $resources["deleted"],
        "collection_allow_not_approved_share" => true,
    ],
    [
        "name" => "07 - Only approved from parent",
        "collection" => get_featured_collections(0, [])[0],
        "result" => $resources["active"],
        "collection_allow_not_approved_share" => false,
    ],
    [
        "name" => "08 - All from parent",
        "collection" => get_featured_collections(0, [])[0],
        "result" => $resources["all"],
        "collection_allow_not_approved_share" => true,
    ],
];


// Test
foreach ($use_cases as $use_case) {
    $collection_allow_not_approved_share = $use_case["collection_allow_not_approved_share"];
    $result = get_featured_collection_resources($use_case["collection"], []);
    sort($result);
    sort($use_case["result"]);
    if ($result != $use_case["result"]) {
        echo "Use case: " . $use_case['name'] . " - ";
        return false;
    }
    unset($GLOBALS["CACHE_FC_RESOURCES"]);
}

// Tear Down
$collection_allow_not_approved_share = $collection_allow_not_approved_share_cache;
$user_data = $original_user_data;
unset($original_user_data);
$userpermissions = $orig_userpermissions;
unset($orig_userpermissions, $use_cases);

return true;
