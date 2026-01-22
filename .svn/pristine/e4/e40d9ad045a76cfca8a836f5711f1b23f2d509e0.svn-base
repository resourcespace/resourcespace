<?php

include "../../include/boot.php";
command_line_only();

$help_text = <<<'HELP'
NAME
    migrate_fixed_to_text.php - # Manually migrate resource type field data from a fixed list type which allows multiple nodes to a text field type which expects one only.

SYNOPSIS
    php /path/to/pages/tools/migrate_fixed_to_text.php [OPTIONS]

DESCRIPTION
    This tool provides a method for administrators (via the command line) to manually migrate data from a fixed list field type to a text type.

    Some fixed list field types including dynamic keywords list, category tree and checkbox list allow multiple values to be saved. Each is recorded by applying a node value to
    the intended resource. When switching to text type fields such as text single or multi-line, only one value is allowed. This script will process the nodes on each resource
    for the resource type field who's type has been changed. It'll convert the existing multiple nodes into a single node containing a concatenation of all existing node data.

    Before running this script, the field type should have already been changed to a text type. You'll also need to indicate if the previous type was category tree as the script
    will attempt to preserve the category tree branches.

    Note: While fixed list fields can contain i18n language strings, the same isn't true for free text fields. You can specify a language to translate to if i18n language strings
    were used previously in the fixed list field.

    After processing, node id ordering of values will be used. Values will be concatenated as they appear currently on the view page of the resource.

OPTIONS SUMMARY

    --help         Display this help text and exit.
    --field        Required parameter to specify a resource type field (metadata field) who's type has been changed from a fixed list to text type.
    --tree         Required parameter to specify if the previous field type was a category tree, enter yes or no.
    --separator    Optional parameter to change the separator used to concatenate the data. The default if not set will be ", " e.g. "Value1, Value2".
    --lang         Optional parameter to change the language to output to if using i18n language strings. If not set, the system default will be used.

EXAMPLES
    php migrate_fixed_to_text.php --field 96 --tree yes --separator=" - "
                                    ^ The resource type field who's type was changed from fixed list to text.
                                               ^ Specify if the field type was previously a category tree.
                                                            ^ Optional change of default separator to " - ".
HELP;

$parameters = getopt('', array('help','field:','tree:','separator::','lang::'));

if (array_key_exists('help', $parameters)) {
    exit($help_text . PHP_EOL);
}

if (!array_key_exists('field', $parameters) || !array_key_exists('tree', $parameters) || !is_numeric($parameters['field']) || $parameters['field'] == 0 || !in_array($parameters['tree'], array('yes', 'no'))) {
    exit('Error: Both --field and --tree parameters must be set. See --help for more details.' . PHP_EOL);
}

$resource_type_field = (int) $parameters['field'];
$category_tree = $parameters['tree'] == 'yes';
$separator = ', ';
if (isset($parameters['separator']) && $parameters['separator'] !== false) {
    $separator = $parameters['separator'];
}
if (isset($parameters['lang']) && $parameters['lang'] !== false) {
    if (strlen($parameters['lang']) != 2) {
        exit('--lang requires a two letter language code e.g. en');
    }
    $GLOBALS['lang'] = strtolower($parameters['lang']);
}

global $TEXT_FIELD_TYPES;

$check_field_type = ps_query("SELECT value_old, value_new FROM activity_log WHERE remote_table = 'resource_type_field' AND remote_ref = ? AND remote_column = 'type' ORDER BY ref DESC LIMIT 1;", array('i', $resource_type_field));
if (
    count($check_field_type) == 0 || !in_array($check_field_type[0]['value_old'], array(FIELD_TYPE_CATEGORY_TREE, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, FIELD_TYPE_CHECK_BOX_LIST)) ||
    !in_array($check_field_type[0]['value_new'], $TEXT_FIELD_TYPES)
) {
    exit("Resource type field id $resource_type_field cannot be processed. Check the field to confirm it was previously a category tree, dynamic keyword list or check box list and that it has now been changed to a text field." . PHP_EOL);
}

if ($check_field_type[0]['value_old'] == FIELD_TYPE_CATEGORY_TREE && !$category_tree) {
    exit("Previous field type was category tree. Try processing with --tree yes" . PHP_EOL);
}

$resources = ps_array("SELECT `resource` AS 'value' FROM resource_node rn JOIN node n ON rn.node = n.ref WHERE n.resource_type_field = ? GROUP BY `resource` HAVING COUNT(`resource`) > 1 ORDER BY `resource` ASC;", array('i', $resource_type_field));
echo 'Processing ' . count($resources) . ' resources.' . PHP_EOL;

foreach ($resources as $resource) {
    $result = migrate_fixed_to_text($resource_type_field, $resource, $category_tree, $separator);
    if (!$result) {
        exit("An error occurred processing $resource." . PHP_EOL);
    }
    echo "Resource $resource updated." .  PHP_EOL;
}

echo 'Completed' . PHP_EOL;
