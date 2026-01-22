<?php

include __DIR__ . '/../../include/boot.php';
include __DIR__ . '/../../include/authenticate.php';

$resource = getval('resource', '');
$ref = getval('ref', '');
$type = getval('type', '');

$resource_data = get_resource_data($resource);

// User should have edit access to this resource!
if (!get_edit_access($resource, $resource_data['archive'], $resource_data)) {
    exit('Permission denied.');
}

if ($type == 'user') {
    // Delete the user record from the database
    ps_query(
        "
			DELETE FROM resource_custom_access 
				  WHERE resource = ?
					AND user = ?
		",
        array("i",$resource,"i",$ref)
    );
} elseif ($type == 'usergroup') {
    // Delete the user record from the database
    ps_query(
        "
			DELETE FROM resource_custom_access 
				  WHERE resource = ?
					AND usergroup = ?;
		",
        array("i",$resource,"i",$ref)
    );
} else {
    exit('No type');
}
