<?php

include_once '../../include/boot.php';
include_once '../../include/authenticate.php';

// Browse bar action endpoint
$action = getval('action', '');
$resource   = getval("resource", 0, true);
$return = array();
$return['status'] = 400; // set to default

if (enforcePostRequest("browse_action")) {
    switch ($action) {
        case 'add_node':
            $node       = getval("node", 0, true);
            $nodeinfo = array();
            get_node($node, $nodeinfo);
            $field = get_resource_type_field($nodeinfo["resource_type_field"]);

            if (!get_edit_access($resource) || !metadata_field_edit_access($nodeinfo["resource_type_field"]) || !in_array($field["type"], $FIXED_LIST_FIELD_TYPES)) {
                $return['status'] = 400;
                $return['message'] = $lang["error-permissiondenied"];
                break;
            }

            // Check valid change
            $curnodes = get_resource_nodes($resource, $nodeinfo["resource_type_field"]);
            $multifields = array(FIELD_TYPE_CATEGORY_TREE,FIELD_TYPE_DYNAMIC_KEYWORDS_LIST,FIELD_TYPE_CHECK_BOX_LIST);
            $valid = count($curnodes) == 0 || in_array($field["type"], $multifields);

            if ($valid) {
                $nodestoadd = array($node);
                // We need to add all parent nodes for category trees
                if ($field['type'] == FIELD_TYPE_CATEGORY_TREE && $category_tree_add_parents) {
                    $parent_nodes = get_parent_nodes($node);
                    foreach ($parent_nodes as $parent_node_ref => $parent_node_name) {
                        $nodestoadd[] = $parent_node_ref;
                    }
                }

                add_resource_nodes($resource, $nodestoadd);
                $return['status'] = 200;
            } else {
                $return['status'] = 400;
                $return['message'] = $lang['error-invalid_browse_action'];
            }

            break;

        case 'collection_add':
            $collection = getval("collection", 0, true);

            if (collection_writeable($collection) && add_resource_to_collection($resource, $collection, false)) {
                $return['status'] = 200;
            }
            break;

        default:
            $return['message'] = $lang["error_generic"] ;
            break;
    }
}
http_response_code($return['status']);
header('Content-type: application/json');
echo json_encode($return);
exit();
