<?php

# Resource functions
# Functions to create, edit and index resources

use Montala\ResourceSpace\CommandPlaceholderArg;

$GLOBALS['get_resource_path_fpcache'] = array();
/**
* Get resource path/ resource URL/ download URL for this resource
*
* IMPORTANT: the download URL should always be used client side (public)
*            whilst filstore path is private for internal use only
*
* @uses ps_value()
* @uses get_alternative_file()
* @uses get_resource_data()
*
* @param integer $ref              Resource ID
* @param boolean $getfilepath      Set to TRUE to get the filestore (physical) path
* @param string  $size             Specify which size of the resource should be returned. Use '' for original file
* @param boolean $generate         Generate folder if not found
* @param string  $extension        Extension of the file we are looking for. For original file, this would be the file
*                                  extension, otherwise use the preview extension (e.g image preview will have JPG
*                                  while video preview can have MP4 or others)
* @param boolean $scramble         Set to TRUE to get the scrambled folder (requires scramble key for it to work)
* @param integer $page             For documents, use the page number we are trying to get the preview of.
* @param boolean $watermarked      Get the watermark version?
* @param int     $file_modified    Specify when the file was last modified as a Unix timestamp
* @param integer $alternative      ID of the alternative file
* @param boolean $includemodified  Show when the file was last modified
*
* @return string
*/
function get_resource_path(
    $ref,
    $getfilepath,
    $size = '',
    $generate = true,
    $extension = 'jpg',
    $scramble = true,
    $page = 1,
    $watermarked = false,
    $file_modified = '',
    $alternative = -1,
    $includemodified = true
) {
    global $storagedir, $originals_separate_storage, $fstemplate_alt_threshold, $fstemplate_alt_storagedir,
    $fstemplate_alt_storageurl, $fstemplate_alt_scramblekey, $scramble_key, $hide_real_filepath,
    $migrating_scrambled, $scramble_key_old, $filestore_evenspread, $filestore_migrate,
    $baseurl, $k, $get_resource_path_extra_download_query_string_params;

    $int_vars = [&$ref, &$page, &$alternative];
    foreach ($int_vars as $var) {
        if (!is_int_loose($var)) {
            $var = (int) $var;
        }
    }

    $size = safe_file_name((string) $size);
    $extension = safe_file_name((string) $extension);

    if (!is_int_loose($file_modified)) {
        $file_modified = '';
    }

    # Returns the correct path to resource $ref of size $size ($size==empty string is original resource)
    # If one or more of the folders do not exist, and $generate=true, then they are generated
    if ($extension == 'icc') {
        # use the preview path
        $size = 'pre';
    }

    $refresh_key = '';
    // Determine v value early so that it can also be used when $hide_real_filepath is enabled
    if (!$getfilepath && $includemodified) {
        if ($file_modified == "") {
            # Work out the value from the file on disk
            $disk_path = get_resource_path($ref, true, $size, false, $extension, $scramble, $page, $watermarked, '', $alternative, false);
            if (file_exists($disk_path)) {
                $refresh_key = urlencode(filemtime($disk_path));
            }
        } else {
            # Use the provided value
            $refresh_key .= urlencode($file_modified);
        }
    }

    // Return URL pointing to download.php. download.php will call again get_resource_path() to ask for the physical path
    if (!$getfilepath && $hide_real_filepath) {
        if (
            !isset($get_resource_path_extra_download_query_string_params)
            || is_null($get_resource_path_extra_download_query_string_params)
            || !is_array($get_resource_path_extra_download_query_string_params)
        ) {
            $get_resource_path_extra_download_query_string_params = array();
        }

        return generateURL(
            "{$baseurl}/pages/download.php",
            array(
                'ref'         => $ref,
                'size'        => $size,
                'ext'         => $extension,
                'page'        => (int) $page,
                'alternative' => (int) $alternative,
                'watermarked' => $watermarked,
                'k'           => $k,
                'noattach'    => 'true',
                'v'           => $refresh_key,
            ),
            $get_resource_path_extra_download_query_string_params
        );
    }

    if ($size == "") {
        # For the full size, check to see if the full path is set and if so return that.
        global $get_resource_path_fpcache;
        truncate_cache_arrays();

        if (!isset($get_resource_path_fpcache[$ref])) {
            $get_resource_path_fpcache[$ref] = ps_value("select file_path value from resource where ref=?", array("i",$ref), "");
        }
        $fp = $get_resource_path_fpcache[$ref] ?? "";

        # Test to see if this nosize file is of the extension asked for, else skip the file_path and return a $storagedir path.
        # If using staticsync, file path will be set already, but we still want the $storagedir path for a nosize preview jpg.
        # Also, returning the original filename when a nosize 'jpg' is looked for is no good, since preview_preprocessing.php deletes $target.
        $fp = $fp ?? "";
        $test_ext = explode(".", $fp);
        $test_ext = trim(strtolower($test_ext[count($test_ext) - 1]));

        if (($test_ext == $extension || $alternative > 0) && strlen($fp) > 0 && (strpos($fp, "/") !== false || strlen($fp) > 1)) {
            if ($getfilepath) {
                global $syncdir;
                $syncdirmodified = hook("modifysyncdir", "all", array($ref, $fp, $alternative));
                if ($syncdirmodified != "") {
                    return $syncdirmodified;
                }
                if ($alternative <= 0) {
                    return $syncdir . "/" . $fp;
                } elseif (!$generate) {
                    // Alternative file and using staticsync. Would not be generating path if checking for an existing file.
                    // Check if file is present in syncdir, else continue to get the $storagedir location
                    $altfile = get_alternative_file($ref, $alternative);
                    if ($altfile["file_extension"] == $extension && file_exists($altfile["file_name"])) {
                        return $altfile["file_name"];
                    }
                }
            } else {
                global $baseurl_short, $k;
                return generateURL($baseurl_short . "pages/download.php", 
                    [
                        'ref'           => $ref, 
                        'size'          => $size, 
                        'ext'           => $extension, 
                        'k'             => $k, 
                        'page'          => $page, 
                        'alternative'   => $alternative,
                        'noattach'      => true
                    ]
                );
            }
        }
    }

    // Create a scrambled path using the scramble key
    // It should be very difficult or impossible to work out the scramble key, and therefore access
    // other resources, based on the scrambled path of a single resource.
    if ($scramble && isset($scramble_key) && '' != $scramble_key) {
        $skey = $scramble_key;

        // FSTemplate support - for trial system templates
        if (0 < $fstemplate_alt_threshold && $ref < $fstemplate_alt_threshold && -1 == $alternative) {
            $skey = $fstemplate_alt_scramblekey;
        }
        $scramblepath = substr(md5("{$ref}_{$skey}"), 0, 15);
    }

    if ($extension == "") {
        $extension = "jpg";
    }

    $folder = "";

    # Original separation support
    if ($originals_separate_storage) {
        global $originals_separate_storage_ffmpegalts_as_previews;
        if ($alternative > 0 && $originals_separate_storage_ffmpegalts_as_previews) {
            $alt_data = ps_query('select ref,resource,name,description,file_name,file_extension,file_size,creation_date,unoconv,alt_type,page_count from resource_alt_files where ref=?', array("i",$alternative));
            if (!empty($alt_data)) {
                // Determine if this file was created from $ffmpeg_alternatives
                $ffmpeg_alt = alt_is_ffmpeg_alternative($alt_data[0]);
                if ($ffmpeg_alt) {
                    $path_suffix = "/resized/";
                } else {
                    $path_suffix = "/original/";
                }
            } else {
                $path_suffix = "/original/";
            }
        } elseif ($size == "") {
            # Original file (core file or alternative)
            $path_suffix = "/original/";
        } else {
            # Preview or thumb
            $path_suffix = "/resized/";
        }
    } else {
        // If getting the physical path, use the appropriate directory separator. For URL, it can only use forward
        // slashes (/). For more information, see RFC 3986 (https://tools.ietf.org/html/rfc3986)
        $path_suffix = ($getfilepath ? DIRECTORY_SEPARATOR : "/");
    }

    for ($n = 0; $n < strlen($ref); $n++) {
        // If using $filestore_evenspread then the path is generated using the least significant figure first instead of the greatest significant figure
        $refpos = $filestore_evenspread ? -($n + 1) : $n;
        $folder .= substr($ref, $refpos, 1);

        if ($scramble && isset($scramblepath) && ($n == (strlen($ref) - 1))) {
            $folder .= "_" . $scramblepath;
        }

        $folder .= "/";
        if (!(file_exists($storagedir . $path_suffix . $folder)) && $generate) {
            $GLOBALS["use_error_exception"] = true;
            try {
                mkdir($storagedir . $path_suffix . $folder, 0777, true);
            } catch (Exception $e) {
                $returned_error = $e->getMessage();
                debug("get_resource_path - mkdir(): " . $returned_error);
                if (substr($returned_error, strpos($returned_error, 'mkdir(): ') + 9) != 'File exists') {
                    trigger_error($returned_error, E_USER_WARNING);
                }
            }
            unset($GLOBALS["use_error_exception"]);
            chmod($storagedir . $path_suffix . $folder, 0777);
        }
    }

    # Add the page to the filename for everything except page 1.
    if ($page == 1) {
        $p = "";
    } else {
        $p = "_" . $page;
    }

    # Add the alternative file ID to the filename if provided
    if ($alternative > 0) {
        $a = "_alt_" . $alternative;
    } else {
        $a = "";
    }

    # Add the watermarked url too
    if ($watermarked) {
        $p .= "_wm";
    }

    $sdir = $storagedir;

    # FSTemplate support - for trial system templates
    if ($fstemplate_alt_threshold > 0 && $ref < $fstemplate_alt_threshold && $alternative == -1) {
        $sdir = $fstemplate_alt_storagedir;
    }
    # switch the size back so the icc profile name matches the original name and find the original extension
    $icc = false;
    if ($extension == 'icc') {
        $size = '';
        $icc = true;
        $extension = ps_value("select file_extension value from resource where ref=?", array("i",$ref), 'jpg');
    }

    $filefolder = $sdir . $path_suffix . $folder;

    # Fetching the file path? Add the full path to the file
    if ($getfilepath) {
        $folder = $filefolder;
    } else {
        global $storageurl;
        $surl = $storageurl;

        # FSTemplate support - for trial system templates
        if ($fstemplate_alt_threshold > 0 && $ref < $fstemplate_alt_threshold && $alternative == -1) {
            $surl = $fstemplate_alt_storageurl;
        }

        $folder = $surl . $path_suffix . $folder;
    }
    if ($scramble && isset($skey)) {
        $file_old = $filefolder . $ref . $size . $p . $a . "." . $extension;
        $file_new = $filefolder . $ref . $size . $p . $a . "_" . substr(md5($ref . $size . $p . $a . $skey), 0, 15) . "." . $extension;
        $file = $folder . $ref . $size . $p . $a . "_" . substr(md5($ref . $size . $p . $a . $skey), 0, 15) . "." . $extension;
        if (file_exists($file_old)) {
            rename($file_old, $file_new);
        }
    } else {
        $file = $folder . $ref . $size . $p . $a . "." . $extension;
    }

    if ($icc) {
        $file .= '.icc';
    }

    if (trim($refresh_key) != '') {
        $file .= "?v={$refresh_key}";
    }

    if (($scramble && isset($migrating_scrambled) && $migrating_scrambled) || ($filestore_migrate && $filestore_evenspread)) {
        // Check if there is a file at the path using no/previous scramble key or with $filestore_evenspread=false;
        // Most will normally have been moved using pages/tools/xfer_scrambled.php or pages/tools/filestore_migrate.php

        // Flag to set whether we are migrating to even out filestore distibution or because of scramble key change
        $redistribute_mode = $filestore_migrate;

        // Get the new paths without migrating to prevent infinite recursion
        $migrating_scrambled = false;
        $filestore_migrate = false;
        $newpath = $getfilepath ? $file : get_resource_path($ref, true, $size, true, $extension, true, $page, false, '', $alternative);

        // Use old settings to get old path before migration and migrate if found
        if ($redistribute_mode) {
            $filestore_evenspread = false;
        } else {
            $scramble_key_saved = $scramble_key;
            $scramble_key = isset($scramble_key_old) ? $scramble_key_old : "";
        }

        if ($icc) {
            $extension = 'icc';
        }

        $oldfilepath = get_resource_path($ref, true, $size, false, $extension, true, $page, false, '', $alternative);
        if (file_exists($oldfilepath)) {
            if (!file_exists(dirname($newpath))) {
                mkdir(dirname($newpath), 0777, true);
            }
            rename($oldfilepath, $newpath);
        }

        // Reset key/evenspread value
        if ($redistribute_mode) {
            $filestore_evenspread = true;
            $filestore_migrate = true;
        } else {
            $scramble_key = $scramble_key_saved;
            $migrating_scrambled = true;
        }
    }

    if (
        !file_exists($file)
        && !$getfilepath
        && $alternative == -1
        && !$generate
        && !defined("GETRESOURCEPATHNORECURSE" . $ref)
    ) {
        $resdata = get_resource_data($ref);
        if ((int)$resdata["has_image"] === RESOURCE_PREVIEWS_NONE) {
            $pullresource = related_resource_pull($resdata);
            if ($pullresource !== false) {
                define("GETRESOURCEPATHNORECURSE" . $ref, true);
                if ($size == "hpr" && is_jpeg_extension($pullresource["file_extension"])) {
                    // If a JPG then no 'hpr' will be available
                    $size = "";
                }
                $file = get_resource_path($pullresource["ref"], $getfilepath, $size, false, $extension, $scramble, $page, $watermarked, $file_modified, -1, $includemodified);
            }
        }
    }

    return $file;
}

$GLOBALS['get_resource_data_cache'] = array();
function get_resource_data($ref, $cache = true)
{
    if ((string)(int)$ref != (string)$ref) {
        return false;
    }
    # Returns basic resource data (from the resource table alone) for resource $ref.
    # For 'dynamic' field data, see get_resource_field_data
    global $default_resource_type, $get_resource_data_cache;
    if ($cache && isset($get_resource_data_cache[$ref])) {
        return $get_resource_data_cache[$ref];
    }
    truncate_cache_arrays();

    # Build a string that will return the 'join' columns (not actually joins but cached truncated metadata stored at the resource level)
    $joins = get_resource_table_joins();
    $join_fields = empty($joins) ? '' : ',' . implode(',', array_map(prefix_value('field'), $joins));

    $resource = ps_query("SELECT " . columns_in("resource") . $join_fields . " FROM resource WHERE ref=?", array("i",$ref));
    if (count($resource) == 0) {
        if ($ref >= 0) {
            return false;
        } else {
            # For upload templates (negative reference numbers), generate a new resource if upload permission.
            if (!(checkperm("c") || checkperm("d"))) {
                return false;
            } elseif (!hook('replace_upload_template_creation', '', array($ref))) {
                global $userref;
                $user = $userref;

                $default_archive_state = get_default_archive_state();
                ps_query(
                    "INSERT INTO resource (ref,resource_type,created_by, archive) VALUES (?,?,?,?)",
                    ["i",$ref,"i",$default_resource_type,"i",$user,"i",$default_archive_state]
                );

                $resource = ps_query("SELECT " . columns_in("resource") . " FROM resource WHERE ref=?", array("i",$ref));
            }
        }
    }

    if (isset($resource[0])) {
        $get_resource_data_cache[$ref] = $resource[0];
        return $resource[0];
    } else {
        return false;
    }
}

/**
 * get_resource_data_batch - get data from resource table for all resource IDs
 *
 * @param  mixed $refs - array of resource IDs
 * @return array
 */
function get_resource_data_batch($refs)
{
    global $get_resource_data_cache;
    truncate_cache_arrays();

    $resids = array_values(array_unique(array_filter($refs, 'is_int_loose')));
    if (count($resids) === 0) {
        return array();
    }
    $resids_chunked = count($resids) <= SYSTEM_DATABASE_IDS_CHUNK_SIZE
        ? [$resids]
        : array_chunk($resids, SYSTEM_DATABASE_IDS_CHUNK_SIZE);

    # Build a string that will return the 'join' columns (not actually joins but cached truncated metadata stored at the resource level)
    $joins = get_resource_table_joins();
    $join_fields = empty($joins) ? '' : ',' . implode(',', array_map(prefix_value('field'), $joins));

    $resource_data = array();
    foreach ($resids_chunked as $resids_cnk) {
        $resdata = ps_query(
            sprintf(
                'SELECT %s%s FROM `resource` WHERE ref IN (%s)',
                columns_in('resource'),
                $join_fields,
                ps_param_insert(count($resids_cnk))
            ),
            ps_param_fill($resids_cnk, 'i')
        );
        foreach ($resdata as $resdatarow) {
            $resource_data[$resdatarow["ref"]] = $resdatarow;
            $get_resource_data_cache[$resdatarow["ref"]] = $resdatarow;
        }
    }

    return $resource_data;
}

/**
* Updates $resource with the name/value pairs in $data - this relates to the resource table column, not metadata.
*
* @param  int  $resource   ID of resource
* @param  array  $data     Array of data to be applied to resource
*
* @return boolean
*/
function put_resource_data($resource, $data)
{
    global $edit_contributed_by;

    // Check access
    if (!get_edit_access($resource)) {
        return false;
    }

    // Get current resource data
    $currentdata = get_resource_data($resource);

    // Define safe columns
    $safe_columns = ["resource_type","creation_date","rating","user_rating","archive","access","mapzoom","modified","geo_lat","geo_long","no_file"];
    $log_columns = [
        "resource_type" => LOG_CODE_EDITED_RESOURCE,
        "access" => LOG_CODE_ACCESS_CHANGED,
        "archive" => LOG_CODE_STATUS_CHANGED,
        "creation_date" => LOG_CODE_EDITED_RESOURCE,
        "geo_lat" => LOG_CODE_EDITED_RESOURCE,
        "geo_long" => LOG_CODE_EDITED_RESOURCE,
        "no_file" => [0 => LOG_CODE_UNSET_NO_FILE, 1 => LOG_CODE_SET_NO_FILE],
        "locked" => [0 => LOG_CODE_UNLOCKED, 1 => LOG_CODE_LOCKED],
    ];
    $safe_column_types = array("i","s","d","i","i","i","d","s","s","s","i");

    // Permit the created by column to be changed also
    if (checkperm("v") && $edit_contributed_by) {
        $safe_columns[] = "created_by";
        $safe_column_types[] = 'i';
    }

    $sql = "";
    $params = array();
    $logupdates = [];
    foreach ($data as $column => $value) {
        if (!in_array($column, $safe_columns)) {
            // Attempted to update a column outside of the expected set
            return false;
        }
        if (isset($currentdata[$column]) && $value == $currentdata[$column]) {
            // No change
            continue;
        }
        if ($sql != "") {
            $sql .= ",";
        }
        $sql .= $column . "=?";
        $params[] = $safe_column_types[array_search($column, $safe_columns)]; // Fetch type to use
        $params[] = $value;
        // Add to $logupdates
        if (isset($log_columns[$column])) {
            // Set log value and type
            $logupdates[] = [
                is_array($log_columns[$column]) && isset($log_columns[$column][$value])
                    ? $log_columns[$column][$value]
                    : $log_columns[$column], // Log code
                $column, // Log note
                $currentdata[$column], // From value
                $value, // To value
            ];
        }
    }

    if ($sql == "") {
        return false;
    } // Nothing to do.
    $params[] = "i";
    $params[] = $resource;
    ps_query("UPDATE resource SET $sql WHERE ref=?", $params);
    if (count($logupdates) > 0) {
        db_begin_transaction("resource_log_updates");
        foreach ($logupdates as $logupdate) {
            resource_log($resource, $logupdate[0], 0, $logupdate[1], $logupdate[2], $logupdate[3]);
        }
        db_end_transaction("resource_log_updates");
    }
    return true;
}

/**
 * create_resource
 *
 * @param  integer  $resource_type      ID of target resource type
 * @param  integer  $archive            ID of target archive state, 999 if archived
 * @param  integer  $user               User ID, -1 for current user
 * @param  string   $origin             Source of resource, should not be blank
 * @param  string   $file_extension     If specified
 * @param  bool     $run_macro          Run autocomplete macros, can be disabled if they are run later in process
 *
 * @return mixed    false if invalid inputs given, integer of resource reference if resource is created
 */
function create_resource($resource_type, $archive = 999, $user = -1, $origin = '', $file_extension = '', $run_macro = true)
{
    # Create a new resource.
    global $k,$terms_upload;

    if (!is_numeric($archive)) {
        return false;
    }

    $alltypes = get_resource_types("", false, false, false);
    if (!in_array($resource_type, array_column($alltypes, "ref"))) {
        return false;
    }

    if ($archive == 999) {
        # Work out an appropriate default state
        for ($n = -2; $n < 3; $n++) {
            if (checkperm("e" . $n)) {
                $archive = $n;
                break;
            }
        }
    }

    if ($user == -1) {
        global $userref;
        $user = $userref;
    }

    // Set no_file=1 because at this stage a new resource technically has no file until a follow-up processing is done.
    ps_query(
        "INSERT INTO resource(resource_type,creation_date,archive,created_by,file_extension, no_file) VALUES (?,NOW(),?,?,?, 1)",
        ["i",$resource_type,"i",$archive,"i",$user,"s",$file_extension]
    );

    $insert = sql_insert_id();

    # set defaults for resource here (in case there are edit filters that depend on them)
    set_resource_defaults($insert);

    hook('resourcecreate', '', array($insert, $resource_type));

    # Autocomplete any blank fields
    if ($run_macro) {
        autocomplete_blank_fields($insert, true);
    }

    # Log this
    daily_stat("Create resource", $insert);

    resource_log($insert, LOG_CODE_CREATED, 0, $origin);
    if (upload_share_active() !== false) {
        # Terms needed to be accepted to complete upload
        $notes = ($terms_upload ? "Terms accepted" : "");
        resource_log($insert, LOG_CODE_EXTERNAL_UPLOAD, 0, $notes, '', $k . ' ('  . get_ip() . ')');
    }

    return $insert;
}

function update_hitcount($ref)
{
    global $resource_hit_count_on_downloads;

    # update hit count if not tracking downloads only
    if (!$resource_hit_count_on_downloads) {
        # greatest() is used so the value is taken from the hit_count column in the event that new_hit_count is zero to support installations that did not previously have a new_hit_count column (i.e. upgrade compatability).
        ps_query("update resource set new_hit_count=greatest(hit_count,new_hit_count)+1 where ref=?", array("i",$ref), false, -1, true, 0);
    }
}

/**
 * Save resource data
 *
 * IMPORTANT: inactive nodes should be left alone (don't add/remove) except when processing fixed list field types that
 * only hold one value (dropdown, radio). Plugins should determine this based on their use cases when hooking.
 *
 * @param int $ref
 * @param bool $multi
 * @param string|int $autosave_field
 * @return true|array List of errors if unsuccessful, true otherwise
 */
function save_resource_data($ref, $multi, $autosave_field = "")
{
    debug_function_call("save_resource_data", func_get_args());
    # Save all submitted data for resource $ref.
    # Also re-index all keywords from indexable fields.
    global $lang, $languages, $language, $FIXED_LIST_FIELD_TYPES,
           $DATE_FIELD_TYPES, $reset_date_field, $reset_date_upload_template,
           $edit_contributed_by, $new_checksums, $upload_review_mode, $blank_edit_template, $is_template, $NODE_FIELDS,
           $userref, $userresourcedefaults;

    hook("befsaveresourcedata", "", array($ref));
    // Ability to avoid editing conflicts by checking checksums.
    // NOTE: this should NOT apply to upload.
    $check_edit_checksums = true;

    if ($upload_review_mode) {
        $check_edit_checksums = false;
    }

    // Save resource defaults (functionality available for upload only)
    // Call it here so that if users have access to the field and want
    // to override it, they can do so
    if (0 > $ref) {
        set_resource_defaults($ref);

        $check_edit_checksums = false;
    }

    # Loop through the field data and save (if necessary)
    $errors = array();
    $fields = get_resource_field_data($ref, $multi, !hook("customgetresourceperms"));

    $expiry_field_edited = false;
    $resource_data = get_resource_data($ref);

    if ($resource_data["lock_user"] > 0 && $resource_data["lock_user"] != $userref) {
        $errors[] = get_resource_lock_message($resource_data["lock_user"]);
        return $errors;
    }

    # Load the configuration for the selected resource type. Allows for alternative notification addresses, etc.
    resource_type_config_override($resource_data["resource_type"]);

    # Set up arrays of node ids to add/remove. We can't remove all nodes as user may not have access
    $nodes_to_add               = [];
    $nodes_to_remove            = [];
    $oldnodenames               = [];
    $nodes_check_delete         = [];
    $resource_update_log_sql    = [];
    $ui_selected_node_values    = [];
    $all_current_field_nodes    = [];
    $new_node_values            = [];
    $updated_resources          = [];

    $node_not_active = fn(array $node): bool => !node_is_active($node);

    // All the nodes passed for editing. Some of them were already a value
    // of the fields while others have been added/removed
    $user_set_values = getval('nodes', [], false, 'is_array');

    // Initialise array to store new checksums that client needs after autosave, without which subsequent edits will fail
    $new_checksums = array();
    for ($n = 0; $n < count($fields); $n++) {
        if (
            !(
            checkperm('F' . $fields[$n]['ref'])
            || (checkperm("F*") && !checkperm('F-' . $fields[$n]['ref']))
            // If we hide on upload the field, there is no need to check values passed from the UI as there shouldn't be any
            || ((0 > $ref || $upload_review_mode) && $fields[$n]['hide_when_uploading'])
            )
            && ('' == $autosave_field || $autosave_field == $fields[$n]['ref']
                || (is_array($autosave_field) && in_array($fields[$n]['ref'], $autosave_field))
            )
        ) {
            // Fixed list  fields use node IDs directly
            if (in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES)) {
                debug("save_resource_data(): Checking nodes to add/ remove for field {$fields[$n]['ref']} - {$fields[$n]['title']}");

                // Get currently selected nodes for this field
                $current_field_nodes = get_resource_nodes($ref, $fields[$n]['ref']);
                $all_current_field_nodes = array_merge($all_current_field_nodes, $current_field_nodes);
                // Check if resource field data has been changed between form being loaded and submitted
                $post_cs = getval("field_" . $fields[$n]['ref'] . "_checksum", "");
                sort($current_field_nodes);
                $current_cs = md5(implode(",", $current_field_nodes));
                if ($check_edit_checksums && $post_cs != "" && $post_cs != $current_cs) {
                    $errors[$fields[$n]["ref"]] = i18n_get_translated($fields[$n]['title']) . ': ' . $lang["save-conflict-error"];
                    continue;
                }

                debug("save_resource_data(): Current nodes for resource " . $ref . ": " . implode(",", $current_field_nodes));

                // Work out nodes submitted by user
                if (
                    isset($user_set_values[$fields[$n]['ref']])
                    && !is_array($user_set_values[$fields[$n]['ref']])
                    && '' != $user_set_values[$fields[$n]['ref']]
                    && is_numeric($user_set_values[$fields[$n]['ref']])
                ) {
                    $ui_selected_node_values[] = $user_set_values[$fields[$n]['ref']];
                } elseif (
                    isset($user_set_values[$fields[$n]['ref']])
                    && is_array($user_set_values[$fields[$n]['ref']])
                ) {
                    $ui_selected_node_values = $user_set_values[$fields[$n]['ref']];
                }
                // Check nodes are valid for this field
                if (FIELD_TYPE_CATEGORY_TREE === $fields[$n]['type']) {
                    $all_tree_nodes_ordered = get_cattree_nodes_ordered($fields[$n]['ref'], null, true);
                    // remove the fake "root" node which get_cattree_nodes_ordered() is adding since we won't be using get_cattree_node_strings()
                    array_shift($all_tree_nodes_ordered);
                    $inactive_nodes = array_column(array_filter($all_tree_nodes_ordered, $node_not_active), 'ref');
                    $all_tree_nodes_ordered = array_values($all_tree_nodes_ordered);

                    $node_options = array_column($all_tree_nodes_ordered, 'name', 'ref');
                    $validnodes = array_keys($node_options);
                } else {
                    $fieldnodes   = get_nodes($fields[$n]['ref'], '', false);
                    $node_options = array_column($fieldnodes, 'name', 'ref');
                    $validnodes   = array_column($fieldnodes, 'ref');
                    $inactive_nodes = array_column(array_filter($fieldnodes, $node_not_active), 'ref');
                }

                // $validnodes are already sorted by the order_by (default for get_nodes). This is needed for the data_joins fields later
                $ui_selected_node_values = array_values(array_intersect($validnodes, $ui_selected_node_values));
                debug("save_resource_data(): UI selected nodes for resource {$ref}: " . implode(',', $ui_selected_node_values));

                // Set new value for logging
                $new_node_values = array_merge($new_node_values, $ui_selected_node_values);

                $added_nodes = array_diff($ui_selected_node_values, $current_field_nodes, $inactive_nodes);
                debug("save_resource_data(): Adding nodes to resource " . $ref . ": " . implode(",", $added_nodes));
                $nodes_to_add = array_merge($nodes_to_add, $added_nodes);

                if (
                    // We must release an inactive node if the type can only hold one value...
                    in_array($fields[$n]['type'], [FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_RADIO_BUTTONS])
                    // ...but prevent direct removals (ie. no value)
                    && $ui_selected_node_values !== []
                ) {
                    $removed_nodes = array_diff($current_field_nodes, $ui_selected_node_values);
                    $current_inactive_resource_field_nodes = [];
                } else {
                    $removed_nodes = array_diff($current_field_nodes, $ui_selected_node_values, $inactive_nodes);
                    $current_inactive_resource_field_nodes = array_intersect($current_field_nodes, $inactive_nodes);
                }
                debug("save_resource_data(): Removed nodes from resource " . $ref . ": " . implode(",", $removed_nodes));
                $nodes_to_remove = array_merge($nodes_to_remove, $removed_nodes);

                if (count($added_nodes) > 0 || count($removed_nodes) > 0) {
                    $new_nodevals = array();
                    // Build new value
                    foreach ($ui_selected_node_values as $ui_selected_node_value) {
                        if (FIELD_TYPE_CATEGORY_TREE === $fields[$n]['type']) {
                            $new_nodevals[] = implode(
                                '/',
                                array_column(
                                    compute_node_branch_path($all_tree_nodes_ordered, $ui_selected_node_value),
                                    'name'
                                )
                            );
                            continue;
                        }

                        $new_nodevals[] = $node_options[$ui_selected_node_value];
                    }
                    # Is this is a 'joined' field?
                    $joins = get_resource_table_joins();
                    if (in_array($fields[$n]["ref"], $joins)) {
                        $new_nodes_val = implode($GLOBALS['field_column_string_separator'], $new_nodevals);
                        if ((1 == $fields[$n]['required'] && "" != $new_nodes_val) || 0 == $fields[$n]['required']) { # If joined field is required we shouldn't be able to clear it.
                            update_resource_field_column($ref, $fields[$n]["ref"], $new_nodes_val);
                        }
                    }
                    $val = implode(",", $new_nodevals);
                    $ui_selected_node_values = array_merge($ui_selected_node_values, $current_inactive_resource_field_nodes);
                    sort($ui_selected_node_values);
                    $new_checksums[$fields[$n]['ref']] = md5(implode(',', $ui_selected_node_values));
                    $updated_resources[$ref][$fields[$n]['ref']] = $new_nodevals; // To pass to hook
                } else {
                    $val = "";
                }
            } // End of if in $FIXED_LIST_FIELD_TYPES
            else {
                if ($fields[$n]['type'] == FIELD_TYPE_DATE_RANGE) {
                    # date range type
                    # each value will be a node so we end up with a pair of nodes to represent the start and end dates

                    $daterangenodes = array();
                    $newval = "";

                    if (($date_edtf = getval("field_" . $fields[$n]["ref"] . "_edtf", "")) !== "") {
                        // We have been passed the range in EDTF format, check it is in the correct format
                        $rangeregex = "/^(\d{4})(-\d{2})?(-\d{2})?\/(\d{4})(-\d{2})?(-\d{2})?/";
                        if (!preg_match($rangeregex, $date_edtf, $matches)) {
                            $errors[$fields[$n]["ref"]] = $lang["information-regexp_fail"] . " : " . $date_edtf;
                            continue;
                        }
                        if (is_int_loose($fields[$n]["linked_data_field"])) {
                            // Update the linked field with the raw EDTF string submitted
                            update_field($ref, $fields[$n]["linked_data_field"], $date_edtf);
                        }
                        $rangedates = explode("/", $date_edtf);
                        $rangestart = str_pad($rangedates[0], 10, "-00");
                        $rangeendparts = explode("-", $rangedates[1]);
                        $rangeendyear = $rangeendparts[0];
                        $rangeendmonth = isset($rangeendparts[1]) ? $rangeendparts[1] : 12;
                        $rangeendday = isset($rangeendparts[2]) ? $rangeendparts[2] : cal_days_in_month(CAL_GREGORIAN, $rangeendmonth, $rangeendyear);
                        $rangeend = $rangeendyear . "-" . $rangeendmonth . "-" . $rangeendday;

                        $newval = $rangestart . DATE_RANGE_SEPARATOR . $rangeend;
                        $daterangenodes[] = set_node(null, $fields[$n]["ref"], $rangestart, null, null);
                        $daterangenodes[] = set_node(null, $fields[$n]["ref"], $rangeend, null, null);
                    } else {
                        // Range has been passed via normal inputs, construct the value from the date/time dropdowns
                        $date_parts = array("_start","_end");

                        foreach ($date_parts as $date_part) {
                            $val = getval("field_" . $fields[$n]["ref"] . $date_part . "-y", "");
                            if (intval($val) <= 0) {
                                $val = "";
                            } elseif (($field = getval("field_" . $fields[$n]["ref"] . $date_part . "-m", "")) != "") {
                                $val .= "-" . $field;
                                if (($field = getval("field_" . $fields[$n]["ref"] . $date_part . "-d", "")) != "") {
                                    $val .= "-" . $field;
                                } else {
                                    $val .= "-00";
                                }
                            } else {
                                $val .= "-00-00";
                            }

                            $newval .= ($newval != "" ? DATE_RANGE_SEPARATOR : "") . $val;
                            if ($val !== "") {
                                $daterangenodes[] = set_node(null, $fields[$n]["ref"], $val, null, null);
                            }
                        }
                    }
                    natsort($daterangenodes);

                    // Set new value for logging
                    $new_node_values = array_merge($new_node_values, $daterangenodes);

                    // Get currently selected nodes for this field
                    $current_field_nodes = get_resource_nodes($ref, $fields[$n]['ref'], false, SORT_ASC);
                    $all_current_field_nodes = array_merge($all_current_field_nodes, $current_field_nodes);

                        // Check if resource field data has been changed between form being loaded and submitted
                        $post_cs = getval("field_" . $fields[$n]['ref'] . "_checksum", "");
                        sort($current_field_nodes);
                        $current_cs = md5(implode(",", $current_field_nodes));
                    if ($check_edit_checksums && $post_cs != "" && $post_cs != $current_cs) {
                        $errors[$fields[$n]["ref"]] = i18n_get_translated($fields[$n]['title']) . ': ' . $lang["save-conflict-error"];
                        continue;
                    }

                    if ($daterangenodes !== $current_field_nodes) {
                        $added_nodes = array_diff($daterangenodes, $current_field_nodes);
                        debug("save_resource_data(): Adding nodes to resource " . $ref . ": " . implode(",", $added_nodes));
                        $nodes_to_add = array_merge($nodes_to_add, $added_nodes);
                        $removed_nodes = array_diff($current_field_nodes, $daterangenodes);
                        debug("save_resource_data(): Removed nodes from resource " . $ref . ": " . implode(",", $removed_nodes));
                        $nodes_to_remove = array_merge($nodes_to_remove, $removed_nodes);

                        $val = $newval;
                        # If this is a 'joined' field it still needs to be added to the resource column
                        $joins = get_resource_table_joins();
                        if (in_array($fields[$n]["ref"], $joins)) {
                            update_resource_field_column($ref, $fields[$n]["ref"], $newval);
                        }
                        sort($daterangenodes);
                        $new_checksums[$fields[$n]['ref']] = md5(implode(",", $daterangenodes));
                        $updated_resources[$ref][$fields[$n]['ref']][] = $newval; // To pass to hook
                    }
                } elseif (in_array($fields[$n]['type'], $DATE_FIELD_TYPES)) {
                    # date type, construct the value from the date/time dropdowns to be used in DB
                    $val = sanitize_date_field_input($fields[$n]["ref"], false);

                    // A proper input:date field
                    if ($GLOBALS['use_native_input_for_date_field'] && $fields[$n]['type'] === FIELD_TYPE_DATE) {
                        $val = getval("field_{$fields[$n]['ref']}", '');
                        if ($val !== '' && !validateDatetime($val, 'Y-m-d')) {
                            $errors[$fields[$n]['ref']] = $lang['error_invalid_date'] . ' : ' . $val;
                            continue;
                        }
                    }

                    // Upload template: always reset to today's date, if configured and field is hidden
                    if (
                        0 > $ref
                        && $reset_date_upload_template
                        && $reset_date_field == $fields[$n]['ref']
                        && $fields[$n]['hide_when_uploading']
                    ) {
                        if ($fields[$n]['type'] == FIELD_TYPE_DATE) {
                            $val = date('Y-M-d');
                        } elseif ($fields[$n]['type'] == FIELD_TYPE_DATE_AND_OPTIONAL_TIME) {
                            $val = date('Y-m-d H:i');
                        }
                    }

                    // Check if resource field data has been changed between form being loaded and submitted
                    $post_cs = getval("field_" . $fields[$n]['ref'] . "_checksum", "");
                    $current_cs = md5((string)$fields[$n]['value']);
                    if ($check_edit_checksums && $post_cs != "" && $post_cs != $current_cs) {
                        $errors[$fields[$n]["ref"]] = i18n_get_translated($fields[$n]['title']) . ': ' . $lang["save-conflict-error"];
                        continue;
                    }

                    $new_checksums[$fields[$n]['ref']] = md5($val);
                    $updated_resources[$ref][$fields[$n]['ref']][] = $val; // To pass to hook
                } else {

                    # Set the value exactly as sent.
                    $val = getval("field_" . $fields[$n]["ref"], "");
                    $rawval = getval("field_" . $fields[$n]["ref"], "");
                    # Check if resource field data has been changed between form being loaded and submitted
                    # post_cs is the checksum of the data when it was loaded from the database
                    # current_cs is the checksum of the data on the database now
                    # if they are the same then there has been no intervening update and so its ok to update with our new value
                    # if our new data yields a different checksum, then we know the new value represents a change
                    # the new checksum for the new value of a field is stored in $new_checksums[$fields[$n]['ref']]
                    $post_cs = getval("field_" . $fields[$n]['ref'] . "_checksum", "");
                    $current_cs = md5(trim(preg_replace('/\s\s+/', ' ', (string) $fields[$n]['value'])));

                    if ($check_edit_checksums && $post_cs != "" && $post_cs != $current_cs) {
                        $errors[$fields[$n]["ref"]] = i18n_get_translated($fields[$n]['title']) . ': ' . $lang["save-conflict-error"];
                        continue;
                    }

                    if ($fields[$n]['type'] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE
                        && html_entity_decode($val, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8') !== strip_tags_and_attributes($val, array("a"), array("href","target","rel","title"))) {
                        $errors[$fields[$n]["ref"]] = i18n_get_translated($fields[$n]['title']) . ': ' . $lang["save-error-invalid"];
                        continue;
                    }

                    $new_checksums[$fields[$n]['ref']] = md5(trim(preg_replace('/\s\s+/', ' ', $rawval)));
                    $updated_resources[$ref][$fields[$n]['ref']][] = $val; // To pass to hook
                }

                # Check for regular expression match
                if (strlen(trim((string)$fields[$n]["regexp_filter"])) >= 1 && strlen((string) $val) > 0) {
                    global $regexp_slash_replace;
                    if (preg_match("#^" . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . "$#", $val, $matches) <= 0) {
                        global $lang;
                        debug($lang["information-regexp_fail"] . ": -" . "reg exp: " . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . ". Value passed: " . $val);
                        $errors[$fields[$n]["ref"]] = $lang["information-regexp_fail"] . " : " . $val;
                        continue;
                    }
                }
                $modified_val = hook("modifiedsavedfieldvalue", '', array($fields,$n,$val));
                if (!empty($modified_val)) {
                    $val = $modified_val;
                    $new_checksums[$fields[$n]['ref']] = md5(trim(preg_replace('/\s\s+/', ' ', $val)));
                }

                $error = hook("additionalvalcheck", "all", array($fields, $fields[$n]));
                if ($error) {
                    $errors[$fields[$n]["ref"]] = $error;
                    continue;
                }
            } // End of if not a fixed list field

            // Determine whether a required field has a default for the user
            $field_has_default_for_user = false;
            if ($userresourcedefaults != '') {
                foreach (explode(';', $userresourcedefaults) as $rule) {
                    $rule_detail         = explode('=', trim($rule));
                    $field_shortname     = $rule_detail[0];
                    $field_default_value = $rule_detail[1];
                    if ($field_shortname  == $fields[$n]['name'] && $field_default_value != "") {
                        $field_has_default_for_user = true;
                        break;
                    }
                }
            }

            // Populate empty field with the default if necessary
            if ($field_has_default_for_user && strlen((string) $val) == 0) {
                $val = $field_default_value;
                $new_checksums[$fields[$n]['ref']] = md5(trim(preg_replace('/\s\s+/', ' ', $val)));
            }

            if (
                $fields[$n]['required'] == 1
                && check_display_condition($n, $fields[$n], $fields, false, $ref)
                && (
                    // Required node field with no nodes submitted is a candidate for error
                    (in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES) && count($ui_selected_node_values) == 0)
                    // Required continuous field with no value is a candidate for error
                    || (!in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES) && trim(strip_leading_comma($val)) == '')
                )
                && (
                    // An existing resource node field with neither any nodes submitted nor a resource default
                    ($ref > 0 && in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES) && count($ui_selected_node_values) == 0 && !$field_has_default_for_user)
                    // An existing resource continuous field with neither an input value nor a resource default
                    || ($ref > 0 && !in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES) && strlen((string) $val) == 0 && !$field_has_default_for_user)
                    // A template node field with neither any nodes submitted nor a resource default
                    || ($ref < 0 && in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES) && count($ui_selected_node_values) == 0 && !$field_has_default_for_user)
                    // A template continuous field with neither an input value nor a resource default
                    || ($ref < 0 && !in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES) && strlen((string) $val) == 0 && !$field_has_default_for_user)
                )
                // Not a metadata template
                && !$is_template
            ) {
                $field_visibility_status = getval("field_" . $fields[$n]['ref'] . "_displayed", "");
                # Register an error only if the empty required field was actually displayed
                if (is_field_displayed($fields[$n]) && $field_visibility_status == "block") {
                    $errors[$fields[$n]['ref']] = i18n_get_translated($fields[$n]['title']) . ": {$lang['requiredfield']}";
                    continue;
                }
            }

            // If all good so far, then save the data
            if (
                in_array($fields[$n]['type'], NON_FIXED_LIST_SINGULAR_RESOURCE_VALUE_FIELD_TYPES)
                && str_replace("\r\n", "\n", trim((string) $fields[$n]['value'])) !== str_replace("\r\n", "\n", trim((string) $val))
            ) {
                # This value is different from the value we have on record.
                # Expiry field? Set that expiry date(s) have changed so the expiry notification flag will be reset later in this function.
                if ($fields[$n]["type"] == FIELD_TYPE_EXPIRY_DATE) {
                    $expiry_field_edited = true;
                }

                $use_node = null;
                if (trim((string) $fields[$n]["nodes"]) != "") {
                    // Remove any existing node IDs for this non-fixed list field (there should only be one) unless used by other resources.
                    $current_field_nodes = array_filter(explode(",", $fields[$n]["nodes"]), "is_int_loose");
                    $all_current_field_nodes = array_merge($all_current_field_nodes, $current_field_nodes);
                    foreach ($current_field_nodes as $current_field_node) {
                        $inuse = get_nodes_use_count([$current_field_node]);
                        $inusecount = $inuse[$current_field_node] ?? 0;
                        if ($current_field_node > 0 && $inusecount == 1) {
                            // Reuse same node
                            $use_node = $current_field_node;
                        } else {
                            // Remove node from resource and create new node
                            $nodes_to_remove[] = $current_field_node;
                            $nodes_check_delete[] = $current_field_node;
                        }
                    }
                }

                # Add new node unless empty string
                if ($val == '') {
                    // Remove and delete node
                    $nodes_to_remove[] = $current_field_node;
                    $nodes_check_delete[] = $current_field_node;
                } else {
                    // Update the existing node
                    $newnode = set_node($use_node, $fields[$n]["ref"], $val, null, null);
                    if ((int)$newnode != (int)$use_node) {
                        // Node already exists, remove current node and replace
                        $nodes_to_add[] = $newnode;
                        $nodes_to_remove[] = $use_node;
                        $nodes_check_delete[] = $use_node;
                        // Set new value for logging
                        $new_node_values[] = $newnode;
                    } else {
                        $new_node_values[] = $use_node;
                    }

                    // Add to array for logging
                    if (!is_null($use_node)) {
                        $oldnodenames[$use_node] = $fields[$n]['value'];
                    }
                }

                # If this is a 'joined' field we need to add it to the resource column
                $joins = get_resource_table_joins();
                if (in_array($fields[$n]["ref"], $joins)) {
                    update_resource_field_column($ref, $fields[$n]["ref"], $val);
                }
            }

            # Add any onchange code if new checksum for field shows that it has changed
            if (
                isset($fields[$n]["onchange_macro"]) && $fields[$n]["onchange_macro"] !== ""
                    && isset($new_checksums[$fields[$n]["ref"]])
                    && $post_cs !== $new_checksums[$fields[$n]["ref"]]
                    && ($post_cs !== "" || (!$check_edit_checksums && $new_checksums[$fields[$n]["ref"]] != md5(trim(preg_replace('/\s\s+/', ' ', (string) $fields[$n]['value'] ?? '')))))
            ) {
                $macro_resource_id = $ref;
                eval(eval_check_signed($fields[$n]["onchange_macro"]));
            }
        } # End of if "allowed to edit field conditions"
    } # End of for $fields

    // When editing a resource, prevent applying the change to the resource if there are any errors
    if (count($errors) > 0 && $ref > 0) {
        return $errors;
    }

   # Save related resource field if value for Related input field is autosaved, or if form has been submitted by user
    if (($autosave_field == "" || $autosave_field == "Related") && isset($_POST["related"])) {
        # save related resources field
        $related = explode(",", getval("related", ""));
        # Trim whitespace from each entry
        foreach ($related as &$relatedentry) {
            $relatedentry = trim($relatedentry);
        }
        # Make sure all submitted values are numeric
        $to_relate = array_filter($related, "is_int_loose");

        $currently_related = get_related_resources($ref);
        $to_add = array_diff($to_relate, $currently_related);
        $to_delete = array_diff($currently_related, $to_relate);

        if (count($to_add) > 0) {
            update_related_resource($ref, $to_add, true);
        }
        if (count($to_delete) > 0) {
            update_related_resource($ref, $to_delete, false);
        }
    }

    // Update resource_node table
    db_begin_transaction("update_resource_node");
    if (count($nodes_to_remove) > 0) {
        delete_resource_nodes($ref, $nodes_to_remove, false);
    }

    if (count($nodes_to_add) > 0) {
        add_resource_nodes($ref, $nodes_to_add, false, false);
    }
    log_node_changes($ref, $new_node_values, $all_current_field_nodes, "", $oldnodenames);

    if (count($nodes_check_delete) > 0) {
        // This has to be after call to log_node_changes() or nodes cannot be resolved
        check_delete_nodes($nodes_check_delete);
    }

    db_end_transaction("update_resource_node");

    // Autocomplete any blank fields without overwriting any existing metadata
    $autocomplete_fields = autocomplete_blank_fields($ref, false, true);
    foreach ($autocomplete_fields as $autocomplete_field_ref => $autocomplete_field_value) {
        $new_checksums[$autocomplete_field_ref] = md5((string)$autocomplete_field_value);
    }

    // Initialise an array of updates for the resource table
    $resource_update_sql = array();
    $resource_update_params = array();
    if ($edit_contributed_by) {
        $created_by = $resource_data['created_by'];
        $new_created_by = getval("created_by", 0, true);
        if ((getval("created_by", 0, true) > 0) && $new_created_by != $created_by) {
            # Also update created_by
            $resource_update_sql[] = "created_by= ?";
            $resource_update_params[] = "i";
            $resource_update_params[] = $new_created_by;
            $olduser = get_user($created_by);
            $newuser = get_user($new_created_by);
            $resource_update_log_sql[] = array(
                    "ref" => $ref,
                    "type" => LOG_CODE_CREATED_BY_CHANGED,
                    "field" => 0,
                    "notes" => "",
                    "from" => $created_by . " (" . ($olduser["fullname"] == "" ? $olduser["username"] : $olduser["fullname"])  . ")","to" => $new_created_by . " (" . ($newuser["fullname"] == "" ? $newuser["username"] : $newuser["fullname"])  . ")");
        }
    }

    # Expiry field(s) edited? Reset the notification flag so that warnings are sent again when the date is reached.
    if ($expiry_field_edited) {
        $resource_update_sql[] = "expiry_notification_sent='0'";
    }

    if (!hook('forbidsavearchive', '', array($errors))) {
        # Also update archive status and access level
        $oldaccess = $resource_data['access'];
        $access = getval("access", $oldaccess, true);

        $oldarchive = $resource_data['archive'];
        $setarchivestate = getval("status", $oldarchive, true);
        if ($setarchivestate != $oldarchive && !checkperm("e" . $setarchivestate)) { // don't allow change if user has no permission to change archive state
            $setarchivestate = $oldarchive;
        }

        // Only if changed
        if (($autosave_field == "" || $autosave_field == "Status") && $setarchivestate != $oldarchive) {
            // Check if resource status has already been changed between form being loaded and submitted
            if (getval("status_checksum", "") != "" && getval("status_checksum", "") != $oldarchive) {
                $errors["status"] = $lang["status"] . ': ' . $lang["save-conflict-error"];
            } else {
                // update archive status if different (doesn't matter whether it is a user template or a genuine resource)
                if ($setarchivestate != $oldarchive) {
                    $missed_required_fields = update_archive_required_fields_check($ref, $setarchivestate);
                    if (count($missed_required_fields) > 0) {
                        global $additional_archive_states;
                        if (in_array($setarchivestate, $additional_archive_states)) {
                            $newarchivename = $lang['status' . $setarchivestate];
                        } else {
                            $newarchivename = $lang["status" . $setarchivestate];
                        }
                        // Array key used to reset drop down to old archive so user can try autosave again.
                        $errors['status' . (int) $oldarchive] = str_replace(array('%%STATUS%%', '%%FIELDS%%'), array($newarchivename,  implode(', ', array_column($missed_required_fields, 'title'))), $lang["requiredfields-status-required_fields"]);
                    } else {
                    update_archive_status($ref, $setarchivestate, array($oldarchive));
                    $new_checksums["status"] = $setarchivestate;
                    }
                }
            }
        }

        if (($autosave_field == "" || $autosave_field == "Access") && $access != $oldaccess) {
            // Check if resource access has already been changed between form being loaded and submitted
            if (getval("access_checksum", "") != "" && getval("access_checksum", "") != $oldaccess) {
                $errors["access"] = $lang["access"] . ': ' . $lang["save-conflict-error"];
            } else {
                $resource_update_sql[] = "access= ?";
                $resource_update_params[] = "i";
                $resource_update_params[] = $access;
                if ($access != $oldaccess && 0 < $ref) {
                    $resource_update_log_sql[] = array(
                        'ref'   => $ref,
                        'type'  => 'a',
                        'field' => 0,
                        'notes' => '',
                        'from'  => $oldaccess,
                        'to'    => $access);
                }

                if ($oldaccess == 3 && $access != 3) {
                    # Moving out of the custom state. Delete any usergroup specific access.
                    # This can delete any 'manual' usergroup grants also as the user will have seen this as part of the custom access.
                    delete_resource_custom_access_usergroups($ref);
                }
                $new_checksums["access"] = $access;
            }
        }
    }

    if (count($resource_update_sql) > 0) {
        $sql = "UPDATE resource SET " . implode(",", $resource_update_sql) . " WHERE ref=?";
        $sqlparams = array_merge($resource_update_params, ["i",$ref]);
        ps_query($sql, $sqlparams);
    }

    foreach ($resource_update_log_sql as $log_sql) {
        resource_log($log_sql["ref"], $log_sql["type"], $log_sql["field"], $log_sql["notes"], $log_sql["from"], $log_sql["to"]);
    }

    # Save any custom permissions
    if (getval("access", 0) == RESOURCE_ACCESS_CUSTOM_GROUP) {
        save_resource_custom_access($ref);
    }

    // Plugins can do extra actions once all fields have been saved and return errors back if needed
    $plg_errors = hook('aftersaveresourcedata', '', array($ref, $nodes_to_add, $nodes_to_remove, $autosave_field, $fields,$updated_resources));
    if (is_array($plg_errors) && !empty($plg_errors)) {
        $errors = array_merge($errors, $plg_errors);
    }

    if (count($errors) == 0) {
        daily_stat("Resource edit", $ref);
        return true;
    }
    return $errors;
}

/**
* Set resource defaults. Optional, a list of field IDs can be passed on to only update certain fields.
* IMPORTANT: this function will always set the resource defaults if any are found. The "client code"
*            is where developers decide whether this should happen
*
*
* @param integer $ref             Resource ID
* @param array   $specific_fields Specific field ID(s) to update
*
* @return boolean
*/
function set_resource_defaults($ref, array $specific_fields = array())
{
    global $userresourcedefaults;

    if ('' == $userresourcedefaults) {
        return false;
    }

    foreach (explode(';', $userresourcedefaults) as $rule) {
        $rule_detail         = explode('=', trim($rule));
        $field_shortname     = $rule_detail[0];
        $field_default_value = $rule_detail[1];

        // Find field(s) - multiple fields can be returned to support several fields with the same name
        $fields = ps_array("SELECT ref AS `value` FROM resource_type_field WHERE name = ?", ["s",$field_shortname], "schema");

        if (0 === count($fields)) {
            continue;
        }

        // Sometimes we may want to set resource defaults only to specific fields so we ignore anything else
        if (0 < count($specific_fields)) {
            $fields = array_intersect($fields, $specific_fields);
        }

        foreach ($fields as $field_ref) {
            update_field($ref, $field_ref, $field_default_value);
        }
    }

    return true;
}

/**
 * Batch save resources in a collection
 *
 * IMPORTANT: inactive nodes should be left alone (don't add/remove) except when processing fixed list field types that
 * only hold one value (dropdown, radio). Plugins should determine this based on their use cases when hooking.
 *
 * @param int $collection
 * @param array $editsearch
 * @param array $postvals
 * @return true|array List of errors if unsuccessful, true otherwise
 */
function save_resource_data_multi($collection, $editsearch = array(), $postvals = [])
{
    global $FIXED_LIST_FIELD_TYPES,$DATE_FIELD_TYPES, $edit_contributed_by, $TEXT_FIELD_TYPES, $userref, $lang, $languages, $language, $baseurl;

    # Save all submitted data for collection $collection or a search result set, this is for the 'edit multiple resources' feature
    if (empty($postvals)) {
        $postvals = $_POST;
    }
    $errors = [];
    $save_warnings = [];
    if ($collection == 0 && isset($editsearch["search"])) {
        // Editing a result set, not a collection
        $edititems  = do_search($editsearch["search"], $editsearch["restypes"], 'resourceid', $editsearch["archive"], -1, 'ASC', false, 0, false, false, '', false, false, true, true, false, $editsearch["search_access"]);
        $list       = array_column($edititems, "ref");
    } else {
        # Save all submitted data for collection $collection,
        $list   = get_collection_resources($collection);
    }

    // Check that user can edit all resources, edit access and not locked by another user
    $noeditaccess = array();
    $lockedresources = array();
    foreach ($list as $listresource) {
        $resource_data[$listresource]  = get_resource_data($listresource, true);
        if (!get_edit_access($listresource, $resource_data[$listresource]["archive"])) {
            $noeditaccess[] = $listresource;
        }
        if ($resource_data[$listresource]["lock_user"] > 0 && $resource_data[$listresource]["lock_user"] != $userref) {
            $lockedresources[] = $listresource;
        }
    }

    if (count($noeditaccess) > 0) {
        $errors[] = $lang["error-edit_noaccess_resources"] . implode(",", $noeditaccess);
    }
    if (count($lockedresources) > 0) {
        $errors[] = $lang["error-edit_locked_resources"] . implode(",", $lockedresources);
    }

    if (count($errors) > 0) {
        return $errors;
    }

    $tmp    = hook("altercollist", "", array("save_resource_data_multi", $list));
    if (is_array($tmp)) {
        if (count($tmp) > 0) {
            $list = $tmp;
        } else {
            return true;
        }
    }

    $ref                 = $list[0];
    $fields              = get_resource_field_data($ref, true);
    $field_restypes = get_resource_type_field_resource_types();
    $expiry_field_edited = false;

    // All the nodes passed for editing. Some of them were already a value
    // of the fields while others have been added/ removed
    $user_set_values = $postvals['nodes'] ?? [];

    // Arrays of nodes to add/ remove from all resources
    $all_nodes_to_add        = [];
    $all_nodes_to_remove     = [];
    // Nodes to add/remove for specific resources (resource as key)
    $resource_nodes_remove   = [];
    $resource_nodes_add      = [];
    // Other changes to make
    $nodes_check_delete      = [];
    $resource_log_updates    = [];
    $log_node_updates        = [];
    $resource_update_sql_arr = [];
    $resource_update_params  = [];
    $updated_resources       = [];
    $successfully_edited_resources = [];
    $fields = array_values(array_filter($fields, function ($field) use ($postvals) {
        return ($postvals['editthis_field_' . $field['ref']] ?? '') != '' || hook('save_resource_data_multi_field_decision', '', array($field['ref']));
    }));
    $node_not_active = fn(array $node): bool => !node_is_active($node);

    // Get all existing nodes for the edited resources
    $existing_nodes = get_resource_nodes_batch($list, array_column($fields, "ref"));
    $joins = get_resource_table_joins();

    for ($n = 0; $n < count($fields); $n++) {
        if (PHP_SAPI !== "cli") {
            set_processing_message(str_replace(["[count]","[total]"], [$n + 1,count($fields)], $lang["processing_calculating_updates_required"]));
        }

        $nodes_to_add       = [];
        $nodes_to_remove    = [];
        $oldnodenames       = [];

        // Append option(s) mode?
        $mode = $postvals["modeselect_" . $fields[$n]["ref"]] ?? "";

        if (in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES)) {
            // Set up arrays of node ids selected and we will later resolve these to add/remove. Don't remove all nodes since user may not have access
            $ui_selected_node_values = array();
            if (
                isset($user_set_values[$fields[$n]['ref']])
                && !is_array($user_set_values[$fields[$n]['ref']])
                && '' != $user_set_values[$fields[$n]['ref']]
                && is_numeric($user_set_values[$fields[$n]['ref']])
            ) {
                $ui_selected_node_values[] = $user_set_values[$fields[$n]['ref']];
            } elseif (
                isset($user_set_values[$fields[$n]['ref']])
                && is_array($user_set_values[$fields[$n]['ref']])
            ) {
                $ui_selected_node_values = $user_set_values[$fields[$n]['ref']];
            }

            // Check nodes are valid for this field
            $fieldnodes = get_nodes($fields[$n]["ref"], null, $fields[$n]['type'] == FIELD_TYPE_CATEGORY_TREE);
            $inactive_nodes = array_column(array_filter($fieldnodes, $node_not_active), 'ref');
            $nodes_by_ref = array_column($fieldnodes, null, 'ref');
            $valid_nodes = in_array($fields[$n]['type'], [FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_RADIO_BUTTONS]) && $ui_selected_node_values !== []
                // We must include inactive nodes if the type can only hold one value so it can be removed later on...
                ? array_keys($nodes_by_ref)
                // ...but prevent direct removals (ie. no value)
                : array_values(array_diff(array_keys($nodes_by_ref), $inactive_nodes));

            // $valid_nodes are already sorted by the order_by (default for get_nodes). This is needed for the data_joins fields later
            $ui_selected_node_values = array_intersect($valid_nodes, $ui_selected_node_values);
            $ui_deselected_node_values = array_diff($valid_nodes, $ui_selected_node_values);

            if ($mode == "AP") {
                $nodes_to_add = $ui_selected_node_values;
                $all_nodes_to_add    = array_merge($all_nodes_to_add, $nodes_to_add);
            } elseif ($mode == "RM") {
                // Remove option(s) mode
                $nodes_to_remove = $ui_selected_node_values;
                $all_nodes_to_remove = array_merge($all_nodes_to_remove, $nodes_to_remove);
            } elseif ($mode == "RT") {
                // Replace option(s) mode
                $nodes_to_add  = $ui_selected_node_values;
                $nodes_to_remove = $ui_deselected_node_values;
                $all_nodes_to_add    = array_merge($all_nodes_to_add, $nodes_to_add);
                $all_nodes_to_remove = array_merge($all_nodes_to_remove, $nodes_to_remove);
            }

            debug(sprintf('Mode %s - $nodes_to_add = %s', $mode, implode(',', $nodes_to_add)));
            debug(sprintf('Mode %s - $nodes_to_remove = %s', $mode, implode(',', $nodes_to_remove)));

            if ($fields[$n]["required"] == 1 && count($nodes_to_add) == 0 && $mode !== "") {
                // Required field and no value now set, revert to existing and add to array of failed edits
                if (!isset($errors[$fields[$n]["ref"]])) {
                    $errors[$fields[$n]["ref"]] = $lang["requiredfield"] . ". " . $lang["error_batch_edit_resources"] . ": " ;
                }
                $errors[$fields[$n]["ref"]] .=  implode(",", $list);
                $all_nodes_to_remove = array_diff($all_nodes_to_remove, $nodes_to_remove); // Don't remove any nodes in the required field that would be left empty.
                $nodes_to_remove = [];
                continue;
            }

            // Loop through all the resources and check current node values so we can check if we need to log this as a change
            for ($m = 0; $m < count($list); $m++) {
                $ref            = $list[$m];
                $value_changed  = false;
                $new_nodes_val  = [];
                // Nodes to add only to this resource e.g. from hook to revert to previous value
                $resource_nodes_to_add[$ref] = [];

                $current_field_nodes = $existing_nodes[$ref][$fields[$n]['ref']] ?? [];
                debug('Current nodes for resource #' . $ref . ' : ' . implode(',', $current_field_nodes));

                /*
                Possibility to hook in and alter the value - additional mode support.

                Plugins will have to determine if their use cases should handle saving inactive nodes or ignoring them.
                For example, rse_version will act upon inactive nodes because we can't assume a node will not be required
                later (it could be re-activated) when reverting.
                */
                $hookval = hook('save_resource_data_multi_extra_modes', '', array($ref, $fields[$n],$current_field_nodes,$postvals,&$errors));
                if ($hookval !== false) {
                    if (!is_string($hookval)) {
                        continue;
                    }

                    $resource_add_nodes = [];
                    $valid_hook_nodes = false;
                    $log_node_names = [];

                    if (trim((string)$hookval) != "") {
                        // Get array of current field options
                        $nodes_available_keys = [];
                        foreach ($fieldnodes as $node_details) {
                            if ($fields[$n]['type'] == FIELD_TYPE_CATEGORY_TREE) {
                                $nodes_available_keys[mb_strtolower($node_details["path"])] = $node_details["ref"];
                                $nodes_available_keys[mb_strtolower($node_details["translated_path"])] = $node_details["ref"];
                            }
                            $nodes_available_keys[mb_strtolower($node_details["name"])] = $node_details["ref"];
                            $nodes_available_keys[mb_strtolower($node_details["translated_name"])] = $node_details["ref"];
                        }

                        $oldnodenames = explode(NODE_NAME_STRING_SEPARATOR, $hookval);
                        foreach ($oldnodenames as $oldnodename) {
                            debug("Looking for previous node: '" . $oldnodename . "'");
                            $findname = strtolower($oldnodename);
                            if (isset($nodes_available_keys[$findname])) {
                                debug(" - Found valid previous node '" . $nodes_available_keys[$findname] . "'");
                                $resource_add_nodes[] = $nodes_available_keys[$findname];
                                $log_node_names[] = $oldnodename;
                                $valid_hook_nodes = true;
                            } else {
                                debug(" - Unable to find previous node for '" . $oldnodename . "'");
                                $save_warnings[] = ["Resource" => $ref,"Field" => $fields[$n]['title'],"Message" => str_replace("[value]", $oldnodename, $lang["error_invalid_revert_option"])];
                            }
                        }
                    } else {
                        if ($fields[$n]["required"]) {
                            debug(" - No previous node for required field");
                            $save_warnings[] = ["Resource" => $ref,"Field" => $fields[$n]['title'],"Message" => $lang["requiredfield"]];
                            continue;
                        } else {
                            $resource_add_nodes = [];
                            $valid_hook_nodes = true;
                        }
                    }

                    if ($valid_hook_nodes) {
                        sort($resource_add_nodes);
                        sort($current_field_nodes);
                        if ($resource_add_nodes == $current_field_nodes) {
                            debug("hook nodes match existing nodes. Skipping resource " . $ref);
                            continue;
                        }
                        $resource_nodes_add[$ref] = $resource_add_nodes;
                        $resource_nodes_remove[$ref] = array_diff($current_field_nodes, $resource_add_nodes);
                        debug(sprintf('$resource_nodes_add[%s] = %s', $ref, implode(',', $resource_nodes_add[$ref])));
                        debug(sprintf('$resource_nodes_remove[%s] = %s', $ref, implode(',', $resource_nodes_remove[$ref])));

                        $log_node_updates[$ref][] = [
                            'from'  => $current_field_nodes,
                            'to'    => $resource_add_nodes,
                            ];

                        if (in_array($fields[$n]['ref'], $joins)) {
                            // Build new value to add to resource table:
                            foreach ($resource_add_nodes as $new_node) {
                                if (FIELD_TYPE_CATEGORY_TREE === $fields[$n]['type']) {
                                    $new_nodes_val[] = $nodes_by_ref[$new_node]["path"];
                                } else {
                                    $new_nodes_val[] = $nodes_by_ref[$new_node]["name"];
                                }
                            }
                            $resource_update_sql_arr[$ref][] = "field" . (int)$fields[$n]["ref"] . " = ?";
                            $resource_update_params[$ref][] = "s";
                            $resource_update_params[$ref][] = truncate_join_field_value(implode($GLOBALS['field_column_string_separator'], $new_nodes_val));
                        }

                        $updated_resources[$ref][$fields[$n]['ref']] = $new_nodes_val; // To pass to hook
                    }
                } else {
                    $added_nodes = array_diff($nodes_to_add, $current_field_nodes);
                    debug('Adding nodes to resource #' . $ref . ' : ' . implode(',', $added_nodes));
                    $removed_nodes = array_intersect($nodes_to_remove, $current_field_nodes);
                    debug('Removed nodes from resource #' . $ref . ' : ' . implode(',', $removed_nodes));

                    // Work out what all the new nodes for this resource  will be while maintaining their order
                    $new_nodes = array_filter($nodes_by_ref, function ($node) use ($nodes_to_add) {
                        return in_array($node["ref"], $nodes_to_add);
                    });

                    # 'to' should contain the resulting nodes after the amendment. The difference between 'to' and 'from' is what has changed.
                    $resulting_nodes_for_log = $nodes_to_add;
                    if ($mode == "AP") {
                        $resulting_nodes_for_log = array_unique(array_merge($nodes_to_add, $current_field_nodes));
                    }
                    if ($mode == "RM") {
                        $resulting_nodes_for_log = array_diff($current_field_nodes, $removed_nodes);
                    }
                    $log_node_updates[$ref][] = [
                        'from'  => $current_field_nodes,
                        'to'    => $resulting_nodes_for_log,
                        ];

                    if ((count($added_nodes) > 0 || count($removed_nodes) > 0) && in_array($fields[$n]['ref'], $joins)) {
                        // Build new value:
                        foreach ($new_nodes as $noderef => $new_node) {
                            if (FIELD_TYPE_CATEGORY_TREE === $fields[$n]['type']) {
                                $new_nodes_val[] = $nodes_by_ref[$noderef]["path"];
                            } else {
                                $new_nodes_val[] = $nodes_by_ref[$noderef]["name"];
                            }
                        }
                        $resource_update_sql_arr[$ref][] = "field" . (int)$fields[$n]["ref"] . " = ?";
                        $resource_update_params[$ref][] = "s";
                        $resource_update_params[$ref][] = truncate_join_field_value(implode($GLOBALS['field_column_string_separator'], $new_nodes_val));

                        $updated_resources[$ref][$fields[$n]['ref']] = $new_nodes_val; // To pass to hook
                    }
                }
                // Add any onchange code
                if ($fields[$n]["onchange_macro"] != "") {
                    $val = implode(
                        ',',
                        array_column(get_nodes_by_refs($resource_add_nodes ?? $resulting_nodes_for_log), "name")
                    );
                    $macro_resource_id = $ref;
                    eval(eval_check_signed($fields[$n]["onchange_macro"]));
                }
            }
        } // End of fixed list field section
        elseif ($fields[$n]['type'] == FIELD_TYPE_DATE_RANGE) {
            # date range type
            # each value will be a node so we end up with a pair of nodes to represent the start and end dates

            $daterangenodes = array();
            $newval = "";

            if ($date_edtf = ($postvals["field_" . $fields[$n]["ref"] . "_edtf"] ?? "") !== "") {
                // We have been passed the range in EDTF format, check it is in the correct format
                $rangeregex = "/^(\d{4})(-\d{2})?(-\d{2})?\/(\d{4})(-\d{2})?(-\d{2})?/";
                if (!preg_match($rangeregex, $date_edtf, $matches)) {
                    $errors[$fields[$n]["ref"]] = $lang["information-regexp_fail"] . " : " . $rangeregex;
                    continue;
                }
                if (is_numeric($fields[$n]["linked_data_field"])) {
                    // Update the linked field with the raw EDTF string submitted
                    update_field($ref, $fields[$n]["linked_data_field"], $date_edtf);
                }
                $rangedates = explode("/", $date_edtf);
                $rangestart = str_pad($rangedates[0], 10, "-00");
                $rangeendparts = explode("-", $rangedates[1]);
                $rangeendyear = $rangeendparts[0];
                $rangeendmonth = isset($rangeendparts[1]) ? $rangeendparts[1] : 12;
                $rangeendday = isset($rangeendparts[2]) ? $rangeendparts[2] : cal_days_in_month(CAL_GREGORIAN, $rangeendmonth, $rangeendyear);
                $rangeend = $rangeendyear . "-" . $rangeendmonth . "-" . $rangeendday;

                $newval = $rangestart . DATE_RANGE_SEPARATOR . $rangeend;
                $daterangenodes[] = set_node(null, $fields[$n]["ref"], $rangestart, null, null);
                $daterangenodes[] = set_node(null, $fields[$n]["ref"], $rangeend, null, null);
            } else {
                // Range has been passed via normal inputs, construct the value from the date/time dropdowns
                $date_parts = array("_start","_end");

                foreach ($date_parts as $date_part) {
                    $val = $postvals["field_" . $fields[$n]["ref"] . $date_part . "-y"] ?? "";
                    if ((int) $val <= 0) {
                        $val = "";
                    } elseif (($field = ($postvals["field_" . $fields[$n]["ref"] . $date_part . "-m"] ?? "")) != "") {
                        $val .= "-" . $field;
                        if (($field = ($postvals["field_" . $fields[$n]["ref"] . $date_part . "-d"] ?? "")) != "") {
                            $val .= "-" . $field;
                        } else {
                            $val .= "-00";
                        }
                    } else {
                        $val .= "-00-00";
                    }
                    if ($val !== "") {
                        $daterangenodes[] = set_node(null, $fields[$n]["ref"], $val, null, null);
                        $newval .= ($newval != "" ? DATE_RANGE_SEPARATOR : "") . $val;
                    }
                }
            }

            for ($m = 0; $m < count($list); $m++) {
                $ref            = $list[$m];
                $value_changed  = false;
                $log_node_names = [];
                $current_field_nodes = $existing_nodes[$ref][$fields[$n]['ref']] ?? [];
                debug(' -  current_field_nodes nodes for resource #' . $ref . ': ' . implode(",", $current_field_nodes));
                # Possibility to hook in and alter the value - additional mode support
                $hookval = hook('save_resource_data_multi_extra_modes', '', array($ref, $fields[$n],$current_field_nodes,$postvals,&$errors));

                if ($hookval !== false) {
                    if (!is_string($hookval)) {
                        continue;
                    }

                    $resource_add_nodes = [];
                    $valid_hook_nodes = false;
                    $oldnodenames = explode(NODE_NAME_STRING_SEPARATOR, $hookval);
                    foreach ($oldnodenames as $oldnodename) {
                        if (trim($oldnodename) == "" && !$fields[$n]["required"]) {
                            $valid_hook_nodes = true;
                        } elseif (check_date_format($oldnodename) == "") {
                            $valid_hook_nodes = true;
                            debug(" - Found valid previous date '" . $oldnodename . "'");
                            $resource_add_nodes[] = set_node(null, $fields[$n]['ref'], $oldnodename, null, 10);
                            $log_node_names[] = $oldnodename;
                        } else {
                            $save_warnings[] = ["Resource" => $ref,"Field" => $fields[$n]['title'],"Message" => str_replace("[value]", $oldnodename, $lang["error_invalid_revert_date"])];
                            debug(" - Invalid previous date " . $oldnodename . "'");
                        }
                    }
                    if ($valid_hook_nodes) {
                        sort($resource_add_nodes);
                        sort($current_field_nodes);
                        if ($resource_add_nodes == $current_field_nodes) {
                            debug("hook nodes match existing nodes. Skipping resource " . $ref);
                            continue;
                        }
                        $resource_nodes_add[$ref] = $resource_add_nodes;
                        $resource_nodes_remove[$ref] = $current_field_nodes;
                        $log_node_updates[$ref][] = [
                            'from'  => $current_field_nodes,
                            'to'    => $resource_add_nodes,
                            ];
                        if (in_array($fields[$n]['ref'], $joins)) {
                            $resource_update_sql_arr[$ref][] = "field" . (int)$fields[$n]["ref"] . " = ?";
                            $resource_update_params[$ref][] = "s";
                            $resource_update_params[$ref][] = implode(DATE_RANGE_SEPARATOR, $log_node_names);
                        }
                        $updated_resources[$ref][$fields[$n]['ref']] = $log_node_names; // To pass to hook
                    }
                } else {
                    $added_nodes = array_diff($daterangenodes, $current_field_nodes);
                    debug("save_resource_data_multi(): Adding nodes to resource " . $ref . ": " . implode(",", $added_nodes));
                    $nodes_to_add = array_merge($nodes_to_add, $daterangenodes);

                    $removed_nodes = array_diff($current_field_nodes, $daterangenodes);
                    debug("save_resource_data_multi(): Removing nodes from resource " . $ref . ": " . implode(",", $removed_nodes));
                    $nodes_to_remove = array_merge($nodes_to_remove, $removed_nodes);

                    if (count($added_nodes) > 0 || count($removed_nodes) > 0) {
                        $log_node_updates[$ref][] = [
                            'from'  => $current_field_nodes,
                            'to'    => $daterangenodes,
                            ];

                        // If this is a 'joined' field it still needs to add it to the resource column
                        if (in_array($fields[$n]['ref'], $joins)) {
                            $resource_update_sql_arr[$ref][] = "field" . (int)$fields[$n]["ref"] . " = ?";
                            $resource_update_params[$ref][] = "s";
                            $resource_update_params[$ref][] = $newval;
                        }

                        $updated_resources[$ref][$fields[$n]['ref']][] = $newval; // To pass to hook
                    }
                }
                $all_nodes_to_add    = array_merge($all_nodes_to_add, $nodes_to_add);
                $all_nodes_to_remove = array_merge($all_nodes_to_remove, $nodes_to_remove);
            }
        } else {
            if ($GLOBALS['use_native_input_for_date_field'] && $fields[$n]['type'] === FIELD_TYPE_DATE) {
                $val = $postvals["field_{$fields[$n]['ref']}"] ?? '';
                if ($val !== '' && !validateDatetime($val, 'Y-m-d')) {
                    $errors[$fields[$n]['ref']] = $val;
                    continue;
                }
            } elseif (in_array($fields[$n]['type'], $DATE_FIELD_TYPES)) {
                # date/expiry date type, construct the value from the date dropdowns
                $val = sanitize_date_field_input($fields[$n]["ref"], false);
            } else {
                $val = $postvals["field_" . $fields[$n]["ref"]] ?? "";
            }

            $origval = $val;
            # Loop through all the resources and save.
            for ($m = 0; $m < count($list); $m++) {
                $ref            = $list[$m];
                $value_changed  = false;
                $use_node = null;

                // Reset nodes to add/remove as may differ for each resource
                $nodes_to_add       = [];
                $nodes_to_remove    = [];
                if ($fields[$n]["global"] == 0 && !in_array($resource_data[$ref]["resource_type"], $field_restypes[$fields[$n]["ref"]])) {
                    continue;
                }

                # Work out existing field value.
                $existing = get_data_by_field($ref, $fields[$n]['ref']);
                if ($mode == "FR") {
                    # Find and replace mode? Perform the find and replace.

                    // If field is a TinyMCE field, use a HTML aware find and replace
                    // else perform the standard one

                    $findstring     = $postvals["find_" . $fields[$n]["ref"]] ?? "";
                    $replacestring  = $postvals["replace_" . $fields[$n]["ref"]] ?? "";

                    if ($fields[$n]['type'] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE) {
                        $val = html_find_and_replace($findstring, $replacestring, $existing);
                    } else {
                        $val = str_replace($findstring, $replacestring, $existing);
                    }

                }

                # Append text/option(s) mode?
                elseif ($mode == "AP" && in_array($fields[$n]["type"], $TEXT_FIELD_TYPES)) {
                    $val = $existing . " " . $origval;
                }

                # Prepend text/option(s) mode?
                elseif ($mode == "PP" && in_array($fields[$n]["type"], $TEXT_FIELD_TYPES)) {
                    global $filename_field;
                    if ($fields[$n]["ref"] == $filename_field) {
                        $val = rtrim($origval, "_") . "_" . trim($existing); // use an underscore if editing filename.
                    } else {
                        # Automatically append a space when appending text types.
                        $val = $origval . " " . $existing;
                    }
                } elseif ($mode == "RM") {
                    # Remove text/option(s) mode

                    if ($fields[$n]['type'] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE) {
                        $removestring = $postvals["remove_" . $fields[$n]["ref"]] ?? "";
                        $val = html_find_and_replace($removestring, "", $existing);
                    } else {
                        $val = str_replace($origval, "", $existing);
                    }                    
                    if ($fields[$n]["required"] && strip_leading_comma($val) == "") {
                        // Required field and  no value now set, revert to existing and add to array of failed edits
                        $val = $existing;
                        if (!isset($errors[$fields[$n]["ref"]])) {
                            $errors[$fields[$n]["ref"]] = $lang["requiredfield"] . ". " . $lang["error_batch_edit_resources"] . ": " ;
                        }
                        $errors[$fields[$n]["ref"]] .=  $ref;
                        if ($m < count($list) - 1) {
                            $errors[$fields[$n]["ref"]] .= ",";
                        }
                    }
                } elseif ($mode == "CF") {
                    # Copy text from another text field
                    $copyfrom = (int)$postvals["copy_from_field_" . $fields[$n]["ref"]] ?? 0;
                    if (!in_array($fields[$n]["type"], $TEXT_FIELD_TYPES)) {
                        // Not a valid option for this field
                        debug("Copy data from field " . $copyfrom . " to field " . $fields[$n]["ref"] . " requires target field to be of a text type");
                        continue;
                    }
                    $val = get_data_by_field($ref, $copyfrom);
                    if ($fields[$n]["required"] && strip_leading_comma($val) == "") {
                        // Required field and no value now set, revert to existing and add to array of failed edits
                        $val = $existing;
                        if (!isset($errors[$fields[$n]["ref"]])) {
                            $errors[$fields[$n]["ref"]] = $lang["requiredfield"] . ". " . $lang["error_batch_edit_resources"] . ": " ;
                        }
                        $errors[$fields[$n]["ref"]] .=  $ref;
                        if ($m < count($list) - 1) {
                            $errors[$fields[$n]["ref"]] .= ",";
                        }
                        continue;
                    }
                }

                # Possibility to hook in and alter the value - additional mode support
                $hookval = hook('save_resource_data_multi_extra_modes', '', array($ref, $fields[$n],$existing,$postvals,&$errors));

                if ($hookval !== false) {
                    if (!is_string($hookval)) {
                        continue;
                    }
                    $val = $hookval;
                }

                # Check for regular expression match
                if (strlen(trim((string)$fields[$n]["regexp_filter"])) >= 1 && strlen($val) > 0) {
                    global $regexp_slash_replace;
                    if (preg_match("#^" . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . "$#", $val, $matches) <= 0) {
                        global $lang;
                        debug($lang["information-regexp_fail"] . ": -" . "reg exp: " . str_replace($regexp_slash_replace, '\\', $fields[$n]["regexp_filter"]) . ". Value passed: " . $val);
                        $errors[$fields[$n]["ref"]] = $lang["information-regexp_fail"] . " : " . $val;
                        continue;
                    }
                }
                if ($val !== $existing || $value_changed) {
                    if ($fields[$n]["required"] && $val == "") {
                        // Required field and no value now set, revert to existing and add to array of failed edits
                        if (!isset($errors[$fields[$n]["ref"]])) {
                            $errors[$fields[$n]["ref"]] = $lang["requiredfield"] . ". " . $lang["error_batch_edit_resources"] . ": " ;
                        }
                        $errors[$fields[$n]["ref"]] .=  $ref;
                        if ($m < count($list) - 1) {
                            $errors[$fields[$n]["ref"]] .= ",";
                        }
                        continue;
                    }

                    // This value is different from the value we have on record.

                    // Expiry field? Set that expiry date(s) have changed so the expiry notification flag will be reset later in this function.
                    if ($fields[$n]["type"] == FIELD_TYPE_EXPIRY_DATE) {
                        $expiry_field_edited = true;
                    }

                    // Find existing node IDs for this non-fixed list field (there should only be one). These can then be resused or deleted, unless used by other resources.
                    $current_field_nodes = $existing_nodes[$ref][$fields[$n]['ref']] ?? [];
                    foreach ($current_field_nodes as $current_field_node) {
                        $inuse = get_nodes_use_count([$current_field_node]);
                        $inusecount = $inuse[$current_field_node] ?? 0;
                        if ($current_field_node > 0 && $inusecount == 1 && is_null($use_node)) {
                            // Node can be reused or deleted
                            debug("Found node only in use by resource #" . $ref . ", node # " . $current_field_node);
                            $use_node = $current_field_node;
                        } else {
                            // Remove node from resource and create a new node
                            debug("Removing node from resource #" . $ref . ", node # " . $current_field_node);
                            $nodes_to_remove[] = $current_field_node;
                            $nodes_check_delete[] = $current_field_node;
                        }
                    }

                    # Add new node, unless empty string
                    if ($val == '') {
                        // Remove and delete node
                        if (!is_null($use_node)) {
                            $nodes_to_remove[] = $use_node;
                            $nodes_check_delete[] = $use_node;
                        }
                    } else {
                        $findnode = get_node_id($val, $fields[$n]["ref"]);
                        if ($findnode === false) {
                            debug("No existing  node found for value : '" . $val . "'");
                            // No existing node, rename/create node
                            $newnode = set_node($use_node, $fields[$n]["ref"], $val, null, null);
                            if ($newnode == $use_node) {
                                // May have simply renamed the node but add to array as other resources may not have it
                                $nodes_to_add[] = $newnode;
                                debug("Renamed node #" . $newnode . " to " . $val);
                            } else {
                                // New node created, add this to resource and delete old node
                                debug("Created new node #" . $newnode . " for " . $val);
                                $nodes_to_add[] = $newnode;
                                if (!is_null($use_node)) {
                                    $nodes_to_remove[] = $use_node;
                                    $nodes_check_delete[] = $use_node;
                                }
                            }
                        } else {
                            // Another node has the same name, use that and delete existing node
                            debug("Using existing node #" . $findnode);
                            $nodes_to_add[] = $findnode;
                            if (!is_null($use_node)) {
                                $nodes_to_remove[] = $use_node;
                            }
                        }
                    }

                    // Need to save data separately as potentially setting different values for each resource
                    $resource_nodes_add[$ref] = array_merge($resource_nodes_add[$ref] ?? [], $nodes_to_add);
                    $resource_nodes_remove[$ref] = array_diff(array_merge($resource_nodes_remove[$ref] ?? [], $nodes_to_remove), $resource_nodes_add[$ref]);

                    $resource_log_updates[$ref][] = [
                        'ref'   => $ref,
                        'type'  => LOG_CODE_EDITED,
                        'field' => $fields[$n]["ref"],
                        'notes' => '',
                        'from'  => $existing,
                        'to'    => $val,
                        ];

                    // If this is a 'joined' field it still needs to add it to the resource column
                    if (in_array($fields[$n]['ref'], $joins)) {
                        $resource_update_sql_arr[$ref][] = "field" . (int)$fields[$n]["ref"] . " = ?";
                        $resource_update_params[$ref][] = "s";
                        $resource_update_params[$ref][] = truncate_join_field_value($val);
                    }

                    $newval = $val;

                    // Add any onchange code
                    if ($fields[$n]["onchange_macro"] != "") {
                        $macro_resource_id = $ref;
                        eval(eval_check_signed($fields[$n]["onchange_macro"]));
                    }

                    $successfully_edited_resources[] = $ref;
                    $updated_resources[$ref][$fields[$n]['ref']][] = $newval; // To pass to hook
                }
            } // End of for each resource
        }  // End of non-fixed list editing section
    } // End of foreach field loop

    // Perform the actual updates
    set_processing_message(str_replace(["[field_count]", "[resource_count]"], [count($fields), count($list)], $lang["processing_batch_edit_save"]));
    db_begin_transaction("save_resource_data_multi");
    // Add/remove nodes for all resources
    if (count($all_nodes_to_add) > 0) {
        add_resource_nodes_multi($list, $all_nodes_to_add, false);
    }
    if (count($all_nodes_to_remove) > 0) {
        delete_resource_nodes_multi($list, $all_nodes_to_remove);
    }
    // Updates for individual resources
    foreach ($resource_nodes_add as $resource => $addnodes) {
        add_resource_nodes($resource, $addnodes, false, false);
    }
    foreach ($resource_nodes_remove as $resource => $delnodes) {
        delete_resource_nodes($resource, $delnodes, false);
    }
    if (count($nodes_check_delete) > 0) {
        // This has to be after call to log_node_changes() or nodes cannot be resolved
        check_delete_nodes($nodes_check_delete);
    }

    // Update resource table
    foreach ($resource_update_sql_arr as $resource => $resource_update_sql) {
        $sql = "UPDATE resource SET " . implode(",", $resource_update_sql) . " WHERE ref=?";
        $sqlparams = array_merge($resource_update_params[$resource], ["i",$resource]);
        ps_query($sql, $sqlparams);
    }

    // Log the updates
    foreach ($resource_log_updates as $resource => $log_add) {
        foreach ($log_add as $log_sql) {
            resource_log($resource, $log_sql["type"], $log_sql["field"], $log_sql["notes"], $log_sql["from"], $log_sql["to"]);
        }
    }
    foreach ($log_node_updates as $resource => $log_add) {
        foreach ($log_add as $log_node_sql) {
            log_node_changes($resource, $log_node_sql["to"], $log_node_sql["from"]);
        }
    }

    // Autocomplete follows principal resource update
    foreach ($list as $resource_id) {
        autocomplete_blank_fields($resource_id, false);  // false means only autocomplete blank fields
    }

    db_end_transaction("save_resource_data_multi");

    // Also save related resources field
    if (($postvals["editthis_related"] ?? "") != "") {
        $related = explode(',', ($postvals['related'] ?? ''));

        // Make sure all submitted values are numeric and each related resource is editable.
        $resources_to_relate = array();
        $no_access_to_relate = array();
        for ($n = 0; $n < count($related); $n++) {
            $ref_to_relate = trim($related[$n]);
            if (is_numeric($ref_to_relate)) {
                if (!get_edit_access($ref_to_relate)) {
                    debug("Edit multiple - Failed to update related resource - no edit access to resource $ref_to_relate");
                    $no_access_to_relate[] = $ref_to_relate;
                } else {
                    $resources_to_relate[] = $ref_to_relate;
                }
            }
        }

        if (count($no_access_to_relate) > 0) {
            $errors[] = $lang["error-edit_noaccess_related_resources"] . implode(",", $no_access_to_relate);
            return $errors;
        }

        // Clear out all relationships between related resources in this collection
        ps_query(
            "
                DELETE rr
                  FROM resource_related AS rr
            INNER JOIN collection_resource AS cr ON rr.resource = cr.resource
                 WHERE cr.collection = ?",
            ["i",$collection]
        );

        for ($m = 0; $m < count($list); $m++) {
            $ref = $list[$m];
            // Only add new relationships
            $existing_relations = ps_array("SELECT related value FROM resource_related WHERE resource = ?", array("i", $ref));

            // Don't relate a resource to itself
            $for_relate_sql = array();
            $for_relate_parameters = array();
            foreach ($resources_to_relate as $resource_to_relate) {
                if ($ref != $resource_to_relate && !in_array($resource_to_relate, $existing_relations)) {
                    $for_relate_sql = array_merge($for_relate_sql, array('(?, ?)'));
                    $for_relate_parameters = array_merge($for_relate_parameters, array("i", $ref, "i", $resource_to_relate));
                }
            }

            if (0 < count($for_relate_sql)) {
                ps_query("INSERT INTO resource_related (resource, related) VALUES " . implode(",", $for_relate_sql), $for_relate_parameters);
                $successfully_edited_resources[] = $ref;
            }
        }
    }

    # Also update archive status
    if (($postvals["editthis_status"] ?? "") != "") {
        $missed_required_fields = array();
        for ($m = 0; $m < count($list); $m++) {
            $ref = $list[$m];

            if (!hook('forbidsavearchive', '', array($errors))) {
                $oldarchive = ps_value("SELECT archive value FROM resource WHERE ref = ?", ["i",$ref], "");
                $setarchivestate = ((int)$postvals["status"] ?? $oldarchive); // Originally used to get the 'archive' value but this conflicts with the archive used for searching
                $successfully_edited_resources[] = $ref;

                $set_archive_state_hook = hook("save_resource_data_multi_set_archive_state", "", array($ref, $oldarchive));
                if ($set_archive_state_hook !== false && is_numeric($set_archive_state_hook)) {
                    $setarchivestate = $set_archive_state_hook;
                }

                if ($setarchivestate != $oldarchive && !checkperm("e" . $setarchivestate)) { // don't allow change if user has no permission to change archive state
                    $setarchivestate = $oldarchive;
                }

                if ($setarchivestate != $oldarchive) { // Only if changed
                    $field_check = update_archive_required_fields_check($ref, $setarchivestate);
                    if (count($field_check) > 0) {
                        $missed_required_fields[$ref] = $field_check;
                    } else {
                        update_archive_status($ref, $setarchivestate, array($oldarchive));
                    }
                }
            }
        }
        if (count($missed_required_fields) > 0) {
            $missed_required_fields = array_keys($missed_required_fields);
            sort($missed_required_fields, SORT_NUMERIC);
            $errors[] = $lang["error-edit_status_change_missing_required_fields"] . '<br> ' . implode(', ', $missed_required_fields);
        }
    }

    # Expiry field(s) edited? Reset the notification flag so that warnings are sent again when the date is reached.
    if ($expiry_field_edited) {
        if (count($list) > 0) {
            ps_query("UPDATE resource SET expiry_notification_sent=0 WHERE ref IN (" . ps_param_insert(count($list)) . ")", ps_param_fill($list, "i"));
        }

        $successfully_edited_resources = array_merge($successfully_edited_resources, $list);
    }

    # Also update access level
    if (($postvals["editthis_created_by"] ?? "") != "" && $edit_contributed_by) {
        for ($m = 0; $m < count($list); $m++) {
            $ref = $list[$m];
            $created_by = ps_value("SELECT created_by value FROM resource WHERE ref=?", array("i",$ref), "");
            $new_created_by = (int)$postvals["created_by"] ?? 0;
            if ($new_created_by > 0 && $new_created_by != $created_by) {
                ps_query("UPDATE resource SET created_by=? WHERE ref=?", array("i",$new_created_by,"i",$ref));
                $olduser = get_user($created_by);
                $newuser = get_user($new_created_by);
                resource_log($ref, LOG_CODE_CREATED_BY_CHANGED, 0, "", $created_by . " (" . ($olduser["fullname"] == "" ? $olduser["username"] : $olduser["fullname"])  . ")", $new_created_by . " (" . ($newuser["fullname"] == "" ? $newuser["username"] : $newuser["fullname"])  . ")");
                $successfully_edited_resources[] = $ref;
            }
        }
    }

    # Also update access level
    if (($postvals["editthis_access"] ?? "") != "") {
        for ($m = 0; $m < count($list); $m++) {
            $ref = $list[$m];
            $access = (int)$postvals["access"] ?? 0;
            $oldaccess = ps_value("SELECT access value FROM resource WHERE ref=?", array("i",$ref), "");
            if ($access != $oldaccess) {
                ps_query("UPDATE resource SET access=? WHERE ref=?", array("i",$access,"i",$ref));
                if ($oldaccess == 3) {
                    # Moving out of custom access - delete custom usergroup access.
                    delete_resource_custom_access_usergroups($ref);
                }
                resource_log($ref, LOG_CODE_ACCESS_CHANGED, 0, "", $oldaccess, $access);
                $successfully_edited_resources[] = $ref;
            }
            # For access level 3 (custom) - also save custom permissions
            if ($access == 3) {
                save_resource_custom_access($ref);
            }
        }
    }

    # Update resource type?

    if (($postvals["editresourcetype"] ?? "") != "") {
        $newrestype = (int)$postvals["resource_type"] ?? 0;
        $alltypes = get_resource_types();
        if (in_array($newrestype, array_column($alltypes, "ref"))) {
            for ($m = 0; $m < count($list); $m++) {
                $ref = $list[$m];
                update_resource_type($ref, $newrestype);
                $successfully_edited_resources[] = $ref;
            }
        }
    }

    # Update location?
    if (($postvals["editlocation"] ?? "") != "" || ($postvals["editmaplocation"] ?? "") != "") {
        $location = explode(",", $postvals["location"]);
        if (count($list) > 0) {
            $list_data = get_resource_data_batch($list);
            $log_location = "";
            if (count($location) == 2) {
                $geo_lat = (float)$location[0];
                $geo_long = (float)$location[1];
                $log_location = $geo_lat . ", " . $geo_long;
                ps_query(
                    "UPDATE resource SET geo_lat = ?,geo_long = ? WHERE ref IN (" . ps_param_insert(count($list)) . ")",
                    array_merge(["d",$geo_lat,"d",$geo_long], ps_param_fill($list, "i"))
                );
            } elseif (($postvals["location"] ?? "") == "") {
                ps_query(
                    "UPDATE resource SET geo_lat=NULL,geo_long=NULL WHERE ref IN (" . ps_param_insert(count($list)) . ")",
                    ps_param_fill($list, "i")
                );
            }

            foreach ($list as $ref) {
                $successfully_edited_resources[] = $ref;
                if ($list_data[$ref]["geo_lat"] != "" && $list_data[$ref]["geo_long"] != "") {
                    $old_location = $list_data[$ref]["geo_lat"] . ", " . $list_data[$ref]["geo_long"];
                }
                resource_log(
                    $ref,
                    LOG_CODE_EDITED_RESOURCE,
                    null,
                    $log_location != "" ? "Edited Location" : "Removed Location",
                    $old_location ?? "",
                    $log_location
                );
            }
        }
    }

    # Update mapzoom?
    if (($postvals["editmapzoom"] ?? "") != "") {
        $mapzoom = $postvals["mapzoom"] ?? "";
        if (count($list) > 0) {
            if ($mapzoom != "") {
                ps_query("UPDATE resource SET mapzoom = ? WHERE ref IN (" . ps_param_insert(count($list)) . ")", array_merge(["i",$mapzoom], ps_param_fill($list, "i")));
            } else {
                ps_query("UPDATE resource SET mapzoom=NULL WHERE ref IN (" . ps_param_insert(count($list)) . ")", ps_param_fill($list, "i"));
            }

            foreach ($list as $ref) {
                $successfully_edited_resources[] = $ref;
            }
        }
    }

    hook("saveextraresourcedata", "", array($list));

    // Plugins can do extra actions once all fields have been saved and return errors back if needed.
    // NOTE: Ensure the list of arguments is matching with aftersaveresourcedata hook in save_resource_data()
    $plg_errors = hook('aftersaveresourcedata', '', array($list, $all_nodes_to_add, $all_nodes_to_remove, '', $fields,$updated_resources));
    if (is_array($plg_errors) && !empty($plg_errors)) {
        $errors = array_merge($errors, $plg_errors);
    }

    if (!empty($successfully_edited_resources)) {
        $successfully_edited_resources = array_unique($successfully_edited_resources);

        foreach ($successfully_edited_resources as $editedref) {
            daily_stat("Resource edit", $editedref);
        }
    }

    if (count($save_warnings) > 0) {
        $save_message = new ResourceSpaceUserNotification();
        $save_message->set_subject("lang_editallresources");
        $save_message->set_text($lang["batch_edit_save_warning_message"]); // No line breaks or on screen message will end up with <br> tags
        $save_message->append_text("<div>");
        foreach ($save_warnings as $save_warning) {
            $save_message->append_text("<div><strong>" . $lang["resourceid"] . ": <a href ='" . $baseurl . "/?r=" . $save_warning["Resource"] . "' target='_blank'>" . $save_warning["Resource"] . "</a></strong><br/><strong>" . $lang["field"] . ": </strong>" . $save_warning["Field"] . "<br /><strong>" . $lang["error"] . ": </strong>" . $save_warning["Message"] . "</div><br />");
        }
        $save_message->append_text("</div>");
        send_user_notification([$userref], $save_message);
        $errors[] = $lang["batch_edit_save_warning_alert"];
    }

    if (count($errors) == 0) {
        return true;
    } else {
        return $errors;
    }
}

/**
* Updates resource field. Works out the previous value, so this is
* not efficient if we already know what this previous value is (hence
* it is not used for edit where multiple fields are saved)
*
* @param integer $resource      Resource ID
* @param integer $field         Field ID
* @param string  $value         The new value
* @param array   &$errors       Any errors that may occur during update
* @param boolean $log           Log this change in the resource log?
* @param boolean $nodevalues    Set to TRUE to process the value as a comma separated list of node IDs
*
* @return boolean
*/
function update_field($resource, $field, $value, array &$errors = array(), $log = true, $nodevalues = false)
{
    global $category_tree_add_parents, $userref, $FIXED_LIST_FIELD_TYPES, $lang;

    $resource_data = get_resource_data($resource);
    if ($resource_data === false) {
        $errors[] = $lang["resourcenotfound"] . " " . (string) $resource;
        return false;
    }
    if ($resource_data["lock_user"] > 0 && $resource_data["lock_user"] != $userref) {
        $errors[] = get_resource_lock_message($resource_data["lock_user"]);
        return false;
    }

    // accept shortnames in addition to field refs
    if (!is_int_loose($field)) {
        $field = ps_value("SELECT ref AS `value` FROM resource_type_field WHERE name = ?", ["s",$field], '', "schema");
    }

    // Fetch some information about the field
    $fieldinfo = get_resource_type_field($field);

    if (!$fieldinfo) {
        $errors[] = "No field information about field ID '{$field}'";
        return false;
    }

    if ($fieldinfo["global"] == 0 && !in_array($resource_data['resource_type'], ps_array("SELECT resource_type value FROM resource_type_field_resource_type WHERE resource_type_field=?", array("i",$field)))) {
        $errors[] = "Field is not valid for this resource type";
        return false;
    }

    $value = $data_joins_field_value = trim((string)$value);
    if ($value === '' && $fieldinfo['required']) {
        $errors[] = i18n_get_translated($fieldinfo['title']) . ": {$lang['requiredfield']}";
        return false;
    }

    // Create arrays that will be passed to hook function later
    $newnodes        = [];
    $newvalues       = [];

    if (in_array($fieldinfo['type'], array_merge($FIXED_LIST_FIELD_TYPES, [FIELD_TYPE_DATE_RANGE]))) {
        // Standard node fields
        // Set up arrays of node ids to add/remove and all new nodes.
        $nodes_to_add    = [];
        $nodes_to_remove = [];

        // Get currently selected nodes for this field
        $current_field_nodes = get_resource_nodes($resource, $field, true);
        $current_field_node_ids = array_column($current_field_nodes, "ref");
        // Get an 'existing' value
        $existing = implode(",", array_column($current_field_nodes, "path"));

        // Get all node values into an array to search, not for dynamic keyword fields as these can be very long
        if ($fieldinfo['type'] != FIELD_TYPE_DYNAMIC_KEYWORDS_LIST) {
            $fieldnodes = get_nodes($field, null, $fieldinfo['type'] == FIELD_TYPE_CATEGORY_TREE);
            $nodes_by_ref = [];
            foreach ($fieldnodes as $fieldnode) {
                $nodes_by_ref[$fieldnode["ref"]] = $fieldnode;
            }
        }

        if ($nodevalues) {
            // List of node IDs has been passed in comma separated form, use them directly
            $sent_nodes = explode(",", $value);
            if (
                in_array($fieldinfo['type'], [FIELD_TYPE_RADIO_BUTTONS,FIELD_TYPE_DROP_DOWN_LIST])
                && count($sent_nodes) > 1
            ) {
                // Only a single value allowed
                return false;
            }

            // Validate each node
            foreach ($sent_nodes as $sent_node) {
                $node_details = [];
                $valid_node = get_node($sent_node, $node_details);
                if (!$valid_node || $node_details["resource_type_field"] !== (int) $field) {
                    return false;
                }
                $nodes_to_add[] = $sent_node;
                if (!in_array($sent_node, $current_field_node_ids)) {
                    $newnodes[] = $sent_node;
                }
            }

            $nodes_to_remove = array_diff($nodes_to_add, $current_field_node_ids);
            if (count($nodes_to_add) != count($sent_nodes)) {
                // Unable to find all node values that were passed
                return false;
            }
        } else {
            // Not a list of node IDs; value has been passed as normal string value
            // Get all the new values into an array
            if (strpos($value, NODE_NAME_STRING_SEPARATOR) !== false) {
                $newvalues = array_map('trim', explode(NODE_NAME_STRING_SEPARATOR, $value));
            } else {
                if (strlen($value) > 0 && (($value[0] == "'" && $value[strlen($value) - 1] == "'"))) {
                    // Quoted value - don't attempt to split on comma.
                    // Quoting should normally use double quotes however accepting the whole string quoted with single quotes for legacy support.
                    $newvalues[] = substr($value, 1, -1);
                } else {
                    $newvalues = trim_array(str_getcsv($value));
                }
            }

            if ($fieldinfo['type'] == FIELD_TYPE_DATE_RANGE) {
                // Check it is in the correct format
                $rangeregex = "/^(\d{4})(-\d{2})?(-\d{2})?\/(\d{4})(-\d{2})?(-\d{2})?/";
                // If this is a date range field we need to add values to the field options
                if (!preg_match($rangeregex, $value, $matches)) {
                    debug("ERROR - Invalid date range submitted: '" . $value . "'");
                    return false;
                }

                $rangedates = array_map('trim', explode('/', $value));
                $rangestart = str_pad($rangedates[0], 10, "-00");
                $rangeendparts = explode("-", $rangedates[1]);
                $rangeendyear = $rangeendparts[0];
                $rangeendmonth = isset($rangeendparts[1]) ? $rangeendparts[1] : 12;
                $rangeendday = isset($rangeendparts[2]) ? $rangeendparts[2] : cal_days_in_month(CAL_GREGORIAN, $rangeendmonth, $rangeendyear);
                $rangeend = $rangeendyear . "-" . $rangeendmonth . "-" . $rangeendday;

                $current_dates = array_column($fieldnodes, "ref", "name");
                $nodes_to_add[] = $current_dates[$rangestart] ?? set_node(null, $fieldinfo["ref"], $rangestart, null, null);
                $nodes_to_add[] = $current_dates[$rangeend] ?? set_node(null, $fieldinfo["ref"], $rangeend, null, null);

                $value = $rangestart . DATE_RANGE_SEPARATOR . $rangeend;
            } elseif ($fieldinfo['type'] == FIELD_TYPE_CATEGORY_TREE) {
                // Values may have been passed as full paths or the individual node names
                // Create array with indexes as the values to look for
                $nodes_available_keys  = [];
                $nodes_available_lower_keys  = [];
                foreach ($fieldnodes as $fieldnode) {
                    $nodes_available_keys[$fieldnode["path"]] = $fieldnode["ref"];
                    $nodes_available_keys[$fieldnode["translated_path"]] = $fieldnode["ref"];
                    $nodes_available_keys[$fieldnode["name"]] = $fieldnode["ref"];
                    $nodes_available_keys[$fieldnode["translated_name"]] = $fieldnode["ref"];
                    // Add case insensitive to check in case matching fails
                    $nodes_available_lower_keys[mb_strtolower($fieldnode["path"])] = $fieldnode["ref"];
                    $nodes_available_lower_keys[mb_strtolower($fieldnode["translated_path"])] = $fieldnode["ref"];
                    $nodes_available_lower_keys[mb_strtolower($fieldnode["name"])] = $fieldnode["ref"];
                    $nodes_available_lower_keys[mb_strtolower($fieldnode["translated_name"])] = $fieldnode["ref"];
                }

                foreach ($newvalues as $newvalue) {
                    $validval = false;
                    // Check if a full node path has been passed
                    if (isset($nodes_available_keys[$newvalue])) {
                        debug("update_field: Found node #" . $newvalue  . " for tree value: '" . trim($newvalue) . "'");
                        $nodes_to_add[] = $nodes_available_keys[$newvalue];
                        $validval = true;
                    } elseif (isset($nodes_available_lower_keys[mb_strtolower($newvalue)])) {
                        debug("update_field: Found node #" . $newvalue  . " for tree value: '" . trim($newvalue) . "'");
                        $nodes_to_add[] = $nodes_available_lower_keys[$newvalue];
                        $validval = true;
                    }
                    if (!$validval) {
                        // Check for separate name values
                        $splitvalues = array_map('trim', explode('/', $newvalue));
                        foreach ($splitvalues as $splitvalue) {
                            # Check if each new value exists in current options list
                            if (isset($nodes_available_keys[$splitvalue])) {
                                debug("update_field: Found node # " . $nodes_available_keys[$splitvalue]  . " for tree value: '" . trim($splitvalue) . "'");
                                $nodes_to_add[] = $nodes_available_keys[$splitvalue];
                                $validval = true;
                            } elseif (isset($nodes_available_lower_keys[mb_strtolower($splitvalue)])) {
                                debug("update_field: Found node # " . $nodes_available_lower_keys[$splitvalue]  . " for tree value: '" . trim($splitvalue) . "'");
                                $nodes_to_add[] = $nodes_available_lower_keys[$splitvalue];
                                $validval = true;
                            }
                        }
                    }
                    if (!$validval) {
                        // Still not found - invalid option passed
                        debug("update_field: " . $newvalue . " not found: in field tree");
                        return false;
                    }

                    // Add all parent nodes
                    if ($category_tree_add_parents) {
                        foreach ($nodes_to_add as $node_to_add) {
                            $parent = $nodes_by_ref[$node_to_add]["parent"];
                            while ((int)$parent > 0) {
                                if (isset($nodes_by_ref[$parent])) {
                                    $nodes_to_add[] = $nodes_by_ref[$parent]["ref"];
                                    $parent = $nodes_by_ref[$parent]["parent"];
                                } else {
                                    $parent = 0;
                                }
                            }
                        }
                    }
                }
            } else {
                // Check to see other nodes that need to be added
                foreach ($newvalues as $newvalue) {
                    $valuenode = get_node_id($newvalue, $field);
                    if ($valuenode) {
                        // Add to array of nodes, unless it has been added to array already as a parent for a previous node
                        if (!in_array($valuenode, $nodes_to_add)) {
                            $nodes_to_add[] = $valuenode;
                        }
                        if (!in_array($valuenode, $current_field_node_ids)) {
                            $newnodes[] = $valuenode;
                        }
                    } else {
                        // Check for translated versions and different cases

                        if ($fieldinfo['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST) {
                            // Special case for dynamic keywords as there will likely be a lot of them. Instead of getting and checking all of them,
                            // $fieldnodes to get subset of existing nodes for checking of language translations using like match by node name.
                            $fieldnodes = get_nodes($field, null, false, null, null, $newvalue, false, false, false);
                        }

                        $matchnode = 0;
                        foreach ($fieldnodes as $fieldnode) {
                            if (
                                i18n_get_translated($fieldnode["name"]) == i18n_get_translated($newvalue)
                                && !in_array($fieldnode["ref"], $nodes_to_add)
                            ) {
                                $matchnode = $fieldnode["ref"];
                                break;
                            } elseif (
                                mb_strtolower(i18n_get_translated($fieldnode["name"])) == mb_strtolower(i18n_get_translated($newvalue))
                                && !in_array($fieldnode["ref"], $nodes_to_add)
                            ) {
                                // Check for node with same text in different case
                                $matchnode = $fieldnode["ref"];
                                break;
                            }
                        }

                        if ($matchnode > 0) {
                            if (!in_array($matchnode, $nodes_to_add)) {
                                $nodes_to_add[] = $matchnode;
                            }
                            if (!in_array($matchnode, $current_field_node_ids)) {
                                $newnodes[] = $matchnode;
                            }
                        } elseif ($fieldinfo['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && !checkperm('bdk' . $field)) {
                            // Dynamic keyword, add as new node
                            // Append the option and update the field
                            $newnode            = set_node(null, $field, trim($newvalue), null, null);
                            $nodes_to_add[]     = $newnode;
                            debug("update_field: field option added: '" . trim($newvalue));
                            clear_query_cache("schema");
                        }
                    }
                } // End of foreach $newvalue
            }
            $nodes_to_remove = array_diff($current_field_node_ids, $nodes_to_add);
        } // End of $nodevalues test

        // Now carry out the node additions and removals
        if (count($nodes_to_add) > 0 || count($nodes_to_remove) > 0) {
            # Work out what nodes need to be added/removed/kept
            $nodes_to_add       = array_unique($nodes_to_add);
            $added_nodes        = array_diff($nodes_to_add, $current_field_node_ids);
            if (
                in_array($fieldinfo['type'], [FIELD_TYPE_RADIO_BUTTONS,FIELD_TYPE_DROP_DOWN_LIST])
                &&
                (count($added_nodes) + count($current_field_node_ids) - count($nodes_to_remove)) > 1
            ) {
                // Only a single value allowed
                return false;
            }

            // Update resource_node table and log
            db_begin_transaction("update_field_{$field}");
            if (count($nodes_to_remove) > 0) {
                delete_resource_nodes($resource, $nodes_to_remove, false);
            }
            if (count($nodes_to_add) > 0) {
                add_resource_nodes($resource, $nodes_to_add, false, false);
            }

            // Update log
            if ($log && (count($nodes_to_add) > 0 || count($nodes_to_remove) > 0)) {
                log_node_changes($resource, $nodes_to_add, $current_field_node_ids);
                // Don't need to log this later
                $log = false;
            }

            db_end_transaction("update_field_{$field}");

            if ($fieldinfo['type'] == FIELD_TYPE_CATEGORY_TREE) {
                $all_treenodes = get_cattree_nodes_ordered($field, $resource, false); # True means get all nodes; False means get selected nodes
                $treenodenames = get_cattree_node_strings($all_treenodes, true); # True means names are paths to nodes; False means names are node names
                $value = implode(",", $treenodenames);
                $data_joins_field_value = implode($GLOBALS['field_column_string_separator'], $treenodenames);
            } else {
                $node_names = [];
                foreach ($nodes_to_add as $ref) {
                    $returned_node = [];
                    if (get_node($ref, $returned_node)) {
                        $node_names[] = $returned_node["name"];
                    }
                }
                $value = implode(",", $node_names);
                $data_joins_field_value = implode($GLOBALS['field_column_string_separator'], $node_names);
            }
        } // End of adding/removing nodes
        else {
            // No node changes were made e.g. blocked adding new value with bdk permission. Nothing to log. Prevents resource_log()
            $log = false;
        }
    } else {
        # Fetch previous value
        $existing_resource_node = get_resource_nodes($resource, $field, true)[0] ?? [];
        $existing = $existing_resource_node["name"] ?? "";
        if ($value === $existing) {
            // Nothing to do
            return true;
        }

        if (
            $GLOBALS['use_native_input_for_date_field']
            && $fieldinfo['type'] === FIELD_TYPE_DATE
            && $value !== ''
        ) {
            // Establish whether date is a valid date or a valid date time
            $value_valid_date_part = false;
            if (validateDatetime($value, 'Y-m-d')) {
                $value_valid_date_part = true;
            } elseif (validateDatetime($value, 'Y-m-d H:i:s')) {
                $value_valid_date_part = true;
            }
            // Return if there is no valid date part
            if (!$value_valid_date_part) {
                $errors[] = sprintf('%s: %s', i18n_get_translated($fieldinfo['title']), $lang['invalid_date_generic']);
                return false;
            }
            // We only use the valid date part, so discard the time part if any
            if ($value_valid_date_part) {
                $value_parts = explode(" ", $value);
                $value = $value_parts[0];
            }
        }

        $curnode = $existing_resource_node["ref"] ?? 0 ;
        if ($curnode > 0 && get_nodes_use_count([$curnode]) == 1) {
            // Reuse same node
            set_node($curnode, $field, $value, null, 0);
        } else {
            // Remove node from resource and create new node
            delete_resource_nodes($resource, [$curnode], false);
            $savenode = set_node(null, $field, $value, null, 0);
            add_resource_nodes($resource, [$savenode], true, false);
        }
    }

    # If this is a 'joined' field we need to add it to the resource column
    $joins = get_resource_table_joins();
    if (in_array($fieldinfo['ref'], $joins)) {
        update_resource_field_column($resource, $field, $data_joins_field_value);
    }

    # Add any onchange code
    if ($fieldinfo["onchange_macro"] != "") {
        $macro_resource_id = $resource;
        eval(eval_check_signed($fieldinfo["onchange_macro"]));
    }

    # Allow plugins to perform additional actions.
    // Log this update
    if ($log && $value != $existing) {
        resource_log($resource, LOG_CODE_EDITED, $field, "", $existing, unescape($value));
    }

    # Allow plugins to perform additional actions.
    hook("update_field", "", array($resource,$field,$value,$existing,$fieldinfo,$newnodes,$newvalues));
    return true;
}

function email_resource($resource, $resourcename, $fromusername, $userlist, $message, $access = -1, $expires = "", $sharepwd = "", $useremail = "", $from_name = "", $cc = "", $list_recipients = false, $open_internal_access = false, $useraccess = 2, $group = "")
{
    # Attempt to resolve all users in the string $userlist to user references.

    global $baseurl,$email_from,$applicationname,$lang,$userref,$usergroup;

    if ($useremail == "") {
        $useremail = $email_from;
    }
    if ($group == "") {
        $group = $usergroup;
    }

    # remove any line breaks that may have been entered
    $userlist = str_replace("\\r\\n", ",", $userlist);

    if (trim($userlist) == "") {
        return $lang["mustspecifyoneusername"];
    }
    $userlist = resolve_userlist_groups($userlist);
    if (strpos($userlist, $lang["groupsmart"] . ": ") !== false) {
        $userlist_with_groups = $userlist;
        $groups_users = resolve_userlist_groups_smart($userlist, true);
        if ($groups_users != '') {
            if ($userlist != "") {
                $userlist = remove_groups_smart_from_userlist($userlist);
                if ($userlist != "") {
                    $userlist .= ",";
                }
            }
            $userlist .= $groups_users;
        }
    }

    $ulist = trim_array(explode(",", $userlist));
    $ulist = array_filter($ulist);
    $ulist = array_values($ulist);

    $ulist = trim_array(explode(",", $userlist));
    $ulist = array_filter($ulist);
    $ulist = array_values($ulist);

    $emails = array();
    $key_required = array();

    $emails_keys = resolve_user_emails($ulist);

    if (0 === count($emails_keys)) {
        return $lang['email_error_user_list_not_valid'];
    }

    $unames       = $emails_keys['unames'];
    $emails       = $emails_keys['emails'];
    $key_required = $emails_keys['key_required'];

    # Send an e-mail to each resolved user / e-mail address
    $subject = "$applicationname: $resourcename";
    if ($fromusername == "") {
        $fromusername = $applicationname;
    } // fromusername is used for describing the sender's name inside the email
    if ($from_name == "") {
        $from_name = $applicationname;
    } // from_name is for the email headers, and needs to match the email address (app name or user name)

    // Remove links from the sharing message entered by the user to prevent inclusion in the email or system message.
    $message = strip_tags_and_attributes($message);

    $message = str_replace(array("\\n","\\r","\\"), array("\n","\r",""), $message);

    $resolve_open_access = false;

    for ($n = 0; $n < count($emails); $n++) {
        $key = "";
        # Do we need to add an external access key for this user (e-mail specified rather than username)?
        if ($key_required[$n]) {
            $k = generate_resource_access_key($resource, $userref, $access, $expires, $emails[$n], $group, $sharepwd);
            $key = "&k=" . $k;
        } elseif ($useraccess == 0 && $open_internal_access && !$resolve_open_access) {
            debug("smart_groups: going to resolve open access");
            # get this all done at once
            resolve_open_access((isset($userlist_with_groups) ? $userlist_with_groups : $userlist), $resource, $expires);
            $resolve_open_access = true;
        }

        # make vars available to template
        global $watermark;
        $templatevars['thumbnail'] = get_resource_path($resource, true, "thm", false, "jpg", -1, 1, ($watermark) ? (($access == 1) ? true : false) : false);
        if (!file_exists($templatevars['thumbnail'])) {
            $resourcedata = get_resource_data($resource);
            $templatevars['thumbnail'] = "../gfx/no_preview/default.png";
        }
        $templatevars['url'] = $baseurl . "/?r=" . $resource . $key;
        $templatevars['fromusername'] = $fromusername;
        $templatevars['message'] = $message;
        $templatevars['resourcename'] = $resourcename;
        $templatevars['from_name'] = $from_name;
        if (isset($k)) {
            if ($expires == "") {
                $templatevars['expires_date'] = $lang["email_link_expires_never"];
                $templatevars['expires_days'] = $lang["email_link_expires_never"];
            } else {
                $day_count = round((strtotime($expires) - strtotime('now')) / (60 * 60 * 24));
                $templatevars['expires_date'] = $lang['email_link_expires_date'] . nicedate($expires);
                $templatevars['expires_days'] = $lang['email_link_expires_days'] . $day_count;
                if ($day_count > 1) {
                    $templatevars['expires_days'] .= " " . $lang['expire_days'] . ".";
                } else {
                    $templatevars['expires_days'] .= " " . $lang['expire_day'] . ".";
                }
            }
        } else {
            # Set empty expiration templatevars
            $templatevars['expires_date'] = '';
            $templatevars['expires_days'] = '';
        }

        # Build message and send.
        if (count($emails) > 1 && $list_recipients === true) {
            $body = $lang["list-recipients"] . "\n" . implode("\n", $emails) . "\n\n";
            $templatevars['list-recipients'] = $lang["list-recipients"] . "\n" . implode("\n", $emails) . "\n\n";
        } else {
            $body = "";
        }

        $body .= $templatevars['fromusername'] . " " . $lang["hasemailedyouaresource"] . "\n\n" . $templatevars['message'] . "\n\n" . $lang["clicktoviewresource"] . "\n\n" . $templatevars['url'];
        $send_result = send_mail($emails[$n], $subject, $body, $fromusername, $useremail, "emailresource", $templatevars, $from_name, $cc);
        if ($send_result !== true) {
            return $send_result;
        }

        # log this
        resource_log($resource, LOG_CODE_EMAILED, "", $unames[$n]);
    }
    hook("additional_email_resource", "", array($resource,$resourcename,$fromusername,$userlist,$message,$access,$expires,$useremail,$from_name,$cc,$templatevars));
    # Return an empty string (all OK).
    return "";
}

function delete_resource($ref)
{
    global $userref;
    # Delete the resource, all related entries in tables and all files on disk
    $resource = get_resource_data($ref);

    if (
        !$resource
        ||
            (
                (
                checkperm("D")
                ||
                !get_edit_access($ref, $resource["archive"], $resource)
                ||
                (isset($userref) && $resource["lock_user"] > 0 && $resource["lock_user"] != $userref)
                )
            &&
                !hook('check_single_delete')
            &&
                PHP_SAPI != 'cli'
            )
    ) {
        return false;
    }

    $current_state = $resource['archive'];

    global $resource_deletion_state, $staticsync_allow_syncdir_deletion, $storagedir;
    if (isset($resource_deletion_state) && $current_state != $resource_deletion_state) { # Really delete if already in the 'deleted' state.
        # $resource_deletion_state is set. Do not delete this resource, instead move it to the specified state.
        update_archive_status($ref, $resource_deletion_state, $current_state);

        # log this so that administrator can tell who requested deletion
        resource_log($ref, LOG_CODE_DELETED, '');

        return true;
    }

    # FStemplate support - do not allow samples from the template to be deleted
    if (resource_file_readonly($ref)) {
        return false;
    }

    # Is transcoding
    if ($resource['is_transcoding'] == 1) {
        return false;
    } # Can't delete when transcoding

    # Delete files first
    $extensions = array();
    $extensions[] = $resource['file_extension'] ? $resource['file_extension'] : "jpg";
    $extensions[] = $resource['preview_extension'] ? $resource['preview_extension'] : "jpg";
    $extensions[] = $GLOBALS['ffmpeg_preview_extension'];
    $extensions[] = 'icc'; // also remove any extracted icc profiles
    $extensions = array_unique($extensions);

    foreach ($extensions as $extension) {
        if (in_array(strtolower($extension), ['jpg', 'jpeg'])) {
            $sizes = get_image_sizes($ref, true, $extension);
        } else {
            $sizes = [['path' => get_resource_path($ref, true, '', false, $extension)]];
        }
        foreach ($sizes as $size) {
            if (
                file_exists($size['path'])
                && ($staticsync_allow_syncdir_deletion || false !== strpos($size['path'], $storagedir))
            ) { // Only delete if file is in filestore
                $pathtofile = realpath($size['path']); // avoid passing relative path to unlink function to prevent error on removal of file.
                unlink($pathtofile);
            }
        }
    }

    hook('delete_resource_extra', '', array($resource));

    # Delete any alternative files
    $alternatives = get_alternative_files($ref);
    for ($n = 0; $n < count($alternatives); $n++) {
        delete_alternative_file($ref, $alternatives[$n]['ref']);
    }

    // Attempt to remove directory
    $resource_path = get_resource_path($ref, true, "pre", true);

    $dirpath = dirname($resource_path);
    hook('delete_resource_path_extra', '', array($dirpath));
    rcRmdir($dirpath); // try to delete directory, but if we do not have permission fail silently for now

    hook("beforedeleteresourcefromdb", "", array($ref));

    # Delete all database entries
    clear_resource_data($ref);
    resource_log($ref, LOG_CODE_DELETED_PERMANENTLY, '');
    ps_query("delete from resource where ref=?", array("i",$ref));
    ps_query("delete from collection_resource where resource=?", array("i",$ref));
    ps_query("delete from resource_custom_access where resource=?", array("i",$ref));
    ps_query("delete from external_access_keys where resource=?", array("i",$ref));
    ps_query("delete from resource_alt_files where resource=?", array("i",$ref));
    ps_query(
        "    DELETE an
               FROM annotation_node AS an
         INNER JOIN annotation AS a ON a.ref = an.annotation
              WHERE a.resource = ?",
        array("i",$ref)
    );
    ps_query("DELETE FROM annotation WHERE resource = ?", array("i",$ref));
    hook("afterdeleteresource");

    clear_query_cache("stats");

    return true;
}

/**
* Returns field data from resource_type_field for the given field
*
* @uses ps_query()
*
* @param integer $field Resource type field ID
*
* @return boolean|array
*/
function get_resource_type_field($field)
{
    $rtf_query = "SELECT " . columns_in("resource_type_field", "rtf") . ", GROUP_CONCAT(rtfrt.resource_type) resource_types FROM resource_type_field rtf LEFT JOIN resource_type_field_resource_type rtfrt ON rtfrt.resource_type_field=rtf.ref WHERE rtf.ref = ? GROUP BY rtf.ref";
    $return = ps_query($rtf_query, array("i",$field), "schema");

    if (0 == count($return)) {
        return false;
    } else {
        return $return[0];
    }
}

/**
 * get_resource_field_data
 *
 * @param  int  $ref                Resource ID
 * @param  bool $multi              Get all fields? False by default (only fields that apply to the given resource type)
 * @param  bool $use_permissions    Honour user permissions e.g. field access. TRUE by default
 * @param  int  $originalref        Original resource ID to get data for. NULL by default
 * @param  bool $external_access    Only get data permitted to view externally. FALSE by default
 * @param  bool $ord_by             Use field order_by setting. FALSE by default (order is by resource type first)
 * @param  bool $forcsv             Get data for CSV export (uses \ separator for category tree nodes). FALSE by default
 * @param  bool $translate_value    Field value will be translated with i18n_get_translated() unless FALSE supplied.
 *
 * @return array|boolean
 */
function get_resource_field_data($ref, $multi = false, $use_permissions = true, $originalref = null, $external_access = false, $ord_by = false, $forcsv = false, $translate_value = true)
{
    # Returns field data and field properties (resource_type_field and resource_data tables)
    # for this resource, for display in an edit / view form.
    # Standard field titles are translated using $lang.  Custom field titles are i18n translated.

    global $view_title_field, $metadata_template_resource_type, $NODE_FIELDS, $FIXED_LIST_FIELD_TYPES, $pagename;

    # Find the resource type.
    if (is_null($originalref)) {
        $originalref = $ref;
    } # When a template has been selected, only show fields for the type of the original resource ref, not the template (which shows fields for all types)
    $rtype = ps_value("SELECT resource_type value FROM resource WHERE ref=?", array("i",$originalref), 0);
    $rtype = ($rtype == "") ? 0 : $rtype;

    if ($use_permissions && checkperm("T" . $rtype)) {
        return false;
    }

    # If using metadata templates,
    if (isset($metadata_template_resource_type) && $metadata_template_resource_type == $rtype) {
        # Show all resource fields, just as with editing multiple resources.
        $multi = true;
    }

    $return           = array();
    $order_by_sql     = ($ord_by ? 'f.order_by, f.ref' : 'f.global desc, f.order_by, f.ref');

    // Category tree fields need special handling
    $nontree_field_types    = array_diff($NODE_FIELDS, array(FIELD_TYPE_CATEGORY_TREE));

    $field_restypes = get_resource_type_field_resource_types();
    $restypesql = "";
    $restype_params = [];
    if (!$multi) {
        $restypesql = "AND (f.global=1 OR f.ref=? OR f.ref IN (SELECT resource_type_field FROM resource_type_field_resource_type rtjoin WHERE rtjoin.resource_type=?))";
        $restype_params[] = "i";
        $restype_params[] = $view_title_field;
        $restype_params[] = "i";
        $restype_params[] = $rtype;
    }

    $field_info_sql =
    "SELECT f.ref resource_type_field,
            f.ref AS fref,
            f.required AS frequired, " .
            columns_in("resource_type_field", "f") .
    " FROM resource_type_field f
    WHERE (f.active=1 AND f.type IN (" . ps_param_insert(count($nontree_field_types)) . ") " . $restypesql . ")
    ORDER BY {$order_by_sql}";

    $field_info_params = array_merge(ps_param_fill($nontree_field_types, "i"), $restype_params);

    # Fetch field information first
    $fields = ps_query($field_info_sql, $field_info_params);

    # Now fetch field values ensuring that multiple node values are ordered correctly
    $field_value_sql =
    "SELECT rn.resource, n.resource_type_field `field`, rn.node, n.name, n.order_by, n.parent
        FROM resource_node rn
    INNER JOIN node n on n.ref = rn.node and rn.resource=?
    ORDER BY n.resource_type_field, n.order_by";

    $field_values = ps_query($field_value_sql, array("i", $ref));

    $field_node_list = [];
    $field_ref_list = [];
    $arr_field_nodes = []; // For later re-ordering
    $last_field = null;

    # Assemble comma separated lists of node names and refs to attach to the fields array
    foreach ($field_values as $field_value) {
        $this_field = $field_value['field'];
        if ($this_field === $last_field) {
            $field_node_list[$this_field]['values'] .= (", " . $field_value['name']);
            $field_ref_list[$this_field]['refs'] .= ("," . $field_value['node']);
        } else {
            $field_node_list[$this_field]['values'] = $field_value['name'];
            $field_ref_list[$this_field]['refs'] = $field_value['node'];
            $last_field = $this_field;
        }
        $arr_field_nodes[$this_field][] = [
            "ref" => $field_value['node'],
            "resource_type_field" => $this_field,
            "name" => $field_value['name'],
            "parent" => $field_value['parent'],
            "order_by" => $field_value['order_by'],
        ];
    }

    # Attach the lists of node names and refs to the corresponding fields array entry
    foreach (array_keys($fields) as $fikey) {
        $this_field = $fields[$fikey]['ref'];
        if (isset($field_node_list[$this_field])) {
            $fields[$fikey]['value'] = $field_node_list[$this_field]['values'];
            $fields[$fikey]['nodes'] = $field_ref_list[$this_field]['refs'];
        } else {
            $fields[$fikey]['value'] = null;
            $fields[$fikey]['nodes'] = null;
        }
    }

    # Build an array of valid resource types and only return fields for these types. Translate field titles.
    if ($multi) {
        // Get all fields
        $valid_resource_types = ps_array('SELECT ref AS `value` FROM resource_type', [], 'schema');
    } else {
        $valid_resource_types = [$rtype] ;
    }

    # Add category tree values, reflecting tree structure
    $tree_fields = get_resource_type_fields($valid_resource_types, "ref", "asc", '', array(FIELD_TYPE_CATEGORY_TREE));
    foreach ($tree_fields as $tree_field) {
        $addfield = $tree_field;

        $treenodes = get_cattree_nodes_ordered($tree_field["ref"], $ref, false); # True means get all nodes; False means get selected nodes
        $treenodenames = get_cattree_node_strings($treenodes, true); # True means names are paths to nodes; False means names are node names

        // Quoting each element is required for csv export
        $valstring = $forcsv ? ("\"" . implode("\",\"", $treenodenames) . "\"") : implode(",", $treenodenames);
        $addfield["value"] = count($treenodenames) > 0 ? $valstring : "";
        $addfield["resource_type_field"] = $tree_field["ref"];
        $addfield["fref"] = $tree_field["ref"];
        $fields[] = $addfield;
    }
    if (empty($fields)) {
        return false;
    }

    foreach ($fields as $fkey => $field) {
        $fieldorder_by[$fkey]   = $field["order_by"];
        $fieldglobal[$fkey]     = $field["global"];
        $fieldref[$fkey]        = $field["ref"];
    }
    if ($ord_by) {
        array_multisort($fieldorder_by, SORT_ASC, $fieldglobal, SORT_ASC, $fieldref, SORT_ASC, $fields);
    } else {
        array_multisort($fieldglobal, SORT_DESC, $fieldorder_by, SORT_ASC, $fieldref, SORT_ASC, $fields);
    }

    for ($n = 0; $n < count($fields); $n++) {
        if (
                (!$use_permissions
                ||
                ($ref < 0 && checkperm("P" . $fields[$n]["fref"])) // Upload only edit access to this field
                ||
                (metadata_field_view_access($fields[$n]["fref"]))
                )
            &&
                (!($external_access && !$fields[$n]["external_user_access"]))
        ) {
            debug("field" . $fields[$n]["title"] . "=" . $fields[$n]["value"]);
            $fields[$n]["title"] = lang_or_i18n_get_translated($fields[$n]["title"], "fieldtitle-");
            // Add in associated resource types
            $fields[$n]["resource_types"] = implode(",", $field_restypes[$fields[$n]["ref"]]);
            // Sort nodes
            if (
                in_array($fields[$n]['type'], $FIXED_LIST_FIELD_TYPES)
                && $fields[$n]['type'] != FIELD_TYPE_CATEGORY_TREE
                && trim($fields[$n]['nodes'] ?? "") != ""
            ) {
                $fieldnodes = $arr_field_nodes[$fields[$n]["ref"]];
                if ((bool) $fields[$n]['automatic_nodes_ordering']) {
                    $fieldnodes = reorder_nodes($fieldnodes);
                }
                $fieldnodes = array_column($fieldnodes, 'name', 'ref');
                if ($translate_value) {
                    $fieldnodes = array_map("i18n_get_translated", $fieldnodes);
                }
                $fields[$n]['value'] = implode(", ", $fieldnodes);
                $fields[$n]['nodes_values'] = $fieldnodes;
            }

            $return[] = $fields[$n];
        }
    }
    # Remove duplicate fields
    $return = array_unique($return, SORT_REGULAR);

    # Return reindexed array
    $return = array_values($return);

    return $return;
}

/**
 * get_resource_field_data_batch - Get all resource data for the given resources
 *
 * Returns a multidimensional array with resource IDs as top level keys, then fields (order determined by $ord_by setting)
 * IMPORTANT: This differs from get_resource_field_data() in that only fields containing data will be returned.
 *
 * e.g.
 * Array
 * (
 *     [119912] => array
 *         (
 *          [0] => Array
 *              (
 *              [resource] => 119912
 *              [value] => This is the title of resource 119912
 *              [resource_type_field] => 8
 *              [ref] => 8
 *              [name] => title
 *              [title] => Title
 *              [field_constraint] => 0
 *              [type] => 1))
 *              ....
 *
 *
 * @param array $resources (either an array of resource ids or an array returned from search results)
 * @param  bool $use_permissions    Honour user permissions e.g. field access. TRUE by default
 * @param  bool $external_access    Only get data permitted to view externally. FALSE by default
 * @param  bool $ord_by             Use field order_by setting. FALSE by default (order is by resource type first)
 * @param  mixed $exportoptions     Array of options as below
 *                                   "csvexport" (bool) - get data for CSV export (uses \ separator for category tree nodes)
 *                                   "personal"  (bool) - include data in fields marked as personal
 *                                   "alldata"   (bool) - include data in all fields, including technical metadata
 *
 * @return array                    Array of resource data organised by resource then metadata field ID
 */
function get_resource_field_data_batch($resources, $use_permissions = true, $external_access = false, $ord_by = false, $exportoptions = array())
{
    # Returns field data and field properties (resource_type_field and resource_data tables)
    # for all the resource references in the array $refs.
    # This will use a single SQL query and is therefore a much more efficient way of gathering
    # resource data for a list of resources (e.g. search result display for a page of resources).
    if (count($resources) == 0) {
        return array();
    } # return an empty array if no resources specified (for empty result sets)

    global $view_title_field, $NODE_FIELDS;

    $restype = $allresdata = [];
    $csvexport = isset($exportoptions["csvexport"]) ? $exportoptions["csvexport"] : false;
    $personal = isset($exportoptions["personal"]) ? $exportoptions["personal"] : false;
    $alldata = isset($exportoptions["alldata"]) ? $exportoptions["alldata"] : false;
    $nontree_field_types = array_diff($NODE_FIELDS, array(FIELD_TYPE_CATEGORY_TREE));

    // Get field_info
    $tree_fields = get_resource_type_fields("", "ref", "asc", '', array(FIELD_TYPE_CATEGORY_TREE));
    $field_restypes = get_resource_type_field_resource_types();
    $fields_info = ps_query(
        "SELECT f.ref resource_type_field,
                f.ref AS fref,
                f.required AS frequired, " .
                columns_in("resource_type_field", "f") .
        " FROM resource_type_field f
        WHERE (f.active=1 AND f.type IN (" . ps_param_insert(count($nontree_field_types)) . "))
        ORDER BY f.order_by, f.ref",
        ps_param_fill($nontree_field_types, 'i')
    );

    # Build arrays of resources
    $resource_chunks = array_chunk($resources, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    foreach ($resource_chunks as $resource_chunk) {
        if (isset($resource_chunk[0]["resource_type"])) {
            // This is an array of search results so we already have the resource types
            $restype = array_column($resource_chunk, "resource_type", "ref");
            $resourceids = array_filter(array_column($resource_chunk, "ref"), 'is_int_loose');
            $getresources = $resource_chunk;
        } else {
            $resource_chunk = array_filter($resource_chunk, 'is_int_loose');
            if ($resource_chunk === []) {
                break;
            }
            $resourceids = $resource_chunk;
            $allresourcedata = ps_query("SELECT ref, resource_type FROM resource WHERE ref IN (" . ps_param_insert(count($resource_chunk)) . ")", ps_param_fill($resource_chunk, "i"));
            foreach ($allresourcedata as $resourcedata) {
                $restype[$resourcedata["ref"]] = $resourcedata["resource_type"];
            }
            $getresources = array();
            foreach ($resource_chunk as $resource) {
                $getresources[]["ref"] = $resource;
            }
        }

        # Fields array is no longer built from direct SQL using group concat
        $fields = array();

        # Fetch field values at node level for all resources in chunk ensuring that multiple node values are ordered correctly
        $field_value_sql =
        "SELECT rn.resource, n.resource_type_field `field`, rn.node, n.name 
            FROM resource_node rn
        INNER JOIN node n on n.ref = rn.node and rn.resource IN (" . ps_param_insert(count($resourceids)) . ")
        ORDER BY rn.resource, n.resource_type_field, n.order_by";

        $field_values_allresources = ps_query($field_value_sql, ps_param_fill($resourceids, "i"));

        # Append an ending sentinal
        $field_values_allresources[] = array('resource' => null,'field' => null,'node' => null,'name' => null);

        $field_node_list = array();
        $field_ref_list = array();
        $field_node_values = array();
        $last_resource = null;
        $last_field = null;

        # Assemble comma separated lists of node names and refs to attach to the fields array
        foreach ($field_values_allresources as $field_value) {
            $this_resource = $field_value['resource'];
            if ($this_resource === null && $last_resource === null) {
                break;
            } # Sentinal reached, nothing to assemble
            $this_field = $field_value['field'];
            if ($this_resource === $last_resource) {
                # Same resource
                if ($this_field === $last_field) {
                    # Same field so append
                    $field_node_list[$this_field]['values'] .= (", " . $field_value['name']);
                    $field_ref_list[$this_field]['refs'] .= ("," . $field_value['node']);
                } else {
                    # New field so replace
                    $field_node_list[$this_field]['values'] = $field_value['name'];
                    $field_ref_list[$this_field]['refs'] = $field_value['node'];
                    $last_field = $this_field;
                }
                $field_node_values[$this_field][$field_value['node']] = $field_value['name'];
            } else {
                # Change of resource
                if (!is_null($last_resource)) {
                    # Process the data just assembled for the last resource

                    # Build an array of fields for this resource using the fields_info array
                    foreach (array_keys($fields_info) as $fikey) {
                        $this_base_field = $fields_info[$fikey]['resource_type_field'];
                        # Add base field info for this field
                        $data_for_this_field = $fields_info[$fikey];
                        # Now enrich the data for this field for this resource
                        $data_for_this_field['resource'] = $last_resource;
                        if (isset($field_node_list[$this_base_field])) {
                            $data_for_this_field['value'] = $field_node_list[$this_base_field]['values'];
                            $data_for_this_field['nodes'] = $field_ref_list[$this_base_field]['refs'];
                            $data_for_this_field['nodes_values'] = $field_node_values[$this_base_field];
                            # The data for this resource field is ready so attach it to the final result array for this chunk
                            $fields[] = $data_for_this_field;
                        } else {
                            # Empty fields are not attached
                            $data_for_this_field['value'] = null;
                            $data_for_this_field['nodes'] = null;
                            $data_for_this_field['node_values'] = null;
                        }
                    }

                    # Clear node list and ref list for new resource
                    $field_node_list = array();
                    $field_ref_list = array();
                    $field_node_values = [];
                    $last_field = null;
                    if ($this_resource === null) {
                        break;
                    } # Sentinal reached, assembly is complete
                }
                # Attach
                if ($this_field === $last_field) {
                    # Same field so append
                    $field_node_list[$this_field]['values'] .= (", " . $field_value['name']);
                    $field_ref_list[$this_field]['refs'] .= ("," . $field_value['node']);
                } else {
                    # New field so replace
                    $field_node_list[$this_field]['values'] = $field_value['name'];
                    $field_ref_list[$this_field]['refs'] = $field_value['node'];
                    $last_field = $this_field;
                }
                $field_node_values[$this_field][$field_value['node']] = $field_value['name'];
                $last_resource = $this_resource;
            }
        } # End of allresources for this chunk

        foreach ($tree_fields as $tree_field) {
            // Establish the tree strings for all nodes belonging to the tree field
            $addfield = $tree_field;
            // Now for each resource, build an array consisting of all of the paths for the selected nodes
            foreach ($getresources as $getresource) {
                $treenodes = get_cattree_nodes_ordered($tree_field["ref"], $getresource["ref"], false); # True means get all nodes; False means get selected nodes
                $treenodenames = get_cattree_node_strings($treenodes, true); # True means names are paths to nodes; False means names are node names

                $valstring = $csvexport ? ("\"" . implode("\",\"", $treenodenames) . "\"") : implode(",", $treenodenames);

                $addfield["resource"] = $getresource["ref"];
                $addfield["value"] = count($treenodenames) > 0 ? $valstring : "";
                $addfield["resource_type_field"] = $tree_field["ref"];
                $addfield["fref"] = $tree_field["ref"];
                $fields[] = $addfield;
            }
        }

        // Prepare the all resource data array if there is data
        if (empty($fields)) {
            return array();
        }

        // Convert to array with resource ID as index
        for ($n = 0; $n < count($fields); $n++) {
            $rowadded = false;
            if (!isset($allresdata[$fields[$n]["resource"]])) {
                $allresdata[$fields[$n]["resource"]] = [];
            }

            // Skip fields which are not applicable
            if ($fields[$n]["global"] != 1 && (!isset($field_restypes[$fields[$n]["ref"]]) || !in_array($restype[$fields[$n]["resource"]], $field_restypes[$fields[$n]["ref"]]))) {
                // This resource's resource_type is not associated with this field
                continue;
            }

            // Add data to array
            if (
                    (!$use_permissions
                    ||
                    ($fields[$n]["resource"] < 0 && checkperm("P" . $fields[$n]["fref"])) // Upload only edit access to this field
                    ||
                    metadata_field_view_access($fields[$n]["fref"])
                    )
                &&
                    (!($external_access && !$fields[$n]["external_user_access"]))
                &&
                    (!$personal || $fields[$n]["personal_data"])
                &&
                    ($alldata || $fields[$n]["include_in_csv_export"])
            ) {
                $fields[$n]["title"] = lang_or_i18n_get_translated($fields[$n]["title"], "fieldtitle-");
                $allresdata[$fields[$n]["resource"]][$fields[$n]["ref"]] = $fields[$n];
                $rowadded = true;
            }

            # Add title field
            if (
                !$rowadded &&
                    $fields[$n]['ref'] == $view_title_field  #Check field against $title_field for default title reference
                    &&
                    metadata_field_view_access($fields[$n]["fref"]) #Check permissions to access title field
            ) {
                $allresdata[$fields[$n]["resource"]][$fields[$n]["ref"]] = $fields[$n];
            }
        }
    }
    $fields = array();
    foreach ($allresdata as $resourceid => $resdata) {
        $fieldorder_by = array();
        $fieldglobal = array();
        $fieldref = array();
        foreach ($resdata as $fkey => $field) {
            $fieldorder_by[$fkey]   = $field["order_by"];
            $fieldglobal[$fkey]     = $field["global"] == 1 ? 0 : 1;
            $fieldref[$fkey]        = $field["ref"];
        }
        if ($ord_by) {
            array_multisort($fieldorder_by, SORT_ASC, $fieldglobal, SORT_ASC, $fieldref, SORT_ASC, $allresdata[$resourceid]);
        } else {
            array_multisort($fieldglobal, SORT_ASC, $fieldorder_by, SORT_ASC, $fieldref, SORT_ASC, $allresdata[$resourceid]);
        }
    }
    return $allresdata;
}

/**
 * Return an array of resource types that this user has access to
 *
 * @param  string   $types          Comma separated list to limit the types that are returned by ref,
 *                                  blank string returns all available types
 * @param  boolean  $translate      Flag to translate the resource types before returning
 * @param  boolean  $ignore_access  Return all resource types regardless of access?#
 * @param  boolean  $usecache       Return cached result?
 *
 * @return array    Array of resource types limited by T* permissions and optionally by $types
 */
function get_resource_types($types = "", $translate = true, $ignore_access = false, $usecache = false)
{
    global $restype_cache;
    $hash = md5(serialize([$types,$translate,$ignore_access]));
    if ($usecache && isset($restype_cache[$hash])) {
        return $restype_cache[$hash];
    }

    $resource_types = get_all_resource_types();

    if ($types != "") {
        $s = explode(",", $types);
    }

    $return = [];
    for ($n = 0; $n < count($resource_types); $n++) {
        if (
            ($ignore_access || !checkperm('T' . $resource_types[$n]['ref']))
            &&
            (empty($s) || in_array($resource_types[$n]['ref'], $s))
        ) {
            if (!isset($return[$resource_types[$n]["ref"]])) {
                if ($translate) {
                    # Translate name
                    $resource_types[$n]["name"] = lang_or_i18n_get_translated($resource_types[$n]["name"], "resourcetype-");
                }
                $return[$resource_types[$n]["ref"]] = $resource_types[$n]; # Add to return array
                // Create an array to hold associated resource_type_fields, no need to keep the current row's field ID
                $return[$resource_types[$n]["ref"]]["resource_type_fields"] = [];
                unset($return[$resource_types[$n]["ref"]]["resource_type_field"]);
            }
            // Add associated fields to the resource_type_fields array
            $return[$resource_types[$n]["ref"]]["resource_type_fields"] = array_filter(explode(",", (string)$resource_types[$n]["resource_type_field"]), "is_int_loose");
        }
    }
    $restype_cache[$hash] = array_values($return);
    return array_values($return);
}

/**
 * Returns all resources types
 *
 * No permissions are checked or applied, do not expose this function to the API
 *
 * @return array Array of resource types ordered by 'order_by' then 'ref'
 */
function get_all_resource_types()
{
    return ps_query(
        "
         SELECT " . columns_in("resource_type", "rt") . ",
                t.name AS tab_name,
                GROUP_CONCAT(rtfrt.resource_type_field ORDER BY rtfrt.resource_type_field) AS resource_type_field
           FROM resource_type rt
      LEFT JOIN tab t ON t.ref=rt.tab
      LEFT JOIN resource_type_field_resource_type rtfrt
             ON rtfrt.resource_type=rt.ref
       GROUP BY rt.ref
       ORDER BY order_by,
                rt.ref",
        [],
        "schema"
    );
}

function clear_resource_data($resource)
{
    # Clears stored data for a resource.
    ps_query("DELETE FROM resource_dimensions WHERE resource = ?", ["i",$resource]);
    ps_query("DELETE FROM resource_related WHERE resource = ? OR related = ?", ["i",$resource,"i",$resource]);
    delete_all_resource_nodes($resource);

    // Clear all 'joined' fields
    $joins = get_resource_table_joins();
    if (count($joins) > 0) {
        $joins_sql = "";
        foreach ($joins as $join) {
            $joins_sql .= (($joins_sql != "") ? "," : "") . "field" . (int)$join . "=NULL";
        }
        ps_query("UPDATE resource SET $joins_sql WHERE ref = ?", ["i",$resource]);
    }
    return true;
}

/**
*  Create a new resource, copying all data from the resource with reference $from.
*  Note this copies only the data and not any attached file. It's very unlikely the
*  same file would be in the system twice, however users may want to clone an existing resource
*  to avoid reentering data if the resource is very similar.
*  If $resource_type if specified then the resource type for the new resource will be set to $resource_type
*  rather than simply copied from the $from resource.
*
* @param  int    $from            ID of resource
* @param  int    $resource_type   ID of resource type
* @param  string $origin          Origin of resource when uploading, leave blank if not an upload
*
* @return boolean|integer
*/
function copy_resource($from, $resource_type = -1, $origin = '')
{
    debug("copy_resource: copy_resource(\$from = {$from}, \$resource_type = {$resource_type})");
    global $userref;
    global $upload_then_edit;

    # Check that the resource exists
    if (ps_value("SELECT COUNT(*) value FROM resource WHERE ref = ?", ["i",$from], 0) == 0) {
        return false;
    }

    # copy joined fields to the resource column
    $joins = get_resource_table_joins();

    # Filtering data join columns to only copy those relevant to the new resource.
    if ($resource_type === -1) {
        # New resource will be of the same resource type so get fields valid for the source resource type
        $query = 'SELECT rtf.ref AS value FROM resource_type_field AS rtf
            INNER JOIN resource AS r ON (rtf.global = 1 OR rtf.ref IN (SELECT resource_type_field FROM resource_type_field_resource_type rtjoin WHERE rtjoin.resource_type = r.resource_type))
            WHERE r.ref = ?;';
        $relevant_rtype_fields = ps_array($query, ["i", $from]);
    } else {
        # New resource has a different resource type so only copy fields relevant to the new resource type
        $query = 'SELECT ref AS value FROM resource_type_field WHERE global = 1 OR ref IN (SELECT resource_type_field FROM resource_type_field_resource_type WHERE resource_type = ?);';
        $relevant_rtype_fields = ps_array($query, ["i", $resource_type]);
    }
    $filtered_joins = array_values(array_intersect($joins, $relevant_rtype_fields));
    $joins_sql = empty($filtered_joins) ? '' : ',' . implode(',', array_map(prefix_value('field'), $filtered_joins));

    $archive = ps_value("SELECT archive value FROM resource WHERE ref = ?", ["i",$from], 0);

    if ($archive == "") { // Needed if user does not have a user template
        $archive = 0;
    }

    # Determine if the user has access to the source archive status
    if (!checkperm("e" . $archive)) {
        # Find the right permission mode to use
        for ($n = -2; $n < 3; $n++) {
            if (checkperm("e" . $n)) {
                $archive = $n;
                break;
            }
        }
    }

    $sql = '';
    $params = [];
    if ($resource_type === -1) {
        $sql = 'resource_type';
    } else {
        $sql = '?';
        $params = ['i', $resource_type];
    }

    # First copy the resources row
    # Set no_file=1 because at this stage any resource copied technically has no file until there's some kind of a
    # follow-up processing done (e.g. create a new record and later upload a file for it)
    ps_query(
        "INSERT INTO resource(resource_type,creation_date,rating,archive,access,created_by, no_file $joins_sql)
         SELECT {$sql},now(),rating, ?,access,created_by, 1 $joins_sql
           FROM resource
          WHERE ref= ?",
          array_merge($params, ['i', $archive, 'i', $from])
    );
    $to = sql_insert_id();

    # Set that this resource was created by this user.
    # This needs to be done if either:
    # 1) The user does not have direct 'resource create' permissions and is therefore contributing using My Contributions directly into the active state
    # 2) The user is contributiting via My Contributions to the standard User Contributed pre-active states.
    if ((!checkperm("c")) || $archive < 0) {
        # Update the user record
        ps_query("update resource set created_by=? where ref=?", array("i",$userref,"i",$to));
    }

    # Copy Metadata
    copy_resource_nodes($from, $to);

    # Copy relationships
    copyRelatedResources($from, $to);

    # Copy access
    ps_query("insert into resource_custom_access(resource,usergroup,access) select ?,usergroup,access from resource_custom_access where resource=?", array("i",$to,"i",$from));

    // Set any resource defaults
    // Expected behaviour: set resource defaults only on upload and when
    // there is no edit access OR no existing value
    if (0 > $from || $upload_then_edit) {
        $fields_to_set_resource_defaults = array();
        $fields_data                     = get_resource_field_data($from, false, false);

        // Set resource defaults only for fields user hasn't set
        // $from data may have not been copied to new resource by copy_resource_nodes() if user has no edit access to field
        foreach ($fields_data as $field_data) {
            if (
                metadata_field_edit_access($field_data['ref'])
                && metadata_field_view_access($field_data['ref'])
                && trim((string)$field_data['value']) != "" // Field has a value
                && !($upload_then_edit && $from < 0)
            ) {
                continue;
            }

            $fields_to_set_resource_defaults[] = $field_data['ref'];
        }

        if (0 < count($fields_to_set_resource_defaults)) {
            set_resource_defaults($to, $fields_to_set_resource_defaults);
        }
    }

    // Autocomplete any blank fields without overwriting any existing metadata
    autocomplete_blank_fields($to, false);

    # Log this
    daily_stat("Create resource", $to);
    resource_log($to, LOG_CODE_CREATED, 0, $origin);

    hook("afternewresource", "", array($to));

    return $to;
}

/**
 * Log resource activity
 *
 *
 * @param   int        $resource - resource ref                            -- resource_log.resource
 * @param   string     $type - log code defined in include/definitions.php -- resource_log.type
 * @param   int        $field - resource type field                        -- resource_log.resource_type_field
 * @param   string     $notes - text notes                                 -- resource_log.notes
 * @param   mixed      $fromvalue - original value (int or string)         -- resource_log.previous_value
 * @param   mixed      $tovalue - new value (int or string)
 * @param   int        $usage                                              -- resource_log.usageoption
 * @param   int        $alt_ref - ref of alt file                          -- resource_log.alt_ref
 *
 * @return int (or false)
 */

function resource_log($resource, $type, $field, $notes = "", $fromvalue = "", $tovalue = "", $usage = -1, $alt_ref = -1)
{
    global $userref,$k,$lang,$resource_log_previous_ref, $internal_share_access;

    // Param type checks
    $param_str = array($type,$notes);
    $param_num = array($resource,$usage,$alt_ref);

    foreach ($param_str as $par) {
        if (!is_string($par)) {
            return false;
        }
    }

    foreach ($param_num as $par) {
        if (!is_numeric($par)) {
            return false;
        }
    }

    // check that $usage is valid value for int type db field
    // https://dev.mysql.com/doc/refman/8.0/en/integer-types.html
    $options_db_int = [ 'options' => [ 'min_range' => -2147483648,   'max_range' => 2147483647] ];
    if (!filter_var($usage, FILTER_VALIDATE_INT, $options_db_int) && $usage != 0) {
        return false;
    }

    // If it is worthy of logging, update the modified date in the resource table
    update_timestamp($resource);

    if (($resource === RESOURCE_LOG_APPEND_PREVIOUS && !isset($resource_log_previous_ref)) || ($resource !== RESOURCE_LOG_APPEND_PREVIOUS && $resource < 0)) {
        return false;
    }

    if ($fromvalue === $tovalue) {
        $diff = "";
    } else {
        switch ($type) {
            case LOG_CODE_STATUS_CHANGED:
                $from = $lang["status" . $fromvalue] ?? $lang["statusinvalid"];
                $diff = $lang["status" . $fromvalue] . " -> " . $lang["status" . $tovalue];
                break;

            case LOG_CODE_ACCESS_CHANGED:
                $from = $lang["access" . $fromvalue] ?? $lang["accessinvalid"];
                $diff = $from . " -> " . $lang["access" . $tovalue];
                break;

            // do not do a diff, just dump out whole new value (this is so we can cleanly append transform output)
            case LOG_CODE_TRANSFORMED:
            case LOG_CODE_NODE_REVERT:
            case LOG_CODE_EXTERNAL_UPLOAD:
            case LOG_CODE_CREATED:
                $diff = $tovalue;
                break;

            default:
                $diff = log_diff($fromvalue, $tovalue);
        }
    }

    // Avoid out of memory errors such as when working with large PDF files
    if (mb_strlen($diff) > 10000) {
        $diff = mb_strcut($diff, 0, 10000);
    }

    $modifiedlogtype = hook("modifylogtype", "", array($type));
    if ($modifiedlogtype) {
        $type = $modifiedlogtype;
    }

    $modifiedlognotes = hook("modifylognotes", "", array($notes,$type,$resource));
    if ($modifiedlognotes) {
        $notes = $modifiedlognotes;
    }

    if ($resource === RESOURCE_LOG_APPEND_PREVIOUS) {
        ps_query("UPDATE `resource_log` SET `diff`=left(concat(`diff`,'\n',?),60000) WHERE `ref`=?", array("s",$diff,"i",$resource_log_previous_ref));
        return $resource_log_previous_ref;
    } else {
        ps_query(
            "INSERT INTO `resource_log` (`date`, `user`, `resource`, `alt_ref`, `type`, `resource_type_field`, `notes`, `diff`, `usageoption`, `access_key`, `previous_value`) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
            'i', (($userref != "" && $type !== LOG_CODE_SYSTEM) ? $userref : null),
            'i', $resource,
            'i', $alt_ref > 0 ? $alt_ref : null,
            's', $type,
            'i', (($field == "" || !is_numeric($field)) ? null : $field),
            's', $notes,
            's', $diff,
            'i', $usage,
            's', ((isset($k) && !$internal_share_access) ? mb_strcut($k, 0, 50) : null),
            's', $fromvalue
            ]
        );
        $log_ref = sql_insert_id();
        $resource_log_previous_ref = $log_ref;
        return $log_ref;
    }
}

/**
 * Get resource log records. The standard field titles are translated using $lang. Custom field titles are i18n translated.
 *
 * @param  int    $resource    Resource ID - set to NULL and specify r.ref=>[id] in the $filters array to retrieve a specific log entry by log ref
 * @param  int    $fetchrows   If $fetchrows is set we don't have to loop through all the returned rows. @see ps_query()
 * @param  array  $filters     List of filters to include in the where clause. The key of the array is linked to the
 *                             available columns in the sql statement so they must match!
 *
 * @return array
 */
function get_resource_log($resource, $fetchrows = -1, array $filters = array())
{
    // Logs can sometimes contain confidential information and the user
    // looking at them must have admin permissions set
    if (!checkperm('v')) {
        return array();
    }

    // If there is no resource ID passed and the filters are also empty then
    // return empty array
    if (!$resource && empty($filters)) {
        return array();
    }

    $extrafields = hook('get_resource_log_extra_fields');
    $extrafields = is_a($extrafields, PreparedStatementQuery::class) ? $extrafields : new PreparedStatementQuery();

    // Create filter SQL
    $filterarr = array();
    $params = [];
    if (is_int_loose($resource)) {
        $filterarr[] = "r.resource= ?";
        $params = array_merge($params, ['i', $resource]);
    }
    foreach ($filters as $column => $filter_value) {
        $filterarr[] = trim($column) . "= ?";
        $params = array_merge($params, ['s', $filter_value]);
    }
    $sql_filter = "WHERE " . implode(" AND ", $filterarr);

    $sql_query = new PreparedStatementQuery(
        "SELECT r.ref,
                        r.resource,
                        r.alt_ref,
                        r.date,
                        u.username,
                        u.fullname,
                        r.type,
                        rtf.type AS resource_type_field,
                        rtf.ref AS field,
                        f.title,
                        r.notes,
                        r.diff,
                        r.usageoption,
                        r.previous_value,
                        r.access_key,
                        ekeys_u.fullname AS shared_by {$extrafields->sql}
                   FROM resource_log AS r
        LEFT OUTER JOIN user AS u ON u.ref = r.user
        LEFT OUTER JOIN resource_type_field AS f ON f.ref = r.resource_type_field
        LEFT OUTER JOIN external_access_keys AS ekeys ON r.access_key = ekeys.access_key AND r.resource = ekeys.resource
        LEFT OUTER JOIN user AS ekeys_u ON ekeys.user = ekeys_u.ref
        LEFT OUTER JOIN resource_type_field AS rtf ON r.resource_type_field = rtf.ref
                        {$sql_filter}
               GROUP BY r.ref
               ORDER BY r.ref DESC",
        array_merge($extrafields->parameters, $params)
    );

    $log = sql_limit_with_total_count($sql_query, $fetchrows, 0);

    for ($n = 0; $n < count($log['data']); $n++) {
        if ($fetchrows != -1 && $log['data'][$n] == 0) {
            continue;
        }

        $log['data'][$n]['title'] = lang_or_i18n_get_translated($log['data'][$n]['title'], 'fieldtitle-');
    }

    return $log;
}

function get_resource_type_name($type)
{
    global $lang;
    if ($type == 999) {
        return $lang["archive"];
    }
    return lang_or_i18n_get_translated(ps_value("SELECT name value FROM resource_type WHERE ref=?", array("i",$type), "schema"), "resourcetype-");
}

function get_resource_custom_access($resource)
{
    /*Return a list of usergroups with the custom access level for resource $resource (if set).
    The standard usergroup names are translated using $lang. Custom usergroup names are i18n translated.*/
    $sql = '';
    $params = [];
    if (checkperm('E')) {
        // Restrict to this group and children groups only.
        global $usergroup, $usergroupparent;
        $sql = "WHERE g.parent = ? OR g.ref = ? OR g.ref = ?";
        $params = ['i', $usergroup, 'i', $usergroup, 'i', $usergroupparent];
    }

    $resource_custom_access = ps_query("
                   SELECT g.ref,
                          g.name,
                          g.permissions,
                          c.access
                     FROM usergroup AS g
          LEFT OUTER JOIN resource_custom_access AS c ON g.ref = c.usergroup AND c.resource = ?
                     $sql
                 GROUP BY g.ref
                 ORDER BY (g.permissions LIKE '%v%') DESC, g.name
     ", array_merge(['i', $resource], $params));

    for ($n = 0; $n < count($resource_custom_access); $n++) {
        $resource_custom_access[$n]['name'] = lang_or_i18n_get_translated($resource_custom_access[$n]['name'], 'usergroup-');
    }

    return $resource_custom_access;
}

/**
 * Retrieves custom access settings for users and user groups associated with a specific resource.
 *
 * This function performs a database query to return a list of custom access rows for a given resource,
 * including the names of user groups and users, the access level, and expiration details.
 *
 * @param int $resource The resource reference ID for which to retrieve custom access settings.
 * @return array The result set containing user groups, users, access levels, and expiration information.
 */
function get_resource_custom_access_users_usergroups($resource)
{
    # Returns only matching custom_access rows, with users and groups expanded
    return ps_query("
                 SELECT g.name usergroup,
                        u.username user,
                        c.access,
                        c.user_expires AS expires
                   FROM resource_custom_access AS c
        LEFT OUTER JOIN usergroup AS g ON g.ref = c.usergroup
        LEFT OUTER JOIN user AS u ON u.ref = c.user
                  WHERE c.resource = ?
               ORDER BY g.name, u.username
    ", ['i', $resource]);
}

/**
 * Saves custom access settings for a specific resource.
 *
 * This function retrieves custom access groups associated with the given resource,
 * deletes existing custom access records for that resource, and then inserts new
 * access settings based on user input.
 *
 * @param int $resource The resource reference ID for which custom access is being set.
 * @return void
 */
function save_resource_custom_access($resource)
{
    $groups = get_resource_custom_access($resource);
    ps_query("delete from resource_custom_access where resource=? and usergroup is not null", array("i",$resource));
    for ($n = 0; $n < count($groups); $n++) {
        $usergroup = $groups[$n]["ref"];
        $access = getval("custom_" . $usergroup, 0);
        ps_query("insert into resource_custom_access(resource,usergroup,access) values (?,?,?)", array("i",$resource,"i",$usergroup,"i",$access));
    }
}

/**
 * Lookup custom access value for a resource
 *
 * @param  int     $resource         Resource ID.
 * @param  int     $usergroup        User group ID.
 * @param  bool    $return_default   Return default custom access value RESOURCE_ACCESS_CONFIDENTIAL
 *
 * @return mixed   False if custom access is disabled or there is no custom access value set for this resource.
 *                 Int representing custom access level if set; 0 - open, 1 - restricted, 2 - confidential.
 */
function get_custom_access($resource, $usergroup, $return_default = true)
{
    global $custom_access;

    if (!$custom_access) {
        return false;
    }

    $result = ps_value("select access value from resource_custom_access where resource = ? and usergroup = ?", array("i", $resource, "i", $usergroup), '');

    if ($result === '' && $return_default) {
        return RESOURCE_ACCESS_CONFIDENTIAL;
    } elseif ($result === '') {
        return false;
    }

    return $result;
}

/**
* Determine the featured collections and public collections a resource is associated with.
*
* @param integer $ref Resource ref
*
* @return array
*/
function get_themes_by_resource($ref)
{
    global $lang;

    $sql = "SELECT c.ref, c.`name`, c.`type`, u.fullname FROM collection_resource AS cr
            JOIN collection AS c ON cr.collection = c.ref AND cr.resource = ? AND c.`type` IN (?, ?)
            LEFT OUTER JOIN user AS u ON c.user = u.ref
            " . trim(featured_collections_permissions_filter_sql("WHERE", "c.ref", true)) . " # access control filter (ok if empty - it means we don't want permission checks or there's nothing to filter out)";

    $results = ps_query($sql, ['i', $ref, 'i', COLLECTION_TYPE_FEATURED, 'i', COLLECTION_TYPE_PUBLIC]);
    $branch_path_fct = function ($carry, $item) {
        return sprintf("%s / %s", $carry, strip_prefix_chars(i18n_get_translated($item["name"]), "*"));
    };

    foreach ($results as $i => $col) {
        $path = sprintf("%s: %s", $lang["public"], i18n_get_translated($col["name"]));

        if ($col["type"] == COLLECTION_TYPE_FEATURED) {
            $branch_path = get_featured_collection_category_branch_by_leaf($col["ref"], array());
            $branch_path_str = array_reduce($branch_path, $branch_path_fct, "");
            $path = mb_substr($branch_path_str, 2, mb_strlen($branch_path_str));
        }

        $results[$i]["path"] = trim($path);
    }

    // Order by resulting path
    usort($results, function ($a, $b) {
        return strnatcasecmp($a["path"], $b["path"]);
    });

    return $results;
}

function update_resource_type($ref, $type)
{
    global $lang;

    if (checkperm("XU" . $type)) {
        return false;
    }

    $old_rt = ps_value("SELECT resource_type `value` FROM resource WHERE ref = ?", ["i",$ref], '');
    ps_query("UPDATE resource SET resource_type = ? WHERE ref = ?", ["i",$type,"i",$ref]);

    # Clear data that is no longer needed (data/keywords set for other types).
    ps_query(
        "DELETE FROM resource_node
                    WHERE resource = ?
                          AND node>0 
                          AND node NOT IN 
                                (SELECT n.ref
                                   FROM node n
                              LEFT JOIN resource_type_field rf ON n.resource_type_field=rf.ref
                              LEFT JOIN resource_type_field_resource_type rtfrt ON rf.ref=rtfrt.resource_type_field
                                  WHERE (rtfrt.resource_type = ? OR rf.global=1)
                                )",
        ["i",$ref,"i",$type]
    );

    $old_rt_fields = ps_array(
        "SELECT column_name value 
           FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = 'resource' 
            AND column_name LIKE 'field%'
            AND column_name NOT IN
        (SELECT CONCAT('field', rf.ref)
           FROM resource_type_field rf
      LEFT JOIN resource_type_field_resource_type rtfrt ON rf.ref=rtfrt.resource_type_field
          WHERE (rtfrt.resource_type = ? OR rf.global=1)
                    )",
        ["i",$type]
    );

    foreach($old_rt_fields as $field) {
        ps_query("UPDATE resource SET $field = '' WHERE ref = ?",["i",$ref]);
    }

    if ($type != $old_rt) {
        $rts = get_resource_types("$type,$old_rt");
        $rts = array_column($rts, 'name', 'ref');
        resource_log($ref, '', null, $lang["log-rtchange"], $rts[$old_rt] ?? "", $rts[$type] ?? "");
    }
    return true;
}

/**
* Returns a list of exiftool fields, which are basically fields with an 'exiftool field' set.
*
* @param integer    $resource_type
* @param string     $option_separator   String to separate the node options returned for fixed list fields
*                                       - Recommended NODE_NAME_STRING_SEPARATOR
*                                       - Defaults to comma for backwards compatibility
* @param bool       $skip_translation   Set to true to return the entire untranslated node value rather than the appropriate translation only.
*
* @return array
*/
function get_exiftool_fields($resource_type, string $option_separator = ",", bool $skip_translation = false)
{
    global $FIXED_LIST_FIELD_TYPES;
    $return = ps_query("
           SELECT f.ref,
                  f.type,
                  f.exiftool_field,
                  f.exiftool_filter,
                  f.name,
                  f.read_only
             FROM resource_type_field AS f
        LEFT JOIN resource_type_field_resource_type rtfrt ON f.ref=rtfrt.resource_type_field
            WHERE length(exiftool_field) > 0
              AND (rtfrt.resource_type = ? OR f.global=1)
         GROUP BY ref
         ORDER BY exiftool_field", array("i",$resource_type), "schema");

    // Add options for fixed list fields
    foreach ($return as &$field) {
        if (in_array($field["type"], $FIXED_LIST_FIELD_TYPES)) {
            $options = get_field_options($field["ref"], false, $skip_translation);
            $field["options"] = implode($option_separator, $options);
        } else {
            $field["options"] = "";
        }
    }
    return $return;
}

/**
* Create a temporary copy of the file in the tmp folder (ie. the usual filestore/tmp/)
*
* @uses get_temp_dir()
*
* @param  string  $path      File path
* @param  string  $uniqid    If a uniqid is provided, create a folder within tmp. See get_temp_dir() for more information.
* @param  string  $filename  Filename of the new file
*
* @return boolean|string  Returns FALSE or the file path of the temporary file
*/
function createTempFile($path, $uniqid, $filename)
{
    if (
        !file_exists($path)
        || !is_readable($path)
        || !is_valid_rs_path($path)
    ) {
        return false;
    }

    $tmp_dir = get_temp_dir(false, $uniqid);

    if (trim($filename) == '') {
        $file_path_info = pathinfo($path);
        $filename = md5(mt_rand()) . "_{$file_path_info['basename']}";
    }

    $tmpfile = "{$tmp_dir}/{$filename}";

    $copy_hook = hook('createtempfile_copy', '', array($path, $tmpfile));
    if (!$copy_hook) {
        copy($path, $tmpfile);
    } else {
        $tmpfile = $copy_hook;
    }

    return $tmpfile;
}

/**
* Strips metadata from file
*
* @uses get_utility_path()
* @uses run_command()
*
* @param string  $file_path  Physical path to file that will have metadata stripped. Use NULL to just get the exiftool
*                            command returned instead of running the command on the file
*
* @return boolean|string  Returns TRUE or the Exiftool command for stripping metadata
*/
function stripMetadata($file_path)
{
    debug_function_call('stripMetadata', func_get_args());
    $exiftool_fullpath = get_utility_path('exiftool');

    if ($exiftool_fullpath === false) {
        trigger_error('stripMetadata function requires Exiftool utility!');
    }

    $command = "{$exiftool_fullpath} -m -overwrite_original -E -gps:all= -EXIF:all= -XMP:all= -IPTC:all=";

    if (is_null($file_path)) {
        return $command;
    }

    if (!file_exists($file_path) || !is_writable($file_path)) {
        return false;
    }

    $file_path = escapeshellarg($file_path);
    run_command("{$command} {$file_path}");

    return true;
}

/**
 * Writes metadata to a specified file using ExifTool.
 *
 * @param string $path The file path where the metadata will be written.
 * @param int $ref The reference ID of the resource associated with the file.
 * @param string $uniqid A unique identifier for the temporary file location (optional).
 * @return string|bool The path to the temporary file if successful, false otherwise.
 */
function write_metadata($path, $ref, $uniqid = "")
{
    debug_function_call('write_metadata', func_get_args());

    if (!is_valid_rs_path($path)) {
        // Not a valid path to a ResourceSpace file source
        return false;
    }

    // copys the file to tmp and runs exiftool on it
    // uniqid tells the tmp file to be placed in an isolated folder within tmp
    global $exiftool_remove_existing, $storagedir, $exiftool_write, $exiftool_write_option, $exiftool_no_process, $mysql_charset, $exiftool_write_omit_utf8_conversion;

    # Fetch file extension and resource type.
    $resource_data = get_resource_data($ref);
    $extension = $resource_data["file_extension"];

    $exiftool_fullpath = get_utility_path("exiftool");

    # Check if an attempt to write the metadata shall be performed.
    if (false != $exiftool_fullpath && $exiftool_write && $exiftool_write_option && !in_array($extension, $exiftool_no_process)) {
        debug("[write_metadata()][ref={$ref}] Attempting to write metadata...");
        // Trust Exiftool's list of writable formats
        $writable_formats = run_command("{$exiftool_fullpath} -listwf");
        $writable_formats = str_replace("\n", "", $writable_formats);
        $writable_formats_array = explode(" ", $writable_formats);
        if (!in_array(strtoupper((string) $extension), $writable_formats_array)) {
            debug("[write_metadata()][ref={$ref}] Extension '{$extension}' not in writable_formats_array - " . json_encode($writable_formats_array));
            return false;
        }

        $tmpfile = createTempFile($path, $uniqid, '');
        if ($tmpfile === false) {
            debug("[write_metadata()][ref={$ref}] Unable to create temp file!");
            return false;
        }

        # Add the call to exiftool and some generic arguments to the command string.
        # Argument -overwrite_original: Now that we have already copied the original file, we can use exiftool's overwrite_original on the tmpfile.
        # Argument -E: Escape values for HTML. Used for handling foreign characters in shells not using UTF-8.
        # Arguments -EXIF:all= -XMP:all= -IPTC:all=: Remove the metadata in the tag groups EXIF, XMP and IPTC.
        $command = $exiftool_fullpath . " -m -overwrite_original -E ";
        if ($exiftool_remove_existing) {
            $command = stripMetadata(null) . ' ';
            debug("[write_metadata()][ref={$ref}] Removing existing metadata. Command: " . json_encode($command));
        }

        $metadata_all = get_resource_field_data($ref, false, true, null, getval("k", "") != ""); // Using get_resource_field_data means we honour field permissions
        $read_only_fields = array_column(array_filter($metadata_all, function ($value) {
            return (bool) $value['read_only'] === true;
        }), 'ref');

        $write_to = array();
        foreach ($metadata_all as $metadata_item) {
            if (trim($metadata_item["exiftool_field"] ?? "") != "" && !in_array($metadata_item['ref'], $read_only_fields)) {
                $write_to[] = $metadata_item;
            }
        }

        $writtenfields = array(); // Need to check if we are writing to an embedded field from more than one RS field, in which case subsequent values need to be appended, not replaced

        for ($i = 0; $i < count($write_to); $i++) { # Loop through all the found fields.
            $fieldtype = $write_to[$i]['type'];
            $writevalue = $write_to[$i]['value'] ?? "";
            # Formatting and cleaning of the value to be written - depending on the RS field type.
            switch ($fieldtype) {
                case 2:
                case 3:
                case 9:
                case 12:
                    # Check box list, drop down, radio buttons or dynamic keyword list: remove initial comma if present
                    $writevalue = strip_leading_comma($writevalue);
                    break;
                case 4:
                case 6:
                case 10:
                    # Date / Expiry Date: write datetype fields in exiftool preferred format
                    if ($writevalue != '') {
                        $writevalue_to_time = strtotime($writevalue);
                        if ($writevalue_to_time != '') {
                            $writevalue = date("Y:m:d H:i:sP", strtotime($writevalue));
                        }
                    }
                    break;
                    # Other types, already set
            }
            $filtervalue = hook("additionalmetadatafilter", "", [$write_to[$i]["exiftool_field"], $writevalue]);
            if ($filtervalue) {
                $writevalue = $filtervalue;
            }
            # Add the tag name(s) and the value to the command string.
            $group_tags = explode(",", $write_to[$i]['exiftool_field']); # Each 'exiftool field' may contain more than one tag.
            foreach ($group_tags as $group_tag) {
                $group_tag = strtolower($group_tag); # E.g. IPTC:Keywords -> iptc:keywords
                if (strpos($group_tag, ":") === false) {
                    $tag = $group_tag;
                } # E.g. subject -> subject
                else {
                    $tag = substr($group_tag, strpos($group_tag, ":") + 1);
                } # E.g. iptc:keywords -> keywords
                if (strpos($group_tag, "-") !== false && stripos($group_tag, "xmp") !== false) {
                    // Remove the XMP namespace for XMP data if included
                    $group_tag = substr($group_tag, 0, (strpos($group_tag, "-")));
                }
                $exifappend = false; // Need to replace values by default
                if (isset($writtenfields[$group_tag])) {
                    // This embedded field is already being updated, we need to append values from this field
                    $exifappend = true;
                    debug("write_metadata - more than one field mapped to the tag '" . $group_tag . "'. Enabling append mode for this tag. ");
                }

                switch ($tag) {
                    case "filesize":
                        # Do nothing, no point to try to write the filesize.
                        break;
                    case "filename":
                        # Do nothing, no point to try to write the filename either as ResourceSpace controls this.
                        break;
                    case "directory":
                        # Do nothing, we don't want metadata to control this
                        break;
                    case "keywords":
                        if (substr($group_tag, 0, 3) != "xmp") {
                            # Only IPTC Keywords are a list type - these are written one at a time and not all together.
                            if (!isset($writtenfields["keywords"])) {
                                $writtenfields["keywords"] = "";
                            }
                            $keywords = explode(",", $writevalue); # "keyword1,keyword2, keyword3" (with or without spaces)
                            if (implode("", $keywords) != "") {
                                # Only write non-empty keywords/ may be more than one field mapped to keywords so we don't want to overwrite with blank
                                foreach ($keywords as $keyword) {
                                    $keyword = trim($keyword);
                                    if ($keyword != "") {
                                        debug("[write_metadata()][ref={$ref}] Writing keyword '{$keyword}'");
                                        $writtenfields["keywords"] .= "," . $keyword;
                                        # Convert the data to UTF-8 if not already.
                                        if (!$exiftool_write_omit_utf8_conversion && (!isset($mysql_charset) || (isset($mysql_charset) && strtolower($mysql_charset) != "utf8"))) {
                                            $keyword = mb_convert_encoding($keyword, mb_detect_encoding($keyword), 'UTF-8');
                                        }
                                        $command .= escapeshellarg("-" . $group_tag . "-=" . htmlentities($keyword, ENT_QUOTES, "UTF-8")) . " "; // In case value is already embedded, need to manually remove it to prevent duplication
                                        $command .= escapeshellarg("-" . $group_tag . "+=" . htmlentities($keyword, ENT_QUOTES, "UTF-8")) . " ";
                                    }
                                }
                            }
                            break; // The break is in here so that Non-IPTC keywords continue to be handled by default
                        }
                    default:
                        if ($exifappend && ($writevalue == "" || ($writevalue != "" && strpos($writtenfields[$group_tag], $writevalue) !== false))) {
                            // The new value is blank or already included in what is being written, skip to next group tag
                            continue 2; # @see https://www.php.net/manual/en/control-structures.continue.php note
                        }
                        $writtenfields[$group_tag] = $writevalue;
                        debug("[write_metadata()][ref={$ref}] Updating tag '{$group_tag}' with value '{$writevalue}'");
                        # Write as is, convert the data to UTF-8 if not already.

                        global $strip_rich_field_tags;
                        if (
                            !$exiftool_write_omit_utf8_conversion
                            && (!isset($mysql_charset)
                                || (isset($mysql_charset) && strtolower($mysql_charset) != "utf8"))
                        ) {
                            $writevalue = mb_convert_encoding($writevalue, mb_detect_encoding($writevalue), 'UTF-8');
                        }
                        if ($strip_rich_field_tags) {
                            $command .= escapeshellarg("-" . $group_tag . "=" . trim(strip_tags(i18n_get_translated($writevalue)))) . " ";
                        } else {
                            $command .= escapeshellarg("-" . $group_tag . "=" . htmlentities(i18n_get_translated($writevalue), ENT_QUOTES, "UTF-8")) . " ";
                        }
                }
            }
        }

            # Add the filename to the command string.
            $command .= " " . escapeshellarg($tmpfile);

            # Perform the actual writing - execute the command string.
            run_command($command);
        return $tmpfile;
    } else {
        debug("[write_metadata()][ref={$ref}] Did not perform - write metadata!");
        return false;
    }
}

/**
* Delete Exif temp file
*
* @param  string  $tmpfile   Exif temp file to be deleted
*
*/
function delete_exif_tmpfile($tmpfile)
{
    $tempdir = get_temp_dir();
    if (
        file_exists($tmpfile)
        && strpos($tmpfile, realpath($tempdir)) === 0 
    ) {
        unlink($tmpfile);
    } else {
        debug("WARNING - Attempt to delete a file not in the configured temporary directory: {$tmpfile}");
    }
}

/**
 * Updates a resource with a new file and its associated metadata.
 *
 * @param int $r The resource ID to update.
 * @param string $path The file path of the new resource.
 * @param string $type The type of the resource.
 * @param string $title The title for the resource.
 * @param bool $ingest Indicates if the resource is being ingested.
 * @param bool $createPreviews Flag to create previews.
 * @param string $extension The file extension of the new resource.
 * @param bool $after_upload_processing Indicates if the update is after upload processing.
 * @return int|bool The resource ID if successful, false otherwise.
 */
function update_resource($r, $path, $type, $title, $ingest = false, $createPreviews = true, $extension = '', $after_upload_processing = false)
{
    global $syncdir, $staticsync_prefer_embedded_title, $view_title_field, $filename_field, $upload_then_process, $offline_job_queue, $lang,
        $extracted_text_field, $offline_job_queue, $offline_job_in_progress, $autorotate_ingest, $enable_thumbnail_creation_on_upload,
        $userref, $lang, $upload_then_process_holding_state,$unoconv_extensions;

    if ($upload_then_process && !$offline_job_queue) {
        $upload_then_process = false;
    }

    # FStemplate support - do not allow samples from the template to be replaced
    if (resource_file_readonly($r)) {
        return false;
    }

    # Work out extension based on path
    if ($extension == '') {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
    }

    if ($extension !== '') {
        $extension = trim(strtolower($extension));
    }

    $original_file_path = get_resource_path($r, true, "", true, $extension);
    if (!$upload_then_process || !$after_upload_processing) {
        update_resource_type($r, $type);

        # file_path should only really be set to indicate a staticsync location. Otherwise, it should just be left blank.
        if ($ingest) {
            $file_path = "";
        } else {
            $file_path = $path;
        }

        # Store extension/data in the database
        ps_query("UPDATE resource SET archive=0,file_path=?,file_extension=?,preview_extension=?,file_modified=NOW(),no_file=0 WHERE ref=?", array("s",$file_path,"s",$extension,"s",$extension,"i",$r));
        unset($GLOBALS['get_resource_data_cache'][$r]);

        # Store original filename in field, if set
        if (!$ingest) {
            # This file remains in situ; store the full path in file_path to indicate that the file is stored remotely.
            if (isset($filename_field)) {
                $s = explode("/", $path);
                $filename = end($s);

                update_field($r, $filename_field, $filename);
            }
        } else {
            # This file is being ingested. Store only the filename.
            $s = explode("/", $path);
            $filename = end($s);

            if (isset($filename_field)) {
                update_field($r, $filename_field, $filename);
            }

            # Move the file
            if (!hook('update_resource_replace_ingest', '', array($r, $path, $extension))) {
                $source_file = $syncdir . "/" . $path;
                $process_file_upload = process_file_upload(
                    new SplFileInfo($source_file),
                    new SplFileInfo($original_file_path),
                    // Copy instead of rename so that permissions of filestore will be used
                    ['file_move' => 'copy']
                );
                if (!$process_file_upload['success']) {
                    # The copy failed. The file is possibly still being copied or uploaded and must be ignored on this pass.
                    # If the file is still being copied then $staticsync_file_minimum_age can be set to prevent this error from occurring
                    # Delete the resouce just created and return false.
                    debug("ERROR: Staticsync failed ({$process_file_upload['error']->name}) to copy file from: {$source_file}");
                    delete_resource($r);
                    return false;
                }
                $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
                $GLOBALS["use_error_exception"] = true;
                try {
                    unlink($source_file);
                    try {
                        chmod($original_file_path, 0777);
                    } catch (Exception $e) {
                        // Not fatal, just log
                        debug(" - ERROR: Staticsync failed to set permissions on ingested file: " .  $original_file_path . PHP_EOL . " - Error message: " . $e->getMessage() . PHP_EOL);
                    }
                } catch (Exception $e) {
                    echo " - ERROR: failed to delete file from source. Please check correct permissions on: " .  $source_file . PHP_EOL . " - Error message: "  . $e->getMessage() . PHP_EOL;
                    return false;
                }

                $GLOBALS["use_error_exception"] = $use_error_exception_cache;
            }
        }
    }

    if (!$upload_then_process || $after_upload_processing) {
        # generate title and extract embedded metadata
        # order depends on which title should be the default (embedded or generated)
        if ($staticsync_prefer_embedded_title) {
            if ($view_title_field !== $filename_field) {
                update_field($r, $view_title_field, $title);
            }
            extract_exif_comment($r, $extension);
        } else {
            extract_exif_comment($r, $extension);
            if ($view_title_field !== $filename_field) {
                update_field($r, $view_title_field, $title);
            }
        }

        # Extract text from documents (e.g. PDF, DOC)
        if (
            isset($extracted_text_field)
            && !(isset($unoconv_path) && in_array($extension, $unoconv_extensions))
        ) {
            if ($offline_job_queue && !$offline_job_in_progress) {
                $extract_text_job_data = array(
                    'ref'       => $r,
                    'extension' => $extension,
                );

                job_queue_add('extract_text', $extract_text_job_data);
            } else {
                extract_text($r, $extension);
            }
        }

        # Ensure folder is created, then create previews.
        get_resource_path($r, false, "pre", true, $extension);

        if ($createPreviews) {
            # Attempt autorotation
            if ($ingest && $autorotate_ingest) {
                AutoRotateImage($original_file_path);
            }

            # Generate previews/thumbnails (if configured i.e if not completed by offline process 'create_previews.php')
            if ($enable_thumbnail_creation_on_upload) {
                create_previews($r, false, $extension, false, false, -1, false, $ingest);
            } elseif (!$enable_thumbnail_creation_on_upload && $offline_job_queue) {
                $create_previews_job_data = array(
                    'resource' => $r,
                    'thumbonly' => false,
                    'extension' => $extension,
                    'previewonly' => false,
                    'previewbased' => false,
                    'alternative' => -1,
                    'ignoremaxsize' => false,
                    'ingested' => $ingest
                );
                $create_previews_job_failure_text = str_replace('%RESOURCE', $r, $lang['jq_create_previews_failure_text']);

                job_queue_add('create_previews', $create_previews_job_data, '', '', '', $create_previews_job_failure_text);
            }
        }
    }

    if ($upload_then_process && !$after_upload_processing) {
        # Add this to the job queue for offline processing
        $job_data = array();
        $job_data["r"] = $r;
        $job_data["title"] = $title;
        $job_data["ingest"] = $ingest;
        $job_data["createPreviews"] = $createPreviews;

        if (isset($upload_then_process_holding_state)) {
            $job_data["archive"] = ps_value("SELECT archive value from resource where ref=?", array("i",$r), "");
            update_archive_status($r, $upload_then_process_holding_state);
        }

        $job_code = $r . md5($job_data["r"] . strtotime('now'));
        $job_failure_lang = "update_resource fail " . ": " . str_replace(array('%ref', '%title'), array($r, $filename), $lang["ref-title"]);
        job_queue_add("update_resource", $job_data, $userref, '', '', $job_failure_lang, $job_code);
    }

    hook('after_update_resource', '', array("resourceId" => $r ));
    # Pass back the newly created resource ID.
    return $r;
}

/**
 * Imports a resource from the specified path and updates its details.
 *
 * This function is used to import resources into the system, particularly through staticsync.php
 * It creates a new resource entry and updates it with the provided file details.
 *
 * @param string $path The file path of the resource to import.
 * @param string $type The type of the resource being imported.
 * @param string $title The title for the imported resource.
 * @param bool $ingest Indicates if the resource is being ingested (default is false).
 * @param string $extension The file extension of the resource (optional).
 * @return int|bool The newly created resource ID if successful, false otherwise.
 */
function import_resource($path, $type, $title, $ingest = false, $extension = '')
{
    global $syncdir,$lang;

    $r = create_resource($type);
    if ($r === false) {
        return false;
    }
    debug("Created resource #{$r}");

    // Log this in case the original location is not stored anywhere else
    resource_log(
        RESOURCE_LOG_APPEND_PREVIOUS,
        LOG_CODE_CREATED,
        '',
        $lang["createdfromstaticsync"],
        '',
        $syncdir . DIRECTORY_SEPARATOR . $path
    );
    return update_resource($r, $path, $type, $title, $ingest, true, $extension);
}

/**
 * Retrieves alternative files associated with a specified resource.
 *
 * @param int $resource The resource ID for which to fetch alternative files.
 * @param string $order_by The field to order the results by (optional).
 * @param string $sort The sorting direction (ASC or DESC) for the order_by field (optional).
 * @param string $type The type of alternative file to filter by (optional).
 * @return array|bool An array of alternative files if successful, false otherwise.
 */
function get_alternative_files($resource, $order_by = "", $sort = "", $type = "")
{
    # Returns a list of alternative files for the given resource
    if (
        $order_by != ""
        && $sort != ""
        && in_array(strtoupper($order_by), array("ALT_TYPE"))
        && validate_sort_value($sort)
    ) {
        $ordersort = $order_by . " " . $sort . ",";
    } else {
        $ordersort = "";
    }

    # The following hook now returns a query object
    $extrasql = hook("get_alternative_files_extra_sql", "", array($resource));

    if (!$extrasql) {
        # Hook inactive, ensure we have an empty query object
        $extrasql = new PreparedStatementQuery();
    }

    # Filter by type, if provided.
    if ($type != "") {
        $extrasql->sql .= " AND alt_type=?";
        $extrasql->parameters = array_merge($extrasql->parameters, array("s",$type));
    }

    $alt_files_sql = "SELECT ref,name,description,file_name,file_extension,file_size,creation_date,alt_type
                    FROM resource_alt_files where resource=? " . $extrasql->sql .
                   " order by " . $ordersort . " name asc, file_size desc";

    $alt_files_parameters = array_merge(array("i",$resource), $extrasql->parameters);

    return ps_query($alt_files_sql, $alt_files_parameters);
}

/**
* Add alternative file
*
* @param integer $resource
* @param string  $name
* @param string  $description
* @param string  $file_name
* @param string  $file_extension
* @param integer $file_size
* @param string  $alt_type
*
* @return integer
*/
function add_alternative_file($resource, $name, $description = "", $file_name = "", $file_extension = "", $file_size = 0, $alt_type = '')
{
    debug_function_call("add_alternative_file", func_get_args());

    $name = trim_filename($name);
    $file_name = trim_filename($file_name);
    ps_query(
        "INSERT INTO resource_alt_files (resource, name, creation_date, description, file_name, file_extension, file_size, alt_type)
              VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)",
        [
        'i', $resource,
        's', $name,
        's', $description,
        's', $file_name,
        's', $file_extension,
        'i', $file_size,
        's', $alt_type,
        ]
    );
    return sql_insert_id();
}

/**
 * delete_alternative_file
 *
 * @param   int   $resource   Resource id.
 * @param   int   $ref        Alternative file id.
 */
function delete_alternative_file($resource, $ref): bool
{
    # Delete any uploaded file.
    $info = get_alternative_file($resource, $ref);
    if (!$info) {
        return false;
    }

    $path = get_resource_path($resource, true, "", true, $info["file_extension"], -1, 1, false, "", $ref);
    hook('delete_alternative_file_extra', '', array($path));
    if (file_exists($path)) {
        unlink($path);
    }

        // run through all possible extensions/sizes
    $extensions = array();
    $extensions[] = $info['file_extension'] ? $info['file_extension'] : "jpg";
    $extensions[] = isset($info['preview_extension']) ? $info['preview_extension'] : "jpg";
    $extensions[] = $GLOBALS['ffmpeg_preview_extension'];
        $extensions[] = 'jpg'; // always look for jpegs, just in case
    $extensions[] = 'icc'; // always look for extracted icc profiles
    $extensions = array_unique($extensions);
        $sizes = ps_array('SELECT id value FROM preview_size', array(), "schema");

        // in some cases, a jpeg original is generated for non-jpeg files like PDFs. Delete if it exists.
        $path = get_resource_path($resource, true, '', true, 'jpg', -1, 1, false, "", $ref);
    if (file_exists($path)) {
        unlink($path);
    }

        hook('delete_alternative_jpg_extra', '', array($path));

        // in some cases, a mp3 original is generated for non-mp3 files like WAVs. Delete if it exists.
        $path = get_resource_path($resource, true, '', true, 'mp3', -1, 1, false, "", $ref);
    if (file_exists($path)) {
        unlink($path);
    }

    foreach ($extensions as $extension) {
        foreach ($sizes as $size) {
            $page = 1;
            $lastpage = 0;
            while ($page <> $lastpage) {
                $lastpage = $page;
                $path = get_resource_path($resource, true, $size, true, $extension, -1, $page, false, "", $ref);
                hook('delete_alternative_file_loop', '', array($path));
                if (file_exists($path)) {
                    unlink($path);
                    $page++;
                }
            }
        }
    }
        hook('delete_alternative_mp3_extra', '', array($path));

    # Delete the database row
    ps_query("delete from resource_alt_files where resource=? and ref=?", array("i",$resource,"i",$ref));

    # Log the deletion
    resource_log($resource, LOG_CODE_DELETED_ALTERNATIVE, '');

    # Update disk usage
    update_disk_usage($resource);
    clear_query_cache("stats");

    return true;
}

/**
 * Retrieves a specific alternative file associated with a given resource.
 *
 * @param int $resource The resource ID associated with the alternative file.
 * @param int $ref The reference ID of the alternative file to retrieve.
 * @return array|bool An associative array containing the alternative file details if found, false otherwise.
 */
function get_alternative_file($resource, $ref)
{
    # Returns the row for the requested alternative file
    $return = ps_query("select ref,name,description,file_name,file_extension,file_size,creation_date,alt_type from resource_alt_files where resource=? and ref=?", array("i",$resource,"i",$ref));
    if (count($return) == 0) {
        return false;
    } else {
        return $return[0];
    }
}

/**
 * Updates the details of an alternative file in the database.
 *
 * @param int $resource The resource ID associated with the alternative file.
 * @param int $ref      The reference ID of the alternative file to update.
 * @param array $data   Array of data in name=>value format, where name is the resource_alt_files column name.
 *                      If a column is to be left as is then it must not be included in the array
 *
 */
function save_alternative_file(int $resource, int $ref, array $data = []): void
{
    $validcolumns = [
        "name" => "s",
        "description" => "s",
        "alt_type" => "s",
        "file_name" => "s",
        "file_extension" => "s",
        "file_size" => "i",
        "unoconv" => "i",
    ];
    $setcolumns = [];
    $setparams = [];
    foreach ($validcolumns as $column => $type) {
        if (isset($data[$column])) {
            $setcolumns[] = $column;
            $setparams[] = $type;
            $setparams[] = $data[$column];
        }
    }
    if (count($setcolumns) > 0) {
        $setcolumnsql = implode(" = ?, ", $setcolumns) . " = ?";
        $setparams = array_merge($setparams, ["i", $resource, "i", $ref]);
        ps_query(
            "UPDATE resource_alt_files
                     SET $setcolumnsql
                   WHERE resource = ? AND ref = ?",
            $setparams
        );
    }
}

/**
 * Saves a user rating for a given resource.
 *
 * @param int $userref The reference ID of the user rating the resource.
 * @param int $ref The reference ID of the resource being rated.
 * @param int $rating The rating value to be saved (0 to remove rating).
 * @return void
 */
function user_rating_save($userref, $ref, $rating)
{
    # Save a user rating for a given resource
    $resource = get_resource_data($ref);

    # Recalculate the averate rating
    $total = $resource["user_rating_total"];
    if ($total == "") {
        $total = 0;
    }
    $count = $resource["user_rating_count"];
    if ($count == "") {
        $count = 0;
    }

    # modify behavior to allow only one current rating per user (which can be re-edited)
    global $user_rating_only_once;
    if ($user_rating_only_once) {
        $ratings = ps_query("select user,rating from user_rating where ref=?", array("i",$ref));

        #Calculate ratings total and get current rating for user if available
        $total = 0;
        $current = "";
        for ($n = 0; $n < count($ratings); $n++) {
            $total += $ratings[$n]['rating'];

            if ($ratings[$n]['user'] == $userref) {
                $current = $ratings[$n]['rating'];
            }
        }
        # Calculate Count
        $count = count($ratings);

        # if user has a current rating, subtract the old rating and add the new one.
        if ($current != "") {
            $total = $total - $current + $rating;
            if ($rating == 0) {  //rating remove feature
                ps_query("delete from user_rating where user=? and ref=?", array("i",$userref,"i",$ref));
                $count--;
            } else {
                ps_query("update user_rating set rating=? where user=? and ref=?", array("i",$rating,"i",$userref,"i",$ref));
            }
        }

        # if user does not have a current rating, add it
        else {
            if ($rating != 0) { // rating remove feature
                $total = $total + $rating;
                $count++;
                ps_query("insert into user_rating (user,ref,rating) values (?,?,?)", array("i",$userref,"i",$ref,"i",$rating));
            }
        }
    } else {
        # If not using $user_rating_only_once, Increment the total and count
        $total += $rating;
        $count++;
    }

    if ($count == 0) {
        # avoid division by zero
        $average = $total;
    } else {
    # work out a new average.
        $average = ceil($total / $count);
    }

    # Save to the database
    ps_query("UPDATE resource SET user_rating = ?, user_rating_total = ?, user_rating_count = ? WHERE ref = ?", array("d", $average, "i", $total, "i", $count, "i", $ref));
}

/**
*  A standard field title is translated using $lang.  A custom field title is i18n translated.
*
* @param integer $field Resource type field ID
*
* @return boolean|array Returns FALSE or record data (array)
*/
function get_field($field)
{
    $r = ps_query("
        SELECT " . columns_in("resource_type_field") . "
          FROM resource_type_field
         WHERE ref = ?
     ", ['i', $field], "schema");

    # Translates the field title if the searched field is found.
    if (0 == count($r)) {
        return false;
    } else {
        $r[0]["title"] = lang_or_i18n_get_translated($r[0]["title"], "fieldtitle-");
        return $r[0];
    }
}

/**
 * Retrieves the keyword that will be indexed for a given field option.
 *
 * This function splits the provided option into keywords and applies stemming if enabled.
 *
 * @param string $option The field option to process.
 * @return string The keyword to be indexed.
 */
function get_keyword_from_option($option)
{
    # For the given field option, return the keyword that will be indexed.
    $keywords = split_keywords("," . $option);

    global $stemming;
    if ($stemming && function_exists('GetStem')) {
        $keywords[1] = GetStem($keywords[1]);
    }

    return $keywords[1];
}

/**
 * Retrieves the access level for the currently logged-in user for a specified resource.
 *
 * The access levels returned are:
 * - 0 = Full Access (download all sizes)
 * - 1 = Restricted Access (download only those sizes that are set to allow restricted downloads)
 * - 2 = Confidential (no access)
 *
 * @param int|array $resource The reference ID of the resource or a resource data array.
 * @return int The access level for the resource.
 */
function get_resource_access($resource)
{
    global $customgroupaccess,$customuseraccess, $internal_share_access, $k,$uploader_view_override, $userref, $open_access_for_contributor,
        $userref,$usergroup, $usersearchfilter, $search_all_workflow_states,
        $userderestrictfilter, $userdata, $custom_access, $resource_access_cache;

    $passthru = "no";

    // get_resource_data doesn't contain permissions, so fix for the case that such an array could be passed into this function unintentionally.
    if (is_array($resource) && !isset($resource['group_access']) && !isset($resource['user_access'])) {
        $resource = $resource['ref'];
    }

    if (!is_array($resource)) {
        $resourcedata = get_resource_data($resource, true);
        if (!$resourcedata) {
            return RESOURCE_ACCESS_INVALID_REQUEST;
        }
    } else {
        $resourcedata = $resource;
        $passthru = "yes";
    }

    $ref = $resourcedata['ref'];
    $access = $resourcedata["access"];
    $resource_type = $resourcedata['resource_type'];

    if (isset($resource_access_cache[$ref])) {
        return $resource_access_cache[$ref];
    }

    // Set a couple of flags now that we can check later on if we need to check whether sharing is permitted based on whether access has been specifically granted to user/group
    $customgroupaccess = false;
    $customuseraccess = false;

    if ('' != $k) {
        # External access - check how this was shared.
        $extaccess = ps_value("SELECT access `value` FROM external_access_keys WHERE resource = ? AND access_key = ? AND (expires IS NULL OR expires > NOW())", array("i",$ref,"s",$k), -1);

        if (-1 != $extaccess && (!$internal_share_access || ($internal_share_access && $extaccess < $access))) {
            $resource_access_cache[$ref] = (int) $extaccess;
            return (int) $extaccess;
        }
    }

    if (checkperm("z" . $resourcedata['archive']) && !($uploader_view_override && $resourcedata['created_by'] == $userref)) {
        // User has no access to this archive state
        $resource_access_cache[$ref] = RESOURCE_ACCESS_CONFIDENTIAL;
        return RESOURCE_ACCESS_CONFIDENTIAL;
    }

    $usersearchfilter_isvalid = false;
    $usersearchfilter_isset = trim($usersearchfilter ?? "") != "";
    if ($usersearchfilter_isset) {
        # A search filter has been set. Perform filter processing to establish if the user can view this resource.
        # Apply filters by searching for the resource, utilising the existing filter matching in do_search to avoid duplication of logic.
        $search_all_workflow_states_cache = $search_all_workflow_states;
        $search_all_workflow_states = true;
        $results = do_search("!resource" . $ref);
        $search_all_workflow_states = $search_all_workflow_states_cache;
        if (count($results) > 0) {
            $usersearchfilter_isvalid = true;
        }
    }

    if (checkperm("v") && (!$usersearchfilter_isset || $usersearchfilter_isvalid)) {
        # Permission to access all resources
        # Always return 0
        $resource_access_cache[$ref] = RESOURCE_ACCESS_FULL;
        return RESOURCE_ACCESS_FULL;
    }

    if ($access == RESOURCE_ACCESS_CUSTOM_GROUP) {
        $customgroupaccess = true;
        # Load custom access level
        if ($passthru == "no") {
            $access = get_custom_access($resource, $usergroup);
            if ($access === false) {
                # Custom access disabled? Always return 'open' access for resources marked as custom.
                $access = RESOURCE_ACCESS_FULL;
                $customgroupaccess = false;
            }
        } else {
            if ($custom_access) {
                $access = $resource['group_access'];
            } else {
                # Custom access disabled? Always return 'open' access for resources marked as custom.
                $access = RESOURCE_ACCESS_FULL;
                $customgroupaccess = false;
            }
        }
    }

    if ($access == RESOURCE_ACCESS_RESTRICTED && get_edit_access($ref, $resourcedata['archive'], $resourcedata)) {
        # If access is restricted and user has edit access, grant open access.
        $access = RESOURCE_ACCESS_FULL;
    }

    if ($open_access_for_contributor && $resourcedata['created_by'] == $userref) {
        # If user has contributed resource, grant open access and ignore any further filters.
        $resource_access_cache[$ref] = RESOURCE_ACCESS_FULL;
        return RESOURCE_ACCESS_FULL;
    }

    # Check for user-specific and group-specific access (overrides any other restriction)

    // We need to check for custom access either when access is set to be custom or
    // when the user group has restricted access to all resource types or specific resource types
    // are restricted
    if ($access != RESOURCE_ACCESS_FULL || !checkperm('g') || checkperm('X' . $resource_type) || checkperm("rws{$resourcedata['archive']}")) {
        if ($passthru == "no") {
            $userspecific = get_custom_access_user($resource, $userref);
            $groupspecific = get_custom_access($resource, $usergroup, false);
        } else {
            $userspecific = $resourcedata['user_access'];
            $groupspecific = $resourcedata['group_access'];
        }
    }
    if (isset($userspecific) && $userspecific !== false) {
        $customuseraccess = true;
        $resource_access_cache[$ref] = (int) $userspecific;
        return (int) $userspecific;
    }
    if (isset($groupspecific) && $groupspecific !== false) {
        $customgroupaccess = true;
        $resource_access_cache[$ref] = (int) $groupspecific;
        return (int) $groupspecific;
    }

    if (checkperm('T' . $resource_type)) {
        // this resource type is always confidential/hidden for this user group
        $resource_access_cache[$ref] = RESOURCE_ACCESS_CONFIDENTIAL;
        return RESOURCE_ACCESS_CONFIDENTIAL;
    }

    if ($usersearchfilter_isset && !$usersearchfilter_isvalid) {
        $resource_access_cache[$ref] = RESOURCE_ACCESS_CONFIDENTIAL;
        return RESOURCE_ACCESS_CONFIDENTIAL; # Not found in results, so deny
    }

    /*
    Restricted access to all available resources
    OR Restricted access to resources in a particular workflow state
    OR Restricted access to resources of a particular resource type
    UNLESS user/ group has been granted custom (override) access
    */
    if (
        $access == RESOURCE_ACCESS_FULL
        && ((!checkperm("g") || checkperm("rws{$resourcedata['archive']}") || checkperm('X' . $resource_type))
        && !$customgroupaccess
        && !$customuseraccess)
    ) {
        $access = RESOURCE_ACCESS_RESTRICTED;
    }

    // Check for a derestrict filter, this allows exceptions for users without the 'g' permission who normally have restricted accesss to all available resources)
    if ($access == RESOURCE_ACCESS_RESTRICTED && !checkperm("g") && !checkperm("rws{$resourcedata['archive']}") && !checkperm('X' . $resource_type) && trim((string) $userderestrictfilter) != "") {
        if (
            strlen(trim((string) $userderestrictfilter)) > 0
            && !is_numeric($userderestrictfilter)
            && trim((string) $userdata[0]["derestrict_filter"]) != ""
            && $userdata[0]["derestrict_filter_id"] != -1
        ) {
            // Migrate unless marked not to due to failure (flag will be reset if group is edited)
            $migrateresult = migrate_filter($userderestrictfilter);
            $notification_users = get_notification_users();
            global $userdata, $lang, $baseurl;
            if (is_numeric($migrateresult)) {
                // Successfully migrated - now use the new filter
                save_usergroup($usergroup, array('derestrict_filter_id' => $migrateresult));
                debug("FILTER MIGRATION: Migrated derestrict_filter_id filter - '" . $userderestrictfilter . "' filter id#" . $migrateresult);
                $userderestrictfilter = $migrateresult;
            } elseif (is_array($migrateresult)) {
                debug("FILTER MIGRATION: Error migrating filter: '" . $userderestrictfilter . "' - " . implode('\n', $migrateresult));
                // Error - set flag so as not to reattempt migration and notify admins of failure
                save_usergroup($usergroup, array('derestrict_filter_id' => -1));
                message_add(array_column($notification_users, "ref"), $lang["filter_migration"] . " - " . $lang["filter_migrate_error"] . ": <br/>" . implode('\n', $migrateresult), generateURL($baseurl . "/pages/admin/admin_group_management_edit.php", array("ref" => $usergroup)));
            }
        }

        if (is_int_loose($userderestrictfilter) && $userderestrictfilter > 0) {
            $matchedfilter = filter_check($userderestrictfilter, get_resource_nodes($ref));
            if ($matchedfilter) {
                $access = RESOURCE_ACCESS_FULL;
                $customgroupaccess = true;
            }
        }
    }

    $resource_access_cache[$ref] = (int) $access;
    return (int) $access;
}

/**
 * Retrieves the custom access level for a specific user on a given resource.
 *
 * This function checks the `resource_custom_access` table to see if the user has any custom access
 * permissions for the specified resource. It returns the access level if found and still valid;
 * otherwise, it returns false.
 *
 * @param int $resource The resource ID to check access for.
 * @param int $user The user ID to check for custom access.
 * @return mixed The access level if found; false otherwise.
 */
function get_custom_access_user($resource, $user)
{
    return ps_value("select access value from resource_custom_access where resource=? and user=? and (user_expires is null or user_expires>now())", array("i",$resource,"i",$user), false);
}

/**
 * Edits the external access settings for a given resource.
 *
 * @param string $key The external access key to edit.
 * @param int $access The new access level; defaults to -1 for no change.
 * @param string $expires The expiration date for the access key; optional.
 * @param string $group The user group to share with; defaults to the current user group if not specified.
 * @param string $sharepwd The password for accessing the resource; "(unchanged)" means no change.
 * @return bool Returns true if the operation was successful; false if the key is empty.
 */
function edit_resource_external_access($key, $access = -1, $expires = "", $group = "", $sharepwd = "")
{
    global $userref,$usergroup, $scramble_key;
    if ($group == "" || !checkperm("x")) {
        $group = $usergroup;
    } # Default to sharing with the permission of the current usergroup if not specified OR no access to alternative group selection.
    if ($key == "") {
        return false;
    }
    if ($sharepwd != "(unchanged)") {
        $sql = "password_hash= ?,";
        $params = ['s', (($sharepwd == "") ? "" : hash('sha256', $key . $sharepwd . $scramble_key))];
    } else {
        $sql = "";
        $params = [];
    }
    # Update the expiration and acccess
    ps_query(
        "UPDATE external_access_keys SET {$sql} access= ?, expires= ?,date=NOW(),usergroup= ? WHERE access_key = ?",
        array_merge($params, [
        'i', $access,
        's', (($expires == "") ? null : $expires),
        'i', $group,
        's',$key,
        ])
    );
    hook('edit_resource_external_access', '', array($key,$access,$expires,$group));
    return true;
}

/**
 * For the given resource and size, can the current user download it?
 * resource type and access may already be available in the case of search, so pass them along to get_resource_access to avoid extra queries
 * $resource can be a resource-specific search result array.
 *
 * @param  int     $resource        ID of resource
 * @param  string  $size            ID of size
 * @param  int     $resource_type   ID of resource type
 * @param  int     $alternative     Use alternative?
 * @param  bool    $usecache        Use cached result if available?
 *
 * @return boolean
 */
function resource_download_allowed($resource, $size, $resource_type, $alternative = -1, $usecache = false)
{
    global $userref, $usergroup, $user_dl_limit, $user_dl_days, $sizes_always_allowed, $download_access_cache;

    $cacheid = $resource . "_" . $size;
    if ($usecache && isset($download_access_cache[$cacheid])) {
        return $download_access_cache[$cacheid];
    }

    $access = get_resource_access($resource);

    if (resource_has_access_denied_by_RT_size($resource_type, $size)) {
        $download_access_cache[$cacheid] = false;
        return $download_access_cache[$cacheid];
    }

    if (checkperm('X' . $resource_type . "_" . $size) && $alternative == -1) {
        # Block access to this resource type / size? Not if an alternative file
        # Only if no specific user access override (i.e. they have successfully requested this size).
        $usercustomaccess = get_custom_access_user($resource, $userref);
        $usergroupcustomaccess = get_custom_access($resource, $usergroup);
        if (
            ($usercustomaccess === false || $usercustomaccess !== 0) &&
            ($usergroupcustomaccess === false || $usergroupcustomaccess !== 0)
        ) {
            $download_access_cache[$cacheid] = false;
            return $download_access_cache[$cacheid];
        }
    }

    if (($size == "" || $size == "hpr" || getval("noattach", "") == "")  && intval($user_dl_limit) > 0) {
        $download_limit_check = get_user_downloads($userref, $user_dl_days);
        if ($download_limit_check >= $user_dl_limit) {
            $download_access_cache[$cacheid] = false;
            return $download_access_cache[$cacheid];
        }
    }

    # Full access
    if ($access == 0) {
        $download_access_cache[$cacheid] = true;
        return $download_access_cache[$cacheid];
    }

    # Restricted
    if (1 == $access) {
        // The system should always allow these sizes to be downloaded as these are needed for search results and it makes
        // sense to allow them if a request for one of them is received. For example when $hide_real_filepath is enabled.
        // 'videojs' represents the preview loaded by videojs viewer.

        if ('' == $size) {
            # Original file - access depends on the 'restricted_full_download' config setting.
            $download_access_cache[$cacheid] = $GLOBALS["restricted_full_download"];
            return $download_access_cache[$cacheid];
        } elseif ('' != $size && in_array($size, $sizes_always_allowed)) {
            $download_access_cache[$cacheid] = true;
            return $download_access_cache[$cacheid];
        } else {
            # Return the restricted access setting for this resource type.
            $download_access_cache[$cacheid] = ps_value("
                SELECT allow_restricted value
                  FROM preview_size
                 WHERE id = ?",
                ["s", $size], 0, "schema") == 1;
                return $download_access_cache[$cacheid];
        }
    }

    # Confidential
    if ($access == 2) {
        $download_access_cache[$cacheid] = false;
        return $download_access_cache[$cacheid];
    }

    # Unable to determine access
    $download_access_cache[$cacheid] = false;
    return $download_access_cache[$cacheid];
}

/**
 * Check if current user has edit access to a resource. Checks the edit permissions (e0, e-1 etc.) and also the group
 * edit filter which filters edit access based on resource metadata.
 *
 * @param int $resource Resource ID
 * @param int $status Archive status ID. Use -999 to use the one from resourcedata argument
 * @param array $resourcedata
 */
function get_edit_access($resource, int $status = -999, array &$resourcedata = []): bool
{
    global $userref,$usergroup, $usereditfilter,$edit_access_for_contributor,
    $userpermissions, $lang, $baseurl, $userdata, $edit_only_own_contributions;

    $plugincustomeditaccess = hook('customediteaccess', '', array($resource,$status,$resourcedata));
    if ($plugincustomeditaccess) {
        return 'false' === $plugincustomeditaccess ? false : true;
    }

    if ($resourcedata === []) {
        $resourcedata = get_resource_data($resource);
    }

    if ($resourcedata === [] || $resourcedata === false) {
        return false;
    }
    if ($status == -999) { # Archive status may not be passed
        $status = $resourcedata["archive"];
    }

    if ($resource == 0 - (int)$userref) {
        return true;
    } # Can always edit their own user template.

    # If $edit_access_for_contributor is true in config then users can always edit their own resources.
    if ($edit_access_for_contributor && $userref == $resourcedata["created_by"]) {
        return true;
    }

    if ($edit_only_own_contributions && $userref != $resourcedata["created_by"]) {
        return false;
    }

    # Must have edit permission to this resource first and foremost, before checking the filter.
    if (
        (!checkperm("e{$status}") && !checkperm("ert{$resourcedata['resource_type']}"))
        || !acl_can_edit_resource_of_type($resourcedata['resource_type'])
    ) {
        return false;
    }

    # Cannot edit if z permission
    if (checkperm("z" . $status)) {
        return false;
    }

    # Cannot edit if accessing upload share and resource not in the collection associated witrh their session
    $external_upload = upload_share_active();
    if ($external_upload && !in_array($resource, get_collection_resources($external_upload))) {
        return false;
    }

    # Cannot edit if pending status (<0) and neither admin ('t') nor created by currentuser
    #             and does not have force edit access to the resource type
    if (
        $status < 0 && !( checkperm("t") || $resourcedata['created_by'] == $userref )
         && !checkperm("ert" . $resourcedata['resource_type'])
    ) {
        return false;
    }

    $gotmatch = false;

    if (
        strlen(trim((string) $usereditfilter)) > 0
        && !is_numeric($usereditfilter)
        && trim($userdata[0]["edit_filter"]) != ""
        && $userdata[0]["edit_filter_id"] != -1
    ) {
        // Migrate unless marked not to due to failure (flag will be reset if group is edited)
        $migrateeditfilter = edit_filter_to_restype_permission($usereditfilter, $usergroup, $userpermissions, true);
        if (trim($usereditfilter) !== "") {
            $migrateresult = migrate_filter($migrateeditfilter);
        } else {
            $migrateresult = 0; // filter was only for resource type, not failed but no need to migrate again
        }

        $notification_users = get_notification_users();
        if (is_numeric($migrateresult)) {
            // Successfully migrated - now use the new filter
            save_usergroup($usergroup, array('edit_filter_id' => $migrateresult));
            debug("FILTER MIGRATION: Migrated edit filter - '" . $usereditfilter . "' filter id#" . $migrateresult);
            $usereditfilter = $migrateresult;
        } elseif (is_array($migrateresult)) {
            debug("FILTER MIGRATION: Error migrating filter: '" . $usereditfilter . "' - " . implode('\n', $migrateresult));
            // Error - set flag so as not to reattempt migration and notify admins of failure
            save_usergroup($usergroup, array('edit_filter_id' => 0));
            message_add(array_column($notification_users, "ref"), $lang["filter_migration"] . " - " . $lang["filter_migrate_error"] . ": <br/>" . implode('\n', $migrateresult), generateURL($baseurl . "/pages/admin/admin_group_management_edit.php", array("ref" => $usergroup)));
        }
    }

    if (trim((string) $usereditfilter) == "" || ($status < 0 && $resourcedata['created_by'] == $userref)) { # No filter set, or resource was contributed by user and is still in a User Contributed state in which case the edit filter should not be applied.
        $gotmatch = true;
    } elseif (is_int_loose($usereditfilter) && $usereditfilter > 0) {
        $gotmatch = filter_check($usereditfilter, get_resource_nodes($resource));
    }

    if ($gotmatch) {
        $gotmatch = !hook("denyafterusereditfilter");
    }

    if (checkperm("ert" . $resourcedata['resource_type'])) {
        return true;
    }

    return $gotmatch;
}

/**
* In the given filter string, does name/value match?
* Returns:
* 0 = no match for name
* 1 = matched name but value was not present
* 2 = matched name and value was correct
*
* @param  string  $filter   Sring to for which filtering is to be applied
* @param  string  $name     Name to match
* @param  string  $value    Value to match
*
* @return int
*/
function filter_match($filter, $name, $value)
{
    $s = explode(";", $filter);
    foreach ($s as $condition) {
        $s = explode("=", $condition);
        # Support for "NOT" matching. Return results only where the specified value or values are NOT set.
        $checkname = $s[0];
        $filter_not = false;
        if (substr($checkname, -1) == "!") {
            $filter_not = true;
            $checkname = substr($checkname, 0, -1);# Strip off the exclamation mark.
        }
        if ($checkname == $name) {
            $checkvalues = $s[1];

            $s = explode("|", strtoupper($checkvalues));
            $v = trim_array(explode(",", strtoupper($value ?? "")));
            foreach ($s as $checkvalue) {
                if (in_array($checkvalue, $v)) {
                    return $filter_not ? 1 : 2;
                }
            }
            return $filter_not ? 2 : 1;
        }
    }
    return 0;
}

/**
* Check changes made to a metadata field and create a nice user friendly summary
*
* @uses Diff::compare()
* @uses Diff::toString()
*
* @param string $fromvalue - if nodes then values will be separated by NODE_NAME_STRING_SEPARATOR
* @param string $tovalue - if nodes then values will be separated by NODE_NAME_STRING_SEPARATOR
*
* @return string
*/
function log_diff($fromvalue, $tovalue)
{
    $return = '';
    debug_function_call("log_diff", func_get_args());

    // Trim values as it can cause out of memory errors with class.Diff.php e.g. when saving extracted text or creating previews for large PDF files
    if (strlen((string) $fromvalue) > 10000) {
        $fromvalue = mb_substr($fromvalue, 10000);
    }
    if (strlen((string) $tovalue) > 10000) {
        $tovalue = mb_substr($tovalue, 10000);
    }

    // Remove any database escaping
    $fromvalue = str_replace("\\", '', $fromvalue);
    $tovalue   = str_replace("\\", '', $tovalue);

    // Work a different way for fixed lists
    if (strpos($fromvalue, NODE_NAME_STRING_SEPARATOR) !== false || strpos($tovalue, NODE_NAME_STRING_SEPARATOR) !== false) {
        $fromvalue = array_filter(explode(NODE_NAME_STRING_SEPARATOR, $fromvalue));
        $tovalue   = array_filter(explode(NODE_NAME_STRING_SEPARATOR, $tovalue));

        // Empty arrays if either side is blank.
        if (count($fromvalue) == 0) {
            $fromvalue = array();
        }
        if (count($tovalue) == 0) {
            $tovalue = array();
        }

        // Get diffs
        $inserts = array_diff($tovalue, $fromvalue);
        $deletes = array_diff($fromvalue, $tovalue);

        // Process array diffs into meaningful strings
        if (0 < count($deletes)) {
            $return .= '- ' . join("\n- ", $deletes);
        }

        if (0 < count($inserts)) {
            if ('' != $return) {
                $return .= "\n";
            }

            $return .= '+ ' . join("\n+ ", $inserts);
        }

        return $return;
    }

    // Simple return when either side is blank (the user is adding or removing all the text)
    if ($fromvalue == "") {
        return "+ " . $tovalue;
    }
    if ($tovalue == "") {
        return "- " . $fromvalue;
    }

    // For standard strings, use Diff library
    require_once __DIR__ . '/../lib/Diff/class.Diff.php';
    return Diff::toString(Diff::compare($fromvalue, $tovalue));
}

/**
 * Retrieves a list of all metadata templates for a specified resource type.
 *
 * @return array An array of metadata templates, each containing the resource reference and its title.
 */

function get_metadata_templates()
{
    # Returns a list of all metadata templates; i.e. resources that have been set to the resource type specified via '$metadata_template_resource_type'.
    global $metadata_template_resource_type,$metadata_template_title_field;
    return ps_query("select ref,field$metadata_template_title_field from resource where ref>0 and resource_type= ? order by field$metadata_template_title_field", ['i', $metadata_template_resource_type]);
}

/**
 * Retrieves a list of collections that a resource is used in for the specified resource reference.
 *
 * @param int $ref The resource reference for which to retrieve associated collections.
 * @return array An array of collections associated with the resource, including user information and resource count.
 */
function get_resource_collections($ref)
{
    global $userref;
    if (checkperm('b') || is_anonymous_user()) {
        return array();
    }

    # Returns a list of collections that a resource is used in for the $view_resource_collections option
    $sql = "";

    # Include themes in my collections?
    # Only filter out themes if $themes_in_my_collections is set to false in config.php
    global $themes_in_my_collections;
    if (!$themes_in_my_collections) {
        if ($sql != "") {
            $sql .= " and ";
        }
        $sql .= "(length(c.theme)=0 or c.theme is null) ";
    }
    if ($sql != "") {
        $sql = "where " . $sql;
    }

    return ps_query("select * from
	(select c.*,u.username,u.fullname,count(r.resource) count from user u join collection c on u.ref=c.user and c.user=? left outer join collection_resource r on c.ref=r.collection group by c.ref
	union
	select c.*,u.username,u.fullname,count(r.resource) count from user_collection uc join collection c on uc.collection=c.ref and uc.user=? and c.user<>? left outer join collection_resource r on c.ref=r.collection left join user u on c.user=u.ref group by c.ref) clist where clist.ref in (select collection from collection_resource cr where cr.resource=?)", array(
        "i",$userref,
        "i",$userref,
        "i",$userref,
        "i",$ref
        ));
}

/**
 * Retrieves a summary of downloads for a given resource, categorized by usage type.
 *
 * This function queries the resource log to count the number of downloads
 * for the specified resource, grouping the results by the usage option.
 *
 * @param int $resource The resource reference for which to retrieve the download summary.
 * @return array An array of download summaries, each containing the usage option and the corresponding count.
 */
function download_summary($resource)
{
    return ps_query("select usageoption,count(*) c from resource_log where resource=? and type='D' group by usageoption order by usageoption", array("i",$resource));
}

/*
* Check if watermark is required.
*
* @param string $download_key  Optional - download key used when $terms_download and $watermark_open are enabled
* @param string $resource      Optional - resource ID to check download key is valid for
*
* * @return boolean
*/
function check_use_watermark($download_key = "", $resource = "")
{
    debug_function_call("check_use_watermark", func_get_args());
    # This function checks whether or not to use watermarks
    # Note that access status must be available prior to calls to this function

    global $access,$k,$watermark,$watermark_open,$pagename,$watermark_open_search, $terms_download;

    # Cannot watermark without a watermark
    if ($watermark === '') {
        return false;
    }

    $blockwatermark = hook("blockwatermark");
    if ($blockwatermark) {
        return false;
    }

    # Cannot watermark unless permission "w" is present
    if (!checkperm('w')) {
        return false;
    }

    # Watermark is present and permission "w" is present

    # Watermark if access is restricted
    if ($access == 1) {
        return true;
    }

    # Watermark if open override is present
    if (
        $watermark_open
        && (    ($pagename == "preview")
             || ($pagename == "view")
             || ($pagename == "search" && $watermark_open_search)
             || ($pagename == "download" && $terms_download && !download_link_check_key($download_key, $resource))
           )
    ) {
        return true;
    }

    # Watermark not necessary
    return false;
}

/**
* Fill in any blank fields for the resource.
*
* IMPORTANT: Auto completing blank fields with inactive options should be allowed (possibly a system misconfiguration).
* This function will NOT exclude inactive nodes because, for required fields, you might end up having no value
* after processing the field.
*
* @param  integer   $resource         Resource ID
* @param  boolean   $force_run        Allow code to force running this function and update the fields even if there is data.
* @param  boolean   $return_changes   When true an array of fields changed by autocomplete is returned.
* For example:
* - when creating a resource, autocomplete_blank_fields() should always be triggered regardless if user has data in its user template.
* - when copying resource/ extracting embedded metadata, autocomplete_blank_fields() should not overwrite if there is data
* for that field as at this point you probably have the expected data for your field.
* @param  integer   $field_ref        Optional parameter to specify which metadata field should be processed. Left blank, all fields will be processed (default behaviour).
*
* @return boolean|array Success/fail or array of changes made
*/
function autocomplete_blank_fields($resource, $force_run, $return_changes = false, int $field_ref = 0)
{
    global $FIXED_LIST_FIELD_TYPES, $lang;

    if ((string)(int)$resource != (string)$resource) {
        return false;
    }

    $resource_type = ps_value("SELECT resource_type AS `value` FROM resource WHERE ref = ?", ["i",$resource], 0);

    $sql_set_field = '';
    $sql_set_field_params = array();
    if ($field_ref > 0) {
        $sql_set_field = ' AND rtf.ref = ?';
        $sql_set_field_params = array('i', $field_ref);
    }

    $fields = ps_query(
        "SELECT rtf.ref, rtf.type, rtf.autocomplete_macro
            FROM resource_type_field rtf
            LEFT JOIN resource_type rt ON rt.ref = ?
            WHERE length(rtf.autocomplete_macro) > 0
                AND (((rtf.global=0 OR rtf.global IS NULL) 
                        AND rt.ref IN (
                            SELECT resource_type 
                                FROM resource_type_field_resource_type rtjoin 
                                WHERE rtjoin.resource_type_field=rtf.ref
                                ))
                    OR (rtf.global=1)
                    ) $sql_set_field",
        array_merge(array("i", $resource_type), $sql_set_field_params),
        "schema"
    );

    $fields_updated = array();

    foreach ($fields as $field) {
        $run_autocomplete_macro = $force_run || hook('run_autocomplete_macro');
        # The autocomplete macro will run if the existing value is blank, or if forced to always run
        if (count(get_resource_nodes($resource, $field['ref'], true)) == 0 || $run_autocomplete_macro) {
            # Autocomplete and update using the returned value
            $value = eval(eval_check_signed($field['autocomplete_macro']));
            if (in_array($field['type'], $FIXED_LIST_FIELD_TYPES)) {
                # Multiple values are comma separated
                $autovals = str_getcsv((string) $value);
                $autonodes = array();
                # Establish an array of nodes from the values
                foreach ($autovals as $autoval) {
                    $nodeid = get_node_id($autoval, $field['ref']);
                    if ($nodeid !== false) {
                        $autonodes[] = $nodeid;
                    }
                }
                # Add nodes if any were established
                if (count($autonodes) > 0) {
                    natsort($autonodes);
                    add_resource_nodes($resource, $autonodes, false, false);
                    log_node_changes($resource, $autonodes, array(), $lang["autocomplete_log_note"]);
                    $fields_updated[$field['ref']] = implode(",", $autonodes);

                    # If this is a 'joined' field we need to add it to the resource column
                    $joins = get_resource_table_joins();
                    if (in_array($field['ref'], $joins)) {
                        update_resource_field_column($resource, $field['ref'], $value);
                    }
                }
            } else {
                update_field($resource, $field['ref'], $value);
                $fields_updated[$field['ref']] = $value;
            }
        }
    }

    if ($return_changes) {
        return $fields_updated;
    }
    return true;
}

function get_page_count($resource, $alternative = -1)
{
    # gets page count for multipage previews from resource_dimensions table.
    # also handle alternative file multipage previews by switching $resource array if necessary
    # $alternative specifies an actual alternative file
    $ref = $resource['ref'];

    if ($alternative != -1) {
        $pagecount = ps_value("select page_count value from resource_alt_files where ref=?", array("i",$alternative), "");
        $resource = get_alternative_file($ref, $alternative);
    } else {
        $pagecount = ps_value("select page_count value from resource_dimensions where resource=?", array("i",$ref), "");
    }
    if (!empty($pagecount)) {
        return $pagecount;
    }
    # or, populate this column with exiftool or image magick (for installations with many pdfs already
    # previewed and indexed, this allows pagecount updates on the fly when needed):
    # use exiftool.
    if ($resource['file_extension'] == "pdf" && $alternative == -1) {
        $file = get_resource_path($ref, true, "", false, "pdf");
    } elseif ($alternative == -1) {
        # some unoconv files are not pdfs but this needs to use the auto-alt file
        $alt_ref = ps_value("select ref value from resource_alt_files where resource=? and unoconv=1", array("i",$ref), "");
        $file = get_resource_path($ref, true, "", false, "pdf", -1, 1, false, "", $alt_ref);
    } else {
        $file = get_resource_path($ref, true, "", false, "pdf", -1, 1, false, "", $alternative);
    }

    if (file_exists($file)) {
        # locate exiftool
        $exiftool_fullpath = get_utility_path("exiftool");

        $cmdparams = [
            '[FILE]' => new CommandPlaceholderArg($file, 'is_valid_rs_path'),
        ];
        if (!$exiftool_fullpath) {
            # Try with ImageMagick instead
            $command = get_utility_path("im-identify") . ' -format %n [FILE]';
            $pages = trim(run_command($command, false, $cmdparams));
        } else {
            $command = $exiftool_fullpath . " -sss -pagecount [FILE]";
            $output = run_command($command, false, $cmdparams);
            $pages = str_replace("Page Count", "", $output);
            $pages = str_replace(":", "", $pages);
            $pages = trim($pages);
        }
        if (!is_numeric($pages)) {
            $pages = 1; // default to 1 page if we didn't get anything back
        }
    } else {
        $pages = 1;
    }

    if ($alternative != -1) {
        ps_query("update resource_alt_files set page_count=? where ref=?", array("i",$pages,"i",$alternative));
    } else {
        ps_query("update resource_dimensions set page_count=? where resource=?", array("i",$pages,"i",$ref));
    }
    return $pages;
}

/**
 * Updates the disk usage for a specified resource by calculating the total size of the files
 * in the resource's directory and storing the result in the resource table.
 *
 * This function first checks for the size of the primary resource file and then scans the
 * associated folder to compute the total disk usage, excluding any files in staticsync locations.
 *
 * @param int $resource The resource reference whose disk usage is to be updated.
 * @return bool Returns true on success, or false if the directory does not exist.
 */
function update_disk_usage($resource)
{
    # we're also going to record the size of the primary resource here before we do the entire folder
    $ext = ps_value("SELECT file_extension `value` FROM `resource` WHERE ref = ?", array("i", $resource), 'jpg');
    $path = get_resource_path($resource, true, '', false, $ext);
    if (file_exists($path)) {
        $rsize = filesize_unlimited($path);
    } else {
        $rsize = 0;
    }

    # Scan the appropriate filestore folder and update the disk usage fields on the resource table. Use the thm size so that we don't get a Staticsync location
    $dir = dirname(get_resource_path($resource, true, "thm", false));
    if (!file_exists($dir)) {
        return false;
    } # Folder does not yet exist.
    $d = dir($dir);
    $total = 0;
    while ($f = $d->read()) {
        if ($f != ".." && $f != ".") {
            $s = (int) filesize_unlimited($dir . "/" . $f);
            $total += $s;
        }
    }
    ps_query("update resource set disk_usage=?,disk_usage_last_updated=now(),file_size=? where ref=?", array("i",$total,"i",$rsize,"i",$resource));

    return true;
}

/**
 * Update disk usage for all resources that have not yet been updated or have not been updated in the past 30 days.
 * Limit to a reasonable amount so that this process is spread over several cron intervals for large data sets.
 *
 * @return boolean|void
 */
function update_disk_usage_cron()
{
    global $update_disk_usage_batch_size;

    $lastrun = get_sysvar('last_update_disk_usage_cron', '1970-01-01');
    # Don't run if already run in last 24 hours.
    if (time() - strtotime($lastrun) < 24 * 60 * 60) {
        echo " - Skipping update_disk_usage_cron  - last run: " . $lastrun . "<br/>\n";
        return false;
    }
    if (is_process_lock("disk_usage_cron")) {
        echo " - disk_usage_cron process lock is in place. Skipping.\n";
        return;
    }
    set_process_lock("disk_usage_cron");

    $resources = ps_array(
        "SELECT ref value
            FROM resource
        WHERE ref>0
            AND disk_usage_last_updated IS null
                OR datediff(now(),disk_usage_last_updated)>30
        ORDER BY disk_usage_last_updated ASC
        LIMIT " . (int) $update_disk_usage_batch_size,
        []
    );
    foreach ($resources as $resource) {
        update_disk_usage($resource);
    }

    clear_query_cache("stats");
    set_sysvar("last_update_disk_usage_cron", date("Y-m-d H:i:s"));
    clear_process_lock("disk_usage_cron");
}

/**
 * Returns the total disk space used by all resources on the system
 *
 * @return int
 */
function get_total_disk_usage()
{
    global $fstemplate_alt_threshold;
    return ps_value("select ifnull(sum(disk_usage),0) value from resource where ref>?", array("i",$fstemplate_alt_threshold), 0, "stats");
}

/**
 *  Calculate if the quota set by $disksize in the configuration has been exceeded.
 *
 * @return array|bool           True if the system is over quota
 */
function overquota()
{
    global $disksize;
    if (isset($disksize)) {
        $avail = $disksize * 1000 * 1000 * 1000;
        $used = get_total_disk_usage();

        $free = $avail - $used;
        if ($free <= 0) {
            return true;
        }
    }
    return false;
}

/**
 * Get size of specified image file
 *
 * @param  int $ref             Resource ID
 * @param  string $path         File path
 * @param  string $extension    File extension
 * @param  bool $forcefromfile  Get info from file instead of database cache
 *
 * @return array|bool           File size info. Returns false if not available
 */
function get_original_imagesize($ref = "", $path = "", $extension = "jpg", $forcefromfile = false)
{
    $fileinfo = array();
    if ($ref == "" || $path == "") {
        return false;
    }
    global $imagemagick_path, $imagemagick_calculate_sizes;

    if (!file_exists($path)) {
        return false;
    }

    $file = $path;

    // check for valid image
    if (function_exists('mime_content_type')) {
        $mime_content_type = mime_content_type($file);
    } else {
        $mime_content_type = get_mime_type($file)[0];
    }

    $o_size = ps_query("SELECT " . columns_in("resource_dimensions") . " FROM resource_dimensions WHERE resource=?", array("i",$ref));
    if (!empty($o_size)) {
        if (count($o_size) > 1) {
            # delete all the records and start fresh. This is a band-aid should there be multiple records as a result of using api_search
            ps_query("DELETE FROM resource_dimensions WHERE resource=?", array("i",$ref));
            $o_size = false;
            $forcefromfile = true;
        } else {
            $o_size = $o_size[0];
        }
    } else {
        $o_size = false;
    }

    if ($o_size !== false && !$forcefromfile && $o_size['file_size'] > 0) {
        $fileinfo[0] = $o_size['file_size'];
        $fileinfo[1] = $o_size['width'];
        $fileinfo[2] = $o_size['height'];
        return $fileinfo;
    }

    $filesize = filesize_unlimited($file);

    $is_image = strpos($mime_content_type, 'image/') !== false;
    # imagemagick_calculate_sizes is normally turned off
    if ($is_image && isset($imagemagick_path) && $imagemagick_calculate_sizes) {
        # Use ImageMagick to calculate the size
        $prefix = '';
        # Camera RAW images need prefix
        if (preg_match('/^(dng|nef|x3f|cr2|crw|mrw|orf|raf|dcr)$/i', $extension, $rawext)) {
            $prefix = $rawext[0] . ':';
        }

        # Locate imagemagick.
        $identify_fullpath = get_utility_path("im-identify");
        if (!$identify_fullpath) {
            exit("Could not find ImageMagick 'identify' utility at location '$imagemagick_path'.");
        }
        # Get image's dimensions.
        $identcommand = $identify_fullpath . ' -format %wx%h ' . escapeshellarg($prefix . $file) . '[0]';
        $identoutput = run_command($identcommand);
        preg_match('/^([0-9]+)x([0-9]+)$/ims', $identoutput, $smatches);
        if (count($smatches) < 3) {
            return false;
        }
        @list(,$sw,$sh) = $smatches;
        if (($sw != '') && ($sh != '')) {
            if (!$o_size) {
                ps_query("insert into resource_dimensions (resource, width, height, file_size) values(?, ?, ?, ?)", array("i",$ref,"i",$sw,"i",$sh,"i",(int)$filesize));
            } else {
                ps_query("update resource_dimensions set width=?, height=?, file_size=? where resource=?'", array("i",$sw,"i",$sh,"i",(int)$filesize,"i",$ref));
            }
        }
    } else {
        # check if this is a raw file.
        $rawfile = false;
        if (preg_match('/^(dng|nef|x3f|cr2|crw|mrw|orf|raf|dcr)$/i', $extension, $rawext)) {
            $rawfile = true;
        }

        # Use GD to calculate the size
        $GLOBALS["use_error_exception"] = true;
        try {
            list($sw,$sh) = getimagesize($file);
        } catch (Exception $e) {
            $returned_error = $e->getMessage();
            debug("get_original_imagesize: Unable to get image size for file: $file  -  $returned_error");
        }
        unset($GLOBALS["use_error_exception"]);
        if ((isset($sw) && isset($sh)) && !$rawfile) {
            if (!$o_size) {
                ps_query("insert into resource_dimensions (resource, width, height, file_size) values(?, ?, ?, ?)", array("i",$ref,"i",$sw,"i",$sh,"i",(int)$filesize));
            } else {
                ps_query("update resource_dimensions set width=?, height=?, file_size=? where resource=?", array("i",$sw,"i",$sh,"i",(int)$filesize,"i",$ref));
            }
        } else {
            # Assume size cannot be calculated.
            $sw = "?";
            $sh = "?";

            global $ffmpeg_supported_extensions;
            if (in_array(strtolower($extension), $ffmpeg_supported_extensions) && function_exists('json_decode')) {
                $file = get_resource_path($ref, true, "", false, $extension);
                $ffprobe_array = get_video_info($file);

                # Different versions of ffprobe store the dimensions in different parts of the json output. Test both.
                if (!empty($ffprobe_array['width'])) {
                    $sw = intval($ffprobe_array['width']);
                }
                if (!empty($ffprobe_array['height'])) {
                    $sh = intval($ffprobe_array['height']);
                }
                if (isset($ffprobe_array['streams']) && is_array($ffprobe_array['streams'])) {
                    foreach ($ffprobe_array['streams'] as $stream) {
                        if (!empty($stream['codec_type']) && $stream['codec_type'] === 'video') {
                            $sw = intval($stream['width']);
                            $sh = intval($stream['height']);
                            break;
                        }
                    }
                }
            }
            if ($sw !== '?' && $sh !== '?') {
                # Size could be calculated after all
                if (!$o_size) {
                    ps_query("INSERT INTO resource_dimensions (resource, width, height, file_size) VALUES (?, ?, ?, ?)", array("i",$ref,"i",$sw,"i",$sh,"i",(int)$filesize));
                } else {
                    ps_query("UPDATE resource_dimensions SET width=?, height=?, file_size=? WHERE resource=?", array("i",$sw,"i",$sh,"i",$filesize,"i",$ref));
                }
            } else {
                # Size cannot be calculated.
                $sw = "?";
                $sh = "?";
                if (!$o_size) {
                    # Insert a dummy row to prevent recalculation on every view.
                    ps_query("INSERT INTO resource_dimensions (resource, width, height, file_size) VALUES (?,'0', '0', ?)", array("i",$ref,"i",$filesize));
                } else {
                    ps_query("UPDATE resource_dimensions SET width='0', height='0', file_size=? WHERE resource=?", array("i",$filesize,"i",$ref));
                }
            }
        }
    }

    $fileinfo[0] = $filesize;
    $fileinfo[1] = $sw;
    $fileinfo[2] = $sh;
    return $fileinfo;
}

/**
 * Generates an external access key for a resource, allowing specified access to a user or group.
 *
 * This function creates a unique access key and stores it in the database along with
 * information about the resource, the user, the type of access granted,
 * expiration date, email, and user group. It also hashes the share password if provided.
 *
 * @param int $resource The resource reference for which the access key is being generated.
 * @param int $userref The user reference for the user to whom access is granted.
 * @param int $access The level of access granted (e.g., full access, restricted).
 * @param string|null $expires The expiration date of the access key, formatted as 'Y-m-d'.
 * @param string $email The email address associated with the user receiving the access key.
 * @param string $group The user group associated with the access, defaults to the current user group if not specified.
 * @param string $sharepwd The share password, if any, used to secure access.
 * @return string|bool Returns the generated access key on success, or false if permissions are insufficient.
 */
function generate_resource_access_key($resource, $userref, $access, $expires, $email, $group = "", $sharepwd = "")
{
    global $username, $anonymous_login;

    if (checkperm("noex") || is_anonymous_user()) {
        // "noex" users shouldn't ever get here, but catch in case not already checked
        // also block anon users from generating keys as they're unneeded.
        return false;
    }

        global $userref,$usergroup, $scramble_key;
    if ($group == "" || !checkperm("x")) {
        $group = $usergroup;
    } # Default to sharing with the permission of the current usergroup if not specified OR no access to alternative group selection.
        $k = substr(md5(time()), 0, 10);
        ps_query(
            "insert into external_access_keys(resource,access_key,user,access,expires,email,date,usergroup,password_hash) values (?, ?, ?, ?, ?, ?,now(), ?, ?);",
            [
            'i', $resource,
            's', $k,
            'i', $userref,
            'i', $access,
            's', ((!validateDatetime($expires, 'Y-m-d')) ? null : $expires),
            's', $email,
            'i', $group,
            's', (($sharepwd != "" && $sharepwd != "(unchanged)") ? hash('sha256', $k . $sharepwd . $scramble_key) : null)
            ]
        );
        hook("generate_resource_access_key", "", array($resource,$k,$userref,$email,$access,$expires,$group));
        return $k;
}

/**
 * Retrieves all external access keys granted for a specific resource.
 *
 * This function returns a list of all external access details for the given resource,
 * including users and emails associated with each access key. If the user does not have
 * the elevated 'v' permission, the function will only return access keys associated
 * with the current user. The results are grouped by access key, allowing for multiple
 * users or emails to be returned as comma-separated values.
 *
 * @param int $resource The reference ID of the resource for which external access is being retrieved.
 * @return array An array of access details, including access keys, users, emails, expiration dates,
 *               and other related information for the specified resource.
 */
function get_resource_external_access($resource)
{
    # Return all external access given to a resource
    # Users, emails and dates could be multiple for a given access key, an in this case they are returned comma-separated.
    global $userref;

    # Build parameters for the query
    $params = array("i",$resource);

    # Restrict to only their shares unless they have the elevated 'v' permission
    $condition = "";
    if (!checkperm("v")) {
        $condition = "AND user=?";
        $params[] = "i";
        $params[] = $userref;
    }

    return ps_query("select access_key,group_concat(DISTINCT user ORDER BY user SEPARATOR ', ') users,group_concat(DISTINCT email ORDER BY email SEPARATOR ', ') emails,max(date) maxdate,max(lastused) lastused,access,expires,collection,usergroup, password_hash from external_access_keys where resource=? $condition group by access_key,access,expires,collection,usergroup order by maxdate", $params);
}

function delete_resource_access_key($resource, $access_key)
{
    global $lang;
    ps_query("delete from external_access_keys where access_key=? and resource=?", array("s",$access_key,"i",$resource));
    resource_log($resource, LOG_CODE_DELETED_ACCESS_KEY, '', '', str_replace('%access_key', $access_key, $lang['access_key_deleted']), '');
}

function resource_type_config_override($resource_type, $only_onchange = true)
{
    debug_function_call(__FUNCTION__, func_get_args());
    # Pull in the necessary config for a given resource type
    # As this could be called many times, e.g. during search result display
    # By default (only_onchange) only execute the override if the passed resourcetype is different from the previous
    global $resource_type_config_override_last,$resource_type_config_override_snapshot, $ffmpeg_alternatives;

    $config_override_required = false;
    # If the overrides are only to be executed on change of resource type
    if ($only_onchange) {
        # If the resource type has changed or if this is the first resource....
        if (!isset($resource_type_config_override_last) || $resource_type_config_override_last != $resource_type) {
            $config_override_required = true;
            $resource_type_config_override_last = $resource_type;
        }
    } else # The overrides are to be executed for every resource
        {
        $config_override_required = true;
    }

    if ($config_override_required) {
        # Look for config and execute.
        $config_options = ps_value("select config_options value from resource_type where ref=?", array("i",$resource_type), "", "schema");
        if ($config_options != "") {
            override_rs_variables_by_eval($GLOBALS, $config_options, 'resource_type');
        }
    }
}

/**
* Update the archive state of resource(s) and log this
*
* @param integer|array $resource_id - Resource unique ref -or- array of Resource refs
* @param integer $archive - Destination archive state
* @param integer|array $existingstates -  existing archive state _or_ array of corresponding existing archive states
* @param integer $collection - optional id of collection containing resources
*
* @return void
*/
function update_archive_status($resource, $archive, $existingstates = array(), $collection = 0, $more_notes = "")
{
    global $resource_deletion_state,$remove_deleted_resources_from_collections;

    if (!is_array($resource)) {
        $resource = array($resource);
    }

    if (!is_array($existingstates)) {
        $existingstates = array($existingstates);
    }

    $count = count($resource);

    for ($n = 0; $n < $count; $n++) {
        if (!is_numeric($resource[$n])) {
            continue;
        }

        resource_log(
            $resource[$n],
            $resource_deletion_state == $archive ? LOG_CODE_DELETED : LOG_CODE_STATUS_CHANGED,
            0,
            $more_notes,
            isset($existingstates[$n]) ? $existingstates[$n] : '',
            $archive
        );
    }

    # Prevent any attempt to update with non-numeric archive state
    if (!is_numeric($archive)) {
        debug("update_archive_status FAILED - resources=(" . implode(",", $resource) . "), archive: " . $archive . ", existingstates:(" . implode(",", $existingstates) . "), collection: " . $collection);
        return;
    }

    $resource_chunks = array_chunk($resource, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    foreach ($resource_chunks as $chunk) {
        ps_query("UPDATE resource SET archive = ? WHERE ref IN (" . ps_param_insert(count($chunk)) . ")", array_merge(["i", $archive], ps_param_fill($chunk, "i")));
    }

    # Resources should be removed from collections when being moved into the deletion state as specified in config.default.php
    if ($remove_deleted_resources_from_collections && $resource_deletion_state == $archive) {
        foreach ($resource as $ref) {
            $in_collections = ps_query('SELECT collection, resource FROM collection_resource WHERE resource = ?', ['i', $ref]);
            ps_query('DELETE FROM collection_resource WHERE resource = ?', ['i', $ref]);
            foreach ($in_collections as $col) {
                collection_log($col['collection'], LOG_CODE_COLLECTION_DELETED_RESOURCE, $col['resource']);
            }
        }
    }

    hook('after_update_archive_status', '', array($resource, $archive,$existingstates));
    // Send notifications
    debug("update_archive_status - resources=(" . implode(",", $resource) . "), archive: " . $archive . ", existingstates:(" . implode(",", $existingstates) . "), collection: " . $collection);
}

function delete_resources_in_collection($collection)
{
    if (
        !is_int_loose($collection)
        || checkperm("D")
        || !allow_multi_edit($collection)
        || !collection_writeable($collection)
    ) {
        debug("Unable to delete resources in collection: $collection");
        return false;
    }

    global $resource_deletion_state, $lang;

    // Always find all resources in deleted state and delete them permanently:
    // Note: when resource_deletion_state is null it will find all resources in collection and delete them permanently
    $query = "SELECT ref AS value FROM resource INNER JOIN collection_resource ON collection_resource.resource = resource.ref AND collection_resource.collection = ?";
    $params = array("i",$collection);

    if (isset($resource_deletion_state)) {
        $query .= " WHERE archive = ?";
        $params[] = "i";
        $params[] = $resource_deletion_state;
    }

    $resources_in_deleted_state = ps_array($query, $params);

    if (!empty($resources_in_deleted_state)) {
        foreach ($resources_in_deleted_state as $resource_in_deleted_state) {
            delete_resource($resource_in_deleted_state);
            collection_log($collection, 'D', '', 'Resource ' . $resource_in_deleted_state . ' deleted permanently.');
        }
    }

    // Create a comma separated list of all resources remaining in this collection:
    $resources = ps_query("SELECT cr.resource, r.archive FROM collection_resource cr LEFT JOIN resource r on r.ref=cr.resource WHERE cr.collection = ?", ["i",$collection]);
    $r_refs = array_column($resources, "resource");
    $r_states = array_column($resources, "archive");

    // If all resources had their state the same as resource_deletion_state, stop here:
    // Note: when resource_deletion_state is null it will always stop here
    if (empty($resources)) {
        return true;
    }

    // Delete (ie. move to resource_deletion_state set in config):
    if (isset($resource_deletion_state)) {
        update_archive_status($r_refs, $resource_deletion_state, $r_states);
        collection_log($collection, 'D', '', str_replace("%ARCHIVE", $resource_deletion_state, $lang['log-deleted_all']));
    }

    return true;
}

/**
 * Update related resources - add new related resource(s) or delete existing
 *
 * @param  int      $ref        ID of primary resource
 * @param  int|array  related   Resource ID or array of resource IDs to link to current resource
 * @param  boolean  $add        Add relationship? If false this will delete the specified relationships
 *
 * @return boolean
 */
function update_related_resource($ref, $related, $add = true)
{
    if (!is_int_loose($ref) || (!is_int_loose($related) && !is_array($related))) {
        return false;
    }
    if (is_array($related)) {
        $related = array_filter($related, "is_int_loose");
    } else {
        $related = array((int)$related);
    }

    if (count($related) == 0) {
        return false;
    }

    // Check edit access
    $access = get_edit_access($ref);
    if (!$access) {
        debug('Failed to update related resources for ref ' . $ref . ' - no edit access to this resource');
        return false;
    }
    foreach ($related as $relate) {
        $access = get_edit_access($relate);
        if (!$access) {
            debug('Failed to update related resources for ref ' . $ref . ' - user cannot edit ref ' . $relate);
            return false;
        }
    }

    // This params array can be used for both SELECT and DELETE
    $relatedparams = array_merge(["i",$ref], ps_param_fill($related, "i"), ps_param_fill($related, "i"), ["i",$ref]);

    $query = "SELECT resource, related FROM resource_related  WHERE (resource = ? AND related IN (" . ps_param_insert(count($related)) . "))
      OR (resource IN (" . ps_param_insert(count($related)) . ") AND related = ?)";
    $currentlyrelated = ps_query($query, $relatedparams);

    // Create array of all related resources
    $currentlyrelated_arr = array_unique(array_merge(
        array_column($currentlyrelated, "related"),
        array_column($currentlyrelated, "resource")
    ));

    if (count($currentlyrelated_arr) > 0 && !$add) {
        // Relationships exist and we want to remove
        $query = "DELETE FROM resource_related  WHERE (resource = ? AND related IN (" . ps_param_insert(count($related)) . "))
        OR (resource IN (" . ps_param_insert(count($related)) . ") AND related = ?)";
        ps_query($query, $relatedparams);
    } elseif ($add) {
        $newrelated = array();
        foreach ($related as $torelate) {
            if (!in_array($torelate, $currentlyrelated_arr) && $torelate != $ref) {
                $newrelated[] = $torelate;
            }
        }
        if (count($newrelated) > 0) {
            ps_query("INSERT INTO resource_related (resource,related)
                            VALUES ('" . $ref . "','" .
                                   implode("'),('" . $ref . "','", $newrelated) .
                                   "')");
        }
    }
    return true;
}

/**
 * Check if sharing of resource is permitted
 *
 * @param  int  $ref      ID of resource
 * @param  int  $access   Level of resource access  (0 - Open  1 - Restricted  2 - Confidential)
 *
 * @return boolean
 */
function can_share_resource($ref, $access = "")
{
    global $allow_share, $restricted_share, $customgroupaccess,$customuseraccess, $allow_custom_access_share;
    if ($access == "" || !isset($customgroupaccess)) {
        $access = get_resource_access($ref);
    }

    if (!$allow_share || $access == 2 || ($access == 1 && !$restricted_share)) {
        return false;
    } // return false asap

    if ($restricted_share) {
        return true;
    } // If sharing of restricted resources is permitted we should allow sharing whether access is open or restricted

    // User is not permitted to share if open access has been specifically granted for an otherwise restrcited resource to the user/group.
    if (!$allow_custom_access_share && ($customgroupaccess || $customuseraccess)) {
        return false;
    }

    // Must have open access and sharing is permitted
    return true;
}

/**
* Delete all usergroup specific access to resource $ref
*
* @param  int  $ref   ID of resource
*
*/
function delete_resource_custom_access_usergroups($ref)
{
    ps_query("delete from resource_custom_access where resource=? and usergroup is not null", array("i",$ref));
}

/**
* Truncate the field for insertion into the main resource table field
*
* @param string $value
*
* @return string
*/
function truncate_join_field_value($value)
{
    global $resource_field_column_limit, $server_charset;

    $encoding = 'UTF-8';

    if (isset($server_charset) && '' != $server_charset) {
        $encoding = $server_charset;
    }

    $truncated_value = mb_substr($value, 0, $resource_field_column_limit, $encoding);

    if ($resource_field_column_limit >= strlen($truncated_value)) {
        return $truncated_value;
    }

    $more_limit = $resource_field_column_limit;
    while ($resource_field_column_limit < strlen($truncated_value)) {
        $truncated_value = mb_substr($value, 0, --$more_limit, $encoding);
    }

    return $truncated_value;
}

/**
* Check whether a resource (of a video type) has any snapshots created.
* Snapshots are being created using config option $ffmpeg_snapshot_frames
*
* @uses get_resource_path()
*
*                                                                     as expected by generateURL()
*
* @param integer $resource_id Resource unique ref
* @param boolean $file_path   Specify whether the return value should be the file path. Default is FALSE
* @param boolean $count_only  Set to true if we are only interested in how many snapshots we have. Default is FALSE
*
* @return array|integer Array of all file paths found or number of files found
*/
function get_video_snapshots($resource_id, $file_path = false, $count_only = false, $includemodified = false)
{
    global $get_resource_path_extra_download_query_string_params, $hide_real_filepath;

    $snapshots_found = array();

    $template_path            = get_resource_path($resource_id, true, 'snapshot', false, 'jpg', -1, 1, false, '');
    $template_webpath         = get_resource_path($resource_id, false, 'snapshot', false, 'jpg', -1, 1, false, '');

    $i = 1;
    do {
        $path = str_replace("snapshot", "snapshot_" . $i, $template_path);
        if ($hide_real_filepath) {
            $webpath = $template_webpath . "&snapshot_frame=" . $i;
        } else {
            $webpath = str_replace("snapshot", "snapshot_" . $i, $template_webpath);
            if ($includemodified && file_exists($path)) {
                $webpath .= "?v=" . urlencode(filemtime($path));
            }
        }

        $snapshot_found  = file_exists($path);

        if ($snapshot_found) {
            $snapshots_found[$i] = ($file_path ? $path : $webpath);
        }

        $i++;
    } while (true === $snapshot_found);

    return !$count_only ? $snapshots_found : count($snapshots_found);
}

/**
 * Check if resource file is read-only because it's part of the filestore template threshold
 * @param int|numeric-string $ref Resource ID
 */
function resource_file_readonly($ref): bool
{
    global $fstemplate_alt_threshold;
    return $fstemplate_alt_threshold > 0 && $ref < $fstemplate_alt_threshold;
}

function delete_resource_custom_user_access($resource, $user)
{
    ps_query("delete from resource_custom_access where resource=? and user=?", array("i",$resource,"i",$user));
}

function get_video_info($file)
{
    $ffprobe_fullpath = get_utility_path("ffprobe");
    $ffprobe_output = run_command($ffprobe_fullpath . " -v 0 " . escapeshellarg($file) . " -show_streams -of json");
    return json_decode($ffprobe_output, true);
}

/**
* Provides the ability to copy any metadata field data from one resource to another.
*
* @param integer $from Resource we are copying data from
* @param integer $to   The Resource ID that needs updating
*
* @return boolean
*/
function copyAllDataToResource($from, $to, $resourcedata = false)
{
    global $userref;

    if ((int)(string)$from !== (int)$from || (int)(string)$to !== (int)$to) {
        return false;
    }

    if (!$resourcedata) {
        $resourcedata = get_resource_data($to);
    }

    # Permission check isn't required if copying data from the user's upload template as with edit then upload mode.
    if (
        $from != 0 - $userref
        && !get_edit_access($to, $resourcedata["archive"], $resourcedata)
    ) {
            return false;
    }

    copy_resource_nodes($from, $to);

    # Update 'joined' fields in resource table
    $joins = get_resource_table_joins();
    $joinsql = "UPDATE resource AS target LEFT JOIN resource AS source ON source.ref=? SET ";
    $joinfields = "";
    foreach ($joins as $joinfield) {
        if ($joinfields != "") {
            $joinfields .= ",";
        }
        $joinfield = (int)$joinfield; // Ensure integer for inclusion in SQL.
        $joinfields .= "target.field{$joinfield} = source.field{$joinfield}";
    }
    $joinsql = $joinsql . $joinfields . " WHERE target.ref=?";
    ps_query($joinsql, array("i",$from,"i",$to));
    return true;
}

/**
* Update resource data for 'locked' fields from last edited resource. Used for upload_then_edit
*
* @uses get_resource_data()
* @uses update_resource_type()
* @uses update_archive_status()
* @uses resource_log()
* @uses checkperm()
* @uses ps_query()
* @uses checkperm()
*
* @param array $resource - existing resource data
* @param array $locked_fields - array of locked data columns (may also include field ids which are handled by copy_locked_fields)
* @param integer $lastedited   - last edited resource to copy data from
* @param boolean $save - if true, save data to database (as opposed to just updating the $resource array e.g. for edit page)
*
* @return array $resource - modified resource data array
*/
function copy_locked_data($resource, $locked_fields, $lastedited, $save = false)
{
    debug_function_call(__FUNCTION__, [$resource['ref'], $locked_fields, $lastedited, $save]);
    global $custom_access;

    // Get details of the last resource edited and use these for this resource if field is 'locked'
    $lastresource = get_resource_data($lastedited, false);

    if (in_array("resource_type", $locked_fields) && $resource["resource_type"] != $lastresource["resource_type"]) {
        if (!checkperm("XU" . $lastresource["resource_type"])) {
            update_resource_type($resource["ref"], $lastresource["resource_type"]);
            apply_resource_default((int) $resource["resource_type"], (int) $lastresource["resource_type"], (int) $resource["ref"]);
        }
        $resource["resource_type"] = $lastresource["resource_type"];
    }

    if (in_array("archive", $locked_fields) && $resource["archive"] != $lastresource["archive"]) {
        $resource["archive"] = $lastresource["archive"];
        if ($save && checkperm("e" . $lastresource["archive"])) {
            update_archive_status($resource["ref"], $lastresource["archive"], $resource["archive"]);
        }
    }

    if (in_array("access", $locked_fields) && $resource["access"] != $lastresource["access"]) {
        $newaccess = $lastresource["access"];
        if ($save) {
            $ea[0] = !checkperm('ea0');
            $ea[1] = !checkperm('ea1');
            $ea[2] = checkperm("v") ? (!checkperm('ea2') ? true : false) : false;
            $ea[3] = $custom_access ? !checkperm('ea3') : false;
            if ($ea[$newaccess]) {
                ps_query("update resource set access=? where ref=?", array("i",$newaccess,"i",$resource["ref"]));

                if ($newaccess == 3) {
                        # Copy custom access
                        ps_query("insert into resource_custom_access (resource,usergroup,user,access) select ?, usergroup,user,access from resource_custom_access where resource = ?", array("i",$resource["ref"],"i",$lastresource["ref"]));
                }
                resource_log($resource["ref"], LOG_CODE_ACCESS_CHANGED, 0, "", $resource["access"], $newaccess);
            }
        }
        $resource["access"] = $newaccess;
    }

    return $resource;
}

/**
* Update resource metadata for 'locked' fields from last edited resource.
* NB: $fields and $all_selected_nodes are passed by reference
*
* @uses get_resource_type_field()
* @uses get_resource_nodes()
* @uses add_resource_nodes()
* @uses delete_resource_nodes()*
* @uses get_resource_field_data()
* @uses update_field()
* @uses ps_query()
*
* @param integer $ref - resource id being updated
* @param array $fields - resource $fields array
* @param array $all_selected_nodes - array of existing resource nodes
* @param array $locked_fields - array of locked data columns (may also include  resource table columns  - handled by copy_locked_data)
* @param integer $lastedited   - last edited resource to copy data from
* @param boolean $save - save data to database (as opposed to just updating the $fields array e.g. for edit page)
*
* @return void
*/
function copy_locked_fields($ref, &$fields, &$all_selected_nodes, $locked_fields, $lastedited, $save = false)
{
    debug("copy_locked_fields resource " . $ref . " lastedited: " . $lastedited);
    global $FIXED_LIST_FIELD_TYPES, $tabs_on_edit;
    foreach ($locked_fields as $locked_field) {
        if (!is_numeric($locked_field)) {
            // These are handled by copy_locked_data
            continue;
        }

            // Check if this field is listed in the $fields array - if resource type has changed it may not be present
            $key = array_search($locked_field, array_column($fields, 'ref'));
        if ($key !== false) {
            $fieldtype = $fields[$key]["type"];
        } else {
            $lockfieldinfo = get_resource_type_field($locked_field);
            $fieldtype = $lockfieldinfo["type"];
        }

        if (in_array($fieldtype, $FIXED_LIST_FIELD_TYPES)) {
            // Replace nodes for this field
            $field_nodes = get_nodes($locked_field, null, $fieldtype == FIELD_TYPE_CATEGORY_TREE);
            $field_node_refs = array_column($field_nodes, "ref");
            $stripped_nodes = array_diff($all_selected_nodes, $field_node_refs);
            $locked_nodes = get_resource_nodes($lastedited, $locked_field);
            $all_selected_nodes = array_merge($stripped_nodes, $locked_nodes);

            if ($save) {
                debug("- adding locked field nodes for resource " . $ref . ", field id: " . $locked_field);
                delete_resource_nodes($ref, $field_node_refs, false);
                if (count($locked_nodes) > 0) {
                    add_resource_nodes($ref, $locked_nodes, false);
                }

                # If this is a 'joined' field it still needs to add it to the resource column
                $joins = get_resource_table_joins();
                if (in_array($locked_field, $joins)) {
                    $node_vals = array();
                    // Build new value:
                    foreach ($locked_nodes as $locked_node) {
                        foreach ($field_nodes as $key => $val) {
                            if ($val['ref'] === $locked_node) {
                                array_push($node_vals, $field_nodes[$key]["name"]);
                            }
                        }
                        $resource_type_field = $field_nodes[$key]["resource_type_field"];
                        $values_string = implode($GLOBALS['field_column_string_separator'], $node_vals);
                        update_resource_field_column($ref, $resource_type_field, $values_string);
                    }
                }
            }
        } else {
            debug(" - checking field values for last resource " . $lastedited . " field id: " . $locked_field);
            if (!isset($last_fields)) {
                $last_fields = get_resource_field_data($lastedited, !hook("customgetresourceperms"), null, "", $tabs_on_edit);
            }

            $addkey = array_search($locked_field, array_column($last_fields, 'ref'));
            if ($key !== false) {
                // Field is already present - just update the value
                debug(" - updating field value for resource " . $lastedited . " field id: " . $locked_field);
                $fields[$key]["value"] = $last_fields[$addkey]["value"];
            } else {
                // Add the field to the $fields array
                debug(" - adding field value for resource " . $lastedited . " field id:" . $locked_field);
                $fields[] = $last_fields[$addkey];
            }
            if ($save) {
                debug("- adding locked field value for resource " . $ref . ", field id: " . $locked_field);
                update_field($ref, $locked_field, $last_fields[$addkey]["value"]);
            }
        }
    }
}

/**
* Copy  related resources from one resource to another
*
* @uses ps_query()
*
* @param integer $from Resource we are copying related resources from
* @param integer $ref  Resource we are copying related resources to
*
* @return void
*/
function copyRelatedResources($from, $to)
{
    ps_query("insert into resource_related(resource,related) SELECT ?,related FROM resource_related WHERE resource=? AND related <> ?", array("i",$to,"i",$from,"i",$to));
}

function process_edit_form($ref, $resource)
{
    global $multiple, $lang, $embedded_data_user_select, $embedded_data_user_select_fields, $data_only_resource_types,
    $check_edit_checksums, $uploadparams, $relate_on_upload, $enable_related_resources,
    $is_template, $upload_collection_name_required, $upload_review_mode, $userref, $userref, $collection_add, $baseurl_short,
    $no_exif, $autorotate;

    # save data
    # When auto saving, pass forward the field so only this is saved.
    $autosave_field = getval("autosave_field", "");

    # Upload template: Change resource type
    $resource_type = getval("resource_type", "");
    if ($resource_type != "" && $resource_type != $resource["resource_type"] && !checkperm("XU{$resource_type}") && $autosave_field == "") {     // only if resource type specified and user has permission for that resource type
        // Check if resource type has been changed between form being loaded and submitted
        $post_cs = getval("resource_type_checksum", "");
        $current_cs = $resource["resource_type"];
        if ($check_edit_checksums && $post_cs != "" && $post_cs != $current_cs) {
            $save_errors = array("resource_type" => $lang["resourcetype"] . ": " . $lang["save-conflict-error"]);
        } else {
            update_resource_type($ref, $resource_type);
            $previous_resource_type = $resource["resource_type"];
        }
    }
    $resource = get_resource_data($ref, false); # Reload resource data.

    if (!in_array($resource['resource_type'], $data_only_resource_types)) {
        unset($uploadparams['forcesingle']);
        unset($uploadparams['noupload']);
    }
    if (!isset($save_errors)) {
        # Perform the save
        $save_errors = save_resource_data($ref, $multiple, $autosave_field);
    }

    if (isset($previous_resource_type) && !$multiple && $upload_review_mode) {
        apply_resource_default((int) $previous_resource_type, (int) $resource_type, (int) $ref);
    }

    if ($relate_on_upload && $enable_related_resources && getval("relateonupload", "") != "") {
        $uploadparams['relateonupload'] = 'yes';
    }

    if (
        $upload_collection_name_required
        && getval("entercolname", "") == ""
        && getval("collection_add", "") == "new"
    ) {
        if (!is_array($save_errors)) {
            $save_errors = array();
        }
            $save_errors['collectionname'] = $lang["collectionname"] . ": " . $lang["requiredfield"];
    }

    return $save_errors;
}

/*
* Update the modified column in the resource table
*
* @param integer $resource      Resource to be updated
*
* @return void
*/
function update_timestamp($resource)
{
    if (!is_numeric($resource)) {
        return false;
    }
    ps_query("UPDATE resource SET modified=NOW() WHERE ref=?", array("i",$resource));
}

/**
* Get resource file extension from the database or use JPG, for download
*
* @uses hook()
*
* @param array  $resource
* @param string $size      Preview size as defined in the system
*
* @return string
*/
function get_extension(array $resource, $size)
{
    global $job_ext;
    if ($size == '') {
        $size = 'original';
    }

    // Offline collection download job may have requested a specific file extension
    $pextension = $size == 'original' ? $resource['file_extension'] : ((isset($job_ext) && trim($job_ext) != "") ? $job_ext : 'jpg');

    $replace_extension = hook('replacedownloadextension', '', array($resource, $pextension));
    if (trim($replace_extension) !== '') {
        return $replace_extension;
    }

    return $pextension;
}

/**
* Obtain details of the last resource edited in the given array of resource ids
*
* @param array $resources   Array of resource IDs
*
* @return array | false     Array containing details of last edit (resource ID, timestamp and username of user who performed edit)
*/
function get_last_resource_edit_array($resources = array())
{
    if (count($resources) == 0) {
        return false;
    }

    $plugin_last_resource_edit = hook('override_last_resource_edit_array');
    if ($plugin_last_resource_edit === true) {
        return false;
    }

    $chunks = array_chunk($resources, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    foreach ($chunks as $chunk) {
        $rows  = ps_query(
            "SELECT r.ref, r.modified 
                FROM resource r 
            WHERE r.ref IN (" . ps_param_insert(count($chunk)) . ") 
            ORDER BY r.modified DESC",
            ps_param_fill($chunk, "i")
        );
        if (!isset($lastmodified) || $rows[0]["modified"] > $lastmodified["modified"]) {
            $lastmodified = $rows[0];
        }
    }
    $lastuserdetails = ps_query("SELECT u.username, u.fullname, rl.date FROM resource_log rl LEFT JOIN user u on u.ref=rl.user WHERE rl.resource = ? AND rl.type='e' ORDER BY rl.date DESC", array("i",$lastmodified["ref"]));
    if (count($lastuserdetails) == 0) {
        return false;
    }

    $timestamp = max($lastuserdetails[0]["date"], $lastmodified["modified"]);

    $lastusername = (trim((string)$lastuserdetails[0]["fullname"]) != "") ? $lastuserdetails[0]["fullname"] : $lastuserdetails[0]["username"];
    return array("ref" => $lastmodified["ref"],"time" => $timestamp, "user" => $lastusername);
}

/**
* Get the default archive state for new resources
*
* @param integer    $requestedstate     (optional) ID of requested archive state
*
* @return integer   ID of valid user requested archive state, may differ from that requested
*/
function get_default_archive_state($requestedstate = "")
{
    global $override_status_default;

    if ((string)(int)$requestedstate == (string)$requestedstate && checkperm("e" . $requestedstate)) {
        return $requestedstate;
    }

    $modified_defaultstatus = hook("modifydefaultstatusmode");
    if ($modified_defaultstatus !== false) {
        # Set the modified default status
        return $modified_defaultstatus;
    } elseif ($override_status_default !== false) {
        # Set the default status if set in config.
        return $override_status_default;
    } elseif (checkperm("c") && checkperm("e0")) {
        # Set status to Active
        return 0;
    } elseif (checkperm("d") && !checkperm('e-2') && checkperm('e-1')) {
        # Set status to 'pending review' if the user has only edit access to Pending review
        return -1;
    } else {
        return -2;
    }
}

/**
* Save the original file being replaced, as an alternative file
*
* @param integer    $ref      (required) ID of original resource
* @return boolean | int       int = id of new alternative file; false = file not saved
*/

function save_original_file_as_alternative($ref)
{
    debug("save_original_file function called for resource ref: " . (int)$ref);
    if (!$ref) {
        debug("ERROR: Unable to save original file as alternative - no resource id passed");
        return false;
    }

    /*
    global vars
    * @param boolean $alternative_file_previews                  Generate thumbs/previews for alternative files?
    * @param boolean $alternative_file_previews_batch            Generate thumbs/previews for alternative files?
    * @param array   $lang
    */

    global $lang, $alternative_file_previews, $alternative_file_previews_batch, $filename_field;

    // Values may be passed in POST or GET data from upload_batch.php
    $replace_resource_original_alt_filename = getval('replace_resource_original_alt_filename', ''); // alternative filename
    $filename_field_use                     = getval('filename_field', $filename_field); // GET variable - field to use for filename

    // Make the original into an alternative, need resource data so we can get filepath/extension
    $origdata     = get_resource_data($ref);
    $origpath = get_resource_path($ref, true, "", true, $origdata["file_extension"]);
    # It's possible that there is no original in the filestore; quit if this is the case
    if (!file_exists($origpath)) {
        debug("ERROR: Unable to find original file to save as alternative: " . $origpath);
        return false;
    }

    $origfilename = get_data_by_field($ref, $filename_field_use);

    $newaltname        = str_replace('%EXTENSION', strtoupper($origdata['file_extension']), $lang['replace_resource_original_description']);
    $newaltdescription = nicedate(date('Y-m-d H:i'), true);

    if ('' != $replace_resource_original_alt_filename) {
        $newaltname = $replace_resource_original_alt_filename;
    }

    $newaref = add_alternative_file($ref, $newaltname, $newaltdescription, $origfilename, $origdata['file_extension'], $origdata['file_size']);

    $newaltpath = get_resource_path($ref, true, "", true, $origdata["file_extension"], -1, 1, false, "", $newaref);
    # Move the old file to the alternative file location
    if (!hook('save_original_alternative_extra', '', array('origpath' => $origpath, 'newaltpath' => $newaltpath))) {
        rename($origpath, $newaltpath);
    }

    if ($alternative_file_previews) {
        // Move the old previews to new paths
        $ps = ps_query("SELECT " . columns_in("preview_size")  . " FROM preview_size", [], "schema");
        for ($n = 0; $n < count($ps); $n++) {
            # Find the original
            $orig_preview_path = get_resource_path($ref, true, $ps[$n]["id"], false, "");
            if (file_exists($orig_preview_path)) {
                # Copy the old preview file to the alternative preview file location, not moved as original may still be required
                $alt_preview_path = get_resource_path($ref, true, $ps[$n]["id"], true, "", -1, 1, false, "", $newaref);
                copy($orig_preview_path, $alt_preview_path);
            }
            # Also for the watermarked versions.
            $wmpath = get_resource_path($ref, true, $ps[$n]["id"], false, "jpg", -1, 1, true);
            if (file_exists($wmpath)) {
                # Move the old preview file to the alternative preview file location
                $alt_preview_wmpath = get_resource_path($ref, true, $ps[$n]["id"], true, "", -1, 1, true, "", $newaref);
                copy($wmpath, $alt_preview_wmpath);
            }
        }
    }
    debug("save_original_file_as_alternative() completed");
    return $newaref;
}

/**
* Replace the primary resource file with the file located at the path specified
*
* @param integer    $ref    Resource ID to replace
*
* @return boolean
*/

function replace_resource_file($ref, $file_location, $no_exif = false, $autorotate = false, $keep_original = true)
{
    global $replace_resource_preserve_option, $notify_on_resource_change_days, $lang, $userref;
    debug("replace_resource_file(ref=" . $ref . ", file_location=" . $file_location . ", no_exif=" . ($no_exif ? "TRUE" : "FALSE") . " , keep_original=" . ($keep_original ? "TRUE" : "FALSE"));

    $ref = (int) $ref;
    # FStemplate support - do not allow samples from the template to be replaced
    if (resource_file_readonly($ref)) {
        return false;
    }

    $resource = get_resource_data($ref);
    if (
        !get_edit_access($ref, $resource["archive"], $resource)
        ||
        ($resource["lock_user"] > 0 && $resource["lock_user"] != $userref)
    ) {
        return false;
    }

    // save original file as an alternative file
    if ($replace_resource_preserve_option && $keep_original) {
        // the following save may not succeed because there is no original in which case a debug log will have been created
        // allow replace resource to continue with its principal task of uploading
        save_original_file_as_alternative($ref);
    }

    if (filter_var($file_location, FILTER_VALIDATE_URL)) {
        $uploadstatus = upload_file_by_url($ref, $no_exif, false, $autorotate, $file_location);
        if (!$uploadstatus) {
            debug("replace_resource_file - upload_file_by_url() failed");
            return false;
        }
    } else {
        $uploadstatus = upload_file($ref, $no_exif, false, $autorotate, $file_location, false, false);
        if (!$uploadstatus) {
            debug("replace_resource_file - upload_file() failed");
            return false;
        }
    }

    hook('replace_resource_file_extra', '', array($resource));
    $log_ref = resource_log($ref, LOG_CODE_REPLACED, '', '', '');
    daily_stat('Resource upload', $ref);
    hook("additional_replace_existing", "", array($ref,$log_ref));

    if ($notify_on_resource_change_days != 0) {
        // we don't need to wait for this.
        ob_flush();
        flush();
        notify_resource_change($ref);
    }

    return true;
}

/**
* Return all sizes available for a specific resource. Multi page resources should have each page size included as well
* in the output.
*
* @uses get_resource_access()
* @uses get_resource_data()
* @uses get_image_sizes()
* @uses get_page_count()
* @uses get_resource_path()
*
* @param integer $ref Resource ID
*
* @return array
*/
function get_resource_all_image_sizes($ref)
{
    if (get_resource_access($ref) !== 0) {
        return array();
    }

    $resource_data = get_resource_data($ref, true);
    if ($resource_data["file_extension"] == "" || $resource_data["preview_extension"] == "") {
        return array();
    }

    $extensions = array($resource_data["file_extension"], $resource_data["preview_extension"]);
    $all_image_sizes = array();

    foreach ($extensions as $extension) {
        $available_sizes_by_extension = get_image_sizes($ref, true, $extension, true);

        foreach ($available_sizes_by_extension as $size_data) {
            $size_id = trim($size_data["id"]) === "" ? "original" : $size_data["id"];

            if (array_key_exists("{$size_id}_{$size_data["extension"]}", $all_image_sizes)) {
                continue;
            }

            $key = "{$size_id}_{$size_data["extension"]}";
            $all_image_sizes[$key]["size_code"] = $size_id;
            $all_image_sizes[$key]["extension"] = $size_data["extension"];
            $all_image_sizes[$key]["path"] = $size_data["path"];
            $all_image_sizes[$key]["url"] = $size_data["url"];
            $all_image_sizes[$key]["width"] = $size_data["width"];
            $all_image_sizes[$key]["height"] = $size_data["height"];
            $all_image_sizes[$key]["filesize"] = $size_data["filesize"];

            // Screen size can have multi page previews so if this is one of those cases, get rest of the pages before
            // moving on to the next available size
            if ($size_id == "scr" && ($page_count = get_page_count($resource_data)) && $page_count > 1) {
                // First page is always the normal scr size preview, so just tag it as such.
                $all_image_sizes[$key]["multi_page"] = true;
                $all_image_sizes[$key]["page"] = 1;

                for ($page = 2; $page <= $page_count; $page++) {
                    $path = get_resource_path($ref, true, "scr", false, $extension, true, $page);
                    if (!file_exists($path)) {
                        continue;
                    }

                    $url = get_resource_path($ref, false, "scr", false, $extension, true, $page);

                    $key = "{$size_id}_{$size_data["extension"]}_{$page}";
                    $all_image_sizes[$key]["size_code"] = $size_id;
                    $all_image_sizes[$key]["extension"] = $size_data["extension"];
                    $all_image_sizes[$key]["multi_page"] = true;
                    $all_image_sizes[$key]["page"] = $page;
                    $all_image_sizes[$key]["url"] = $url;
                }
            }
        }
    }

    return array_values($all_image_sizes);
}

function sanitize_date_field_input($date, $validate = false)
{
    $year_input = getval("field_" . $date . "-y", "");
    $year = sprintf("%04d", $year_input);  // Assume CE year
    if (strlen($year_input) == 5) {
        $year = sprintf("%05d", $year_input);  // BCE year has leading -
    }
    $month  = getval("field_" . $date . "-m", "");
    $day    = getval("field_" . $date . "-d", "");
    $hour   = getval("field_" . $date . "-h", "");
    $minute = getval("field_" . $date . "-i", "");

    // Construct value, replacing missing parts with placeholders
    $val  = ($year != "" && $year != "0000") ? $year : "year";
    $val .= "-" . ($month != "" ? $month : "month");
    $val .= "-" . ($day != "" ? $day : "day");
    $val .= " " . ($hour != "" ? $hour : "hh");
    $val .= ":" . ($minute != "" ? $minute : "mm");
    if ($validate) {
        # Format dates for the date validator e.g. 2020, 2020-month-29 by stripping unused placeholders
        $removedates = array("year-month-day","-month-day","-day"," hh:mm");
        $val = str_replace($removedates, "", $val);
    } else {
        # Format for database entry e.g. 2020-00-00, 2020-00-29, if nothing is set replace with a null string
        $removedates = array("year-month-day hh:mm","year","month","day"," hh:mm","hh","mm");
        $subdates = array("","0000","00","00","","00","00");
        $val = str_replace($removedates, $subdates, $val);
    }

    return $val;
}

/**
* Create a temporary download key for a specific user or key and resource combination
* Used when both $watermark_open and $terms_download are enabled
*
* @param string $id                 Key identifier e.g. user ID or external access key
* @param integer $resource          Resource ID
*
* @return string
*/
function download_link_generate_key($id, $resource)
{
    global $scramble_key, $usersession;
    $remote_ip = get_ip();
    return $id . ":" . hash('sha256', $id . $usersession . $scramble_key . $resource . $remote_ip);
}

/**
* Check the download key for a specific user/resource combination
*
* @param string  $download_key      Download key
* @param integer $resource          Resource ID
*
* @return string
*/
function download_link_check_key($download_key, $resource)
{
    $download_link_parts = explode(":", $download_key);

    if (count($download_link_parts) != 2) {
        return false;
    }

    $download_link_id   = $download_link_parts[0];
    $keycheck = download_link_generate_key($download_link_id, $resource);
    if ($keycheck != $download_key) {
        return false;
    }

    return true;
}

/**
* Check if a given set of nodes meets the conditions set for the provided filter
* NOte that all resource_nodes for a resource should be passed to check if a filter is matched
*
* @param integer    $ref        Filter ID
* @param array      $nodes      Array of nodes
*
* @return boolean
*/
function filter_check($filterid, $nodes)
{
    $filterdata         = get_filter($filterid);
    $filterrules        = get_filter_rules($filterid);
    $filtercondition    = $filterdata["filter_condition"];

    // Used for RS_FILTER_ALL type
    $filtersfailed  = 0;
    $filtersok      = 0;

    foreach ($filterrules as $filterrule) {
        // Check if any nodes are present that shouldn't be, or nodes not present that need to be
        $badnodes   = array_intersect($filterrule["nodes_off"], $nodes);
        $goodnodes  = array_intersect($filterrule["nodes_on"], $nodes);
        $rulemet    = count($badnodes) == 0 && (count($filterrule["nodes_on"]) == 0 || count($goodnodes) > 0);
        // Can return now if filter successfully matched and RS_FILTER_ANY or RS_FILTER_NONE,
        // or if filter not matched and RS_FILTER_ALL
        if ($rulemet) {
            if ($filtercondition === RS_FILTER_ANY) {
                return true;
            } elseif ($filtercondition === RS_FILTER_NONE) {
                return false;
            }
            $filtersok++;
        } else {
            if ($filtercondition === RS_FILTER_ALL) {
                return false;
            }
            $filtersfailed++;
        }
        // Need to check subsequent rules if RS_FILTER_ALL and filter rule met
    }

    if (
        ($filtercondition == RS_FILTER_ALL && $filtersfailed == 0 && $filtersok == count($filterrules))
        || ($filtercondition == RS_FILTER_NONE && $filtersfailed == count($filterrules) && $filtersok == 0)
    ) {
        return true;
    }

    return false;
}

function update_node_hitcount_from_search($resource, $search)
{
    // For the specified $resource, increment the hitcount for each node that has been used in $search
    // This is done into a temporary column first (new_hit_count) so existing results are not affected.
    // copy_hitcount_to_live() is then executed at a set interval to make this data live.
    // Note that from v10 the resource_keyword table is no longer used
    $nodes = [];
    $notsearchednodes = [];
    resolve_given_nodes($search, $nodes, $notsearchednodes);
    if (count($nodes) > 0) {
        update_resource_node_hitcount($resource, array_column($nodes, 0));
    }
}

function copy_hitcount_to_live()
{
    # Also update the resource table
    # greatest() is used so the value is taken from the hit_count column in the event that new_hit_count is zero to support installations that did not previously have a new_hit_count column (i.e. upgrade compatability)
    ps_query("update resource set hit_count=greatest(hit_count,new_hit_count)");

    # Also now update resource_node_hitcount())
    ps_query("update resource_node set hit_count=new_hit_count");
}

/**
 * Returns a table of available image sizes for resource $ref. The standard image sizes are translated using $lang. Custom image sizes are i18n translated.
 * The original image file assumes the name of the 'nearest size (up)' in the table
 *
 * @param  int      $ref            ID of resource
 * @param  boolean  $internal
 * @param  string   $extension      File extension of image
 * @param  boolean  $onlyifexists
 *
 * @return array    $return
 */
function get_image_sizes(int $ref, $internal = false, $extension = "jpg", $onlyifexists = true)
{
    global $imagemagick_calculate_sizes;

    # Work out resource type
    $resource_data = get_resource_data($ref);
    $resource_type = $resource_data["resource_type"];

    # add the original image
    $return = array();
    $lastname = ps_value("SELECT name value FROM preview_size WHERE width=(SELECT max(width) FROM preview_size)", [], "schema"); # Start with the highest resolution.
    $lastpreview = 0;
    $lastrestricted = 0;
    $path2 = get_resource_path($ref, true, '', false, $extension);

    if (!resource_has_access_denied_by_RT_size($resource_type, '') && (file_exists($path2) || !$onlyifexists)) {
        $returnline = array();
        $returnline["name"] = lang_or_i18n_get_translated($lastname, "imagesize-");
        $returnline["allow_preview"] = $lastpreview;
        $returnline["allow_restricted"] = $lastrestricted;
        $returnline["path"] = $path2;
        $returnline["url"] = get_resource_path($ref, false, "", false, $extension);
        $returnline["id"] = "";
        $returnline["original"] = 1;
        $dimensions = ps_query("select width,height,file_size,resolution,unit from resource_dimensions where resource=?", array("i",$ref));

        if (count($dimensions)) {
            $sw = $dimensions[0]['width'];
            if ($sw == 0) {
                $sw = "?";
            }
            $sh = $dimensions[0]['height'];
            if ($sh == 0) {
                $sh = "?";
            }
            $filesize = $dimensions[0]['file_size'];
            # resolution and unit are not necessarily available, set to empty string if so.
            $resolution = ($dimensions[0]['resolution']) ? $dimensions[0]['resolution'] : "";
            $unit = ($dimensions[0]['unit']) ? $dimensions[0]['unit'] : "";
        } else {
            $fileinfo = get_original_imagesize($ref, $path2, $extension);
            if ($fileinfo !== false) {
                $filesize = $fileinfo[0];
                $sw = $fileinfo[1];
                $sh = $fileinfo[2];
            } else {
                $filesize = $resource_data["file_size"];
                $sw = 0;
                $sh = 0;
            }
        }
        if (!is_numeric($filesize)) {
            $returnline["filesize"] = "?";
            $returnline["filedown"] = "?";
        } else {
            $returnline["filedown"] = ceil($filesize / 50000) . " seconds @ broadband";
            $returnline["filesize"] = formatfilesize($filesize);
        }
        $returnline["width"] = $sw;
        $returnline["height"] = $sh;
        $returnline["extension"] = $extension;
        (isset($resolution)) ? $returnline["resolution"] = $resolution : $returnline["resolution"] = "";
        (isset($unit)) ? $returnline["unit"] = $unit : $returnline["unit"] = "";
        $return[] = $returnline;
    }
    # loop through all image sizes
    $sizes = ps_query("SELECT " . columns_in("preview_size") . " FROM preview_size ORDER BY width DESC", [], "schema");

    for ($n = 0; $n < count($sizes); $n++) {
        $path = get_resource_path($ref, true, $sizes[$n]["id"], false, "jpg");
        $file_exists = file_exists($path);

        if (
            ($file_exists || !$onlyifexists)
            && !resource_has_access_denied_by_RT_size($resource_type, $sizes[$n]['id'])
            && ($sizes[$n]["internal"] == 0 || $internal)
        ) {
                $returnline = array();
                $returnline["name"] = lang_or_i18n_get_translated($sizes[$n]["name"], "imagesize-");
                $returnline["original"] = 0;
                $returnline["allow_preview"] = $sizes[$n]["allow_preview"];

            if (isset($resolution)) {
                $returnline["resolution"] = $resolution;
            }

                # The ability to restrict download size by user group and resource type.
            if (checkperm("X" . $resource_type . "_" . $sizes[$n]["id"])) {
                # Permission set. Always restrict this download if this resource is restricted.
                $returnline["allow_restricted"] = false;
            } else {
                # Take the restriction from the settings for this download size.
                $returnline["allow_restricted"] = $sizes[$n]["allow_restricted"];
            }
                $returnline["path"] = $path;
                $returnline["url"] = get_resource_path($ref, false, $sizes[$n]["id"], false, "jpg");
                $returnline["id"] = $sizes[$n]["id"];
            if ($file_exists && filesize_unlimited($path) > 0) {
                $filesize = filesize_unlimited($path);
                $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
                $GLOBALS["use_error_exception"] = true;
                try {
                    list($sw,$sh) = getimagesize($path);
                } catch (Exception $e) {
                    $sw = 0;
                    $sh = 0;
                }
                $GLOBALS["use_error_exception"] = $use_error_exception_cache;
            } else {
                $filesize = 0;
                $sw = 0;
                $sh = 0;
            }

            if ($filesize === false) {
                $returnline["filesize"] = "?";
                $returnline["filedown"] = "?";
            } else {
                $returnline["filedown"] = ceil($filesize / 50000) . " seconds @ broadband";
                $filesize = formatfilesize($filesize);
            }
                $returnline["filesize"] = $filesize;
                $returnline["width"] = $sw;
                $returnline["height"] = $sh;
                $returnline["extension"] = 'jpg';
                $return[] = $returnline;
        }
        $lastname = lang_or_i18n_get_translated($sizes[$n]["name"], "imagesize-");
        $lastpreview = $sizes[$n]["allow_preview"];
        $lastrestricted = $sizes[$n]["allow_restricted"];
    }
    return $return;
}

/**
* Return an array of resource references that are related to resource $ref
*
* @param  int  $ref   ID of resource
*
* @return array
*/
function get_related_resources($ref)
{
    return ps_array("select related value from resource_related where resource=? union select resource value from resource_related where related=?", array("i",$ref,"i",$ref));
}

/**
 * Get available options for fixed list field types
 *
 * @param int   $ref                    Metadata field ref
 * @param bool  $nodeinfo               Get full node details?
 * @param bool  $skip_translation       Do not translate node name. Only relevant if $nodeinfo=false
 *
 * @return array|bool Array of field options, either as a simple array or with full node details
 *
 */
function get_field_options(int $ref, bool $nodeinfo = false, bool $skip_translation = false)
{
    global $FIXED_LIST_FIELD_TYPES, $auto_order_checkbox,$auto_order_checkbox_case_insensitive;
    # For the field with reference $ref, return a sorted array of options. Optionally use the node IDs as array keys
    if (!is_int_loose($ref)) {
        return false;
    }

    $field = get_resource_type_field($ref);
    if (!in_array($field["type"], $FIXED_LIST_FIELD_TYPES)) {
        return false;
    }

    $options = get_nodes($ref, null, $field["type"] == FIELD_TYPE_CATEGORY_TREE);
    if ($options === false) {
        return false;
    }
    for ($m = 0; $m < count($options); $m++) {
        if (!$nodeinfo) {
            if ($field["type"] == FIELD_TYPE_CATEGORY_TREE) {
                $options[$m] = $skip_translation ? $options[$m]["path"] : $options[$m]["translated_path"];
            } else {
                $options[$m] = $skip_translation ? $options[$m]["name"] : $options[$m]["translated_name"];
            }
        } else {
            unset($options[$m]["resource_type_field"]); // Not needed
        }
    }

    if (!$nodeinfo && $field["type"] == FIELD_TYPE_CHECK_BOX_LIST && $auto_order_checkbox) {
        if ($auto_order_checkbox_case_insensitive) {
            natcasesort($options);
        } else {
            sort($options);
        }
    }

    return $options;
}

/**
* Get the resource data value for a field and a specific resource
* or get the specified field for all resources in the system
*
* @param integer        $resource Resource ID. Use NULL to retrieve all resources
*                                 records for the specified field
* @param integer|string $field    Resource type field ID. Can also be a shortname.
* @param bool           $flatten  Should a fixed list field value be flatten to a simple string? Set to FALSE to get the list of nodes
*
* @return string|array|Generator  Generator is returned for the old behaviour of returning field data for all resources
*                                 (for performance). Shouldn't be an issue as long as it's used in foreach loops
*/
function get_data_by_field($resource, $field, bool $flatten = true)
{
    global $get_data_by_field_fct_field_cache;

    $fetch_all_resources = is_null($resource);

    if (!isset($get_data_by_field_fct_field_cache[$field])) {
        $rtf_info = ps_query(
            'SELECT ref, type FROM resource_type_field WHERE ref = ? OR name = ?',
            ['i',$field, 's',$field],
            'schema'
        );

        if (empty($rtf_info)) {
            return $fetch_all_resources ? [] : '';
        }

        $get_data_by_field_fct_field_cache[$field] = $rtf_info[0];
    }

    $rtf_ref = $get_data_by_field_fct_field_cache[$field]['ref'];
    $rtf_type = $get_data_by_field_fct_field_cache[$field]['type'];

    if (!$fetch_all_resources && $rtf_type == FIELD_TYPE_CATEGORY_TREE) {
        $tree_nodes = get_resource_nodes($resource, $rtf_ref, true);
        return $flatten ? implode(', ', get_node_strings($tree_nodes, false)) : $tree_nodes;
    } elseif (!$fetch_all_resources) {
        $resource_data_for_field = get_resource_nodes($resource, $rtf_ref, true);
        return $flatten ? implode(', ', array_column($resource_data_for_field, 'name')) : $resource_data_for_field;
    }
    // Old behaviour from when we had resource_data (before r19945) - return the metadata field values for all resources
    elseif ($fetch_all_resources && in_array($rtf_type, NON_FIXED_LIST_SINGULAR_RESOURCE_VALUE_FIELD_TYPES)) {
        return get_resources_nodes_by_rtf($rtf_ref);
    }

    return '';
}

function get_all_image_sizes($internal = false, $restricted = false)
{
        # Returns all image sizes available.
        # Standard image sizes are translated using $lang.  Custom image sizes are i18n translated.

        $condition = ($internal) ? "" : "WHERE internal!=1";
    if ($restricted) {
        $condition .= ($condition != "" ? " AND " : " WHERE ") . " allow_restricted=1";
    }

        # Executes query.
        $r = ps_query("SELECT " . columns_in("preview_size") . " FROM preview_size " . $condition . " ORDER BY width ASC", [], "schema"); // $condition does not contain any user entered params and is safe for inclusion

        # Translates image sizes in the newly created array.
        $return = array();
    for ($n = 0; $n < count($r); $n++) {
        $r[$n]["name"] = lang_or_i18n_get_translated($r[$n]["name"], "imagesize-");
        $return[] = $r[$n];
    }
        return $return;
}

function image_size_restricted_access($id)
{
    # Returns true if the indicated size is allowed for a restricted user.
    return ps_value("SELECT allow_restricted value FROM preview_size WHERE id=?", array("s",$id), false, "schema");
}

/**
* Returns a list of fields with refs matching the supplied field refs.
*
* @param array $field_refs Array of field refs
* @param bool  order_by_passed_refs Returns the array of fields in the order requested in $field_refs
*
* @return array
*/
function get_fields($field_refs, bool $order_by_passed_refs = false)
{
    if (!is_array($field_refs)) {
        trigger_error("\$field_refs passed to get_fields() is not an array.");
    }

    if (count($field_refs) === 0) {
        return array();
    }

    $fields = ps_query("
        SELECT " . columns_in("resource_type_field", "rtf") . ", t.name AS tab_name
          FROM resource_type_field rtf
          LEFT JOIN tab t ON t.ref=rtf.tab          
         WHERE rtf.ref IN (" . ps_param_insert(count($field_refs)) . ")
      ORDER BY rtf.order_by", ps_param_fill($field_refs, "i"), "schema");

    $return = array();   

    if ($order_by_passed_refs) {

        $fields_array = array_column($fields, null, 'ref');

        foreach ($field_refs as $field_ref) {
            if (array_key_exists($field_ref, $fields_array) && metadata_field_view_access($field_ref)) {
                $return[] = $fields_array[$field_ref];
            }
        }

    } else {
        foreach ($fields as $field) {
            if (metadata_field_view_access($field['ref'])) {
                $return[] = $field;
            }
        }
    }

    return $return;
}

function get_hidden_indexed_fields()
{
    # Return an array of indexed fields to which the current user does not have access
    # Used by do_search to ommit fields when searching.
    $hidden = array();
    global $hidden_fields_cache;
    if (is_array($hidden_fields_cache)) {
        return $hidden_fields_cache;
    } else {
        $fields = ps_query("select ref,active from resource_type_field where length(name)>0", array(), "schema");
        # Apply field permissions
        for ($n = 0; $n < count($fields); $n++) {
            if ($fields[$n]["active"] == 1 && metadata_field_view_access($fields[$n]["ref"])) {
                # Visible field
            } else {
                # Hidden field
                $hidden[] = $fields[$n]["ref"];
            }
        }
        $hidden_fields_cache = $hidden;
        return $hidden;
    }
}

function get_OR_fields()
{
    # Returns a list of fields that should retain semicolon separation of keywords in a search string
    global $orfields_cache;
    if (is_array($orfields_cache)) {
        return $orfields_cache;
    } else {
        $fields = ps_query("select name from resource_type_field where type=7 or type=2 or type=3 and length(name)>0 order by order_by", array(), "schema");
        $orfields = array();
        foreach ($fields as $field) {
            $orfields[] = $field['name'];
        }
        $orfields_cache = $orfields;
        return $orfields;
    }
}

/**
* Returns the HTML necessary to represent a resource with the given extension when no image preview exists.
*
* @param string     $extension       File extension
* @param int        $resource_type   Optional, the resource type of the resource, used to find an icon at the resource type level if an icon for this extension is not defined.
*
* @return string
*/
function get_nopreview_html(string $extension, $resource_type = null): string
{
    $extension = strtolower(trim($extension));

    if (isset(LUCIDE_EXTENSIONS[$extension])) {
        // Use the icon set for this file extension
        $icon = LUCIDE_EXTENSIONS[$extension];
    } else {
        // No icon for this specific extension Get default for this resource type and cache it.
        $icon = ps_value("select icon value from resource_type where ref=?", ["i",$resource_type], "", "resourcetypeicon");
        if ($icon == "") {
            $default = LUCIDE_EXTENSIONS["default"];
        }
    }

    return "<i class='nopreview icon-" . $icon . "'></i>";
}

/**
* Gets all metadata fields, optionally for a specified array of resource types
*
* @param  array    $restypes           Optional array of resource types to check
* @param  string   $field_order_by     Order by column
* @param  string   $field_sort         Sort order
* @param  string   $find               Parameter value to search for
* @param  array    $fieldtypes         List of field types to include
* @param  boolean  $include_inactive   Should inactive resources be checked, default is false
*
* @return array
*/
function get_resource_type_fields($restypes = "", $field_order_by = "ref", $field_sort = "asc", $find = "", $fieldtypes = array(), $include_inactive = false)
{
    debug_function_call(__FUNCTION__, func_get_args());
    if ($field_order_by != "ref") {
        // Default order by is not being used so check order by columns supplied are valid for the table
        $fields = columns_in("resource_type_field", null, null, true);
        $fields[] = "tab_name";
        $order_by_cols = explode(',', $field_order_by);
        $valid_order_by_cols = array();
        foreach ($order_by_cols as $col) {
            if ($col == "resource_type") {
                // Now multiple mapping and resource_type no longer used. Need to reverse order and show global fields first
                $valid_order_by_cols[] = "global " . $field_sort . ", resource_types";
            } elseif (in_array(trim($col), $fields)) {
                $valid_order_by_cols[] = "length(rtf." . trim($col) . ")=0 desc," . trim($col);
            }
        }
        if (count($valid_order_by_cols) == 0) {
            $field_order_by = "ref";
        } else {
            $field_order_by = implode(', ', $valid_order_by_cols);
        }
    }

    $valid_sorts = ['asc', 'ascending', 'desc', 'descending'];
    if (!in_array(strtolower($field_sort), $valid_sorts)) {
        $field_sort = 'asc';
    }
    $conditions = [];
    $restypeconditions = [];
    $groupcondition = "";
    $groupparams = [];
    $joins = [];
    $params = [];
    if (!is_array($restypes)) {
        $restypes = array_filter(explode(",", $restypes), "is_int_loose");
    }
    $restypeselect = ",t.name AS tab_name, GROUP_CONCAT(rtfrt.resource_type ORDER BY rtfrt.resource_type) resource_types";
    $joins[] = " LEFT JOIN resource_type_field_resource_type rtfrt ON rtfrt.resource_type_field = rtf.ref";
    $joins[] = " LEFT JOIN tab t ON t.ref=rtf.tab";

    if (count($restypes) > 0) {
        // Always return global fields
        $restypeconditions[]  = "global=1";
        foreach ($restypes as $restype) {
            if ($restype > 0) {
                $restypeconditions[] = "FIND_IN_SET(?,resource_types)";
                $groupparams[] = "i";
                $groupparams[] = $restype;
            }
        }
    }

    if (count($restypeconditions) > 0) {
        $groupcondition = " HAVING ((" . implode(") OR (", $restypeconditions) . "))";
    }

    if ($include_inactive === false) {
        $conditions[]  = " (rtf.active=1)";
    }
    if ($find != "") {
        $conditions[] = " (rtf.name LIKE ? OR rtf.title LIKE ? OR t.name LIKE ? OR rtf.exiftool_field LIKE ? OR rtf.help_text LIKE ? 
            OR rtf.ref LIKE ? OR rtf.tooltip_text LIKE ? OR rtf.display_template LIKE ?)";
        $params = array_merge($params, ['s', "%$find%", 's', "%$find%", 's', "%$find%", 's', "%$find%", 's', "%$find%", 's', "%$find%", 's', "%$find%", 's', "%$find%"]);
    }
    $newfieldtypes = array_filter($fieldtypes, "is_int_loose");

    if (count($newfieldtypes) > 0) {
        $conditions[] = " rtf.type IN(" . ps_param_insert(count($newfieldtypes)) . ")";
        $params = array_merge($params, ps_param_fill($newfieldtypes, 'i'));
    }

    $conditionstring = count($conditions) > 0 ? (" WHERE " . implode(" AND ", $conditions) . " ") : "";

    $params = array_merge($params, $groupparams);

    $allfieldsquery = "SELECT " . columns_in("resource_type_field", "rtf") . $restypeselect . "
        FROM resource_type_field rtf " . implode(" ", $joins)  . $conditionstring . " 
        GROUP BY rtf.ref " . $groupcondition . "
        ORDER BY rtf.active desc," . $field_order_by . " " . $field_sort;

    $allfields = ps_query($allfieldsquery, $params, "schema");

    // Sort by translated strings if sorting by title
    if (strtolower($field_order_by) == "title") {
        $sortflag = strtolower($field_sort) == "asc" ? SORT_ASC : SORT_DESC;
        foreach ($allfields as $field) {
            $translations[] = i18n_get_translated($field["title"]);
        }
        array_multisort($translations, $sortflag, SORT_STRING, $allfields);
    }

    return $allfields;
}

function notify_resource_change($resource)
{
    debug("notify_resource_change " . $resource);
    global $notify_on_resource_change_days, $baseurl;
    // Check to see if we need to notify users of this change
    if ($notify_on_resource_change_days == 0 || !is_int($notify_on_resource_change_days)) {
        return false;
    }

    debug("notify_resource_change - checking for users that have downloaded this resource " . $resource);
    $download_users = ps_query("SELECT DISTINCT u.ref, u.email FROM resource_log rl LEFT JOIN user u ON rl.user=u.ref WHERE rl.type='d' AND rl.resource=? AND DATEDIFF(NOW(),date)<?", ["i",$resource,"i",$notify_on_resource_change_days], "");
    if (count($download_users) > 0) {
        $notifymessage = new ResourceSpaceUserNotification();
        $notifymessage->set_subject("lang_notify_resource_change_email_subject");
        $notifymessage->set_text("lang_notify_resource_change_email", ["[days]","[url]"], [$notify_on_resource_change_days,$baseurl . "/?r=" . $resource]);
        $notifymessage->user_preference = "user_pref_resource_notifications";
        $notifymessage->url = $baseurl . "/?r=" . $resource;
        $notifymessage->template = 'notify_resource_change_email';
        $notifymessage->templatevars = ["days" => $notify_on_resource_change_days,"url" => $baseurl . "/?r=" . $resource];
        send_user_notification($download_users, $notifymessage);
    }
}

# Takes a string and add verbatim regex matches to the keywords list on found matches (for that field)
# It solves the problem, for example, indexing an entire "nnn.nnn.nnn" string value when '.' are used as a keyword separator.
# Uses config option $resource_field_verbatim_keyword_regex[resource type field] = '/regex/'
function add_verbatim_keywords(&$keywords, $string, $resource_type_field, $called_from_search = false)
{
    global $resource_field_verbatim_keyword_regex,$resource_field_checkbox_match_full;

    // add ",<string>" if specified resource_type_field is found within $resource_field_checkbox_match_full array.
    if (
        !$called_from_search &&
        isset($resource_field_checkbox_match_full) &&
        is_array($resource_field_checkbox_match_full) &&
        in_array($resource_type_field, $resource_field_checkbox_match_full)
    ) {
        preg_match_all('/,[^,]+/', $string, $matches);
        if (isset($matches[0][0])) {
            foreach ($matches[0] as $match) {
                $match = strtolower($match);
                array_push($keywords, $match);
            }
        }
    }

    // normal verbatim expansion of keywords as defined in config.php
    if (!empty($resource_field_verbatim_keyword_regex[$resource_type_field])) {
        preg_match_all($resource_field_verbatim_keyword_regex[$resource_type_field], $string, $matches);
        foreach ($matches as $match) {
            foreach ($match as $sub_match) {
                array_push($keywords, $sub_match);        // note that the keywords array is passed in by reference.
            }
        }
    }

    // when searching change "field:<string>" to "field:,<string>" if specified resource_type_field is found within $resource_field_checkbox_match_full array.
    if (
        $called_from_search &&
        isset($resource_field_checkbox_match_full) &&
        is_array($resource_field_checkbox_match_full) &&
        in_array($resource_type_field, $resource_field_checkbox_match_full)
    ) {
        $found_name = ps_value("SELECT `name` AS 'value' FROM `resource_type_field` WHERE `ref`=?", array("i",$resource_type_field), "");
        preg_match_all('/' . $found_name . ':([^,]+)/', $string, $matches);
        if (isset($matches[1][0])) {
            foreach ($matches[1] as $match) {
                $match = strtolower($match);
                $remove = "{$found_name}:{$match}";
                if (in_array($remove, $keywords)) {
                    unset($keywords[array_search($remove, $keywords)]);
                }
                array_push($keywords, "{$found_name}:,{$match}");
            }
        }
    }
}

/**
 * Check the current user's edit access to given metadata field ID
 *
 * @param  int $field
 * @return bool
 */
function metadata_field_edit_access($field)
{
    return (!checkperm("F*") || checkperm("F-" . $field)) && !checkperm("F" . $field);
}

/**
 * Work out the filename to use, based on the download_filename_format configuration option, when downloading the
 * specified resource file with the given settings
 *
 * @param  int $ref Resource ID
 * @param  string $size size code
 * @param  int $alternative Alternative file ID
 * @param  string $ext File extension
 * @return string  Filename to use
 */
function get_download_filename(int $ref, string $size, int $alternative, string $ext): string
{
    resource_type_config_override(get_resource_data($ref)['resource_type']);
    [$size, $ext, $dff] = array_map('trim', [$size, $ext, $GLOBALS['download_filename_format']]);
    $fallback_filename = "RS{$ref}.{$ext}";
    $formatted_str = $dff ?: DEFAULT_DOWNLOAD_FILENAME_FORMAT;

    $bind['%resource'] = $ref;
    $bind['%extension'] = $ext;
    $bind['%size'] = $size !== '' ? "_$size" : '';
    $bind['%alternative'] = $alternative > 0 ? "_$alternative" : '';

    // Get (original) filename value
    $filename_val = get_data_by_field($ref, $GLOBALS['filename_field'], true);
    if ($alternative > 0) {
        $alt_file = get_alternative_file($ref, $alternative);
        $filename_val = $alt_file !== false ? $alt_file['name'] : '';
    }
    $bind['%filename'] = strip_extension(mb_basename($filename_val), true);

    // Legacy: do an extra check to see if the filename has an uppercase extension that could be preserved
    $filename_val_ext = pathinfo($filename_val, PATHINFO_EXTENSION);
    if ($filename_val_ext !== '' && mb_strtolower($filename_val_ext) === mb_strtolower($ext)) {
        $bind['%extension'] = $filename_val_ext;
    }

    // Get specific field value
    $matching_fields = [];
    if (preg_match_all('/%field(\d+)/', $formatted_str, $matching_fields, PREG_SET_ORDER)) {
        foreach ($matching_fields as [$placeholder, $field_id]) {
            if (!metadata_field_view_access($field_id)) {
                $bind[$placeholder] = '';
                continue;
            }

            $field_data = trim(get_data_by_field($ref, $field_id, true));
            if ($field_data !== '') {
                $bind[$placeholder] = i18n_get_translated($field_data);
            } else {
                // No data, just remove placeholder
                $bind[$placeholder] = "";
            }
        }
    }

    // Build the filename
    $filename = preg_replace_callback(
        '/%(field(\d+)|resource|extension|size|alternative|filename)/',
        fn($matches) => $bind[$matches[0]],
        $formatted_str
    );
    // Allow plugins to completely overwrite it
    $hook_downloadfilenamealt = hook('downloadfilenamealt', '', [$ref, $size, $alternative, $ext]);
    if (is_string($hook_downloadfilenamealt) && $hook_downloadfilenamealt !== '') {
        debug('[get_download_filename] Filename overriden by a hook');
        $filename = $hook_downloadfilenamealt;
    }

    // Remove invalid characters
    $filename = (string) preg_replace(
        '/(:|\r\n|\r|\n|\\\|\/)/',
        '_',
        trim(strip_tags(nl2br($filename)))
    );

    /**
     * If required, truncate to 255 characters - {@see https://en.wikipedia.org/wiki/Comparison_of_file_systems#Limits}
     */
    if (mb_strlen($filename, 'UTF-8') > 255) {
        $filename = mb_strcut($filename, 0, 255, 'UTF-8');
    }

    // Extra check that the generated filename extension matches the requested extension e.g. if using $filename_field value previews it may have the extension of the original resource file, rather than jpg or the field data was too long and the extension has been removed by truncation.
    $filename_parts = pathinfo($filename);
    if (!isset($filename_parts["extension"]) || strtolower($filename_parts["extension"]) != strtolower($ext)) {
        $filename = mb_strcut($filename_parts["basename"], 0, (254 - strlen($ext)), 'UTF-8') . "." . $ext;
    }

    if ($filename !== '') {
        return $filename;
    }

    debug('[get_download_filename] Invalid download filename, fall back.');
    return (string) preg_replace('/(:|\r\n|\r|\n)/', '_', strip_tags(nl2br($fallback_filename)));
}

/**
* Get resource type ID based on extension
* $mappings = array(resource_type_id => array(allowed_extensions));
*
* Example of mapping array:
* $mappings = array(2 => array('pdf', 'doc', 'docx', 'epub', 'ppt', 'pptx', 'odt', 'ods', 'tpl'));
*
* @param string  $extension                        Extension we search by (ie. "mp4")
* @param array   $resource_type_extension_mapping  Maps between resource types and extensions
* @param integer $default                          The default value to use in case we can't find it the mappings
*
* @return integer  Resource type ID
*/
function get_resource_type_from_extension($extension, array $resource_type_extension_mapping, $default)
{
    $resource_types = ps_array("SELECT ref AS value FROM resource_type", array());
    foreach ($resource_type_extension_mapping as $resource_type_id => $allowed_extensions) {
        if (
            !checkperm('T' . $resource_type_id)
            && in_array(strtolower($extension), $allowed_extensions)
            && in_array($resource_type_id, $resource_types)
        ) {
                return $resource_type_id;
        }
    }
    if (in_array($default, $resource_types)) {
        return $default;
    } else {
        // default resource type does not exist so use the first available type
        sort($resource_types, SORT_NUMERIC);
        return $resource_types[0];
    }
}

/**
* Helper function for Preview tools feature. Checks all necessary permissions or options
* in order to tell the system whether PreviewTools panel should be displayed
*
* @param array $resource Resource data structure
*/
function canSeePreviewTools(array $resource): bool
{
    global $image_preview_zoom;
    return (resource_type_applicable_for_annotations($resource['resource_type']) && canSeeAnnotationsFields() !== [])
        || $image_preview_zoom;
}

/**
 * Helper function to determine if annotations are to be displayed.
 *
 * @return array   Array of annotation fields that can be viewed.
 */
function canSeeAnnotationsFields(): array
{
    global $annotate_enabled, $k, $annotate_public_view;
    return ($annotate_enabled && ($annotate_public_view || $k == '')) ? get_annotate_fields() : [];
}

/**
* Determine if a video alternative was created from $ffmpeg_alternatives settings.
* Places in this file because get_resource_path relies on it
*
* @param array $alternative Record line from resource_alt_files
*
* @return boolean True means alternative was created from $ffmpeg_alternatives settings
*/
function alt_is_ffmpeg_alternative($alternative)
{
    global $ffmpeg_alternatives;

    $alt_is_ffmpeg_alternative = false;

    if (isset($ffmpeg_alternatives) && !empty($ffmpeg_alternatives)) {
        foreach ($ffmpeg_alternatives as $alt_setting) {
            if ($alternative['name'] == $alt_setting['name'] && $alternative['file_name'] == $alt_setting['filename'] . '.' . $alt_setting['extension']) {
                return true;
            }
        }
    }
    return $alt_is_ffmpeg_alternative;
}

/**
* Create a new resource type field with the specified name of the required type
*
* @param string $name - name of new field
* @param integer|array $restype - resource type - resource type(s) that field applies to (0 = global, single value = one type, array = multiple types)
* @param integer $type - field type - refer to include/definitions.php
* @param string $shortname - shortname of new field
* @param boolean $index - should new field be indexed?
*
* @return boolean|integer - ref of new field, false if unsuccessful
*/
function create_resource_type_field($name, $restype = 0, $type = FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $shortname = "", $index = false)
{
    if ((trim($name) == "") || !is_numeric($type) || !(is_numeric($restype) || is_array($restype))) {
        return false;
    }

    if (trim($shortname) == "") {
        $shortname = mb_substr(mb_strtolower(str_replace(array("_", "-"), "", safe_file_name($name))), 0, 20);
    }

    $duplicate = (bool) ps_value("SELECT count(ref) AS `value` FROM resource_type_field WHERE `name` = ?", array("s",$shortname), 0, "schema");
    $order_by_current = ps_value("SELECT MAX(order_by) AS `value` FROM resource_type_field", [], 0, "schema");
    $default_tab = ps_value("SELECT MIN(ref) value FROM tab", [], 0, "schema");

    // Global type?
    if ($restype == 0) {
        $global = 1;
        $restypes = [];
    } else {
        $global = 0;
        $restypes = is_array($restype) ? $restype : [$restype];
    }

    ps_query(
        "INSERT INTO resource_type_field (title, global, type, `name`, keywords_index, order_by, tab) VALUES (?, ?, ?, ?, ?, ?, ?)",
        array("s",$name,"i",$global,"i",$type,"s",$shortname,"i",($index ? "1" : "0"),"i",$order_by_current + 10,"i",$default_tab)
    );
    $new = sql_insert_id();

    // Add joins
    foreach ($restypes as $resinsert) {
        ps_query("INSERT INTO resource_type_field_resource_type (resource_type_field,resource_type) VALUES (?, ?)", array("i",$new,"i",$resinsert));
    }

    if ($duplicate) {
        ps_query("UPDATE resource_type_field SET `name` = ? WHERE ref = ?", array("s",$shortname . $new,"i",$new));
    }

    log_activity(null, LOG_CODE_CREATED, $name, 'resource_type_field', 'title', $new, null, '');

    clear_query_cache("schema");

    return $new;
}

/**
* Check if user has view access to metadata field
*
* @uses checkperm()
*
* @param integer $field Field ref
*
* @return boolean
*/
function metadata_field_view_access($field)
{
    return
        (PHP_SAPI == 'cli' && !defined("RS_TEST_MODE"))
        || ((checkperm("f*") || checkperm("f" . $field)) && !checkperm("f-" . $field));
}

/**
* Utility to get all workflow states available in the system.
*
* IMPORTANT: No permissions are being honoured on purpose! If you need to honour permissions @see get_editable_states()
*
* @uses global additional_archive_states
*
* @return array
*/
function get_workflow_states()
{
    global $additional_archive_states;

    $default_workflow_states = range(-2, 3);
    return array_merge($default_workflow_states, $additional_archive_states);
}

function get_workflow_state_names(): array
{
    global $lang, $additional_archive_states;

    $workflow_states = [];

    for ($n=-2; $n<=3; $n++) {
        $workflow_states["$n"] = $lang["status" . $n];
    }
       
    foreach ($additional_archive_states as $additional_archive_state) {
        $workflow_states["$additional_archive_state"] = $lang["status{$additional_archive_state}"] ?? $additional_archive_state;
    }

    return $workflow_states;
}

/**
* Delete the specified metadata field. Also delets any node or resource_data rows associated with that field
*
* @param integer $ref Metadata field id (ref from resource_type_field)
* @param array $varnames Array of variable names
*
* @return boolean|string Returns true on success or text on failure describing error
*/
function delete_resource_type_field($ref)
{
    global $lang, $corefields, $core_field_refs;

    if ('cli' != php_sapi_name() && !checkperm('a')) {
        return $lang["error-permissiondenied"];
    }

    $fieldvars = array();
    foreach ($corefields as $scope => $scopevars) {
        foreach ($scopevars as $varname) {
            global $$varname;
            if (isset($$varname) && ((is_array($$varname) && in_array($ref, $$varname)) || (int)$$varname == $ref)) {
                $fieldvars[] = $varname . ($scope != "BASE" ? " (" . $scope . ")" : "");
            }
        }
    }

    // Prevent deleting a "core" field required by other parts of the system (e.g plugins)
    $core_field_scopes = [];
    foreach ($core_field_refs as $scope => $core_refs) {
        if (in_array($ref, $core_refs) && !in_array($scope, $core_field_scopes)) {
            $core_field_scopes[] = $scope;
        }
    }

    if (count($fieldvars) > 0) {
        return $lang["admin_delete_field_error"] . "<br/>\$" . implode(", \$", $fieldvars);
    } elseif (!empty($core_field_scopes)) {
        return sprintf('%s%s', $lang["admin_delete_field_error_scopes"], implode(', ', $core_field_scopes));
    }

    $fieldinfo = get_resource_type_field($ref);

    // Delete the resource type field
    ps_query("DELETE FROM resource_type_field WHERE ref=?", ["i",$ref]);

    // Remove all nodes and keywords or resources. Always remove nodes last otherwise foreign keys will not work
    ps_query("DELETE rn.* FROM resource_node rn LEFT JOIN node n ON n.ref=rn.node WHERE n.resource_type_field = ?", ["i",$ref]);
    ps_query("DELETE nk.* FROM node_keyword AS nk LEFT JOIN node AS n ON n.ref = nk.node WHERE n.resource_type_field = ?", ["i",$ref]);
    ps_query("DELETE FROM node WHERE resource_type_field = ?", ["i",$ref]);

    hook("after_delete_resource_type_field");

    log_activity('Deleted metadata field "' . $fieldinfo["title"] . '" (' . $fieldinfo["ref"] . ')', LOG_CODE_DELETED, null, 'resource_type_field', null, $ref);

    clear_query_cache("schema");

    return true;
}

function get_resource_table_joins()
{

    global
    $sort_fields,
    $thumbs_display_fields,
    $list_display_fields,
    $data_joins,
    $metadata_template_title_field,
    $view_title_field,
    $date_field,
    $config_sheetlist_fields,
    $config_sheetthumb_fields,
    $config_sheetsingle_fields;

    $joins = array_merge(
        $sort_fields,
        $thumbs_display_fields,
        $list_display_fields,
        $data_joins,
        $config_sheetlist_fields,
        $config_sheetthumb_fields,
        $config_sheetsingle_fields,
        array(
        $metadata_template_title_field,
        $view_title_field,
        $date_field)
    );

    if (isset($GLOBALS["related_pushed_order_by"]) && is_int_loose($GLOBALS["related_pushed_order_by"])) {
        $joins[] = $GLOBALS["related_pushed_order_by"];
    }

    $additional_joins = hook("additionaljoins");
    if ($additional_joins) {
        $joins = array_merge($joins, $additional_joins);
    }

    $joins = array_unique($joins);
    $n = 0;
    foreach ($joins as $join) {
        if ($join != "") {
            $return[$n] = $join;
            $n++;
        }
    }
    return $return;
}

/*
* Update the lock status of the current resource
*
* @param  int       $ref            Resource ID
* @param  int       $lockaction     Lock action (1 = Lock, 0 = Unlock)
* @param  int       $newlockuser    User ID to set lock for. Will default to current user if not passed
* @param  boolean   $accesschecked  Has access to the resource already been checked (false by default)?
*
* @return boolean   Success/failure
*/
function update_resource_lock($ref, $lockaction, $newlockuser = null, $accesschecked = false)
{
    global $userref;

    if (
        ((string)(int)$ref != (string)$ref)
        || $ref <= 0
        || !in_array($lockaction, array(0,1))
    ) {
        return false;
    }

    if (is_null($newlockuser) || ((string)(int)$newlockuser != (string)$newlockuser)) {
        $newlockuser = $userref;
    }

    if (!$accesschecked) {
        $resource_data  = get_resource_data($ref);
        $lockeduser     =  $resource_data["lock_user"];
        $edit_access    = get_edit_access($ref, $resource_data["archive"], $resource_data);
        if (
            !checkperm("a")
            &&
            $lockeduser != $userref
            &&
            !($edit_access && $lockeduser == 0 && !checkperm("nolock"))
        ) {
            return false;
        }
    }

    ps_query("UPDATE resource SET lock_user=? WHERE ref=?", array("i",($lockaction ? $newlockuser : "0"),"i",(int)$ref));
    resource_log($ref, ($lockaction ? LOG_CODE_LOCKED : LOG_CODE_UNLOCKED), 0);
    return true;
}

/*
* Get a message to indicate the lock status
*
* @param  int       id of the locking user
*
* @return string    Text to display
*/
function get_resource_lock_message($lockuser)
{
    global $lang, $userref;
    // Check if user can see details of locking user
    $visible_users = get_users(0, "", "u.username", true);
    if ($lockuser == 0) {
        return "";
    } elseif ($lockuser == $userref) {
        return $lang["status_locked_self"];
    } elseif (in_array($lockuser, array_column($visible_users, "ref"))) {
        $lock_user_data = get_user($lockuser);
        $lock_username = $lock_user_data["fullname"] != "" ? $lock_user_data["fullname"] : $lock_user_data["username"];
        return str_replace("[user]", $lock_username, $lang["status_locked_by"]);
    } else {
        return $lang["error_locked_other_user"];
    }
}

/**
 * Get details of external shares
 *
 * @param  array $filteropts    Array of options to filter shares returned
 *                              "share_group"       - (int) Usergroup ref 'shared as'
 *                              "share_user"        - (int) user ID of share creator
 *                              "share_order_by"    - (string) order by column
 *                              "share_sort"        - (string) sortorder (ASC or DESC)
 *                              "share_type"        - (int) 0=view, 1=upload
 *                              "share_collection"  - (int) Collection ID
 *                              "share_resource"    - (int) Resource ID
 *                              "access_key"        - (string) Access key
 *                              "ignore_permissions"- (bool) Show all shares, irrespective of permissions
 * @return array
 */
function get_external_shares(array $filteropts)
{
    global $userref;

    $share_group = $filteropts['share_group'] ?? null;
    $share_user = $filteropts['share_user'] ?? null;
    $share_order_by = $filteropts['share_order_by'] ?? null;
    $share_sort = $filteropts['share_sort'] ?? null;
    $share_type = $filteropts['share_type'] ?? null;
    $share_collection = $filteropts['share_collection'] ?? null;
    $share_resource = $filteropts['share_resource'] ?? null;
    $access_key = $filteropts['access_key'] ?? null;
    $ignore_permissions = $filteropts['ignore_permissions'] ?? null;

    $valid_orderby = array("collection","user", "sharedas", "expires", "date", "email", "lastused", "access_key", "upload");
    if (!in_array($share_order_by, $valid_orderby)) {
        $share_order_by = "expires";
    }
    $share_sort = strtoupper((string) $share_sort) == "ASC" ? "ASC" : "DESC";

    $conditions = array();
    $params = [];
    if (
        (int)$share_user > 0 && ($share_user == $userref || checkperm_user_edit($share_user))
    ) {
        $conditions[] = "eak.user = ?";
        $params = ['i', (int)$share_user];
    } elseif (!checkperm('a') && !$ignore_permissions) {
        $usercondition = "eak.user = ?";
        $params = array_merge($params, ['i', (int)$userref]);
        if (checkperm("ex")) {
            // Can also see shares that never expire
            $usercondition = " (expires IS NULL OR " . $usercondition . ")";
        }
        $conditions[] = $usercondition;
    }

    if (!is_null($share_group) && (int)$share_group > 0  && checkperm('a')) {
        $conditions[] = "eak.usergroup = ?";
        $params = array_merge($params, ['i', (int)$share_group]);
    }

    if (!is_null($access_key)) {
        $conditions[] = "eak.access_key = ?";
        $params = array_merge($params, ['s', $access_key]);
    }

    if ((int)$share_type === 0) {
        $conditions[] = "(eak.upload=0 OR eak.upload IS NULL)";
    } elseif ((int)$share_type === 1) {
        $conditions[] = "eak.upload=1";
    }
    if (is_int_loose($share_collection) && $share_collection != 0) {
        $conditions[] = "eak.collection = ?";
        $params = array_merge($params, ['i', (int)$share_collection]);
    }
    if ((int)$share_resource > 0) {
        $conditions[] = "eak.resource = ?";
        $params = array_merge($params, ['i', (int)$share_resource]);
    }

    $conditional_sql = "";
    if (count($conditions) > 0) {
        $conditional_sql = " WHERE " . implode(" AND ", $conditions);
    }

    $external_access_keys_query =
        "SELECT access_key,
                ifnull(collection,'-') collection,
                CASE
                    WHEN collection IS NULL THEN resource
                    ELSE '-'
                END AS 'resource',
                user,
                eak.email,
                min(date) date,
                MAX(date) maxdate,
                max(lastused) lastused,
                eak.access,
                eak.expires,
                eak.usergroup,
                eak.password_hash,
                eak.upload,
                ug.name sharedas,
                u.fullname,
                u.username
           FROM external_access_keys eak
      LEFT JOIN user u ON u.ref=eak.user
      LEFT JOIN usergroup ug ON ug.ref=eak.usergroup " .
                $conditional_sql .
     " GROUP BY access_key, collection
       ORDER BY " . $share_order_by . " " . $share_sort;

    return ps_query($external_access_keys_query, $params);
}

/**
* Get video duration
*
* @uses run_command()
*
* @param  string $file_path    Path to video file
*
* @return float
*/
function get_video_duration(string $file_path)
{
    $exiftool_fullpath = get_utility_path("exiftool");

    $duration_tag = run_command($exiftool_fullpath . "-n -duration %filepath", false, ["%filepath" => $file_path]);

    if (!empty($duration_tag)) {
        $duration = str_replace(" s", "", substr($duration_tag, strpos($duration_tag, ":") + 2));
        return floatval($duration);
    } else {
        return 0;
    }
}

/**
 * Relate all resources in the passed array with each other
 *
 * @param  array $related Array of resource IDs
 * @return boolean
 */
function relate_all_resources(array $related = [])
{
    $error = false;
    array_filter($related, "is_int_loose");
    foreach ($related as $ref) {
        $other_refs = array_diff($related, array($ref));
        $success = update_related_resource($ref, $other_refs, true);
        if (!$success) {
            $error = true;
        }
    }
    return !$error;
}

/**
 * Apply new order to metadata fields
 *
 * @param  array $neworder  Field IDs in new order
 *
 * @return void
 */
function update_resource_type_field_order($neworder)
{
    global $lang;
    if (!is_array($neworder)) {
        exit("Error: invalid input to update_resource_type_field_order function.");
    }

    $updatesql = "update resource_type_field set order_by=(case ref ";
    $counter = 10;
    $params = array();
    foreach ($neworder as $restype) {
        $updatesql .= "when ? then ? ";
        $params[] = "i";
        $params[] = $restype;
        $params[] = "i";
        $params[] = $counter;
        $counter = $counter + 10;
    }
    $updatesql .= "else order_by END)";
    ps_query($updatesql, $params);
    clear_query_cache("schema");
    log_activity($lang['resourcetypefieldreordered'], LOG_CODE_REORDERED, implode(', ', $neworder), 'resource_type_field', 'order_by');
}

/**
 * Apply a new order to resource types
 *
 * @param  array $neworder  Resource type IDs in new order
 *
 * @return void
 */
function update_resource_type_order($neworder)
{
    global $lang;
    if (!is_array($neworder)) {
        exit("Error: invalid input to update_resource_type_field_order function.");
    }

    $updatesql = "update resource_type set order_by=(case ref ";
    $counter = 10;
    $params = array();

    foreach ($neworder as $restype) {
        $updatesql .= "when ? then ? ";
        $params[] = "i";
        $params[] = $restype;
        $params[] = "i";
        $params[] = $counter;
        $counter = $counter + 10;
    }
    $updatesql .= "else order_by END)";
    ps_query($updatesql, $params);
    clear_query_cache("schema");
    log_activity($lang['resourcetypereordered'], LOG_CODE_REORDERED, implode(', ', $neworder), 'resource_type', 'order_by');
}

/**
 * Check if file can be rendered in browser via download.php
 *
 * @param  string $path Path to file
 */
function allow_in_browser($path): bool
{
    if (!file_exists($path) || is_dir($path)) {
        return false;
    }
    // Permitted mime types can only be overridden by plugins
    $permitted_mime[] = "application/pdf";
    $permitted_mime[] = "image/jpeg";
    $permitted_mime[] = "image/png";
    $permitted_mime[] = "image/gif";
    $permitted_mime[] = "image/webp";
    $permitted_mime[] = "audio/mpeg";
    $permitted_mime[] = "video/mp4";
    $permitted_mime[] = "text/plain";
    $permitted_mime[] = "text/csv";
    $allow = hook('allow_in_browser', "", [$permitted_mime]);
    if (is_array($allow)) {
        $permitted_mime = $allow;
    }

    if (function_exists('mime_content_type')) {
        $type = array_values(array_filter([mime_content_type($path)]));
    } else {
        $type = get_mime_type($path);
    }

    // Unsure? Try and get mime type via exiftool if possible
    if ($type === ['application/octet-stream']) {
        $type = get_mime_type($path, null, true);
    }

    return array_intersect($permitted_mime, $type) !== [];
}

/**
* Update the value of the fieldXX field on resource table
*
* @param integer $resource  - Resource ID
* @param integer $field     - Metadata field ID
* @param string  $value     - Value

* @return boolean
*/
function update_resource_field_column(int $resource, int $field, string $value)
{
    $sql = "UPDATE resource SET `field" . $field . "` = ? WHERE ref = ?";
    $params = ["s",truncate_join_field_value($value),"i",$resource];
    ps_query($sql, $params);
    return true;
}

/**
* Convert $data_joins (ie fieldX column) value to a user friendly version.
*
* IMPORTANT: csv in this context simply means user defined separator values (relies on {@see $field_column_string_separator}).
*
* Text value will be:-
* - split by the configued separator {@see $field_column_string_separator};
* - have all parts translated; (note: to correctly translate tree paths, a part will also be broken into path elements)
* - glued back using the same separator
*
* @param string|null $value Text to be processed
*
* @return string|null
*/
function data_joins_field_value_translate_and_csv(?string $value): ?string
{
    if (is_null($value)) {
        return null;
    }

    $value_parts = [];
    $split_by_fcss = explode($GLOBALS['field_column_string_separator'], $value);
    foreach ($split_by_fcss as $el) {
        $value_parts[] = implode('/', array_map('i18n_get_translated', explode('/', $el)));
    }

    return implode($GLOBALS['field_column_string_separator'], $value_parts);
}

/**
 * Process resource data_joins (ie fieldX columns) values
 *
 * @param array $resource             A resource table record
 * @param array $resource_table_joins List of refs for the resource table data_joins. {@see get_resource_table_joins()}
 *
 * @return array Returns the resource record with updated data_joins (ie fieldX columns) values
 */
function process_resource_data_joins_values(array $resource, array $resource_table_joins): array
{
    $fieldX_column_names = array_map(prefix_value('field'), $resource_table_joins);
    $fieldX_data = array_intersect_key($resource, array_flip($fieldX_column_names));
    $fieldX_translated_csv = array_map('data_joins_field_value_translate_and_csv', $fieldX_data);
    return array_merge($resource, $fieldX_translated_csv);
}

/**
 * Check if resource has access denied by its type and for a size.
 *
 * @param int $resource_type Resource type ref
 * @param string $size Preview size ID (not ref).
 *
 * @return bool
 */
function resource_has_access_denied_by_RT_size(int $resource_type, string $size): bool
{
    $Trt = 'T' . $resource_type;
    return checkperm($Trt) || checkperm("{$Trt}_{$size}");
}

/**
 * Revert primary resource file based on log entry data
 *
 * @param int   $resource       Resource ID
 * @param array $logentry       Log data from get_resource_log(). Requires rse_version plugin to be enabled
 * @param bool $createpreviews  Create previews?
 *
 * @return bool
 *
 */
function revert_resource_file($resource, $logentry, $createpreviews = true)
{
    global $lang;
    // Find file extension of current resource.
    $old_extension = ps_value("SELECT file_extension value FROM resource WHERE ref=?", array("i",$resource), "");

    // Copy current file to alternative file.
    $old_path = get_resource_path($resource, true, '', true, $old_extension);
    if (!file_exists($old_path)) {
        debug("Revert failed: Missing file: $old_path ($old_extension)");
        return false;
    }

    // Create a new alternative file based on the current resource
    $alt_file = add_alternative_file($resource, '', '', '', $old_extension, 0, '');
    $new_path = get_resource_path($resource, true, '', true, $old_extension, -1, 1, false, "", $alt_file);

    copy($old_path, $new_path);

    // Also copy thumbnail
    $old_thumb = get_resource_path($resource, true, 'thm', true, "");
    if (file_exists($old_thumb)) {
        $new_thumb = get_resource_path($resource, true, 'thm', true, "", -1, 1, false, "", $alt_file);
        copy($old_thumb, $new_thumb);
    }

   // Update log so this has a pointer.
    $log_ref = resource_log($resource, LOG_CODE_UPLOADED, 0, $lang["revert_log_note"]);
    $parameters = array("i",$alt_file, "i",$log_ref);
    ps_query("UPDATE resource_log SET previous_file_alt_ref=? WHERE ref=?", $parameters);

    // Now perform the revert, copy and recreate previews.
    $revert_alt_ref = $logentry["previous_file_alt_ref"];
    $revert_ext = ps_value("SELECT file_extension value FROM resource_alt_files WHERE ref=?", array("i",$revert_alt_ref), "");

    $revert_path = get_resource_path($resource, true, '', true, $revert_ext, -1, 1, false, "", $revert_alt_ref);
    $current_path = get_resource_path($resource, true, '', true, $revert_ext);
    if (!file_exists($revert_path)) {
        debug("Revert fail... $revert_path not found.");
        return false;
    }

    copy($revert_path, $current_path);
    $parameters = array("s",$revert_ext, "i",$resource);
    ps_query("UPDATE resource SET file_extension=?, has_image=0,no_file=0 WHERE ref=?", $parameters);
    if ($createpreviews) {
        create_previews($resource, false, $revert_ext);
    }
    return true;
}

/**
 * When changing resource type, new resource type specific fields may become available. This function will apply any resource default
 * values for resource type specific fields that were not previously available (i.e. not containing user data). This is used by upload
 * then edit mode after switching resource types and also when locking the resource type with save and next.
 *
 * @param  int   $old_resource_type   Original resource type.
 * @param  int   $new_resource_type   Resource type being switched to.
 * @param  int   $resource            Resource id.
 *
 * @return void
 */
function apply_resource_default(int $old_resource_type, int $new_resource_type, int $resource): void
{
    $fields_for_old_resource_type = array_column(get_resource_type_fields($old_resource_type), 'ref');
    $fields_for_new_resource_type = array_column(get_resource_type_fields($new_resource_type), 'ref');
    $check_resource_default_fields = array_diff($fields_for_new_resource_type, $fields_for_old_resource_type);
    if (count($check_resource_default_fields) > 0) {
        global $get_resource_data_cache;
        if (isset($get_resource_data_cache[$resource])) {
            # update_field() calls get_resource_data() which needs to return the new resource type.
            unset($get_resource_data_cache[$resource]);
        }
        set_resource_defaults($resource, $check_resource_default_fields);
    }
}

/**
 * Get a related resource to pull images from
 *
 * @param array $resource   Array of resource data from do_search()
 *
 * @return array|bool $resdata    Array of alternative resource data to use, or false if not configured or no resource image found
 *
 */
function related_resource_pull(array $resource)
{
    global $resource_path_pull_cache;
    $related = false;
    if (isset($resource_path_pull_cache[$resource["ref"]])) {
        return $resource_path_pull_cache[$resource["ref"]];
    }

    $restypes = get_resource_types('', false, true, true);
    $pull_images = array_column($restypes, "pull_images", "ref")[$resource['resource_type']];
    if ((int)$pull_images === 1) {
        $relatedpull = do_search("!related" . $resource["ref"]);
        debug("Looking for a related resource with image for resource ID #" . $resource["ref"]);
        if (is_array($relatedpull)) {
            foreach ($relatedpull as $related) {
                if ($related["has_image"] !== RESOURCE_PREVIEWS_NONE) {
                    $relatedpath = get_resource_path($related["ref"], true, "pre", false, "jpg", true, 1, false);
                    if (file_exists($relatedpath)) {
                        $resource_path_pull_cache[$resource["ref"]] = $related;
                        debug("Found related resource with image: " . $related["ref"]);
                        break;
                    }
                }
            }
        }
    }
    return $related;
}

/**
 * Get the largest available preview URL for the given resource and the given array of sizes
 *
 * @param array     $resource               Array of resource data from get_resource_data() or search results
 * @param array     $sizes                  Array of size IDs to look through, in order of size. If not provided will use all sizes
 * @param int       $access                 Resource access
 * @param bool      $watermark              Look for watermarked versions?
 * @param int       $page                   Page to look for
 * @param bool      $try_pulled_resource    Should we try to use an image from a pulled resource if the current resource doesn't have a usable preview
 * @param int       $alt                    Int ref of alternative file 
 *
 * @return array | bool         array, or false if no image is found
 *
 */
function get_resource_preview(array $resource, array $sizes = [], int $access = -1, bool $watermark = false, int $page = 1, bool $try_pulled_resource = true, int $alt = -1)
{
    global $userref, $open_access_for_contributor, $sizes_always_allowed;
    if (empty($sizes)) {
        $sizes = array_reverse(array_column(get_all_image_sizes(), "id"));
    }

    $preview["url"] = "";
    if (isset($resource['thm_url']) && $alt === -1) {
        // Option to override thumbnail image in search results, e.g. by plugin using process_search_results hook
        $preview["url"] = $resource['thm_url'];
        $preview["height"] = $resource["thumb_height"];
        $preview["width"] = $resource["thumb_width"];
    } else {
        if ($access == -1) {
            $access = get_resource_access($resource);
        }

        // Work out image to use.
        // With restricted access not all sizes are available.
        if ($access !== RESOURCE_ACCESS_FULL) {
            $sizes = array_intersect($sizes, $sizes_always_allowed);
        }

        if ($watermark !== '') {
            $use_watermark = check_use_watermark();
        } else {
            $use_watermark = false;
        }
        $validimage = false;
        foreach ($sizes as $size) {
            if (!resource_download_allowed($resource['ref'], $size, $resource['resource_type'], $alt)) {
                continue;
            }

            // Check that file actually exists
            $img_file = get_resource_path(
                $resource['ref'],
                true,
                $size,
                false,
                $resource['preview_extension'],
                true,
                $page,
                $use_watermark,
                $resource['file_modified'],
                $alt
            );
            if (file_exists($img_file)) {
                $preview["path"] = $img_file;
                $preview["url"] = get_resource_path($resource['ref'], false, $size, false, $resource['preview_extension'], true, $page, $use_watermark, $resource['file_modified'], $alt);
                $GLOBALS["use_error_exception"] = true;
                try {
                    list($preview["width"], $preview["height"]) = getimagesize($img_file);
                    $validimage = true;
                } catch (Exception $e) {
                    $returned_error = $e->getMessage();
                    debug("get_resource_preview - getimagesize(): " . $returned_error);
                }
                unset($GLOBALS["use_error_exception"]);
                break;
            }
        }
        if (!$validimage && (int)$resource['has_image'] === RESOURCE_PREVIEWS_NONE && $try_pulled_resource && $alt === -1) {
            // If configured, try and use a preview from a related resource
            $pullresource = related_resource_pull($resource);
            if ($pullresource !== false) {
                $resource = $pullresource;
                $preview = get_resource_preview($resource, $sizes, get_resource_access($resource), $watermark, $page, false);
                $validimage = $preview !== false; 
            }
        }
    }
    if (!$validimage) {
        return false;
    }
    return $preview;
}

/**
 * Check integrity of primary resource files
 *
 * @param array $resources      Array of resource data e.g. from search results
 * @param bool $presenceonly    Check for file presence only? If false (and if $file_checksums is enabled)
 *                              then file checksums will be checked
 *
 * @return array                Array of resource IDs that have failed  to verify
 *
 */
function check_resources(array $resources = [], bool $presenceonly = false): array
{
    if (count($resources) === 0) {
        $resources = get_resources_to_validate();
    }
    if (count($resources) === 0) {
        echo " - No resources require integrity checks" . PHP_EOL;
        return [];
    }

    $existingfailed = array_column(array_filter($resources, function ($resource) {
        return $resource["integrity_fail"] == 1;
    }), "ref");

    $return_failed = [];
    foreach (array_chunk($resources, 1000) as $checkresources) {
        db_begin_transaction("checkresources");

        // Resources with no file_size have no file so can't be checked.
        $resources_with_no_file = array();
        $resource_with_file = array();
        foreach($checkresources as $check_ref) {
            if (!is_null($check_ref["file_size"]) && $check_ref["file_size"] > 0) {
                $resource_with_file[] = $check_ref;
            } elseif ((int) $check_ref["no_file"] === 0 && (int) $check_ref["integrity_fail"] !== 1) {
                $resources_with_no_file[] = $check_ref;
            }
        }
        $checkresources = $resource_with_file;
        $resources_with_no_file = array_column($resources_with_no_file, 'ref');

        $had_uploads_file_found = array();
        $had_uploads_no_file_found = array();
        if (count($resources_with_no_file) > 0) {
            // Extra check of resources with no file to make sure they never had one at any point.
            $had_uploads = ps_array("
                            SELECT DISTINCT resource value 
                            FROM resource_log
                            WHERE resource IN (" . ps_param_insert(count($resources_with_no_file)) . ")
                            AND type = ?",
                            array_merge(ps_param_fill($resources_with_no_file, 'i'), ['s', LOG_CODE_UPLOADED])
                        );

            if (count($had_uploads) > 0) {
                // At this point a resource may have no file_size set but still have had an uploaded file
                $check_no_upload_resources = validate_resource_files($had_uploads, array("is_readable" => true));
                foreach ($check_no_upload_resources as $ref => $result) {
                    if ($result) {
                        $had_uploads_file_found[] = $ref;
                    } else {
                        $had_uploads_no_file_found[] = $ref;
                    }
                }

                if (count($had_uploads_file_found) > 0) {
                    // There is actually a file. file_size was 0 or null and shouldn't have been.
                    // Update file_size but don't mark this resource as invalid so it'll be checked on the next pass.
                    foreach ($had_uploads_file_found as $update_ref_file_size) {
                        $update_ref_file_size = get_resource_data($update_ref_file_size);
                        $resource_path = get_resource_path($update_ref_file_size['ref'], true, "", false, $update_ref_file_size['file_extension']);
                        $filesize = filesize_unlimited($resource_path);
                        $filesize_sql = "UPDATE resource SET file_size = ? WHERE ref = ?;";
                        ps_query($filesize_sql, array('i', $filesize, 'i', $update_ref_file_size['ref']));
                    }
                }

                if (count($had_uploads_no_file_found) > 0) {
                    // Checking for the file confirmed it wasn't there so mark as integrity failed.
                    $failed_sql = "UPDATE resource SET integrity_fail = 1 WHERE ref IN (" . ps_param_insert(count($had_uploads_no_file_found)) . ")";
                    $failed_params = ps_param_fill($had_uploads_no_file_found, "i");
                    ps_query($failed_sql, $failed_params);
                }
            }

            $no_uploads = array_diff($resources_with_no_file, $had_uploads);

            if (count($no_uploads) > 0) {
                // No evidence of a file ever being uploaded. Mark these as having no_file
                ps_query("
                    UPDATE resource 
                    SET no_file = 1
                    WHERE ref IN (" . ps_param_insert(count($no_uploads)) . ")",
                    ps_param_fill($no_uploads, "i")
                );
            }
        }

        if (count($checkresources) === 0) {
            db_end_transaction("checkresources");
            return array();
        }

        $checks = [];
        $checks["is_readable"] = true; // Always check for file presence as checksums may not have been generated yet
        if (!$presenceonly && $GLOBALS["file_checksums"]) {
            $checks["get_checksum"]  = "%RESOURCE%file_checksum";
        }
        $results = validate_resource_files($checkresources, $checks);

        $failed = [];
        $succeeded = [];
        foreach ($results as $ref => $result) {
            if ($result === false) {
                $failed[] = $ref;
            } else {
                $succeeded[] = $ref;
            }
        }

        if (count($failed) > 0) {
            if (count($failed) > 0) {
                $failed_sql = "UPDATE resource SET integrity_fail = 1 WHERE ref IN (" . ps_param_insert(count($failed)) . ")";
                $failed_params = ps_param_fill($failed, "i");
                ps_query($failed_sql, $failed_params);
            }
        }
        if (count($succeeded) > 0) {
            $success_sql = "UPDATE resource SET integrity_fail = 0,no_file=0, last_verified=NOW() WHERE ref IN (" . ps_param_insert(count($succeeded)) . ")";
            $success_params = ps_param_fill($succeeded, "i");
            ps_query($success_sql, $success_params);
        }
        // Add any failures to the array to return
        $return_failed = array_merge($return_failed, $failed, $had_uploads_no_file_found);
        db_end_transaction("checkresources");

        // Log any newly failed resources
        db_begin_transaction("logfailedresources");
        $arr_newfails = array_diff($failed, $existingfailed);
        foreach ($arr_newfails as $newfail) {
            resource_log($newfail, LOG_CODE_SYSTEM, 0, "Failed file integrity check", 0, 1);
        }
        db_end_transaction("logfailedresources");

         // Log any recovered resources
         db_begin_transaction("logrecoveredresources");
         $arr_recovered = array_intersect($succeeded, $existingfailed);
        foreach ($arr_recovered as $recovered) {
            resource_log($recovered, LOG_CODE_SYSTEM, 0, "Passed file integrity check", 1, 0);
        }
         db_end_transaction("logrecoveredresources");
    }
    return $return_failed;
}

/**
 * Get an array of all resources that require files to be validated
 *
 * @param int $days     Return only resources not validated in the last X number of days
 */
function get_resources_to_validate(int $days = 0): array
{
    $params = [];
    $filtersql = "";
    $restypes_ignore = array_unique(array_merge(
        $GLOBALS["data_only_resource_types"],
        $GLOBALS["file_integrity_ignore_resource_types"]
    ));

    if (count($GLOBALS["file_integrity_ignore_states"]) > 0) {
        $filtersql .= " AND archive NOT IN (" . ps_param_insert(count($GLOBALS["file_integrity_ignore_states"])) . ")";
        $params = array_merge($params, ps_param_fill($GLOBALS["file_integrity_ignore_states"], "i"));
    }

    if (count($restypes_ignore) > 0) {
        $filtersql .= " AND resource_type NOT IN (" . ps_param_insert(count($restypes_ignore)) . ")";
        $params = array_merge($params, ps_param_fill($restypes_ignore, "i"));
    }

    if ($days > 0) {
        $filtersql .= " AND (last_verified IS NULL OR DATEDIFF(NOW(), last_verified) > ?)";
        $params = array_merge($params, ["i", $days]);
    }

    return ps_query(
        "SELECT ref,
                archive,
                file_extension,
                resource_type,
                file_checksum,
                last_verified,
                integrity_fail,
                file_size,
                no_file
        FROM resource
        WHERE ref > 0 AND no_file = 0 {$filtersql}
        ORDER BY integrity_fail DESC, last_verified ASC",
        $params
    );
}

/**
 * Access control check that user can edit (a resource) of a particular Resource Type (RT). Main use is to hide
 * inapplicable RTs.
 *
 * @param int $ref Resource type ID
 */
function acl_can_edit_resource_of_type(int $ref): bool
{
    return checkperm("ert{$ref}")
        || !(
            checkperm("XE{$ref}")
            || checkperm("XE") && !checkperm("XE-{$ref}")
        );
}

/**
 *  Check if a resource is a template resource, dictated by the $fstemplate_alt_threshold config
 * 
 *  @param int $ref resource ref to check
 */
function resource_is_template(int $ref): bool
{
    global $fstemplate_alt_threshold;
    return $ref < $fstemplate_alt_threshold && $fstemplate_alt_threshold > 0;
}

/**
 * Check if current user can upload a preview image
 *
 * @param int $ref  Resource ID
 *
 */
function can_upload_preview_image(int $ref): bool
{
    return !resource_file_readonly($ref)
        && !checkperm("F*")
        && !checkperm("xupr")
        && !resource_is_template($ref) 
        && !$GLOBALS['custompermshowfile'];
}
