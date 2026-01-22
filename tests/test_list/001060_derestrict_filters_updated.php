<?php

command_line_only();


// Test derestrict filters
$saved_edit_filter = $usereditfilter;
$saved_user = $userref;
$original_user_data = $userdata;

function test_derestrict_filter_text_update($user, $group, $filtertext)
{
    global $userdata,$udata_cache;
    $udata_cache = array();
    save_usergroup($group, array('derestrict_filter' => $filtertext, 'derestrict_filter_id' => 0));
    $userdata = get_user($user);
    setup_user($userdata);
    $userdata = [$userdata];
}

function test_derestrict_filter_id_update($user, $group, $filterid)
{
    global $userdata,$udata_cache;
    $udata_cache = array();
    save_usergroup($group, array('derestrict_filter_id' => $filterid));
    
    $userdata = get_user($user);
    setup_user($userdata);
    $userdata = [$userdata];
}

// Set permissions to restrict access to all resources
$derestrictuser = new_user("derestricted");
$usergroup_values = array(
    'name' => 'testeditgroup',
    'permissions' => 's,e0,f*',
    'edit_filter' => '',
    'derestrict_filter' => '',
    'edit_filter_id' => 0,
    'derestrict_filter_id' => 0
    );
$testderestrictgroup = save_usergroup(0, $usergroup_values);
user_set_usergroup($derestrictuser, $testderestrictgroup);


// create 5 new resources
$resourcea  = create_resource(1, 0);
$resourceb  = create_resource(1, 0);
$resourcec  = create_resource(2, 0);
$resourced  = create_resource(2, 0);
$resourcee  = create_resource(2, 0);
$resourcef  = create_resource(2, 0);

$regionfield = create_resource_type_field("Region", 0, FIELD_TYPE_CHECK_BOX_LIST, "region");
$classificationfield = create_resource_type_field("Classification", 0, FIELD_TYPE_DROP_DOWN_LIST, "classification");

// Add new nodes to fields
$emeanode       = set_node(null, $regionfield, "EMEA", '', 1000);
$apacnode       = set_node(null, $regionfield, "APAC", '', 1000);
$americasnode   = set_node(null, $regionfield, "Americas", '', 1000);
$sensitivenode  = set_node(null, $classificationfield, "Sensitive", '', 1000);
$opennode       = set_node(null, $classificationfield, "Open", '', 1000);
$topsecretnode  = set_node(null, $classificationfield, "Top Secret", '', 1000);

add_resource_nodes($resourcea, array($emeanode, $sensitivenode));
add_resource_nodes($resourceb, array($emeanode, $opennode));
add_resource_nodes($resourcec, array($emeanode, $topsecretnode));
add_resource_nodes($resourced, array($apacnode, $sensitivenode));
add_resource_nodes($resourcee, array($apacnode,$opennode));
add_resource_nodes($resourcef, array($americasnode,$topsecretnode));

// SUBTEST A: old style derestrict filter migrated
$userderestrictfilter = "classification=Open;region=EMEA";
test_derestrict_filter_text_update($derestrictuser, $testderestrictgroup, $userderestrictfilter);
$migrateresult = migrate_filter($userderestrictfilter);
test_derestrict_filter_id_update($derestrictuser, $testderestrictgroup, $migrateresult);

$openaccessa = get_resource_access($resourcea) == 0;
$openaccessb = get_resource_access($resourceb) == 0;
$openaccessc = get_resource_access($resourcec) == 0;
$openaccessd = get_resource_access($resourced) == 0;
$openaccesse = get_resource_access($resourcee) == 0;
$openaccessf = get_resource_access($resourcef) == 0;
if ($openaccessa || !$openaccessb || $openaccessc || $openaccessd || $openaccesse || $openaccessf) {
    echo "SUBTEST B";
    return false;
}

// Reset saved settings
$userdata = $original_user_data;
setup_user($original_user_data);

return true;
