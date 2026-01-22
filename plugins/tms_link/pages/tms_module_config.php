<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('a')) {
    http_response_code(401);
    exit($lang["error-permissiondenied"]);
}

// Get available tables
$conn = odbc_connect($tms_link_dsn_name, $tms_link_user, $tms_link_password);
$arrtables = [];
$alltables = odbc_tables($conn);
while (odbc_fetch_row($alltables)) {
    $type = odbc_result($alltables, 4);
    if ($type == "TABLE" || $type == "VIEW") {
        // Add "table schema" prefix to all table names
        $schema = odbc_result($alltables, 2);
        $tablename = ((!empty($schema)) ? $schema . '.' : '') . odbc_result($alltables, 3);
        $arrtables[$tablename] = $tablename;
    }
}

$id = getval('id', '');
$action = getval('action', '');

$tms_link_modules_mappings = unserialize(base64_decode($tms_link_modules_saved_mappings));
$tms_link_module_name = getval('tms_link_module_name', '');
$tms_link_tms_uid_field = getval('tms_link_tms_uid_field', '');
$tms_link_tms_uid_field_int = getval('tms_link_tms_uid_field_int', true, false, static fn($val) => !$val);
$tms_link_rs_uid_field = getval('tms_link_rs_uid_field', 0, true);
$tms_link_checksum_field = getval('tms_link_checksum_field', 0, true);
$tms_link_applicable_resource_types = getval('tms_link_applicable_resource_types', [], false, 'is_array');
$tms_link_tms_rs_mappings = getval('tms_rs_mappings', [], false, 'is_array');

if (getval('save', '') !== '' && enforcePostRequest(false)) {
    if ($id === '') {
        do {
            $new_id = uniqid();
        } while (array_key_exists($new_id, $tms_link_modules_mappings));

        $id = $new_id;
    }

    if ($tms_link_module_name == escape($lang["select"])) {
        $tms_link_module_name = $tms_link_modules_mappings[$id]['module_name'];
    }

    $tms_link_modules_mappings[$id] = array(
        'module_name'   => $tms_link_module_name,
        'tms_uid_field' => $tms_link_tms_uid_field,
        'tms_uid_field_int' => $tms_link_tms_uid_field_int,
        'rs_uid_field'  => $tms_link_rs_uid_field,
        'checksum_field'  => $tms_link_checksum_field,
        'applicable_resource_types' => $tms_link_applicable_resource_types,
        'tms_rs_mappings' => $tms_link_tms_rs_mappings,
    );

    tms_link_save_module_mappings_config($tms_link_modules_mappings);
}

if ($action == 'delete' && $id !== '' && enforcePostRequest(false)) {
    if (!array_key_exists($id, $tms_link_modules_mappings)) {
        odbc_close($conn);
        http_response_code(400);
        exit();
    }

    unset($tms_link_modules_mappings[$id]);
    tms_link_save_module_mappings_config($tms_link_modules_mappings);
    odbc_close($conn);
    exit();
}

if ($id !== '' && array_key_exists($id, $tms_link_modules_mappings)) {
    $record = $tms_link_modules_mappings[$id];

    $tms_link_module_name = $record['module_name'];
    $tms_link_tms_uid_field = $record['tms_uid_field'];
    $tms_link_tms_uid_field_int = $record['tms_uid_field_int'] ?? true;
    $tms_link_rs_uid_field = $record['rs_uid_field'];
    $tms_link_checksum_field = $record['checksum_field'];
    $tms_link_applicable_resource_types = $record['applicable_resource_types'];
    $tms_link_tms_rs_mappings = $record['tms_rs_mappings'];
}

$current_module_missing = false;
if ($tms_link_module_name != '' && !in_array($tms_link_module_name, $arrtables)) {
    $current_module_missing = true;
    # Show "Select..." in drop down rather than displaying the first result.
    $arrtables = array_merge(array($lang["select"] => $lang["select"]), $arrtables);
}


// Generate back to setup page of tms plugin link
$plugin_yaml = get_plugin_yaml('tms_link', false);
$back_to_url = $baseurl . '/' . $plugin_yaml['config_url'];
$back_to_link_name = LINK_CARET_BACK . str_replace(
    '%area',
    escape($lang['tms_link_configuration']),
    escape($lang["back_to"])
);

include '../../../include/header.php';
?>
<div class="BasicsBox">
    <p>
        <a
            href="<?php echo $back_to_url; ?>"
            onclick="return CentralSpaceLoad(this, true);"
            ><?php echo $back_to_link_name; ?>
        </a>
    </p>
    <h1><?php echo escape($lang["tms_link_tms_module_configuration"]); ?></h1>
    <?php
    if (isset($error)) {
        echo "<div class=\"PageInformal\">{" . escape($error) . "}</div>";
    }

    $form_action = generateURL(
        "{$baseurl}/plugins/tms_link/pages/tms_module_config.php",
        array(
            'id' => $id,
        )
    );
    ?>
    <form id="TmsModuleConfigForm" method="post" action="<?php echo $form_action; ?>">
        <?php
        generateFormToken("tms_module_config");

        if ($current_module_missing) {
            ?>
            <div class="Question">
            <p><span style="color:red;"><?php echo escape($lang["status-warning"]) . ':  '; ?></span><?php echo(escape(str_replace('%%MODULE%%', $tms_link_module_name, $lang['tms_link_selected_module_missing'])));?></p>
            <div class="clearerleft"></div>
            </div>
            <?php
            $tms_link_module_name = $lang["select"];
        }

        render_dropdown_question(
            $lang["tms_link_tms_module_name"],
            "tms_link_module_name",
            $arrtables,
            $tms_link_module_name
        ); ?>

        <div class="Question">
            <label><?php echo escape($lang["tms_link_tms_uid_field"]); ?></label>
            <input
                name="tms_link_tms_uid_field"
                type="text"
                class="stdwidth"
                value="<?php echo escape($tms_link_tms_uid_field); ?>"
                >
            <div class="clearerleft"></div>
        </div>
        <?php
        config_boolean_select(
            "tms_link_tms_uid_field_int",
            $lang["tms_link_uid_field_int"],
            $tms_link_tms_uid_field_int,
        );
        render_field_selector_question(
            $lang["tms_link_rs_uid_field"],
            "tms_link_rs_uid_field",
            array(),
            "stdwidth",
            false,
            $tms_link_rs_uid_field
        );
        render_field_selector_question(
            $lang["tms_link_checksum_field"],
            "tms_link_checksum_field",
            array(),
            "stdwidth",
            false,
            $tms_link_checksum_field
        );
        config_multi_rtype_select(
            "tms_link_applicable_resource_types",
            $lang["tms_link_applicable_rt"],
            $tms_link_applicable_resource_types,
            420
        );
        ?>
        <div class="Question">
            <label for="buttons"><?php echo escape($lang["tms_link_field_mappings"]); ?></label>
            <table id="tmsModulesMappingTable">
                <tbody>
                    <tr>
                        <th><strong><?php echo escape($lang["tms_link_column_name"]); ?></strong></th>
                        <th><strong><?php echo escape($lang["tms_link_resourcespace_field"]); ?></strong></th>
                        <th><strong
                            ><?php echo escape("{$lang["tms_link_column_name"]} {$lang["tms_link_encoding"]}"); ?>
                        </strong></th>
                        <th><strong></strong></th>
                    </tr>
                <?php
                foreach ($tms_link_tms_rs_mappings as $tms_rs_mapping_index => $tms_rs_mapping) {
                    ?>
                    <tr>
                        <td>
                            <input class="medwidth" 
                                   type="text"
                                   name="tms_rs_mappings[<?php echo (int) $tms_rs_mapping_index; ?>][tms_column]"
                                   value="<?php echo escape($tms_rs_mapping['tms_column']); ?>">
                        </td>
                        <td>
                            <select
                                class="medwidth"
                                name="tms_rs_mappings[<?php echo (int) $tms_rs_mapping_index; ?>][rs_field]"
                                >
                                <option value=""><?php echo escape($lang['select']); ?></option>
                        <?php
                        $fields = ps_query('SELECT * FROM resource_type_field ORDER BY title, name', array(), "schema");
                        foreach ($fields as $field) {
                            $selected = ($tms_rs_mapping['rs_field'] == $field['ref'] ? ' selected' : '');
                            $option_text = lang_or_i18n_get_translated($field['title'], 'fieldtitle-');
                            ?>
                            <option
                                value="<?php echo (int) $field['ref']; ?>"
                                <?php echo $selected; ?>
                                ><?php echo escape($option_text); ?>
                            </option>
                            <?php
                        }
                        ?>
                            </select>
                        </td>
                        <td>
                            <input
                                class="srtwidth"
                                type="text"
                                name="tms_rs_mappings[<?php echo (int) $tms_rs_mapping_index; ?>][encoding]"
                                value="<?php echo escape($tms_rs_mapping['encoding']); ?>">
                        </td>
                        <td>
                            <button
                                type="button"
                                onclick="delete_tms_field_mapping(this);"
                                ><?php echo escape($lang['action-delete']); ?>
                            </button>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                    <tr>
                        <td colspan="4">
                            <button
                                type="button"
                                onclick="add_new_tms_field_mapping(this);"
                                ><?php echo escape($lang['tms_link_add_mapping']); ?>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <script>
            function add_new_tms_field_mapping(element)
                {
                var button = jQuery(element);
                var row_index = document.getElementById('tmsModulesMappingTable').rows.length - 2;
                var new_row_html = '';

                new_row_html += '<tr>';
                new_row_html += '<td><input';
                new_row_html += '    class="medwidth"';
                new_row_html += '    type="text"';
                new_row_html += '    name="tms_rs_mappings[' + row_index + '][tms_column]"';
                new_row_html += '    value="">';
                new_row_html += '</td>';
                new_row_html += '<td><select class="medwidth" name="tms_rs_mappings[' + row_index + '][rs_field]">';
                new_row_html += '<option value=""><?php echo escape($lang['select']); ?></option>';
                <?php
                $fields = ps_query('SELECT * FROM resource_type_field ORDER BY title, name', array(), "schema");
                foreach ($fields as $field) {
                    $option_text = lang_or_i18n_get_translated($field['title'], 'fieldtitle-');
                    ?>
                    new_row_html += '<option';
                    new_row_html += '    value="<?php echo (int) $field['ref']; ?>"';
                    new_row_html += '><?php echo escape($option_text); ?>';
                    new_row_html += '</option>';
                    <?php
                }
                ?>
                new_row_html += '</select>';
                new_row_html += '</td>';
                new_row_html += '<td><input';
                new_row_html += '    class="srtwidth"';
                new_row_html += '    type="text"';
                new_row_html += '    name="tms_rs_mappings[' + row_index + '][encoding]" value="">';
                new_row_html += '</td>';
                new_row_html += '<td><button';
                new_row_html += '    type="button"';
                new_row_html += '    onclick="delete_tms_field_mapping(this);"';
                new_row_html += '    ><?php echo escape($lang['action-delete']); ?>';
                new_row_html += '</button></td>';
                new_row_html += '</tr>';

                jQuery(new_row_html).insertBefore(jQuery(button).closest('tr'));

                reindexTable();

                }

            function delete_tms_field_mapping(element)
                {
                var button = jQuery(element);
                var record = jQuery(button).closest('tr');

                record.remove();
                reindexTable();

                }

            // This function reindexes the attribute 'name' when 'Add mapping' or 'Delete' is pressed
            function reindexTable()
                {
                // Go through each row (not first or last though)
                jQuery('#tmsModulesMappingTable tr').not(':first').not(':last').each(function(i)
                    {

                    // Build strings again using correct number
                    nameFirst   = "tms_rs_mappings[" + i + "][tms_column]";
                    nameMiddle  = "tms_rs_mappings[" + i + "][rs_field]";
                    nameLast    = "tms_rs_mappings[" + i + "][encoding]";

                    // Change name of each input/select to its correct number
                    jQuery(this).find('td').eq(0).find('input').attr("name", nameFirst);
                    jQuery(this).find('td').eq(1).find('select').attr("name", nameMiddle);
                    jQuery(this).find('td').eq(2).find('input').attr("name", nameLast);

                    });

                }

            </script>
        </div>
        <div class="QuestionSubmit">
            <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>">
        </div>
    </form>
</div>
<?php

odbc_close($conn);
include '../../../include/footer.php';
