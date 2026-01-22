<?php

/**
* @package ResourceSpace\Tools
*/

$webroot = dirname(__DIR__, 2);
include_once "{$webroot}/include/boot.php";
command_line_only();

set_time_limit(0);

$help_text = <<<'HELP'
NAME
    join_fields - merge values from multiple fields into one.

SYNOPSIS
    php /path/to/pages/tools/join_fields.php [OPTIONS] fields... result_field

DESCRIPTION
    A tool to help administrators merge multiple fields' values together.

    The result of joining those values together will be saved in another field.

    IMPORTANT: supports only text fields!

    Please note joining a FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE with any text field will result in plain HTML 
    displayed. This is not a fault, you can either:
     - run pages/tools/remove_html.php, or
     - IF you care about rendering actual HTML, then make sure the result of the join is saved into a FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE

OPTIONS SUMMARY

    -h, --help                 Display this help text and exit
    -g, --glue                 The string that glues all the values together. Default: empty string (ie. '')

EXAMPLES
    php join_fields.php 1 29 85 25
                                ^ the field ID storing the result of the join
                             ^ field to join
                          ^ field to join
                        ^ field to join

    php join_fields.php --glue="\n" 1 29 85 25


HELP;

// Script options @see https://www.php.net/manual/en/function.getopt.php
$cli_short_options = "hg:";
$cli_long_options  = [
    "help",
    'glue:',
];
$options = getopt($cli_short_options, $cli_long_options);
foreach ($options as $option_name => $option_value) {
    if (in_array($option_name, ["h", "help"])) {
        echo $help_text;
        exit(0);
    }

    if (in_array($option_name, ['g', 'glue']) && is_string($option_value)) {
        $glue = $option_value;
    }
}

$glue = $glue ?? '';
$fields_to_join = array_values(array_filter($argv, function ($v) {
    return is_int_loose($v) && $v > 0;
}));
$out = array_pop($fields_to_join) ?? 0;

if (!($out > 0 && count($fields_to_join) >= 2)) {
    logScript("ERROR: Insufficient arguments passed. You need at least two fields to join and one field to store the result. See help for more." . PHP_EOL);
    echo $help_text;
    exit(1);
}

logScript("Script will save all joined data to field #{$out}");
logScript("Script set to join data using the following glue: '{$glue}'");

$resources_updates = [];
foreach ($fields_to_join as $rtf_ref) {
    $rtf_data = get_resource_type_field($rtf_ref);
    if ($rtf_data === false) {
        logScript("WARNING: Unable to find metadata field #{$rtf_ref}. Skipping...");
        continue;
    } elseif (!in_array($rtf_data['type'], $TEXT_FIELD_TYPES)) {
        logScript(
            sprintf(
                'WARNING: Unsupported metadata field type (%s) found for field #%s . Only text fields are supported!',
                strtolower($lang[$field_types[$rtf_data['type']]]),
                $rtf_ref
            )
        );
        continue;
    }

    $data = get_data_by_field(null, $rtf_ref);
    foreach ($data as $resource_field_data) {
        $joined_value = $resources_updates[$resource_field_data['resource']] ?? '';
        $joined_value .= $glue . $resource_field_data['value'];

        $resources_updates[$resource_field_data['resource']] = $joined_value;
    }
}

foreach ($resources_updates as $resource => $new_field_value) {
    logScript("Processing resource #{$resource} ...");
    $new_field_value = ltrim($new_field_value, $glue);

    $update_err = [];
    if (update_field($resource, $out, $new_field_value, $update_err)) {
        logScript("Updated resource #{$resource}");
    } else {
        logScript(
            sprintf(
                'ERROR: Failed to update resource #%s with new value "%s". Reason(s): %s',
                $resource,
                $new_field_value,
                implode('; ', $update_err)
            )
        );
    }
}

logScript("Script ran successfully!");
