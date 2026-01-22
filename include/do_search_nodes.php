<?php

// Node filtering for main search

$node_bucket_sql = "";
$rn = 0;
$node_hitcount = "";
foreach ($node_bucket as $node_bucket_or) {
    if ($category_tree_search_use_and_logic) {
        foreach ($node_bucket_or as $node_bucket_and) {
            $sql_join->sql .= ' JOIN `resource_node` rn' . $rn . ' ON r.`ref`=rn' . $rn . '.`resource` AND rn' . $rn . '.`node` = ?';
            array_push($sql_join->parameters, "i", $node_bucket_and);
            $node_hitcount .= (($node_hitcount != "") ? " +" : "") . "rn" . $rn . ".hit_count";
            $rn++;
        }
    } else {
        $sql_join->sql .= ' JOIN `resource_node` rn' . $rn . ' ON r.`ref`=rn' . $rn . '.`resource` AND rn' . $rn . '.`node` IN (' . ps_param_insert(count($node_bucket_or)) . ')';
        $sql_join->parameters = array_merge($sql_join->parameters, ps_param_fill($node_bucket_or, "i"));
        $node_hitcount .= (($node_hitcount != "") ? " +" : "") . "rn" . $rn . ".hit_count";
        $rn++;
    }
}
if ($node_hitcount != "") {
    $sql_hitcount_select = "(SUM(" . $sql_hitcount_select . ") + SUM(" . $node_hitcount . ")) ";
}

$select->sql .= ", " . $sql_hitcount_select . " total_hit_count";

$sql_filter->sql  = $node_bucket_sql . $sql_filter->sql;

if (count($node_bucket_not) > 0) {
    $sql_filter->sql = 'NOT EXISTS (SELECT `resource`, node FROM `resource_node` WHERE r.ref=`resource` AND `node` IN (' .
    ps_param_insert(count($node_bucket_not)) . ')) AND ' . $sql_filter->sql;
    $sql_filter->parameters = array_merge(ps_param_fill($node_bucket_not, "i"), $sql_filter->parameters);
}
