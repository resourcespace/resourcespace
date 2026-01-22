<?php

include_once "../include/boot.php";
include "../include/authenticate.php";

$smart_theme = getval("smart_theme", 0, true);

if ($smart_theme > 0) {
    $node = getval("node", 0, true);
    $smart_redirect_params = array(
        "smart_rtf" => $smart_theme,
        "smart_fc_parent" => ($node > 0 ? $node : ""),
    );
    redirect(generateURL("{$baseurl}/pages/collections_featured.php", $smart_redirect_params));
}

$theme_category_levels = (isset($theme_category_levels) ? $theme_category_levels : 3);
$themes = GetThemesFromRequest($theme_category_levels);
$last_theme_found = array_slice($themes, -1);
$find_by_last_theme_name = array_pop($last_theme_found);

if (is_null($find_by_last_theme_name)) {
    redirect("{$baseurl}/pages/collections_featured.php");
}

$found_fc_categ_refs = ps_array(
    "SELECT DISTINCT c.ref AS `value`
             FROM collection AS c
        LEFT JOIN collection_resource AS cr ON c.ref = cr.collection
            WHERE c.`type`= ?
              AND c.`name` = ?
         GROUP BY c.ref
           HAVING count(DISTINCT cr.resource) = 0",
    array("s",COLLECTION_TYPE_FEATURED,"s",$find_by_last_theme_name)
);

$redirect_params = array();

foreach ($found_fc_categ_refs as $found_fc_categ_ref) {
    $found_branch_path = get_featured_collection_category_branch_by_leaf($found_fc_categ_ref, array());
    $found_branch_path = array_column($found_branch_path, "name");

    if ($themes === $found_branch_path) {
        $redirect_params["parent"] = $found_fc_categ_ref;
        break;
    }
}

redirect(generateURL("{$baseurl}/pages/collections_featured.php", $redirect_params));
