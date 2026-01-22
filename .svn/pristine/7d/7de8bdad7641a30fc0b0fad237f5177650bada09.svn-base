<?php

command_line_only();


if (create_resource_type_field(" ") !== false) {
    echo "Empty space title - ";
    return false;
}

if (create_resource_type_field("Bad restype", "invalid") !== false) {
    echo "String restype - ";
    return false;
}

if (create_resource_type_field("Bad type", 0, "invalid") !== false) {
    echo "String type - ";
    return false;
}

if (create_resource_type_field("Bad type", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 251) === false) {
    echo "Shortname as a number - ";
    return false;
}

if (create_resource_type_field("Shortname as a string", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "some random string") === false) {
    echo "Shortname as a string - ";
    return false;
}

$empty_shortname = create_resource_type_field("Empty shortname_text", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "");
if ($empty_shortname !== false) {
    $rtf = get_resource_type_field($empty_shortname);
    if ($rtf["name"] != "emptyshortnametext") {
        echo "Shortname: empty (title used instead) - ";
        return false;
    }
}

$rtf_responsibility = create_resource_type_field("Responsibility", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "responsibility");
$rtf_responsibility_duplicate = create_resource_type_field("Responsibility", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "responsibility");
if ($rtf_responsibility !== false && $rtf_responsibility_duplicate !== false) {
    $rtf = get_resource_type_field($rtf_responsibility_duplicate);
    if ($rtf["name"] == "responsibility") {
        echo "Shortname: duplicate fields - ";
        return false;
    }
} else {
    echo "Creating duplicate fields - ";
    return false;
}

$indexed_rtf = create_resource_type_field("Indexed rtf", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "", null);
if ($indexed_rtf !== false) {
    $rtf = get_resource_type_field($indexed_rtf);
    if ($rtf["keywords_index"] != "0") {
        echo "Index: null - ";
        return false;
    }
}


return true;
