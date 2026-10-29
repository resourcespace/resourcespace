<?php

command_line_only();

$start_restypes = get_resource_types();
$new_restype = create_resource_type("newtype");

$fields = get_resource_type_fields([0,$new_restype]);
$initial_count = count($fields);

// Create new global field
$new_field = create_resource_type_field("new field", 0);

$fields = get_resource_type_fields([0,$new_restype]);

$cur_count = count($fields);
if ($cur_count != $initial_count + 1) {
    // The new field hasn't been applied to the new resource type
    echo "SUBTEST A";
    return false;
}


// Update the new field to not apply to the new resource type
update_resource_type_field_resource_types($new_field, array_column($start_restypes, "ref"));

$fields = get_resource_type_fields([0,$new_restype]);
$cur_count = count($fields);
if ($cur_count != $initial_count) {
    // The new field is still being applied to the new resource type
    echo "SUBTEST B";
    return false;
}


// Reset Video resource type to the original state:

return true;
