<?php

command_line_only();



// Add a new node
$resource_type_field = create_resource_type_field('nodes_testing', 1, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, 'nodes_testing', false);

$node_id_1 = set_node(null, $resource_type_field, 'New option added via test', null, null);
$node_id_2 = set_node(null, $resource_type_field, 'Option assigned order_by', null, 40);
$node_id_3 = set_node(null, $resource_type_field, '', null, 40);
$node_id_4 = set_node(null, $resource_type_field, null, null, null);
$node_id_5 = set_node(null, '', 'Option with resource_type_field invalid', null, null);
$node_id_6 = set_node(null, null, 'Option with resource_type_field invalid', null, null);
$node_id_7a = set_node(null, $resource_type_field, 'Same node name test', null, null);
$node_id_7b = set_node(null, $resource_type_field, 'Same node name test', null, null);
$node_id_7c = set_node(null, $resource_type_field, 'Same node name test', null, null);

// Have nodes been set? No other integrity check...
$current_node = array();
if (!get_node($node_id_1, $current_node) || !get_node($node_id_2, $current_node)) {
    return false;
}

// Check node (expecting parent empty and order by 10)
$current_node = array();
if (
    get_node($node_id_1, $current_node)
    && ($resource_type_field != $current_node['resource_type_field'] || 'New option added via test' != $current_node['name'] || '' != $current_node['parent'] || 10 != $current_node['order_by'])
) {
    return false;
}

// Check node (expecting parent empty and order by 40
$current_node = array();
if (
    get_node($node_id_2, $current_node)
    && ($resource_type_field != $current_node['resource_type_field'] || 'Option assigned order_by' != $current_node['name'] || '' != $current_node['parent'] || 40 != $current_node['order_by'])
) {
    return false;
}

// Check cases when either resource_type_field or name are invalid, hence returning false
if (false !== $node_id_3 || false !== $node_id_4 || false !== $node_id_5 || false !== $node_id_6) {
    return false;
}

// Check cases for multiple nodes with the same name
$i = 0;
foreach (get_nodes($resource_type_field, null, false, null, null, 'Same node name test') as $node) {
    if ('Same node name test' != $node['name']) {
        continue;
    }

    if (!in_array($node['ref'], array($node_id_7a, $node_id_7b, $node_id_7c))) {
        return false;
    }

    $i++;
}
// Something went wrong with creating these nodes
if (0 === $i) {
    return false;
}

// Creating some nodes to be used as parents or children
$resource_type_field = create_resource_type_field('nodes_testing_tree', 1, FIELD_TYPE_CATEGORY_TREE, 'nodes_testing_tree', false);

$parent_node_id_1 = set_node(null, $resource_type_field, 'Parent 1', null, null);
$parent_node_id_2 = set_node(null, $resource_type_field, 'Parent 2 without children', null, null);
$parent_node_id_3 = set_node(null, $resource_type_field, 'Parent 3', null, null);

$child_node_id_11 = set_node(null, $resource_type_field, 'Child 1.1', $parent_node_id_1, null);
$child_node_id_12 = set_node(null, $resource_type_field, 'Child 1.2', $parent_node_id_1, null);
$child_node_id_13 = set_node(null, $resource_type_field, 'Child 1.3', $parent_node_id_1, null);
$child_node_id_31 = set_node(null, $resource_type_field, 'Child 3.1', $parent_node_id_3, null);
$child_node_id_32 = set_node(null, $resource_type_field, 'Child 3.2', $parent_node_id_3, null);
$child_node_id_33 = set_node(null, $resource_type_field, 'Child 3.3', $parent_node_id_3, null);

// Duplicate check - allow for different parents only.
$child_node_id_34 = set_node(null, $resource_type_field, 'Child 1.1', $parent_node_id_3, null);
$child_node_id_35 = set_node(null, $resource_type_field, 'Child 3.3', $parent_node_id_3, null);

$parent_children_nodes = array(
    $parent_node_id_1,
    $parent_node_id_2,
    $parent_node_id_3,
    $child_node_id_11,
    $child_node_id_12,
    $child_node_id_13,
    $child_node_id_31,
    $child_node_id_32,
    $child_node_id_33
);

if (in_array($child_node_id_34, $parent_children_nodes) || !in_array($child_node_id_35, $parent_children_nodes)) {
    return false;
}

$parent_children_nodes[] = $child_node_id_34;

foreach (get_nodes($resource_type_field, null, true) as $nodes) {
    if (!in_array($nodes['ref'], $parent_children_nodes)) {
        return false;
    }
}

// TODO: check set_node() for updating capabilities (names, orders)

return true;
