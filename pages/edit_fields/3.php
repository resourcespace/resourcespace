<?php
/*
---------- Drop down list ----------

Inactive nodes should be shown because this type of field can only hold one value so a user changing its value is
allowed to remove a disabled option for another (active) option.
*/

$active_nodes = array_column(array_filter($field['node_options'], 'node_is_active'), 'ref');

// Selected nodes should be used most of the times.
// When searching, an array of searched_nodes can be found instead
// which represent the same thing (ie. already selected values)
if (!isset($selected_nodes)) {
    $selected_nodes = array();
}

if ((bool) $field['automatic_nodes_ordering']) {
    $field['node_options'] = reorder_nodes($field['node_options']);
}
?>

<select
    class="stdwidth"
    name="<?php echo $name; ?>"
    id="<?php echo $name; ?>"
    <?php
    if ($edit_autosave) {
        ?>
        onChange="AutoSave('<?php echo $field['ref']; ?>');"
        <?php
    }
    ?>
    >
    <?php
    global $pagename;
    if (!hook('replacedropdowndefault', '', array($field))) {
        ?>
        <option value=""></option>
        <?php
    }

    foreach ($field['node_options'] as $node) {
        if ('' != trim($node['name'])) {
            $selected = (
                // When editing multiple resources, we don't want to preselect any options; the user must make the necessary selection
                (!$multiple || $copyfrom != '')
                && in_array($node['ref'], $selected_nodes) || (isset($user_set_values[$field['ref']])
                && $node['ref'] == $user_set_values[$field['ref']])
            );
            $inactive = !in_array($node['ref'], $active_nodes);

            if (($multiple && $inactive) || (!$selected && $inactive)) {
                continue;
            }
            ?>
            <option value="<?php echo escape(trim($node['ref'])); ?>"<?php
            echo $selected ? ' selected' : ''; ?>><?php echo escape(trim(i18n_get_translated($node['name']))); ?></option>
            <?php
        }
    }
    ?>
</select>
