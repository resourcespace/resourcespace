<?php

/* We will use output buffering to prevent any included files
from outputting stray characters that will mess up the binary download
we will clear the buffer and start over right before we download the file*/
ob_start();
$nocache = true;
$disable_browser_check = true;
include_once __DIR__ . '/../include/boot.php';
include_once __DIR__ . '/../include/image_processing.php';
ob_end_clean();
if ($download_no_session_cache_limiter) {
    session_cache_limiter(false);
}

$ref                = getval('ref', '', true);
$size               = trim(getval('size', ''));
$alternative        = getval('alternative', -1, true);
$page               = getval('page', 1, true);
$iaccept            = getval('iaccept', 'off');
$usage              = getval('usage', '-1');
$usagecomment       = getval('usagecomment', '');
$email              = getval('email', '');
$ext                = getval('ext', '');
$snapshot_frame     = getval('snapshot_frame', 0, true);
$modal              = (getval("modal", "") == "true");
$tempfile           = getval("tempfile", "");
$slideshow          = getval("slideshow", 0, true);
$userfiledownload   = getval('userfile', '');
$write_exif_data    = (getval('exif_write', '') == 'true');
$k                  = getval('k', '');
$download_temp_key  = getval("access_key", '');
$watermarked        = getval('watermarked', 0, true);
$override_temp_key  = getval("override_key", '');
$noattach           = getval('noattach', '') != '';

// Check for temporary download access using key (e.g. from API get_resource_path)
$valid_key = false;
if ($ref > 0 && ($download_temp_key !== '' || $override_temp_key !== '')) {
    $valid_key = validate_temp_download_key($ref, trim($download_temp_key === '' ? $override_temp_key : $download_temp_key), $size);
}

// External access support (authenticate only if no key provided, or if invalid access key provided)
if (!$valid_key && ('' == $k || !check_access_key(getval('ref', '', true), $k)) && getval("slideshow", 0, true) <= 0) {
    include __DIR__ . '/../include/authenticate.php';
}

// Set a flag for logged in users if $external_share_view_as_internal is set and logged on user is accessing an external share
$internal_share_access = internal_share_access();
$log_download = true;

// Ensure terms have been accepted and usage has been supplied when required. Not for slideshow files etc.
$checktermsusage =  !in_array($size, $sizes_always_allowed)
    && $tempfile == ""
    && $slideshow == 0
    && $userfiledownload == ""
    && (!$video_preview_original && get_resource_access($ref));
if (
    $terms_download
    && $checktermsusage
    && $iaccept != 'on'
) {
        exit($lang["mustaccept"]);
}
if ($download_usage && $checktermsusage) {
    if (!(is_numeric($usage) && $usage >= 0)) {
        exit($lang["termsmustindicateusage"]);
    }
    if ($usagecomment == '' && !$usage_comment_blank) {
        exit($lang["termsmustspecifyusagecomment"]);
    }
}

if (is_banned_extension($ext)) {
    $ext = 'jpg';
}

$override_key = false;
if ($ref > 0 && $override_temp_key !== '') {
    // Check if the download should be allowed. Permissions have already been considered elsewhere.
    // Used to display edit page resource preview image after upload where search filter has not yet been set.
    $override_key = validate_temp_download_key($ref, trim($override_temp_key), $size, 2, false);
}

// Is this a user specific download?
if ('' != $userfiledownload) {
    $noattach       = false;
    $log_download   = true;
    // Provide a way of overriding $exiftool_write = false depending on download source e.g. from format chooser
    if ($exiftool_write && $write_exif_data) {
        $exiftool_write = true;
    } else {
        $exiftool_write = false;
    }

    $filedetails    = explode('_', $userfiledownload);
    $ref            = (int)$filedetails[0];
    $downloadkey    = strip_extension($filedetails[1]);
    $ext            = safe_file_name(substr($filedetails[1], strlen($downloadkey) + 1));
    $path           = get_temp_dir(false, 'user_downloads') . '/' . $ref . '_' . md5($username . $downloadkey . $scramble_key) . '.' . $ext;
    $rqstname       = getval("filename", "");
    if ($rqstname != "") {
        $filename   = $rqstname . "." . $ext;
        $filename_prefix = explode('_', $filename);
        if (($filename_prefix[0] == 'Col' && $ext == 'zip') || ($filename_prefix[0] == 'metadata' && isset($filename_prefix[1]) && $filename_prefix[1] == 'export' && $ext == 'csv')) {
            // For offline collection or offline csv download, $ref will be user ref not resource ref.
            $log_download = false;
        }
    }
    hook('modifydownloadpath');
} elseif ($slideshow != 0) {
    $noattach       = true;
    $log_download   = false;
    $path           = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . $homeanim_folder . DIRECTORY_SEPARATOR . getval("slideshow", 0, true) . ".jpg";
} elseif ($tempfile != "") {
    $noattach       = true;
    $log_download   = false;
    $exiftool_write = false;
    $filedetails    = explode('_', $tempfile);
    if (count($filedetails) >= 3) {
        $code           = safe_file_name($filedetails[0]);
        $ref            = (int)$filedetails[1];
        $downloadkey    = strip_extension($filedetails[2]);
        $ext            = safe_file_name(substr($filedetails[2], strlen($downloadkey) + 1));
        $path           = get_temp_dir(false, "") . '/' . $code . '_' . $ref . "_" . md5($username . $downloadkey . $scramble_key) . '.' . $ext;
    } else {
        $error = $lang['downloadfile_nofile'];
        if (getval('ajax', '') != '') {
            error_alert($error, true, 200);
        } else {
            include "../include/header.php";
            $onload_message = ['title' => $lang["error"],'text' => $error];
            include "../include/footer.php";
        }
        exit();
    }
} else {
    $resource_data = get_resource_data($ref);
    if (!is_array($resource_data)) {
        $error = $lang["resourcenotfound"];
        if (getval("ajax", "") != "") {
            error_alert($error, true, 200);
        } else {
            include "../include/header.php";
            $onload_message = array("title" => $lang["error"],"text" => $error);
            include "../include/footer.php";
        }
        exit();
    }

    if ((int)$resource_data['has_image'] === 0 && $size != "") {
        // If configured, try and use the preview from a related resource
        $pullresource = related_resource_pull($resource_data);
        if ($pullresource !== false) {
            $resource_data = $pullresource;
            $ref = $pullresource["ref"];
        }
    }

    resource_type_config_override($resource_data['resource_type']);

    // Check permissions
    $allowed = $override_key ? true : resource_download_allowed($ref, $size, $resource_data['resource_type'], $alternative);
    debug("PAGES/DOWNLOAD.PHP: \$allowed = " . ($allowed ? 'TRUE' : 'FALSE'));

    if (!$allowed || $ref <= 0) {
        $error = $lang['error-permissiondenied'];
        if (getval("ajax", "") != "") {
            error_alert($error, true, 200);
        } else {
            include "../include/header.php";
            $onload_message = array("title" => $lang["error"],"text" => $error);
            include "../include/footer.php";
        }
        exit();
    }

    // additional access check, as the resource download may be allowed, but access restriction should force watermark.
    $access        = get_resource_access($ref);
    $use_watermark = check_use_watermark(getval("dl_key", ""), $ref) || $watermarked;
    // If no extension was provided, we fallback to JPG.
    if ('' == $ext) {
        $ext = 'jpg';
    }

    // Where we are getting mp3 preview for videojs, clear size as we want to get the auto generated mp3 file rather than a custom size.
    // Also allow mp4 files for videojs if $video_preview_original is enabled
    if (
        $size == 'videojs'
        && ($ext == 'mp3' || ($ext == 'mp4' && $video_preview_original))
    ) {
        $size = "";
        $log_download = false;
    } elseif ($preview_tiles && $allowed && $size == '' && getval('tile_region', 0, true) == 1) {
        // Provide a tile region if enabled and requested for the main resource.
        $tile_scale = (int) getval('tile_scale', 1, true);
        $tile_row = (int) getval('tile_row', 0, true);
        $tile_col = (int) getval('tile_col', 0, true);
        $fulljpgsize = strtolower($ext) != "jpg" ? "hpr" : "";
        $fullpath = get_resource_path($ref, true, $fulljpgsize, false, "jpg");
        $image_size = get_original_imagesize($ref, $fullpath, "jpg");
        if ($image_size === false) {
            debug("PAGES/DOWNLOAD.PHP: File does not exist!");
            http_response_code(404);
            exit($lang['downloadfile_nofile']);
        } else {
            $image_width = (int) $image_size[1];
            $image_height = (int) $image_size[2];

            debug(sprintf('PAGES/DOWNLOAD.PHP: Requesting a tile region with scale=%s, row=%s, col=%s', $tile_scale, $tile_row, $tile_col));

            $tiles = compute_tiles_at_scale_factor($tile_scale, $image_width, $image_height);
            foreach ($tiles as $tile) {
                if ($tile['column'] == $tile_col && $tile['row'] == $tile_row) {
                    $size = $tile['id'];
                    $ext = 'jpg';
                    break;
                }
            }
        }
    }

    // Establish nonwatermarked path for use when returning snapshot frames
    $nowmpath = get_resource_path($ref, true, $size, false, $ext, -1, $page, false, '', $alternative, true);

    $path = get_resource_path($ref, true, $size, false, $ext, -1, $page, $use_watermark && $alternative == -1, '', $alternative, true);
    $download_extra = hook('download_resource_extra', '', array($path));

    // Process depending on whether snapshot frame is to be returned
    if ($snapshot_frame > 0 && $ffmpeg_snapshot_frames > 1) {
        // Snapshot frame is to be returned, so adjust the path to be the actual frame requested
        $path = str_replace('snapshot', "snapshot_{$snapshot_frame}", $nowmpath);
    }

    if ($snapshot_frame == 0 && $size == "pre" && $use_watermark && $ext == "mp4") {
        // Video stream preview size is to be returned and watermark is specified
        // In this case there is no such thing as a watermarked video preview size, so use the unwatermarked path
        $path = $nowmpath;
    }

    hook('modifydownloadpath');
    // Hook to modify the download path.
    $path_modified = hook('modifydownloadpath2', '', array($download_extra));
    if (isset($path_modified) && $path_modified != '' && is_string($path_modified)) {
        $path = $path_modified;
    }

    if (!file_exists($path) && $noattach) {
        # Return icon for file (for previews)
        if(in_array($size,["thm","col"])) {
            $path = '../gfx/no_preview/default_thm.png';
        } else {            
            $path = '../gfx/no_preview/default.png';
        }
    }

    if ($noattach) {
        if (!allow_in_browser($path)) {
            // Override the noattach request if file is not valid for display in browser
            $noattach = false;
        }
        if (!($size == "" && $resource_data["file_extension"] == $ext)) {
            // Do not log downloads for these unless accessing original resource files
            $log_download = false;
        }
    }

    // Process metadata
    // Note: only for downloads (not previews)
    if (!$noattach && -1 == $alternative) {
        // Strip existing metadata only if we do not plan on writing metadata, otherwise this will be done twice
        if ($exiftool_remove_existing && !$exiftool_write) {
            $temp_file_stripped_metadata = createTempFile($path, '', '');

            if ($temp_file_stripped_metadata !== false && stripMetadata($temp_file_stripped_metadata)) {
                $path = $temp_file_stripped_metadata;
            }
        }
        // writing RS metadata to files: exiftool
        if ($exiftool_write) {
            $tmpfile = write_metadata($path, $ref);
            if (false !== $tmpfile && file_exists($tmpfile)) {
                $path = $tmpfile;
            }
        }
    }
}

debug("PAGES/DOWNLOAD.PHP: Preparing to download/ stream file '{$path}'");
if (!file_exists($path)) {
    debug("PAGES/DOWNLOAD.PHP: File does not exist!");
    http_response_code(404);
    exit($lang['downloadfile_nofile']);
}

hook('modifydownloadfile');

$file_size   = filesize_unlimited($path);
$file_handle = fopen($path, 'rb');

debug("PAGES/DOWNLOAD.PHP: \$file_size = {$file_size}");

if (!$file_handle) {
    // File could not be opened
    debug("PAGES/DOWNLOAD.PHP: File could not be opened!");
    http_response_code(500);
    exit();
}

if (
    !$noattach
    && (!isset($filename) || $filename == "" || $alternative != -1)
) {
    // Compute a file name for the download.
        $filename = get_download_filename($ref, $size, $alternative, $ext);
}

if ($log_download) {
    // Log this activity (download only, not preview)
    daily_stat('Resource download', $ref);
    $email_add_to_log = ($email != "") ? ' Downloaded by ' . $email : "";
    resource_log($ref, LOG_CODE_DOWNLOADED, 0, $usagecomment . $email_add_to_log, '', '', $usage, $alternative);

    hook('moredlactions');

    // Update hit count if tracking downloads only
    if ($resource_hit_count_on_downloads) {
        // greatest() is used so the value is taken from the hit_count column in the event that new_hit_count
        // is zero to support installations that did not previously have a new_hit_count column (i.e. upgrade compatability).
        ps_query("UPDATE resource SET new_hit_count = greatest(hit_count, new_hit_count) + 1 WHERE ref = ?", ['i', $ref]);
    }
}

// Set appropriate headers for attachment or streamed file
if (isset($filename)) {
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    debug("PAGES/DOWNLOAD.PHP: Set header for attachment file");
} else {
    header("Content-Disposition: inline; filename=\"download.{$ext}\"");
    header('Content-Transfer-Encoding: binary');
    debug("PAGES/DOWNLOAD.PHP: Set header for streamed file");
}

// We declare the downloaded content mime type
$mime = get_mime_type($path)[0];
header("Content-Type: {$mime}");
header('X-Content-Type-Options: nosniff');

debug("PAGES/DOWNLOAD.PHP: Set MIME type to '{$mime}'");

// Check if http_range is sent by browser (or download manager)
$range_requested = false;
if (isset($_SERVER['HTTP_RANGE']) && strpos($_SERVER['HTTP_RANGE'], "=") !== false) { # Check it's set and also contains the expected = delimiter
    debug("PAGES/DOWNLOAD.PHP: HTTP_RANGE is set to '{$_SERVER['HTTP_RANGE']}'");

    list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);

    if ('bytes' == $size_unit) {
        /* Multiple ranges could be specified at the same time, but for simplicity only serve the first range
        http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt

        IMPORTANT: If multiple ranges are not specified, PHP can return an error for "Undefined offset: 1",
        so we pad the array with an empty string */
        list($range, $extra_ranges) = array_pad(explode(',', $range_orig, 2), 2, '');
        $range_requested = true;
    } else {
        debug("PAGES/DOWNLOAD.PHP: Requested range was not valid");

        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: */{$file_size}");
        exit();
    }
} else {
    debug("PAGES/DOWNLOAD.PHP: HTTP_RANGE is not set!");

    $range = '';
}

debug("PAGES/DOWNLOAD.PHP: \$range = {$range}");

// Figure out download piece from range (if set)
list($seek_start, $seek_end) = array_pad(explode('-', $range, 2), 2, '');

// Set start and end based on range (if set), else set defaults
// also check for invalid ranges.
$seek_end   = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)), ($file_size - 1));
$seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

debug("PAGES/DOWNLOAD.PHP: \$seek_start = {$seek_start}");
debug("PAGES/DOWNLOAD.PHP: \$seek_end = {$seek_end}");

// Only send partial content header if downloading a piece of the file (IE workaround)
if (0 < $seek_start || $seek_end < ($file_size - 1)) {
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes {$seek_start}-{$seek_end}/{$file_size}");
    header('Content-Length: ' . ($seek_end - $seek_start + 1));

    debug("PAGES/DOWNLOAD.PHP: Content-Range: bytes {$seek_start}-{$seek_end}/{$file_size}");
    debug('PAGES/DOWNLOAD.PHP: Content-Length: ' . ($seek_end - $seek_start + 1));

    $total_to_send = $seek_end - $seek_start + 1;
} else {
    header("Content-Length: {$file_size}");
    if ($range_requested) {
        // Safari seems to require this
        header("Content-Range: bytes {$seek_start}-{$seek_end}/{$file_size}");
    }
    debug("PAGES/DOWNLOAD.PHP: Content-Length: {$file_size}");

    $total_to_send = $file_size;
}

header('Accept-Ranges: bytes');

set_time_limit(0);

$sent = (0 == fseek($file_handle, $seek_start) ? $seek_start : 0);

while ($sent < $file_size) {
    echo fread($file_handle, $download_chunk_size);

    ob_flush();
    flush();

    $sent += $download_chunk_size;

    if (0 != connection_status()) {
        break;
    }
}

fclose($file_handle);

// File send complete, log to daily stat
log_bandwidth(floor($total_to_send / 1024));

// Deleting Exiftool temp File:
// Note: Only for downloads (not previews)
if (!$noattach && -1 == $alternative && $exiftool_write && isset($tmpfile) && file_exists($tmpfile)) {
    delete_exif_tmpfile($tmpfile);
}

if (isset($download_extra)) {
    hook('beforedownloadresourceexit', '', array($download_extra));
}

exit();
