<?php

/**
* Set node - Used for both creating and saving a node in the database.
* Use NULL for ref if you just want to insert a new record.
*
* @param  integer  $ref                   ID of the node. To insert new record ID should be NULL
* @param  integer  $resource_type_field   ID of the metadata field
* @param  string   $name                  Node name to be used (international)
* @param  integer  $parent                ID of the parent of this node (null for non trees)
* @param  integer  $order_by              Value of the order in the list (e.g. 10). To automatically pick next order by use ''.
*
* @return boolean|integer
*/
function set_node($ref, $resource_type_field, $name, $parent, $order_by)
{
    global $FIXED_LIST_FIELD_TYPES;
    if (!is_null($name)) {
        $name = trim((string) $name);
    }

    if (is_null($resource_type_field) || '' == $resource_type_field || is_null($name) || '' == $name) {
        return false;
    }

    // Blank parent fixup to NULL; non-blank parent fixup to integer
    if (!is_null($parent)) {
        if ($parent == "") {
            $parent = null;
        } else {
            $parent = (int) $parent;
        }
    }

    // Prevent the creation of duplicate nodes unless type is category tree and the nodes have different parents in the tree.
    $resource_type_field_data = get_resource_type_field($resource_type_field);

    if (!$resource_type_field_data) {
        return false;
    }

    if ($resource_type_field_data['type'] != FIELD_TYPE_CATEGORY_TREE) {
        $returnexisting = true;
    } else {
        $nodes_for_parent = get_nodes($resource_type_field, $parent);
        if (count($nodes_for_parent) == 0 || !in_array($name, array_column($nodes_for_parent, 'name'))) {
            $returnexisting = false;
        } else {
            $returnexisting = true;
        }
    }

    if ($returnexisting) {
        // Check for an existing match. MySQL checks case insensitive so case is checked on this side.
        $existingnode = ps_query("SELECT ref,name FROM node WHERE resource_type_field = ? AND name = ?", array("i",$resource_type_field,"s",$name));
        if (count($existingnode) > 0) {
            foreach ($existingnode as $node) {
                if ($node["name"] == $name) {
                    return (int)$node["ref"];
                }
            }
        }
    }

    // If creating new node establish order_by if necessary
    if (is_null($ref) && '' == $order_by) {
        $order_by = get_node_order_by($resource_type_field, ($resource_type_field_data['type'] == FIELD_TYPE_CATEGORY_TREE), $parent);
    }

    $query = "INSERT INTO `node` (`resource_type_field`, `name`, `parent`, `order_by`) VALUES (?, ?, ?, ?)";
    $parameters = array
        (
        "i",$resource_type_field,
        "s",$name,
        "i",$parent,
        "s",$order_by
        );

    // Check if we only need to save the record
    $current_node = array();
    if (get_node($ref, $current_node)) {
        // If nothing has changed, just return true, otherwise continue and update record
        if (
            $resource_type_field === $current_node['resource_type_field'] &&
            $name === $current_node['name'] &&
            $parent === $current_node['parent'] &&
            $order_by === $current_node['order_by']
        ) {
            return $ref;
        }

        // When changing parent we need to make sure order by is changed as well
        // to reflect the fact that the node has just been added (ie. at the end of the list)
        if ($parent != $current_node['parent']) {
            $order_by = get_node_order_by($resource_type_field, true, $parent);
        }

        // Order by can be changed asynchronously, so when we save a node we can pass null or an empty
        // order_by value and this will mean we can use the current order
        if (!is_null($ref) && '' == $order_by) {
            $order_by = $current_node['order_by'];
        }

        $query = "
                UPDATE node
                   SET resource_type_field = ?,
                       `name` = ?,
                       parent = ?,
                       order_by = ?
                 WHERE ref = ?
            ";
        $parameters = array
                (
                "i",$resource_type_field,
                "s",$name,
                "i",$parent,
                "s",$order_by,
                "i",$ref
                );

        if ($resource_type_field_data["keywords_index"] == 1) {
            // Handle node indexing for existing nodes
            remove_node_keyword_mappings(array('ref' => $current_node['ref'], 'resource_type_field' => $current_node['resource_type_field'], 'name' => $current_node['name']), null);

            $is_date = in_array($resource_type_field_data['type'], [FIELD_TYPE_DATE_AND_OPTIONAL_TIME,FIELD_TYPE_EXPIRY_DATE,FIELD_TYPE_DATE,FIELD_TYPE_DATE_RANGE]);
            $is_html = ($resource_type_field_data["type"] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE);
            add_node_keyword_mappings(array('ref' => $ref, 'resource_type_field' => $resource_type_field, 'name' => $name), null, $is_date, $is_html);
        }
    }

    ps_query($query, $parameters);
    $new_ref = sql_insert_id();
    if ($new_ref == 0 || $new_ref === false) {
        if ($ref == null) {
            $return = ps_value("SELECT `ref` AS 'value' FROM `node` WHERE `resource_type_field`=? AND `name`=?", array("i",$resource_type_field,"s",$name), 0);
        } else {
            $return = $ref;
        }
    } else {
        if (in_array($resource_type_field_data['type'], $FIXED_LIST_FIELD_TYPES)) {
            log_activity("Set metadata field option for field {$resource_type_field}", LOG_CODE_CREATED, $name, 'node', 'name', $new_ref, null, '');
        }

        // Handle node indexing for new nodes
        if ($resource_type_field_data["keywords_index"] == 1) {
            add_node_keyword_mappings(array('ref' => $new_ref, 'resource_type_field' => $resource_type_field, 'name' => $name), null);
        }
        $return = $new_ref;
    }

    if (in_array($resource_type_field_data['type'], $FIXED_LIST_FIELD_TYPES)) {
        clear_query_cache("schema");
    }

    return $return;
}

/**
* Delete node. This will fully delete a node and remove any association between the deleted node and resources / keywords.
*
* @param  integer  $ref  ID of the node
*
* @return void
*/
function delete_node($ref)
{
    if (is_parent_node($ref)) {
        return;
    }

    $returned_node = array();
    get_node($ref, $returned_node, false);
    if (empty($returned_node)) {
        // Node has already been removed.
        return;
    }
    $resource_type_field = $returned_node['resource_type_field'];
    $field_data = get_resource_type_field($resource_type_field);

    global $FIXED_LIST_FIELD_TYPES;
    if (in_array($field_data['type'], $FIXED_LIST_FIELD_TYPES)) {
        log_activity("Delete metadata field option for field {$resource_type_field}", LOG_CODE_DELETED, null, 'node', 'name', $ref, null, $returned_node['name']);
    }

    ps_query("DELETE FROM node WHERE ref = ?", array("i",$ref));
    delete_node_resources($ref);
    remove_all_node_keyword_mappings($ref);
}

/**
* Delete all nodes for a resource type field
*
* @param  integer  $resource_type_field  ID of the resource type field
*
* @return void
*/
function delete_nodes_for_resource_type_field($ref)
{
    if (is_null($ref) || '' === trim($ref) || 0 === $ref) {
        trigger_error('$ref must be an integer greater than 0');
    }

    ps_query("DELETE FROM node WHERE resource_type_field = ?", array("i",$ref));
}

/**
* Get a specific node by ref
*
* @param  integer  $ref              ID of the node
* @param  array    $returned_node    If a value does exist it will be returned through
*                                    this parameter which is passed by reference
* @param  bool     $cache            By default this function returns cached data. This may not be appropriate if called after
*                                    a value has been changed, for example after editing a node name. Set to false to not use cache.
* @return boolean
*/
function get_node($ref, array &$returned_node, $cache = true)
{
    if (is_null($ref) || (trim($ref) == "") || 0 >= $ref) {
        return false;
    }

    $parameters = [];
    $sql = columns_in("node");
    add_sql_node_language($sql, $parameters);
    $parameters[] = "i";
    $parameters[] = $ref;
    $node  = ps_query("SELECT " . $sql . " FROM node WHERE ref = ?", $parameters, $cache ? "schema" : "");

    if (count($node) == 0) {
        return false;
    }

    $returned_node = $node[0];

    return true;
}

/**
* Get all nodes from database for a specific metadata field or parent.
*
* Use $parent = NULL and recursive = TRUE to get all nodes for a category tree field
*
* Use $offset and $rows only when returning a subset.
*
* @param  integer  $resource_type_field         ID of the metadata field
* @param  integer  $parent                      ID of parent node
* @param  boolean  $recursive                   Set to true to get children nodes as well.
*                                               IMPORTANT: this is normally used with category trees, but can also be used with
*                                               other fixed field types which allow multiple values eg. dynamic keywords list and checkbox list.
*                                               This allows field type changes to be made between these three types without losing sight of all nodes.
* @param  integer  $offset                      Specifies the offset of the first row to return
* @param  integer  $rows                        Specifies the maximum number of rows to return.
*                                               IMPORTANT! For non-fixed list fields this is capped at 10000
*                                               to avoid out of memory errors
* @param  string   $name                        Filter by name of node
* @param  boolean  $use_count                   Show how many resources use a particular node in the node properties
* @param  boolean  $order_by_translated_name    Flag to order by translated names rather then the order_by column
* @param  boolean  $exact_match                 When $name is supplied, match the exact name only instead of returning
*                                               matches containing the $name string.
*
* @return array
*/
function get_nodes(
    $resource_type_field = null,
    $parent = null,
    $recursive = false,
    $offset = null,
    $rows = null,
    $name = '',
    $use_count = false,
    $order_by_translated_name = false,
    $exact_match = false
) {
    global $FIXED_LIST_FIELD_TYPES;
    debug_function_call("get_nodes", func_get_args());

    if (!is_int_loose($resource_type_field) && !is_null($resource_type_field)) {
        return [];
    }

    if (!is_null($parent)) {
        if ($parent == "") {
            $parent = null;
        } else {
            $parent = (int) $parent;
        }
    }

    if (!is_null($resource_type_field)) {
        $fieldinfo  = get_resource_type_field($resource_type_field);
        if ($fieldinfo === false) {
            return false;
        }
        if (!in_array($fieldinfo["type"], $FIXED_LIST_FIELD_TYPES) && (is_null($rows) || (int)$rows > 10000 )) {
            $rows = 10000;
        }
    }

    $return_nodes = array();

    $parameters = [];
    $sql = "";
    add_sql_node_language($sql, $parameters);

    // Filter by resource type if required
    $filter_by_resource_type_field = "true";
    if (!is_null($resource_type_field)) {
        $filter_by_resource_type_field = "resource_type_field=?";
        $parameters[] = "i";
        $parameters[] = $resource_type_field;
    }

    // Filter by name if required
    $filter_by_name = '';
    if ('' != $name) {
        if ($exact_match) {
            $filter_by_name = " AND `name` = ?";
            $parameters[] = "s";
            $parameters[] = $name;
        } else {
            $filter_by_name = " AND `name` LIKE ?";
            $parameters[] = "s";
            $parameters[] = "%" . $name . "%";
        }
    }

    // Option to include a usage count alongside each node
    $use_count_sql = "";
    if ($use_count) {
        $use_count_sql = ",(SELECT count(resource) FROM resource_node WHERE resource_node.resource > 0 AND resource_node.node = node.ref) AS use_count";
    }

    $parent_sql = is_null($parent) ? ($recursive ? "TRUE" : "parent IS NULL") : ("parent = ?");
    if (strpos($parent_sql, "?") !== false) {
        $parameters[] = "i";
        $parameters[] = $parent;
    }

    // Order by translated_name or order_by based on flag
    $order_by = $order_by_translated_name ? "translated_name" : "order_by";

    // Check if limiting is required
    $limit = '';
    if (!is_null($offset) && is_int($offset)) { # Offset specified
        if (!is_null($rows) && is_int($rows)) { # Row limit specified
            $limit = "LIMIT ?,?";
            $parameters[] = "i";
            $parameters[] = $offset;
            $parameters[] = "i";
            $parameters[] = $rows;
        } else # Row limit absent
            {
            $limit = "LIMIT ?,999999999"; # Use a large arbitrary limit
            $parameters[] = "i";
            $parameters[] = $offset;
        }
    } else # Offset not specified
        {
        if (!is_null($rows) && is_int($rows)) { # Row limit specified
            $limit = "LIMIT ?";
            $parameters[] = "i";
            $parameters[] = $rows;
        }
    }

    $query = "SELECT " . columns_in("node") . $sql . $use_count_sql . "
        FROM node 
        WHERE 
        " . $filter_by_resource_type_field . $filter_by_name . "
        AND " . $parent_sql . "
        ORDER BY " . $order_by . ", ref ASC
        " . $limit;

    $sqlcache = (is_null($resource_type_field) || in_array($fieldinfo["type"], $FIXED_LIST_FIELD_TYPES)) ? "schema" : "";
    $nodes = ps_query($query, $parameters, $sqlcache);

    // No need to recurse if no parent was specified as we already have all nodes
    if ($recursive && (int)$parent > 0) {
        foreach ($nodes as $node) {
            foreach (get_nodes($resource_type_field, $node['ref'], true) as $sub_node) {
                array_push($nodes, $sub_node);
            }
        }
    } else {
        $return_nodes = $nodes;
    }

    if ($recursive) {
        // Need to reorder so that parents are ordered by first, with children between (query will have returned them all according to the passed order_by)
        $return_nodes = order_tree_nodes($return_nodes, $order_by_translated_name);
    }

    return $return_nodes;
}

/**
* Find and return node details for a list of node IDs.
*
* @param array $refs List of node IDs
*
* @return array
*/
function get_nodes_by_refs(array $refs)
{
    $refs = array_filter($refs, 'is_int_loose');
    if (empty($refs)) {
        return [];
    }

    $parameters = [];
    $sql = columns_in("node");
    add_sql_node_language($sql, $parameters);
    $query = "SELECT " . $sql  . " FROM node WHERE ref IN (" . ps_param_insert(count($refs)) . ")";
    $parameters = array_merge($parameters, ps_param_fill($refs, "i"));

    return ps_query($query, $parameters);
}

/**
* Checks whether a node is parent to other nodes or not
*
* @param int $ref Node ref
* @param bool $active_only Check only for active (children) nodes
*/
function is_parent_node($ref, bool $active_only = false): bool
{
    if (is_null($ref)) {
        return false;
    }

    $query = $active_only
        ? 'SELECT DISTINCT parent `value` from node WHERE active = 1'
        : 'SELECT DISTINCT parent `value` from node';

    $parents = ps_array($query, [], 'schema');

    return is_array($parents) && in_array($ref, $parents); 
}

/**
* Determine how many level deep a node is. Useful for knowing how much to indent a node
*
* @param  integer    $ref    Node ref
*
* @return integer            The depth value of a tree node
*/
function get_tree_node_level($ref)
{
    if (!isset($ref)) {
        trigger_error('Node ID should be set AND NOT NULL');
    }

    $parent      = $ref;
    $depth_level = -1;

    do {
        $query  = "SELECT parent AS value FROM node WHERE ref = ?";
        $parameters = array("i",$parent);
        $parent = ps_value($query, $parameters, 0);

        $depth_level++;
    } while ('' != trim((string) $parent) && $parent != 0);

    return $depth_level;
}

/**
* Return a row consisting of all ancestor nodes of a given node
* Example:
* 1
* 2
* 2.3
* 2.7
* 2.8.4
* 2.8.5
* 2.8.6
* 2.9
* 3
* Passing in node 5 will return nodes 8,2 in one row
*
* @param integer $ref   A tree node
* @param integer $level Node depth level (as returned by get_tree_node_level())
*
* @return array|boolean
*/
function get_all_ancestors_for_node(int $ref, int $level)
{
    if (0 >= $level) {
        return false;
    }

    $querycolumns = array();
    $query = " FROM node AS n{$level}";

    $from_level = $level;
    $level--;

    while (0 <= $level) {
        $query .= " LEFT JOIN node AS n{$level} ON n" . ($level + 1) . ".parent = n{$level}.ref";
        $querycolumns[] = "n{$level}.ref n{$level}ref";

        if (0 === $level) {
            $query .= " WHERE n{$from_level}.ref = ?";
            $placeholders = ['i', $ref];
        }

        $level--;
    }

    $query = "SELECT " . implode(",", $querycolumns) . $query;
    return ps_query($query, $placeholders);
}

/**
* Function used to reorder nodes based on an array with nodes in the new order
*
* @param  array  $nodes_new_order  Array of nodes
*
* @return void
*/
function reorder_node(array $nodes_new_order)
{
    if (0 === count($nodes_new_order)) {
        trigger_error('$nodes_new_order cannot be an empty array!');
    }

    $order_by = 10;

    $query = 'UPDATE node SET order_by = (CASE ref ';
    $parameters = array();

    foreach ($nodes_new_order as $node_ref) {
        $query    .= 'WHEN ? THEN ? ';
        $parameters[] = "i";
        $parameters[] = $node_ref;
        $parameters[] = "i";
        $parameters[] = $order_by;
        $order_by += 10;
    }
    $query .= 'ELSE order_by END);';

    ps_query($query, $parameters);
    clear_query_cache("schema");
}

/**
* Virtually re-order nodes
*
* Temporarily re-order nodes (mostly) for display purposes
*
*
* @param array $unordered_nodes Original nodes array
*
* @return array
*/
function reorder_nodes(array $unordered_nodes)
{
    // Put $auto_order_checkbox and $auto_order_checkbox_case_insensitive as global
    // to future proof function for when we will drop globals for easier refactoring
    global $auto_order_checkbox, $auto_order_checkbox_case_insensitive;

    $reordered_options = array();
    $use_index_key     = array();
    foreach ($unordered_nodes as $unordered_node_index => $node) {
        $reordered_options[$node['ref']] = normalize_keyword(i18n_get_translated($node['name']), true);
        $use_index_key[$node['ref']]     = ($unordered_node_index == $node['ref']);
    }

    if (isset($auto_order_checkbox) && $auto_order_checkbox && $auto_order_checkbox_case_insensitive) {
        natcasesort($reordered_options);
    } else {
        natsort($reordered_options);
    }

    $reordered_nodes = array();
    foreach ($reordered_options as $reordered_node_id => $reordered_node_option) {
        if (!$use_index_key[$reordered_node_id]) {
            $reordered_nodes[$reordered_node_id] = $unordered_nodes[array_search($reordered_node_id, array_column($unordered_nodes, 'ref'))];
        } else {
            $reordered_nodes[$reordered_node_id] = $unordered_nodes[array_search($reordered_node_id, array_column($unordered_nodes, 'ref', 'ref'))];
        }
    }

    return $reordered_nodes;
}

/**
* Renders HTML for adding a new node record in the database
*
* @param  string   $form_action          Set the action path of the form
* @param  boolean  $is_tree              Set to TRUE if the field is category tree type
* @param  integer  $parent               ID of the parent of this node
* @param  integer  $node_depth_level     When rendering for trees, we need to know how many levels deep we need to render it
* @param  array    $parent_node_options  Array of node options to be used as parent for new records
*/
function render_new_node_record($form_action, bool $is_tree, $parent = 0, $node_depth_level = 0, array $parent_node_options = array()): void
{
    global $baseurl_short, $lang;

    if (!is_safe_url($form_action)) {
        $form_action = '';
    }

    if (trim($form_action) == "") {
        trigger_error('$form_action param for render_new_node_record() must be set and not be an empty string!');
    }

    // Render normal fields first then go to tree type
    if (!$is_tree) {
        ?>
        <tr id="new_node_<?php echo (int) $parent; ?>_children">
            <td>
                <input type="text" class="stdwidth" name="new_option_name" form="new_option" value="">
            </td>
            <td> </td>
            <td>
                <div class="ListTools">
                    <form id="new_option" method="post" action="<?php echo $form_action; ?>">
                        <?php generateFormToken("new_option"); ?>
                        <button type="submit" onClick="AddNode(<?php echo (int) $parent; ?>); return false;"><?php echo escape($lang['add']); ?></button>
                    </form>
                </div>
            </td>
        </tr>
        <?php

        return;
    }

    // Trees only
    ?>
    <table id="new_node_<?php echo (int) $parent; ?>_children" cellspacing="0" cellpadding="5">
        <tbody>
            <tr>
            <?php
            // Indent node to the correct depth level
            $i = $node_depth_level;
            while (0 < $i) {
                $i--;
                ?>
                <td class="backline" width="10">
                    <img alt="" width="11" height="11" hspace="4" src="<?php echo $baseurl_short; ?>gfx/interface/sp.gif">
                </td>
                <?php
            }
            ?>
                <td class="backline" width="10">
                    <img alt="" width="11" height="11" hspace="4" src="<?php echo $baseurl_short; ?>gfx/interface/sp.gif">
                </td>
                <td>
                    <input type="text" name="new_option_name" form="new_node_<?php echo (int) $parent; ?>_option" value="">
                </td>
                <td>
                    <select class="node_parent_chosen_selector" name="new_option_parent" form="new_node_<?php echo (int) $parent; ?>_option">
                        <option value="">Select parent</option>
                        <?php foreach ($parent_node_options as $node) {
                            $selected = '';
                            if (trim($parent) != "" && $node['ref'] == $parent) {
                                $selected = ' selected';
                            } ?>
                            <option value="<?php echo $node['ref']; ?>"<?php echo $selected; ?>><?php echo escape($node['name']); ?></option>
                        <?php } ?>
                    </select>
                </td>
                <td>
                    <div class="ListTools">
                        <form id="new_node_<?php echo (int) $parent; ?>_option" method="post" action="<?php echo $form_action; ?>">
                            <?php generateFormToken("new_node_{$parent}_option"); ?>
                            <button type="submit" onClick="AddNode(<?php echo (int) $parent; ?>); return false;"><?php echo escape($lang['add']); ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <?php
}

/**
* Calculate the next order by for a new record
*
* @param  integer  $resource_type_field   ID of the metadata field
* @param  boolean  $is_tree               Param to flag whether this is for a tree node
* @param  integer  $parent                ID of the parent of this node
*
* @return integer  $order_by
*/
function get_node_order_by($resource_type_field, $is_tree = false, $parent = null)
{
    $order_by = 10;

    // Blank parent fixup to NULL; non-blank parent fixup to integer
    if (!is_null($parent)) {
        if ($parent == "") {
            $parent = null;
        } else {
            $parent = (int) $parent;
        }
    }

    $query         = "SELECT COUNT(*) AS value FROM node WHERE resource_type_field = ?;";
    $parameters     = array("i",$resource_type_field);
    $nodes_counter = ps_value($query, $parameters, 0);

    if ($is_tree) {
        $query = "SELECT COUNT(*) AS value FROM node WHERE resource_type_field = ?";
        $parameters = array("i",$resource_type_field);

        if (is_null($parent)) {
            $query .= " AND parent IS NULL;";
        } else {
            $query .= " AND parent = ?;";
            $parameters = array_merge($parameters, array("i",$parent));
        }

        $nodes_counter = ps_value($query, $parameters, 0);
    }

    if (0 < $nodes_counter) {
        $order_by = ($nodes_counter + 1) * 10;
    }

    return $order_by;
}

/**
* Renders HTML for a tree node
*
* @param  integer  $ref                   ID of the node
* @param  integer  $resource_type_field   ID of the metadata field
* @param  string   $name                  Node name to be used (international)
* @param  integer  $parent                ID of the parent of this node
* @param  integer  $order_by              Value of the order in the list (e.g. 10)
* @param  boolean  $last_node             Set to true to allow to insert new records after last node in each level
* @param  integer  $use_count             Counter of how many resources use a particular node
*/
function draw_tree_node_table(
    $ref,
    int $resource_type_field,
    string $name,
    $parent,
    int $order_by,
    bool $last_node = false,
    int $use_count = 0
): bool {
    global $baseurl_short, $lang, $FIXED_LIST_FIELD_TYPES;

    static $resource_type_field_last = 0;
    static $all_nodes = array();

    if (is_null($ref) || (trim($ref) == "")) {
        return false;
    }
    $ref_escaped = escape($ref);

    $fieldinfo  = get_resource_type_field($resource_type_field);
    if (!in_array($fieldinfo["type"], $FIXED_LIST_FIELD_TYPES)) {
        return false;
    }

    $toggle_node_mode = '';
    $spacer_filename  = 'sp.gif';
    $onClick          = '';
    $node_index = $order_by / 10;

    if (is_parent_node($ref)) {
        $toggle_node_mode = 'unex';
        $spacer_filename  = 'node_unex.gif';
        $onClick          = sprintf('ToggleTreeNode(%s, %s);', (int) $ref, $resource_type_field);
    }

    // Determine Node depth
    $node_depth_level = get_tree_node_level($ref);

    // Fetch all nodes on change of resource type field
    if ($resource_type_field !== $resource_type_field_last) {
        global $node_tree_data;
        if (!empty($node_tree_data)) {
            $all_nodes = $node_tree_data;
        } else {
            $all_nodes = $node_tree_data = get_nodes($resource_type_field, null, true, null, null, '', true);
        }
        $resource_type_field_last = $resource_type_field;
    }

    // We remove the current node from the list of parents for it( a node should not add to itself)
    $nodes = $all_nodes;
    $nodes_index_to_remove = array_search($ref, array_column($nodes, 'ref'));
    $node_info = $nodes[$nodes_index_to_remove];
    unset($nodes[$nodes_index_to_remove]);
    $nodes = array_values($nodes);
    $node_disabled_class = $node_info['active'] === 0 ? 'FieldDisabled' : '';
    $activation_action_label = node_is_active($node_info)
        ? $lang['userpreference_disable_option']
        : $lang['userpreference_enable_option'];
    ?>
    <table id="node_<?php echo $ref_escaped; ?>" cellspacing="0" cellpadding="5" data-toggle-node-mode = "<?php echo $toggle_node_mode; ?>">
        <tbody>
            <tr>
            <?php
            // Indent node to the correct depth level
            $i = $node_depth_level;
            while (0 < $i) {
                $i--;
                ?>
                <td class="backline" width="10">
                    <img alt="" width="11" height="11" hspace="4" src="<?php echo $baseurl_short; ?>gfx/interface/sp.gif">
                </td>
                <?php
            }
            ?>
                <td class="backline" width="10">
                    <img alt="" id="node_<?php echo $ref_escaped; ?>_toggle_button" width="11" height="11" hspace="4" src="<?php echo $baseurl_short; ?>gfx/interface/<?php echo $spacer_filename; ?>" onclick="<?php echo $onClick; ?>">
                </td>
                <td>
                    <input
                        type="text"
                        class="<?php echo escape($node_disabled_class); ?>"
                        name="option_name"
                        form="option_<?php echo $ref_escaped; ?>"
                        value="<?php echo escape($name); ?>"
                    >
                </td>
                <td>
                    <select id="node_option_<?php echo $ref_escaped; ?>_parent_select" parent_node="<?php echo (int) $parent; ?>" class="node_parent_chosen_selector" name="option_parent" form="option_<?php echo $ref_escaped; ?>">
                        <option value="">Select parent</option>
                    </select>
                </td>
                <td><?php echo escape($use_count); ?></td>
                <td>
                    <div class="ListTools">
                        <form id="option_<?php echo $ref_escaped; ?>" method="post" action="/pages/admin/admin_manage_field_options.php?field=<?php echo (int) $resource_type_field; ?>">
                            <input type="hidden" name="option_order_by" value="<?php echo $order_by; ?>">
                            <input 
                                    type="number"
                                    name="node_order_by" 
                                    value="<?php echo $node_index; ?>" 
                                    id="option_<?php echo $ref_escaped; ?>_order_by" 
                                    readonly='true'
                                    min='1'
                                >
                            </td>
                            <td> <!-- Buttons for changing order -->
                                <button 
                                    type="button"
                                    id="option_<?php echo $ref_escaped; ?>_move_to"
                                    onclick="
                                        EnableMoveTo(<?php echo $ref_escaped; ?>);
                                        
                                        return false;
                                    ">
                                    <?php echo escape($lang['action-move-to']); ?>
                                </button>
                                <button 
                                    type="submit"
                                    id="option_<?php echo $ref_escaped; ?>_order_by_apply"
                                    onclick="
                                        ApplyMoveTo(<?php echo $ref_escaped; ?>);
                                        return false;
                                    "
                                    style="display: none;"
                                >
                                <?php echo escape($lang['action-title_apply']); ?>
                                </button>
                                <button type="submit" onclick="ReorderNode(<?php echo $ref_escaped; ?>, 'moveup'); return false;"><?php echo escape($lang['action-move-up']); ?></button>
                                <button type="submit" onclick="ReorderNode(<?php echo $ref_escaped; ?>, 'movedown'); return false;"><?php echo escape($lang['action-move-down']); ?></button>
                            </td>
                        <td> <!-- Action buttons -->
                            <button type="submit" onclick="SaveNode(<?php echo $ref_escaped; ?>); return false;"><?php echo escape($lang['save']); ?></button>
                            <button id="node_<?php echo $ref_escaped; ?>_toggle_active_btn" type="submit" onclick="ToggleNodeActivation(<?php echo $ref_escaped; ?>); return false;"><?php echo escape($activation_action_label); ?></button>
                            <?php
                            if (!is_parent_node($ref)) {?>
                            <button type="submit" onclick="DeleteNode(<?php echo $ref_escaped; ?>); return false;"><?php echo escape($lang['action-delete']); ?></button>
                                <?php
                            }
                            ?>
                        </td>
                            
                        <?php generateFormToken("option_{$ref}"); ?>
                        </form>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <div id="node_<?php echo $ref_escaped; ?>_children"></div>

    <?php
    // Add a way of inserting new records after the last node of each level
    if ($last_node) {
        if (trim((string) $parent) == "") {
            $parent = 0;
        }
        render_new_node_record(
            generateURL(
                "{$GLOBALS['baseurl']}/pages/admin/admin_manage_field_options.php",
                ['field' => $resource_type_field]
            ),
            true,
            $parent,
            $node_depth_level,
            $all_nodes
        );
    }

    return true;
}

/**
 * Overrides either a field[options] array structure or a flat option array with values derived from nodes.
 * If a field, then will also add field=>nodes[] sub array ready for field rendering.
 *
 * @param  mixed    $field                 Either field array structure or flat options list array
 * @param  integer  $resource_type_field   ID of the metadata field, if specified will treat as flat options list
 *
 * @return boolean
 */
function node_field_options_override(&$field, $resource_type_field = null)
{
    global $FIXED_LIST_FIELD_TYPES;
    if (isset($field["type"]) && !in_array($field["type"], $FIXED_LIST_FIELD_TYPES)) {
        return false;
    }
    if (!is_null($resource_type_field)) {     // we are dealing with a single specified resource type so simply return array of options
        $options = get_nodes($resource_type_field);
        if (count($options) > 0) {     // only override if field options found within nodes
            $field = array();
            foreach ($options as $option) {
                array_push($field, $option['name']);
            }
        }
        return true;
    }

    if (
        !isset($field['ref']) ||
        !isset($field['type']) ||
        !in_array($field['type'], array(2, 3, 7, 9, 12))
    ) {
        return false;       // get out of here if not a node supported field type
    }

    migrate_resource_type_field_check($field);

    $field['node_options'] = [];
    $nodes = get_nodes($field['ref'], null, $field['type'] == FIELD_TYPE_CATEGORY_TREE, null, null, null, null, (bool)$field['automatic_nodes_ordering']);
    foreach ($nodes as $node) {
        $field['node_options'][$node['ref']] = $node;
    }
    return true;
}

/**
* Adds node keyword for indexing purposes
*
* @param  integer  $node        ID of the node (from node table) the keyword should be linked to
* @param  string   $keyword     Keyword to index
* @param  integer  $position    The position of the keyword in the string that was indexed
* @param  boolean  $normalized  If this keyword is normalized by the time we add it, set as true
*
* @return boolean
*/
function add_node_keyword($node, $keyword, $position, $normalize = true, $stem = true)
{
    global $noadd, $stemming;

    debug("add_node_keyword: node:" . $node . ", keyword: " . $keyword . ", position: " . $position . ", normalize:" . ($normalize ? "TRUE" : "FALSE") . ", stem:" . ($stem ? "TRUE" : "FALSE"));

     $unstemmed = $keyword;
    if ($stem && $stemming && function_exists("GetStem")) {
        $keyword = GetStem($keyword);
        if ($keyword != $unstemmed) {
            // $keyword has been changed by stemming, also index the original value
            debug("add_node_keyword - adding unstemmed: " . $unstemmed);
            add_node_keyword($node, $unstemmed, $position, $normalize, false);
        }
    }

    // $keyword should not be indexed if it can be found in the $noadd array, no need to continue
    if (in_array($unstemmed, $noadd)) {
        debug('Ignored keyword "' . $keyword . '" as it is in the $noadd array. Triggered in ' . __FUNCTION__ . '() on line ' . __LINE__);
        return false;
    }

    $keyword_ref = resolve_keyword($keyword, true, $normalize, false); // We have already stemmed

    ps_query("INSERT INTO node_keyword (node, keyword, position) VALUES (?, ?, ?)", array("i",$node,"i",$keyword_ref,"i",$position));
    return true;
}

/**
* Removes node keyword for indexing purposes
*
* @param  integer  $node        ID of the node (from node table) the keyword should be linked to
* @param  string   $keyword     Keyword to index
* @param  integer  $position    The position of the keyword in the string that was indexed
* @param  boolean  $normalized  If this keyword is normalized by the time we add it, set as true
*
* @return void
*/
function remove_node_keyword($node, $keyword, $position, $normalized = false)
{
    global $noadd;

    if (!$normalized) {
        $keyword          = normalize_keyword($keyword);
    }

    $keyword_ref = resolve_keyword($keyword, true);

    $parameters = array("i",$node,"i",$keyword_ref);
    $position_sql = '';
    if ('' != trim($position)) {
        $position_sql = " AND position = ?";
        $parameters[] = "i";
        $parameters[] = $position;
    }

    ps_query("DELETE FROM node_keyword WHERE node = ? AND keyword = ? $position_sql", $parameters);

    ps_query("UPDATE keyword SET hit_count = hit_count - 1 WHERE ref = ?", array("i",$keyword_ref));
}

/**
* Removes all indexed keywords for a specific node ID
*
* @param  integer  $node  Node ID
*
* @return void
*/
function remove_all_node_keyword_mappings($node)
{
    ps_query("DELETE FROM node_keyword WHERE node = ?", array("i",$node));
}

/**
* Function used to check if a fields' node needs (re-)indexing
*
* @param  array    $node           Individual node for a field ( as returned by get_nodes() )
* @param  boolean  $partial_index  Partially index flag for node keywords
*
* @return void
*/
function check_node_indexed(array $node, $partial_index = false)
{
    if ('' === trim($node['name'])) {
        return;
    }

    $count_indexed_node_keywords = ps_value("SELECT count(node) AS 'value' FROM node_keyword WHERE node = ?", array("i", $node['ref']), 0);
    $keywords                    = split_keywords($node['name'], true, $partial_index);

    if ($count_indexed_node_keywords == count($keywords)) {
        // node has already been indexed
        return;
    }

    // (re-)index node
    remove_all_node_keyword_mappings($node['ref']);
    add_node_keyword_mappings($node, $partial_index);
}

/**
* Function used to index node keywords
*
* @param  array         $node           Individual node for a field ( as returned by get_nodes() )
* @param  boolean|null  $partial_index  Partially index flag for node keywords. Use NULL if code doesn't
*                                       have access to the fields' data
*
* @return boolean
*/
function add_node_keyword_mappings(array $node, $partial_index = false, bool $is_date = false, bool $is_html = false)
{
    global $node_keyword_index_chars,$index_whole_field;
    if ('' == trim($node['ref']) && '' == trim($node['name']) && '' == trim($node['resource_type_field'])) {
        return false;
    }

    $field_data = get_field($node['resource_type_field']);
    if (isset($field_data['complete_index']) && $field_data['complete_index']) {
        add_node_keyword($node['ref'], $node['name'], 0);
        return true;
    }

    // Client code does not know whether field is partially indexed or not
    if (
        is_null($partial_index)
        && isset($field_data['partial_index'])
        && '' != trim($field_data['partial_index'])
    ) {
        $partial_index = $field_data['partial_index'];
    }

    // Check for translations and split as necessary
    if (substr($node['name'], 0, 1) == "~") {
        $translations = array_filter(i18n_get_translations($node['name']));
    } else {
        $translations[] = $node['name'];
    }
    $in_transaction = $GLOBALS['sql_transaction_in_progress'] ?? false;
    if (!$in_transaction) {
        db_begin_transaction("add_node_keyword_mappings");
    }
    foreach ($translations as $translation) {
        // Only index the first 500 characters
        $translation = mb_substr($translation, 0, $node_keyword_index_chars);

        $keywords = split_keywords($translation, true, $partial_index, $is_date, $is_html);

        add_verbatim_keywords($keywords, $translation, $node['resource_type_field']);

        for ($n = 0; $n < count($keywords); $n++) {
            unset($keyword_position);

            if (is_array($keywords[$n])) {
                $keyword_position = $keywords[$n]['position'];
                $keywords[$n]     = $keywords[$n]['keyword'];
            }

            if (!isset($keyword_position)) {
                $keyword_position = $n;
            }

            add_node_keyword($node['ref'], $keywords[$n], $keyword_position);
        }
    }
    if (!$in_transaction) {
        db_end_transaction("add_node_keyword_mappings");
    }

    return true;
}

/**
* Function used to un-index node keywords
*
* @param  array         $node           Individual node for a field ( as returned by get_nodes() )
* @param  boolean|null  $partial_index  Partially index flag for node keywords. Use NULL if code doesn't
*                                       have access to the fields' data
*
* @return boolean
*/
function remove_node_keyword_mappings(array $node, $partial_index = false)
{
    if ('' == trim($node['ref']) && '' == trim($node['name']) && '' == trim($node['resource_type_field'])) {
        return false;
    }

    // Client code does not know whether field is partially indexed or not
    if (is_null($partial_index)) {
        $field_data = get_field($node['resource_type_field']);

        if (isset($field_data['partial_index']) && '' != trim($field_data['partial_index'])) {
            $partial_index = $field_data['partial_index'];
        }
    }

    $in_transaction = $GLOBALS['sql_transaction_in_progress'] ?? false;
    if (!$in_transaction) {
        db_begin_transaction("remove_node_keyword_mappings");
    }

    $keywords = split_keywords($node['name'], true, $partial_index);
    add_verbatim_keywords($keywords, $node['name'], $node['resource_type_field']);

    for ($n = 0; $n < count($keywords); $n++) {
        unset($keyword_position);

        if (is_array($keywords[$n])) {
            $keyword_position = $keywords[$n]['position'];
            $keywords[$n]     = $keywords[$n]['keyword'];
        }

        if (!isset($keyword_position)) {
            $keyword_position = $n;
        }

        remove_node_keyword($node['ref'], $keywords[$n], $keyword_position);
    }

    if (!$in_transaction) {
        db_end_transaction("remove_node_keyword_mappings");
    }


    return true;
}

/**
* Add nodes in array to resource
*
* @param  integer      $resourceid         Resource ID to add nodes to
* @param  array        $nodes              Array of node IDs to add
* @param  boolean      $checkperms         Check permissions before adding?
* @param  boolean      $logthis            Log this? Log entries are ideally added when more data on all the changes made is available to make reverts easier.
*
* @return boolean
*/
function add_resource_nodes(int $resourceid, $nodes = array(), $checkperms = true, $logthis = true)
{
    global $userref;
    if (!is_array($nodes) && (string)(int)$nodes != $nodes) {
        return false;
    }

    if (count($nodes) == 0) {
        return false;
    }

    $sql = '';
    $sql_params = [];

    # check $nodes array values are positive integers and valid for int type node db field
    $options_db_int = [ 'options' => [ 'min_range' => 1,   'max_range' => 2147483647] ];
    foreach ($nodes as $node) {
        if (!filter_var($node, FILTER_VALIDATE_INT, $options_db_int)) {
            return false;
        }

        $sql .= ',(?, ?)';
        $sql_params[] = 'i';
        $sql_params[] = $resourceid;
        $sql_params[] = 'i';
        $sql_params[] = $node;
    }
    $sql = ltrim($sql, ',');

    if ($checkperms && (PHP_SAPI != 'cli' || defined("RS_TEST_MODE"))) {
        // Need to check user has permissions to add nodes (unless running from any CLI script other than unit tests)
        $resourcedata = get_resource_data($resourceid);

        if (!$resourcedata) {
            return false;
        }

        $access = get_edit_access($resourceid, $resourcedata["archive"], $resourcedata);
        if (!$access) {
            return false;
        }

        if ($resourcedata["lock_user"] > 0 && $resourcedata["lock_user"] != $userref) {
            return false;
        }
    }
    if (!is_array($nodes)) {
        $nodes = array($nodes);
    }

    ps_query("INSERT IGNORE INTO resource_node(`resource`, node) VALUES {$sql};", $sql_params);

    if ($logthis) {
        $field_nodes_arr = array();
        foreach ($nodes as $node) {
            $nodedata = array();
            get_node($node, $nodedata);
            if ($nodedata) {
                $field_nodes_arr[$nodedata["resource_type_field"]][] = $nodedata["name"];
            }
        }

        foreach ($field_nodes_arr as $key => $value) {
            resource_log($resourceid, "e", $key, "", "", implode(NODE_NAME_STRING_SEPARATOR, $value));
        }
    }

    return true;
}

/**
* Add nodes in array to multiple resources. Changes made using this function will not be logged by default.
*
* @param array   $resources  Array of resource IDs to add nodes to
* @param array   $nodes      Array of node IDs to add
* @param boolean $checkperms Check permissions before adding?
* @param boolean $logthis    Log this? Log entries are ideally added when more data on all the changes made is available to make reverts easier.
*
* @return boolean
*/
function add_resource_nodes_multi($resources = array(), $nodes = array(), $checkperms = true, bool $logthis = false)
{
    global $userref, $lang;
    if ((!is_array($resources) && (string)(int)$resources != $resources) || (!is_array($nodes) && (string)(int)$nodes != $nodes)) {
        return false;
    }

    $resources = array_values(array_filter($resources, 'is_int_loose'));
    $nodes = array_values(array_filter(is_array($nodes) ? $nodes : [$nodes], 'is_int_loose'));

    if ($checkperms) {
        // Need to check user has permissions to add nodes
        foreach ($resources as $resourceid) {
            $resourcedata = get_resource_data($resourceid);
            $access = get_edit_access($resourceid, $resourcedata["archive"], $resourcedata);
            if (!$access) {
                return false;
            }

            if ($resourcedata["lock_user"] > 0 && $resourcedata["lock_user"] != $userref) {
                return false;
            }
        }
    }

    $resources_chunks = array_chunk($resources, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    $done = 0;
    foreach ($resources_chunks as $resources_chunk) {
        if (PHP_SAPI !== "cli") {
            set_processing_message(str_replace(["[done]","[total]"], [$done,count($resources)], $lang["processing_updating_resources"]));
        }
        $done += count($resources_chunk);

        $resource_node_values = '';
        $sql_params = [];
        foreach ($resources_chunk as $resource) {
            foreach ($nodes as $node) {
                $resource_node_values .= ',(?, ?)';
                $sql_params[] = 'i';
                $sql_params[] = $resource;
                $sql_params[] = 'i';
                $sql_params[] = $node;
            }

            if ($logthis && !empty($nodes)) {
                log_node_changes($resource, $nodes, []);
            }
        }
        $resource_node_values = ltrim($resource_node_values, ',');

        if ($resource_node_values !== '') {
            ps_query("INSERT INTO resource_node (resource, node) VALUES {$resource_node_values} ON DUPLICATE KEY UPDATE hit_count=hit_count", $sql_params);
        }
    }
    return true;
}

/**
* Get nodes associated with a particular resource for all / a specific field (optionally)
*
* @param integer $resource
* @param integer $resource_type_field
* @param boolean $detailed             Set to true to return full node details (as get_node() does)
* @param boolean $node_sort            Set to SORT_ASC to sort nodes ascending, SORT_DESC sort nodes descending, null means do not sort
*
* @return array
*/
function get_resource_nodes($resource, $resource_type_field = null, $detailed = false, $node_sort = null)
{
    $sql_select = 'n.ref AS `value`';
    if ($detailed) {
        $sql_select = columns_in("node", "n");
        // Add code to get translated names
        $params = [];
        add_sql_node_language($sql_select, $params, "n");
    }

    $query = "SELECT {$sql_select} FROM node AS n INNER JOIN resource_node AS rn ON n.ref = rn.node WHERE rn.resource = ?";
    $params[] = 'i';
    $params[] = $resource;
    if (!is_null($resource_type_field) && is_numeric($resource_type_field)) {
        $query .= " AND n.resource_type_field = ?";
        $params[] = 'i';
        $params[] = $resource_type_field;
    }

    if (!is_null($node_sort)) {
        if ($node_sort == SORT_ASC) {
            $query .= " ORDER BY n.ref ASC";
        }
        if ($node_sort == SORT_DESC) {
            $query .= " ORDER BY n.ref DESC";
        }
    } else {
        $query .= " ORDER BY n.resource_type_field, n.order_by ASC";
    }

    return $detailed ? ps_query($query, $params) : ps_array($query, $params);
}

/**
 * Get all resource nodes associated for a specific resource type field.
 *
 * @param integer $ref Resource type field ID
 *
 * @return Generator
 */
function get_resources_nodes_by_rtf(int $ref)
{
    $offset = null;
    do {
        $rows = 1000;
        $sql_limit = sql_limit($offset, $rows);
        $offset += $rows;

        $parameters = [];
        $sql = columns_in("node", "n");
        add_sql_node_language($sql, $parameters, "n");

        $parameters = array_merge($parameters, ['i', $ref]);
        $data = ps_query(
            "SELECT " . $sql . "
                  FROM resource_node AS rn
            INNER JOIN node AS n ON rn.node = n.ref AND n.resource_type_field = ?
            INNER JOIN resource AS r ON rn.resource = r.ref
            $sql_limit",
            $parameters
        );
        foreach ($data as $page_data) {
            yield $page_data;
        }
    } while (!empty($data) && count($data) === $rows);
}

/**
* Delete nodes in array from resource
*
* @param  integer      $resourceid         Resource ID to add nodes to
* @param  array        $nodes              Array of node IDs to remove
* @param  boolean      $logthis            Log this? Log entries are ideally added when more data on all changes made is available to make reverts easier.
*
* @return void
*/
function delete_resource_nodes(int $resourceid, $nodes = array(), $logthis = true)
{
    if (!is_array($nodes)) {
        $nodes = array($nodes);
    }

    $nodes = array_filter($nodes, 'is_int_loose');
    $nodes_count = count($nodes);
    if ($nodes_count === 0) {
        return;
    }

    $chunks = array_chunk($nodes, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    foreach ($chunks as $chunk) {
        ps_query(
            'DELETE FROM resource_node WHERE resource = ? AND node IN (' . ps_param_insert(count($chunk)) . ')',
            array_merge(['i', $resourceid], ps_param_fill($chunk, 'i'))
        );
    }

    if ($logthis) {
        $field_nodes_arr = array();
        foreach ($nodes as $node) {
            $nodedata = array();
            get_node($node, $nodedata);
            if ($nodedata) {
                $field_nodes_arr[$nodedata["resource_type_field"]][] = $nodedata["name"];
            }
        }
        foreach ($field_nodes_arr as $key => $value) {
            resource_log($resourceid, "e", $key, "", "," . implode(",", $value), '');
        }
    }
}

/**
 * Delete all node relationships matching the passed resource IDs and node IDs.
 *
 * @param  array $resources An array of resource IDs
 * @param  mixed $nodes An integer or array of single/multiple nodes
 * @return void
 */
function delete_resource_nodes_multi($resources = array(), $nodes = array())
{
    global $lang;
    if (!is_array($nodes)) {
        $nodes = array($nodes);
    }

    $resource_chunks = array_chunk($resources, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    $node_chunks = array_chunk($nodes, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    $done = 0;
    foreach ($resource_chunks as $resource_chunk) {
        if (PHP_SAPI !== "cli") {
            set_processing_message(str_replace(["[done]","[total]"], [$done,count($resources)], $lang["processing_updating_resources"]));
        }
        $done += count($resource_chunk);

        foreach ($node_chunks as $node_chunk) {
            $sql = "DELETE FROM resource_node WHERE resource in (" . ps_param_insert(count($resource_chunk)) . ") AND node in (" . ps_param_insert(count($node_chunk)) . ")";
            $params = array_merge(ps_param_fill($resource_chunk, "i"), ps_param_fill($node_chunk, "i"));
            ps_query($sql, $params);
        }
    }
}

/**
 * Delete all node relationships for the given resource.
 *
 * @param  integer $resourceid    The resource ID
 * @return void
 */
function delete_all_resource_nodes($resourceid)
{
    ps_query("DELETE FROM resource_node WHERE resource = ?", array("i",$resourceid));
}

/**
 * Delete all resource node relationships for the given node.
 *
 * @param integer  $node   The node ID to remove from all resources.
 * @return void
 */
function delete_node_resources(int $node)
{
    ps_query("DELETE FROM resource_node WHERE node = ?", array("i", $node));
}

/**
* Copy resource nodes from one resource to another. Only applies for active metadata fields.
*
* @param integer $resourcefrom Resource we are copying data from
* @param integer $resourceto   Resource we are copying data to
*/
function copy_resource_nodes($resourcefrom, $resourceto): void
{
    $omit_fields_sql = '';
    $omit_fields_sql_params = array();
    $omitfields = array();

    // When copying normal resources from one to another, check for fields that should be excluded
    // NOTE: this does not apply to user template resources (negative ID resource)
    if ($resourcefrom > 0) {
        $omitfields = ps_array("SELECT ref AS `value` FROM resource_type_field WHERE omit_when_copying = 1", array(), "schema");
    }

    // Exclude fields which user cannot edit "F?" or cannot see "f-?". With config, users permissions maybe overridden for different resource types.
    global $userpermissions;

    $no_permission_fields = array();
    foreach ($userpermissions as $permission_to_check) {
        if (substr($permission_to_check, 0, 2) == "f-") {
            $no_permission_fields[] = substr($permission_to_check, 2);
        } elseif (substr($permission_to_check, 0, 1) == "F") {
            $no_permission_fields[] = substr($permission_to_check, 1);
        }
    }

    $omitfields = array_merge($omitfields, array_unique($no_permission_fields));

    if (count($omitfields) > 0) {
        $omit_fields_sql = " AND n.resource_type_field NOT IN (" . ps_param_insert(count($omitfields)) . ") ";
        $omit_fields_sql_params = ps_param_fill($omitfields, "i");
    } else {
        $omit_fields_sql = "";
    }

    // This is for logging after the insert statement
    $nodes_to_add = ps_array("
    SELECT node value
        FROM resource_node AS rnold
    LEFT JOIN node AS n ON n.ref = rnold.node
    WHERE resource = ?
        AND n.`active` = 1
        {$omit_fields_sql};
    ", array_merge(array("i", (int) $resourcefrom), $omit_fields_sql_params));

    ps_query("
        INSERT INTO resource_node(resource, node, hit_count, new_hit_count)
             SELECT ?, node, 0, 0
               FROM resource_node AS rnold
          LEFT JOIN node AS n ON n.ref = rnold.node
          LEFT JOIN resource_type_field AS rtf ON n.resource_type_field = rtf.ref
              WHERE resource = ?
              AND rtf.active = 1
                AND n.`active` = 1
                {$omit_fields_sql}
                 ON DUPLICATE KEY UPDATE hit_count = rnold.new_hit_count;
    ", array_merge(array("i", $resourceto, "i", $resourcefrom), $omit_fields_sql_params));

    log_node_changes($resourceto, $nodes_to_add, array());
}

/**
* Copy all nodes from one metadata field to another one.
* Used mostly with copy field functionality
*
* @param integer $from resource_type_field ID FROM which we copy
* @param integer $to   resource_type_field ID TO which we copy
*/
function copy_resource_type_field_nodes($from, $to): bool
{
    global $FIXED_LIST_FIELD_TYPES;

    // Since field has been copied, they are both the same, so we only need to check the from field
    $type = ps_value("SELECT `type` AS `value` FROM resource_type_field WHERE ref = ?", array("i", $from), 0, "schema");

    if (!in_array($type, $FIXED_LIST_FIELD_TYPES)) {
        return false;
    }

    if (FIELD_TYPE_CATEGORY_TREE == $type) {
        $nodes = get_cattree_nodes_ordered($from, null, true);
        // Remove the fake "root" node which get_cattree_nodes_ordered() is adding since we won't be
        // using get_cattree_node_strings() with it.
        array_shift($nodes);
        $nodes = array_filter($nodes, 'node_is_active');

        // array(from_ref => new_ref)
        $processed_nodes = array();

        foreach ($nodes as $node) {
            if (array_key_exists($node['ref'], $processed_nodes)) {
                continue;
            }

            // Make the parent the expected type of a node parent (i.e. null|int) because get_cattree_nodes_ordered() is
            // setting root parent to string zero for some unknown reason.
            $parent = $node['parent'] == 0 ? null : $node['parent'];

            // Child nodes need to have their parent set to the new parent ID
            if ($parent !== null) {
                $parent = $processed_nodes[$parent];
            }

            $new_node_id = set_node(null, $to, $node['name'], $parent, $node['order_by']);
            $processed_nodes[$node['ref']] = $new_node_id;
        }

        return true;
    }

    // Default handle for types different than category trees
    $nodes = array_filter(get_nodes($from), 'node_is_active');
    foreach ($nodes as $node) {
        set_node(null, $to, $node['name'], $node['parent'], $node['order_by']);
    }

    return true;
}

/**
 * Get all the parent nodes of the given node, all the way back to the top of the node tree.
 *
 * @param  integer  $noderef        The child node ID
 * @param  bool     $detailed       Return all node data? false by default
 * @param  bool     $include_child  Include the passed node in the returned array (easier for resolving tree nodes to paths)? false by default
 *
 * @return array Array of the parent node IDs
 */
function get_parent_nodes(int $noderef, bool $detailed = false, $include_child = false)
{
    // Get all parents. Query varies according to MySQL cte support
    $mysql_version = ps_query('SELECT LEFT(VERSION(), 3) AS ver');
    if (version_compare($mysql_version[0]['ver'], '8.0', '>=')) {
        $colsa = $detailed ? "ref, name, parent, resource_type_field, order_by, `active`" : "ref, name, parent";
        $colsb = $detailed ? "n.ref, n.name, n.parent, n.resource_type_field, n.order_by, n.`active`" : "n.ref, n.name, n.parent";
        $parent_nodes = ps_query(
            "
            WITH RECURSIVE cte($colsa,level) AS
                    (
                    SELECT $colsa,
                           1 AS level
                      FROM node
                     WHERE ref= ?
                 UNION ALL
                    SELECT $colsb,
                           level+1 AS LEVEL
                      FROM  node n
                INNER JOIN  cte
                        ON  n.ref = cte.parent
                    )
            SELECT $colsa
              FROM cte
          ORDER BY level ASC;",
            ['i', $noderef]
        );
    } else {
        $colsa = $detailed ? columns_in("node", "N2") : "ref, name";
        $parent_nodes = ps_query(
            "
        SELECT  $colsa
        FROM  (SELECT @r AS p_ref,
                (SELECT @r := parent FROM node WHERE ref = p_ref) AS parent,
                @l := @l + 1 AS lvl
        FROM  (SELECT @r := ?, @l := 0) vars,
                node c
        WHERE  @r <> 0) N1
        JOIN  node N2
            ON  N1.p_ref = N2.ref
        ORDER BY  N1.lvl ASC",
            ['i', $noderef]
        );
    }

    if (!$include_child) {
        $parent_nodes = array_values(array_filter($parent_nodes, function ($node) use ($noderef) {
            return $node["ref"] != $noderef;
        }));
    }

    if (!$detailed) {
        $parent_nodes = array_column($parent_nodes, "name", "ref");
    } else {
        for ($n = 0; $n < count($parent_nodes); $n++) {
            $parent_nodes[$n]["translated_name"] = i18n_get_translated($parent_nodes[$n]["name"]);
        }
    }

    return $parent_nodes;
}

/**
* Get the total number of nodes for a specific field
*
* @param integer $resource_type_field ID of the metadata field
* @param string  $name                Filter by name of node
*
* @return integer
*/
function get_nodes_count($resource_type_field, $name = '')
{
    $query = "SELECT count(ref) AS `value` FROM node WHERE resource_type_field = ?";
    $parameters = array("i",$resource_type_field);

    if ('' != $name) {
        $query .= " AND `name` LIKE ?";
        $parameters[] = "s";
        $parameters[] = "%" . $name . "%";
    }

    return (int) ps_value($query, $parameters, 0);
}

/**
* Extract option names (in raw form if desired) from a nodes array.
*
* @param  array    $nodes               Array of nodes as returned by get_nodes()
* @param  boolean  $i18n                Set to false if you don't need to translate the option name
* @param  boolean  $index_with_node_id  Set to false if you don't want a map between node ID and its name
*
* @return array
*/
function extract_node_options(array $nodes, $i18n = true, $index_with_node_id = true)
{
    if (0 == count($nodes)) {
        return array();
    }

    $return = array();

    foreach ($nodes as $node) {
        $value = $node['name'];

        if ($i18n) {
            $value = i18n_get_translated($node['name']);
        }

        if ($index_with_node_id) {
            $return[$node['ref']] = $value;

            continue;
        }

        $return[] = $value;
    }

    return $return;
}

/**
* Search an array of nodes by name
*
* Useful to avoid querying the database multiple times
* if we already have a full detail array of nodes
*
* @uses i18n_get_translated()
*
* @param array   $nodes Nodes array as returned by get_nodes()
* @param string  $name  Filter by name of node
* @param boolean $i18n  Use the translated option value?
*
* @return array
*/
function get_node_by_name(array $nodes, $name, $i18n = true)
{
    if (0 == count($nodes) || is_null($name) || '' == trim($name)) {
        return array();
    }

    $name = mb_strtolower($name);

    foreach ($nodes as $node) {
        $option = $node['name'];

        if ($i18n) {
            $option = i18n_get_translated($node['name']);
        }

        if ($name === mb_strtolower($option)) {
            return $node;
        }
    }

    return array();
}

/**
* Return a node ID for a given string
*
* @param  string    $value                  The node name to return
* @param  integer   $resource_type_field    The field to search
*
* @return false|int false = not found
*                   integer = node ID of matching keyword.
*/
function get_node_id($value, $resource_type_field)
{
    // Finding a match MUST distinguish nodes which are different only by diacritics or casing
    $node = ps_query(
        'SELECT ref FROM node WHERE resource_type_field = ? AND `name` = BINARY(?)',
        [
            'i',$resource_type_field,
            's',$value,
        ]
    );
    return count($node) > 0 ? $node[0]['ref'] : false;
}

/**
* Comparator function for uasort to allow sorting of node array by translated name
*
* @param array   $n1 Node one to compare
* @param string  $n2 Node two to compare
*
* @return        0 means $n1 equals $n2
*               <0 means $n1 less than $n2
*               >0 means $n1 greater than $n2
*/
function node_translated_name_comparator($n1, $n2)
{
    return strcmp(
        $n1["translated_name"] ?: i18n_get_translated($n1["name"]),
        $n2["translated_name"] ?: i18n_get_translated($n2["name"])
    );
}
/**
* Comparator function for uasort to allow sorting of node array by order_by field
*
* @param array   $n1 Node one to compare
* @param string  $n2 Node two to compare
*
* @return        0 means $n1 equals $n2
*               <0 means $n1 less than $n2
*               >0 means $n1 greater than $n2
*/
function node_orderby_comparator($n1, $n2)
{
    return $n1["order_by"] - $n2["order_by"];
}

/**
 *
 * This function returns an array containing list of values for a selected field, identified by $field_label, in the multidimensional array $nodes
 *
 * @param array $nodes - node tree to parse
 * @param string $field_label - node field to retrieve value of and add to array $node_values
 * @param array $node_values  - list of values for a selected field in the node tree
 *
 * @return array $node_values
 */

function get_node_elements(array $node_values, array $nodes, $field_label)
{
    if (isset($nodes[0])) {
        foreach ($nodes as $node) {
            if (isset($node["name"])) {
                array_push($node_values, $node[$field_label]);
            }
            $node_values = (isset($node["children"])) ? get_node_elements($node_values, $node["children"], $field_label)  :  get_node_elements($node_values, $node, $field_label);
        }
    }

    return $node_values;
}

/**
 * This function returns a multidimensional array with hierarchy that reflects category tree field hierarchy, using parent and order_by fields
 *
 * @param string $parentId - elements at top of tree do not have a value for "parent" field, so default value is empty string, otherwise it is the value of the parent element in tree
 * @param array $nodes - node tree to parse and order
 *
 * @return array $tree - multidimension array containing nodes in correct hierarchical order
 *
 */

function get_node_tree($parentId = "", array $nodes = array())
{
    $tree = array();
    foreach ($nodes as $node) {
        if ($node["parent"] == $parentId) {
            $children = get_node_tree($node["ref"], $nodes);
            if ($children) {
                uasort($children, "node_orderby_comparator");
                $node["children"] = $children;
            }
            $tree[] = $node;
        }
    }
    return $tree;
}

/**
 * This function returns an array of category tree nodes in the hierarchical sequence defined in manage options
 *
 * @param array   $treefield - the category tree field to be processed
 * @param integer $resource  - the resource against which to check for selected nodes - optional
 * @param array   $allnodes  - is true if all nodes in the structure are returned, false if only selected nodes are returned
 *
 * @return array  $flatnodes - the array of nodes returned in correct hierarchical order
 *
 */
function get_cattree_nodes_ordered($treefield, $resource = null, $allnodes = false)
{
    $sql_query = "SELECT n.ref, n.resource_type_field, n.name, coalesce(n.parent, 0) parent, n.order_by, n.active, rn.resource FROM node n ";
    if ($allnodes) {
        $sql_query .= " LEFT OUTER ";
    }
    $sql_query .=  "JOIN resource_node rn on rn.resource = ? and rn.node = n.ref WHERE n.resource_type_field=? order by n.parent, n.order_by";

    $nodeentries = ps_query($sql_query, array("i", (int) $resource, "i", (int) $treefield));

    # Any node that doesn't have a parent in the nodes supplied becomes a parent in this context as its real parent might not have been selected.
    # For example, when viewing options set when $category_tree_add_parents=false
    # Needed for sorting below to ensure the container "ROOT" has child items to return.
    $selected_nodes = array_column($nodeentries, 'ref');
    for ($n = 0; $n < count($nodeentries); ++$n) {
        if ($nodeentries[$n]['parent'] !== 0 && !in_array($nodeentries[$n]['parent'], $selected_nodes)) {
            $nodeentries[$n]['parent'] = 0;
        }
    }

    # Category trees have no container root, so create one to carry all top level category tree nodes which don't have a parent
    $rootnode = cattree_node_creator(0, 0, "ROOT", null, 0, null, array(), 1);

    $nodeswithpointers = array(0 => &$rootnode);

    foreach ($nodeentries as $nodeentry) {
        $ref = $nodeentry['ref'];
        $resource_type_field = $nodeentry['resource_type_field'];
        $name = $nodeentry['name'];
        $parent = $nodeentry['parent'];
        $order_by = $nodeentry['order_by'];
        $resource = $nodeentry['resource'];
        $active = $nodeentry['active'] ?? 1;

        # Save the current node prior to establishing the pointer which can null the current node
        $savednode = null;
        if (isset($nodeswithpointers[$ref])) {
            $savednode = $nodeswithpointers[$ref];
        }

        # Establish a pointer so that this node will be a child of its parent node
        #  This means that the current node entry will be "added" to the children of the parent entry
        $nodeswithpointers[$ref] = &$nodeswithpointers[$parent]['children'][];

        # Create an entry for the current node with any existing children at this point
        $existingchildren = array();
        if ($savednode && isset($savednode['children'])) {
            $existingchildren = $savednode['children'];
        }
        $nodeswithpointers[$ref] = cattree_node_creator($ref, $resource_type_field, $name, $parent, $order_by, $resource, $existingchildren, $active);
    }

    # Flatten the tree starting at the root
    $flatnodes = cattree_node_flatten($rootnode);

    $returned_nodes = array();
    foreach ($flatnodes as $flatnode) {
        if ($allnodes || $flatnode['resource'] != '') {
            $returned_nodes[$flatnode['ref']] = $flatnode;
        }
    }
    return $returned_nodes;
}

/**
 * This function returns an array of category tree node strings in the hierarchical sequence defined in manage options
 * The returned strings are i18 translated
 *
 * @param array  $nodesordered      - the array of nodes in correct hierarchical order
 * @param array  $strings_are_paths - governs the format of the name returned
 *                 True (default) strings are paths to nodes; False strings are the individual node names
 *
 * @return array $strings         - the returned array of node paths or node names
 *
 */
function get_cattree_node_strings($nodesordered, $strings_are_paths = true)
{
    # If names are not to be returned as paths, just return the individual node names
    if (!$strings_are_paths) {
        $strings_as_names = array();
        foreach ($nodesordered as $node) {
            $strings_as_names[] = i18n_get_translated($node["name"]);
        }
        return $strings_as_names;
    }
    # Build a string consisting of a comma separated list of individual nodes and paths of consecutive child nodes
    $strings_as_paths = array();
    # Establish a list of parents referenced by the nodes
    $parents_referenced = array_column($nodesordered, 'name', 'parent');
    # Establish a list of referenced parents which are in the list
    $parents_listed = array_intersect_key($nodesordered, $parents_referenced);

    # Processing is driven by each leaf node (ie. nodes with no selected children)
    foreach ($nodesordered as $node) {
        if (!array_key_exists($node['ref'], $parents_listed)) {
            # This selected node is effectively a leaf node because it has no selected children
            # This leaf node is the first entry in the leafpath
            $leafpath = array(i18n_get_translated($node["name"]));
            $parenttofind = $node['parent'];
            # Append consecutive selected ancestors to the leafpath
            while (isset($parenttofind)) {
                if ($parenttofind == 0) { # Ignore root node
                    $parenttofind = null;
                    continue;
                }
                # If current node's parent is listed then append it to the leafpath
                if (array_key_exists($parenttofind, $parents_listed)) {
                    $leafpath[] = i18n_get_translated($parents_listed[$parenttofind]['name']);
                    $parenttofind = $parents_listed[$parenttofind]['parent'];
                } else {
                    # Current node's parent is not listed so this leafpath is complete
                    $parenttofind = null;
                }
            }
            $leafpathstring = implode("/", array_reverse($leafpath));
            $strings_as_paths[] = $leafpathstring;
        }
    }
    return $strings_as_paths;
}

/**
* Helper function for building node entry arrays for ordering
*
* @param int    $ref                    Node id
* @param int    $resource_type_field    Category tree field id
* @param string $name                   Node name
* @param int    $parent                 Parent node id
* @param int    $order_by               Node order by
* @param int    $resource               Resource id
* @param array  $children               Array of child node ids
* @param int    $active                 Node active state (0 or 1)
*
* @return array
*/
function cattree_node_creator($ref, $resource_type_field, $name, $parent, $order_by, $resource, $children, int $active): array
{
    return [
        'ref' => $ref,
        'resource_type_field' => $resource_type_field,
        'name' => $name,
        'parent' => $parent,
        'order_by' => $order_by,
        'resource' => $resource,
        'children' => $children,
        'active' => $active,
    ];
}

/**
* Helper function which adds child nodes after each flattened parent node
*
* @param array  $node   Array of nodes each with a child node array
*
* @return array         Array of nodes with child nodes flattened out after their respective parents
*/
function cattree_node_flatten(array $node): array
{
    # Build node being flattened
    $flat_element = [
        'ref' => (string) $node['ref'],
        'resource_type_field' => (string) $node['resource_type_field'],
        'name' => (string) $node['name'],
        'parent' => (string) $node['parent'],
        'order_by' => (string) $node['order_by'],
        'resource' => (string) $node['resource'],
        'active' => (int) $node['active'],
    ];
    $children = $node['children'] ?? [];

    # Append children after flattened node
    $cumulative_entries = array($flat_element);
    foreach ($children as $child) {
        $cumulative_entries = array_merge($cumulative_entries, cattree_node_flatten($child));
    }
    return $cumulative_entries;
}

/**
 * This function returns an array of strings that represent the full paths to each tree node passed
 *
 * @param array $resource_nodes - node tree to parse
 * @param bool  $allnodes       - include paths to all nodes -if false will just include the paths to the end leaf nodes
 * @param bool  $translate      - translate strings?
 *
 * @return array $nodestrings - array of strings for all nodes passed in correct hierarchical order
 *
 */
function get_node_strings($resource_nodes, $allnodes = false, $translate = true): array
{
    // Arrange all passed nodes with parents first so that unnecessary paths can be removed
    $orderednodes = order_tree_nodes($resource_nodes);
    // Create an array of all branch nodes for each node
    $nodestrings = array();
    foreach ($orderednodes as $resource_node) {
        $path = $translate ? $resource_node["translated_path"] : $resource_node["path"];
        if (!$allnodes && isset($nodestrings[$resource_node["parent"]])) {
            unset($nodestrings[$resource_node["parent"]]);
        }

        $nodestrings[$resource_node["ref"]] = $path;
    }
    return $nodestrings;
}

/**
* Get to the root of the branch starting from a node.
*
* IMPORTANT: the term nodes here is generic, it refers to a tree node structure containing at least ref and parent
*
* @param  array    $nodes  List of nodes to search through (MUST contain elements with at least the "ref" index)
* @param  integer  $id     Node ref we compute the branch path for
*
* @return array Branch path structure starting from root to the searched node (inclusive)
*/
function compute_node_branch_path(array $nodes, int $id)
{
    if (empty($nodes)) {
        return array();
    }

    global $NODE_BRANCH_PATHS_CACHE;
    $NODE_BRANCH_PATHS_CACHE = (!is_null($NODE_BRANCH_PATHS_CACHE) && is_array($NODE_BRANCH_PATHS_CACHE) ? $NODE_BRANCH_PATHS_CACHE : array());
    // create a unique ID for this list of nodes since these can be used for anything
    $nodes_list_id = md5(json_encode($nodes));

    if (isset($NODE_BRANCH_PATHS_CACHE[$nodes_list_id][$id])) {
        return $NODE_BRANCH_PATHS_CACHE[$nodes_list_id][$id];
    }

    $nodes_ref_list = array_column($nodes, 'ref');

    $found_node_index = array_search($id, $nodes_ref_list);
    if ($found_node_index === false) {
        return array();
    }

    $node = $nodes[$found_node_index];
    $node_parent = (isset($node["parent"]) && $node["parent"] > 0 ? (int) $node["parent"] : null);

    $path = array($node);
    while (!is_null($node_parent)) {
        // Parent node path is already known (cached), use it instead of recalculating it
        if (isset($NODE_BRANCH_PATHS_CACHE[$nodes_list_id][$node_parent])) {
            $parent_branch_reversed = array_reverse($NODE_BRANCH_PATHS_CACHE[$nodes_list_id][$node_parent]);
            $path = array_merge($path, $parent_branch_reversed);
            break;
        }

        $found_node_index = array_search($node_parent, $nodes_ref_list);
        if ($found_node_index === false) {
            break;
        }

        $node = $nodes[$found_node_index];
        $node_parent = (isset($node["parent"]) && $node["parent"] > 0 ? (int) $node["parent"] : null);

        $path[] = $node;
    }

    $path_reverse = array_reverse($path);
    $NODE_BRANCH_PATHS_CACHE[$nodes_list_id][$id] = $path_reverse;

    return $path_reverse;
}

/**
* Find all nodes with parent
*
* @param  array    $nodes  List of nodes to search through (MUST contain elements with at least the "parent" index)
* @param  integer  $id     Parent node ref to search by
*
* @return array
*/
function compute_nodes_by_parent(array $nodes, int $id): array
{
    $found_nodes_keys = array_keys(array_column($nodes, 'parent'), $id);

    $result = array();
    foreach ($found_nodes_keys as $nodes_key) {
        if (!isset($nodes[$nodes_key])) {
            continue;
        }

        $result[] = $nodes[$nodes_key];
    }

    return $result;
}

/**
* Get all nodes for given resources and fields. Returns a multidimensional array wth resource IDs as top level indexes and field IDs as second level indexes
*
* @param array $resources
* @param array $resource_type_fields
* @param boolean $detailed             Set to true to return full node details (as get_node() does)
* @param boolean $node_sort            Set to SORT_ASC to sort nodes ascending, SORT_DESC sort nodes descending, null means do not sort
*
* @return array
*/
function get_resource_nodes_batch(array $resources, array $resource_type_fields = array(), bool $detailed = false, $node_sort = null)
{
    $sql_select = "rn.resource, n.ref, n.resource_type_field ";

    if ($detailed) {
        $sql_select .= ', n.`name`, n.parent, n.order_by, n.`active`';
    }

    $resources = array_filter($resources, "is_int_loose");
    if (empty($resources)) {
        return [];
    }

    $chunks = array_chunk($resources, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    $noderows = [];
    foreach ($chunks as $chunk) {
        $query = "SELECT {$sql_select} FROM resource_node rn LEFT JOIN node n ON n.ref = rn.node WHERE rn.resource IN (" . ps_param_insert(count($chunk)) . ")";
        $query_params = ps_param_fill($chunk, "i");

        if (is_array($resource_type_fields) && count($resource_type_fields) > 0) {
            $fields = array_filter($resource_type_fields, "is_int_loose");
            if (count($fields) > 0) {
                $query .= " AND n.resource_type_field IN (" . ps_param_insert(count($fields)) . ")";
                $query_params = array_merge($query_params, ps_param_fill($fields, "i"));
            }
        }

        if (!is_null($node_sort)) {
            if ($node_sort == SORT_ASC) {
                $query .= " ORDER BY n.ref ASC";
            }
            if ($node_sort == SORT_DESC) {
                $query .= " ORDER BY n.ref DESC";
            }
        }

        $newnoderows = ps_query($query, $query_params);
        $noderows = array_merge($noderows, $newnoderows);
    }

    $results = array();
    foreach ($noderows as $noderow) {
        if (!isset($results[$noderow["resource"]])) {
            $results[$noderow["resource"]] = array();
        }
        if (!isset($results[$noderow["resource"]][$noderow["resource_type_field"]])) {
            $results[$noderow["resource"]][$noderow["resource_type_field"]] = array();
        }

        if ($detailed) {
            $results[$noderow["resource"]][$noderow["resource_type_field"]][] = [
                "ref"                   => $noderow["ref"],
                "resource_type_field"   => $noderow["resource_type_field"],
                "name"                  => $noderow["name"],
                "parent"                => $noderow["parent"],
                "order_by"              => $noderow["order_by"],
                'active'                => $noderow['active'],
            ];
        } else {
            $results[$noderow["resource"]][$noderow["resource_type_field"]][] = $noderow["ref"];
        }
    }

    return $results;
}

/**
* Process one of the columns whose value is a search string containing nodes (e.g @@228@229, @@555) and mutate input array
* by adding a new column (named $column + '_node_name') which will hold the nodes found in the search string and their
* translated names
*
* @param array  $R      Generic type for array (e.g DB results). Each value is a result row.
* @param string $column Record column which needs to be checked and its value converted (if applicable)
*
* @return array
*/
function process_node_search_syntax_to_names(array $R, string $column)
{
    $all_nodes = [];
    $record_node_buckets = [];

    foreach ($R as $idx => $record) {
        if (!(is_array($record) && isset($record[$column]))) {
            continue;
        }

        $search = $record[$column];
        $node_bucket = $node_bucket_not = [];
        resolve_given_nodes($search, $node_bucket, $node_bucket_not);

        // Build list of nodes identified (so we can get their details later)
        foreach ($node_bucket as $node_refs) {
            $all_nodes = array_merge($all_nodes, $node_refs);
        }
        $all_nodes = array_merge($all_nodes, $node_bucket_not);

        // Add node buckets found for this record
        $record_node_buckets[$idx] = [
            'node_bucket' => $node_bucket,
            'node_bucket_not' => $node_bucket_not,
        ];
    }

    // Translate nodes
    $node_details = get_nodes_by_refs(array_unique($all_nodes));
    $i18l_nodes = [];
    foreach ($node_details as $node) {
        $i18l_nodes[$node['ref']] = i18n_get_translated($node['name']);
    }

    // Convert the $column value to URL
    $new_col_name = "{$column}_node_name";
    $syntax_desc_tpl = '%s - "%s"<br>';
    foreach ($R as $idx => $record) {
        // mutate array - add a new column for all records
        $R[$idx][$new_col_name] = '';

        if (!(is_array($record) && isset($record[$column]) && isset($record_node_buckets[$idx]))) {
            continue;
        }

        foreach ($record_node_buckets[$idx] as $bucket_type => $node_buckets) {
            $prefix = ($bucket_type === 'node_bucket' ? NODE_TOKEN_PREFIX : NODE_TOKEN_PREFIX . NODE_TOKEN_NOT);

            foreach ($node_buckets as $node_ref) {
                $nodes = (is_array($node_ref) ? $node_ref : [$node_ref]);
                foreach ($nodes as $node) {
                    if (!isset($i18l_nodes[$node])) {
                        continue;
                    }

                    $R[$idx][$new_col_name] .= sprintf($syntax_desc_tpl, "{$prefix}{$node}", $i18l_nodes[$node]);
                }
            }
        }
    }

    return $R;
}

/**
 * Delete unused non-fixed list field nodes with a 1:1 resource association
 *
 * @param integer $resource_type_field Resource type field (metadata field) ID
 */
function delete_unused_non_fixed_list_nodes(int $resource_type_field)
{
    if ($resource_type_field <= 0) {
        return;
    }

    // Delete nodes that no longer have a resource association
    ps_query(
        'DELETE n
              FROM node AS n
        INNER JOIN resource_type_field AS rtf ON n.resource_type_field = rtf.ref
         LEFT JOIN resource_node AS rn ON rn.node = n.ref
             WHERE n.resource_type_field = ?
               AND rtf.`type`IN (' . ps_param_insert(count(NON_FIXED_LIST_SINGULAR_RESOURCE_VALUE_FIELD_TYPES)) . ')
               AND rn.node IS NULL',
        array_merge(['i', $resource_type_field], ps_param_fill(NON_FIXED_LIST_SINGULAR_RESOURCE_VALUE_FIELD_TYPES, 'i'))
    );
    remove_invalid_node_keyword_mappings();
}

/**
 * Delete invalid node_keyword associations. Note, by invalid, it's meant where the node is missing.
 */
function remove_invalid_node_keyword_mappings()
{
    ps_query('DELETE nk FROM node_keyword AS nk LEFT JOIN node AS n ON n.ref = nk.node WHERE n.ref IS NULL');
}

/**
 * Delete invalid resource_node associations. Note, by invalid, it's meant where the node is missing.
 */
function remove_invalid_resource_node_mappings()
{
    ps_query('DELETE rn FROM resource_node AS rn LEFT JOIN node AS n ON n.ref = rn.node WHERE n.ref IS NULL');
}

/**
 * Get a count of how many resources are using the specified nodes
 *
 * @param array $nodes      Array of node refs
 *
 * @return array            Array of node ref as keys and number of resources using them as the values
 */
function get_nodes_use_count(array $nodes)
{
    $nodes = array_filter($nodes, 'is_int_loose');
    if (empty($nodes)) {
        return [];
    }

    $nodes_use_count = ps_query(
        'SELECT node, COUNT(node) AS `use_count` FROM resource_node WHERE node IN (' . ps_param_insert(count($nodes)) . ') GROUP BY node',
        ps_param_fill($nodes, 'i')
    );

    return array_column($nodes_use_count, 'use_count', 'node');
}

/**
 * Check array of nodes and delete any that relate to non-fixed list fields and are unused
 *
 * @param array $nodes Array of node IDs
 */
function check_delete_nodes($nodes)
{
    global $FIXED_LIST_FIELD_TYPES;
    debug_function_call('check_delete_nodes', func_get_args());

    // Check and delete unused nodes
    $count = get_nodes_use_count($nodes);
    foreach ($nodes as $node) {
        $nodeinfo = [];
        get_node($node, $nodeinfo);
        if (isset($nodeinfo["resource_type_field"])) {
            $fieldinfo  = get_resource_type_field($nodeinfo["resource_type_field"]);
            debug("check_delete_nodes: checking node " . $node . " - (" . $nodeinfo["name"] . ")");
            if (
                !in_array($fieldinfo["type"], $FIXED_LIST_FIELD_TYPES)
                && (!isset($count[$node]) ||  $count[$node] == 0)
            ) {
                    debug("Deleting unused node #" . $node . " - (" . $nodeinfo["name"] . ")");
                    delete_node($node);
            }
        }
    }
}

/**
* Delete all keywords for all nodes associated with the specified field
*
* @param  integer  $field  Field ID
*
* @return void
*/
function remove_field_keywords($field)
{
    ps_query("DELETE nk FROM node_keyword nk LEFT JOIN node n ON n.ref=nk.node WHERE n.resource_type_field = ?", ["i",$field]);
}

/**
 * For the specified $resource, increment the hitcount for each node in array
 *
 * @param  integer $resource
 * @param  array $nodes
 * @return void
 */
function update_resource_node_hitcount($resource, $nodes)
{
    if (!is_array($nodes)) {
        $nodes = array($nodes);
    }
    if (count($nodes) > 0) {
        ps_query("UPDATE resource_node SET new_hit_count = new_hit_count + 1 WHERE resource = ? AND node IN (" . ps_param_insert(count($nodes)) . ")", array_merge(array("i", $resource), ps_param_fill($nodes, "i")), false, -1, true, 0);
    }
}

/**
 * Order array of tree nodes into logical order - Each parent followed by its child nodes,  all following order_by
 *
 * @param array $nodes      Array of detailed nodes
 *
 * @return array            Full nodes in order
 *
 */
function order_tree_nodes($nodes, $order_by_translated_name = false)
{
    if (count($nodes) == 0) {
        return [];
    }

    if ($order_by_translated_name) {
        $sort = 'node_translated_name_comparator';
    } else {
        $sort = 'node_orderby_comparator';
    }
    // Find parent nodes first
    $parents = array_column($nodes, "parent");
    $toplevels = min($parents) > 0 ? $parents : [0];
    $orderednodes = array_values(array_filter($nodes, function ($node) use ($toplevels) {
        return in_array((int)$node["parent"], $toplevels);
    }));

    if (!$order_by_translated_name) {
        usort($orderednodes, $sort);
    }

    for ($n = 0; $n < count($orderednodes); $n++) {
        $orderednodes[$n]["path"] = $orderednodes[$n]["name"];
        if (!isset($orderednodes[$n]["translated_name"]) || is_i18n_language_string($orderednodes[$n]["translated_name"])) {
            // Where translated_path is in i18n format, add_sql_node_language() couldn't find a match with the current language. Translate it to get default language.
            $orderednodes[$n]["translated_path"] = i18n_get_translated($orderednodes[$n]["name"]);
        } else {
            $orderednodes[$n]["translated_path"] = $orderednodes[$n]["translated_name"];
        }
    }

    // Find child nodes
    $parents_processed = [];
    while (count($nodes) > 0) {
        // Loop to find children
        for ($n = 0; $n < count($orderednodes); $n++) {
            if (!in_array($orderednodes[$n]["ref"], $parents_processed)) {
                // Add the children of this node with the the path added (relative to paremnt)
                $children = array_filter($nodes, function ($node) use ($orderednodes, $n) {
                    return (int)$node["parent"] == $orderednodes[$n]["ref"];
                });
                // Set order
                uasort($children, $sort);
                $children = array_values($children);
                for ($c = 0; $c < count($children); $c++) {
                    $children[$c]["path"] = $orderednodes[$n]["path"] . "/" .  $children[$c]["name"];
                    if (!isset($children[$c]["translated_name"]) || is_i18n_language_string($children[$c]["translated_name"])) {
                        // Where translated_path is in i18n format, add_sql_node_language() couldn't find a match with the current language. Translate it to get default language.
                        $children[$c]["translated_path"] = $orderednodes[$n]["translated_path"] . "/" .  i18n_get_translated($children[$c]["name"]);
                    } else {
                        $children[$c]["translated_path"] = $orderednodes[$n]["translated_path"] . "/" .  ($children[$c]["translated_name"]);
                    }
                    // Insert the child after the parent and any nodes with a lower order_by value
                    array_splice($orderednodes, $n + 1 + $c, 0, [$children[$c]]);
                    // Remove child from $treenodes
                    $pos = array_search($children[$c]["ref"], array_column($nodes, "ref"));
                    unset($nodes[$pos]);
                    $nodes = array_values($nodes);
                }
                $parents_processed[] = $orderednodes[$n]["ref"];
            } else {
                $pos = array_search($orderednodes[$n]["ref"], array_column($nodes, "ref"));
                unset($nodes[$pos]);
            }
        }
        $nodes = array_values($nodes);
    }
    return $orderednodes;
}

/**
 * Append SQL to an existing node query to obtain the translated names of the node
 *
 * @param string $sql_select    SQL query
 * @param array  $sql_params    Array of SQL parameters
 *
 * @return void
 *
 */
function add_sql_node_language(&$sql_select, &$sql_params, string $alias = "node")
{
    global $language,$defaultlanguage;

    // Use language specified, if not use default
    isset($language) ? $language_in_use = $language : $language_in_use = $defaultlanguage;

    // Get length of language string + 2 (for ~ and :) for usage in SQL below
    $language_string_length = (strlen($language_in_use) + 2);

    $sql_params = array_merge($sql_params, [
        "s","~" . $language_in_use,
        "s","~" . $language_in_use . ":",
        "i",$language_string_length,
        "s","~" . $language_in_use . ":",
        "i",$language_string_length,
        "s","~" . $language_in_use . ":",
        "i",$language_string_length,
        ]);
    $sql_select .= ", 
        CASE
        WHEN
            POSITION(? IN " . $alias . ".name) > 0
        THEN
            TRIM(SUBSTRING(name,
                    POSITION(? IN " . $alias . ".name) + ?,
                    CASE
                        WHEN
                            POSITION('~' IN SUBSTRING(" . $alias . ".name,
                                    POSITION(? IN " . $alias . ".name) + ?,
                                    LENGTH(" . $alias . ".name) - 1)) > 0
                        THEN
                            POSITION('~' IN SUBSTRING(" . $alias . ".name,
                                    POSITION(? IN " . $alias . ".name) + ?,
                                    LENGTH(" . $alias . ".name) - 1)) - 1
                        ELSE LENGTH(" . $alias . ".name)
                    END))
        ELSE TRIM(" . $alias . ".name)
        END AS translated_name";
}

/**
 * Migrate fixed list field data to text field data for a given resource reference. Useful when changing resource type field from a data type
 * that can contain multiple values such as a dynamic keywords field. This script will concatenate the existing values and leave one remaining
 * node for the new text field.
 *
 * @param  mixed $resource_type_field   Resource type field id. ** The field type should have been changed to a text type in advance
 *                                       - additional checks maybe need before calling this to ensure the fields are / were of the expected type.
 *                                       see examples in pages/tools/migrate_fixed_to_text.php **
 * @param  mixed $resource              Resource reference to be processed.
 * @param  mixed $category_tree         Was the field data being migrated previously of type category tree? Specifying true will allow the format
 *                                      of category tree branches to be preserved e.g. "level1/value, level2/value"
 * @param  mixed $separator             Default is comma and space e.g. "value1, value2"
 *
 * @return bool   True on success else false.
 */
function migrate_fixed_to_text(int $resource_type_field, int $resource, bool $category_tree, string $separator = ', '): bool
{
    $current_nodes = get_resource_nodes($resource, $resource_type_field, true, SORT_ASC); # Ordering will be as displayed on the view page.

    if (count($current_nodes) < 2) {
        return true; # No need to make changes as no node / only one node present.
    }

    if ($category_tree) {
        $all_treenodes = get_cattree_nodes_ordered($resource_type_field, $resource, false);
        $treenodenames = get_cattree_node_strings($all_treenodes, true);
        $new_value = implode($separator, $treenodenames);
    } else {
        $current_nodes_names = array_column($current_nodes, 'name');
        $current_nodes_translated = array_map("i18n_get_translated", $current_nodes_names);
        $new_value = implode($separator, $current_nodes_translated);
    }

    delete_resource_nodes($resource, array_column($current_nodes, 'ref'), false);
    $savenode = set_node(null, $resource_type_field, $new_value, null, 0);
    return add_resource_nodes($resource, [$savenode], true, false);
}

/**
 * Remove invalid field data from resources, optionally just for the specified resource types and/or fields
 *
 * @param array $fields=[]      Array of resource_type_field refs
 * @param array $restypes=[]    Array of resource_type refs
 * @param bool  $dryrun         Don't delete, just return count of rows that will be affected
 *
 * @return int Count of rows deleted/to delete
 *
 */
function cleanup_invalid_nodes(array $fields = [], array $restypes = [], bool $dryrun = false)
{
    $allrestypes = get_resource_types('', false, false, true);
    $allrestyperefs = array_column($allrestypes, "ref");
    $allfields = get_resource_type_fields();
    $fieldglobals = array_column($allfields, "global", "ref");
    $joined_fields = get_resource_table_joins();

    $restypes = array_filter($restypes, function ($val) {
        return $val > 0;
    });
    $fields = array_filter($fields, function ($val) {
        return $val > 0;
    });

    $fields = count($fields) > 0 ? array_intersect($fields, array_column($allfields, "ref")) : array_column($allfields, "ref");
    $restypes = count($restypes) > 0 ? array_intersect($restypes, $allrestyperefs) : $allrestyperefs;
    $restype_mappings = get_resource_type_field_resource_types();
    $deletedrows = 0;
    foreach ($restypes as $restype) {
        if (!in_array($restype, $allrestyperefs)) {
            continue;
        }
        // Find invalid fields for this resource type
        $remove_fields = [];
        foreach ($fields as $field) {
            if (!in_array($field, array_column($allfields, "ref"))) {
                continue;
            }
            if ((int)$fieldglobals[$field] == 0 && !in_array($restype, $restype_mappings[$field])) {
                $remove_fields[] = $field;
            }
        }

        if (count($remove_fields) > 0) {
            if ($dryrun) {
                $query = "SELECT COUNT(*) AS value FROM resource_node LEFT JOIN resource r ON r.ref=resource_node.resource LEFT JOIN node n ON n.ref=resource_node.node WHERE r.resource_type = ? AND n.resource_type_field IN (" . ps_param_insert(count($remove_fields))  . ");";
                $params = array_merge(["i",$restype], ps_param_fill($remove_fields, "i"));
                $deletedrows = ps_value($query, $params, 0);
            } else {
                $query = "DELETE rn.* FROM resource_node rn LEFT JOIN resource r ON r.ref=rn.resource LEFT JOIN node n ON n.ref=rn.node WHERE r.resource_type = ? AND n.resource_type_field IN (" . ps_param_insert(count($remove_fields))  . ");";
                $params = array_merge(["i",$restype], ps_param_fill($remove_fields, "i"));
                ps_query($query, $params);
                $deletedrows += sql_affected_rows();

                # Also remove data in joined fields.
                foreach ($remove_fields as $check_joined_field) {
                    if (in_array($check_joined_field, $joined_fields)) {
                        ps_query("UPDATE resource SET `field" . $check_joined_field . "` = null WHERE resource_type = ?", array("i", $restype));
                    }
                }
            }
        }
    }
    return $deletedrows > 0 ? ((!$dryrun ? "Deleted " : "Found ") . $deletedrows . " row(s)") :  "No rows found";
}

/**
 * Batch update nodes' active state to the database. The same state will apply to all nodes in the list.
 *
 * For logic on which nodes to toggle {@see toggle_active_state_for_nodes()}
 *
 * @param list<int> $refs Node IDs
 * @param bool $active Should nodes be active or not?
 */
function update_node_active_state(array $refs, bool $active): void
{
    if ($refs === []) {
        return;
    }

    $refs_chunked = db_chunk_id_list($refs);
    if ($refs_chunked === []) {
        return;
    }

    db_begin_transaction('set_node_active_state');

    foreach ($refs_chunked as $refs_chunk) {
        ps_query(
            sprintf(
                'UPDATE node AS n
                INNER JOIN resource_type_field AS rtf ON n.resource_type_field = rtf.ref
                       SET n.`active` = ?
                     WHERE n.`ref` IN (%s)
                       AND rtf.`type` IN (%s)',
                ps_param_insert(count($refs_chunk)),
                ps_param_insert(count($GLOBALS['FIXED_LIST_FIELD_TYPES']))
            ),
            array_merge(
                ['i', $active],
                ps_param_fill($refs_chunk, 'i'),
                ps_param_fill($GLOBALS['FIXED_LIST_FIELD_TYPES'], 'i')
            )
        );
    }

    db_end_transaction('set_node_active_state');
}

/**
 * Toggle nodes' active state
 *
 * @param list<int> $refs Node IDs
 * @return array<int, 0|1>
 */
function toggle_active_state_for_nodes(array $refs): array
{
    $refs_chunked = db_chunk_id_list($refs);
    $rtfs_tree = array_column(get_resource_type_fields('', 'ref', 'asc', '', [FIELD_TYPE_CATEGORY_TREE]), 'ref');
    $tree_nodes_by_rtf = [];
    $nodes_new_state = [];

    foreach ($refs_chunked as $refs_chunk) {
        db_begin_transaction('toggle_node_active_state');
        $nodes = get_nodes_by_refs($refs_chunk);
        $nodes_to_toggle = [];

        foreach ($nodes as $node) {
            if (in_array($node['resource_type_field'], $rtfs_tree)) {
                // Build a list of nodes, grouped by resource type fields because changes to a tree need to follow its structure
                $tree_nodes_by_rtf[$node['resource_type_field']][] = $node['ref'];
            } else {
                // Simple fixed list fields get toggled straight away
                $nodes_to_toggle[] = $node['ref'];
            }
        }

        if ($nodes_to_toggle !== []) {
            ps_query(
                sprintf(
                    'UPDATE node AS n
                    INNER JOIN resource_type_field AS rtf ON n.resource_type_field = rtf.ref
                        SET n.`active` = IF(n.`active` = 1, 0, 1)
                        WHERE n.`ref` IN (%s)
                        AND rtf.`type` IN (%s)',
                    ps_param_insert(count($nodes_to_toggle)),
                    ps_param_insert(count($GLOBALS['FIXED_LIST_FIELD_TYPES']))
                ),
                array_merge(ps_param_fill($nodes_to_toggle, 'i'), ps_param_fill($GLOBALS['FIXED_LIST_FIELD_TYPES'], 'i'))
            );
        }

        db_end_transaction('toggle_node_active_state');
        if ($nodes_to_toggle !== []) {
            $nodes_new_state += array_column(get_nodes_by_refs($nodes_to_toggle), 'active', 'ref');
        }
    }

    foreach ($tree_nodes_by_rtf as $rtf => $nodes_list) {
        $nodes_new_state += toggle_category_tree_nodes_active_state($rtf, $nodes_list);
    }

    clear_query_cache('schema');
    return $nodes_new_state;
}

/**
 * Toggle category tree nodes' active state
 *
 * @param int $rtf Resource type field ID
 * @param list<int> $node_refs
 * @return array<int, 0|1>
 */
function toggle_category_tree_nodes_active_state(int $rtf, array $node_refs): array
{
    $is_not_active = fn(int $active): bool => $active === 0;
    $nodes_to_enable = [];
    $nodes_to_disable = [];

    $rtf_nodes = get_cattree_nodes_ordered($rtf, null, true);
    // Remove the fake "root" node which get_cattree_nodes_ordered() is adding since we won't be
    // using get_cattree_node_strings() with it.
    array_shift($rtf_nodes);
    $rtf_nodes_indexed = array_column($rtf_nodes, null, 'ref');
    $nodes_ordered = array_keys(array_intersect_key($rtf_nodes_indexed, array_flip($node_refs)));

    foreach ($nodes_ordered as $node_ref) {
        if (in_array($node_ref, $nodes_to_disable)) {
            // Child node disabled previously by a parent - no need to carry on processing it (already done because of
            // a parents' change on its path)
            continue;
        }

        $node = $rtf_nodes_indexed[$node_ref];
        $node_is_active = $node['active'] === 1;

        // Check that no parent on this branch path is disabled
        $node_branch = array_column(
            compute_node_branch_path(array_values($rtf_nodes_indexed), $node['ref']),
            'active',
            'ref'
        );
        array_pop($node_branch);
        if (array_filter($node_branch, $is_not_active) !== []) {
            // Node should already be disabled, add it to the list so we can return it
            $nodes_to_disable[] = $node['ref'];
            continue;
        }

        if ($node_is_active) {
            // Disable - this MUST propagate to its children (if any)
            $branch_refs = array_column(
                cattree_node_flatten(
                    array_merge(
                        $node,
                        [
                            'resource' => null, # Fake it for cattree_node_flatten()
                            'children' => get_node_tree($node['ref'], $rtf_nodes_indexed),
                        ]
                    )
                ),
                'ref'
            );

            $nodes_to_disable = array_merge($nodes_to_disable, $branch_refs);
            foreach ($branch_refs as $path_node_ref) {
                $rtf_nodes_indexed[$path_node_ref]['active'] = 0;
            }
        } else {
            // Activate - this MUST NOT propagate to its children (if any)
            $nodes_to_enable[] = $node_ref;

            // Pretend this has been done in case there's going to be a child node in the queue waiting to be toggled
            $rtf_nodes_indexed[$node_ref]['active'] = 1;
        }
    }

    update_node_active_state($nodes_to_enable, true);
    update_node_active_state($nodes_to_disable, false);

    return array_column(get_nodes_by_refs(array_merge($nodes_to_enable, $nodes_to_disable)), 'active', 'ref');
}

/** Helper function to check if a node is active
 * Note: a node here requires the "active" key.
 * @param array{'active': 0|1} $node A node structure with at least the "active" key present
 */
function node_is_active(array $node): bool
{
    return $node['active'] === 1;
}

/**
 * Suggest dynamic keyword nodes. Used by pages/edit_fields/9_ajax/suggest_keywords.php
 *
 * @param int $field        Metadata field ID
 * @param string $keyword   String to match
 * @param bool $readonly    Add an option to add new node if no exact match
 *
 */
function suggest_dynamic_keyword_nodes(int $field, string $keyword, bool $readonly): array
{
    if (checkperm("bdk" . $field) || !metadata_field_edit_access($field)) {
        // Not permitted to add new nodes
        $readonly = true;
    }
    $fielddata = get_resource_type_field($field);
    if (
        !$fielddata
        || !metadata_field_view_access($field)
    ) {
        return [];
    }

    $nodes = get_nodes($field);

    // Return matches
    $exactmatch = false;
    $results    = [];
    $match_is_deactivated = false;

    // Set $keywords_remove_diacritics so as to only add versions with diacritics to return array if none are in the submitted string
    $GLOBALS['keywords_remove_diacritics'] = mb_strlen($keyword) === strlen($keyword);
    $keyword = normalize_keyword($keyword);

    foreach ($nodes as $node) {
        $trans = i18n_get_translated($node['name'], true);
        $compare = normalize_keyword($trans);
        $node_is_active = node_is_active($node);
        if ($GLOBALS['dynamic_keyword_suggest_contains']) {
            if (
                '' != $trans
                && (
                    !isset($GLOBALS['dynamic_keyword_suggest_contains_characters'])
                    || $GLOBALS['dynamic_keyword_suggest_contains_characters'] <= mb_strlen($keyword)
                )
                && mb_strpos(mb_strtolower($compare), mb_strtolower($keyword)) !== false
            ) {
                if (mb_strtolower($compare) == mb_strtolower($keyword)) {
                    $exactmatch = true;
                    $match_is_deactivated = !$node_is_active;
                }

                if ($node_is_active) {
                    $results[] = [
                        'label' => $trans,
                        'value' => $node['ref']
                    ];
                }
            }
        } else {
            if ('' != $compare && mb_substr(mb_strtolower($compare), 0, mb_strlen($keyword)) == mb_strtolower($keyword)) {
                if (mb_strtolower($compare) == mb_strtolower($keyword)) {
                    $exactmatch = true;
                    $match_is_deactivated = !$node_is_active;
                }

                if ($node_is_active) {
                    $results[] = [
                        'label' => $trans,
                        'value' => $node['ref']
                    ];
                }
            }
        }
    }

    $keyword = stripslashes($keyword);

    $fielderror = false;
    if (!$exactmatch && !$readonly) {
        # Ensure regexp filter is honoured if one is present
        if (
            strlen(trim((string)$fielddata["regexp_filter"])) >= 1
            && preg_match("#^" . str_replace($GLOBALS['regexp_slash_replace'], '\\', $fielddata["regexp_filter"]) . "$#", $keyword, $matches) <= 0
        ) {
            $fielderror = true;
        }

        if (!$fielderror) {
            $results[] = array(
                'label' => "{$GLOBALS['lang']['createnewentryfor']} {$keyword}",
                'value' => "{$GLOBALS['lang']['createnewentryfor']} {$keyword}"
            );
        } else {
            $results[] = array(
                'label' => "{$GLOBALS['lang']['keywordfailedregexfilter']} {$keyword}",
                'value' => "{$GLOBALS['lang']['keywordfailedregexfilter']} {$keyword}"
            );
        }
    } elseif ($exactmatch && $match_is_deactivated) {
        $text = "{$GLOBALS['lang']['inactive_entry_matched']} {$keyword}";
        $results = [
            [
                'label' => $text,
                'value' => $text,
            ]
        ];
    } elseif ($readonly && empty($results)) {
        $results[] = array(
                'label' => "{$GLOBALS['lang']['noentryexists']} {$keyword}",
                'value' => "{$GLOBALS['lang']['noentryexists']} {$keyword}"
            );
    }
    return $results;
}