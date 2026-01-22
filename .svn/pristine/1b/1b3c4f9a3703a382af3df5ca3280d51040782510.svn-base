<?php

include '../../include/boot.php';

$k = getval('k', '');
$upload_collection = getval('upload_share_active', '');
if ($k == "" || (!check_access_key_collection($upload_collection, $k))) {
    include "../../include/authenticate.php";
}

if (is_anonymous_user() && !upload_share_active()) {
    exit(escape($lang['error-permissiondenied']));
}

// Initialise
$ajax           = ('' != getval('ajax', '') ? true : false);
$node_ref       = getval('node_ref', null, true);
$field          = (int) getval('field', '', true);
$selected_nodes = getval('selected_nodes', [], false, 'is_array');
$opened_nodes   = array();
$js_tree_data   = array();

$nodes = array_filter(get_nodes($field, $node_ref), 'node_is_active');

// Find the ancestor nodes for any of the searched nodes
// Most of the nodes will most likely be a tree leaf.
// This allows us to know which tree nodes we need to
// expand from the beginning
foreach ($selected_nodes as $selected_node) {
    $tree_level = get_tree_node_level($selected_node);

    if (0 === $tree_level) {
        continue;
    }

    $found_all_parents = get_all_ancestors_for_node($selected_node, $tree_level);
    if (is_array($found_all_parents)) {
        foreach ($found_all_parents[0] as $p_key => $p_ref) {
            $opened_nodes[] = $p_ref;
        }
    }
}

foreach ($nodes as $node) {
    $node_opened = false;

    if (in_array($node['ref'], $opened_nodes)) {
        $node_opened = true;
    }

    $js_tree_data[] = array(
            'id'     => $node['ref'],
            'parent' => ('' == $node['parent'] ? '#' : $node['parent']),
            'text'   => escape(i18n_get_translated($node['name'])),
            'li_attr' => array(
                'title' => escape(i18n_get_translated($node['name'])),
                'class' => 'show_tooltip'
            ),
            'state'  => array(
                'opened'   => $node_opened,
                'selected' => in_array($node['ref'], $selected_nodes)
            ),
            'children' => is_parent_node($node['ref'], true)
        );
}

header('Content-Type: application/json');
echo json_encode($js_tree_data);
