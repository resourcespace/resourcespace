<?php
/*
---------- Radio buttons ----------

Inactive nodes should be shown because this type of field can only hold one value so a user changing its value is
allowed to remove a disabled option for another (active) option.
*/

// Selected nodes should be used most of the times.
// When searching, an array of searched_nodes can be found instead
// which represent the same thing (ie. already selected values)
if (!isset($selected_nodes)) {
    $selected_nodes = array();

    if (isset($searched_nodes) && is_array($searched_nodes)) {
        $selected_nodes = $searched_nodes;
    }
}

$node_options = array_column($field['node_options'], 'name');
$l = average_length($node_options);

$cols = 10;
if ($l > 5) {
    $cols = 6;
}

if ($l > 10) {
    $cols = 4;
}

if ($l > 15) {
    $cols = 3;
}

if ($l > 25) {
    $cols = 2;
}

$rows = ceil(count($node_options) / $cols);

// Default behaviour
if (!isset($display_as_radiobuttons)) {
    $display_as_radiobuttons = true;
}

// Display as checkboxes is a feature for advanced search only
if (!isset($display_as_checkbox)) {
    $display_as_checkbox = false;
}

// Display as dropdown is a feature for advanced search only, if set in field options
if (!isset($display_as_dropdown)) {
    $display_as_dropdown = false;
}

// Autoupdate is set only on search forms, otherwise it should be false
if (!isset($autoupdate)) {
    $autoupdate = false;
}


if ($display_as_radiobuttons) {
    $active_nodes = array_column(array_filter($field['node_options'], 'node_is_active'), 'ref');
    $field_ref_escaped = escape($field['ref']);
    ?>

    <table id="" class="radioOptionTable" cellpadding="3" cellspacing="3">                    
        <tbody>
            <tr>
                <?php
                $row = 1;
                $col = 1;

                foreach ($field['node_options'] as $node) {
                    if ($col > $cols) {
                        $col = 1;
                        $row++; ?>
                        </tr>
                        <tr>
                        <?php
                    }
                    $col++;

                    $checked = (
                        (!$multiple ||  $copyfrom != '')
                        && in_array($node['ref'], $selected_nodes) || (isset($user_set_values[$field['ref']])
                        && $node['ref'] == $user_set_values[$field['ref']])
                    );
                    $inactive = !in_array($node['ref'], $active_nodes);

                    if (($multiple && $inactive) || (!$checked && $inactive)) {
                        continue;
                    }
                    ?>
                    <td width="10" valign="middle">
                        <input type="radio"
                            id="field_<?php echo $field_ref_escaped . '_' . sha1($node['name']); ?>"
                            name="<?php echo escape($name); ?>"
                            value="<?php echo (int) $node['ref']; ?>"
                            <?php
                                echo $checked ? ' checked ' : '';
                            if ($edit_autosave) {
                                ?>
                                    onChange="AutoSave('<?php echo $field_ref_escaped; ?>');"
                                    <?php
                            }
                            if ($autoupdate) {
                                ?>
                                    onChange="UpdateResultCount();"
                                <?php
                            }
                                ?>
                            >
                    </td>

                    <td align="left" valign="middle">
                        <label
                            class="customFieldLabel"
                            for="field_<?php echo $field_ref_escaped . '_' . sha1($node['name']); ?>"
                        ><?php echo escape(i18n_get_translated($node['name'])); ?></label>
                    </td>
                    <?php
                }
                ?>
            </tr>
        </tbody>
    </table>
    <?php
} elseif ($display_as_checkbox) {
    // On advanced search, by default, show as checkboxes:
    ?>
    <table cellpadding=2 cellspacing=0>
        <tbody>
            <tr>
                <?php
                $row = 1;
                $col = 1;

                foreach ($field['node_options'] as $node) {
                    if ($col > $cols) {
                        $col = 1;
                        $row++;
                        ?>
                        </tr>
                        <tr>
                        <?php
                    }
                    $col++;
                    ?>

                    <td valign=middle>
                        <input id="nodes_searched_<?php echo $node['ref']; ?>"
                            type="checkbox"
                            name="<?php echo escape($name); ?>"
                            value="<?php echo (int) $node['ref']; ?>"
                            <?php
                            if (in_array($node['ref'], $selected_nodes)) {
                                ?>
                                checked
                                <?php
                            }
                            if ($autoupdate) {
                                ?>
                                onClick="UpdateResultCount();"
                                <?php
                            }
                            ?>
                        >
                    </td>
                    <td valign=middle>
                        <?php echo escape(i18n_get_translated($node['name'])); ?>&nbsp;&nbsp;
                    </td>
                    <?php
                }
                ?>
            </tr>
        </tbody>
    </table>
    <?php
} elseif ($display_as_dropdown) {
    // On advanced search, display it as a dropdown, if set like this:
    ?>
    <select
        class="<?php echo $class; ?>"
        name="<?php echo escape($name); ?>"
        <?php echo ($autoupdate) ? 'onChange="UpdateResultCount();"' : ''; ?>>
        <option value=""></option>
        <?php
        foreach ($field['node_options'] as $node) {
            if ('' != trim($node['name'])) {
                ?>
                <option
                    value="<?php echo escape(trim($node['ref'])); ?>"
                    <?php echo (in_array($node['ref'], $selected_nodes)) ? " selected" : ''; ?>>
                    <?php echo escape(trim(i18n_get_translated($node['name']))); ?>
                </option>
                <?php
            }
        }
        ?>
    </select>
    <?php
}
