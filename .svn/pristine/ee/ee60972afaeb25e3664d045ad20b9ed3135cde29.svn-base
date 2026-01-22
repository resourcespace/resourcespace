<?php

command_line_only();

$webroot = dirname(__DIR__, 2);
include_once "{$webroot}/include/request_functions.php";


$missing_id_prop = array(
    array(
        "title"    => "Full name",
        "type"     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
        "required" => true,
    )
);
$missing_title_prop = array(
    array(
        "id"       => 1,
        "type"     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
        "required" => true,
    )
);
$missing_type_prop = array(
    array(
        "id"       => 1,
        "title"    => "Full name",
        "required" => true,
    )
);
$missing_required_prop = array(
    array(
        "id"       => 1,
        "title"    => "Full name",
        "type"     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
    )
);
$missing_options_prop = array(
    array(
        "id"       => 1,
        "title"    => "~en:Roles~fr:rôles",
        "type"     => FIELD_TYPE_CHECK_BOX_LIST,
        "required" => true,
    )
);

if (!empty(get_valid_custom_fields($missing_id_prop))) {
    echo "missing_id_prop - ";
    return false;
}

if (!empty(get_valid_custom_fields($missing_title_prop))) {
    echo "missing_title_prop - ";
    return false;
}

if (!empty(get_valid_custom_fields($missing_type_prop))) {
    echo "missing_type_prop - ";
    return false;
}

if (!empty(get_valid_custom_fields($missing_required_prop))) {
    echo "missing_required_prop - ";
    return false;
}

if (!empty(get_valid_custom_fields($missing_options_prop))) {
    echo "missing_options_prop - ";
    return false;
}

$fields = array(
    array(
        "id"       => 1,
        "title"    => "Add HTML props",
        "type"     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
        "required" => false,
    )
);

// Check HTML props are generated
$html_props = gen_custom_fields_html_props(get_valid_custom_fields($fields), []);
if (!isset($html_props[0]["html_properties"]) || !is_array($html_props[0]["html_properties"])) {
    echo "Generate HTML properties - ";
    return false;
}

// Tear down
unset($missing_id_prop, $missing_title_prop, $missing_type_prop, $missing_required_prop, $fields);

return true;
