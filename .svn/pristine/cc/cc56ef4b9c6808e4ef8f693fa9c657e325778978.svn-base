<?php
/* -------- Category Tree ------------------- */
global $lang, $baseurl, $css_reload_key;
global $category_tree_open, $is_search, $category_tree_add_parents,
$category_tree_remove_children, $k;

$is_search      = (isset($is_search) ? $is_search : false);
$forsearchbar   = (isset($forsearchbar) ? $forsearchbar : false);
$edit_autosave  = (isset($edit_autosave) ? $edit_autosave : false);

$hidden_input_elements             = '';
$hidden_input_elements_id_prefix   = ($is_search ? 'nodes_searched_' : 'nodes_');
$status_box_id                     = ($is_search ? "nodes_searched_{$field['ref']}_statusbox" : "nodes_{$field['ref']}_statusbox");
$status_box_elements               = '';
$update_result_count_function_call = 'UpdateResultCount();';
$tree_id                           = ($is_search ? "search_tree_{$field['ref']}" : "tree_{$field['ref']}");
$tree_container_styling            = ($category_tree_open ? 'display: block;' : 'display: none;');

$current_val_fieldname = "field_{$field['ref']}_currentval";

if (isset($user_set_values[$field['ref']]) && is_array($user_set_values[$field['ref']]) && !empty($user_set_values[$field['ref']])) {
    // User set values are options selected by user - used to render what users selected before submitting the form and
    // receiving an error (e.g required field missing
    $selected_nodes = $user_set_values[$field['ref']];
} elseif (isset($field_nodes)) {
    $selected_nodes = $field_nodes;
} elseif (isset($searched_nodes) && is_array($searched_nodes)) {
    $selected_nodes = $searched_nodes;
} elseif (!isset($selected_nodes)) {
    $selected_nodes = [];
}

// When editing multiple resources, we don't want to preselect any options; the user must make the necessary selection
if ($multiple && $copyfrom == '') {
    $selected_nodes = [];
}

// Strip out invalid nodes
$valid_nodes = [];
foreach ($selected_nodes as $node) {
    $node_data = array();
    if (get_node($node, $node_data) && $node_data["resource_type_field"] != $field["ref"]) {
        continue;
    }

    if (get_node($node, $node_data) === false) {
        continue;
    }
    $valid_nodes [] = $node_data;
}

$valid_nodes = array_filter($valid_nodes, 'node_is_active');

foreach ($valid_nodes as $node) {
    $hidden_input_elements .= "<input id=\"{$hidden_input_elements_id_prefix}{$node["ref"]}\" class =\"{$tree_id}_nodes\" type=\"hidden\" name=\"" . escape($name) . "\" value=\"{$node["ref"]}\">";

    // Show previously selected options on the status box
    if (!(isset($treeonly) && $treeonly)) {
        $status_box_elements .= "<div id=\"" . $tree_id . "_selected_" . $node['ref'] . "\" class=\"" . $tree_id . "_option_status\"  ><span id=\"{$status_box_id}_option_{$node['ref']}\">" . escape(i18n_get_translated($node['name'])) . "</span><br /></div>";
    }
}

if ($forsearchbar) {
    $update_result_count_function_call = '';
}

if (!$is_search) {
    $update_result_count_function_call = '';
}
?>

<div class="Fixed">
    <?php if (!(isset($treeonly) && $treeonly)) { ?>
        <div id="<?php echo $status_box_id; ?>" class="CategoryBox">
            <div id="<?php echo $tree_id; ?>_statusbox_begin" style="display:none;"></div>
            <div id="<?php echo $tree_id; ?>_statusbox_platform" style="display:none;"></div>
            <?php echo $status_box_elements; ?>
        </div>
        <div>
            <a
                href="#"
                onclick="
                    if (document.getElementById('<?php echo $tree_id; ?>').style.display!='block') {
                        document.getElementById('<?php echo $tree_id; ?>').style.display='block';
                    } else {
                        document.getElementById('<?php echo $tree_id; ?>').style.display='none';
                    }
                    return false;"
            ><?php echo LINK_CARET . escape($lang['showhidetree']); ?></a>
            &nbsp;
            <a href="#" onclick="clearCategoryTree_<?php echo $tree_id; ?>(); return false;">
                <?php echo LINK_CARET .  escape($lang['clearall']); ?>
            </a>
        </div>
        <script>
            // The nodes in the statusbox list have been rendered in ascending node sequence.
            // The list must be reordered to reflect the preorder traversal sequence of the jstree to make it more readable.
            function reorder_selected_statusbox_<?php echo $tree_id; ?>() {

                var thisJstree = jQuery('#<?php echo $tree_id; ?>');
                var treefieldid = jQuery(thisJstree)[0].id;
                // The preorder sequence will only contain the loaded tree nodes (ie. not necessarily the whole tree).
                var preorderlist = jQuery(thisJstree).find("li");

                // The statusbox platform is the element before which we need to insert selected divs in preorder sequence
                // This method should give us the order we need for improved readability (instead of by node ref)
                let statusboxbegin = jQuery("#"+treefieldid+"_statusbox_begin");
                let statusboxplatform = jQuery("#"+treefieldid+"_statusbox_platform");
                // Re-position the platform
                // Any selected nodes not yet loaded in the tree will remain below the platform and so are effectively "moved" to the end of the list
                statusboxplatform.insertAfter(statusboxbegin);
                
                // Place each node (in preorder sequence) onto the platform
                for (var i = 0;i<preorderlist.length;i++) {
                    // var pentry = jQuery("#tree_"+treefieldid+"_selected_"+preorderlist[i].id);
                    var pentry = jQuery("#"+treefieldid+"_selected_"+preorderlist[i].id);
                    if (pentry) {
                        pentry.insertBefore(statusboxplatform);
                    }
                }
            }

            function clearCategoryTree_<?php echo $tree_id; ?>() {
                if (!confirm('<?php echo escape($lang["clearcategoriesareyousure"]);?>')) {
                    return false;
                }
                var thisJstree = jQuery('#<?php echo $tree_id; ?>');

                // Ensure that the child deselection occurs irrespective of the setting of category_tree_remove_children
                category_tree_clear = true;

                // Establish all root nodes of this tree, open them and deselect them
                var rootNodesJstree = thisJstree.jstree(true).get_node("#").children;
                rootNodesJstree.forEach(function(rootNode) {
                    thisJstree.jstree('open_node', rootNode, function(e, data) {
                        if (thisJstree.jstree('is_selected', e.id)) {
                            thisJstree.jstree('deselect_node', e.id);
                        } else {
                            deselect_children_of_jstree_node(thisJstree, e.id);   
                        }
                    });
                });

                category_tree_clear = false;

                // Remove the hidden inputs
                var elements = document.getElementsByName('<?php echo $name; ?>');
                while (elements[0]) {
                    elements[0].parentNode.removeChild(elements[0]);
                }

                // Clear contents of status box
                var node_statusbox = document.getElementById('<?php echo $status_box_id; ?>');
                while (node_statusbox.lastChild) {
                    node_statusbox.removeChild(node_statusbox.lastChild);
                }

                <?php
                if (!$is_search && $edit_autosave) {
                    echo "AutoSave('{$field['ref']}');";
                }

                echo $update_result_count_function_call;
                ?>
            }
        </script>
        <?php
    }

    echo $hidden_input_elements;
    ?>

    <div id="<?php echo $tree_id; ?>" style="<?php echo $tree_container_styling; ?>"></div>
    <script>
        jQuery('#<?php echo $tree_id; ?>').jstree({
            'core' : {
                'data' : {
                    url  : '<?php echo $baseurl; ?>/pages/ajax/category_tree_lazy_load.php',
                    data : function(node) {
                        return {
                            ajax           : true,
                            node_ref       : node.id,
                            field          : <?php echo $field['ref']; ?>,
                            selected_nodes : <?php echo json_encode(array_column($valid_nodes, "ref")); ?>,
                            k : '<?php echo escape((string)$k); ?>'
                        };
                    }
                },
                'themes' : {
                    'name' : 'default-dark',
                    'icons': false
                },
                'multiple' : true,
            },
            'plugins' : [
                'wholerow',
                'checkbox',
            ],
            'checkbox' : {
                // jsTree Documentation: three_state is a boolean indicating if checkboxes should cascade down and have an 
                // undetermined state. Defaults to true
                'three_state': false,
                // jsTree Documentation: This setting controls how cascading and undetermined nodes are applied.
                // If 'up' is in the string - cascading up is enabled, if 'down' is in the string - cascading down is enabled,
                // if 'undetermined' is in the string - undetermined nodes will be used.
                // If three_state is set to true this setting is automatically set to 'up+down+undetermined'. Defaults to ''.
                // IMPORTANT: we set it to default so we can create our intended behaviour
                'cascade': ''
            }
        });

        /*
        Intended behaviour (jstree does certain things by default that we don't want)
        ----------------------------------------------
        1. Selecting a sub (child) node will automatically select all parent nodes up to and including the root level, unless 
        the option $category_tree_add_parents is set to false
        2. Deselecting a parent node will automatically deselect all child nodes, unless the option $category_tree_remove_children
        is set to false
        3. Deselecting a sub node will not affect any parent nodes
        4. Selection of a parent node will have no effect on any sub nodes
        */
        var jquery_tree_by_id             = jQuery('#<?php echo $tree_id; ?>');
        var category_tree_add_parents     = <?php echo $category_tree_add_parents ? 'true' : 'false'; ?>;
        var category_tree_remove_children = <?php echo $category_tree_remove_children ? 'true' : 'false'; ?>;

        var category_tree_clear = false;

        // When a node is selected, and add parents is enabled, then automatically select all ancestors 
        jquery_tree_by_id.on('select_node.jstree', function (event, data) {
            var thisJstree = jQuery(this);

            if (category_tree_add_parents) {
                // Establish the parent of the selected node
                var parent_node = thisJstree.jstree('get_parent', data.node.id);
                if (parent_node) {      
                    // Trigger selection of the parent node
                    thisJstree.jstree('select_node', parent_node);
                }
            }
        });

        <?php if (!$is_search) { ?>
            // When a node is deselected and remove children is enabled, then automatically deselect the children if present 
            jquery_tree_by_id.on('deselect_node.jstree', function (event, data) {       
                var thisJstree = jQuery(this);

                if (thisJstree.jstree('is_leaf', data.node.id)) { 
                    // console.log("NODE "+data.node.id+" DESELECTED LEAF - NO CHILDREN - NO FURTHER ACTION");
                    return; 
                }
            
                if (category_tree_remove_children || category_tree_clear) {
                    if (thisJstree.jstree('is_parent', data.node.id)) { 
                        // console.log("PARENT NODE "+data.node.id+" DESELECTED - PROCESS ITS CHILDREN");
                        if (thisJstree.jstree('is_closed', data.node.id)) {
                            // console.log("-- PARENT NODE "+data.node.id+" IS CLOSED - OPEN IT");
                            thisJstree.jstree('open_node', data.node.id, function(e, data) {
                                // console.log("-- -- NODE "+e.id+" OPENED CALLBACK ");
                                deselect_children_of_jstree_node(thisJstree, e.id);   
                            });
                        } else {
                            // Parent is already open
                            // console.log("-- PARENT NODE "+data.node.id+" ALREADY OPEN - DESELECT ITS CHILDREN");
                            deselect_children_of_jstree_node(thisJstree, data.node.id);
                        }
                    }
                }
            });
        <?php } ?>              

        // Reflect node selections onto the status box and hidden inputs 
        jquery_tree_by_id.on('select_node.jstree', function (event, data) {
            // Add hidden input which is used to post to the edit or search 
            document.getElementById('<?php echo $tree_id; ?>').insertAdjacentHTML(
                'beforeBegin',
                '<input id="<?php echo $hidden_input_elements_id_prefix; ?>' + data.node.id +
                '" type="hidden" name="<?php echo $name; ?>" class ="<?php echo $tree_id; ?>_nodes" value="' + data.node.id +
                '">');

            document.getElementById('<?php echo $status_box_id; ?>').insertAdjacentHTML(
                'beforeEnd',
                '<div id="<?php echo $tree_id . "_selected_";?>' + data.node.id + '" class="<?php echo $tree_id; ?>_option_status"><span id="<?php echo $status_box_id;?>_option_'
                + data.node.id + '">'
                + data.node.text
                + '</span><br /></div>');
        });

        // Reflect node deselections onto the status box and hidden inputs 
        jquery_tree_by_id.on('deselect_node.jstree', function (event, data) {
            // Remove hidden input so that it is no longer posted to the edit or search 
            jQuery('#<?php echo $hidden_input_elements_id_prefix; ?>'+data.node.id+'.<?php echo "{$tree_id}_nodes"?>').remove();

            // Remove entry from statusbox 
            jQuery('#<?php echo $status_box_id;?>_option_'+data.node.id).parent().remove();
        });

        // Reflect aggregated changes - trigger centralspace events; trigger autosave
        jquery_tree_by_id.on('changed.jstree', function (event, data) {
            if (!(data.action == 'select_node' || data.action == 'deselect_node')) {
                return;
            }

            <?php if (!(isset($treeonly) && $treeonly)) { ?>
                reorder_selected_statusbox_<?php echo $tree_id; ?>();
            <?php } ?>

            var selected_rs_node_ids = data.selected;

            for (var i = 0; i < selected_rs_node_ids.length; i++) {
                // Trigger an event so we can chain actions once we've changed a category tree option
                jQuery('#<?php echo $forsearchbar ? "SearchBox" : "CentralSpace" ?>').trigger('categoryTreeChanged', [{node: selected_rs_node_ids[i]}]);
            }

            if (selected_rs_node_ids.length == 0) {
                // Category tree cleared
                jQuery('#<?php echo $forsearchbar ? "SearchBox" : "CentralSpace" ?>').trigger('categoryTreeChanged', 0);
            }

            <?php if (!$is_search && $edit_autosave) { ?>
                // Give time for all actions e.g. check parents to complete before autosaving
                if (typeof(edit_tree_block_autosave) == 'undefined' || !edit_tree_block_autosave) {
                    edit_tree_block_autosave = true;
                    loadingtimers.push(window.setTimeout("AutoSave('<?php echo escape($field['ref']); ?>');edit_tree_block_autosave = false;",500));
                }
            <?php }
            echo $update_result_count_function_call;
            ?>
        });

        // Reorder associated statusbox list if present
        jquery_tree_by_id.on('after_open.jstree', function (event, data) {
            <?php if (!(isset($treeonly) && $treeonly)) { ?>
                reorder_selected_statusbox_<?php echo $tree_id; ?>();
            <?php } ?>
        });
    </script>
</div>
