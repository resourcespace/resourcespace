<?php
# Setup page for autoassign_mrequests plugin

# Do the include and authorization checking ritual.
include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

# Specify the name of this plugin, the heading to display for the page.
$plugin_name = 'autoassign_mrequests';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}
$page_heading = "Auto-assign Managed Requests Configuration";

// Add map
if (getval('add_new', '') !== '' && enforcePostRequest(false)) {
    $user_group_new  = getval('user_group_new', '');
    $field_new       = getval('field_new', '');
    $field_value_new = getval('field_value_new', '');
    $user_new        = getval('user_new', '');

    if ($user_group_new != '' && $field_new != '' && $field_value_new != '' && $user_new != '') {
        $query = "INSERT INTO assign_request_map (user_id, user_group_id, field_id, field_value)
                 VALUES (?, ?, ?, ?)";
        $parameters = array("i",$user_new, "i",$user_group_new, "i",$field_new, "s",$field_value_new);
        ps_query($query, $parameters);
    }
}

// Get information needed for saving and deleting
$id_row          = getval('id_row', '');
$user_group_row  = getval('user_group_row', '');
$field_row       = getval('field_row', '');
$field_value_row = getval('field_value_row', '');
$user_id_row     = getval('user_id_row', '');

// Save map
if (getval('save', '') !== '' && enforcePostRequest(false)) {
    $save_query = "UPDATE assign_request_map
                    SET user_id = ?, user_group_id = ?, field_id = ?, field_value = ?
                    WHERE id = ?";
    $parameters = array("i",$user_id_row, "i",$user_group_row, "i",$field_row, "s",$field_value_row, "i",$id_row);
    ps_query($save_query, $parameters);
}

// Delete map
if (getval('delete', '') !== '' && enforcePostRequest(false)) {
    $delete_query = "DELETE FROM assign_request_map WHERE id = ?";
    ps_query($delete_query, array("i",$id_row));
}

include '../../../include/header.php';

// Get information to populate options later on
$user_groups = get_usergroups();
$ordered_groups = [];
foreach ($user_groups as $group) {
        $ordered_groups[$group['ref']] = $group;
}

$user_groups = array_filter($ordered_groups, function ($group) use ($ordered_groups) {
    if (
        in_array('R', explode(',', $group['permissions']))
        || in_array('R', explode(',', $ordered_groups[$group['parent']]['permissions'] ?? ''))
    ) {
        return true;
    }
});
$fields = ps_query("SELECT ref, title 
                    FROM resource_type_field 
                    ORDER BY title, name;", array(), "schema");
$users = get_users(implode(',', array_column($user_groups, 'ref')));

// Get maps
$rows = ps_query("SELECT id, user_id, user_group_id, field_id, field_value 
                    FROM assign_request_map;", array());

?>

<div class="BasicsBox">
    <h1>Auto-assign Managed Requests Configuration</h1>
    <div class="Question">
        <h3>Mapping rules</h3>
        <table id="mappings">
            <tbody>
                <tr>
                    <th><strong>User group</strong></th>
                    <th><strong>User</strong></th>
                    <th><strong>Field</strong></th>
                    <th><strong>Value</strong></th>
                    <th></th>
                </tr>
                <!-- Foreach rows -->
                <?php foreach ($rows as $row) { ?>
                    <form id="form<?php echo $row['id']; ?>" name="form<?php echo $row['id']; ?>" method="post" action="<?php echo $baseurl; ?>/plugins/autoassign_mrequests/pages/setup.php">
                        <?php generateFormToken("form{$row['id']}"); ?>
                        <input type="hidden" name="id_row" value="<?php echo $row['id']; ?>" />
                        <tr id="row<?php echo $row['id']; ?>">
                            <td>
                                <select name="user_group_row" for="form<?php echo $row['id']; ?>" id="user_group_row" style="width:300px">
                                    <?php
                                    foreach ($user_groups as $user_group) {
                                        $selected = false;
                                        if ($row['user_group_id'] == $user_group['ref']) {
                                            $selected = true;
                                        }
                                        ?>
                                        <option value="<?php echo $user_group['ref']; ?>"<?php if ($selected) { ?> selected=""<?php } ?>>
                                            <?php echo $user_group['name']; ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>
                                <select name="user_id_row" for="form<?php echo $row['id']; ?>" id="user_id_row" style="width:300px">
                                    <option value="" selected=""></option>
                                    <?php
                                    foreach ($users as $user) {
                                        $selected = false;
                                        if ($row['user_id'] == $user['ref']) {
                                            $selected = true;
                                        }
                                        ?>
                                        <option value="<?php echo $user['ref']; ?>"<?php if ($selected) { ?> selected=""<?php } ?>>
                                            <?php echo $user['fullname'] . ' (' . $user['email'] . ')'; ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>
                                <select name="field_row" for="form<?php echo $row['id']; ?>" id="field_row" style="width:300px">
                                    <?php
                                    foreach ($fields as $field) {
                                        // Skip this field if current user cannot see it
                                        if (checkperm('f-' . $field['ref'])) {
                                            continue;
                                        }

                                        $selected = false;
                                        if ($row['field_id'] == $field['ref']) {
                                            $selected = true;
                                        }
                                        ?>
                                        <option value="<?php echo escape($field['ref']); ?>"<?php if ($selected) { ?> selected=""<?php } ?>>
                                            <?php echo escape(i18n_get_translated($field['title'])); ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><input type="text" for="form<?php echo $row['id']; ?>" name="field_value_row" value="<?php echo $row['field_value']; ?>"></td>
                            <td>
                                <input type="submit" name="save" id="save" value="Save" />
                                <input type="submit" name="delete" id="delete" value="Delete map" />
                            </td>
                        </tr>
                    </form>
                    <?php
                }
                ?>
                <!-- end of foreach rows -->

                <!-- Add new map row -->
                <form id="form1" name="form1" method="post" action="<?php echo $baseurl; ?>/plugins/autoassign_mrequests/pages/setup.php">
                    <?php generateFormToken("form1"); ?>
                    <tr id="new_map_row">
                        <td>
                            <select name="user_group_new" id="resource_type_new" style="width:300px" onChange="filterUsers(this);">
                                <option value="" selected=""></option>
                                <?php foreach ($user_groups as $user) { ?>
                                    <option value="<?php echo $user['ref']; ?>"><?php echo $user['name']; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <select name="user_new" id="user_new" style="width:300px">
                                <option value="" selected=""></option>
                                <?php foreach ($users as $user) { ?>
                                    <option value="<?php echo $user['ref']; ?>"><?php echo $user['fullname'] . ' (' . $user['email'] . ')'; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <select name="field_new" id="field_new" style="width:300px">
                                <option value="" selected=""></option>
                                <?php
                                foreach ($fields as $field) {
                                    // Skip this field if current user cannot see it
                                    if (checkperm('f-' . $field['ref'])) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo $field['ref']; ?>"><?php echo $field['title']; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </td>
                        <td><input type="text" name="field_value_new" value=""></td>
                        <td><input type="submit" name="add_new" id="add_new" value="Add map" /></td>
                    </tr>
                </form>
                <!-- end of add new map row -->
            </tbody>
        </table>
    </div>
    <div class="clearerleft"></div>
</div>

<script type="text/javascript">
function filterUsers(select_element)
{
    var selected_option = select_element.options[select_element.selectedIndex];

    console.log('Filtering users that are part of user group "' + selected_option.text + '"');

    jQuery.ajax({
        type: 'POST',
        url: '<?php echo $baseurl_short; ?>plugins/autoassign_mrequests/ajax/filters.php',
        data: {
            ajax: 'true',
            user_group_id: selected_option.value,
            <?php echo generateAjaxToken('filterUsers'); ?>
        },
        dataType: "json",
        success: function(data) {
            if(!jQuery.isArray(data) || !data.length ) {
                alert('There are no users assigned to the selected user group. Please select another user group.')
                return false;
            }

            // Remove all options for the users column and add only those that passed the filter
            jQuery('#user_new').empty();
            for(var key in data) {
                var option = '';
                
                if(data.hasOwnProperty(key)) {
                    var obj = data[key];

                    for(var prop in obj) {
                        if(obj.hasOwnProperty(prop)) {
                            switch(prop) {
                                case 'ref':
                                    option += '<option value="' + obj[prop] + '">';
                                    break;
                                case 'fullname':
                                    option += obj[prop];
                                    break;
                                case 'email':
                                    option += ' (' + obj[prop] + ')</option>';
                                    break;
                            }
                        }
                    }
                }

                jQuery('#user_new').append(option);
            }
        }
    });
}
</script>

<?php
include '../../../include/footer.php';
?>
