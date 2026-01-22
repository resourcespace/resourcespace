<?php

command_line_only();

// Test to ensure that $editable_only search returns all relevant resources that can be edited.

$saved_userref = $userref;
$userref = 999;
$savedpermissions = $userpermissions;
// create 5 new resources
// 2 of type 1 (1 active and 1 pending review)
// 2 of type 2 (1 active and 1 pending review)
// 1 of type 3
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, -1);
$resourcec = create_resource(2, 0);
$resourced = create_resource(2, -1);
$resourcee = create_resource(3, 0);

debug("BANG Resource A: " . $resourcea);
debug("BANG Resource B: " . $resourceb);
debug("BANG Resource C: " . $resourcec);
debug("BANG Resource D: " . $resourced);
debug("BANG Resource E: " . $resourcee);

// Add text to free text to fields
update_field($resourcea, 'title', 'test_000975_A');
update_field($resourceb, 'title', 'test_000975_B');
update_field($resourcec, 'title', 'test_000975_C');
update_field($resourced, 'title', 'test_000975_D');
update_field($resourcee, 'title', 'test_000975_E');

// Set dummy nodes
$dummynode = set_node(null, 73, 'test000975', '', 1000);
add_resource_nodes($resourcea, array($dummynode));
add_resource_nodes($resourceb, array($dummynode));
add_resource_nodes($resourcec, array($dummynode));
add_resource_nodes($resourced, array($dummynode));
add_resource_nodes($resourcee, array($dummynode));

$userref = 1;
// SUBTEST A
// ----- Admin permission ('v') -----
// All relevant resources should be shown
/*
+----------+--------+---------------+------+-------------+
| Resource | Search | Search Active | Edit | Edit Active |
+----------+--------+---------------+------+-------------+
| A        | Yes    | Yes           | Yes  | Yes         |
| B        | Yes    | No            | Yes  | No          |
| C        | Yes    | Yes           | Yes  | Yes         |
| D        | Yes    | No            | Yes  | No          |
| E        | Yes    | Yes           | Yes  | Yes         |
+----------+--------+---------------+------+-------------+
| Total    | 5      | 3             | 5    | 3           |
+----------+--------+---------------+------+-------------+
*/
$userpermissions = array("v","s","e0","e-1","f*");
// Search Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc"); //this should return three assets: A, C, E

if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST A.i\n";
    return false;
}

// Search Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc"); //this should return all assets
if (!is_array($results) || count($results) != 5 || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourced,$resourcee))) {
    echo "ERROR BANG- SUBTEST A.ii\n";
    echo "Count: " . count($results) . "\n";
    foreach ($results as $result) {
        echo $result['ref'] . " ";
    }
    echo "\n";
    return false;
}


// Editable Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST A.iii\n";
    return false;
}
// Editable Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc", false, 0, false, false, '', false, false, false, false);// this should return three assets
if (!is_array($results) || count($results) != 5 || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourced,$resourcee))) {
    echo "ERROR - SUBTEST A.iv\n";
    return false;
}
// END SUBTEST A



// SUBTEST B
// ------ Force edit access permission (ert1) ----
// Should see as admin for resource type 1, general user for other resources
/*
+----------+--------+---------------+------+-------------+
| Resource | Search | Search Active | Edit | Edit Active |
+----------+--------+---------------+------+-------------+
| A        | Yes    | Yes           | Yes  | Yes         |
| B        | Yes    | No            | Yes  | No          |
| C        | Yes    | Yes           | Yes  | Yes         |
| D        | Yes    | No            | No   | No          |
| E        | Yes    | Yes           | Yes  | Yes         |
+----------+--------+---------------+------+-------------+
| Total    | 5      | 3             | 4    | 3           |
+----------+--------+---------------+------+-------------+
*/
$userpermissions = array("ert1","s","e0","e-1","f*");

// Search Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc"); //this should return three assets: A, C, E
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST B.i\n";
    return false;
}
// Search Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc"); //this should return four assets
if (!is_array($results) || count($results) != 4 || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST B.ii\n";
    return false;
}

// Editable Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST B.iii\n";
    return false;
}
// Editable Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return four assets
if (!is_array($results) || count($results) != 4 || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST B.iv\n";
    return false;
}

// END SUBTEST B
// --------------------------------------


// SUBTEST C
// ------ Force edit access, block pending review access ('ert1,e0,z-1') ----
// Should see all resources
/*
+----------+--------+---------------+------+-------------+
| Resource | Search | Search Active | Edit | Edit Active |
+----------+--------+---------------+------+-------------+
| A        | Yes    | Yes           | Yes  | Yes         |
| B        | No     | No            | No   | No          |
| C        | Yes    | Yes           | Yes  | Yes         |
| D        | No     | No            | No   | No          |
| E        | Yes    | Yes           | Yes  | Yes         |
+----------+--------+---------------+------+-------------+
| Total    | 3      | 3             | 3    | 3           |
+----------+--------+---------------+------+-------------+
*/
$userpermissions = array("ert1","e0","z-1","f*");
// Search Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc"); //this should return three assets: A, C, E
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST C.i\n";
    return false;
}
// Search Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc"); //this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST C.ii\n";
    return false;
}

// Editable Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST C.iii\n";
    echo count($results);
    return false;
}
// Editable Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST C.iv\n";
    return false;
}

// SUBTEST D
// ------ Force edit access, restrict access to pending review ('ert1,rws-1') ----
//
/*
+----------+--------+---------------+------+-------------+
| Resource | Search | Search Active | Edit | Edit Active |
+----------+--------+---------------+------+-------------+
| A        | Yes    | Yes           | Yes  | Yes         |
| B        | Yes    | No            | Yes  | No          |
| C        | Yes    | Yes           | Yes  | Yes         |
| D        | No     | No            | No   | No          |
| E        | Yes    | Yes           | Yes  | Yes         |
+----------+--------+---------------+------+-------------+
| Total    | 4      | 3             | 4    | 3           |
+----------+--------+---------------+------+-------------+
*/
$userpermissions = array("ert1","e0","e-1","rws-1","f*");
// Search Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc"); //this should return three assets: A, C, E
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST D.i\n";
    return false;
}
// Search Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc"); //this should return four assets
if (!is_array($results) || count($results) != 4 || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST D.ii\n";
    return false;
}

// Editable Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST D.iii\n";
    return false;
}
// Editable Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return four assets
if (!is_array($results) || count($results) != 4 || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST D.iv\n";
    return false;
}

// SUBTEST E
// ------ Edit access ('e0,e-1') ----
//
/*
+----------+--------+---------------+------+-------------+
| Resource | Search | Search Active | Edit | Edit Active |
+----------+--------+---------------+------+-------------+
| A        | Yes    | Yes           | Yes  | Yes         |
| B        | No     | No            | No   | No          |
| C        | Yes    | Yes           | Yes  | Yes         |
| D        | No     | No            | No   | No          |
| E        | Yes    | Yes           | Yes  | Yes         |
+----------+--------+---------------+------+-------------+
| Total    | 3      | 3             | 3    | 3           |
+----------+--------+---------------+------+-------------+
*/
$userpermissions = array("e0","e-1","f*");
// Search Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc"); //this should return three assets: A, C, E
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST E.i\n";
    return false;
}
// Search Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc"); //this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST E.ii\n";
    return false;
}

// Editable Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST E.iii\n";
    return false;
}
// Editable Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST E.iv\n";
    return false;
}

// SUBTEST F
// ------ Edit access, block access to pending review ('e0,e-1,z-1') ----
//
/*
+----------+--------+---------------+------+-------------+
| Resource | Search | Search Active | Edit | Edit Active |
+----------+--------+---------------+------+-------------+
| A        | Yes    | Yes           | Yes  | Yes         |
| B        | No     | No            | No   | No          |
| C        | Yes    | Yes           | Yes  | Yes         |
| D        | No     | No            | No   | No          |
| E        | Yes    | Yes           | Yes  | Yes         |
+----------+--------+---------------+------+-------------+
| Total    | 3      | 3             | 3    | 3           |
+----------+--------+---------------+------+-------------+
*/
$userpermissions = array("e0","e-1","z-1","f*");
$results = do_search('000975', '', '', 0, -1, "desc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
// Search Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc"); //this should return three assets: A, C, E
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.i\n";
    return false;
}
// Search Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc"); //this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.ii\n";
    return false;
}

// Editable Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.iii\n";
    return false;
}
// Editable Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.iv\n";
    return false;
}

// SUBTEST G
// ------ Edit access, restrict access to pending review ('e0,e-1,rws-1') ----
//
/*
+----------+--------+---------------+------+-------------+
| Resource | Search | Search Active | Edit | Edit Active |
+----------+--------+---------------+------+-------------+
| A        | Yes    | Yes           | Yes  | Yes         |
| B        | No     | No            | No   | No          |
| C        | Yes    | Yes           | Yes  | Yes         |
| D        | No     | No            | No   | No          |
| E        | Yes    | Yes           | Yes  | Yes         |
+----------+--------+---------------+------+-------------+
| Total    | 3      | 3             | 3    | 3           |
+----------+--------+---------------+------+-------------+
*/
$userpermissions = array("e0","e-1","rws-1","f*");
$results = do_search('000975', '', '', 0, -1, "desc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
// Search Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc"); //this should return three assets: A, C, E
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.i\n";
    return false;
}
// Search Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc"); //this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.ii\n";
    return false;
}

// Editable Results, active resources
$results = do_search("subject:test000975", "", "ref", "0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.iii\n";
    return false;
}
// Editable Results, all resources
$results = do_search("subject:test000975", "", "ref", "-1,0", -1, "asc", false, 0, false, false, '', false, false, false, true);  // this should return three assets
if (!is_array($results) || count($results) != 3 || !match_values(array_column($results, 'ref'), array($resourcea,$resourcec,$resourcee))) {
    echo "ERROR - SUBTEST F.iv\n";
    return false;
}

$userref = $saved_userref;
$userpermissions = $savedpermissions;
