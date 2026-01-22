<?php

include_once __DIR__ . "/../../include/boot.php";
include_once __DIR__ . "/../../include/image_processing.php";
command_line_only();

$send_notification  = false;
$suppress_output    = (isset($staticsync_suppress_output) && $staticsync_suppress_output) ? true : false;

// CLI options check
$cli_short_options = 'hcd:';
$cli_long_options  = array(
    'help',
    'send-notifications',
    'suppress-output',
    'clearlock',
    'delete-resources:'
);

foreach (getopt($cli_short_options, $cli_long_options) as $option_name => $option_value) {
    if (in_array($option_name, array('h', 'help'))) {
        echo "To clear the lock after a failed run, ";
        echo "pass in '--clearlock'" . PHP_EOL;
        echo 'If you have the configs [$file_checksums=true; $file_upload_block_duplicates=true;] set and would like to have duplicate resource information sent as a notification please run php staticsync.php --send-notifications' . PHP_EOL;
        echo "To force or disable resource deletion if the file has been removed from the syncdir, ";
        echo "pass in -d1, -d0, --delete_resources=true, or --delete_resources=false\n";
        echo "If not passed in the default value will be used: ";
        echo ($staticsync_delete_resources ? "true": "false") . PHP_EOL;
        exit(1);
    }
    if (
        in_array($option_name, array('clearlock', 'c'))
        && is_process_lock("staticsync")
    ) {
            clear_process_lock("staticsync");
    }

    if ('send-notifications' == $option_name) {
        $send_notification = true;
    }
    if ('suppress-output' == $option_name) {
        $suppress_output = true;
    }
    if (in_array($option_name, array('delete-resources', 'd'))) {
        if (in_array($option_value, [1, 0, 'true', 'false'])) {
            if ($option_value && !isset($staticsync_deleted_state)) {
                echo "Unable to run with delete files as deleted state is not defined.\n";
                echo "Set \$staticsync_deleted_state and run the script again\n";
                exit(1);
            }
            $staticsync_delete_resources = (bool) $option_value;
        } else {
            echo "Delete resources must be set to 1, true, 0, or false.\n";
            echo "pass in -d1, --delete_resources=true etc.\n";
            echo "If not passed in the default value will be used: ";
            echo ($staticsync_delete_resources ? "true": "false") . PHP_EOL;
            exit(1);
        }
    }
}

if (isset($staticsync_userref)) {
    # If a user is specified, log them in.
    $userref = $staticsync_userref;
    $userdata = get_user($userref);
    if ($userdata === false) {
        echo 'Unable to get user.' . PHP_EOL;
        exit(1);
    }
    $userdata = array($userdata);
    setup_user($userdata[0]);
}

ob_end_clean();
if ($suppress_output) {
    ob_start();
}

set_time_limit(60 * 60 * 40);
echo "StaticSync started at " . date('Y-m-d H:i:s', time()) . PHP_EOL;

# Check for a process lock
if (is_process_lock("staticsync")) {
    echo 'Process lock is in place. Deferring.' . PHP_EOL;
    echo 'To clear the lock after a failed run use --clearlock flag.' . PHP_EOL;
    exit();
}
set_process_lock("staticsync");

// Strip trailing slash if it has been left in
$syncdir = rtrim($syncdir, "/");

echo "Preloading data... ";

// Set options that don't make sense here
$merge_filename_with_title = false;

$count = 0;
$done = $fcs_to_reorder = [];
$errors = array();
$syncedresources = ps_query("SELECT ref, file_path, file_modified, archive FROM resource WHERE LENGTH(file_path)>0");
foreach ($syncedresources as $syncedresource) {
    $done[$syncedresource["file_path"]]["ref"] = $syncedresource["ref"];
    $done[$syncedresource["file_path"]]["modified"] = $syncedresource["file_modified"];
    $done[$syncedresource["file_path"]]["archive"] = $syncedresource["archive"];
}

// Set up an array to monitor processing of new alternative files
$alternativefiles = array();
$restypes = get_resource_types();

if (isset($numeric_alt_suffixes) && $numeric_alt_suffixes > 0) {
    // Add numeric suffixes to $staticsync_alt_suffix_array to support additional suffixes
    $newsuffixarray = array();
    foreach ($staticsync_alt_suffix_array as $suffix => $description) {
        $newsuffixarray[$suffix] = $description;
        for ($i = 1; $i < $numeric_alt_suffixes; $i++) {
            $newsuffixarray[$suffix . $i] = $description . " (" . $i . ")";
        }
    }
    $staticsync_alt_suffix_array = $newsuffixarray;
}

// Add all the synced alternative files to the list of completed
if (isset($staticsync_alternative_file_text) && (!$staticsync_ingest || $staticsync_ingest_force)) {
    // Add any staticsynced alternative files to the array so we don't process them unnecessarily
    $syncedalternatives = ps_query("SELECT ref, file_name, resource, creation_date FROM resource_alt_files WHERE file_name like concat('%',?,'%')", ['s', $syncdir]);
    foreach ($syncedalternatives as $syncedalternative) {
        $shortpath = str_replace($syncdir . '/', '', $syncedalternative["file_name"]);
        $done[$shortpath]["ref"] = $syncedalternative["resource"];
        $done[$shortpath]["modified"] = $syncedalternative["creation_date"];
        $done[$shortpath]["alternative"] = $syncedalternative["ref"];
    }
}

$lastsync = ps_value("SELECT value FROM sysvars WHERE name='lastsync'", array(), "");
$lastsync = (strlen($lastsync) > 0) ? strtotime($lastsync) : '';

echo "done." . PHP_EOL;
echo "Looking for changes..." . PHP_EOL;

# Pre-load the category tree, if configured.
if (isset($staticsync_mapped_category_tree)) {
    $treefield = get_resource_type_field($staticsync_mapped_category_tree);
    migrate_resource_type_field_check($treefield);
    $tree = get_nodes($staticsync_mapped_category_tree, '', true);
}

function touch_category_tree_level($path_parts)
{
    # For each level of the mapped category tree field, ensure that the matching path_parts path exists
    global $staticsync_mapped_category_tree, $tree;

    $parent_search = '';
    $nodename      = '';
    $order_by = 10;
    $treenodes = array();
    for ($n = 0; $n < count($path_parts); $n++) {
        $nodename = $path_parts[$n];

        echo " - Looking for folder '" . $nodename . "' @ level " . $n  . " in linked metadata field... ";
        # Look for this node in the tree.
        $found = false;
        foreach ($tree as $treenode) {
            if ($treenode["parent"] == $parent_search) {
                if ($treenode["name"] == $nodename) {
                    # A match!
                    echo " - FOUND" . PHP_EOL;
                    $found = true;
                    $treenodes[] = $treenode["ref"];
                    $parent_search = $treenode["ref"]; # Search for this as the parent node on the pass for the next level.
                } else {
                    if ($order_by <= $treenode["order_by"]) {
                        $order_by = $order_by + 10;
                    }
                }
            }
        }
        if (!$found) {
            echo " - NOT FOUND. Updating tree field" . PHP_EOL;
            # Add this node
            $newnode = set_node(null, $staticsync_mapped_category_tree, $nodename, $parent_search, $order_by);
            $tree[] = array("ref" => $newnode,"parent" => $parent_search,"name" => $nodename,"order_by" => $order_by);
            $parent_search = $newnode; # Search for this as the parent node on the pass for the next level.
            $treenodes[] = $newnode;
            clear_query_cache("schema");
        }
    }
    // Return the matching path nodes
    return $treenodes;
}

function ProcessFolder($folder)
{
    global $lang, $syncdir, $nogo, $staticsync_max_files, $count, $done, $lastsync, $unoconv_extensions,
           $staticsync_autotheme, $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS, $staticsync_mapped_category_tree,
           $staticsync_title_includes_path, $staticsync_ingest, $staticsync_mapfolders, $staticsync_alternatives_suffix,
           $staticsync_defaultstate, $additional_archive_states, $staticsync_extension_mapping_append_values,
           $staticsync_deleted_state, $staticsync_alternative_file_text, $staticsync_filepath_to_field,
           $resource_deletion_state, $alternativefiles, $staticsync_revive_state, $enable_thumbnail_creation_on_upload,
           $staticsync_extension_mapping_append_values_fields, $staticsync_extension_mapping_append_separator,
           $view_title_field, $filename_field, $FIXED_LIST_FIELD_TYPES,
           $staticsync_whitelist_folders,$staticsync_ingest_force,$errors, $category_tree_add_parents,
           $staticsync_alt_suffixes, $staticsync_alt_suffix_array, $staticsync_file_minimum_age, $userref,
           $resource_type_extension_mapping_default, $resource_type_extension_mapping, $restypes;

    $collection = 0;
    $treeprocessed = false;

    if (!file_exists($folder)) {
        echo "Sync folder does not exist: " . $folder . PHP_EOL;
        return false;
    }
    echo "Processing Folder: " . $folder . PHP_EOL;

    # List all files in this folder.

    $directories_arr = array();
    $files_arr = array();
    $import_paths = array();

    $dh = opendir($folder);
    while (($file = readdir($dh)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }

        $fullpath = "{$folder}/{$file}";
        $filetype = filetype($fullpath);

        # Sort directory content so files are processed first.
        if ($filetype == 'dir' || $filetype == 'link') {
            $directories_arr[] = $fullpath;
        }
        if ($filetype == 'file') {
            $files_arr[] = $fullpath;
        }
    }

    $import_paths = array_merge($files_arr, $directories_arr);
    $fullpath = '';

    foreach ($import_paths as $fullpath) {
        if (!is_readable($fullpath)) {
            echo "Warning: File '{$fullpath}' is unreadable!" . PHP_EOL;
            continue;
        }
        $skipfc_create = false; // Flag to prevent creation of new FC
        $filetype        = filetype($fullpath);
        $shortpath       = str_replace($syncdir . '/', '', $fullpath);
        $file = basename($fullpath);

        if ($staticsync_mapped_category_tree && !$treeprocessed) {
            $path_parts = explode("/", $shortpath);
            array_pop($path_parts);
            $treenodes = touch_category_tree_level($path_parts);
            $treeprocessed = true;
        }

        # -----FOLDERS-------------
        if (
            ($filetype == 'dir' || $filetype == 'link')
            && count($staticsync_whitelist_folders) > 0
            && !isPathWhitelisted($shortpath, $staticsync_whitelist_folders)
        ) {
            // Folders which are not whitelisted will not be processed any further
            continue;
        }

        if (
            ($filetype == 'dir' || $filetype == 'link')
            && strpos($nogo, "[{$file}]") === false
            && strpos($file, $staticsync_alternatives_suffix) === false
        ) {
            // Recurse
            ProcessFolder("{$fullpath}");
        }

        # -------FILES---------------
        if (($filetype == "file") && (substr($file, 0, 1) != ".") && (strtolower($file) != "thumbs.db")) {
            if (isset($staticsync_file_minimum_age) && (time() -  filemtime($folder . "/" . $file) < $staticsync_file_minimum_age)) {
                // Don't process this file yet as it is too new
                echo " - " . $file . " is too new (" . (time() -  filemtime($folder . "/" . $file)) . " seconds), skipping\n";
                continue;
            }

            # Work out extension
            $extension = mb_strcut(parse_filename_extension($file), 0, 10);
            $filename = pathinfo($file, PATHINFO_FILENAME);

            if (isset($staticsync_alternative_file_text) && strpos($file, $staticsync_alternative_file_text) !== false && !$staticsync_ingest_force) {
                // Set a flag so we can process this later in case we don't process this along with a primary resource file (it may be a new alternative file for an existing resource)
                $alternativefiles[] = $syncdir . '/' . $shortpath;
                continue;
            } elseif (isset($staticsync_alt_suffixes) && $staticsync_alt_suffixes && is_array($staticsync_alt_suffix_array)) {
                // Check if this is a file with a suffix defined in the $staticsync_alt_suffixes array and then process at the end
                foreach ($staticsync_alt_suffix_array as $altsfx => $altname) {
                    $altsfxlen = mb_strlen($altsfx);
                    $checksfx = substr($filename, -$altsfxlen) == $altsfx;
                    if ($checksfx == $altsfx) {
                        echo " - Adding to \$alternativefiles array " . $file . "\n";
                        $alternativefiles[] = $syncdir . '/' . $shortpath;
                        continue 2;
                    }
                }
            }

            $modified_extension = hook('staticsync_modify_extension', 'staticsync', array($fullpath, $shortpath, $extension));
            if ($modified_extension !== false) {
                $extension = $modified_extension;
            }

            // Verify if the file would actually pass the upload checks to prevent any unnecessary processing (e.g. collections)
            $dry_run_process_file_upload = process_file_upload(
                new SplFileInfo($fullpath),
                new SplFileInfo(get_temp_dir(false) . '/staticsync/' . generateSecureKey(16) . '.bin'),
                ['file_move' => 'dry_run', 'mime_file_based_detection' => false]
            );
            if (!$dry_run_process_file_upload['success']) {
                printf(
                    ' * Skipping file - %s: %s%s',
                    $fullpath,
                    $dry_run_process_file_upload['error']->i18n($lang),
                    PHP_EOL
                );
                continue;
            }

            global $file_checksums, $file_upload_block_duplicates, $file_checksums_50k;

            if ($count > $staticsync_max_files) {
                return true;
            }

            # Already exists or deleted/archived in which case we won't proceed?
            if (!isset($done[$shortpath])) {
                // Extra check to make sure we don't end up with duplicates
                $existing = ps_value("SELECT ref value FROM resource WHERE file_path = ?", array("s",$shortpath), 0);
                if ($existing > 0 || hook('staticsync_plugin_add_to_done')) {
                    $done[$shortpath]["processed"] = true;
                    $done[$shortpath]["modified"] = date('Y-m-d H:i:s', time());
                    continue;
                }
                # Check for duplicate files
                if ($file_upload_block_duplicates) {
                    # Generate the ID
                    if ($file_checksums_50k) {
                        # Fetch the string used to generate the unique ID
                        $use = filesize_unlimited($fullpath) . "_" . file_get_contents($fullpath, false, null, 0, 50000);
                        $checksum = md5($use);
                    } else {
                        $checksum = md5_file($fullpath);
                    }
                    $duplicates = ps_array("select ref value from resource where file_checksum= ?", ['s', $checksum]);
                    if (count($duplicates) > 0) {
                        $message = str_replace("%resourceref%", implode(",", $duplicates), str_replace("%filename%", $fullpath, $lang['error-duplicatesfound']));
                        debug("STATICSYNC ERROR- " . $message);
                        $errors[] = $message;
                        continue;
                    }
                }

                $count++;

                echo "Processing file: $fullpath" . PHP_EOL;

                if ($collection == 0 && $staticsync_autotheme && !$skipfc_create) {
                    # Find or create a featured collection for this folder as required.
                    $e = explode("/", $shortpath);
                    $fallback_fc_categ_name = ucwords($e[0]);
                    $name = (count($e) == 1) ? '' : $e[count($e) - 2];
                    echo " - Collection '{$name}'" . PHP_EOL;
                    // The real featured collection will always be the last directory in the path
                    $proposed_fc_categories = array_values(array_diff($e, array_slice($e, -2)));
                    if (count($proposed_fc_categories) == 0) {
                        if (count($e) > 1) {
                            // This is a top level folder - this is needed to ensure no duplication of existing top level FCs
                            echo " - File is in a top level folder" . PHP_EOL;
                            $proposed_fc_categories = array($e[0]);
                        } else {
                            // This file is in the root folder, no FC needs to be created
                            echo " - File is not in a folder, skipping FC creation" . PHP_EOL;
                            $skipfc_create = true;
                        }
                    }
                    if (!$skipfc_create) {
                        echo " - Proposed Featured Collection Categories: " . join(" / ", $proposed_fc_categories) . PHP_EOL;
                        // Build the tree first, if needed
                        $proposed_branch_path = array();
                        for ($b = 0; $b < count($proposed_fc_categories); $b++) {
                            $parent = ($b == 0 ? 0 : $proposed_branch_path[($b - 1)]);
                            $fc_categ_name = ucwords($proposed_fc_categories[$b]);

                            $params = [];
                            if ($parent == 0) {
                                $parent_sql = 'IS NULL';
                            } else {
                                $parent_sql = '= ?';
                                $params[] = 'i';
                                $params[] = $parent;
                            }

                            $fc_categ_ref_sql = 'SELECT DISTINCT ref AS `value` FROM collection c LEFT JOIN collection_resource cr on c.ref = cr.collection
                                                 WHERE parent ' . $parent_sql . ' AND type = ? AND name = ? GROUP BY c.ref HAVING count(DISTINCT cr.resource) = 0';
                            $fc_categ_ref = ps_value($fc_categ_ref_sql, array_merge($params, ['i', COLLECTION_TYPE_FEATURED, 's', $fc_categ_name]), 0);
                            if ($fc_categ_ref == 0) {
                                echo " - Creating new Featured Collection category named '{$fc_categ_name}'" . PHP_EOL;
                                $fc_categ_ref = create_collection($userref, $fc_categ_name);
                                echo " - Created '{$fc_categ_name}' with ref #{$fc_categ_ref}" . PHP_EOL;

                                $updated_fc_category = save_collection(
                                    $fc_categ_ref,
                                    array(
                                        "featured_collections_changes" => array(
                                            "update_parent" => $parent,
                                            "force_featured_collection_type" => true,
                                            "thumbnail_selection_method" => $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"],
                                        )
                                    )
                                );

                                if ($updated_fc_category === false) {
                                    echo " - Unable to update '{$fc_categ_name}' with ref #{$fc_categ_ref} to a Featured Collection Category" . PHP_EOL;
                                }
                            }

                            $proposed_branch_path[] = $fc_categ_ref;
                        }

                        $collection_parent = array_pop($proposed_branch_path);
                        if (is_null($collection_parent)) {
                            // We don't have enough folders to create categories so the first one will do (legacy logic)
                            $collection_parent = create_collection($userref, $fallback_fc_categ_name);
                            save_collection(
                                $collection_parent,
                                array(
                                    "featured_collections_changes" => array(
                                        "update_parent" => 0,
                                        "force_featured_collection_type" => true,
                                        "thumbnail_selection_method" => $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"],
                                    )
                                )
                            );
                        }
                        echo " - Collection parent should be ref #{$collection_parent}" . PHP_EOL;

                        $params = [];
                        if ($collection_parent == 0) {
                            $parent_sql = 'IS NULL';
                        } else {
                            $parent_sql = '= ?';
                            $params[] = 'i';
                            $params[] = $collection_parent;
                        }

                        /*
                        When trying to determine if we already have a featured collection, the viable options are:-
                        - an actual featured collection (i.e. has resources), or;
                        - an ambiguous state record (i.e. it doesn't have resources nor any children - so not a true
                        category either; note they get displayed as a category by default).
                        */
                        $collection = ps_value(
                              "SELECT DISTINCT c.ref as `value`,
                                      count(DISTINCT cr.resource) > 0 AS has_resources
                                 FROM collection AS c
                            LEFT JOIN collection_resource AS cr on c.ref = cr.collection
                            LEFT JOIN collection AS cc ON c.ref = cc.parent
                                WHERE c.parent {$parent_sql}
                                  AND c.`type` = ?
                                  AND c.`name` = ?
                             GROUP BY c.ref
                               HAVING count(DISTINCT cr.resource) > 0
                                   OR (count(DISTINCT cr.resource) = 0 AND count(DISTINCT cc.ref) = 0)
                             ORDER BY has_resources DESC
                                LIMIT 1",
                            array_merge($params, ['i', COLLECTION_TYPE_FEATURED, 's', ucwords($name)]),
                            0
                        );

                        if ($collection == 0) {
                            $collection = create_collection($userref, ucwords($name));
                            echo " - Created '{$name}' with ref #{$collection}" . PHP_EOL;

                            $updated_fc_category = save_collection(
                                $collection,
                                array(
                                    "featured_collections_changes" => array(
                                        "update_parent" => $collection_parent,
                                        "force_featured_collection_type" => true,
                                        "thumbnail_selection_method" => $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"],
                                    )
                                )
                            );

                            if ($updated_fc_category === false) {
                                echo " - Unable to update '{$name}' with ref #{$collection} to be a Featured Collection under parent ref #{$collection_parent}" . PHP_EOL;
                            }
                        }
                    }
                }

                # Work out a resource type based on the extension.
                $type = (isset($GLOBALS['staticsync_extension_mapping_default']) ? $GLOBALS['staticsync_extension_mapping_default'] : $resource_type_extension_mapping_default);
                $rt_ext_mappings = (isset($GLOBALS['staticsync_extension_mapping']) ? $GLOBALS['staticsync_extension_mapping'] : $resource_type_extension_mapping);
                reset($rt_ext_mappings);
                foreach ($rt_ext_mappings as $rt => $extensions) {
                    if (in_array(strtolower($extension), $extensions)) {
                        $type = $rt;
                    }
                }

                if (isset($staticsync_mapfolders)) {
                    $field_nodes    = array();
                    foreach ($staticsync_mapfolders as $mapfolder) {
                        $match = $mapfolder["match"];
                        $field = $mapfolder["field"];
                        $level = $mapfolder["level"];
                        $path_parts = explode("/", $shortpath);
                        if (
                            $field == 'resource_type'
                            && (strpos("/" . $shortpath, $match) !== false)
                            && $level < count($path_parts)
                        ) {
                            $value = $path_parts[$level - 1];
                            $typeidx = array_search($value, array_column($restypes, "name"));
                            if ($typeidx !== false) {
                                $type = $restypes[$typeidx]["ref"];
                                echo " - \$staticsync_mapfolders - set resource type to " . $value . " ($type)" . PHP_EOL;
                            }
                        }
                    }
                }

                $modified_type = hook('modify_type', 'staticsync', array( $type ));
                if (is_numeric($modified_type)) {
                    $type = $modified_type;
                }

                # Formulate a title
                if ($staticsync_title_includes_path && $view_title_field !== $filename_field) {
                    $title_find = array('/',   '_', ".$extension" );
                    $title_repl = array(' - ', ' ', '');
                    $title      = ucfirst(str_ireplace($title_find, $title_repl, $shortpath));
                } else {
                    $title = str_ireplace(".$extension", '', $file);
                }

                $modified_title = hook('modify_title', 'staticsync', array( $title ));
                if ($modified_title !== false) {
                    $title = $modified_title;
                }

                # Import this file
                $r = import_resource($shortpath, $type, $title, $staticsync_ingest, $extension);
                echo " - Created resource #" . $r . PHP_EOL;

                if ($r !== false) {
                    # Add to mapped category tree (if configured)
                    if (isset($staticsync_mapped_category_tree) && isset($treenodes) && count($treenodes) > 0) {
                        // Add path nodes to resource
                        add_resource_nodes($r, $treenodes);
                    }

                    # default access level. This may be overridden by metadata mapping.
                    $accessval = 0;

                    # StaticSync path / metadata mapping
                    # Extract metadata from the file path as per $staticsync_mapfolders in config.php
                    if (isset($staticsync_mapfolders)) {
                        $field_nodes    = array();
                        foreach ($staticsync_mapfolders as $mapfolder) {
                            $match = $mapfolder["match"];
                            $field = $mapfolder["field"];
                            $level = $mapfolder["level"];
                            if (strpos("/" . $shortpath, $match) !== false) {
                                # Match. Extract metadata.
                                $path_parts = explode("/", $shortpath);
                                if ($level < count($path_parts)) {
                                    // special cases first.
                                    if ($field == 'access') {
                                        # access level is a special case
                                        # first determine if the value matches a defined access level
                                        $value = $path_parts[$level - 1];
                                        for ($n = 0; $n < 3; $n++) {
                                            # if we get an exact match or a match except for case
                                            if ($value == $lang["access" . $n] || strtoupper($value) == strtoupper($lang['access' . $n])) {
                                                $accessval = $n;
                                                echo " - Will set access level to " . $lang['access' . $n] . " ($n)" . PHP_EOL;
                                            }
                                        }
                                    } elseif ($field == 'archive') {
                                        # archive level is a special case
                                        # first determine if the value matches a defined archive level
                                        $value = $mapfolder["archive"];
                                        $archive_array = array_merge(array(-2,-1,0,1,2,3), $additional_archive_states);
                                        if (in_array($value, $archive_array)) {
                                            $archiveval = $value;
                                            echo " - Will set archive level to " . $lang['status' . $value] . " ($archiveval)" . PHP_EOL;
                                        }
                                    } elseif (is_int_loose($field)) {
                                        # Save the value
                                        $value = $path_parts[$level - 1];
                                        $modifiedval = hook('staticsync_mapvalue', '', array($r, $value, $field));
                                        if ($modifiedval) {
                                            $value = $modifiedval;
                                        }
                                        $field_info = get_resource_type_field($field);
                                        if (in_array($field_info['type'], $FIXED_LIST_FIELD_TYPES)) {
                                            $fieldnodes = get_nodes($field, null, $field_info['type'] == FIELD_TYPE_CATEGORY_TREE);

                                            if (in_array($value, array_column($fieldnodes, "name")) || ($field_info['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && !checkperm('bdk' . $field))) {
                                                // Add this to array of nodes to add

                                                if ($field_info['type'] == FIELD_TYPE_CATEGORY_TREE) {
                                                    # Use value found in category tree
                                                    $category_tree_values = array_filter($fieldnodes, function (array $fieldnodes) use ($value) {
                                                        return $value == $fieldnodes['name'];
                                                    });
                                                    $newnode = array_values($category_tree_values)[0]['ref']; # If multiple values found (category tree "leaves") we must pick one, taking first in array i.e. lowest node ref.
                                                    echo " - Using category tree node $newnode - $value" . "\n";
                                                } else {
                                                    # Add new field for dynamic keywords list
                                                    $newnode = set_node(null, $field, trim($value), null, null);
                                                    echo " - Adding node" . trim($value) . "\n";
                                                }

                                                $newnodes = array($newnode);

                                                if ($field_info['type'] == FIELD_TYPE_CATEGORY_TREE && $category_tree_add_parents) {
                                                    // We also need to add all parent nodes for category trees
                                                    $parent_nodes = get_parent_nodes($newnode);
                                                    $newnodes = array_merge($newnodes, array_keys($parent_nodes));
                                                }

                                                if ($staticsync_extension_mapping_append_values && !in_array($field_info['type'], array(FIELD_TYPE_DROP_DOWN_LIST,FIELD_TYPE_RADIO_BUTTONS)) && (!isset($staticsync_extension_mapping_append_values_fields) || in_array($field_info['ref'], $staticsync_extension_mapping_append_values_fields))) {
                                                    // The $staticsync_extension_mapping_append_values variable actually refers to folder->metadata mapping, not the file extension
                                                    $curnodes = get_resource_nodes($r, $field);
                                                    $field_nodes[$field]   = array_merge($curnodes, $newnodes, $field_nodes[$field] ?? []);
                                                } else {
                                                    // We have got a new value for this field and we are not appending values,
                                                    // replace any existing value the array
                                                    $field_nodes[$field]   = $newnodes;
                                                }
                                            }
                                        } else {
                                            if (
                                                $staticsync_extension_mapping_append_values
                                                && (!isset($staticsync_extension_mapping_append_values_fields)
                                                    || in_array($field_info['ref'], $staticsync_extension_mapping_append_values_fields))
                                                && in_array(
                                                    $field_info['type'],
                                                    [
                                                        FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
                                                        FIELD_TYPE_TEXT_BOX_MULTI_LINE,
                                                        FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE,
                                                        FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE,
                                                        FIELD_TYPE_DATE,FIELD_TYPE_WARNING_MESSAGE,
                                                    ]
                                                )
                                            ) {
                                                // Append the values if possible
                                                $existing_value  = get_data_by_field($r, $field);
                                                if ((string) $existing_value != "") {
                                                    $values_to_add[$field] = $existing_value .
                                                        ($staticsync_extension_mapping_append_separator ?? " ") .
                                                        $value;
                                                } else {
                                                    $values_to_add[$field] = $value;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if (count($field_nodes) > 0) {
                            $nodes_to_add = array();
                            foreach ($field_nodes as $nodeids) {
                                $nodes_to_add = array_merge($nodes_to_add, $nodeids);
                            }
                        }
                    }

                    # Update resource table
                    $setvals = array();
                    $setvals["access"] = $accessval;

                    if (isset($archiveval)) {
                        $setvals["archive"] = $archiveval;
                    } else {
                        $setvals["archive"] = $staticsync_defaultstate;
                    }

                    if (!$enable_thumbnail_creation_on_upload) {
                        $setvals["has_image"] = 0;
                        $setvals["preview_attempts"] = 0;
                    }

                    $updatesql = array();
                    $params = [];

                    foreach ($setvals as $name => $val) {
                        $updatesql[] = $name . "= ? ";
                        $params[] = 'i';
                        $params[] = $val;
                    }

                    $params[] = 'i';
                    $params[] = $r;

                    ps_query("UPDATE resource SET " . implode(",", $updatesql) . " WHERE ref = ?", $params);
                    unset($GLOBALS["get_resource_data_cache"][$r]);

                    if (count($nodes_to_add ?? [])) {
                        echo " - adding nodes " . implode(", ", $nodes_to_add) . "to resource: $r" . PHP_EOL;
                        add_resource_nodes($r, $nodes_to_add);
                        $joins = get_resource_table_joins();
                        foreach ($nodes_to_add as $node) {
                            $node_data = [];
                            if (get_node($node, $node_data) && in_array($node_data["resource_type_field"], $joins)) {
                                update_resource_field_column($r, $node_data["resource_type_field"], $node_data["name"]);
                            }
                        }
                    }

                    if (count($values_to_add ?? [])) {
                        foreach ($values_to_add as $field => $value) {
                            echo " - adding value $value in field ref: $field to resource: $r" . PHP_EOL;
                            $errors = [];
                            $result = update_field($r, $field, $value);
                        }
                    }

                    if (isset($staticsync_filepath_to_field)) {
                        update_field($r, $staticsync_filepath_to_field, $shortpath);
                    }

                    # Add any alternative files
                    $altpath = $fullpath . $staticsync_alternatives_suffix;
                    if ($staticsync_ingest && file_exists($altpath)) {
                        $adh = opendir($altpath);
                        while (($altfile = readdir($adh)) !== false) {
                            $filetype = filetype($altpath . "/" . $altfile);
                            if (($filetype == "file") && (substr($file, 0, 1) != ".") && (strtolower($file) != "thumbs.db")) {
                                # Create alternative file
                                # Find extension
                                $ext = explode(".", $altfile);
                                $ext = $ext[count($ext) - 1];

                                $description = str_replace("?", strtoupper($ext), $lang["originalfileoftype"]);
                                $file_size   = filesize_unlimited($altpath . "/" . $altfile);

                                $aref = add_alternative_file($r, $altfile, $description, $altfile, $ext, $file_size);
                                $path = get_resource_path($r, true, '', true, $ext, -1, 1, false, '', $aref);
                                $result = copy($altpath . "/" . $altfile, $path); // Copy alternative file instead of rename so that permissions of filestore will be used
                                if ($result === false) {
                                    # The copy failed.
                                    debug(" - ERROR: Staticsync failed to copy alternative file from: " .  $altpath . "/" . $altfile);
                                    return false;
                                }
                                $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
                                $GLOBALS["use_error_exception"] = true;
                                try {
                                    unlink($altpath . "/" . $altfile);
                                    try {
                                        chmod($path, 0777);
                                    } catch (Exception $e) {
                                        // Not fatal, just log
                                        debug(" - ERROR: Staticsync failed to set permissions on ingested alternative file: " .  $path . PHP_EOL . " - Error message: " . $e->getMessage() . PHP_EOL);
                                    }
                                } catch (Exception $e) {
                                    echo " - ERROR: failed to delete file from source. Please check correct permissions on: " .  $syncdir . "/" . $shortpath . "\n - Error message: "  . $e->getMessage() . PHP_EOL;
                                    return false;
                                }
                                $GLOBALS["use_error_exception"] = $use_error_exception_cache;
                            }
                        }
                    } elseif (isset($staticsync_alternative_file_text)) {
                        $basefilename = str_ireplace(".$extension", '', $file);
                        $altfilematch = "/{$basefilename}{$staticsync_alternative_file_text}(.*)\.(.*)/";

                        echo " - Searching for alternative files for base file: " . $basefilename , PHP_EOL;
                        echo " - Checking " . $altfilematch . PHP_EOL;

                        $folder_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder));
                        $altfiles = new RegexIterator($folder_files, $altfilematch, RecursiveRegexIterator::MATCH);
                        foreach ($altfiles as $altfile) {
                            staticsync_process_alt($altfile->getPathname(), $r);
                            echo " - Processed alternative: " . $shortpath . PHP_EOL;
                        }
                    }

                    # Add to collection
                    if ($staticsync_autotheme && !$skipfc_create) {
                        // Featured collection categories cannot contain resources. At this stage we need to distinguish
                        // between categories and collections by checking for children collections.
                        if (!is_featured_collection_category_by_children($collection)) {
                            $test = ps_query("SELECT " . columns_in("collection_resource") . " FROM collection_resource WHERE collection= ? AND resource= ?", ['i', $collection, 'i', $r]);
                            if (count($test) == 0) {
                                ps_query("INSERT INTO collection_resource (collection, resource, date_added) VALUES (?, ?, NOW())", ['i', $collection, 'i', $r]);
                                $featured_collection_parent = validate_collection_parent(get_collection($collection, false));
                                if (!in_array($featured_collection_parent, $GLOBALS['fcs_to_reorder'])) {
                                    $GLOBALS['fcs_to_reorder'][] = $featured_collection_parent;
                                }
                            }
                        } else {
                            echo " - Error: Unable to add resource to a featured collection category!" . PHP_EOL;
                            exit(1);
                        }
                    }

                    $done[$shortpath]["ref"] = $r;
                    $done[$shortpath]["processed"] = true;
                    $done[$shortpath]["modified"] = date('Y-m-d H:i:s', time());
                    update_disk_usage($r);
                } else {
                    # Import failed - file still being uploaded?
                    echo " *** Skipping file - it was not possible to move the file (still being imported/uploaded?)" . PHP_EOL;
                }
            } elseif ($staticsync_ingest_force) {
                // If the resource has a path but $ingest is true then the $ingest has been changed, need to copy original file into filestore
                global $get_resource_path_fpcache;
                $existing = $done[$shortpath]["ref"];
                $alternative = isset($done[$shortpath]["alternative"]) ? $done[$shortpath]["alternative"] : -1;

                echo " - File already imported - $shortpath (resource #$existing, alternative #$alternative). Ingesting.." . PHP_EOL;

                $get_resource_path_fpcache[$existing] = ""; // Forces get_resource_path to ignore the syncdir file_path
                $destination = get_resource_path($existing, true, "", true, $extension, -1, 1, false, "", $alternative);
                $result = copy($syncdir . "/" . $shortpath, $destination); // Copy instead of rename so that permissions of filestore will be used

                if ($result === false) {
                    # The copy failed.
                    debug(" - ERROR: Staticsync failed to copy file from: " .  $syncdir . "/" . $shortpath);
                    return false;
                }

                $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
                $GLOBALS["use_error_exception"] = true;
                try {
                    unlink($syncdir . "/" . $shortpath);
                    try {
                        chmod($destination, 0777);
                    } catch (Exception $e) {
                        // Not fatal, just log
                        debug(" - ERROR: Staticsync failed to set permissions on ingested file: " .  $destination . PHP_EOL . " - Error message: " . $e->getMessage());
                    }
                } catch (Exception $e) {
                    echo " - ERROR: failed to delete file from source. Please check correct permissions on: " .  $syncdir . "/" . $shortpath . PHP_EOL . " - Error message: "  . $e->getMessage() . PHP_EOL;
                    return false;
                }
                $GLOBALS["use_error_exception"] = $use_error_exception_cache;

                if ($alternative == -1) {
                    ps_query("UPDATE resource SET file_path=NULL WHERE ref = ?", ['i', $existing]);
                } else {
                    ps_query("UPDATE resource_alt_files SET file_name = ? WHERE resource = ? AND ref= ?", ['s', $file, 'i', $existing, 'i', $alternative]);
                }
            } elseif (
                !isset($done[$shortpath]["archive"]) // Check modified times and and update previews if no existing archive state is set,
                    || (isset($resource_deletion_state) && $done[$shortpath]["archive"] != $resource_deletion_state) // or if resource is not in system deleted state,
                    || (isset($staticsync_revive_state) && $done[$shortpath]["archive"] == $staticsync_deleted_state)
            ) { // or resource is currently in staticsync deleted state and needs to be reinstated
                if (!file_exists($fullpath)) {
                    echo " - Warning: File '{$fullpath}' does not exist anymore!";
                    continue;
                }

                $filemod = filemtime($fullpath);
                if (isset($done[$shortpath]["modified"]) && $filemod > strtotime($done[$shortpath]["modified"]) || (isset($staticsync_revive_state) && $done[$shortpath]["archive"] == $staticsync_deleted_state)) {
                    $count++;
                    # File has been modified since we last created previews. Create again.
                    $rd = ps_query("SELECT ref, has_image, file_modified, file_extension, archive FROM resource WHERE file_path= ?", ['s', $shortpath]);
                    if (count($rd) > 0) {
                        $rd   = $rd[0];
                        $rref = $rd["ref"];

                        echo " - Resource $rref has changed, regenerating previews: $fullpath" . PHP_EOL;

                        if (isset($done[$shortpath]["modified"]) && $filemod > strtotime($done[$shortpath]["modified"])) {
                            resource_log($rref, LOG_CODE_STATICSYNC_FILE_MODIFIED, '', '',  date("Y-m-d H:i:s", strtotime($done[$shortpath]["modified"])),  date("Y-m-d H:i:s", $filemod));
                        }

                        extract_exif_comment($rref, $rd["file_extension"]);

                        # extract text from documents (e.g. PDF, DOC).
                        global $extracted_text_field, $extracted_text_extensions;
                        if (
                            isset($extracted_text_field)
                            && in_array($extension, $extracted_text_extensions)
                            ) {
                            if (isset($unoconv_path) && in_array($extension, $unoconv_extensions)) {
                                // omit, since the unoconv process will do it during preview creation below
                            } else {
                                global $offline_job_queue, $offline_job_in_progress;
                                if ($offline_job_queue && !$offline_job_in_progress) {
                                    $extract_text_job_data = array(
                                        'ref'       => $rref,
                                        'extension' => $extension,
                                    );

                                    job_queue_add('extract_text', $extract_text_job_data);
                                } else {
                                    extract_text($rref, $extension);
                                }
                            }
                        }

                        # Store original filename in field, if set
                        global $filename_field;
                        if (isset($filename_field)) {
                            update_field($rref, $filename_field, $file);
                        }
                        if ($enable_thumbnail_creation_on_upload) {
                            create_previews($rref, false, $rd["file_extension"], false, false, -1, false, $staticsync_ingest);
                        }
                        $sql = '';
                        $params = [];
                        if (isset($staticsync_revive_state) && ($rd["archive"] == $staticsync_deleted_state)) {
                            $sql .= ", archive= ?";
                            $params[] = 'i';
                            $params[] = $staticsync_revive_state;
                        }
                        $params[] = 'i';
                        $params[] = $rref;
                        ps_query("UPDATE resource SET file_modified=NOW() " . $sql . ((!$enable_thumbnail_creation_on_upload) ? ", has_image=0, preview_attempts=0 " : "") . " WHERE ref= ?", $params);

                        if (isset($staticsync_revive_state) && ($rd["archive"] == $staticsync_deleted_state)) {
                            # Log this
                            resource_log($rref, LOG_CODE_STATUS_CHANGED, '', '', $staticsync_deleted_state, $staticsync_revive_state);
                        }
                    }
                }
            }
        }
    }
    closedir($dh);
}

function staticsync_process_alt($alternativefile, $ref = "", $alternative = "")
{
    // Process an alternative file
    global $staticsync_alternative_file_text, $syncdir, $lang, $staticsync_ingest, $alternative_file_previews,
    $done, $filename_field, $view_title_field, $staticsync_title_includes_path, $staticsync_alt_suffixes, $staticsync_alt_suffix_array;

    $shortpath = str_replace($syncdir . '/', '', $alternativefile);
    if (!isset($done[$shortpath])) {
        $alt_parts = pathinfo($alternativefile);

        if (substr($alt_parts['filename'], 0, 1) == ".") {
            return false;
        }

        if (isset($staticsync_alternative_file_text) && strpos($alternativefile, $staticsync_alternative_file_text) !== false) {
            $altfilenameparts = explode($staticsync_alternative_file_text, $alt_parts['filename']);
            $altbasename = $altfilenameparts[0];
            $altdesc = $altfilenameparts[1];
            $altname = str_replace("?", strtoupper($alt_parts["extension"]), $lang["fileoftype"]);
        } elseif (isset($staticsync_alt_suffixes) && $staticsync_alt_suffixes && is_array($staticsync_alt_suffix_array)) {
            // Check for files with a suffix defined in the $staticsync_alt_suffixes array
            foreach ($staticsync_alt_suffix_array as $altsfx => $altname) {
                $altsfxlen = mb_strlen($altsfx);
                if (substr($alt_parts['filename'], -$altsfxlen) == $altsfx) {
                    $altbasename = substr($alt_parts['filename'], 0, -$altsfxlen);
                    $altdesc = strtoupper($alt_parts['extension']) . " " . $lang["file"];
                    break;
                }
            }
        }

        if ($ref == "") {
            // We need to find which resource this alternative file relates to
            echo " - Searching for primary resource related to " . $alternativefile . "  in " . $alt_parts['dirname'] . '/' . $altbasename . "." .  PHP_EOL;
            foreach ($done as $syncedfile => $synceddetails) {
                $syncedfile_parts = pathinfo($syncedfile);
                if (
                    strpos($syncdir . '/' . $syncedfile, $alt_parts['dirname'] . '/' . $altbasename . ".") !== false
                    || (isset($altsfx) && $syncdir . '/' . $syncedfile_parts["filename"] . $altsfx . "." . $syncedfile_parts["extension"] ==  $alternativefile)
                ) {
                    // This synced file has the same base name as the resource
                    $ref = $synceddetails["ref"];
                    break;
                }
            }
        }

        if ($ref == "") {
            //Primary resource file may have been ingested on a previous run - try to locate it
            $ingested = ps_array(
                "SELECT resource value
                FROM resource_node rn
                    LEFT JOIN node n ON n.ref=rn.node
                WHERE n.resource_type_field = ?
                    AND n.name LIKE ?",
                ["i",$filename_field,"s",$altbasename . "%"]
            );

            if (count($ingested) < 1) {
                echo " - No primary resource found for " . $alternativefile . ". Skipping file" . PHP_EOL;
                debug("staticsync - No primary resource found for " . $alternativefile . ". Skipping file");
                return false;
            }

            if (count($ingested) == 1) {
                echo " - Found matching resource: " . $ingested[0] . PHP_EOL;
                $ref = $ingested[0];
            } else {
                if ($staticsync_title_includes_path) {
                    $title_find = array('/',   '_');
                    $title_repl = array(' - ', ' ');
                    $parentpath = ucfirst(str_ireplace($title_find, $title_repl, $shortpath));

                    echo " - This file has path: " . $parentpath . PHP_EOL;
                    foreach ($ingested as $ingestedref) {
                        $ingestedpath = get_data_by_field($ingestedref, $view_title_field);
                        echo "Found resource with same name. Path: " . $ingestedpath . PHP_EOL;
                        if (strpos($parentpath, $ingestedpath) !== false) {
                            echo " - Found matching resource: " . $ingestedref . PHP_EOL;
                            $ref = $ingestedref;
                            break;
                        }
                    }
                }
                if ($ref == "") {
                    echo " - Multiple possible primary resources found for " . $alternativefile . ". (Resource IDs: " . implode(",", $ingested) . "). Skipping file" . PHP_EOL;
                    debug("staticsync - Multiple possible primary resources found for " . $alternativefile . ". (Resource IDs: " . implode(",", $ingested) . "). Skipping file");
                    return false;
                }
            }
        }

        echo " - Processing alternative file - '" . $alternativefile . "' for resource #" . $ref . PHP_EOL;

        if ($alternative == "") {
            // Create a new alternative file
            $alt["file_size"]   = filesize_unlimited($alternativefile);
            $alt["extension"] = $alt_parts["extension"];
            $alt["altdescription"]  = $altdesc;
            $alt["name"]            = $altname;

            $alt["ref"] = add_alternative_file($ref, $alt["name"], $alt["altdescription"], $alternativefile, $alt["extension"], $alt["file_size"]);
            $alternative = $alt["ref"];

            echo " - Created a new alternative file - '" . $alt["ref"] . "' for resource #" . $ref . PHP_EOL;
            debug("Staticsync - Created a new alternative file - '" . $alt["ref"] . "' for resource #" . $ref);
            $alt["path"] = get_resource_path($ref, true, '', false, $alt["extension"], -1, 1, false, '', $alt["ref"]);
            echo " - Alternative file path - " . $alt["path"] . PHP_EOL;
            debug("Staticsync - alternative file path - " . $alt["path"]);
            $alt["basefilename"] = $altbasename;

            if ($staticsync_ingest) {
                echo " - Moving file to " . $alt["path"] . PHP_EOL;
                $result = copy($alternativefile, $alt["path"]); // Copy alternative file instead of rename so that permissions of filestore will be used

                if ($result === false) {
                    debug(" - ERROR: Staticsync failed to copy alternative file from: {$alternativefile}");
                    return false;
                }

                $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
                $GLOBALS["use_error_exception"] = true;
                try {
                    unlink($alternativefile);
                    try {
                        chmod($alt["path"], 0777);
                    } catch (Exception $e) {
                        // Not fatal, just log
                        debug(" - ERROR: Staticsync failed to set permissions on ingested alternative file: " . $alt["path"] . PHP_EOL . " - Error message: " . $e->getMessage() . PHP_EOL);
                    }
                } catch (Exception $e) {
                    echo " - ERROR: failed to delete file from source. Please check correct permissions on: " .  $alternativefile . PHP_EOL . " - Error message: "  . $e->getMessage() . PHP_EOL;
                    return false;
                }
                $GLOBALS["use_error_exception"] = $use_error_exception_cache;
            }

            if ($alternative_file_previews) {
                create_previews($ref, false, $alt["extension"], false, false, $alt["ref"], false, $staticsync_ingest);
            }

            hook("staticsync_after_alt", '', array($ref,$alt));
            echo " - Added alternative file ref:"  . $alt["ref"] . ", name: " . $alt["name"] . ". " . "(" . $alt["altdescription"] . ") Size: " . $alt["file_size"] . PHP_EOL;
            debug("Staticsync - added alternative file ref:"  . $alt["ref"] . ", name: " . $alt["name"] . ". " . "(" . $alt["altdescription"] . ") Size: " . $alt["file_size"]);
            $done[$shortpath]["processed"] = true;
        }
    } elseif ($alternative != "" && $alternative_file_previews) {
        // An existing alternative file has changed, update previews if required
        debug("Alternative file changed, recreating previews");
        create_previews($ref, false, pathinfo($alternativefile, PATHINFO_EXTENSION), false, false, $alternative, false, $staticsync_ingest);
        ps_query("UPDATE resource_alt_files SET creation_date=NOW() WHERE ref= ?", ['i', $alternative]);
        $done[$shortpath]["processed"] = true;
    }
    echo " - Completed path : " . $shortpath . PHP_EOL;
    $done[$shortpath]["ref"] = $ref;
    $done[$shortpath]["alternative"] = $alternative;
    set_process_lock("staticsync"); // Update the lock so we know it is still processing resources
}

# Recurse through the folder structure.
ProcessFolder($syncdir);

debug("StaticSync: \$done = " . json_encode($done));

echo " - Checking for alternative files that have not been processed" . PHP_EOL;

foreach ($alternativefiles as $alternativefile) {
    $shortpath = str_replace($syncdir . "/", '', $alternativefile);
    echo " - Processing alternative file " . $shortpath . PHP_EOL;
    debug("Staticsync -  Processing altfile " . $shortpath);

    if (array_key_exists($shortpath, $done) && isset($done[$shortpath]["alternative"]) && $done[$shortpath]["alternative"] > 0) {
        echo " - Alternative '{$shortpath}' has already been processed. Skipping" . PHP_EOL;
        continue;
    }

    if (!file_exists($alternativefile)) {
        echo " - Warning: File '{$alternativefile}' does not exist anymore!";
        continue;
    }

    if (!isset($done[$shortpath])) {
        staticsync_process_alt($alternativefile);
    } elseif ($alternative_file_previews) {
        // File already synced but check if it has been modified as may need to update previews
        $altfilemod = filemtime($alternativefile);
        if (isset($done[$shortpath]["modified"]) && $altfilemod > strtotime($done[$shortpath]["modified"])) {
            // Update the alternative file
            staticsync_process_alt($alternativefile, $done[$shortpath]["resource"], $done[$shortpath]["alternative"]);
        }
    }
}

echo " - Checking deleted files" . PHP_EOL;

if (!$staticsync_ingest && $staticsync_delete_resources) {
    # If not ingesting files, look for deleted files in the sync folder and archive the appropriate file from ResourceSpace.
    echo "Looking for deleted files..." . PHP_EOL;
    # For all resources with filepaths, check they still exist and archive if not.
    $resources_to_archive = array();
    $n = 0;

    foreach ($done as $syncedfile => $synceddetails) {
        if (!isset($synceddetails["processed"]) && isset($synceddetails["archive"]) && !(isset($staticsync_ignore_deletion_states) && in_array($synceddetails["archive"], $staticsync_ignore_deletion_states)) && $synceddetails["archive"] != $staticsync_deleted_state || isset($synceddetails["alternative"])) {
            $resources_to_archive[$n]["file_path"] = $syncedfile;
            $resources_to_archive[$n]["ref"] = $synceddetails["ref"];
            $resources_to_archive[$n]["archive"] = isset($synceddetails["archive"]) ? $synceddetails["archive"] : "";
            if (isset($synceddetails["alternative"])) {
                $resources_to_archive[$n]["alternative"] = $synceddetails["alternative"];
            }
            $n++;
        }
    }

    # ***for modified syncdir directories:
    $syncdonemodified = hook("modifysyncdonerf");
    if (!empty($syncdonemodified)) {
        $resources_to_archive = $syncdonemodified;
    }

    // Get all the featured collections (including categories) that hold these resources
    $fc_branches = get_featured_collections_by_resources(array_column($resources_to_archive, "ref"));

    foreach ($resources_to_archive as $rf) {
        $fp = $syncdir . '/' . $rf["file_path"];
        if (isset($rf['syncdir']) && $rf['syncdir'] != '') {
               # ***for modified syncdir directories:
               $fp = $rf['syncdir'] . $rf["file_path"];
        }

        if ($fp != "" && !file_exists($fp)) {
            // Additional check - make sure the archive state hasn't changed since the start of the script
            $cas = ps_value("SELECT archive value FROM resource where ref = ?", ['i', $rf['ref']], 0);
            if (isset($staticsync_ignore_deletion_states) && !in_array($cas, $staticsync_ignore_deletion_states)) {
                if (!isset($rf["alternative"])) {
                    echo " - File no longer exists: " . $rf["ref"] . " " . $fp . PHP_EOL;
                    # Set to archived, unless state hasn't changed since script started.
                    if (isset($staticsync_deleted_state)) {
                        ps_query("UPDATE resource SET archive= ? WHERE ref= ?", ['i', $staticsync_deleted_state, 'i', $rf['ref']]);
                    } else {
                        delete_resource($rf["ref"]);
                    }
                    if (isset($resource_deletion_state) && $staticsync_deleted_state == $resource_deletion_state) {
                        // Only remove from collections if we are really deleting this. Some configurations may have a separate state or synced resources may be temporarily absent
                        ps_query("DELETE FROM collection_resource WHERE resource= ?", ['i', $rf['ref']]);
                    }
                    # Log this
                    resource_log($rf['ref'], LOG_CODE_STATUS_CHANGED, '', '', $rf["archive"], $staticsync_deleted_state);
                } else {
                    echo " - Alternative file no longer exists: resource " . $rf["ref"] . " alt:" . $rf["alternative"] . " " . $fp . PHP_EOL;
                    ps_query("DELETE FROM resource_alt_files WHERE ref= ?", ['i', $rf['alternative']]);
                }
            }
        }
    }

    # Remove any themes that are now empty as a result of deleted files.
    echo " - Checking for empty featured collections" . PHP_EOL;
    foreach ($fc_branches as $fc_branch) {
        // Reverse the branch path to start from the leaf node. This way, when you reach the category you won't have any
        // children nodes (ie a normal FC) left (if it will be the case) and we'll be able to delete the FC category.
        $reversed_branch_path = array_reverse($fc_branch);
        foreach ($reversed_branch_path as $fc) {
            if (!can_delete_featured_collection($fc["ref"])) {
                continue;
            }

            if (delete_collection($fc["ref"]) === false) {
                echo " -- Unable to delete featured collection #{$fc["ref"]}" . PHP_EOL;
            } else {
                echo " -- Deleted featured collection #{$fc["ref"]}" . PHP_EOL;
            }
        }
    }

    echo " - Checking if featured collections have to be re-ordered (e.g if a category has become just a featured collection)" . PHP_EOL;
    foreach ($fcs_to_reorder as $fc_parent) {
        $new_fcs_order = reorder_all_featured_collections_with_parent($fc_parent);
        log_activity("via Static Sync, re-ordering for parent #{$fc_parent}", LOG_CODE_REORDERED, implode(', ', $new_fcs_order), 'collection');
    }
}

if (count($errors) > 0) {
    echo PHP_EOL . "ERRORS: -" . PHP_EOL;
    echo implode(PHP_EOL, $errors) . PHP_EOL;
    if ($send_notification) {
        $notify_users = get_notification_users("SYSTEM_ADMIN");
        foreach ($notify_users as $notify_user) {
            $admin_notify_users[] = $notify_user["ref"];
        }
        $message = "STATICSYNC ERRORS FOUND: - " . PHP_EOL . implode(PHP_EOL, $errors);
        message_add($admin_notify_users, $message);
    }
}

echo "\nStaticSync completed at " . date('Y-m-d H:i:s', time()) . PHP_EOL;

if ($suppress_output) {
    ob_clean();
}

ps_query("UPDATE sysvars SET value=now() WHERE name='lastsync'");
clear_query_cache("sysvars");
clear_process_lock("staticsync");
