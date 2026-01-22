<?php

// function to automatically migrate options lists to nodes
function migrate_resource_type_field_check(&$resource_type_field)
{
    if (
        !isset($resource_type_field['options']) ||
        is_null($resource_type_field['options']) ||
        $resource_type_field['options'] == '' ||
        ($resource_type_field['type'] == 7 && preg_match('/^' . MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX_CATEGORY_TREE . '/', $resource_type_field['options'])) ||
        preg_match('/^' . MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX . '/', $resource_type_field['options'])
    ) {
        return;  // get out of here as there is nothing to do
    }

    // Delete all nodes for this resource type field
    // This is to prevent systems that migrated to have old values that have been removed from a default field
    // example: Country field
    delete_nodes_for_resource_type_field($resource_type_field['ref']);

    if ($resource_type_field['type'] == 7) {      // category tree
        migrate_category_tree_to_nodes($resource_type_field['ref'], $resource_type_field['options']);

        // important!  this signifies that this field has been migrated by prefixing with -1,,MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX
        ps_query("UPDATE `resource_type_field` SET `options` = CONCAT('" . MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX_CATEGORY_TREE . "', options) WHERE `ref` = ?", array("i", $resource_type_field['ref']));
    } elseif ($resource_type_field['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST) {
        $options = preg_split('/\s*,\s*/', $resource_type_field['options']);
        $order = 10;
        foreach ($options as $option) {
            set_node(null, $resource_type_field['ref'], $option, null, $order);
            $order += 10;
        }

        // important!  this signifies that this field has been migrated by -replacing- with MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX
        // Note as the dynamic keyword fields can reach the database column length limit this no longer appends the old 'options' text - the migration script will pick up any missing options later from existing resource_data values
        ps_query("UPDATE `resource_type_field` SET `options` = '" . MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX . "' WHERE `ref` = ?", array("i", $resource_type_field['ref']));
    } else // general comma separated fields
        {
        $options = preg_split('/\s*,\s*/', $resource_type_field['options']);
        $order = 10;
        foreach ($options as $option) {
            set_node(null, $resource_type_field['ref'], $option, null, $order);
            $order += 10;
        }

        // important!  this signifies that this field has been migrated by prefixing with MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX
        ps_query("UPDATE `resource_type_field` SET `options` = CONCAT('" . MIGRATION_FIELD_OPTIONS_DEPRECATED_PREFIX . "',',',options) WHERE `ref` = ?", array("i", $resource_type_field['ref']));
    }

    clear_query_cache("schema");
}

function migrate_category_tree_to_nodes($resource_type_field_ref, $category_tree_options)
{
    $options = array();
    $option_lines = preg_split('/\r\n|\r|\n/', $category_tree_options);
    $order = 10;

    // first pass insert current nodes into nodes table
    foreach ($option_lines as $line) {
        $line_fields = preg_split('/\s*,\s*/', $line);
        if (count($line_fields) != 3) {
            continue;
        }
        $id = trim($line_fields[0]);
        $parent_id = trim($line_fields[1]);
        $name = trim($line_fields[2]);
        $ref = set_node(null, $resource_type_field_ref, $name, null, $order);

        $options['node_id_' . $id] = array(
            'id' => $id,
            'name' => $name,
            'parent_id' => $parent_id,
            'order' => $order,
            'ref' => $ref
        );
        $order += 10;
    }

    // second pass is to set parent refs
    foreach ($options as $option) {
        $ref = $option['ref'];
        $name = $option['name'];
        $order = $option['order'];
        $parent_id = $option['parent_id'];
        if ($parent_id == '') {
            continue;
        }
        $parent_ref = isset($options['node_id_' . $parent_id]) ? $options['node_id_' . $parent_id]['ref'] : null;
        set_node($ref, $resource_type_field_ref, $name, $parent_ref, $order);
    }
}

function migrate_filter($filtertext, $allowpartialmigration = false)
{
    global $FIXED_LIST_FIELD_TYPES;
    if (trim($filtertext) == "") {
        return false;
    }

    $all_fields = get_resource_type_fields();

    // Don't migrate if already migrated
    $existingrules = ps_query("SELECT ref, name FROM filter");

    $logtext = "FILTER MIGRATION: Migrating filter rule. Current filter text: '" . $filtertext . "'\n";

    // Check for existing rule (will only match if name hasn't been changed)
    $filterid = array_search($filtertext, array_column($existingrules, 'name'));
    if ($filterid !== false) {
        $logtext .= "FILTER MIGRATION: - Filter already migrated. ID = " . $existingrules[$filterid]["ref"] . "\n";
        return $existingrules[$filterid]["ref"];
    } else {
        $truncated_filter_name = mb_strcut($filtertext, 0, 200);

        // Create filter. All migrated filters will have AND rules
        ps_query("INSERT INTO filter (name, filter_condition) VALUES (?, ?)", array("s", $truncated_filter_name, "i", RS_FILTER_ALL));
        $filterid = sql_insert_id();
        $logtext .= "FILTER MIGRATION: - Created new filter. ID = " . $filterid . "'\n";
    }

    $filter_rules = explode(";", $filtertext);

    $errors = array();
    $n = 1;
    foreach ($filter_rules as $filter_rule) {
        $rulevalid = false;
        $logtext .= "FILTER MIGRATION: -- Parsing filter rule #" . $n . " : '" . $filter_rule . "'\n";
        $rule_parts = explode("=", $filter_rule);
        $rulefields = $rule_parts[0];
        if (isset($rule_parts[1])) {
            $rulevalues = explode("|", trim($rule_parts[1]));
        } else {
            $errors[] = "Invalid filter, no values are set.";
            return $errors;
        }

        // Create filter_rule
        $logtext .=  "FILTER MIGRATION: -- Creating filter_rule for '" . $filter_rule . "'\n";
        ps_query("INSERT INTO filter_rule (filter) VALUES (?)", array("i", $filterid));
        $new_filter_rule = sql_insert_id();
        $logtext .=  "FILTER MIGRATION: -- Created filter_rule # " . $new_filter_rule . "\n";

        $nodeinsert = array(); // This will contain the SQL value sets to be inserted for this rule
        $nodeinsertparams = array();

        $rulenot = substr($rulefields, -1) == "!";
        $node_condition = RS_FILTER_NODE_IN;
        if ($rulenot) {
            $rulefields = substr($rulefields, 0, -1);
            $node_condition = RS_FILTER_NODE_NOT_IN;
        }

        // If there is an OR between the fields we need to get all the possible options (nodes) into one array
        $rulefieldarr = explode("|", $rulefields);
        $all_valid_nodes = array();
        foreach ($rulefieldarr as $rulefield) {
            $all_fields_index = array_search(mb_strtolower($rulefield), array_map("mb_strtolower", array_column($all_fields, 'name')));
            $field_ref = $all_fields[$all_fields_index]["ref"];
            $field_type = $all_fields[$all_fields_index]["type"];
            $logtext .= "FILTER MIGRATION: --- filter field name: '" . $rulefield . "' , field id #" . $field_ref . "\n";

            if (!in_array($field_type, $FIXED_LIST_FIELD_TYPES)) {
                $errors[] = "Invalid field  '" . $field_ref . "' specified for rule: '" . $filtertext . "', skipping";
                $logtext .=  "FILTER MIGRATION: --- Invalid field  '" . $field_ref . "', skipping\n";
                continue;
            }

            $field_nodes = get_nodes($field_ref, null, (FIELD_TYPE_CATEGORY_TREE == $field_type ? true : false));
            $all_valid_nodes = array_merge($all_valid_nodes, $field_nodes);
        }

        foreach ($rulevalues as $rulevalue) {
            // Check for value in field options
            $logtext .=  "FILTER MIGRATION: --- Checking for filter rule value : '" . $rulevalue . "'\n";
            $nodeidx = array_search(mb_strtolower($rulevalue), array_map("mb_strtolower", array_column($all_valid_nodes, 'name')));

            if ($nodeidx !== false) {
                $nodeid = $all_valid_nodes[$nodeidx]["ref"];
                $logtext .=  "FILTER MIGRATION: --- field option (node) exists, node id #: " . $all_valid_nodes[$nodeidx]["ref"] . "\n";

                $nodeinsert[] = "(?, ?, ?)";
                $nodeinsertparams = array_merge($nodeinsertparams, array("i", $new_filter_rule, "i", $nodeid, "i", $node_condition));
                if ($allowpartialmigration) {
                    $rulevalid = true;
                } // Atleast one rule is valid so the filter can be created
            } else {
                $errors[] = "Invalid field option '" . $rulevalue . "' specified for rule: '" . $filtertext . "', skipping";
                $logtext .=  "FILTER MIGRATION: --- Invalid field option: '" . $rulevalue . "', skipping\n";
            }
        }

        debug($logtext);
        if (count($errors) > 0 && !$rulevalid) {
            delete_filter($filterid);
            return $errors;
        }

        // Insert associated filter_rules
        $logtext .=  "FILTER MIGRATION: -- Adding nodes to filter_rule\n";
        $sql = "INSERT INTO filter_rule_node (filter_rule,node,node_condition) VALUES " . implode(',', $nodeinsert);
        ps_query($sql, $nodeinsertparams);
    }

    debug("FILTER MIGRATION: filter migration completed for '" . $filtertext);
    $logtext .= "FILTER MIGRATION: filter migration completed for '" . $filtertext . "\n";

    return $filterid;
}

/**
* Utility function to generate a random UTF8 character
*
* @return string
*/
function random_char()
{
    $hex_code = dechex(mt_rand(195, 202));
    $hex_code .= dechex(mt_rand(128, 175));
    return pack('H*', $hex_code);
}

/**
* Utility function to randomly alter date by offset
*
* @param string $fromdate       - date string
* @param int $maxoffset         - Maximum number of days to offset
* @return string
*/
function mix_date($fromdate, $maxoffset = 30)
{
    global $mixcache;
    if (isset($mixcache[md5($fromdate)])) {
        return $mixcache[md5($fromdate)];
    }

    if (trim($fromdate == "")) {
        $tstamp = time();
    } else {
        $tstamp = strtotime($fromdate);
    }

    $dateshift = 60 * 60 * 24 * $maxoffset; // How much should dates be moved
    $newstamp = $tstamp + (mt_rand(-$dateshift, $dateshift));
    $newdate = gmdate('Y-m-d H:i:s', $newstamp);
    debug("Converted date " . $fromdate . " to " . $newdate);

    // Update cache
    $mixcache[md5($fromdate)] = $newdate;

    return $newdate;
}

/**
* Utility function to randomly scramble string
*
* @param null|string $string Text string to scramble
* @param boolean $recurse Optionally prevent recursion (maybe called by another mix unction)
* @return string
*/
function mix_text(?string $string, bool $recurse = true): string
{
    global $mixcache;

    $string ??= '';
    if (trim($string) === '') {
        return '';
    }

    if (isset($mixcache[md5($string)])) {
        return $mixcache[md5($string)];
    }

    debug("Converting string<br/>" . $string . ", recurse=" . ($recurse ? "TRUE" : "FALSE"));

    // Check if another function is better
    if (validateDatetime($string) && $recurse) {
        debug("This is a date - calling mix_date()");
        return mix_date($string);
    } elseif (strpos($string, "http") === 0  && $recurse) {
        debug("This is a URL - calling mix_url()");
        return mix_url($string);
    } elseif (get_mime_types_by_extension(parse_filename_extension($string)) !== [] && $recurse) {
        debug("This is a filename - calling mix_filename()");
        return mix_filename($string);
    }

    $numbers = '0123456789';
    $uppercons = 'BCDFGHJKLMNPQRSTVWXZ';
    $uppervowels = 'AEIOUY';
    $lowercons = 'bcdfghjklmnpqrstvwxz';
    $lowervowels = 'aeiouy';
    $noreplace = "'\".,<>#-_&\$£:;^?!@+()*% \n";

    $newstring = "";
    $bytelength = strlen($string);
    $mbytelength = mb_strlen($string);

    // Simple conversion if numbers
    if ($bytelength == $mbytelength && (string)(int)$string == $string) {
        $newstring =  mt_rand(0, (int)$string);
    } else {
        // Process each character
        for ($i = 0; $i < $mbytelength; $i++) {
            $oldchar = mb_substr($string, $i, 1);

            if ($i > 3 && strpos($noreplace, $oldchar) === false) {
                // Randomly add or remove character after first
                $randaction = mt_rand(0, 10);
                if ($randaction == 0) {
                    // Skip a character
                    $i++;
                } elseif ($randaction == 1) {
                    // Add a character
                    $i--;
                }
            }

            if ($i >= $mbytelength || $oldchar == "") {
                $newstring .=  substr(str_shuffle($lowervowels . $lowercons), 0, 1);
            } elseif (strpos($noreplace, $oldchar) !== false) {
                $newstring .= $oldchar;
            } elseif (strlen($oldchar) == 1) {
                // Non- multibyte
                if (strpos($lowercons, $oldchar) !== false) {
                    $newchar = substr(str_shuffle($lowercons), 0, 1);
                } elseif (strpos($uppercons, $oldchar) !== false) {
                    $newchar = substr(str_shuffle($uppercons), 0, 1);
                } elseif (strpos($lowervowels, $oldchar) !== false) {
                    $newchar = substr(str_shuffle($lowervowels), 0, 1);
                } elseif (strpos($uppervowels, $oldchar) !== false) {
                    $newchar = substr(str_shuffle($uppervowels), 0, 1);
                } elseif (strpos($numbers, $oldchar) !== false) {
                    $newchar = substr(str_shuffle($numbers), 0, 1);
                } else {
                    $newchar = substr(str_shuffle($noreplace), 0, 1);
                }
                $newstring .= $newchar;
            } else {
                $newchar = random_char();
                $newstring .= $newchar;
            } // End of multibyte conversion
        }
    }

    // Update cache
    $mixcache[md5($string)] = $newstring;
    return $newstring;
}

/**
* Utility function to randomly scramble data array for exporting
*
* @param array $row             - Array of data passed by reference
* @param boolean $scramblecolumns - Optional array of columns to scramble
* @return void
*/
function alter_data(&$row, $key, $scramblecolumns = array())
{
    foreach ($scramblecolumns as $scramblecolumn => $scrambletype) {
        $row[$scramblecolumn] = call_user_func($scrambletype, $row[$scramblecolumn]);
    }
}

/**
* Utility function to scramble a URL
*
* @param string $string           - URL to scramble
*
* @return string
*/
function mix_url($string)
{
    global $mixcache, $baseurl;
    if (trim($string) == "") {
        return "";
    }
    if (isset($mixcache[md5($string)])) {
        return $mixcache[md5($string)];
    }
    if (strpos($string, "pages") === 0 || strpos($string, "/pages") === 0 || strpos($string, $baseurl) === 0) {
        // URL is a relative path within the system, don't scramble
        return $string;
    }
    if (strpos($string, "://") !== false) {
        $urlparts = explode("://", $string);
        return $urlparts[0] . "://" . mix_text($urlparts[1], false);
    }
    return mix_text($string);
}

/**
* Utility function to scramble a filename
*
* @param string $string           - filename to scramble
*
* @return string
*/
function mix_filename($string)
{
    global $mixcache;
    if (trim($string) == "") {
        return "";
    }
    if (isset($mixcache[md5($string)])) {
        return $mixcache[md5($string)];
    }

    debug("filename: " . $string);
    if (strpos($string, ".") === false) {
        return mix_text($string, false);
    }

    $fileparts = pathinfo($string);
    $newfilename = mix_text($fileparts["filename"], false) . "." . $fileparts["extension"];

    debug("New filename: " . $newfilename);
    return $newfilename;
}

/**
* Utility function to scramble an email address
*
* @param string $string           - email to scramble
*
* @return string
*/
function mix_email($string)
{
    global $mixcache;
    if (isset($mixcache[md5($string)])) {
        return $mixcache[md5($string)];
    }

    $emailparts = explode("@", $string);
    if (count($emailparts) < 2) {
        return mix_text($string);
    }

    $newemail = implode("@", array_map("mix_text", $emailparts));

    // Update cache
    $mixcache[md5($string)] = $newemail;

    return $newemail;
}

/**
* Utility function to escape and replace any empty strings with NULLS for exported SQL scripts
*
* @param null|string $value Value to check
* @return string
*/
function safe_export(?string $value): string
{
    return trim($value ?? '') == "" ? "NULL" : "'" . escape_check($value) . "'";
}

/**
* Get array of tables to export when exporting system config and data
*
* @param int $exportcollection      - Optional collection id to include resources and data from
*
* @return array
*/
function get_export_tables($exportcollection = 0)
{
    global $plugins;
    if ((string)(int)$exportcollection !== (string)$exportcollection) {
        $exportcollection = 0;
    }

    // Create array of tables to export
    $exporttables = array();
    $exporttables["sysvars"] = array();
    $exporttables["preview_size"] = array();
    $exporttables["workflow_actions"] = array();

    if (in_array("rse_workflow", $plugins)) {
        $exporttables["archive_states"] = array();
    }

    $exporttables["user"] = array();
    $exporttables["user"]["scramble"] = array("username" => "mix_text","email" => "mix_email","fullname" => "mix_text","comments" => "mix_text","created" => "mix_date");
    $exporttables["user_preferences"] = array();

    $exporttables["usergroup"] = array();
    $exporttables["usergroup"]["scramble"] = array("name" => "mix_text","welcome_message" => "mix_text","search_filter" => "mix_text","edit_filter" => "mix_text");


    $exporttables["dash_tile"] = array();
    $exporttables["dash_tile"]["scramble"] = array("title" => "mix_text","txt" => "mix_text","url" => "mix_url");
    $exporttables["user_dash_tile"] = array();
    $exporttables["usergroup_dash_tile"] = array();

    $exporttables["resource_type"] = array();
    $exporttables["resource_type_field"] = array();
    $exporttables["resource_type_field"]["scramble"] = array("title" => "mix_text","name" => "mix_text");

    $exporttables["node"] = array();
    $exporttables["node"]["scramble"] = array("name" => "mix_text");

    $exporttables["filter"] = array();
    $exporttables["filter"]["scramble"] = array("name" => "mix_text");
    $exporttables["filter_rule"] = array();
    $exporttables["filter_rule_node"] = array();

    // Optional tables
    if ($exportcollection != 0) {
        // Collections
        $exporttables["collection"] = array();
        $exporttables["collection"]["exportcondition"] = "WHERE ref = '$exportcollection'";
        $exporttables["collection"]["scramble"] = array("name" => "mix_text","description" => "mix_text","keywords" => "mix_text","created" => "mix_date");

        $exporttables["user_collection"] = array();
        $exporttables["usergroup_collection"] = array();
        $exporttables["collection_resource"] = array();
        //  Resources and resource metadata
        $exporttables["resource"] = array();
        $exporttables["resource"]["scramble"] = array("field8" => "mix_text","creation_date" => "mix_date");
        $exporttables["resource"]["exportcondition"] = " WHERE ref IN (SELECT resource FROM collection_resource WHERE collection='$exportcollection')";

        $exporttables["resource_node"] = array();
        $exporttables["resource_custom_access"] = array();
        $exporttables["resource_dimensions"] = array();
        $exporttables["resource_related"] = array();
        $exporttables["resource_alt_files"] = array();
        $exporttables["resource_alt_files"]["scramble"] = array("name" => "mix_text","description" => "mix_text","file_name" => "mix_filename");
        $exporttables["annotation"] = array();
        $exporttables["annotation_node"] = array();
    }

    $extra_tables = hook("export_add_tables");
    if (is_array($extra_tables)) {
        $exporttables = array_merge($exporttables, $extra_tables);
    }
    return $exporttables;
}

function edit_filter_to_restype_permission($filtertext, $usergroup, $existingperms, $updatecurrent = false)
{
    global $userpermissions;
    $addpermissions = array();
    // Replace any resource type edit filter sections with new XE/XE-?/XE? permissions
    $filterrules = explode(";", $filtertext);
    $cleanedrules = array();
    foreach ($filterrules as $filterrule) {
        $filterparts = explode("=", $filterrule);
        $checkattr = trim(strtolower($filterparts[0]));
        if (substr($checkattr, 0, 13) == "resource_type") {
            $filternot = false;
            if (substr($checkattr, -1) == "!") {
                $filternot = true;
            } else {
                // Only allowing certain resource types. Add permission to block all resource types
                // and then add a permission for each permitted type
                $addpermissions[] = "XE";
            }

            $checkrestypes = explode("|", $filterparts[1]);
            foreach ($checkrestypes as $checkrestype) {
                // Add either XE-? or XE? permission, depending on whether group is only allowed to edit the specified types or everything except these types
                $addpermissions[] = "XE" . ($filternot ? "" : "-") . (int)trim($checkrestype);
            }
        } else {
            $cleanedrules[] = trim($filterrule);
        }
    }

    $currentgroup = get_usergroup($usergroup);
    if (in_array("permissions", $currentgroup["inherit"])) {
        $existingperms = explode(",", $currentgroup["permissions"]);
    }
    $newperms = array_diff($addpermissions, $existingperms);
    if (count($newperms) > 0) {
        save_usergroup($usergroup, array('permissions' => $currentgroup["permissions"] . ',' . implode(',', $newperms)));
        clear_query_cache('usergroup');
    }
    if ($updatecurrent) {
        $userpermissions = array_merge($userpermissions, $newperms);
    }

    // Reconstruct filter text without this to create new filter
    return implode(";", $cleanedrules);
}
