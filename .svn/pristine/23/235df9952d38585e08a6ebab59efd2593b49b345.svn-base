<?php

command_line_only();

$resourcenew = create_resource(1, 0, -1, "000200_create_resource");
# Did it work?
if (!get_resource_data($resourcenew) !== false) {
    echo "Resource not created\n";
    return false;
}

# Check that this was logged including the origin
$resource_log = get_resource_log($resourcenew, -1, ["r.type" => 'c']);
$logged = false;
foreach ($resource_log["data"] as $log) {
    if ("000200_create_resource" == $log["notes"]) {
        $logged = true;
    }
}

if (!$logged) {
    echo "Resource origin not logged\n";
    return false;
}

return true;
