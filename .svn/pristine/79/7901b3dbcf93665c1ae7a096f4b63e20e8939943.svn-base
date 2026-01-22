<?php

include "../../include/boot.php";

// Allow access from UI only if authenticated and admin
if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";

    if (!checkperm('a')) {
        http_response_code(401);
        exit($lang['error-permissiondenied']);
    }
}

$help_text = <<<'HELP'
NAME
    run_autocomplete_macros.php - Manually re-run autocomplete macros.

SYNOPSIS
    php /path/to/pages/tools/run_autocomplete_macros.php [OPTIONS]

DESCRIPTION
    This tool provides a method for administrators (via the command line or a browser) to re-run autocomplete macros.

    Autocomplete macros contain PHP code that is executed to produce a default value, typically once only for example when creating a new resource.
    This script maybe useful if the autocomplete macro has changed or it is necessary to apply it to preexisting resources.
    A number of options can be specified to change the behaviour of this script.

OPTIONS SUMMARY

    --help         Display this help text and exit.
    --col          Optional parameter to specify a collection of resources to update. If not set, all resources will be processed.
    --field        Optional parameter to specify a resource type field (metadata field) who's autocomplete macro will re-run. If not set, autocomplete macros
                   on all resource type fields will be processed.
    --force        Optional parameter to update resources that already have a value in their autocomplete macro field. This will overwrite existing data!

EXAMPLES
    php run_autocomplete_macros.php --field 25 --col 5 --force
                                            ^ The resource type field who's autocomplete macro will be run.
                                                     ^ The collections of resources to be processed.
                                                          ^ Overwrite existing values.

    https://<ResourceSpace>/pages/tools/run_autocomplete_macros.php?col=4&field=25&force=true
                                                                        ^ The collections of resources to be processed.
                                                                                ^ The resource type field who's autocomplete macro will be run.
                                                                                          ^ Overwrite existing values.
HELP;

$cli_options = array();
if (PHP_SAPI == 'cli') {
    $cli_options = getopt('', array('help','field:','col:','force'));
}

if (array_key_exists('help', $cli_options)) {
    exit($help_text . PHP_EOL);
}

$force_update = false;
if (array_key_exists('force', $cli_options)) {
    $force_update = true;
}
$force_update = (bool) getval("force", $force_update);

$collection = 0;
if (array_key_exists('col', $cli_options)) {
    $collection = $cli_options['col'];
}
$collection = getval("col", $collection);
if (!is_numeric($collection)) {
    exit('Collection reference provided must be numeric.' . PHP_EOL);
}
$collection = (int) $collection;

$field = 0;
if (array_key_exists('field', $cli_options)) {
    $field = $cli_options['field'];
}
$field = getval("field", $field);
if (!is_numeric($field)) {
    exit('Metadata field reference provided must be numeric.' . PHP_EOL);
}
$field = (int) $field;

if ($field !== 0) {
    $valid_fields = get_resource_type_fields("", "ref", "asc", "", array(), true);
    $valid_fields = array_column($valid_fields, 'ref');
    if (!in_array($field, $valid_fields)) {
        exit('Invalid resource type field reference supplied.' . PHP_EOL);
    }
}

if ($collection === 0) {
    $resources = ps_array("SELECT ref value FROM resource WHERE ref > 0");
} else {
    $resources = get_collection_resources($collection);
    if (is_array($resources) && count($resources) == 0) {
        exit('Invalid or empty collection reference supplied. Nothing to do.' . PHP_EOL);
    }
}

foreach ($resources as $resource) {
    $fields_updated = autocomplete_blank_fields((int) $resource, $force_update, true, $field);

    foreach ($fields_updated as $key => $val) {
        echo "Resource " . $resource . ", Field " . $key . " = " . $val . (PHP_SAPI == 'cli' ? PHP_EOL : '</br>');
    }
}
