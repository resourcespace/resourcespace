<?php
include_once __DIR__ . '/../include/emu_api.php';
include_once __DIR__ . '/../include/emu_functions.php';


function HookEmuEditEdithidefield($field)
{
    global $ref, $resource, $emu_irn_field, $emu_resource_types, $emu_created_by_script_field;

    if ($emu_irn_field == $field['ref'] && 0 > $ref && in_array($resource['resource_type'], $emu_resource_types)) {
        return true;
    }

    return false;
}


function HookEmuEditEditbeforesectionhead()
{
    global $lang, $baseurl, $ref, $resource, $emu_irn_field, $emu_confirm_upload, $emu_resource_types;
    if ($ref < 0 && in_array($resource['resource_type'], $emu_resource_types)) {
        $value = escape(get_data_by_field($ref, $emu_irn_field));
        ?>
        <div id="question_emu" class="Question">
            <label for="question_emu"><?php echo escape($lang['emu_upload_emu_field_label']); ?></label>
            <input id="field_<?php echo $emu_irn_field; ?>" type="text" name="field_<?php echo $emu_irn_field; ?>" value="<?php echo $value; ?>">
            <div class="clearerleft"></div>
        </div>
        <?php
        if (isset($emu_confirm_upload) && $emu_confirm_upload) {
            ?>
            <div id="emu_confirm_upload" class="Question FieldSaveError">
                <label for="emu_confirm_upload"><?php echo escape($lang['emu_confirm_upload_nodata']); ?></label>
                <input id="emu_confirm_upload" type="checkbox" name="emu_confirm_upload" value="true">
                <div class="clearerleft"></div>
            </div>
            <?php
        }
    }
}


function HookEmuAllAdditionalvalcheck($fields, $fields_item)
{
    global $lang, $ref, $resource, $emu_api_server, $emu_api_server_port, $emu_irn_field, $emu_resource_types, $emu_rs_saved_mappings;

    if (!in_array($resource['resource_type'], $emu_resource_types)) {
        return false;
    }

    if ($emu_irn_field == $fields_item['ref']) {
        global $emu_data;

        $emu_field_data = getval("field_{$emu_irn_field}", '', true);

        $emu_irn         = intval($emu_field_data);
        $emu_rs_mappings = plugin_decode_complex_configs($emu_rs_saved_mappings);
        $emu_data        = get_emu_data($emu_api_server, $emu_api_server_port, array($emu_irn), $emu_rs_mappings);

        if ($emu_irn === 0 && $emu_field_data === '') {
            // Don't force a blank entry to be 0 on save.
            $emu_irn = '';
        }
        // Make sure we actually do save this data, even if we return an error
        update_field($ref, $emu_irn_field, $emu_irn);

        if (count($emu_data) === 0 && 0 > $ref) {
            // We can't get any data from EMu for this new resource. Need to show warning if user has not already accepted this
            if ('' == getval('emu_confirm_upload', '')) {
                global $emu_confirm_upload;

                $emu_confirm_upload = true;

                return "{$lang['emu_upload_nodata']} {$lang['emu_confirm_upload_nodata']}";
            }
        } else {
            global $emu_import;

            $emu_import = true;

            return false;
        }
    }

    return false;
}


function HookEmuEditSaveextraresourcedata($list)
{
    global $emu_irn_field;

    $emu_irn = intval(getval("field_{$emu_irn_field}", '', true));

    if (0 == $emu_irn) {
        return false;
    }

    global $emu_api_server, $emu_api_server_port, $emu_rs_saved_mappings, $emu_data;

    $emu_rs_mappings = plugin_decode_complex_configs($emu_rs_saved_mappings);
    $emu_data        = get_emu_data($emu_api_server, $emu_api_server_port, array($emu_irn), $emu_rs_mappings);

    if (is_array($emu_data)) {
        global $emu_import, $emu_update_list;

        $emu_import      = true;
        $emu_update_list = $list;
    }

    return false;
}

/**
* Emu plugin attaching to the 'aftersaveresourcedata' hook
* IMPORTANT: 'aftersaveresourcedata' hook is called from both save_resource_data() and save_resource_data_multi()!
*
* @return boolean|array Returns FALSE to show hook didn't run -OR- a list of errors. See hook 'aftersaveresourcedata'
*                       in resource_functions.php for more info.
*/
function HookEmuEditAftersaveresourcedata()
{
    if (!isset($GLOBALS['emu_import']) || (isset($GLOBALS['emu_import']) && !$GLOBALS['emu_import'])) {
        return false;
    }

    global $ref, $emu_irn_field, $emu_rs_saved_mappings, $emu_data, $emu_update_list, $lang;

    $emu_irn = (int) getval("field_{$emu_irn_field}", 0, false, 'is_positive_int_loose');
    $emu_irn_invalid = $emu_data === [];

    if ($emu_irn === 0 && $emu_irn_invalid) {
        return false;
    } elseif ($emu_irn > 0 && $emu_irn_invalid) {
        return [$lang['emu_nodata_returned']];
    }

    // Update resources with EMu data
    $resources = (is_array($emu_update_list) ? $emu_update_list : [(int) $ref]);
    $emu_rs_mappings = plugin_decode_complex_configs($emu_rs_saved_mappings);

    foreach ($resources as $resource_ref) {
        debug("emu: Updating resource ID #{$resource_ref} with data from EMu database");

        foreach ($emu_rs_mappings as $emu_module_columns) {
            foreach ($emu_module_columns as $emu_module_column => $rs_field_id) {
                if (0 != intval($rs_field_id) && isset($emu_data[$emu_irn][$emu_module_column]) && $emu_irn_field != $rs_field_id) {
                    update_field($resource_ref, $rs_field_id, $emu_data[$emu_irn][$emu_module_column]);
                }
            }
        }
    }

    return false;
}