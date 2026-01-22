<?php

command_line_only();

global $resource_deletion_state;
$test_resource = create_resource(1);

delete_resource($test_resource);
$test_resource_data = get_resource_data($test_resource, false);


switch ($test_resource) {
    case $test_resource_data["archive"] != $resource_deletion_state:
        // Check Resource is in the deleted state
        echo "Deleted resource not moved to deletion state";
        return false;
    case empty(ps_query("SELECT * FROM resource_log WHERE resource = ? AND type = 'x'", ["i",$test_resource])):
        // Check this was logged as resource deleted
        echo "Resource deletion not logged in resource log";
        return false;
}

delete_resource($test_resource);
$test_resource_data = get_resource_data($test_resource, false);

switch ($test_resource) {
    case $test_resource_data !== false:
        // Check Resource not in resource table
        echo "Deleted resource {$test_resource} not removed from resource table";
        return false;
    case empty(ps_query("SELECT * FROM resource_log WHERE resource = ? AND type = 'xx'", ["i",$test_resource])):
        // Check this was logged as resource deleted
        echo "Permanent resource deletion not logged in resource log";
        return false;
}

return true;
