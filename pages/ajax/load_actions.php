<?php

include '../../include/boot.php';
include '../../include/authenticate.php';

$forpage = getval('page', '');
$type = getval('actiontype', '');
$ref = getval('ref', '', true);

switch ($type) {
    case "collection":
        hook('render_themes_list_tools', '', $ref);
        $collection_data = get_collection($ref);
        render_actions($collection_data, false, false, $ref, array(), true, $forpage);
        break;

    case "selection_collection":
        render_selected_collection_actions();
        break;

    case "search":
        $search = getval("search", "");
        $restypes = getval("restypes", "");
        $order_by = getval("order_by", "relevance");
        $archive = getval("archive", "0");
        $per_page = getval("per_page", null, true);
        $offset = getval("offset", null, true);
        $fetchrows = (!is_null($per_page) || !is_null($offset) ? $per_page + $offset : -1);
        $sort = getval("sort", "desc");
        $recent_search_daylimit = getval("recent_search_daylimit", "");
        $go = getval("go", "");
        $editable_only = getval("foredit", "") == "true";

        $result = do_search(
            $search,
            $restypes,
            $order_by,
            $archive,
            $fetchrows,
            $sort,
            false,
            DEPRECATED_STARSEARCH,
            false,
            false,
            $recent_search_daylimit,
            $go,
            true,
            false,
            $editable_only
        );
        $resources_count = is_array($result) ? count($result) : 0;
        // Is this a collection search?
        $collectiondata = array();
        $collection_search_strpos = strpos($search, "!collection");
        $collectionsearch = $collection_search_strpos !== false && $collection_search_strpos === 0; // We want the default collection order to be applied
        if ($collectionsearch) {
            // Collection search may also have extra search keywords passed to search within a collection
            $search_trimmed = substr($search, 11); // The collection search must always be the first part of the search string
            $search_elements = split_keywords($search_trimmed, false, false, false, false, true);
            $collection = (int)array_shift($search_elements);
            $search = "!collection" . $collection . " " . implode(", ", $search_elements);
            $collectiondata = get_collection($collection);
        }

        render_actions($collectiondata, true, false);
        break;

    case "resource":
        break;
}
