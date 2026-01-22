<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php';
include '../../../include/ajax_functions.php';
if(!checkperm('a'))
    {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
    }

$museumplus_modules_config = plugin_decode_complex_configs($museumplus_modules_saved_config);

$plugin_name = 'museumplus';
$plugin_yaml = get_plugin_yaml($plugin_name, false);
$breadcrumbs = array(
    array(
        'title' => $lang["systemsetup"],
        'href'  => "{$baseurl_short}pages/admin/admin_home.php"
    ),
    array(
        'title' => $lang["pluginmanager"],
        'href'  => "{$baseurl_short}pages/team/team_plugins.php"
    ),
    array(
        'title' => $lang['museumplus_configuration'],
        'href'  => $baseurl . '/' . $plugin_yaml['config_url']
    ),
    array(
        'title' => $lang['museumplus_module_setup'],
    ),
);

$id = getval('id', 0, true);
$action = getval('action', '');

$module_name = getval('module_name', '');
$mplus_id_field = getval('mplus_id_field', '');
$rs_uid_field = getval('rs_uid_field', 0, true);
$applicable_resource_types = getval('applicable_resource_types', [], false, 'is_array');
$field_mappings = getval('field_mappings', [], false, 'is_array');


if(getval('save', '') !== '' && enforcePostRequest(false))
    {
    if($id == 0)
        {
        $new_id = 1;
        do
            {
            ++$new_id;
            }
        while(isset($museumplus_modules_config[$new_id]));

        $id = $new_id;
        }

    $field_mappings = array_filter($field_mappings, function($v) { return $v['field_name'] != '' && $v['rs_field'] > 0; });

    $museumplus_modules_config[$id] = array(
        'module_name'   => $module_name,
        'mplus_id_field' => $mplus_id_field,
        'rs_uid_field'  => $rs_uid_field,
        'applicable_resource_types' => $applicable_resource_types,
        'field_mappings' => $field_mappings,
    );

    mplus_save_module_config($museumplus_modules_config);
    }
elseif($action == 'delete' && $id > 0 && enforcePostRequest(false))
    {
    if(!isset($museumplus_modules_config[$id]))
        {
        $fail_msg = array_merge(
            ajax_build_message($lang['museumplus_error_not_deleted_module_conf']),
            array('title' => str_replace("'?'", "{$lang['museumplus_module']} #{$id}", $lang["softwarenotfound"]))
        );

        ajax_send_response(400, ajax_response_fail($fail_msg));
        }

    unset($museumplus_modules_config[$id]);
    mplus_save_module_config($museumplus_modules_config);
    ajax_send_response(200, ajax_response_ok_no_data());
    }

if($id > 0 && isset($museumplus_modules_config[$id]))
    {
    $record = $museumplus_modules_config[$id];

    $module_name = $record['module_name'];
    $mplus_id_field = $record['mplus_id_field'];
    $rs_uid_field = $record['rs_uid_field'];
    $applicable_resource_types = $record['applicable_resource_types'];
    $field_mappings = $record['field_mappings'];
    }

$form_action = generateURL(
    "{$baseurl}/plugins/{$plugin_name}/pages/setup_module.php",
    array(
        'id' => $id,
    )
);
$rtfs = ps_query('SELECT * FROM resource_type_field ORDER BY title, name', [],'schema');

include '../../../include/header.php';
?>
<div class="BasicsBox">
<h1><?php echo escape($lang['museumplus_module_setup']); ?></h1>
<?php
renderBreadcrumbs($breadcrumbs);
if(isset($error))
    {
    render_top_page_error_style($error);
    }
?>
    <form id="MplusModuleConfigForm" method="post" action="<?php echo $form_action; ?>">
    <?php generateFormToken("MplusModuleConfigForm"); ?>
    <div class="Question">
        <label><?php echo escape($lang["museumplus_module_name"]); ?></label>
        <input name="module_name" type="text" class="stdwidth" value="<?php echo escape($module_name); ?>">
        <div class="clearerleft"></div>
    </div>
    <div class="Question">
        <label><?php echo escape($lang["museumplus_mplus_id_field"]); ?></label>
        <input name="mplus_id_field" type="text" class="stdwidth" value="<?php echo escape($mplus_id_field); ?>">
        <?php render_question_form_helper($lang['museumplus_mplus_id_field_helptxt'], 'mplus_id_field', array()); ?>
        <div class="clearerleft"></div>
    </div>
    <?php
    render_field_selector_question(
        $lang["museumplus_mpid_field"],
        "rs_uid_field",
        array(FIELD_TYPE_TEXT_BOX_SINGLE_LINE),
        "stdwidth",
        false,
        $rs_uid_field);
    config_multi_rtype_select(
        "applicable_resource_types",
        $lang["museumplus_applicable_resource_types"],
        $applicable_resource_types,
        420);
    ?>
        <div class="Question">
            <label for="buttons"><?php echo escape($lang["museumplus_field_mappings"]); ?></label>
            <table id="MplusModuleFieldsMappingTable">
                <tbody>
                    <tr>
                        <th><strong><?php echo escape($lang["museumplus_mplus_field_name"]); ?></strong></th>
                        <th><strong><?php echo escape($lang["museumplus_rs_field"]); ?></strong></th>
                        <th><strong><!-- actions --></strong></th>
                    </tr>
                <?php
                foreach($field_mappings as $mapping_index => $mapping)
                    {
                    ?>
                    <tr>
                        <td>
                            <input class="medwidth"
                                   type="text"
                                   name="field_mappings[<?php echo $mapping_index; ?>][field_name]"
                                   value="<?php echo escape($mapping['field_name']); ?>">
                        </td>
                        <td>
                            <select class="medwidth" name="field_mappings[<?php echo $mapping_index; ?>][rs_field]">
                                <option value=""><?php echo escape($lang['select']); ?></option>
                            <?php
                            foreach($rtfs as $rtf)
                                {
                                $selected = ($mapping['rs_field'] == $rtf['ref'] ? ' selected' : '');
                                $option_text = escape(lang_or_i18n_get_translated($rtf['title'], 'fieldtitle-'));
                                ?>
                                <option value="<?php echo $rtf['ref']; ?>" <?php echo $selected; ?>><?php echo $option_text; ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" onclick="museumplus_delete_field_mapping(this);"><?php echo escape($lang['action-delete']); ?></button>
                        </td>
                    </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="4">
                            <button type="button" onclick="museumplus_add_new_field_mapping(this);"><?php echo escape($lang['museumplus_add_mapping']); ?></button>
                        </td>
                    </tr>
                </tbody>
            </table><!-- end of MplusModuleFieldsMappingTable -->
            <div class="clearerleft"></div>
        </div>
        <div class="QuestionSubmit">
            <input type="submit" name="save" value="<?php echo escape($lang["save"]); ?>">
        </div>
    </form>  <!-- end of MplusModuleConfigForm -->
</div> <!-- end of BasicBox -->
<script>
function museumplus_add_new_field_mapping(element)
    {
    var button = jQuery(element);
    var row_index = document.getElementById('MplusModuleFieldsMappingTable').rows.length - 2;
    var new_row_html = '';

    new_row_html += '<tr>';
    new_row_html += '<td><input class="medwidth" type="text" name="field_mappings[' + row_index + '][field_name]" value=""></td>';
    new_row_html += '<td><select class="medwidth" name="field_mappings[' + row_index + '][rs_field]">';
    new_row_html += '<option value=""><?php echo escape($lang['select']); ?></option>';
    <?php
    foreach($rtfs as $rtf)
        {
        $option_text = escape(lang_or_i18n_get_translated($rtf['title'], 'fieldtitle-'));
        ?>
        new_row_html += '<option value="<?php echo $rtf['ref']; ?>"><?php echo $option_text; ?></option>';
        <?php
        }
    ?>
    new_row_html += '</select></td>';
    new_row_html += '<td><button type="button" onclick="museumplus_delete_field_mapping(this);"><?php echo escape($lang['action-delete']); ?></button></td>';
    new_row_html += '</tr>';
    jQuery(new_row_html).insertBefore(jQuery(button).closest('tr'));

    museumplus_reindex_table();
    }

function museumplus_delete_field_mapping(element)
    {
    var button = jQuery(element);
    var record = jQuery(button).closest('tr');
    record.remove();
    museumplus_reindex_table();
    }

// Re-index the 'name' attribute when adding/deleting mapping records
function museumplus_reindex_table()
    {
    jQuery('#MplusModuleFieldsMappingTable tr').not(':first').not(':last').each(function(i) 
        {
        var field_name = "field_mappings[" + i + "][field_name]";
        var rs_field = "field_mappings[" + i + "][rs_field]";

        // Change name of each input/select to use the correct index
        jQuery(this).find('td').eq(0).find('input').attr('name', field_name);
        jQuery(this).find('td').eq(1).find('select').attr('name', rs_field);
        });
    }
</script><!-- end of museumplus setup_module script -->
<?php
include '../../../include/footer.php';