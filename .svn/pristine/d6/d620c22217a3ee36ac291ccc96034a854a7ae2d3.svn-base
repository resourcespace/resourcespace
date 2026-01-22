<?php

/**
WARNING: read what this does before even attempting to run it as it will convert data encoding to UTF-8 or potentially
         double encode
*/

$webroot = dirname(dirname(__DIR__));
include_once "{$webroot}/include/boot.php";
include_once "{$webroot}/include/log_functions.php";
command_line_only();

function checkEncoding($string, $string_encoding)
{
    $fs = $string_encoding == 'UTF-8' ? 'UTF-32' : $string_encoding;
    $ts = $string_encoding == 'UTF-32' ? 'UTF-8' : $string_encoding;

    return $string === mb_convert_encoding(mb_convert_encoding($string, $fs, $ts), $ts, $fs);
}

$dry_run = false;
$show_sql = false;

// Tables that should be looked at
$tables = array(
    'resource',
    'resource_data',
    'node',
);
$encodings = array('UTF-8', 'ISO-8859-1', 'ASCII');
$to_encoding = 'UTF-8';

// Script options (if required)
$cli_short_options = '';
$cli_long_options  = array(
    'dry-run',
    'table:',
    'from-encoding:',
    'to-encoding:',
    'show-sql',
);

foreach (getopt($cli_short_options, $cli_long_options) as $option_name => $option_value) {
    if ($option_name == 'table') {
        $tables = $option_value;

        if (!is_array($option_value)) {
            $tables = array($option_value);
        }
    }

    if ($option_name == 'dry-run') {
        $dry_run = true;
    }

    if ($option_name == 'show-sql') {
        $show_sql = true;
    }

    /*
    The from-encoding can be repeated multiple times if the data was somehow in multiple encodings. The order of options
    matters as is used by mb_detect_order().
    Usage example: php utf8_data_convert.php --dry-run --from-encoding="ISO-8859-1" --from-encoding="ISO-8859-6" --to-encoding="UTF-8"
    */
    if ($option_name == 'from-encoding') {
        $from_encodings = (is_array($option_value) ? $option_value : array($option_value));
        foreach ($from_encodings as $from_encoding) {
            $encodings[] = $from_encoding;
        }

        $encodings = array_unique($encodings);
    }
    // We should only encode to one encoding
    elseif ($option_name == 'to-encoding' && !is_array($option_value)) {
        $to_encoding = $option_value;

        $encodings[] = $to_encoding;
        $encodings = array_unique($encodings);
    }
}

logScript("Converting data to UTF-8 (useful when migrating a database from a non-UTF-8 to UTF-8 character set)");
logScript("Running with:");
logScript("dry-run = " . ($dry_run ? 'true' : 'false') . PHP_EOL);

mb_detect_order($encodings);

$query_log = "";

if (in_array('resource', $tables)) {
    logScript("Searching resource table...");
    $resource_table_joins = get_resource_table_joins();
    $joined_fields = (!empty($resource_table_joins) ? ', field' : '' ) . implode(', field', $resource_table_joins);
    $resources = ps_query("SELECT ref {$joined_fields}  FROM resource");
    foreach ($resources as $row) {
        foreach ($row as $column => $value) {
            if (substr($column, 0, 5) != 'field') {
                continue;
            }

            $current_encoding = mb_detect_encoding($row[$column], mb_detect_order(), true);
            if ($current_encoding === false) {
                logScript("Unable to detect encoding from the given string '{$row[$column]}' for '{$column}' column!");
                continue;
            }

            $utf8value = mb_convert_encoding($row[$column], $to_encoding, $current_encoding);
            if ($row[$column] == $utf8value && checkEncoding($row[$column], $to_encoding) === true) {
                continue;
            }

            logScript("Column '{$column}': ($current_encoding) '{$row[$column]}' ===> ({$to_encoding}) '$utf8value'");

            $query = "UPDATE resource SET `{$column}` = ? WHERE ref = ?";
            $params = ['s', $utf8value, 'i', $row['ref']];

            if (!$dry_run) {
                ps_query($query, $params);
            }

            $query_log .= $query . PHP_EOL;
        }
    }
}

if (in_array('resource_data', $tables)) {
    logScript("");
    logScript("Searching resource_data table...");
    $resource_data = ps_query("SELECT resource, resource_type_field, `value` FROM resource_data");
    foreach ($resource_data as $row) {
        $current_encoding = mb_detect_encoding($row["value"], mb_detect_order(), true);
        if ($current_encoding === false) {
            logScript("Unable to detect encoding from the given string '{$row["value"]}'");
            continue;
        }

        $utf8value = mb_convert_encoding($row["value"], $to_encoding, $current_encoding);
        if ($row["value"] == $utf8value && checkEncoding($row["value"], $to_encoding) === true) {
            continue;
        }

        logScript("($current_encoding) '{$row["value"]}' ===> ({$to_encoding}) '$utf8value'");

        $query = "UPDATE resource_data SET `value` = ? WHERE resource = ? AND resource_type_field = ?";
        $params = ['s', $utf8value, 'i', $row['resource'], 'i', $row['resource_type_field']];

        if (!$dry_run) {
            ps_query($query, $params);
        }

        $query_log .= $query . PHP_EOL;
    }
}

if (in_array('node', $tables)) {
    logScript("");
    logScript("Searching node table...");
    $nodes = ps_query("SELECT ref, name FROM node");
    foreach ($nodes as $node) {
        $current_encoding = mb_detect_encoding($node["name"], mb_detect_order(), true);
        if ($current_encoding === false) {
            logScript("Unable to detect encoding from the given string '{$node['name']}'");
            continue;
        }

        $utf8value = mb_convert_encoding($node["name"], $to_encoding, $current_encoding);
        if ($node["name"] == $utf8value && checkEncoding($node["name"], $to_encoding) === true) {
            continue;
        }

        logScript("Node #{$node['ref']}: ($current_encoding) '{$node['name']}' ===> ({$to_encoding}) '$utf8value'");

        $query = "UPDATE node SET `name` = ? WHERE ref = ?";
        $params = ['s', $utf8value, 'i', $node['ref']];

        if (!$dry_run) {
            ps_query($query, $params);
        }

        $query_log .= $query . PHP_EOL;
    }
}

if ($show_sql) {
    logScript("");
    logScript("Query log:" . PHP_EOL . PHP_EOL . $query_log);
}
logScript("Completed!");
