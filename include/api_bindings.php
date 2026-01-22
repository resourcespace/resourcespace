<?php

/*
 * API v2 : Bindings to built in functions
 *
 * Montala Limited, July 2016
 *
 * This allows us to exclude certain parameters for security reasons (such as $use_permissions) and also to
 * map to more API-appropriate parameters and output if necessary.
 *
 * For documentation please see: http://www.resourcespace.com/knowledge-base/api/
 *
 */

function api_do_search($search, $restypes = "", $order_by = "relevance", $archive = 0, $fetchrows = -1, $sort = "desc", $offset = 0)
{
    $offset = (int) $offset;

    // Parse fetchrows
    $fetchrows = array_map('intval', explode(',', trim($fetchrows, ' []')));
    $structured_fetchrows = count($fetchrows) === 2;
    if (!$structured_fetchrows) {
        $fetch = array_pop($fetchrows);
        $fetchrows = $fetch > 0 ? $fetch : -1;
        if ($offset > 0 && $fetchrows != -1) {
            $fetchrows = $fetchrows + $offset;
        }
    }

    $no_results = $structured_fetchrows ? ['total' => 0, 'data' => []] : [];

    if (!checkperm('s')) {
        return $no_results;
    }

    # Note the subset of the available parameters. We definitely don't want to allow override of permissions or filters.
    $results = do_search($search, $restypes, $order_by, $archive, $fetchrows, $sort);
    if (!is_array($results)) {
        return $no_results;
    }

    $resultcount = count($structured_fetchrows ? $results['data'] : $results);
    if ($resultcount < $offset) {
        return $no_results;
    }

    $resultset = array();
    $get_resource_table_joins = get_resource_table_joins();
    $i = 0;
    for ($n = $offset; $n < $resultcount; $n++) {
        $row = $structured_fetchrows ? $results['data'][$n] : $results[$n];
        if (is_array($row)) {
            $resultset[$i] = process_resource_data_joins_values($row, $get_resource_table_joins);
            $i++;
        }
    }

    if ($structured_fetchrows) {
        $results['data'] = $resultset;
        return $results;
    }
    return $resultset;
}

function api_search_get_previews($search, $restypes = "", $order_by = "relevance", $archive = 0, $fetchrows = -1, $sort = "desc", $recent_search_daylimit = "", $getsizes = "", $previewext = "jpg")
{
    # Extension to search capability that also returns the URLs of preview file sizes requested using the $getsizes parameter that match the requested extension.
    if (!checkperm('s')) {
        return array();
    }
    $getsizes = explode(",", $getsizes);

    $structured = false;
    if (strpos((string)$fetchrows, ",") !== false) {
        // Convert string into array, removing square brackets if passed as array syntax in string form
        $fetchrows = explode(",", trim($fetchrows, " []"));
    }
    if (is_array($fetchrows)) {
        $structured = true;
    }
    $results = search_get_previews($search, $restypes, $order_by, $archive, $fetchrows, $sort, false, false, false, $recent_search_daylimit, false, false, false, false, false, $getsizes, $previewext);

    if (is_array($results) && isset($results["total"])) {
        $totalcount = $results["total"];
        $results = $results["data"];
        $resultcount = count($results);
    } elseif (is_array($results)) {
        $totalcount = $resultcount = count($results);
    } else {
        return $structured ? ["total" => 0, "data" => []] : [];
    }

    $get_resource_table_joins = get_resource_table_joins();
    for ($n = 0; $n < $resultcount; $n++) {
        if (is_array($results[$n])) {
            $results[$n] = process_resource_data_joins_values($results[$n], $get_resource_table_joins);
            if ($GLOBALS["hide_real_filepath"]) {
                // Add a temporary key so the file can be accessed unauthenticated
                foreach ($getsizes as $getsize) {
                    if (isset($results[$n]["url_" . $getsize])) {
                        $accesskey = generate_temp_download_key($GLOBALS["userref"], $results[$n]["ref"], $getsize);
                        if ($accesskey !== '') {
                            $results[$n]["url_" . $getsize] .= "&access_key={$accesskey}";
                        }
                    }
                }
            }
        }
    }
    return $structured ? ["total" => $totalcount, "data" => $results] : $results;
}

function api_get_resource_field_data($resource)
{
    # Get all field data for a resource
    $results = get_resource_field_data($resource);
    if (is_array($results)) {
        $resultcount = count($results);
            {
        for ($n = 0; $n < $resultcount; $n++) {
            // Remove node_values array as not required for the API
            unset($results[$n]['nodes_values']);
            $results[$n] = array_map("i18n_get_translated", $results[$n]);
        }
            }
    }
    return $results;
}

function api_create_resource($resource_type, $archive = 999, $url = "", $no_exif = false, $revert = false, $autorotate = false, $metadata = "")
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $lang;
    if (!(checkperm("c") || checkperm("d")) || checkperm("XU" . $resource_type)) {
        return false;
    }

    if ($archive !== 999 && get_default_archive_state($archive) != $archive) {
        // User supplied archive state which they don't have permission to use.
        return false;
    }

    $no_exif    = filter_var($no_exif, FILTER_VALIDATE_BOOLEAN);
    $revert     = filter_var($revert, FILTER_VALIDATE_BOOLEAN);
    $autorotate = filter_var($autorotate, FILTER_VALIDATE_BOOLEAN);

    if ($url != "" && !api_validate_upload_url($url)) {
        // URL failed validation
        return false;
    }

    # Create a new resource
    $ref = create_resource($resource_type, $archive, -1, $lang["createdfromapi"], '', false);
    if (!is_int($ref)) {
        return false;
    }

    # Also allow upload URL in the same pass (API specific, to reduce calls)
    if ($url != "") {
        // Generate unique hash to use so that other uploads with the same name won't conflict
        $upload_key = uniqid($ref . "_");
        $tmp_dld_fpath = temp_local_download_remote_file($url, $upload_key);
        if ($tmp_dld_fpath === false) {
            return "FAILED: Resource #{$ref} was created, but the file was not uploaded. Enable debug log and try again to identify why uploading it failed.";
        }

        #Check for duplicates if required
        $duplicates = check_duplicate_checksum($tmp_dld_fpath, false);
        if (count($duplicates) > 0) {
            $duplicates_string = implode(",", $duplicates);
            return "FAILED: Resource {$ref} was created, but the file was not uploaded. Resources {$duplicates_string} already have a matching file.";
        } else {
            $return = upload_file_by_url($ref, $no_exif, $revert, $autorotate, $tmp_dld_fpath, $upload_key);
            if ($return === false) {
                return false;
            }
        }
    }

    # Also allow metadata to be passed here.
    if ($metadata != "") {
        $metadata = json_decode($metadata, true);
        if (is_array($metadata)) {
            foreach ($metadata as $field => $value) {
                // check $value is not an array
                if (is_array($value)) {
                    return false;
                }
                update_field($ref, $field, $value);
            }
        }
    }

    # Run autocomplete macros as we tell create_resource not to run them earlier
    autocomplete_blank_fields($ref, false);

    return $ref;
}


/**
 *
 * Provides simple way to update field by passing in simple string values for text fields,
 * comma separated values for fixed list (node) fields, using double quotes
 * to enclose strings and backslash as escape character
 * Uses update_field and add_resource_nodes/delete_resource_nodes
 *
 */

function api_update_field($resource, $field, $value, $nodevalues = false)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $FIXED_LIST_FIELD_TYPES, $category_tree_add_parents, $resource_field_column_limit, $userref;

    // This user's template or real resources only
    if ($resource < 1 && $resource != 0 - $userref) {
        return false;
    }

    $resourcedata = get_resource_data($resource, true);
    if (!$resourcedata) {
        return false;
    }

    $editaccess = get_edit_access($resource, $resourcedata['archive'], $resourcedata);

    if (!is_numeric($field)) {
        // Name may have been passed
        $field = ps_value("SELECT ref value FROM resource_type_field WHERE name = ?", ['s',$field], "", "schema");
    }

    if (!$editaccess || !metadata_field_edit_access($field)) {
        return false;
    }
    $fieldinfo = get_resource_type_field($field);

    if (!$fieldinfo) {
        return false;
    }
    $errors = [];
    return update_field($resource, (int) $field, $value, $errors, true, $nodevalues);
}

function api_delete_resource($resource)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    return delete_resource($resource);
}

function api_copy_resource($from, $resource_type = -1)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $lang;
    return copy_resource($from, $resource_type, $lang["createdfromapi"]);
}

function api_get_resource_log($resource, $fetchrows = -1)
{
    return get_resource_log($resource, $fetchrows)["data"] ?? [];
}

function api_update_resource_type($resource, $type)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    return update_resource_type($resource, $type);
}

/**
 * {@see get_resource_path()}
 *
 * @param bool $write_metadata Specify if embedded metadata should be written to the requested file (if applicable).
 */
function api_get_resource_path(
    $ref,
    $not_used = null,
    $size = "",
    $generate = true,
    $extension = "jpg",
    $page = 1,
    $watermarked = false,
    $alternative = -1,
    bool $write_metadata = false
) {
    if ($write_metadata) {
        $GLOBALS['get_resource_path_extra_download_query_string_params'] = ['noattach' => ''];
    }

    if (!is_bool($generate)) {
    $generate = $generate !== 'false' && $generate !== '0';
    }

    # Set defaults
    if ($alternative == "") {
        $alternative = -1;
    }
    if ($page == "") {
        $page = 1;
    }

    $refs = json_decode($ref, true);
    if (!is_array($refs)) {
        $refs = [$refs];
    }

    $return = array();
    foreach ($refs as $ref) {
        $resource = get_resource_data($ref);
        if (!$resource || !is_numeric($ref) || !resource_download_allowed($ref, $size, $resource["resource_type"], $alternative)) {
            $return[$ref] = "";
            continue;
        }

        if (!$GLOBALS["hide_real_filepath"] && !$watermarked) {
            // Check if watermarks are required - handled at download when $hide_real_filepath = true;
            $GLOBALS['access'] = $resource['access'];
            $watermarked = check_use_watermark();
        }

        $return[$ref] = get_resource_path($ref, false, $size, $generate, $extension, -1, $page, $watermarked, '', $alternative, false);
        if ($GLOBALS["hide_real_filepath"]) {
            // Add a temporary key so the file can be accessed unauthenticated
            $accesskey = generate_temp_download_key($GLOBALS["userref"], $ref, $size);
            if ($accesskey !== "") {
                $return[$ref] .= "&access_key={$accesskey}";
            }
        }
    }
    return count($return) > 1 ? $return : reset($return);
}

function api_get_resource_data($resource)
{
    $resdata = get_resource_data($resource);

    // Check access
    $access = get_resource_access($resource);
    if ($access == 2) {
        return false;
    }


    if ($access == RESOURCE_ACCESS_INVALID_REQUEST) {
        return false;
    }

    // Remove column data from inaccessible fields
    $joins = get_resource_table_joins();
    foreach ($joins as $datajoin) {
        $joinfield = get_resource_type_field($datajoin);
        if ((!metadata_field_view_access($datajoin)) || ($access == 1 && $joinfield["hide_when_restricted"] == 1)) {
            unset($resdata["field" . $datajoin]);
        }
    }

    $resdata = process_resource_data_joins_values($resdata, $joins);
    return $resdata;
}

function api_put_resource_data($resource, array $data)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (is_null($data)) {
        return false;
    }
    return put_resource_data($resource, $data);
}

function api_get_alternative_files($resource, $order_by = "", $sort = "", $type = "")
{
    global $alt_files_visible_when_restricted;
    $access = get_resource_access($resource);

    if ($access == RESOURCE_ACCESS_INVALID_REQUEST) {
        return false;
    }

    if ($access != 0 && !($access == 1 && $alt_files_visible_when_restricted)) {
        return false;
    }

    return get_alternative_files($resource, $order_by, $sort, $type);
}

function api_get_resource_types()
{
    return get_resource_types("", true);
}

function api_add_alternative_file($resource, $name, $description = '', $file_name = '', $file_extension = '', $file_size = 0, $alt_type = '', $file = '')
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (0 < $resource && (!(get_edit_access($resource) || checkperm('A')))) {
        return false;
    }

    if ($file_extension != '' && is_banned_extension($file_extension)) {
        return false;
    }

    // Just insert record in the database
    if ('' == trim($file)) {
        return add_alternative_file($resource, $name, $description, $file_name, $file_extension, $file_size, $alt_type);
    }

    // A file has been specified so add it as alternative
    global $valid_upload_paths;
    $deletesourcefile = false;
    if (api_validate_upload_url($file)) {
        // Path is a url
        $upload_key = uniqid($resource . "_");
        $file = temp_local_download_remote_file($file, $upload_key);
        $deletesourcefile = true;
    } elseif (is_valid_upload_path($file, $valid_upload_paths)) {
        // Path is a file
        if (is_banned_extension(pathinfo($file, PATHINFO_EXTENSION))) {
            return false;
        }
    } elseif ($file != "") {
        // Couldn't validate path supplied
        return false;
    }

    if (trim($file_extension) == "") {
        $path_parts = pathinfo($file);
        $file_extension = $path_parts['extension'] ?? '';
    }

    $alternative_ref     = add_alternative_file($resource, $name, $description, $file_name, $file_extension, $file_size, $alt_type);
    $rs_alternative_path = get_resource_path($resource, true, '', true, $file_extension, -1, 1, false, '', $alternative_ref);

    if (!copy($file, $rs_alternative_path)) {
        return false;
    }

    chmod($rs_alternative_path, 0777);
    if ($deletesourcefile) {
        unlink($file);
    }
    $file_size = @filesize_unlimited($rs_alternative_path);

    ps_query("UPDATE resource_alt_files SET file_size= ?, creation_date = NOW() WHERE resource = ? AND ref = ?", ['s', $file_size, 's', $resource, 's', $alternative_ref]);

    global $alternative_file_previews_batch;
    if ($alternative_file_previews_batch) {
        create_previews($resource, false, $file_extension, false, false, $alternative_ref);
    }

    return $alternative_ref;
}

function api_delete_access_keys($access_keys, $resources, $collections)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    // Incoming parameters are csv strings; "-" entries denote a null resource or collection
    // The number of entries in each parameter is always the same
    $access_key_array = explode(",", $access_keys);
    $resource_array = explode(",", $resources);
    $collection_array = explode(",", $collections);

    for ($i = 0; $i < count($access_key_array); $i++) {
        if ($collection_array[$i] != "-") {
            debug("ACCESSKEY DELETING COL=" . $collection_array[$i] . " KEY=" . $access_key_array[$i]);
            delete_collection_access_key($collection_array[$i], $access_key_array[$i]);
        } else {
            debug("ACCESSKEY DELETING RES=" . $resource_array[$i] . " KEY=" . $access_key_array[$i]);
            delete_resource_access_key($resource_array[$i], $access_key_array[$i]);
        }
    }
    return true;
}

function api_delete_alternative_file($resource, $ref)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (0 < $resource && (!(get_edit_access($resource) || checkperm('A')))) {
        return false;
    }
    return delete_alternative_file($resource, $ref);
}

function api_upload_file($ref, $no_exif = false, $revert = false, $autorotate = false, $file_path = "")
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    $no_exif    = filter_var($no_exif, FILTER_VALIDATE_BOOLEAN);
    $revert     = filter_var($revert, FILTER_VALIDATE_BOOLEAN);
    $autorotate = filter_var($autorotate, FILTER_VALIDATE_BOOLEAN);

    // Check not over quota
    if (overquota()) {
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['disk_size_no_upload_explain']));
    }

    // Check for duplicates
    $duplicates = check_duplicate_checksum($file_path, false);
    if (count($duplicates) > 0) {
        $duplicates_string = implode(",", $duplicates);
        return "FAILED: The file for resource {$ref} was not uploaded. Resources {$duplicates_string} already have a matching file.";
    } else {
        $return = upload_file($ref, $no_exif, $revert, $autorotate, $file_path);
        if ($return === false) {
            return false;
        }
    }

    return $ref;
}

function api_upload_file_by_url($ref, $no_exif = false, $revert = false, $autorotate = false, $url = "")
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    $no_exif    = filter_var($no_exif, FILTER_VALIDATE_BOOLEAN);
    $revert     = filter_var($revert, FILTER_VALIDATE_BOOLEAN);
    $autorotate = filter_var($autorotate, FILTER_VALIDATE_BOOLEAN);

    if (!api_validate_upload_url($url)) {
        // URL failed validation
        return false;
    }

    // Check not over quota
    if (overquota()) {
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['disk_size_no_upload_explain']));
    }

    // Generate unique hash to use so that other uploads with the same name won't conflict
    $upload_key = uniqid((int)$ref . "_");
    $tmp_dld_fpath = temp_local_download_remote_file($url, $upload_key);

    if ($tmp_dld_fpath === false) {
        return "FAILED: The file for resource #{$ref} was not uploaded. Enable debug log and try again to identify why uploading it failed.";
    }

    $duplicates = check_duplicate_checksum($tmp_dld_fpath, false);
    if (count($duplicates) > 0) {
        $duplicates_string = implode(",", $duplicates);
        return "FAILED: The file for resource {$ref} was not uploaded. Resources {$duplicates_string} already have a matching file.";
    } else {
        $return = upload_file_by_url($ref, $no_exif, $revert, $autorotate, $tmp_dld_fpath, $upload_key);
        if ($return === false) {
            return false;
        }
    }

    return $ref;
}

function api_get_related_resources($ref)
{
    global $enable_related_resources;
    $access = get_resource_access($ref);
    if ($access == RESOURCE_ACCESS_INVALID_REQUEST) {
        return array();
    }

    if (!$enable_related_resources || $access == 2) {
        return array();
    }
    return get_related_resources($ref);
}

function api_get_field_options($ref, $nodeinfo = false)
{
    if (!is_numeric($ref)) {
        // Name may have been passed
        $ref = ps_value("select ref value from resource_type_field where name= ?", ['s',$ref], "", "schema");
    }

    if (!metadata_field_view_access($ref)) {
        return false;
    }

    return get_field_options($ref, $nodeinfo);
}

function api_get_nodes($ref, $parent = null, $recursive = false, $offset = null, $rows = null, $name = "", $use_count = false, $order_by_translated_name = false)
{
    // Check access to field.
    if (!metadata_field_view_access($ref)) {
        return false;
    }

    return get_nodes($ref, $parent, $recursive, $offset, $rows, $name, $use_count, $order_by_translated_name);
}

function api_get_user_collections()
{
    global $userref;
    if (checkperm("b")) {
        return array();
    }
    return get_user_collections($userref);
}

function api_add_resource_to_collection($resource, $collection = '', $search = '')
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $usercollection;
    if ($collection == '') {
        $collection = $usercollection;
    }
    return add_resource_to_collection($resource, $collection, false, '', '', null, null, $search);
}

function api_collection_add_resources($collection = '', $resources = '', $search = '', $selected = false)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $usercollection;
    if ($collection == '') {
        $collection = $usercollection;
    }
    return collection_add_resources($collection, $resources, $search, $selected);
}

function api_remove_resource_from_collection($resource, $collection = '')
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $usercollection;
    if ($collection == '') {
        $collection = $usercollection;
    }
    return remove_resource_from_collection($resource, $collection);
}

function api_collection_remove_resources($collection = '', $resources = '', $removeall = false, $selected = false)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $usercollection;
    if ($collection == '') {
        $collection = $usercollection;
    }
    return collection_remove_resources($collection, $resources, $removeall, $selected);
}

function api_create_collection($name, $forupload = false)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $userref, $collection_allow_creation;
    if (!can_create_collections()) {
        return false;
    }
    if ($forupload && trim($name) == "") {
        # Do not translate this string, the collection name is translated when displayed!
        $name = "Upload " . offset_user_local_timezone(date('YmdHis'), 'YmdHis');
    }

    return create_collection($userref, $name);
}

function api_delete_collection($ref)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (checkperm("b") || !collection_writeable($ref)) {
        return false;
    }
    return delete_collection($ref);
}

function api_search_public_collections($search = "", $order_by = "name", $sort = "ASC", $exclude_themes = true, $exclude_public = false)
{
    $exclude_themes = filter_var($exclude_themes, FILTER_VALIDATE_BOOLEAN);
    $exclude_public = filter_var($exclude_public, FILTER_VALIDATE_BOOLEAN);
    $results = search_public_collections($search, $order_by, $sort, $exclude_themes, $exclude_public);
    $resultcount = count($results);
        {
    for ($n = 0; $n < $resultcount; $n++) {
        if (is_array($results[$n])) {
            $results[$n] = array_map("i18n_get_translated", $results[$n]);
        }
    }
        }
    return $results;
}

function api_set_node($ref, $resource_type_field, $name, $parent = '', $order_by = 0, $returnexisting = false)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $FIXED_LIST_FIELD_TYPES;

    $fieldinfo = get_resource_type_field($resource_type_field);
    if (
        !in_array($fieldinfo['type'], $FIXED_LIST_FIELD_TYPES)
        ||
        !(checkperm('a') || checkperm('k') || ($fieldinfo['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && !checkperm('bdk' . $resource_type_field)))
    ) {
        // API user doesn't have permission to add new nodes
        return false;
    }
    if (strtoupper($ref) == 'NULL') {
        $ref = null;
    }
    if (strtoupper($parent) == 'NULL') {
        $parent = null;
    }
    return set_node($ref, $resource_type_field, $name, $parent, $order_by);
}

function api_add_resource_nodes($resource, $nodestring)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    // This is only for super admins
    if (!checkperm('a')) {
        return false;
    }
    $nodes = explode(",", $nodestring);
    if (!add_resource_nodes($resource, $nodes)) {
        return false;
    }

    # If this is a 'joined' field we need to add it to the resource column
    $joins = get_resource_table_joins();
    $joined_fields_to_update = array();
    foreach ($nodes as $newnode) {
        $returned_node = array();
        if (!get_node($newnode, $returned_node)) {
            return false;
        }
        if (in_array($returned_node['resource_type_field'], $joins) && !in_array($returned_node['resource_type_field'], $joined_fields_to_update)) {
            $joined_fields_to_update[] = $returned_node['resource_type_field'];
        }
    }
    foreach ($joined_fields_to_update as $field_update) {
        // get_data_by_field() always returns the value separated by ", " when flattening so we have to ensure it's stored
        // using the field_column_string_separator in the data_joins (ie fieldX) columns
        $resource_node_data = str_replace(', ', $GLOBALS['field_column_string_separator'], get_data_by_field($resource, $field_update));
        update_resource_field_column($resource, $field_update, $resource_node_data);
    }

    return true;
}

function api_add_resource_nodes_multi($resources, $nodestring)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    // This is only for super admins
    if (!checkperm('a')) {
        return false;
    }
    $resourcearr = explode(",", $resources);
    $nodes = explode(",", $nodestring);
    return add_resource_nodes_multi($resourcearr, $nodes, false, true);
}

function api_resource_log_last_rows($minref = 0, $days = 7, $maxrecords = 0, string $field = '', string $log_code = '')
{
    $fields = explode(',', $field);
    $log_codes = explode(',', $log_code);
    return resource_log_last_rows($minref, $days, $maxrecords, $fields, $log_codes);
}

function api_get_resource_all_image_sizes($resource)
{
    $sizes = get_resource_all_image_sizes($resource);
    if ($GLOBALS["hide_real_filepath"]) {
        // Add a temporary key so the file can be accessed unauthenticated
        for ($n = 0; $n < count($sizes); $n++) {
            if ($sizes[$n]['size_code'] == 'original') {
                $size_id = '';
            } else {
                $size_id = $sizes[$n]['size_code'];
            }

            $accesskey = generate_temp_download_key($GLOBALS["userref"], $resource, $size_id);
            if ($accesskey !== "") {
                $sizes[$n]["url"] .= "&access_key={$accesskey}";
            }
        }
    }
    // Remove the path elements
    array_walk($sizes, function (&$size) {
        unset($size["path"]);
    });
    return $sizes;
}

function api_get_node_id($value, $resource_type_field)
{
    if (!metadata_field_view_access($resource_type_field)) {
        return false;
    } # Need at least view access to the field.

    return get_node_id($value, $resource_type_field);
}
function api_replace_resource_file($ref, $file_location, $no_exif = false, $autorotate = false, $keep_original = true)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $rse_version_block, $plugins, $usergroup,$rse_version_override_groups, $replace_resource_preserve_option,
            $valid_upload_paths;
    $no_exif    = filter_var($no_exif, FILTER_VALIDATE_BOOLEAN);
    $autorotate = filter_var($autorotate, FILTER_VALIDATE_BOOLEAN);
    $keep_original = filter_var($keep_original, FILTER_VALIDATE_BOOLEAN);
    $generic_err_msg = [
        "Status" => "FAILED",
        "Message" => "Resource not replaced. Refer to ResourceSpace system administrator",
    ];

    $file_location_parts = pathinfo($file_location);

    if (is_valid_upload_path($file_location_parts["dirname"], $valid_upload_paths)) {
        if (is_banned_extension(pathinfo($file_location_parts["basename"], PATHINFO_EXTENSION))) {
            return array("Status" => "FAILED","Message" => "The file for resource {$ref} was not replaced. File {$file_location} is invalid.");
        }
    } elseif (api_validate_upload_url($file_location)) {
        if (overquota()) {
            return ajax_response_fail(ajax_build_message($GLOBALS['lang']['disk_size_no_upload_explain']));
        }
        $upload_key = uniqid((int) $ref . "_");
        $tmp_file_location = temp_local_download_remote_file($file_location, $upload_key);
        if ($tmp_file_location === false) {
            return ["Status" => "FAILED", "Message" => "The file for resource {$ref} was not replaced. The file could not be retrieved from {$file_location}"];
        }
        $file_location = $tmp_file_location;
    } else {
        return array("Status" => "FAILED","Message" => "The file for resource {$ref} was not replaced. File location {$file_location} is invalid.");
    }

    $duplicates = check_duplicate_checksum($file_location, false);
    if (count($duplicates) > 0) {
        $duplicates_string = implode(",", $duplicates);
        return array("Status" => "FAILED","Message" => "The file for resource {$ref} was not replaced. Resources {$duplicates_string} already have a matching file.");
    } else {
        if (!$keep_original) {
            if (in_array("rse_version", $plugins) && (!isset($rse_version_override_groups) || !in_array($usergroup, $rse_version_override_groups))) {
                return array("Status" => "FAILED","Message" => "Invalid option 'keep_original=false'. Original file must be preserved because versioning is enabled.");
            }
            // Set flag that we want to override the versioning behaviour of the rse_version plugin
            $rse_version_block = true;
        } else {
            // Set global otion so that this is not dependent on config
            $replace_resource_preserve_option = true;
        }

        $GLOBALS["use_error_exception"] = true;
        try {
            $success = replace_resource_file($ref, $file_location, $no_exif, $autorotate, $keep_original);
        } catch (Throwable $t) {
            debug(
                sprintf(
                    '[api_replace_resource_file] Failed to replace resource %s file with %s. Reason: %s',
                    $ref,
                    $file_location,
                    $t->getMessage()
                )
            );
            unset($GLOBALS["use_error_exception"]);
            return $generic_err_msg;
        }
        unset($GLOBALS["use_error_exception"]);

        if (!$success) {
            return $generic_err_msg;
        }
    }

    return array("Status" => "SUCCESS","Message" => "Resource ID #$ref replaced");
}


/**
* API binding to get_data_by_field function.
*
* @param integer $ref   Resource ref
* @param integer $field Resource type field ref
*
* @return boolean|array
*/
function api_get_data_by_field($ref, $field)
{
    // Security: Check resource access, if not accessible to user, return FALSE.
    $access = get_resource_access($ref);
    if ($access == 2 || $access == RESOURCE_ACCESS_INVALID_REQUEST) {
        return false;
    }

    // Get the data for a specific field for a specific resource.
    $results = metadata_field_view_access($field) ? get_data_by_field($ref, $field) : false;

    if (is_array($results)) {
        $results_count = count($results);
        for ($n = 0; $n < $results_count; $n++) {
            $results[$n] = array_map("i18n_get_translated", $results[$n]);
        }
    }

    return $results;
}


/**
* API binding to modified get_resource_collections function, as we only want to pass collection ID, name, and
* description for security purposes.
*
* @param integer $ref Resource ref
*
* @return boolean|array
*/
function api_get_resource_collections($ref)
{
    // Security: Check for numeric input value; otherwise, return FALSE.
    if (!is_numeric($ref)) {
        return false;
    }

    // Get all the collections a resource is part of.
    $results = get_resource_collections($ref);

    // Create a new array containing only the collection ID, name, and description fields.
    if (is_array($results)) {
        $ref_collections = [];
        $results_count = count($results);

        for ($n = 0; $n < $results_count; $n++) {
            $ref_collections[$n]["ref"] = $results[$n]["ref"];
            $ref_collections[$n]["name"] = $results[$n]["name"];
            $ref_collections[$n]["description"] = $results[$n]["description"];
        }

        for ($n = 0; $n < $results_count; $n++) {
            $ref_collections[$n] = array_map("i18n_get_translated", $ref_collections[$n]);
        }
    } else // Resource is not part of a collection or other error.
        {
        return false;
    }

    return $ref_collections;
}

function api_update_related_resource($ref, $related, $add = 1)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $enable_related_resources;
    if (!$enable_related_resources) {
        return false;
    }

    $related = explode(",", $related);

    if (!is_numeric($add)) {
        return false;
    }

    $addboolean = null;
    if ((int) $add === 1) {
        $addboolean = true;
    } elseif ((int) $add === 0) {
        $addboolean = false;
    } else {
        return false;
    }

    return update_related_resource($ref, $related, $addboolean);
}

function api_get_collections_resource_count(string $refs)
{
    if (checkperm('b')) {
        return [];
    }

    $cols = array_filter(explode(',', $refs), 'collection_readable');

    return get_collections_resource_count($cols);
}

function api_get_users($find = "", $exact_username_match = false)
{
    // Forward to the internal function - with "usepermissions" locked to TRUE.
    // Return specific columns only as there's sensitive information in the others such as password/session key.
    return get_users(0, $find, "u.username", true, -1, "", false, "u.ref,u.username,u.email,u.fullname,u.usergroup", $exact_username_match);
}

function api_save_collection(int $ref, array $coldata)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (checkperm("b")) {
        return false;
    }

    // DO NOT REMOVE - this is to prevent bypassing allowed coldata. save_collection() uses getvals if coldata is empty!
    if (empty($coldata)) {
        return false;
    }

     // Security control - only limited data is allowed to be set
     $coldata = array_intersect_key(
         $coldata,
         [
                'keywords' => 0,
                'allow_changes' => 0,
                'users' => 0,
                'name' => 0,
                'public' => 0,
                'type' => 0,
                'force_featured_collection_type' => 0,
                'parent' => 0,
                'thumbnail_selection_method' => 0,
                'bg_img_resource_ref' => 0,
            ]
     );
    // Only certain collection types can be edited via the API
    if (
        isset($coldata["type"])
        && !in_array(
            $coldata["type"],
            array(
                    COLLECTION_TYPE_STANDARD,
                    COLLECTION_TYPE_FEATURED,
                    COLLECTION_TYPE_PUBLIC)
        )
    ) {
        return false;
    }

    $fct_return = save_collection($ref, $coldata);
    return is_null($fct_return) ? true : $fct_return;
}

function api_get_collection(int $ref)
{
    // Only work for admin access for now - TO DO: incorporate permissions check within get_collections() internal function and remove the basic admin-only check here.
    if (!checkperm("a")) {
        return false;
    }
    return get_collection($ref);
}

function api_send_user_message($users, $text)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    return send_user_message($users, $text);
}

function api_get_profile_image($user)
{
    return get_profile_image($user);
}

function api_get_system_status($basic = false)
{
    return get_system_status($basic);
}

function api_relate_all_resources($related)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    global $enable_related_resources;
    if (!$enable_related_resources) {
        return false;
    }
    if (!is_array($related)) {
        $related = explode(",", $related);
    }
    return relate_all_resources($related);
}

function api_show_hide_collection($collection, $show, $user)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    return show_hide_collection($collection, $show, $user);
}

function api_send_collection_to_admin($collection)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    return send_collection_to_admin($collection);
}

function api_reorder_featured_collections($refs)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (can_reorder_featured_collections()) {
        sql_reorder_records('collection', $refs);
        log_activity('via API - reorder_featured_collections', LOG_CODE_REORDERED, implode(', ', $refs), 'collection');
        return true;
    }

    http_response_code(403);
    return false;
}

function api_get_dash_search_data($link, $promimg)
{
    return get_dash_search_data($link, $promimg);
}

function api_reorder_tabs($refs)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (acl_can_manage_tabs()) {
        sql_reorder_records('tab', $refs);
        return true;
    }

    http_response_code(403);
    return false;
}

function api_delete_tabs($refs)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (acl_can_manage_tabs()) {
        return delete_tabs($refs);
    }

    http_response_code(403);
    return false;
}

function api_save_tab($tab)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (acl_can_manage_tabs()) {
        if (save_tab($tab)) {
            $tab = get_tabs_by_refs([$tab['ref']])[0];
            $tab['name_translated'] = i18n_get_translated($tab['name']);
            return ajax_response_ok($tab);
        }

        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_fail_save']));
    }

    http_response_code(403);
    return false;
}

function api_mark_email_as_invalid($email)
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return $assert_post;
    }

    if (!checkperm('a')) {
        return false;
    }

    return mark_email_as_invalid($email);
}

function api_get_user_message($ref)
{
    return get_user_message($ref);
}

function api_get_users_by_permission($permissions)
{
    if (!is_array($permissions)) {
        $permissions = explode(",", $permissions);
    }
    return get_users_by_permission($permissions);
}

/**
 * Upload files using HTTP multipart.
 *
 * @param int $ref Resource ID
 * @param bool $no_exif Do not extract embedded metadata
 * @param bool $revert Delete all data and re-extract embedded data
 * @param bool $previewonly Will use the uploaded file to replace preview image only
 * @param int $alternative Use the uploaded file to replace the alternative file with the given ID
 *            Note that api_add_alternative_file() must be called first if creating a new alternative file
 *            If an $alternative identifier is specified then $previewonly is ignored and set to false
 *
 * @return array Returns JSend data back {@see ajax_functions.php} if upload failed, otherwise 204 HTTP status
 */
function api_upload_multipart(int $ref, bool $no_exif, bool $revert, bool $previewonly = false, int $alternative = 0): array
{
    $request_checks = [
        fn(): array => assert_post_request(true),
        fn(): array => assert_content_type('multipart/form-data', $_SERVER['CONTENT_TYPE'] ?? ''),
        // Ensure a "file" has been POSTd
        function (): array {
            http_response_code(400);
            return isset($_FILES['file'])
                ? []
                : ajax_response_fail(ajax_build_message(
                    str_replace('%key', 'file', $GLOBALS['lang']['error-request-missing-key'])
                ));
        },
        // Check file has been received
        function (): array {
            if ($_FILES['file']['error'] === UPLOAD_ERR_INI_SIZE) {
                http_response_code(413);
                return ajax_response_fail(ajax_build_message(
                    sprintf($GLOBALS['lang']['plupload-maxfilesize'], ini_get('upload_max_filesize'))
                ));
            } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                return ajax_response_fail(ajax_build_message(
                    sprintf(
                        '(%s #%s) %s',
                        $GLOBALS['lang']['error'],
                        $_FILES['file']['error'],
                        $GLOBALS['lang']['upload_error_unknown']
                    )
                ));
            } else {
                return [];
            }
        },
    ];
    foreach ($request_checks as $check) {
        $check_result = $check();
        if (!empty($check_result)) {
            return $check_result;
        }
    }

    // Check not over quota
    if (overquota()) {
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['disk_size_no_upload_explain']));
    }

    if ($alternative > 0) {
        $previewonly = false;
    }
    // Set the userfile so upload_file can carry out the rest of the work as usual
    $_FILES['userfile'] = $_FILES['file'];

    if (!get_edit_access($ref)) {
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error-permissiondenied']));
    }

    if ($previewonly) {
        if (!can_upload_preview_image($ref)) {
            return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error-permissiondenied']));
        }
        $success = upload_preview($ref);
        if ($success) {
            http_response_code(204);
            return ajax_response_ok_no_data();
        } else {
            http_response_code(500);
            return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_upload_failed']));
        }
    } elseif ($alternative > 0) {
        if (checkperm('A')) {
            // This is a negative permission. If you have it you can't manage alternative files
            return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error-permissiondenied']));
        }
        // Check this alternative is for the correct resource
        $alternatives = get_alternative_files($ref);

        if (!in_array($alternative, array_column($alternatives, "ref"))) {
            return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_invalid_input']));
        }
        if (!can_upload_preview_image($ref)) {
            return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error-permissiondenied']));
        }
        $processfile = $_FILES['userfile'];
        $extension = pathinfo($processfile['name'])["extension"] ?? "";
        if (is_banned_extension($extension)) {
            http_response_code(403);
            return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_upload_invalid_file']));
        }

        $altpath = get_resource_path($ref, true, "", true, $extension, -1, 1, false, "", $alternative);
        if (move_uploaded_file($processfile['tmp_name'], $altpath)) {
            chmod($altpath, 0777);
            $file_size = filesize_unlimited($altpath);
            // Update alternative file data.
            $altdata = [
                "name" => (string) $processfile["name"],
                "file_name" => (string) $processfile["name"],
                "file_extension" => (string) $extension,
                "file_size" => (int) $file_size,
            ];
            save_alternative_file($ref, $alternative, $altdata);
            create_previews($ref, false, $extension, false, false, $alternative);
            http_response_code(204);
            return ajax_response_ok_no_data();
        }

        http_response_code(500);
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_upload_failed']));
    } else {
        // Main resource file upload
        if ($GLOBALS['file_upload_block_duplicates']) {
            $duplicates = check_duplicate_checksum($_FILES['file']['tmp_name'], false);
            if (count($duplicates) > 0) {
                return ajax_response_fail(ajax_build_message(
                    str_replace('[resources]', implode(', ', $duplicates), $GLOBALS['lang']['error_upload_duplicate_file'])
                ));
            }
        }
        if (upload_file($ref, $no_exif, $revert)) {
            http_response_code(204);
            return ajax_response_ok_no_data();
        }
    }

    http_response_code(500);
    return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_upload_failed']));
}

/**
 * Get metadata field information for all (matching) fields.
 *
 * @param string $by_resource_types Filter result by resource type. If multiple, use a CSV of resource types.
 * @param string $find Filter result by fuzzy searching in different properties (e.g name, title, ref, help text etc)
 * @param string $by_types Filter result by field type ({@see FIELD_TYPE_* constants}). If multiple, use a CSV of field types.
 *
 * @return array Returns the matching fields' information or 403 HTTP status if not authorised
 */
function api_get_resource_type_fields(string $by_resource_types = '', string $find = '', string $by_types = ''): array
{
    if (!checkperm('a')) {
        http_response_code(403);
        return [];
    }

    return array_map(
        'execution_lockout_remove_resource_type_field_props',
        get_resource_type_fields(
            parse_csv_to_list_of_type($by_resource_types, 'is_int_loose'),
            'ref',
            'asc',
            trim($find),
            parse_csv_to_list_of_type($by_types, 'is_int_loose'),
            true
        )
    );
}

/**
 * Create metadata field
 *
 * @param string $name Field name
 * @param string $resource_types CSV of applicable resource types for this field. Use 0 (zero) for global, for others
 *                               {@see API get_resource_types()}
 * @param int $type Metadata field type. For values, {@see FIELD_TYPE_* constants}
 * @return array Returns JSend data back {@see ajax_functions.php} and 200 HTTP status or 403 HTTP status if not authorised
 */
function api_create_resource_type_field(string $name, string $resource_types, int $type): array
{
    if (!checkperm('a')) {
        http_response_code(403);
        return [];
    }

    /** @var int|array $parse_rt_csv */
    $parse_rt_csv = function (string $RT) {
        // Parse CSV to ordered list of integers
        $parse_input = parse_csv_to_list_of_type($RT, 'is_int_loose');
        $parse_input = array_map('intval', $parse_input);
        asort($parse_input, SORT_NUMERIC);
        $parse_input = array_values($parse_input);

        // Global field? (ie resource type = 0)
        $rev = array_reverse($parse_input);
        return array_pop($rev) === 0 ? 0 : $parse_input;
    };

    $ref = create_resource_type_field($name, $parse_rt_csv($resource_types), $type, '', true);
    return $ref !== false
        ? ajax_response_ok(['ref' => $ref])
        : ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_fail_save']));
}

/**
 * Expose {@see get_featured_collections} to the API
 * @param int $parent The feature collection parent's ref. Use 0 for obtaining the root ones.
 */
function api_get_featured_collections($parent): array
{
    return is_int_loose($parent) ? get_featured_collections($parent, []) : [];
}

function api_get_edit_access(int $resource): bool
{
    return get_edit_access($resource);
}

/**
 * Toggle active state for nodes
 * @see https://www.resourcespace.com/knowledge-base/developers/fixed-list-fields
 * @param array $refs List of node IDs
 * @return array Returns the affected nodes' active state (including empty list) and 403 HTTP status if not authorised.
 */
function api_toggle_active_state_for_nodes(array $refs): array
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if ($assert_post !== []) {
        return $assert_post;
    }

    if (checkperm('k')) {
        return toggle_active_state_for_nodes($refs);
    }

    http_response_code(403);
    return [];
}

/**
 * Expose {@see get_processing_message} to the API
 *
 * @return false|array
 */
function api_get_processing_message()
{
    return get_processing_message();
}

/**
 * Expose {@see checkperm} to the API
 *
 * @param string $perm  The permissions string to check for.
 * @return bool
 */
function api_checkperm($perm)
{
    return checkperm($perm);
}

/**
 * Expose {@see get_resource_access} to the API
 *
* @param int $resource The reference ID of the resource.
* @return false|int The access level for the resource, or false if a number was not supplied.
*/
function api_get_resource_access($resource)
{
    if (!is_int_loose($resource)) {
        return false;
    }
    return get_resource_access($resource);
}

/**
 * Exposing {@see resource_file_readonly} to the API
 * @param int|numeric-string $ref Resource ID
 * @return array{status:"success","data":{"readonly":bool}}|array{status:"fail","data":{"message":string}}
 */
function api_resource_file_readonly($ref): array
{
    if (is_positive_int_loose($ref)) {
        return ajax_response_ok(['readonly' => resource_file_readonly($ref)]);
    } else {
        http_response_code(400);
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_resource_id_non_numeric']));
    }
}

/**
 * Exposing {@see delete_resources_in_collection} to the API
 *
 * @param  int  $collection   ID of collection containing resources to be deleted.
 */
function api_delete_resources_in_collection($collection): bool
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if (!empty($assert_post)) {
        return false;
    }

    return delete_resources_in_collection($collection);
}


/**
 * Exposing {@see new_user} to the API
 *
 * @param string $username  - username to create
 * @param integer $usergroup  - optional usergroup to assign
 *
 * @return array Returns JSend data back {@see ajax_functions.php} with the ref value (id of new user or false if user
 * already exists / permission denied, or -2 if user limit reached) and 200 HTTP status or 409 HTTP status if an issue
 * is detected. 
 */
function api_new_user($username, $usergroup = 0): array
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if ($assert_post !== []) {
        return $assert_post;
    }

    if (!checkPermission_manage_users() || !can_set_admin_usergroup($usergroup)) {
        http_response_code(403);
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error-permissiondenied']));
    }

    $ref = new_user($username, $usergroup);
    $payload = ['ref' => $ref];

    if ($ref > 0) {
        return ajax_response_ok($payload);
    }

    http_response_code(409);
    return ajax_response_fail($payload);
}

/**
 * Exposing {@see save_user} to the API
 * 
 * @param string $ref ID of the user
 * @param string  $data Data to save in JSON format (optional, will use posted data otherwise)
 *
 * @return array Returns JSend data back {@see ajax_functions.php} and 200 HTTP status or 409 HTTP status with reason in
 * the response
 */
function api_save_user(int $ref, string $data): array
{
    $assert_post = assert_post_request(defined('API_AUTHMODE_NATIVE'));
    if ($assert_post !== []) {
        return $assert_post;
    }

    $save_status = save_user($ref, json_decode($data, true));

    if ($save_status === true) {
        return ajax_response_ok_no_data();
    }

    http_response_code(409);
    return ajax_response_fail(ajax_build_message($save_status));
}
