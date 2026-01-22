<?php

command_line_only();

// --- Set up
// Metadata fields
$rtf_checkbox = create_resource_type_field("Test #403 checkbox", 1, FIELD_TYPE_CHECK_BOX_LIST, "test_403_checkbox", true);
$ckb_opt_a = set_node(null, $rtf_checkbox, '~en:Scanned negative~fr:Négatif scanné', null, 10);
$ckb_opt_b = set_node(null, $rtf_checkbox, '~en:Digital camera~fr:Appareil photo numérique', null, 20);

$resource_a = create_resource(1, 0);
$resource_b = create_resource(1, 0);

add_resource_nodes($resource_a, [$ckb_opt_a, $ckb_opt_b], false);
// --- End of Set up



// Check that $field_column_string_separator config is applied/used
$field_column_string_separator = '|:|';
$data_joins[] = $rtf_checkbox;

$fields = get_resource_field_data($resource_b, false, false);
$all_selected_nodes = [];
$locked_fields = [$rtf_checkbox];
$last_edited = $resource_a;
copy_locked_fields($resource_b, $fields, $all_selected_nodes, $locked_fields, $last_edited, true);
unset($fields, $all_selected_nodes, $locked_fields, $last_edited);
if (mb_strpos(get_resource_data($resource_b, false)["field{$rtf_checkbox}"], $field_column_string_separator) === false) {
    echo "Use case: use separator for storing multiple node values in the resource table (column fieldX) - ";
    return false;
}



// Tear down
$data_joins = [];
unset($rtf_checkbox, $ckb_opt_a, $ckb_opt_b, $resource_a, $resource_b);

return true;
