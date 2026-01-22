<?php

/* -------- Check box list ------------------ */
if (!hook('customchkboxes', '', array($field))) {
    global $checkbox_ordered_vertically;

    // Selected nodes should be used most of the times.
    // When searching, an array of searched_nodes can be found instead
    // which represent the same thing (ie. already selected values)
    if (!isset($selected_nodes)) {
        $selected_nodes = array();

        if (isset($searched_nodes) && is_array($searched_nodes)) {
            $selected_nodes = $searched_nodes;
        }
    }

    $field['node_options'] = array_filter($field['node_options'], 'node_is_active');

    // Work out an appropriate number of columns based on the average length of the options.
    $l = average_length(array_column($field['node_options'], 'name'));
    switch ($l) {
        case $l > 25:
            $cols = 1;
            break;
        case $l > 15:
            $cols = 2;
            break;   # 50
        case $l > 9:
            $cols = 3;
            break;   # 45
        case $l > 6:
            $cols = 4;
            break;   # 36
        case $l > 4:
            $cols = 5;
            break;   # 30
        case $l > 2:
            $cols = 7;
            break;   # 28
        default:
            $cols = 8;
    }

    if ((bool) $field['automatic_nodes_ordering']) {
        $field['node_options'] = reorder_nodes($field['node_options']);
    }

    $new_node_order    = array();
    $order_by_resetter = 0;
    foreach ($field['node_options'] as $node_index => $node) {
        // Special case for vertically ordered checkboxes.
        // Order by needs to be reset as per the new order so that we can reshuffle them using the order by as a reference
        if ($checkbox_ordered_vertically) {
            $field['node_options'][$node_index]['order_by'] = $order_by_resetter++;
        }
    }

    $wrap = 0;
    $rows = ceil(count($field['node_options']) / $cols);

    if ($checkbox_ordered_vertically) {
        # ---------------- Vertical Ordering -----------
        ?>
        <fieldset class="customFieldset" name="<?php echo $field['title']; ?>">
            <legend class="accessibility-hidden"><?php echo $field['title']; ?></legend>
            <table cellpadding="5" cellspacing="0">
                <tr>
                    <?php
                    for ($i = 0; $i < $rows; $i++) {
                        for ($j = 0; $j < $cols; $j++) {
                            $order_by = ($rows * $j) + $i;

                            $node_index_to_be_reshuffled = array_search($order_by, array_column($field['node_options'], 'order_by', 'ref'));

                            if (false === $node_index_to_be_reshuffled) {
                                continue;
                            }

                            $node = $field['node_options'][$node_index_to_be_reshuffled];
                            ?>
                            <td>
                                <input
                                    type="checkbox"
                                    id="nodes_<?php echo $node['ref']; ?>"
                                    class="nodes_input_checkbox" 
                                    name="<?php echo $name; ?>"
                                    value="<?php echo $node['ref']; ?>"
                                    <?php
                                    // When editing multiple resources, we don't want to check any options;
                                    // Unless copying from another resource the user must make the necessary selections
                                    if (
                                        (!$multiple || getval("copyfrom", "") != "")
                                        && in_array($node['ref'], $selected_nodes) || (isset($user_set_values[$field['ref']])
                                        && in_array($node['ref'], $user_set_values[$field['ref']]))
                                    ) {
                                        ?>
                                        checked
                                        <?php
                                    }

                                    if ($edit_autosave) {
                                        ?>
                                        onChange="AutoSave('<?php echo $field['ref']; ?>');"
                                        <?php
                                    }
                                    ?>
                                >
                                <label class="customFieldLabel" for="nodes_<?php echo $node['ref']; ?>">
                                    <?php echo escape(i18n_get_translated($node['name'])); ?>
                                </label>
                            </td>
                            <?php
                        }
                        ?>
                        </tr>
                        <tr>
                        <?php
                    }
                    ?>
            </table>
        </fieldset>
        <?php
    } else {
        # ---------------- Horizontal Ordering ---------------------
        ?>
        <fieldset class="customFieldset" name="<?php echo $field['title']; ?>">
            <legend class="accessibility-hidden"><?php echo $field['title']; ?></legend>
            <table cellpadding="3" cellspacing="0">
                <tr>
                    <?php
                    foreach ($field['node_options'] as $node) {
                        $wrap++;
                        if ($wrap > $cols) {
                            $wrap = 1;
                            ?>
                            </tr>
                            <tr>
                            <?php
                        }
                        ?>
                        <td>
                            <input
                                type="checkbox"
                                name="<?php echo $name; ?>"
                                class="nodes_input_checkbox"
                                value="<?php echo $node['ref']; ?>"
                                id="nodes_<?php echo $node['ref']; ?>"
                                <?php
                                // When editing multiple resources, we don't want to check any options; the user must make the necessary selections
                                if (
                                    !$multiple
                                    && in_array($node['ref'], $selected_nodes) || (isset($user_set_values[$field['ref']])
                                    && in_array($node['ref'], $user_set_values[$field['ref']]))
                                ) {
                                    ?>
                                    checked
                                    <?php
                                }

                                if ($edit_autosave) {
                                    ?>
                                    onChange="AutoSave('<?php echo $field['ref']; ?>');"
                                    <?php
                                }
                                ?>
                            >
                            <label class="customFieldLabel" for="nodes_<?php echo $node['ref']; ?>">
                                <?php echo escape(i18n_get_translated($node['name'])); ?>
                            </label>
                        </td>
                        <?php
                    }
                    ?>
                </tr>
            </table>
        </fieldset>
        <?php
    }

    if ($field['field_constraint']) {
        ?>
        <script>
            if (pagename != 'search_advanced') {
                jQuery(document).ready(function() {
                    let fieldset = jQuery('fieldset[name="<?php echo htmlentities($field['title'])?>"]');
                    let checked = fieldset.find('input:checked');
                    
                    if (checked.length > 0) {
                        fieldset.find('input:not(:checked)').prop('disabled', true);
                    }

                    fieldset.find('input').on('click', function() {
                        let parenttable = jQuery(this).parents('tbody');

                        if (parenttable.find('input:checked').length > 0) {
                            parenttable.find('input:not(:checked)').each(function() {
                                jQuery(this).prop('disabled', true);    
                            })
                        } else {
                            fieldset.find('input').prop('disabled',false);
                        }
                    });
                });
            }
        </script>        
        <?php
    }
}
