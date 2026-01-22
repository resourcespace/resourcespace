<?php

command_line_only();

$original_user_data = $userdata;

$setoptions = [
    "name" => "External share group",
    "permissions" => "s,g,t,h,r,i,v,q,R,Ra,x",
];

$usergroup = save_usergroup(0, $setoptions);
$userref = new_user('External share user', $usergroup);

$resource = create_resource(1, 0, $userref);

$collection = create_collection($userref, 'External share collection');
add_resource_to_collection($resource, $collection);

$original_config = $GLOBALS['resource_request_reason_required'];
$GLOBALS['resource_request_reason_required'] = false;
$original_lang = $GLOBALS['lang']['requestcollection'];
$GLOBALS['lang']['requestcollection'] = '@@RS_TEST@@';

include_once __DIR__ . "/../../include/request_functions.php";
email_collection_request($collection, '', 'test@test.test');
$request_collection = ps_value('SELECT ref `value` FROM collection WHERE name = ?', ['s', '@@RS_TEST@@'], 0);

$use_cases = [
    [
        'name'              => 'Request managing group does not have access to all collections',
        'usergroup-options' => ["permissions" => "s,g,t,h,r,i,v,q,R,Ra,x"],
        'expected-count'    => 1
    ]
];

foreach ($use_cases as $use_case) {

    save_usergroup($usergroup, $use_case['usergroup-options']);
    setup_user(get_user($userref));

    $result = do_search("!collection{$request_collection}");
    if (count($result) != $use_case['expected-count']) {
        echo $use_case['name'] . ' - ';
        return false;
    }

}

$GLOBALS['lang']['requestcollection'] = $original_lang;
$GLOBALS['resource_request_reason_required'] = $original_config;
setup_user($original_user_data);