<?php

/**
* @package ResourceSpace\Tools
*
* A tool to help administrators merge two ResourceSpace systems.
*/

if ('cli' != PHP_SAPI) {
    http_response_code(401);
    exit('Access denied - Command line only!');
}

$help_text = "NAME
    merge_rs_systems - a tool for merging two ResourceSpace systems.

SYNOPSIS
    On the system (also known as SRC system) that is going to merge with the other (DEST) system:
        php path/tools/merge_rs_systems.php [OPTION...] DEST

    On the system (also known as DEST system), merging in data from the other (SRC) system:
        php path/tools/merge_rs_systems.php [OPTION...] SRC

DESCRIPTION
    A tool to help administrators merge two ResourceSpace systems.

    A specification file is required for the migration to be possible. The spec file will contain:-
    - A mapping between the SRC system and the DEST systems' records. Use the --generate-spec-file option to get
      an example.

OPTIONS SUMMARY

    -h, --help              display this help and exit
    -u, --user              run script as a ResourceSpace user. Use the ID of the user
    -l, --language          Set the language when translating i18n strings (see https://www.resourcespace.com/knowledge-base/systemadmin/translations)
                            Useful to prevent duplicate fixed list field options because one side has translations and
                            the other doesn't.
                            Please note if using the option multiple times, only the last occurence will take precedence.
    --dry-run               perform a trial run with no changes made. IMPORTANT: unavailable for import!
    --clear-progress        clear the progress file which is automatically generated at import
    --generate-spec-file    generate an example specification file
    --spec-file=FILE        read specification from FILE
    --export                export information from ResourceSpace
    --import                import information to ResourceSpace based on the specification file (Requires spec-file and 
                            user options)

DEPENDENCIES
    The tool requires rse_workflow plugin to be enabled.

EXAMPLES
    Export
    ======
    php /path/to/pages/tools/merge_rs_systems.php --dry-run --export /path/to/export_folder/

    Import
    ======
    php /path/to/pages/tools/merge_rs_systems.php --clear-progress --spec-file=\"/path/to/spec.php\" --import /path/to/export_folder_from_src/
    " . PHP_EOL;

$cli_short_options = "hu:l:";
$cli_long_options  = array(
    "help",
    "dry-run",
    "clear-progress",
    "user:",
    "language:",
    "spec-file:",
    "export",
    "import",
    "generate-spec-file",
);
$options = getopt($cli_short_options, $cli_long_options);

$help = false;
$dry_run = false;
$export = false;
$import = false;
$clear_progress = false;

foreach ($options as $option_name => $option_value) {
    if (in_array($option_name, array("h", "help"))) {
        echo $help_text;
        exit(0);
    }

    if (
        in_array(
            $option_name,
            array(
            "dry-run",
            "clear-progress",
            "export",
            "import",
            "generate-spec-file")
        )
    ) {
        fwrite(STDOUT, "Script running with '{$option_name}' option enabled!" . PHP_EOL);

        $option_name = str_replace("-", "_", $option_name);
        $$option_name = true;
    }

    if ($option_name == "spec-file" && !is_array($option_value)) {
        if (!file_exists($option_value)) {
            fwrite(STDERR, "ERROR: Unable to open input file '{$option_value}'!" . PHP_EOL);
            exit(1);
        }

        $spec_file_path = $option_value;
    }

    if (in_array($option_name, array("u", "user")) && !is_array($option_value)) {
        if (!is_numeric($option_value) || (int) $option_value <= 0) {
            fwrite(STDERR, "ERROR: Invalid 'user' value provided: '{$option_value}' of type " . gettype($option_value) . PHP_EOL);
            fwrite(STDOUT, PHP_EOL . $help_text);
            exit(1);
        }

        $user = $option_value;
    }

    if (in_array($option_name, ["l", "language"])) {
        if (is_array($option_value)) {
            fwrite(STDERR, "ERROR: Language should only be set once. Use either -l or --language." . PHP_EOL);
            fwrite(STDOUT, PHP_EOL . $help_text);
            exit(1);
        }

        $language = trim($option_value);
    }
}

$webroot = dirname(dirname(__DIR__));
include_once "{$webroot}/include/boot.php";
set_time_limit(0);

// Increase this DB session idle timeout to 24 hours
ps_query('SET session wait_timeout=86400;');
$sql_session_wait_timeout = array_column(ps_query('SHOW SESSION VARIABLES LIKE "wait_timeout"'), 'Value', 'Variable_name');
logScript("Database session 'wait_timeout' variable is set to {$sql_session_wait_timeout['wait_timeout']} seconds");

$get_file_handler = function ($file_path, $mode) {
    $file_handler = fopen($file_path, $mode);
    if ($file_handler === false) {
        logScript("ERROR: Unable to open output file '{$file_path}'!");
        exit(1);
    }

    return $file_handler;
};

$json_decode_file_data = function ($fh) {
    $input_lines = array();
    while (($line = fgets($fh)) !== false) {
        if (trim($line) != "" &&  mb_check_encoding($line, "UTF-8")) {
            $input_lines[] = trim($line);
        }
    }
    fclose($fh);

    if (empty($input_lines)) {
        logScript("WARNING: No data to import! To be safe, double check on the source side whether this is true.");
        return array();
    }

    $json_decoded_data = array();
    foreach ($input_lines as $input_line) {
        $value = json_decode($input_line, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logScript("ERROR: Unable to decode JSON because of the following error: " . json_last_error_msg());
            exit(100);
        }
        $json_decoded_data[] = $value;
    }

    return $json_decoded_data;
};

if (isset($generate_spec_file) && $generate_spec_file) {
    $spec_fpath = (getcwd() ?: dirname(__DIR__, 2)) . '/spec_file_example.php';
    $spec_fh = $get_file_handler($spec_fpath, "w+b");
    fwrite($spec_fh, '<?php
// All of the following configuration options have the left side (keys) represent the SRC and DEST on the right (values)

// User groups can be configured in three ways:
// - map to an existing record in DEST system
// - create new
// - do not create
$usergroups_spec = array(
    3 => 3, # Super Admin
    9 => array(
        "create" => true,
    ),
    13 => array(
        "create" => false,
    ),
);

// Archive states can either be mapped to an existing record or be created as a new state on the DEST system
$archive_states_spec = array(
    0 => 0, # Active
    4 => null, # Marketing - new on DEST system
);

// Resource types can either be mapped to an existing record or be created as a new state on the DEST system
$resource_types_spec = array(
    0 => 0, # Global (magic type)
    999 => 999, # Archive Only (magic type)
    1 => 1, # Photo
    5 => null, # Case Study
);

/*
Resource type fields can be configured in three ways:
- map to an existing record on DEST system
- do not create 
- create new record

IMPORTANT: make sure you map to a compatible field on the DEST system. This is especially true for a category tree which
can end up a flat structure if mapped to a fixed list field of different type.

When it comes to field mappings, these are the rules the script goes by:-
- one SRC field can only be mapped to one DEST field;
- different SRC fixed list fields could be mapped to the same DEST field;
- when mapping fields, all the options from the SRC fixed list field move over to the DEST fixed list field (this also 
  applies to fields that are created as new on the destination system - ie. there\'s no mapping)

For fixed list fields, if required, there can be extra rules for the options. See nodes_spec rules. Please bear in mind 
that the metadata field mapping becomes the fallback rule for nodes.
*/
$resource_type_fields_spec = array(
    // Note: when mapping to a field on DEST system, the "create" property should still be true
    3  => array(
        "create" => true,
        "ref"    => 3,
    ), # Country | dynamic keywords
    8  => array(
        "create" => true,
        "ref"    => 8,
    ), # Title | text box
    9  => array("create" => false), # Document extract
    10 => array("create" => false), # Credit
    87 => array(
        "create" => true,
        "ref" => null,
    ), # Display condition parent | drop down
    88 => array(
        "create" => true,
        "ref" => null,
    ), # Display condition child | text box
);

/*
Allow nodes (ie fixed list field options) mapping between SRC and DEST. This is optional and should only be done as 
needed (ie. where a SRC field needs to become something else on DEST and its import is no longer required).

The rules governing nodes mapping is as follows:-
- one SRC node can be mapped to multiple DEST nodes (the DEST node can be under a different DEST field - compared 
  to the mapping of the SRC nodes\' field);
- different SRC nodes can be mapped to the same DEST node;

IMPORTANT: this is the true source of truth for mapping a SRC node to DEST node(s).
*/
$nodes_spec = [
    // from field 89
    280 => [260],
    281 => [181, 261,],
    282 => [262],

    // from field 91
    288 => [287], # Example of a map to a category tree ==> "Folder 1/1.2/1.2.1"
    289 => [181],
];
/*
Considering the configuration for both resource_type_fields_spec and nodes_spec, you can expect the following behaviour:-

SRC RTF        | Create on DEST? | Map to DEST RTF? | Options mapped | Outcome
===============|=================|==================|================|========
fixed list rtf | no              | no               | no             | discard all options
fixed list rtf | no              | no               | yes            | use only mapped DEST nodes, discard everything else
fixed list rtf | yes             | no               | yes            | move only mapped nodes to their DEST nodes, everything else is added as an option on the new field 
fixed list rtf | yes             | yes              | yes            | move only mapped nodes to their DEST node, everything else is moved to the mapped DEST field
*/

// Metadata field to store the SRC resource ID. MUST be of type "Text box (single line)". Set to zero to disable.
$rtf_src_resource_ref = 0;

// Fixed list field options that will be applied to all imported SRC resources. List of node IDs.
$nodes_applied_to_all_merged_resources = [];

// A resource access moves across as is. Exception are resources with custom access which need to be converted to a
// different access level (e.g open, restricted or confidential)
// Acceptable values are: RESOURCE_ACCESS_FULL -or- RESOURCE_ACCESS_RESTRICTED -or- RESOURCE_ACCESS_CONFIDENTIAL
$custom_access_new_value_spec = RESOURCE_ACCESS_RESTRICTED;

// SRC API details. Currently used to generate the SRC file (or alternatives) for the upload_processing job which, when
// run, will pull it in the DEST system.
$src_api = [
    \'base_url\' => \'\',
    \'username\' => \'\',
    \'key\' => \'\',
    \'http_options\' => [
        // \'timeout\' => 5, # in seconds, default is 60
    ],
];
' . PHP_EOL);
    fclose($spec_fh);
    logScript("Successfully generated an example of the spec file. Location: '{$spec_fpath}'");
    exit(0);
}

// Advanced workflow plugin is required to be enabled
$active_plugins = array_column(get_active_plugins(), 'name');
if (!in_array('rse_workflow', $active_plugins)) {
    logScript("ERROR: Missing requirement - 'Advanced workflow' plugin is not enabled.");
    exit(1);
}

if ($import && !isset($user)) {
    logScript("ERROR: You need to specify a user when importing. It is best if it is a Super Admin.");
    echo $help_text;
    exit(1);
}

if (isset($user)) {
    $user_select_sql = new PreparedStatementQuery();
    $user_select_sql->sql = "u.ref = ?";
    $user_select_sql->parameters = ["i",$user];
    $user_data = validate_user($user_select_sql, true);

    if (!is_array($user_data) || count($user_data) == 0) {
        logScript("ERROR: Unable to validate user ID #{$user}!");
        exit(1);
    }

    setup_user($user_data[0]);
    logScript("Running script as user '{$username}' (ID #{$userref})");

    // Reset any "maintenance mode" config options the system might be configured with
    $global_permissions_mask = "";
    $system_read_only = false;
}
logScript("Running script with language set to '{$language}'");

/*
For the following usage:
 - php path/tools/merge_rs_systems.php [OPTION...] --export DEST
 - php path/tools/merge_rs_systems.php [OPTION...] --import SRC
Ensure DEST/SRC folder has been provided when exporting/importing data
*/
if ($export || $import) {
    $folder_path = end($argv);
    if (!file_exists($folder_path) || !is_dir($folder_path)) {
        $folder_type = $export ? "DEST" : ($import ? "SRC" : "");
        logScript("ERROR: {$folder_type} MUST be an existing folder. Value provided: '{$folder_path}'");
        exit(1);
    }
}

if ($export && isset($folder_path)) {
    $tables = array(
        array(
            "name" => "usergroup",
            "formatted_name" => "user groups",
            "filename" => "usergroups",
            "record_feedback" => array(
                "text" => "User group #%ref '%name'",
                "placeholders" => array("ref", "name")
            ),
            "additional_process" => function ($record) {
                $usergroup_preferences = array();
                if (get_config_options(array('usergroup' => $record["ref"]), $usergroup_preferences)) {
                    logScript("Found user group preferences");
                    $record["usergroup_preferences"] = $usergroup_preferences;
                }
                return $record;
            }
        ),
        array(
            "name" => "user",
            "formatted_name" => "users",
            "filename" => "users",
            "record_feedback" => array(
                "text" => "User #%ref '%fullname' (Username: %username | E-mail: %email)",
                "placeholders" => array("ref", "fullname", "username", "email")
            ),
            "sql" => array(
                "select" => "*",
                "from"  => "user",
                "where" => "
                    username IS NOT NULL AND trim(username) <> ''
                    AND usergroup IS NOT NULL AND trim(usergroup) <> ''",
            ),
            "additional_process" => function ($record) {
                $user_preferences = array();
                if (get_config_options(array('user' => $record["ref"]), $user_preferences)) {
                    logScript("Found user preferences");
                    $record["user_preferences"] = $user_preferences;
                }
                return $record;
            },
        ),
        array(
            "name" => "resource_type",
            "formatted_name" => "resource types",
            "filename" => "resource_types",
            "record_feedback" => array(
                "text" => "Resource type #%ref '%name'",
                "placeholders" => array("ref", "name")
            )
        ),
        array(
            "name" => "resource_type_field",
            "formatted_name" => "resource type fields",
            "filename" => "resource_type_fields",
            "record_feedback" => array(
                "text" => "Resource type field #%ref '%title' (shortname: '%name')",
                "placeholders" => array("ref", "title", "name")
            )
        ),
        array(
            "name" => "resource_type_field_resource_type",
            "formatted_name" => "resource type field resource types",
            "filename" => "resource_type_field_resource_type",
            "record_feedback" => array(
                "text" => "Resource type field (#%resource_type_field) -> Resource type (#%resource_type) mappings",
                "placeholders" => array("resource_type_field", "resource_type"),
            ),
            "sql" => array(
                "where" => "resource_type_field IN (SELECT ref FROM resource_type_field)",
                "order_by" => 'resource_type_field ASC, resource_type ASC',
            ),
            "pagination" => [
                "cursor_sql" => new PreparedStatementQuery(
                    '(
                        resource_type_field > ? 
                        OR (resource_type_field = ? AND resource_type > ?)
                    )',
                    ['i', 0, 'i', 0, 'i', 0]
                ),
                "last_page_cursor_sql" => static function (PreparedStatementQuery $cursor, array $record): PreparedStatementQuery {
                    return new PreparedStatementQuery(
                        $cursor->sql,
                        [
                            'i',$record['resource_type_field'],
                            'i',$record['resource_type_field'],
                            'i',$record['resource_type'],
                        ]
                    );
                },
            ],
        ),
        array(
            "name" => "node",
            "formatted_name" => "nodes",
            "filename" => "nodes",
            "record_feedback" => array(),
            "sql" => array(
                "where" => "resource_type_field IN (SELECT ref FROM resource_type_field)",
            ),
        ),
        array(
            "name" => "resource",
            "formatted_name" => "resources",
            "filename" => "resources",
            "record_feedback" => array(),
            "sql" => array(
                "where" => "ref > 0
                    AND resource_type IN (SELECT ref FROM resource_type)
                    AND archive IN (SELECT `code` FROM archive_states)
                    AND (file_extension IS NOT NULL AND trim(file_extension) <> '')
                    AND (preview_extension IS NOT NULL AND trim(preview_extension) <> '')",
            ),
            "additional_process" => static function ($record) {
                if (!file_exists(get_resource_path($record["ref"], true, "", false, $record["file_extension"]))) {
                    logScript("WARNING: unable to get original file for resource #{$record["ref"]}");
                    return false;
                }
                return $record;
            },
        ),
        array(
            "name" => "resource_alt_files",
            "formatted_name" => "resource alternative files",
            "filename" => "resource_alt_files",
            "record_feedback" => array(),
            "sql" => array(
                "select"  => "raf.*",
                "from"  => "resource_alt_files AS raf
                    INNER JOIN resource AS r ON raf.resource = r.ref",
                "where" => "raf.resource > 0
                    AND r.resource_type IN (SELECT ref FROM resource_type)
                    AND r.archive IN (SELECT `code` FROM archive_states)
                    AND (r.file_extension IS NOT NULL AND trim(r.file_extension) <> '')
                    AND (r.preview_extension IS NOT NULL AND trim(r.preview_extension) <> '')"
                    . (
                        in_array('rse_version', $active_plugins)
                            ? "\r\n AND raf.ref NOT IN (
                                SELECT previous_file_alt_ref 
                                FROM resource_log
                                WHERE previous_file_alt_ref IS NOT null
                                AND type = 'u'
                                AND resource = r.ref
                            )\r\n"
                            : ''
                    ),
                "order_by" => 'raf.ref ASC',
            ),
            "pagination" => [
                "cursor_sql" => new PreparedStatementQuery('raf.ref > ?', ['i', 0]),
            ],
            "additional_process" => static function ($record) {
                if (
                    !file_exists(
                        get_resource_path(
                            $record["resource"],
                            true,
                            "",
                            false,
                            $record["file_extension"],
                            true,
                            1,
                            false,
                            "",
                            $record["ref"]
                        )
                    )
                ) {
                    logScript("WARNING: unable to get original file for resource - alternative pair: #{$record["resource"]} - #{$record["ref"]}");
                    return false;
                }
                return $record;
            },
        ),
        array(
            "name" => "resource_node",
            "formatted_name" => "resource nodes",
            "filename" => "resource_nodes",
            "record_feedback" => array(),
            "sql" => array(
                "select" => "rn.resource, rn.node, rn.hit_count, rn.new_hit_count",
                "from" => "resource_node AS rn
                    RIGHT JOIN resource AS r ON rn.resource = r.ref
                    RIGHT JOIN node AS n ON rn.node = n.ref",
                "where" => "resource > 0
                    AND r.resource_type IN (SELECT ref FROM resource_type)
                    AND r.archive IN (SELECT `code` FROM archive_states)
                    AND (r.file_extension IS NOT NULL AND trim(r.file_extension) <> '')
                    AND (r.preview_extension IS NOT NULL AND trim(r.preview_extension) <> '')
                    AND n.resource_type_field IN (SELECT ref FROM resource_type_field)",
                "order_by" => 'resource ASC, node ASC',
            ),
            "pagination" => [
                "cursor_sql" => new PreparedStatementQuery(
                    '(
                        resource > ? 
                        OR (resource = ? AND node > ?)
                    )',
                    ['i', 0, 'i', 0, 'i', 0]
                ),
                "last_page_cursor_sql" => static function (PreparedStatementQuery $cursor, array $record): PreparedStatementQuery {
                    return new PreparedStatementQuery(
                        $cursor->sql,
                        [
                            'i',$record['resource'],
                            'i',$record['resource'],
                            'i',$record['node'],
                        ]
                    );
                },
            ],
        ),
        array(
            "name" => "resource_dimensions",
            "formatted_name" => "resource dimensions",
            "filename" => "resource_dimensions",
            "record_feedback" => array(),
            "sql" => array(
                "where" => "resource > 0 AND EXISTS(SELECT ref FROM resource WHERE ref = resource_dimensions.resource)",
                "order_by" => 'resource ASC',
            ),
            "pagination" => [
                "cursor_sql" => new PreparedStatementQuery('resource > ?', ['i', 0]),
                "last_page_cursor_sql" => static function (PreparedStatementQuery $cursor, array $record): PreparedStatementQuery {
                    return new PreparedStatementQuery($cursor->sql, ['i', $record['resource']]);
                },
            ],
        ),
        array(
            "name" => "resource_related",
            "formatted_name" => "resource related",
            "filename" => "resource_related",
            "record_feedback" => array(),
            "sql" => array(
                "where" => "resource > 0
                    AND EXISTS(SELECT ref FROM resource WHERE ref = resource_related.resource)
                    AND EXISTS(SELECT ref FROM resource WHERE ref = resource_related.related)",
                "order_by" => 'resource ASC, related ASC',
            ),
            "pagination" => [
                "cursor_sql" => new PreparedStatementQuery(
                    '(
                        resource > ? 
                        OR (resource = ? AND related > ?)
                    )',
                    ['i', 0, 'i', 0, 'i', 0]
                ),
                "last_page_cursor_sql" => static function (PreparedStatementQuery $cursor, array $record): PreparedStatementQuery {
                    return new PreparedStatementQuery(
                        $cursor->sql,
                        [
                            'i',$record['resource'],
                            'i',$record['resource'],
                            'i',$record['related'],
                        ]
                    );
                },
            ],
        ),
    );

    foreach ($tables as $table) {
        logScript("");
        logScript("Exporting {$table["formatted_name"]}...");

        $export_fh = $get_file_handler($folder_path . DIRECTORY_SEPARATOR . "{$table["filename"]}_export.json", "w+b");

        $select = isset($table["sql"]["select"]) && trim($table["sql"]["select"]) != "" ? $table["sql"]["select"] : "*";
        $from = isset($table["sql"]["from"]) && trim($table["sql"]["from"]) != "" ? $table["sql"]["from"] : $table["name"];
        $where = isset($table["sql"]["where"]) && trim($table["sql"]["where"]) != "" ? "WHERE {$table["sql"]["where"]}" : "";
        $additional_process = isset($table["additional_process"]) && is_callable($table["additional_process"]) ? $table["additional_process"] : null;

        // Keyset (cursor-based) pagination. Please note it requires a data set with deterministic order (the order_by
        // is tightly coupled with the cursor logic).
        $order_by = isset($table['sql']['order_by']) && trim($table['sql']['order_by']) != '' ? "{$table["sql"]["order_by"]}" : 'ref ASC';
        $cursor_sql = isset($table['pagination'], $table['pagination']['cursor_sql'])
            ? $table['pagination']['cursor_sql']
            : new PreparedStatementQuery('ref > ?', ['i', 0]);
        $cursor_sql->sql = $where === '' ? "WHERE {$cursor_sql->sql}" : "AND {$cursor_sql->sql}";
        $last_page_cursor = $cursor_sql;
        $last_page_cursor_key = isset($table['pagination'], $table['pagination']['last_page_cursor_sql'])
            ? $table['pagination']['last_page_cursor_sql']
            : static function (PreparedStatementQuery $cursor, array $record): PreparedStatementQuery {
                return new PreparedStatementQuery($cursor->sql, ['i', $record['ref']]);
            };
        $chunk_size = 5000;
        // end of pagination

        do {
            $records = ps_query(
                "SELECT {$select} FROM {$from} {$where} {$last_page_cursor->sql} ORDER BY {$order_by} LIMIT ?",
                array_merge($last_page_cursor->parameters, ['i', $chunk_size])
            );

            // Check if no records were found for the first page. Note: cursor key(s) will be zero.
            $cursor_key_values = array_filter(
                $last_page_cursor->parameters,
                static fn($_, $key) => $key % 2 === 1,
                ARRAY_FILTER_USE_BOTH
            );
            if (empty($records) && array_filter($cursor_key_values, is_positive_int_loose(...)) === []) {
                logScript("WARNING: no data found!");
                break;
            }

            // Process the current chunk
            foreach ($records as $record) {
                $last_page_cursor = $last_page_cursor_key($last_page_cursor, $record);

                // Sometimes you want to provide feedback to the user to let him/ her know a particular record is being processed
                if (isset($table["record_feedback"]) && !empty($table["record_feedback"])) {
                    $log_msg = $table["record_feedback"]["text"];
                    foreach ($table["record_feedback"]["placeholders"] as $placeholder) {
                        $log_msg = str_replace("%{$placeholder}", $record["{$placeholder}"], $log_msg);
                    }
                    logScript($log_msg);
                }

                if (!is_null($additional_process)) {
                    $record = $additional_process($record);

                    // additional processing might determine we don't want to process this record at all
                    if ($record === false) {
                        continue;
                    }
                }

                if ($dry_run) {
                    continue;
                }

                fwrite($export_fh, json_encode($record, JSON_NUMERIC_CHECK) . PHP_EOL);
            }
        } while (count($records) === $chunk_size);

        fclose($export_fh);
    }

    # ARCHIVE STATES
    ################
    logScript("");
    logScript("Exporting archive states...");

    $archive_states_export_fh = $get_file_handler($folder_path . DIRECTORY_SEPARATOR . "archive_states_export.json", "w+b");
    foreach (get_workflow_states() as $archive_state) {
        if (!isset($lang["status{$archive_state}"])) {
            logScript("Warning: language not set for archive state #{$archive_state}");
            continue;
        }
        $archive_state_text = $lang["status{$archive_state}"];

        logScript("Archive state '{$archive_state_text}' (ID #{$archive_state})");

        if ($dry_run) {
            continue;
        }

        $exported_archive_state = array(
            "ref"  => $archive_state,
            "lang" => $archive_state_text);

        fwrite($archive_states_export_fh, json_encode($exported_archive_state, JSON_NUMERIC_CHECK) . PHP_EOL);
    }
    fclose($archive_states_export_fh);
}

if ($import && isset($folder_path)) {
    if (!isset($spec_file_path) || trim($spec_file_path) == "") {
        logScript("ERROR: Specification file not provided or empty!");
        exit(1);
    }
    include_once $spec_file_path;

    // Quick spec file validation
    if (!in_array($custom_access_new_value_spec, array_diff(RESOURCE_ACCESS_TYPES, [RESOURCE_ACCESS_CUSTOM_GROUP]))) {
        logScript('ERROR: Specification file - invalid $custom_access_new_value_spec');
        exit(1);
    }

    /*
    progress.php is used to override the specification file that was provided as original input by keeping track of new
    mappings created and saving them in the progress file.
    */
    $progress_fp = $folder_path . DIRECTORY_SEPARATOR . "progress.php";
    $progress_fh = $get_file_handler($progress_fp, "a+b");
    $php_tag_found = false;
    while (($line = fgets($progress_fh)) !== false) {
        if (trim($line) != "" &&  mb_check_encoding($line, "UTF-8") && mb_strpos($line, "<?php") !== false) {
            $php_tag_found = true;
        }
    }
    if (!$php_tag_found || $clear_progress) {
        ftruncate($progress_fh, 0);
        fwrite($progress_fh, "<?php" . PHP_EOL);
    }
    include_once $progress_fp;

    define("TX_SAVEPOINT", "merge_rs_systems_import");
    if (!db_begin_transaction(TX_SAVEPOINT)) {
        logScript("ERROR: MySQL - unable to begin transaction!");
        exit(1);
    }
    db_end_transaction(TX_SAVEPOINT);

    $call_src_api = static function (string $function, array $data) use ($src_api) {
        $query = http_build_query(
            array_merge(
                [
                    'user' => $src_api['username'],
                    'language' => $GLOBALS['language'] ?? $GLOBALS['defaultlanguage'],
                    'function' => $function,
                ],
                $data
            )
        );
        logScript("[SRC API] Query: {$query}");
        $sign = hash('sha256', $src_api['key'] . $query);
        $response = file_get_contents(
            "{$src_api['base_url']}/api/?$query&sign=$sign",
            false,
            stream_context_create([
                'http' => array_merge(
                    $src_api['http_options'],
                    [
                        'ignore_errors' => true,
                        'user_agent' => sprintf('ResourceSpace-Merge-Script/1.0 (%s)', $GLOBALS['baseurl']),
                    ]
                ),
            ])
        );
        $status_code = preg_match('/\d{3}/', $http_response_header[0], $match) ? (int) $match[0] : 0;
        $results = json_decode($response, true);

        if ($status_code === 401) {
            throw new RuntimeException('Unauthorised');
        } elseif ($status_code !== 200 && JSON_ERROR_NONE !== json_last_error()) {
            // Handle generic fails (simple string responses)
            throw new RuntimeException($response);
        } elseif ($status_code !== 200 && isset($results['error']['detail'])) {
            // Handle generic (structured) errors (usually done using ajax_functions.php)
            throw new RuntimeException($results['error']['detail']);
        } elseif ($status_code === 200 && JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('(JSON) ' . json_last_error_msg());
        } else {
            return $results;
        }
    };

    // Check API credentials. Any errors require further investigation before moving forward.
    logScript('Checking the SRC API details');
    try {
        $call_src_api('get_system_status', []);
    } catch (RuntimeException $e) {
        logScript("ERROR: Bad SRC API details! Response: {$e->getMessage()}");
        exit(1);
    }

    # USER GROUPS
    #############
    logScript("");
    logScript("Importing user groups...");
    if (!isset($usergroups_spec) || empty($usergroups_spec)) {
        logScript("ERROR: Spec missing 'usergroups_spec'");
        exit(1);
    }
    $processed_usergroups = (isset($processed_usergroups) ? $processed_usergroups : array());
    $usergroups_not_created = (isset($usergroups_not_created) ? $usergroups_not_created : array());
    $src_usergroups = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "usergroups_export.json", "r+b"));
    $dest_usergroups = get_usergroups(false, "", true);
    $process_usergroup_preferences = static function ($usergroup_ref, $usergroup_data) {
        db_begin_transaction(TX_SAVEPOINT);
        if (isset($usergroup_data["usergroup_preferences"]) && is_array($usergroup_data["usergroup_preferences"]) && !empty($usergroup_data["usergroup_preferences"])) {
            logScript("Processing user group preferences (if no warning is showing, this is ok)");
            foreach ($usergroup_data["usergroup_preferences"] as $usergroup_p) {
                if (!set_usergroup_config_option($usergroup_ref, $usergroup_p["parameter"], $usergroup_p["value"])) {
                    logScript("ERROR: unable to save user group preference: {$usergroup_p["parameter"]} = '{$usergroup_p["value"]}'");
                    exit(1);
                }
            }
        }
        db_end_transaction(TX_SAVEPOINT);
    };

    foreach ($src_usergroups as $src_ug) {
        if (in_array($src_ug["ref"], $processed_usergroups) || in_array($src_ug["ref"], $usergroups_not_created)) {
            continue;
        }

        logScript("Processing {$src_ug["name"]} (ID #{$src_ug["ref"]})...");
        if (!array_key_exists($src_ug["ref"], $usergroups_spec)) {
            logScript("WARNING: Specification for usergroups does not contain a mapping for this group! Skipping");
            $usergroups_not_created[] = $src_ug["ref"];
            fwrite($progress_fh, "\$usergroups_not_created[] = {$src_ug["ref"]};" . PHP_EOL);
            continue;
        }

        $spec_cfg_value = $usergroups_spec[$src_ug["ref"]];
        if (is_numeric($spec_cfg_value) && $spec_cfg_value > 0 && array_key_exists($spec_cfg_value, $dest_usergroups)) {
            logScript("Found direct 1:1 mapping to '{$dest_usergroups[$spec_cfg_value]}' (ID #{$spec_cfg_value})... Skipping");
            $processed_usergroups[] = $src_ug["ref"];
            fwrite($progress_fh, "\$processed_usergroups[] = {$src_ug["ref"]};" . PHP_EOL);
        } elseif (is_array($spec_cfg_value)) {
            if (!isset($spec_cfg_value["create"])) {
                logScript("ERROR: usergroup specification config value is invalid. Required keys: create - true/false");
                exit(1);
            }

            if ((bool) !$spec_cfg_value["create"]) {
                logScript("Skipping usergroup as per the specification record");
                $usergroups_not_created[] = $src_ug["ref"];
                fwrite($progress_fh, "\$usergroups_not_created[] = {$src_ug["ref"]};" . PHP_EOL);
                continue;
            }

            db_begin_transaction(TX_SAVEPOINT);
            $new_ug_ref = save_usergroup(0, array('name' => $src_ug["name"], 'request_mode' => 1));
            log_activity(null, LOG_CODE_CREATED, null, 'usergroup', null, $new_ug_ref);
            log_activity(null, LOG_CODE_CREATED, $src_ug["name"], 'usergroup', 'name', $new_ug_ref, null, '');
            log_activity(null, LOG_CODE_CREATED, '1', 'usergroup', 'request_mode', $new_ug_ref, null, '');

            $process_usergroup_preferences($new_ug_ref, $src_ug);

            logScript("Created new user group '{$src_ug["name"]}' (ID #{$new_ug_ref})");
            $usergroups_spec[$src_ug["ref"]] = $new_ug_ref;
            $processed_usergroups[] = $src_ug["ref"];
            fwrite(
                $progress_fh,
                "\$usergroups_spec[{$src_ug["ref"]}] = {$new_ug_ref};"
                . PHP_EOL
                . "\$processed_usergroups[] = {$src_ug["ref"]};"
                . PHP_EOL
            );
        } else {
            logScript("ERROR: Invalid usergroup specification record for key #{$src_ug["ref"]}");
            exit(1);
        }
    }
    unset($src_usergroups);
    unset($dest_usergroups);

    # USERS & USER PREFERENCES
    ##########################
    logScript("");
    logScript("Importing users and their preferences...");
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $usernames_mapping = (isset($usernames_mapping) ? $usernames_mapping : array());
    $users_not_created = (isset($users_not_created) ? $users_not_created : array());
    $src_users = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "users_export.json", "r+b"));
    $process_user_preferences = function ($user_ref, $user_data) use ($progress_fh, &$usernames_mapping) {
        db_begin_transaction(TX_SAVEPOINT);
        if (isset($user_data["user_preferences"]) && is_array($user_data["user_preferences"]) && !empty($user_data["user_preferences"])) {
            logScript("Processing user preferences (if no warning is showing, this is ok)");
            foreach ($user_data["user_preferences"] as $user_p) {
                if (!set_config_option($user_ref, $user_p["parameter"], $user_p["value"])) {
                    logScript("ERROR: unable to save user preference: {$user_p["parameter"]} = '{$user_p["value"]}'");
                    exit(1);
                }
            }
        }

        $usernames_mapping[$user_data["ref"]] = $user_ref;
        fwrite($progress_fh, "\$usernames_mapping[{$user_data["ref"]}] = {$user_ref};" . PHP_EOL);
        db_end_transaction(TX_SAVEPOINT);
    };
    foreach ($src_users as $user) {
        if (array_key_exists($user["ref"], $usernames_mapping) || in_array($user["ref"], $users_not_created)) {
            continue;
        }

        if (
            (($found_udata = get_user((int) get_user_by_username($user['username']))) && $found_udata !== false)
            || (
                $user['email'] != ''
                && ($found_udata = get_user((int) get_user_by_username($user['email'])))
                && $found_udata !== false
            )
        ) {
            logScript("Username '{$user["username"]}' found in current system as '{$found_udata["username"]}', full name '{$found_udata["fullname"]}'");
            $process_user_preferences($found_udata['ref'], $user);
            continue;
        }

        if (in_array($user["usergroup"], $usergroups_not_created)) {
            logScript("WARNING: User '{$user["username"]}' belongs to a user group that was not created as per the specification file. Skipping");
            $users_not_created[] = $user["ref"];
            fwrite($progress_fh, "\$users_not_created[] = {$user["ref"]};" . PHP_EOL);
            continue;
        }

        db_begin_transaction(TX_SAVEPOINT);
        $new_uref = new_user($user["username"], $usergroups_spec[$user["usergroup"]]);
        if ($new_uref <= 0) {
            logScript("ERROR: User could not be created - check user limit or user name conflicts.");
            exit(1);
        }
        logScript("Created new user '{$user["username"]}' (ID #{$new_uref} | User group ID: {$usergroups_spec[$user["usergroup"]]})");

        $valid_user_tbl_cols = array_diff(columns_in('user', null, null, true), ['ref']);
        $save_user_stm = new PreparedStatementQuery();
        $log_act_user_edit = [];
        foreach ($user as $col => $value) {
            if (!in_array($col, $valid_user_tbl_cols)) {
                continue;
            }

            $save_user_stm->sql .= ($save_user_stm->sql !== '' ? ', ' : '') . "`{$col}` = ?";
            $save_user_stm->parameters[] = is_int_loose($value) ? 'i' : 's';
            $save_user_stm->parameters[] = $value;

            $log_act_user_edit[$col] = $value;
        }

        if ($save_user_stm->sql !== '') {
            ps_query(
                "UPDATE `user` SET {$save_user_stm->sql} WHERE ref = ?",
                [...$save_user_stm->parameters, 'i', $new_uref]
            );
            foreach ($log_act_user_edit as $col => $value) {
                log_activity(null, LOG_CODE_EDITED, $value, 'user', $col, $new_uref);
            }
            logScript("Saved user details");
        } else {
            logScript("ERROR: failed to save user '{$user["username"]}'");
            exit(1);
        }

        $process_user_preferences($new_uref, $user);
    }
    unset($src_users);

    # ARCHIVE STATES
    ################
    logScript("");
    logScript("Importing archive states...");
    if (!isset($archive_states_spec) || empty($archive_states_spec)) {
        logScript("ERROR: Spec missing 'archive_states_spec'");
        exit(1);
    }
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $processed_archive_states = (isset($processed_archive_states) ? $processed_archive_states : array());
    $src_archive_states = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "archive_states_export.json", "r+b"));
    $dest_archive_states = get_workflow_states();
    foreach ($src_archive_states as $archive_state) {
        if (in_array($archive_state["ref"], $processed_archive_states)) {
            continue;
        }

        logScript("Processing '{$archive_state["lang"]}' (ID #{$archive_state["ref"]})");
        if (
            array_key_exists($archive_state["ref"], $archive_states_spec)
            && in_array($archive_states_spec[$archive_state["ref"]], $dest_archive_states)
            && !is_null($archive_states_spec[$archive_state["ref"]])
        ) {
            $lang_text = $lang["status{$archive_states_spec[$archive_state["ref"]]}"];
            logScript("Found direct 1:1 mapping to #{$archive_states_spec[$archive_state["ref"]]} - {$lang_text}");

            $processed_archive_states[] = $archive_state["ref"];
            fwrite($progress_fh, "\$processed_archive_states[] = {$archive_state["ref"]};" . PHP_EOL);

            continue;
        } elseif (
            array_key_exists($archive_state["ref"], $archive_states_spec)
            && !in_array($archive_states_spec[$archive_state["ref"]], $dest_archive_states)
        ) {
            logScript("ERROR: Incorrect mapping? Attempted to map to workflow state #{$archive_states_spec[$archive_state["ref"]]}!");
            exit(1);
        }

        if (array_key_exists($archive_state["ref"], $archive_states_spec) && is_null($archive_states_spec[$archive_state["ref"]])) {
            $new_archive_state = rse_workflow_create_state(['name' => $archive_state['lang']]);
            if ($new_archive_state === false) {
                logScript("ERROR: Unable to create new workflow state!");
                exit(1);
            }
            $dest_archive_states[] = $new_archive_state['code'];
            $processed_archive_states[] = $archive_state['ref'];
            fwrite($progress_fh, "\$processed_archive_states[] = {$archive_state['ref']};" . PHP_EOL);
            logScript("Created new workflow state with code #{$new_archive_state['code']}");
        }
    }
    unset($src_archive_states);
    clear_query_cache('workflow');

    # RESOURCE TYPES
    ################
    logScript("");
    logScript("Importing resource types...");
    if (!isset($resource_types_spec) || empty($resource_types_spec)) {
        logScript("ERROR: Spec missing 'resource_types_spec'");
        exit(1);
    }
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $processed_resource_types = (isset($processed_resource_types) ? $processed_resource_types : array());
    $src_resource_types = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "resource_types_export.json", "r+b"));
    $dest_resource_types = get_resource_types("", false);
    foreach ($src_resource_types as $resource_type) {
        if (in_array($resource_type["ref"], $processed_resource_types)) {
            continue;
        }

        logScript("Processing #{$resource_type["ref"]} '{$resource_type["name"]}'");

        if (!array_key_exists($resource_type["ref"], $resource_types_spec)) {
            logScript("ERROR: resource_types_spec does not have a record for this resource type");
            exit(1);
        }

        if (!is_null($resource_types_spec[$resource_type["ref"]])) {
            if (!is_numeric($resource_types_spec[$resource_type["ref"]])) {
                logScript("ERROR: Invalid mapped value!");
                exit(1);
            }

            $found_rt_index = array_search($resource_types_spec[$resource_type["ref"]], array_column($dest_resource_types, "ref"));
            if ($found_rt_index === false) {
                logScript("ERROR: Unable to find destination resource type!");
                exit(1);
            }

            $found_rt = $dest_resource_types[$found_rt_index];
            logScript("Found direct 1:1 mapping to #{$found_rt["ref"]} '{$found_rt["name"]}'");

            $processed_resource_types[] = $resource_type["ref"];
            fwrite($progress_fh, "\$processed_resource_types[] = {$resource_type["ref"]};" . PHP_EOL);

            continue;
        }

        // New record
        ps_query(
            "INSERT INTO resource_type(`name`, config_options, allowed_extensions, tab_name, push_metadata) VALUES (?, ?, ?, ?, ?, ?);",
            [
                's', trim($resource_type["name"]) != "" ? $resource_type["name"] : null,
                's', trim($resource_type["config_options"]) != "" ? $resource_type["config_options"] : null,
                's', trim($resource_type["allowed_extensions"]) != "" ? $resource_type["allowed_extensions"] : null,
                's', trim($resource_type["tab_name"]) != "" ? $resource_type["tab_name"] : null,
                'i', trim($resource_type["push_metadata"]) != "" ? $resource_type["push_metadata"] : null,
            ]
        );
        $new_rt_ref = sql_insert_id();

        log_activity(null, LOG_CODE_EDITED, $resource_type["name"], 'resource_type', 'name', $new_rt_ref);
        log_activity(null, LOG_CODE_EDITED, $resource_type["config_options"], 'resource_type', 'config_options', $new_rt_ref);
        log_activity(null, LOG_CODE_EDITED, $resource_type["allowed_extensions"], 'resource_type', 'allowed_extensions', $new_rt_ref);
        log_activity(null, LOG_CODE_EDITED, $resource_type["tab_name"], 'resource_type', 'tab_name', $new_rt_ref);
        log_activity(null, LOG_CODE_EDITED, $resource_type["push_metadata"], 'resource_type', 'push_metadata', $new_rt_ref);

        logScript("Created new record #{$new_rt_ref} '{$resource_type["name"]}'");
        $resource_types_spec[$resource_type["ref"]] = $new_rt_ref;
        $processed_resource_types[] = $resource_type["ref"];
        fwrite(
            $progress_fh,
            "\$resource_types_spec[{$resource_type["ref"]}] = {$new_rt_ref};"
            . PHP_EOL
            . "\$processed_resource_types[] = {$resource_type["ref"]};"
            . PHP_EOL
        );
    }
    unset($src_resource_types);
    unset($dest_resource_types);

    # RESOURCE TYPE FIELDS
    ######################
    logScript("");
    logScript("Importing resource type fields...");
    if (!isset($resource_type_fields_spec) || empty($resource_type_fields_spec)) {
        logScript("ERROR: Spec missing 'resource_type_fields_spec'");
        exit(1);
    }
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $processed_resource_type_fields = (isset($processed_resource_type_fields) ? $processed_resource_type_fields : array());
    $resource_type_fields_not_created = (isset($resource_type_fields_not_created) ? $resource_type_fields_not_created : array());
    $src_resource_type_fields = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "resource_type_fields_export.json", "r+b"));
    $dest_resource_type_fields = get_resource_type_fields("", "ref", "ASC", "", array());
    $compatible_rtf_types = array(
        FIELD_TYPE_TEXT_BOX_SINGLE_LINE => $TEXT_FIELD_TYPES,
        FIELD_TYPE_TEXT_BOX_MULTI_LINE => $TEXT_FIELD_TYPES,
        FIELD_TYPE_CHECK_BOX_LIST => $FIXED_LIST_FIELD_TYPES,
        FIELD_TYPE_DROP_DOWN_LIST => $FIXED_LIST_FIELD_TYPES,
        FIELD_TYPE_DATE_AND_OPTIONAL_TIME => $DATE_FIELD_TYPES,
        FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE => $TEXT_FIELD_TYPES,
        FIELD_TYPE_EXPIRY_DATE => array(FIELD_TYPE_EXPIRY_DATE),
        FIELD_TYPE_CATEGORY_TREE => $FIXED_LIST_FIELD_TYPES,
        FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE => array(FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE),
        FIELD_TYPE_DYNAMIC_KEYWORDS_LIST => $FIXED_LIST_FIELD_TYPES,
        FIELD_TYPE_DATE => $DATE_FIELD_TYPES,
        FIELD_TYPE_RADIO_BUTTONS => $FIXED_LIST_FIELD_TYPES,
        FIELD_TYPE_WARNING_MESSAGE => array(FIELD_TYPE_WARNING_MESSAGE),
        FIELD_TYPE_DATE_RANGE => array(FIELD_TYPE_DATE_RANGE)
    );
    foreach ($src_resource_type_fields as $src_rtf) {
        if (in_array($src_rtf["ref"], $processed_resource_type_fields) || in_array($src_rtf["ref"], $resource_type_fields_not_created)) {
            continue;
        }

        logScript("Processing #{$src_rtf["ref"]} '{$src_rtf["title"]}'");

        if (!array_key_exists($src_rtf["ref"], $resource_type_fields_spec)) {
            logScript("WARNING: Specification missing mapping for this resource type field! Skipping");
            $resource_type_fields_not_created[] = $src_rtf["ref"];
            fwrite($progress_fh, "\$resource_type_fields_not_created[] = {$src_rtf["ref"]};" . PHP_EOL);
            continue;
        }

        // Check if we need to create this field
        if (!(isset($resource_type_fields_spec[$src_rtf["ref"]]["create"]) && is_bool($resource_type_fields_spec[$src_rtf["ref"]]["create"]))) {
            logScript("ERROR: invalid mapping configuration for mapped value. Expecting array type with index 'create' of type boolean.");
            exit(1);
        }
        if (!$resource_type_fields_spec[$src_rtf["ref"]]["create"]) {
            logScript("Mapping set to not be created. Skipping");
            $resource_type_fields_not_created[] = $src_rtf["ref"];
            fwrite($progress_fh, "\$resource_type_fields_not_created[] = {$src_rtf["ref"]};" . PHP_EOL);
            continue;
        }

        /*
        Check if we have a field mapped. Expected values:
            - integer when we have a direct mapping
            - null when a new field should be created
        */
        if (
            !(
                (
                    isset($resource_type_fields_spec[$src_rtf["ref"]]["ref"])
                    && (
                            is_int($resource_type_fields_spec[$src_rtf["ref"]]["ref"])
                            && $resource_type_fields_spec[$src_rtf["ref"]]["ref"] > 0
                        )
                )
                || is_null($resource_type_fields_spec[$src_rtf["ref"]]["ref"])
            )
        ) {
            logScript("ERROR: invalid mapping configuration for mapped value. Expecting array type with index 'ref' of type integer OR use 'null' to create new field.");
            exit(1);
        }
        $mapped_rtf_ref = $resource_type_fields_spec[$src_rtf["ref"]]["ref"];

        // This is merged as a new field
        if (is_null($mapped_rtf_ref)) {
            $db_rtf_known_columns = array_column(ps_query('DESCRIBE resource_type_field', [], -1, false), 'Field');

            db_begin_transaction(TX_SAVEPOINT);
            $new_rtf_ref = create_resource_type_field(
                $src_rtf["title"],
                $resource_types_spec[$src_rtf["resource_type"]],
                $src_rtf["type"],
                $src_rtf["name"],
                $src_rtf["keywords_index"]
            );

            if ($new_rtf_ref === false) {
                logScript("ERROR: unable to create new resource type field!");
                exit(1);
            }

            // IMPORTANT: we explicitly don't escape SQL values in this case as this should be the exact value stored in the SRC DB
            $sql = "";
            foreach ($src_rtf as $column => $value) {
                if (
                    // SRC may be very old and contain columns no longer in use which are unavailable on DEST
                    !in_array($column, $db_rtf_known_columns)

                    // Ignore columns that have been used for creating this field
                    || in_array($column, array("ref", "name", "title", "type", "keywords_index", "resource_type"))
                ) {
                    continue;
                }

                if (trim($sql) != "") {
                    $sql .= ", ";
                }

                $col_val = trim($value) == "" ? null : $value;
                $sql .= "`{$column}` = ?";
                $sql_params[] = 'i';
                $sql_params[] = $col_val;
                log_activity(null, LOG_CODE_EDITED, $col_val, 'resource_type_field', $column, $new_rtf_ref);
            }
            $sql_params[] = 'i';
            $sql_params[] = $new_rtf_ref;
            ps_query("UPDATE resource_type_field SET {$sql} WHERE ref = ?", $sql_params);

            logScript("Created new record #{$new_rtf_ref} '{$src_rtf["title"]}'");
            $resource_type_fields_spec[$src_rtf["ref"]] = array("create" => true, "ref" => $new_rtf_ref);
            $processed_resource_type_fields[] = $src_rtf["ref"];
            fwrite(
                $progress_fh,
                "\$resource_type_fields_spec[{$src_rtf["ref"]}] = array(\"create\" => true, \"ref\" => {$new_rtf_ref});"
                . PHP_EOL
                . "\$processed_resource_type_fields[] = {$src_rtf["ref"]};"
                . PHP_EOL
            );

            $new_rtf_data = $src_rtf;
            $new_rtf_data["ref"] = $new_rtf_ref;
            $new_rtf_data["resource_type"] = $resource_types_spec[$src_rtf["resource_type"]];
            $dest_resource_type_fields[] = $new_rtf_data;

            unset($new_rtf_ref);
            unset($new_rtf_data);
            db_end_transaction(TX_SAVEPOINT);
            continue;
        }

        $found_rtf_index = array_search($mapped_rtf_ref, array_column($dest_resource_type_fields, "ref"));
        if ($found_rtf_index === false) {
            logScript("ERROR: Unable to find destination resource type field!");
            exit(1);
        }
        $found_rtf = $dest_resource_type_fields[$found_rtf_index];
        logScript("Found direct 1:1 mapping to #{$found_rtf["ref"]} '{$found_rtf["title"]}'");

        if (!in_array($found_rtf["type"], $compatible_rtf_types[$src_rtf["type"]])) {
            $compat_rtf_type_names_msg = '';
            foreach ($compatible_rtf_types[$found_rtf["type"]] as $compatible_rtf_type) {
                $compat_rtf_type_names_msg .= PHP_EOL . " - {$lang[$field_types[$compatible_rtf_type]]}";
            }
            logScript("ERROR: incompatible types! Consider mapping to a field with one of these types: {$compat_rtf_type_names_msg}");
            exit(1);
        }

        $processed_resource_type_fields[] = $src_rtf["ref"];
        fwrite($progress_fh, "\$processed_resource_type_fields[] = {$src_rtf["ref"]};" . PHP_EOL);

        if ($src_rtf["type"] == FIELD_TYPE_CATEGORY_TREE && $found_rtf["type"] != FIELD_TYPE_CATEGORY_TREE) {
            logScript("WARNING: SRC field is a category type and DEST field is a different fixed list type. THIS WILL FLATTEN THE CATEGORY TREE!");
        }
    }
    unset($src_resource_type_fields);

    # NODES
    #######
    logScript("");
    logScript("Importing nodes...");
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $nodes_spec = $nodes_spec ?? [];
    $new_nodes_mapping = $new_nodes_mapping ?? [];
    $nodes_not_created = $nodes_not_created ?? [];
    $src_nodes = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "nodes_export.json", "r+b"));
    $dest_node_refs = ps_array('SELECT ref AS `value` FROM node', array());
    foreach ($src_nodes as $src_node) {
        if (array_key_exists($src_node['ref'], $new_nodes_mapping) || in_array($src_node['ref'], $nodes_not_created)) {
            continue;
        }

        logScript("Processing #{$src_node["ref"]} '{$src_node["name"]}'");

        // Check if the specification has a mapping defined for this node to other DEST node(s).
        if (isset($nodes_spec[$src_node['ref']])) {
            if (!is_array($nodes_spec[$src_node['ref']])) {
                logScript('ERROR: Invalid nodes specification! Reason: expected a list of nodes, received type is ' . gettype($nodes_spec[$src_node['ref']]));
                exit(1);
            }

            $nodes_spec[$src_node['ref']] = array_filter($nodes_spec[$src_node['ref']], 'is_int_loose');
            if (empty($nodes_spec[$src_node['ref']])) {
                logScript('ERROR: Invalid nodes specification! Reason: no mapping defined.');
                exit(1);
            }

            // Safe check: error if any of the mappings for this node is invalid (ie ref doesn't exist).
            $found_invalid_nodes_map = array_diff($nodes_spec[$src_node['ref']], $dest_node_refs);
            if (!empty($found_invalid_nodes_map)) {
                logScript('ERROR: Invalid DEST node(s) mapping found: ' . implode(', ', $found_invalid_nodes_map));
                exit(1);
            }

            logScript('Found direct mapping to DEST node(s): ' . implode(', ', $nodes_spec[$src_node['ref']]));
            continue;
        }

        // If this nodes' resource type field was specified to not be created on the DEST system (via $resource_type_fields_spec), skip it
        if (in_array($src_node["resource_type_field"], $resource_type_fields_not_created)) {
            logScript("Skipping as resource type field was not created on the destination system!");
            $nodes_not_created[] = $src_node["ref"];
            fwrite($progress_fh, "\$nodes_not_created[] = {$src_node["ref"]};" . PHP_EOL);
            continue;
        }

        $mapped_rtf_ref = $resource_type_fields_spec[$src_node["resource_type_field"]]["ref"];
        $found_rtf_index = array_search($mapped_rtf_ref, array_column($dest_resource_type_fields, "ref"));
        if ($found_rtf_index === false) {
            logScript("ERROR: Unable to find destination resource type field!");
            exit(1);
        }
        $found_rtf = $dest_resource_type_fields[$found_rtf_index];

        // Determine parent node
        if (
            $found_rtf["type"] == FIELD_TYPE_CATEGORY_TREE
            && (!is_null($src_node["parent"]) || trim($src_node["parent"]) != "")
            && in_array($src_node["parent"], $nodes_not_created)
        ) {
            logScript("ERROR: Unable to create new node because its parent was not created!");
            exit(1);
        } elseif (
            $found_rtf["type"] == FIELD_TYPE_CATEGORY_TREE
            && (!is_null($src_node["parent"]) || trim($src_node["parent"]) != "")
            && !in_array($src_node["parent"], $nodes_not_created)
            && isset($new_nodes_mapping[$src_node["parent"]])
        ) {
            $node_parent = $new_nodes_mapping[$src_node["parent"]];
            $node_parent_sql = ' AND parent = ?';
            $node_parent_sql_params = ['i', $node_parent];
        } else {
            $node_parent = null;
            $node_parent_sql = '';
            $node_parent_sql_params = [];
        }

        // Check if we can find an existing node for this metadata field (taking into account language translations as well)
        $all_nodes_for_rtf = ps_query(
            "SELECT ref, `name` FROM node WHERE resource_type_field = ?{$node_parent_sql}",
            array_merge(['i', $mapped_rtf_ref], $node_parent_sql_params)
        );
        $found_matching_node_i18n = get_node_by_name($all_nodes_for_rtf, $src_node['name'], true);
        if (!empty($found_matching_node_i18n)) {
            logScript("Found matching node after translation: '{$found_matching_node_i18n['name']}'");
            $new_nodes_mapping[$src_node['ref']] = $found_matching_node_i18n['ref'];
            fwrite($progress_fh, "\$new_nodes_mapping[{$src_node['ref']}] = {$found_matching_node_i18n['ref']};" . PHP_EOL);
        } else {
            db_begin_transaction(TX_SAVEPOINT);
            $new_node_ref = set_node(null, $mapped_rtf_ref, $src_node["name"], $node_parent, "");
            if ($new_node_ref === false) {
                logScript("ERROR: unable to create new node!");
                exit(1);
            }

            logScript("Created new node record #{$new_node_ref} '{$src_node["name"]}'. Node set for resource type field {$mapped_rtf_ref}");
            $new_nodes_mapping[$src_node["ref"]] = $new_node_ref;
            fwrite($progress_fh, "\$new_nodes_mapping[{$src_node["ref"]}] = {$new_node_ref};" . PHP_EOL);
            db_end_transaction(TX_SAVEPOINT);
        }
    }
    unset($src_nodes, $dest_node_refs, $all_nodes_for_rtf);

    # RESOURCES
    ###########
    logScript("");
    logScript("Importing resources...");
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $resources_mapping = (isset($resources_mapping) ? $resources_mapping : array());
    $src_resources = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "resources_export.json", "r+b"));
    foreach ($src_resources as $src_resource) {
        if (array_key_exists($src_resource["ref"], $resources_mapping)) {
            continue;
        }

        logScript("Processing #{$src_resource["ref"]} | resource_type: {$src_resource["resource_type"]} | archive: {$src_resource["archive"]} | created_by: {$src_resource["created_by"]}");

        if (
            !array_key_exists($src_resource["archive"], $archive_states_spec)
            || !in_array($archive_states_spec[$src_resource["archive"]], $dest_archive_states)
        ) {
            logScript("ERROR: Invalid resource archive state! Please check archive_states_spec or dest_archive_states.");
            exit(1);
        }

        $created_by = $userref ?? -1;
        if (!in_array($src_resource["created_by"], $users_not_created) && isset($usernames_mapping[$src_resource["created_by"]])) {
            $created_by = $usernames_mapping[$src_resource["created_by"]];
        }

        db_begin_transaction(TX_SAVEPOINT);
        $new_resource_ref = create_resource(
            $resource_types_spec[$src_resource["resource_type"]],
            $archive_states_spec[$src_resource["archive"]],
            $created_by,
            $lang["createdfrommergerssystems"]
        );

        if ($new_resource_ref === false) {
            logScript("ERROR: unable to create new resource!");
            exit(1);
        }

        // Move across the resource access. Custom access gets remapped.
        if (in_array($src_resource['access'], RESOURCE_ACCESS_TYPES)) {
            $new_resource_acccess = $src_resource['access'] == RESOURCE_ACCESS_CUSTOM_GROUP ? $custom_access_new_value_spec : $src_resource['access'];
        } else {
            logScript("ERROR: unknown resource access type - '{$src_resource['access']}'");
            exit(1);
        }

        $new_resource_data = [
            'creation_date' => $src_resource['creation_date'] ?: null,
            'rating' => $src_resource['rating'] !== '' ? (int) $src_resource['rating'] : null,
            'user_rating' => $src_resource['user_rating'] !== '' ? (int) $src_resource['user_rating'] : null,
            'access' => (int) $new_resource_acccess,
            'mapzoom' => $src_resource['mapzoom'] !== '' ? (int) $src_resource['mapzoom'] : null,
            'modified' => $src_resource['modified'] ?: null,
            'geo_lat' => $src_resource['geo_lat'] !== '' ? $src_resource['geo_lat'] : null,
            'geo_long' => $src_resource['geo_long'] !== '' ? $src_resource['geo_long'] : null,
        ];
        $new_resource_data = array_diff($new_resource_data, array_filter($new_resource_data, 'is_null'));
        if (!empty($new_resource_data) && !put_resource_data($new_resource_ref, $new_resource_data)) {
            logScript("ERROR: unable to update the new resource properties!");
            exit(1);
        }

        try {
            $src_file_url = $call_src_api(
                'get_resource_path',
                [
                    'ref' => $src_resource['ref'],
                    'size' => '',
                    'generate' => 0,
                    'extension' => $src_resource['file_extension'],
                ]
            );
        } catch (RuntimeException $e) {
            logScript("Response: {$e->getMessage()}");
            $src_file_url = '';
        }

        if ($src_file_url === '') {
            logScript('ERROR: unable to fetch the original SRC file!');
            exit(1);
        }

        // we don't want to extract, revert or autorotate. This is a basic file pull into the DEST system from a remote SRC
        $job_data = array(
            "resource" => $new_resource_ref,
            "extract" => false,
            "revert" => false,
            "autorotate" => false,
            "upload_file_by_url" => $src_file_url,
        );
        $job_code = "merge_rs_systems_{$src_resource["ref"]}_{$new_resource_ref}_" . md5("{$src_resource["ref"]}_{$new_resource_ref}");
        $job_failure_lang = "Merge RS systems - upload processing fail "
            . str_replace(
                array('%ref', '%title'),
                array($new_resource_ref, ""),
                $lang["ref-title"]
            );
        $job_queue_added = job_queue_add("upload_processing", $job_data, $userref, "", "", $job_failure_lang, $job_code);
        if ($job_queue_added === false) {
            logScript("ERROR: unable to create job queue for uploading (copying) resource original file from SRC system");
            exit(1);
        } elseif (is_string($job_queue_added) && trim($job_queue_added) != "") {
            logScript("ERROR: unable to create job queue. Reason: '{$job_queue_added}'");
            exit(1);
        }

        resource_log(
            $new_resource_ref,
            LOG_CODE_SYSTEM,
            "",
            "merge_rs_systems: SRC resource ref was #{$src_resource["ref"]}",
            $src_resource["ref"],
            $new_resource_ref
        );

        // If configured, also store the SRC resource ID in a text field
        if ($rtf_src_resource_ref > 0) {
            $rtf_src_resource_ref_data = get_resource_type_field($rtf_src_resource_ref);
            $ursrr_errors = [];
            if (
                $rtf_src_resource_ref_data !== false
                && $rtf_src_resource_ref_data['type'] == FIELD_TYPE_TEXT_BOX_SINGLE_LINE
                && !update_field($new_resource_ref, $rtf_src_resource_ref, $src_resource['ref'], $ursrr_errors)
            ) {
                logScript("WARNING: unable to update the new resource and store the source resource ID! Reason: " . implode('; ', $ursrr_errors));
            }
        }

        logScript("Created new record #{$new_resource_ref}");
        $resources_mapping[$src_resource["ref"]] = $new_resource_ref;
        fwrite($progress_fh, "\$resources_mapping[{$src_resource["ref"]}] = {$new_resource_ref};" . PHP_EOL);
        db_end_transaction(TX_SAVEPOINT);
    }

    // If specification is configured to add certain nodes to all imported resources, do it now
    if (!empty($nodes_applied_to_all_merged_resources)) {
        if (
            !isset($processed_nodes_applied_to_all_merged_resources)
            && add_resource_nodes_multi(array_values($resources_mapping), $nodes_applied_to_all_merged_resources, false, true)
        ) {
            logScript('Updated all resources with the following SRC node IDs: ' . implode(', ', $nodes_applied_to_all_merged_resources));
            fwrite($progress_fh, '$processed_nodes_applied_to_all_merged_resources = true;' . PHP_EOL);
        } elseif (isset($processed_nodes_applied_to_all_merged_resources)) {
            logScript('Nodes that should be applied to all merged resources have already been added. Skipping');
        } else {
            logScript('ERROR: Failed to update all resources with the following SRC node IDs: ' . implode(', ', $nodes_applied_to_all_merged_resources));
            exit(1);
        }
    }
    unset($src_resources);

    # RESOURCE NODES
    ################
    logScript("");
    logScript("Importing resource nodes...");
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $processed_resource_nodes = (isset($processed_resource_nodes) ? $processed_resource_nodes : array());
    $src_resource_nodes = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "resource_nodes_export.json", "r+b"));
    foreach ($src_resource_nodes as $src_rn) {
        if (in_array("{$src_rn["resource"]}_{$src_rn["node"]}", $processed_resource_nodes)) {
            continue;
        }

        logScript("Processing SRC resource #{$src_rn["resource"]} and node #{$src_rn["node"]}");

        if (!array_key_exists($src_rn["resource"], $resources_mapping)) {
            logScript("WARNING: Unable to find a resource mapping. Skipping");
            $processed_resource_nodes[] = "{$src_rn["resource"]}_{$src_rn["node"]}";
            fwrite($progress_fh, "\$processed_resource_nodes[] = \"{$src_rn["resource"]}_{$src_rn["node"]}\";" . PHP_EOL);
            continue;
        }

        // If specification defined a mapping for a SRC node, apply it
        if (isset($nodes_spec[$src_rn['node']])) {
            if (add_resource_nodes($resources_mapping[$src_rn['resource']], $nodes_spec[$src_rn['node']], false, true)) {
                logScript("Found direct mapping ==> added to DEST resource #{$resources_mapping[$src_rn['resource']]} the mapped DEST nodes: " . implode(', ', $nodes_spec[$src_rn['node']]));
                $processed_resource_nodes[] = "{$src_rn["resource"]}_{$src_rn["node"]}";
                fwrite($progress_fh, "\$processed_resource_nodes[] = \"{$src_rn["resource"]}_{$src_rn["node"]}\";" . PHP_EOL);
            } else {
                logScript("WARNING: Failed to add found mapped DEST nodes to the resource (ie. resource_node)");
            }
            continue;
        }

        if (in_array($src_rn["node"], $nodes_not_created)) {
            logScript("Skipping as the node was not created on the destination system!");
            $processed_resource_nodes[] = "{$src_rn["resource"]}_{$src_rn["node"]}";
            fwrite($progress_fh, "\$processed_resource_nodes[] = \"{$src_rn["resource"]}_{$src_rn["node"]}\";" . PHP_EOL);
            continue;
        }

        if (!isset($new_nodes_mapping[$src_rn["node"]])) {
            logScript("WARNING: unable to find a node mapping!");
            $processed_resource_nodes[] = "{$src_rn["resource"]}_{$src_rn["node"]}";
            fwrite($progress_fh, "\$processed_resource_nodes[] = \"{$src_rn["resource"]}_{$src_rn["node"]}\";" . PHP_EOL);
            continue;
        }

        ps_query(
            "INSERT INTO resource_node (resource, node, hit_count, new_hit_count) VALUES (?, ?, ?, ?)",
            [
                'i', $resources_mapping[$src_rn["resource"]],
                'i', $new_nodes_mapping[$src_rn["node"]],
                'i', $src_rn["hit_count"],
                'i', $src_rn["new_hit_count"],
            ]
        );
        $processed_resource_nodes[] = "{$src_rn["resource"]}_{$src_rn["node"]}";
        fwrite($progress_fh, "\$processed_resource_nodes[] = \"{$src_rn["resource"]}_{$src_rn["node"]}\";" . PHP_EOL);
    }
    unset($src_resource_nodes);

    # RESOURCE DIMENSIONS
    #####################
    logScript("");
    logScript("Importing resource dimensions...");
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $processed_resource_dimensions = (isset($processed_resource_dimensions) ? $processed_resource_dimensions : array());
    $src_resource_dimensions = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "resource_dimensions_export.json", "r+b"));
    foreach ($src_resource_dimensions as $src_rdms) {
        $process_rdms_value = "{$src_rdms["resource"]}_{$src_rdms["width"]}_{$src_rdms["height"]}_"
        . md5("{$src_rdms["resource"]}|{$src_rdms["width"]}|{$src_rdms["height"]}|{$src_rdms["file_size"]}|{$src_rdms["resolution"]}|{$src_rdms["unit"]}|{$src_rdms["page_count"]}");
        if (in_array($process_rdms_value, $processed_resource_dimensions)) {
            continue;
        }

        logScript("Processing dimensions for resource #{$src_rdms["resource"]} | width: {$src_rdms["width"]} | height: {$src_rdms["height"]} | page_count: #{$src_rdms["page_count"]}");

        if (!array_key_exists($src_rdms["resource"], $resources_mapping)) {
            logScript("WARNING: Unable to find a resource mapping. Skipping");
            $processed_resource_dimensions[] = $process_rdms_value;
            fwrite($progress_fh, "\$processed_resource_dimensions[] = \"{$process_rdms_value}\";" . PHP_EOL);
            continue;
        }

        ps_query(
            "INSERT INTO resource_dimensions (resource, width, height, file_size, resolution, unit, page_count) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                'i', $resources_mapping[$src_rdms["resource"]],
                'i', $src_rdms["width"],
                'i', $src_rdms["height"],
                'i', $src_rdms["file_size"],
                'i', $src_rdms["resolution"],
                's', $src_rdms["unit"],
                'i', is_int_loose($src_rdms["page_count"]) ? $src_rdms["page_count"] : null,
            ]
        );

        $processed_resource_dimensions[] = $process_rdms_value;
        fwrite($progress_fh, "\$processed_resource_dimensions[] = \"{$process_rdms_value}\";" . PHP_EOL);
    }
    unset($src_resource_dimensions);

    # RESOURCE RELATED
    ##################
    logScript("");
    logScript("Importing resource related...");
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $processed_resource_related = (isset($processed_resource_related) ? $processed_resource_related : array());
    $processed_resource_related = array_flip($processed_resource_related);
    $src_resource_related = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "resource_related_export.json", "r+b"));
    $src_resource_related_chunks = array_chunk($src_resource_related, 2000);
    foreach ($src_resource_related_chunks as $src_resource_related_chunk) {
        $insertvals = $insertvals_bparams = [];
        $temp_processed_related = [];
        $temp_processed_related_log  = "";
        foreach ($src_resource_related_chunk as $src_rr) {
            if (isset($processed_resource_related["{$src_rr["resource"]}_{$src_rr["related"]}"])) {
                continue;
            }

            logScript("Processing resource related - resource: #{$src_rr["resource"]} | related: #{$src_rr["related"]}");

            if (
                !array_key_exists($src_rr["resource"], $resources_mapping)
                || !array_key_exists($src_rr["related"], $resources_mapping)
            ) {
                logScript("WARNING: Unable to find a resource mapping for either resource or related. Skipping");
                $processed_resource_related["{$src_rr["resource"]}_{$src_rr["related"]}"] = true;
                fwrite($progress_fh, "\$processed_resource_related[] = \"{$src_rr["resource"]}_{$src_rr["related"]}\";" . PHP_EOL);
                continue;
            }

            $insertvals[] = "(?, ?)";
            $insertvals_bparams = array_merge(
                $insertvals_bparams,
                ps_param_fill([$resources_mapping[$src_rr["resource"]], $resources_mapping[$src_rr["related"]]], 'i')
            );

            $temp_processed_related["{$src_rr["resource"]}_{$src_rr["related"]}"] = true;
            $temp_processed_related_log .= "\$processed_resource_related[] = \"{$src_rr["resource"]}_{$src_rr["related"]}\";" . PHP_EOL;
        }
        if (count($insertvals) > 0) {
            ps_query("INSERT INTO resource_related (resource, related) VALUES " . implode(",", $insertvals), $insertvals_bparams);
            $processed_resource_related = array_merge($processed_resource_related, $temp_processed_related);
            fwrite($progress_fh, $temp_processed_related_log);
        }
    }
    unset($src_resource_related);

    # RESOURCE ALTERNATIVE FILES
    ############################
    logScript("");
    logScript("Importing resource alternative files...");
    fwrite($progress_fh, PHP_EOL . PHP_EOL);
    $processed_resource_alt_files = (isset($processed_resource_alt_files) ? $processed_resource_alt_files : array());
    $src_resource_alt_files = $json_decode_file_data($get_file_handler($folder_path . DIRECTORY_SEPARATOR . "resource_alt_files_export.json", "r+b"));
    foreach ($src_resource_alt_files as $src_raf) {
        if (in_array("{$src_raf["resource"]}_{$src_raf["ref"]}", $processed_resource_alt_files)) {
            continue;
        }

        logScript("Processing resource alternative file - resource: #{$src_raf["resource"]} | alternative: #{$src_raf["ref"]}");

        if (!array_key_exists($src_raf["resource"], $resources_mapping)) {
            logScript("WARNING: Unable to find a resource mapping. Skipping");
            $processed_resource_alt_files[] = "{$src_raf["resource"]}_{$src_raf["ref"]}";
            fwrite($progress_fh, "\$processed_resource_alt_files[] = \"{$src_raf["resource"]}_{$src_raf["ref"]}\";" . PHP_EOL);
            continue;
        }

        db_begin_transaction(TX_SAVEPOINT);
        $new_alternative_ref = add_alternative_file(
            $resources_mapping[$src_raf["resource"]],
            $src_raf["name"],
            $src_raf["description"],
            $src_raf["file_name"],
            $src_raf["file_extension"],
            $src_raf["file_size"],
            $src_raf["alt_type"]
        );

        try {
            $src_alt_file_url = $call_src_api(
                'get_resource_path',
                [
                    'ref' => $src_raf['resource'],
                    'size' => '',
                    'generate' => 0,
                    'extension' => $src_raf['file_extension'],
                    'alternative' => $src_raf['ref'],
                ]
            );
        } catch (RuntimeException $e) {
            logScript("Response: {$e->getMessage()}");
            $src_alt_file_url = '';
        }

        if ($src_alt_file_url === '') {
            logScript('ERROR: unable to fetch the SRC alternative (original) file!');
            exit(1);
        }

        // we don't want to extract, revert or autorotate. This is a basic file pull into the DEST system from a remote SRC
        $job_data = array(
            "resource" => $resources_mapping[$src_raf["resource"]],
            "extract" => false,
            "revert" => false,
            "autorotate" => false,
            "alternative" => $new_alternative_ref,
            "upload_file_by_url" => $src_alt_file_url,
            "extension" => $src_raf["file_extension"],
        );
        $job_code = "merge_rs_systems_{$src_raf["ref"]}_{$resources_mapping[$src_raf["resource"]]}_"
                    . md5("{$src_raf["ref"]}_{$resources_mapping[$src_raf["resource"]]}");
        $job_failure_lang = "Merge RS systems - alternative upload processing fail "
            . str_replace(
                array('%ref', '%title'),
                array($src_raf["ref"], ""),
                $lang["ref-title"]
            );

        $job_queue_added = job_queue_add("upload_processing", $job_data, $userref, "", "", $job_failure_lang, $job_code);
        if ($job_queue_added === false) {
            logScript("ERROR: unable to create job queue for uploading (copying) resource alternative file from SRC system");
            exit(1);
        } elseif (is_string($job_queue_added) && trim($job_queue_added) != "") {
            logScript("ERROR: unable to create job queue. Reason: '{$job_queue_added}'");
            exit(1);
        }

        $processed_resource_alt_files[] = "{$src_raf["resource"]}_{$src_raf["ref"]}";
        fwrite($progress_fh, "\$processed_resource_alt_files[] = \"{$src_raf["resource"]}_{$src_raf["ref"]}\";" . PHP_EOL);
        db_end_transaction(TX_SAVEPOINT);
    }
    unset($src_resource_alt_files);

    fclose($progress_fh);
}

logScript("");
logScript("Script ran successfully!");
