<?php

# nodes_remove_duplicates.php

# Remove duplicate nodes from a specified fixed list type field.
# Script can only be run by an admin user.
# Example:  https://resourcespace/pages/tools/nodes_remove_duplicates.php?field=91
# Output from this function will include the ids of the duplicate nodes removed, the id of a single duplicate to become the single value,
# the node value without translation and the resource reference of any resources updated.

include_once '../../include/boot.php';
include_once '../../include/general_functions.php';
if (PHP_SAPI != 'cli') {
    include_once "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
}

$resource_type_field = getval("field", "");

if ($resource_type_field == "") {
    die('No field was specified for checking.');
}

# This script cannot be run for category tree type fields. Duplicates are expected in this field type.
$resource_type_field_data = get_resource_type_field($resource_type_field);
if ($resource_type_field_data['type'] == FIELD_TYPE_CATEGORY_TREE) {
    die('Duplicates are expected in category tree type fields and removing them could lead to data loss.');
}

if (!in_array($resource_type_field_data['type'], $FIXED_LIST_FIELD_TYPES)) {
    die('This script is not applicable to non fixed list field types.');
}

# Get all values for node
$all_node_values = get_field_options($resource_type_field, true);

# Return unique list and duplicate list
$filtered_node_values = array();
$duplicate_node_values = array();
foreach ($all_node_values as $node_value) {
    if (!in_array($node_value['name'], $filtered_node_values)) {
        $filtered_node_values[$node_value['ref']] = $node_value['name'];
    } else {
        $duplicate_node_values[$node_value['ref']] = $node_value['name'];
    }
}

if (count($duplicate_node_values) == 0) {
    die('No duplicate nodes were found in resource type field ' . escape($resource_type_field) . '.');
}

# If a duplicate node is found, before we remove it check for resources using it and update them to use the instance of the duplicate node being retained.
foreach ($duplicate_node_values as $duplicate_node_id => $duplicate_value) {
    $resource_ids = ps_query('select resource as `ref` from resource_node where node = ?;', ['i', $duplicate_node_id]);
    $block_duplicate_node_deletion = false;
    $replacement_node_id = array_search($duplicate_value, $filtered_node_values);
    foreach ($resource_ids as $resource) {
        $ref = $resource['ref'];
        $add_node = add_resource_nodes($ref, array($replacement_node_id), false, false);
        if ($add_node) {
            echo '<i>' . '-  Removed node ' . $duplicate_node_id . ' from resource ' . $ref . '</i><br>';
            delete_resource_nodes($ref, $duplicate_node_id, false);
        } else {
            # An error occurred trying to remove a node from a resource. We cannot remove that node completely to avoid loss of data.
            echo 'ERROR: Node ' . $duplicate_node_id . ' (\'' . escape($duplicate_value) . '\') could not be deleted from resource ' . $ref . ' Manually removed it from the resource and then re-run this script.' . '<br>';
            $block_duplicate_node_deletion = true;
        }
    }

    if (!$block_duplicate_node_deletion) {
        echo 'Removed node ' . $duplicate_node_id . ' (\'' . escape($duplicate_value) . '\') Now using node ID ' . $replacement_node_id . '<br><br>';
        delete_node($duplicate_node_id);
    }
}
