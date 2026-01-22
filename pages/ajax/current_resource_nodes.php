<?php

# Ajax page to return all fixed list field nodes for a given field in the search results (collection or search).
# This information is then displayed when editing multiple resources to provide a sample of the options in use already.

include "../../include/boot.php";
include "../../include/authenticate.php";

$ajax = filter_var(getval('ajax', false), FILTER_VALIDATE_BOOLEAN);
if (!$ajax) {
    header('HTTP/1.1 400 Bad Request');
    die('AJAX only accepted!');
}

$search = getval('search', '', false);
$restypes = getval('restypes', '', false);
$archive = getval('archive', false);
$field = getval('field', '', false, 'is_numeric');
$question_ref = getval('question', '', false, 'is_numeric');

if ($field == '' || $question_ref == '') {
    exit();
}

if (!$archive) {
    $archive = 0;
}

$field = (int) $field;
if (checkperm("F" . $field) || (checkperm("F*") && !checkperm("F-" . $field))) {
    exit();
}

$resources = do_search($search, $restypes, 'resourceid', $archive, -1, 'ASC', false, 0, false, false, '', false, false, true, true, false);
$resources = array_column($resources, 'ref');

$all_selected_nodes = get_resource_nodes_batch($resources, array($field), true);

$sorted_nodes = array();
foreach ($resources as $resource) {
    if (isset($all_selected_nodes[$resource])) {
        $resource_nodes = $all_selected_nodes[$resource][$field];
        foreach ($resource_nodes as $node) {
            if (array_key_exists($node['name'], $sorted_nodes)) {
                $sorted_nodes[$node['name']]++;
            } else {
                $sorted_nodes[$node['name']] = 1;
            }
        }
    }
}

$total_nodes_count = count($sorted_nodes);
if ($total_nodes_count > 0) {
    asort($sorted_nodes, SORT_NUMERIC);
    $sorted_nodes = array_reverse($sorted_nodes, true);
    $sorted_nodes = array_slice($sorted_nodes, 0, 100, true);
    $return = '';
    $show = 1; # Only show the first 5 results.
    foreach ($sorted_nodes as $node_to_return => $count) {
        $return .= '<div class="currenteditmulti keywordselected currentmultiquestion' . (int) $question_ref . '"';
        if ($show > 5) {
            $return .= 'style="display:none;"';
        }
        $return .= '>' . escape($node_to_return) . " ($count)</div>";
        $show++;
    }

    if ($total_nodes_count > 100) {
        $return .=  '<div class="currentmultiquestion' . (int) $question_ref . '" style="display:none;">' . escape($lang["edit_multiple_too_many"]) . '</div>';
    }

    echo $return;
}

exit();
