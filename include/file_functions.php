<?php

/**
 * Ensures the filename cannot leave the directory set.
 * Only to be used for internal ResourceSpace paths as only a limited character set is supported
 *
 * @param string $name
 * @return string
 */
function safe_file_name($name)
{
    // Returns a file name stripped of all non alphanumeric values
    // Spaces are replaced with underscores
    $alphanum = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
    $name = str_replace(' ', '_', $name);
    $newname = '';

    for ($n = 0; $n < strlen($name); $n++) {
        $c = substr($name, $n, 1);
        if (strpos($alphanum, $c) !== false) {
            $newname .= $c;
        }
    }

    // Set to 250 to allow for total length to be below 255 limit including filename and extension
    $newname = mb_substr($newname, 0, 250);

    return $newname;
}

/**
* Generate a UID for filnames that can be different from user to user (e.g. contact sheets)
*
* @param integer $user_id
*
* @return string
*/
function generateUserFilenameUID($user_id)
{
    if (!is_numeric($user_id) || 0 >= $user_id) {
        trigger_error('Bad parameter for generateUserFilenameUID()!');
    }

    global $rs_session, $scramble_key;

    $filename_uid = '';

    if (isset($rs_session)) {
        $filename_uid .= $rs_session;
    }

    $filename_uid .= $user_id;

    return substr(hash('sha256', $filename_uid . $scramble_key), 0, 15);
}

/**
* Checks if a path is part of a whitelisted list of paths. This applies to both folders and files.
*
* Note: the function is not supposed to check/ validate the syntax of the path (ie. UNIX/ Windows)
*
* @param  string  $path               Path which is going to be checked against whitelisted paths
* @param  array   $whitelisted_paths  List of whitelisted paths
*
* @return boolean
*/
function isPathWhitelisted($path, array $whitelisted_paths)
{
    foreach ($whitelisted_paths as $whitelisted_path) {
        if (substr_compare($whitelisted_path, $path, 0, strlen($path)) === 0) {
            return true;
        }
    }

    return false;
}

/**
* Return a checksum for the given file path.
*
* @param  string  $path     Path to file
* @param  bool  $forcefull  Force use of whole file and ignore $file_checksums_50k setting
*
* @return string|false Return the checksum value, false otherwise.
*/
function get_checksum($path, $forcefull = false)
{
    debug("get_checksum( \$path = {$path} );");
    global $file_checksums_50k;
    if (!is_readable($path)) {
        return false;
    }

    # Generate the ID
    if ($file_checksums_50k && !$forcefull) {
        # Fetch the string used to generate the unique ID
        $use = filesize_unlimited($path) . "_" . file_get_contents($path, false, null, 0, 50000);
        $checksum = md5($use);
    } else {
        $checksum = md5_file($path);
    }
    return $checksum;
}

/**
 * Download remote file to the temp filestore location.
 *
 * @param string $url Source URL
 * @param string $key Optional key to use - to prevent conflicts when simultaneous calls use same file name
 *
 * @return string|bool Returns the new temp filestore location or false otherwise.
 */
function temp_local_download_remote_file(string $url, string $key = "")
{
    $userref = $GLOBALS['userref'] ?? 0;
    if ($userref === 0) {
        return false;
    }

    if ($key != "" && preg_match('/\W+/', $key) !== 0) {
        // Block potential path traversal - allow only word characters.
        return false;
    }

    $url = trim($url);
    $url_original = $url;
    // Remove query string from URL
    $url = explode('?', $url);
    $url = reset($url);

    $path_parts = pathinfo(basename($url));
    $filename = safe_file_name($path_parts['filename'] ?? '');
    $extension = $path_parts['extension'] ?? '';
    $filename .= ($extension !== '' ? ".{$extension}" : '');

    // When the filename isn't valid, try and get from the HTTP header
    $check_in_header = strpos($filename, ".") === false && filter_var($url_original, FILTER_VALIDATE_URL);
    foreach ($GLOBALS['valid_upload_remote_sources'] as $valid_upload_remote_src) {
        // Support dynamic remote URL that may otherwise be mistaken with a file (e.g. pages/download.php)
        if (url_starts_with($valid_upload_remote_src, $url_original)) {
            $check_in_header = true;
            break;
        }
    }

    if ($check_in_header) {
        $urlinfo = parse_url($url);
        if (!isset($urlinfo["scheme"]) || !in_array($urlinfo["scheme"], ["http","https"])) {
            return false;
        }

        $headers = get_headers($url_original, true);
        foreach ($headers as $header => $headervalue) {
            if (
                strtolower($header) == "content-disposition"
                // Check for double quotes first (e.g. attachment; filename="O'Malley's Bar.pdf")
                // OR Check for single quotes (e.g. attachment; filename='Space Travel.jpg')
                // OR Get file name up to first space
                &&
                (
                    preg_match('/.*filename=[\"]([^\"]+)/', $headervalue, $matches)
                    || preg_match('/.*filename=[\']([^\']+)/', $headervalue, $matches)
                    || preg_match("/.*filename=([^ ]+)/", $headervalue, $matches)
                )
            ) {
                    $filename = $matches[1];
            }
        }

        $extension = pathinfo(basename($filename), PATHINFO_EXTENSION);
        $filename = safe_file_name(pathinfo(basename($filename), PATHINFO_FILENAME)) . ".{$extension}";
    }

    if (is_banned_extension($extension)) {
        debug('[temp_local_download_remote_file] WARN: Banned extension for ' . $filename);
        return false;
    }

    // Get temp location
    $tmp_uniq_path_id = $userref . "_" . $key . generateUserFilenameUID($userref);
    $tmp_dir = get_temp_dir(false) . "/remote_files/" . $tmp_uniq_path_id;
    if (!is_dir($tmp_dir)) {
        mkdir($tmp_dir, 0777, true);
    }
    $tmp_file_path = $tmp_dir . "/" . $filename;
    if ($tmp_file_path == $url) {
        // Already downloaded earlier by API call
        return $tmp_file_path;
    }

    // Download the file
    $GLOBALS['use_error_exception'] = true;
    try {
        if (copy($url_original, $tmp_file_path)) {
            return $tmp_file_path;
        }
    } catch (Throwable $t) {
        debug(sprintf(
            'Failed to download remote file from "%s" to temp location "%s". Reason: %s',
            $url_original,
            $tmp_file_path,
            $t->getMessage()
        ));
    }
    unset($GLOBALS['use_error_exception']);

    return false;
}

/**
 * Basic check of uploaded file against list of allowed extensions
 *
 * @param array{name: string} $uploadedfile An element from the $_FILES PHP reserved variable
 * @param array $validextensions Array of valid extension strings
 */
function check_valid_file_extension(array $uploadedfile, array $validextensions): bool
{
    $extension = parse_filename_extension($uploadedfile['name']);
    return in_array(strtolower($extension), array_map("strtolower", $validextensions)) && !is_banned_extension($extension);
}

/**
 * Is the given extension in the list of blocked extensions?
 * Also ensures extension is no longer than 10 characters due to resource.file_extension database column limit
 *
 * @param  string    $extension - file extension to check
 */
function is_banned_extension(string $extension): bool
{
    return !(
        preg_match('/^[a-zA-Z0-9_-]{1,10}$/', $extension) === 1
        && !in_array(mb_strtolower($extension), array_map('mb_strtolower', $GLOBALS['banned_extensions']))
    );
}

/**
 * Remove empty folder from path to file. Helpful to remove a temp directory once the file it was created to hold no longer exists.
 * This function should be called only once the directory to be removed is empty.
 *
 * @param   string   $path_to_file   Full path to file in filestore.
 *
 * @return void
 */
function remove_empty_temp_directory(string $path_to_file = "")
{
    if ($path_to_file != "" && !file_exists($path_to_file)) {
        $tmp_path_parts = pathinfo($path_to_file);
        $path_to_folder = str_replace(DIRECTORY_SEPARATOR . $tmp_path_parts['basename'], '', $path_to_file);
        rmdir($path_to_folder);
    }
}

/**
 * Confirm upload path is one of valid paths.
 *
 * @param   string   $file_path            Upload path.
 * @param   array    $valid_upload_paths   Array of valid upload paths to test against.
 *
 * @return  bool     true when path is valid else false
 */
function is_valid_upload_path(string $file_path, array $valid_upload_paths): bool
{
    $orig_use_error_exception_val = $GLOBALS['use_error_exception'] ?? false;
    $GLOBALS["use_error_exception"] = true;
    try {
        $file_path = realpath($file_path);
    } catch (Exception $e) {
        debug("Invalid file path specified" . $e->getMessage());
        return false;
    }
    $GLOBALS['use_error_exception'] = $orig_use_error_exception_val;

    foreach ($valid_upload_paths as $valid_upload_path) {
        if (is_dir($valid_upload_path)) {
            $checkpath = realpath($valid_upload_path);
            if (strpos($file_path, $checkpath) === 0) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Validate the files on disk that are associated with the given resources
 *
 * @param array $resources      Array of resource IDs or
 *                              array of resource data e.g, from search results
 * @param array $criteria       Array with an array of callables for each resource with the
 *                              required return values in order to pass the check e.g.
 *                              'file_exists" =>true for a file presence only check
 *
 * @return array $results       An array with resource ID as the index and the results of the check as the value (boolean)
 *                              e.g. ["1234" => true, "1235" => false]
 */
function validate_resource_files(array $resources, array $criteria = []): array
{
    $checkresources = isset($resources[0]["ref"]) ? $resources : get_resource_data_batch($resources);
    $results = [];
    foreach ($checkresources as $resource) {
        if (!is_int_loose($resource["ref"])) {
            $results[$resource["ref"]] = false;
            continue;
        }
        $filepath = get_resource_path($resource["ref"], true, '', false, $resource["file_extension"] ?? "jpg");
        $results[$resource["ref"]] = false;
        foreach ($criteria as $criterion => $expected) {
            if (!is_callable($criterion)) {
                $results[$resource["ref"]] = false;
                // Not a valid check
                continue 2;
            }
            $cscheck = $expected === "%RESOURCE%file_checksum";
            if (substr($expected, 0, 10) == "%RESOURCE%") {
                // $expected is a resource table column
                $expected = $resource[substr($expected, 10)];
            }
            $testresult = call_user_func($criterion, $filepath);
            if ($cscheck && ($expected === null || $expected === "")) {
                // No checksum is currently recorded. Update it now that it's been calculated
                $results[$resource["ref"]] = true;
                debug("Skipping checksum check for resource " . $resource["ref"] . " - no existing checksum");
                ps_query("UPDATE resource SET file_checksum = ? WHERE ref = ?", ['s', $testresult, 'i', $resource["ref"]]);
                continue;
            }

            $results[$resource["ref"]] = $testresult === $expected;
            if ($results[$resource["ref"]] === false) {
                debug($resource["ref"] . " failed integrity check. Expected: " . $criterion . "=" . $expected . ", got : " . $testresult);
                // No need to continue with other $criteria as check has failed
                continue 2;
            }
        }
    }
    return $results;
}

/**
 * Check if a given file path is from a valid RS accessible location
 *
 * @param   string   $path
 * @param   array    $override_paths   Override checking of the default RS paths to check a specific location only.
 */
function is_valid_rs_path(string $path, array $override_paths = []): bool
{
    debug_function_call(__FUNCTION__, func_get_args());
    if ($GLOBALS["config_windows"]) {
        $path = str_replace("\\", "/", $path);
    }

    $sourcerealpath = realpath($path);
    $source_path_not_real = !$sourcerealpath || !file_exists($sourcerealpath);
    debug('source_path_not_real = ' . json_encode($source_path_not_real));

    $checkname  = $path;
    $pathinfo = pathinfo($path);
    if (($pathinfo["extension"] ?? "") === "icc") {
        // ResourceSpace generated .icc files have a double extension, need to strip extension again before checking
        $checkname = $pathinfo["filename"] ?? "";
    }
    debug("checkname = {$checkname}");

    if (
        $source_path_not_real
        && !(preg_match('/^(?!\.)(?!.*\.$)(?!.*\.\.)[a-zA-Z0-9_\-[:space:]\/:.]+$/', ($pathinfo['dirname'] ?? ""))
            && is_safe_basename($checkname))
    ) {
        debug('Invalid non-existing path');
        return false;
    }

    // Check if path contains symlinks, if so don't use the value returned by realpath() as it is unlikely to match the expected paths
    $symlink = false;
    $path_parts = array_filter(explode("/", $path));

    if ($GLOBALS["config_windows"]) {
        $checkpath = "";
    } else {
        $checkpath = "/";
    }

    foreach ($path_parts as $path_part) {
        $checkpath .=  $path_part;
        if (!file_exists($checkpath)) {
            break;
        }
        if (check_symlink($checkpath)) {
            debug("{$checkpath} is a symlink");
            $symlink = true;
            break;
        }
        $checkpath .= "/";
    }
    $path_to_validate = ($source_path_not_real || $symlink) ? $path : $sourcerealpath;
    debug("path_to_validate = {$path_to_validate}");

    if (count($override_paths) > 0) {
        $default_paths = $override_paths;
    } else {
        $default_paths = [
            dirname(__DIR__) . '/gfx',
            $GLOBALS['storagedir'],
            $GLOBALS['syncdir'],
            $GLOBALS['fstemplate_alt_storagedir'],
        ];
        if (isset($GLOBALS['tempdir'])) {
            $default_paths[] = $GLOBALS['tempdir'];
        }
    }
    $allowed_paths = array_filter(array_map('trim', array_unique($default_paths)));
    debug('allowed_paths = ' . implode(', ', $allowed_paths));

    foreach ($allowed_paths as $allowed_path) {
        debug("Iter allowed path - {$allowed_path}");
        $validpath = ($source_path_not_real || $symlink) ? $allowed_path : realpath($allowed_path);
        if ($GLOBALS["config_windows"]) {
            $allowed_path = str_replace("\\", "/", $allowed_path);
            $validpath = str_replace("\\", "/", $validpath);
            $path_to_validate = str_replace("\\", "/", $path_to_validate);
        }
        debug("validpath = {$validpath}");
        debug("path_to_validate = {$path_to_validate}");
        if ($validpath !== false && mb_strpos($path_to_validate, $validpath) === 0) {
            debug('Path allowed');
            return true;
        }
    }

    debug('Default as an invalid path');
    return false;
}

/**
 * Validation helper function to determine if a path base name is unsafe (e.g OS command injection).
 * Very strict, limited to specific characters only. Should only be used for filenames originating in ResourceSpace.
 */
function is_safe_basename(string $val): bool
{
    $file_name = pathinfo($val, PATHINFO_FILENAME);
    return
        safe_file_name($file_name) === str_replace(' ', '_', $file_name)
        && !is_banned_extension(parse_filename_extension($val));
}

// phpcs:disable
enum ProcessFileUploadErrorCondition
{
    case MissingSourceFile;
    case EmptySourceFile;
    case InvalidUploadPath;
    case SpecialFile;
    case InvalidExtension;
    case InvalidMimeType;
    case MimeTypeMismatch;
    case FileMoveFailure;

    /**
     * Translate error condition to users' language.
     * @param array $lang Language string mapping (i.e. the global $lang var)
     * @return string The translated version or the conditions' name if not found in the map
     */
    public function i18n(array $lang): string
    {
        return $lang["error_file_upload_cond-{$this->name}"] ?? $this->name;
    }
}
// phpcs:enable

/**
 * High level function which can handle processing file uploads.
 *
 * @param SplFileInfo|array{name: string, full_path: string, type: string, tmp_name: string, error: int, size: int} $source
 * @param array{
 *      allow_extensions?: list<string>,
 *      file_move?: 'move_uploaded_file'|'rename'|'copy'|'dry_run',
 *      mime_file_based_detection?: bool,
 * } $processor Processors which can override different parts of the main logic (e.g. allow specific extensions)
 *
 * @return array{success: bool, error?: ProcessFileUploadErrorCondition}
 */
function process_file_upload(SplFileInfo|array $source, SplFileInfo $destination, array $processor): array
{
    if ($source instanceof SplFileInfo) {
        $source_file_name = $source->getFilename();
        $source_file_path = $source->getRealPath();
        $source_is_file = $source->isFile();
        $source_file_size = $source_is_file ? $source->getSize() : 0;
        $file_move_processor = $processor['file_move'] ?? 'rename';
    } else {
        $source_file_name = $source['name'];
        $source_file_path = $source['tmp_name'];
        $source_is_file = file_exists($source_file_path);
        $source_file_size = $source_is_file ? filesize($source_file_path) : 0;
        $file_move_processor = 'move_uploaded_file';

        if (!is_uploaded_file($source['tmp_name'])) {
            trigger_error('Invalid $source input. For files not uploaded via HTTP POST, please use SplFileInfo');
            exit();
        }
    }

    $fail_due_to = static fn(ProcessFileUploadErrorCondition $cond): array => ['success' => false, 'error' => $cond];

    // Source validation
    if (!$source_is_file) {
        debug("Missing source file - {$source_file_path}");
        return $fail_due_to(ProcessFileUploadErrorCondition::MissingSourceFile);
    } elseif ($source_file_size === 0) {
        return $fail_due_to(ProcessFileUploadErrorCondition::EmptySourceFile);
    } elseif (
        $source instanceof SplFileInfo
        && !is_valid_upload_path(
            $source_file_path,
            [...$GLOBALS['valid_upload_paths'], ini_get('upload_tmp_dir'), sys_get_temp_dir()]
        )
    ) {
        debug("[WARN] Invalid upload path detected - {$source_file_path}");
        return $fail_due_to(ProcessFileUploadErrorCondition::InvalidUploadPath);
    }

    // Check for "special" files
    if (
        array_intersect(
            [
                'crossdomain.xml',
                'clientaccesspolicy.xml',
                '.htaccess',
                '.htpasswd',
            ],
            [$source_file_name]
        ) !== []
    ) {
        return $fail_due_to(ProcessFileUploadErrorCondition::SpecialFile);
    }

    // Extension validation
    $source_file_ext = parse_filename_extension($source_file_name);
    if (
        (
            isset($processor['allow_extensions'])
            && $processor['allow_extensions'] !== []
            && !check_valid_file_extension(['name' => $source_file_name], $processor['allow_extensions'])
        )
        || is_banned_extension($source_file_ext)
    ) {
        return $fail_due_to(ProcessFileUploadErrorCondition::InvalidExtension);
    }

    // Check content (MIME) type based on the file received (don't trust the header from the upload)
    $mime_file_based_detection = $processor['mime_file_based_detection'] ?? true;
    $mime_type_by_ext = get_mime_types_by_extension($source_file_ext);

    if ($mime_type_by_ext === []) {
        log_activity(
            "Unknown MIME type for file extension '{$source_file_ext}'",
            LOG_CODE_SYSTEM,
            get_mime_type($source_file_path, $source_file_ext, true)[0]
        );
        /* todo: Drop this overriding once we have a better MIME type database (e.g. in 3 releases from now based on the
        activity log entries). This was temporarily added for v10.6 to avoid multiple failed uploads due to this new
        check. */
        $mime_file_based_detection = false;
    }

    if (
        $mime_file_based_detection
        && ($mime_type_from_file = get_mime_type($source_file_path, $source_file_ext, true))
        && array_intersect($mime_type_by_ext, $mime_type_from_file) === []
    ) {
        debug("MIME type mismatch for file '{$source_file_name}'");

        if (in_array('application/octet-stream', $mime_type_from_file)) {
            return $fail_due_to(ProcessFileUploadErrorCondition::InvalidMimeType);
        } elseif (array_intersect($mime_type_from_file, get_unsafe_mime_types()) !== []) {
            return $fail_due_to(ProcessFileUploadErrorCondition::MimeTypeMismatch);
        } else {
            log_activity(
                "MIME type mismatch for file '{$source_file_name}' "
                . '(by ext: ' . implode(', ', $mime_type_by_ext)
                . '; by content: ' . implode(', ', $mime_type_from_file) . ')',
                LOG_CODE_SYSTEM
            );
        }        
    }

    // Destination processing
    $dest_path = $destination->isFile() ? $destination->getRealPath() : $destination->getPathname();
    if ($destination->isDir()) {
        debug("Destination path '{$dest_path}' is a directory not a file");
        trigger_error('Destination path must be for a file, not a directory!');
        exit();
    } elseif (!(is_valid_rs_path($dest_path) && is_safe_basename($destination->getBasename()))) {
        debug("Destination path '{$dest_path}' not allowed!");
        trigger_error('Destination path not allowed');
        exit();
    } elseif ($file_move_processor === 'dry_run') {
        return ['success' => true];
    } elseif (array_intersect(['move_uploaded_file', 'rename', 'copy'], [$file_move_processor]) === []) {
        debug("Invalid upload (file move) processor '" . print_r($file_move_processor, true) . "'");
        trigger_error('Invalid upload (file move) processor');
        exit();
    } elseif ($file_move_processor($source_file_path, $dest_path)) {
        return ['success' => true];
    } else {
        debug("Unable to move file uploaded FROM '{$source_file_path}' TO '$dest_path'");
        return $fail_due_to(ProcessFileUploadErrorCondition::FileMoveFailure);
    }
}

/**
 * Parse file name (can include path, although it's unnecessary) to prevent known security bypasses associated with
 * extensions, such as:
 * - Double extensions, e.g. .jpg.php
 * - Null bytes, e.g. .php%00.jpg, where .jpg gets truncated and .php becomes the new extension
 * - Using Windows (DOS) 8.3 short path feature where it's possible to replace existing files by using their shortname
 * (e.g. ".htaccess" can be replaced by "HTACCE~1")
 */
function parse_filename_extension(string $filename): string
{
    $orig_use_error_exception_val = $GLOBALS['use_error_exception'] ?? false;
    $GLOBALS["use_error_exception"] = true;
    try {
        $finfo = new SplFileInfo($filename);
    } catch (Throwable $t) {
        debug("[WARN] Bad file '{$filename}'. Reason: {$t->getMessage()}");
        return '';
    }
    $GLOBALS['use_error_exception'] = $orig_use_error_exception_val;

    /*
    Windows (DOS) 8.3 short paths (e.g. "HTACCE~1" = ".htaccess"). Example file info in such scenario:
    Filename is: HTACCE~1
    Path is: (note, depends if input is a file name with path)
    Path name is: HTACCE~1
    Real path is: C:\path\to\.htaccess
    */
    if (preg_match('/^[A-Z0-9]{1,6}~([A-Z0-9]?)(\.[A-Z0-9_]{1,3})?$/i', $finfo->getFilename()) === 1) {
        if ($finfo->getRealPath() !== false && basename($finfo->getRealPath()) !== $finfo->getFilename()) {
            return parse_filename_extension($finfo->getRealPath());
        } else {
            // Invalid if not a real file to avoid potential exploits
            debug("[WARN] Windows (DOS) 8.3 short path for non-existent file '{$filename}' - considered invalid");
            return '';
        }
    }

    // Invalidate if it's a hidden file without an extension (e.g .htaccess or .htpasswd) which would be incorrectly
    // picked up as an extension
    if (trim($finfo->getBasename($finfo->getExtension())) === '.') {
        debug("Hidden file '{$filename}' without an extension - considered invalid");
        return '';
    }

    return $finfo->getExtension();
}


/**
 * Delete old files and folders from tempo directory based on the configured $purge_temp_folder_age value
 * Affects filestore/tmp, $storagedir/tmp or the configured $tempdir directory
 */
function delete_temp_files(): void
{
    if ($GLOBALS["purge_temp_folder_age"] === 0) {
        // Disabled
        return;
    }
    // Set up array of folders to scan
    $folderstoscan = [];
    $folderstoscan[] = get_temp_dir();

    $modified_folderstoscan = hook("add_folders_to_delete_from_temp", "", array($folderstoscan));
    if (is_array($modified_folderstoscan) && !empty($modified_folderstoscan)) {
        $folderstoscan = $modified_folderstoscan;
    }

    // Set up array of folders to exclude
    $excludepaths = [];

    $excludepaths[] = get_temp_dir() . "offline_job_logs";

    if (isset($GLOBALS["geo_tile_cache_directory"])) {
        $excludepaths[] = $GLOBALS["geo_tile_cache_directory"];
    } else {
        $excludepaths[] = get_temp_dir() . "tiles";
    }
    if (DOWNLOAD_FILE_LIFETIME > $GLOBALS["purge_temp_folder_age"]) {
        $excludepaths[] = get_temp_dir(false, "user_downloads");
    }
    $excludeplugindirs = hook('temp_block_deletion');
    if (is_array($excludeplugindirs)) {
        $excludepaths[] = array_merge($excludepaths, $excludeplugindirs);
    }

    // Set up arrays to hold items to delete
    $folderstodelete = [];
    $filestodelete = [];

    foreach ($folderstoscan as $foldertoscan) {
        if (!file_exists($foldertoscan)) {
            continue;
        }
        $foldercontents = new DirectoryIterator($foldertoscan);
        foreach ($foldercontents as $object) {
            if (time() - $object->getMTime() > $GLOBALS["purge_temp_folder_age"] * 24 * 60 * 60) {
                $tmpfilename = $object->getFilename();
                if ($object->isDot()) {
                    continue;
                }
                foreach ($excludepaths as $excludepath) {
                    if (
                        ($tmpfilename == $excludepath)
                        || strpos($object->getRealPath(), $excludepath) == 0
                    ) {
                        continue 2;
                    }
                }
                if ($object->isDir()) {
                    $folderstodelete[] = $foldertoscan . DIRECTORY_SEPARATOR . $tmpfilename;
                } elseif ($object->isFile()) {
                    $filestodelete[] = $foldertoscan . DIRECTORY_SEPARATOR . $tmpfilename;
                }
            }
        }
    }

    foreach ($folderstodelete as $foldertodelete) {
        // Extra check that folder is in an expected path
        if (
            strpos($foldertodelete, $GLOBALS["storagedir"]) === false
            && strpos($foldertodelete, $GLOBALS["tempdir"]) === false
            && strpos($foldertodelete, 'filestore/tmp') === false
        ) {
            continue;
        }

        $success = rcRmdir($foldertodelete);
        if ('cli' == PHP_SAPI) {
            echo " - deleting directory " . $foldertodelete . " - " . ($success ? "SUCCESS" : "FAILED")  . PHP_EOL;
        }
        debug(" - deleting directory " . $foldertodelete . " - " . ($success ? "SUCCESS" : "FAILED"));
    }

    foreach ($filestodelete as $filetodelete) {
        // Extra check that file is in an expected path
        if (
            strpos($filetodelete, $GLOBALS["storagedir"]) === false
            && strpos($filetodelete, $GLOBALS["tempdir"]) === false
            && strpos($filetodelete, 'filestore/tmp') === false
        ) {
            continue;
        }

        $success = try_unlink($filetodelete);

        if ('cli' == PHP_SAPI) {
            echo " - deleting file " . $filetodelete . " - " . ($success ? "SUCCESS" : "FAILED")  . PHP_EOL;
        }
        debug(" - deleting file " . $filetodelete . " - " . ($success ? "SUCCESS" : "FAILED"));
    }
}

/**
 * Are the arguments set in $archiver_settings["arguments"] permitted?
 * Allows word characters, '@', and '-' only
 *
 * @param string    Argument string
 *
 */
function permitted_archiver_arguments($string): bool
{
    return preg_match('/[^\@\-\w]/', $string) === 0;
}

/**
 * Check if a given path is absolute or contains a symlink or junction
 * is_link() does not accurately detect junction links on Windows systems
 * instead we check if the output from stat() and lstat() differ.
 *
 * @param  string $checkpath
 * @return bool
 */
function check_symlink(string $checkpath): bool
{
    if ($GLOBALS["config_windows"]) {
        return stat($checkpath) != lstat($checkpath);
    } else {
        return is_link($checkpath);
    }
}
