<?php

command_line_only();


// Test edit filters in a similar way to search filters

// Save current settings
$saved_edit_filter = $usereditfilter;
$saved_user = $userref;

function test_edit_filter_text_update($user, $group, $filtertext)
{
    global $userdata,$udata_cache;
    $udata_cache = array();
    save_usergroup($group, array('edit_filter' => $filtertext, 'edit_filter_id' => 0));
    $userdata = get_user($user);
    setup_user($userdata);
    $userdata = [$userdata];
}

function test_edit_filter_id_update($user, $group, $filterid)
{
    global $userdata,$udata_cache;
    $udata_cache = array();
    save_usergroup($group, array('edit_filter_id' => $filterid));
    $userdata = get_user($user);
    setup_user($userdata);
    $userdata = [$userdata];
}
// Create a new user in a new group so we can test edit access for resources
$editor = new_user("editor");
$superadmin_permissions = ps_value('SELECT permissions `value` FROM usergroup WHERE ref = ?;', array('i', 3), 's,g,c,a,t,h,hdt_ug,u,r,i,e-2,e-1,e0,e1,e2,e3,o,m,g,v,q,f*,j*,k,R,Ra,x,ex');
$usergroup_values = array(
    'name' => 'testeditgroup',
    'permissions' => $superadmin_permissions,
    'edit_filter' => '',
    'derestrict_filter' => '',
    'edit_filter_id' => 0,
    'derestrict_filter_id' => 0
    );
$testeditgroup = save_usergroup(0, $usergroup_values);
user_set_usergroup($editor, $testeditgroup);

// create 5 new resources
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(2, 0);
$resourced = create_resource(2, 0);
$resourcee = create_resource(2, 0);

// create new 'Edit Department' field
$editdepartmentfield = create_resource_type_field("Edit Department", 0, FIELD_TYPE_CHECK_BOX_LIST, "editdepartment");

// create new 'Colour' field
$colourfield = create_resource_type_field("Colour", 0, FIELD_TYPE_DROP_DOWN_LIST, "colour");

// Add new nodes to fields
$salesnode  = set_node(null, $editdepartmentfield, "Sales", '', 1000);
$itnode     = set_node(null, $editdepartmentfield, "IT", '', 1000);
$rednode    = set_node(null, $colourfield, "Red", '', 1000);
$bluenode   = set_node(null, $colourfield, "Blue", '', 1000);

add_resource_nodes($resourcea, array($salesnode, $rednode));
add_resource_nodes($resourceb, array($salesnode, $itnode, $rednode));
add_resource_nodes($resourcec, array($itnode, $rednode));
add_resource_nodes($resourced, array($bluenode, $itnode));
add_resource_nodes($resourcee, array($itnode,$rednode,$bluenode));

// SUBTEST A: old style edit filter migrated
$usereditfilter = "editdepartment=Sales;colour=Red";
test_edit_filter_text_update($editor, $testeditgroup, $usereditfilter);
$migrateresult = migrate_filter($usereditfilter);
test_edit_filter_id_update($editor, $testeditgroup, $migrateresult);
$results = do_search('', '', '', 0, -1, 'desc', false, 0, false, false, '', false, false, true, true);
if (
    count($results) != 2 || !isset($results[0]['ref'])
    ||
    !match_values(array_column($results, 'ref'), array($resourcea,$resourceb))
) {
    echo "SUBTEST A";
    return false;
}

// SUBTEST B: Check get_edit_access() after migration
$accessa = get_edit_access($resourcea);
$accessb = get_edit_access($resourceb);
$accessc = get_edit_access($resourcec);
$accessd = get_edit_access($resourced);
$accesse = get_edit_access($resourcee);
if (!$accessa || !$accessb || $accessc || $accessd || $accesse) {
    echo "SUBTEST B";
    return false;
}

// SUBTEST C: old style edit filter with resource_type - migrated to new code
$usereditfilter = "editdepartment=IT;resource_type=1";
test_edit_filter_text_update($editor, $testeditgroup, $usereditfilter);
$filtertext = edit_filter_to_restype_permission($usereditfilter, $testeditgroup, $userpermissions);
$migrateresult = migrate_filter($filtertext);
test_edit_filter_id_update($editor, $testeditgroup, $migrateresult);

$results = do_search('', '', '', 0, -1, 'desc', false, 0, false, false, '', false, false, false, true);
if (
    count($results) != 1 || !isset($results[0]['ref'])
    ||
    !match_values(array_column($results, 'ref'), array($resourceb))
) {
    echo "SUBTEST C";
    return false;
}

// SUBTEST D: Check get_edit_access()
$accessa = get_edit_access($resourcea);
$accessb = get_edit_access($resourceb);
$accessc = get_edit_access($resourcec);
$accessd = get_edit_access($resourced);
$accesse = get_edit_access($resourcee);
if ($accessa || !$accessb || $accessc || $accessd || $accesse) {
    echo "SUBTEST D";
    return false;
}

// Reset saved settings
$usereditfilter = $saved_edit_filter;
$userdata = get_user($saved_user);
setup_user($userdata);

return true;
