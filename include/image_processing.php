<?php

/**
 * Image processing functions
 *
 * Functions to allow upload and resizing of images.
 */

use Montala\ResourceSpace\CommandPlaceholderArg;

include_once 'metadata_functions.php';

/**
 * Upload a file from the provided path to the given resource
 *
 * @param  int $ref                         Resource ID
 * @param  bool $no_exif                    Do not extract embedded metadata. False by default so data will be extracted
 * @param  bool $revert                     Delete all data and re-extract embedded data
 * @param  bool $autorotate                 Autorotate images - alters embedded orientation data in uploaded file
 * @param  string $file_path                Path to file
 * @param  bool $after_upload_processing    Set to true will create an offline job to process the file
 * @param  bool $deletesource               Delete resource after upload
 * @return bool
 */
function upload_file($ref, $no_exif = false, $revert = false, $autorotate = false, $file_path = "", $after_upload_processing = false, $deletesource = true)
{
    debug_function_call('upload_file', func_get_args());

    global $lang, $userref, $filename_field, $extracted_text_field, $extracted_text_extensions, $amended_filename;
    global $upload_then_process, $upload_then_process_holding_state,$offline_job_queue, $offline_job_in_progress;
    global $icc_extraction, $camera_autorotation, $camera_autorotation_ext;
    global $ffmpeg_supported_extensions, $ffmpeg_preview_extension, $pdf_pages;
    global $unoconv_extensions, $merge_filename_with_title, $merge_filename_with_title_default;
    global $file_checksums_offline, $file_upload_block_duplicates, $replace_batch_existing, $valid_upload_paths;

    $ref = (int) $ref;

    # FStemplate support - do not allow samples from the template to be replaced
    if (resource_file_readonly($ref)) {
        debug("Resource record is read only - template.");
        return false;
    }

    hook("beforeuploadfile", "", array($ref));
    hook("clearaltfiles", "", array($ref)); // optional: clear alternative files before uploading new resource

    # revert is mainly for metadata reversion, removing all metadata and simulating a reupload of the file from scratch.

    hook("removeannotations", "", array($ref));

    if (
        trim($file_path) != ""
        && !is_valid_upload_path($file_path, $valid_upload_paths)
    ) {
            debug("Invalid file path specified: " . $file_path);
            return false;
    }

    if (!$after_upload_processing && !(checkperm('c') || checkperm('d') || hook('upload_file_permission_check_override'))) {
        debug("User does not have permission to upload.");
        return false;
    }

    $resource_data = get_resource_data($ref);

    if (!is_array($resource_data)) {
        debug("No valid resource found.");
        return false;
    }

    if (isset($resource_data["lock_user"]) && $resource_data["lock_user"] > 0 && $resource_data["lock_user"] != $userref) {
        debug("Resource locked.");
        return false;
    }

    if ($upload_then_process && !$offline_job_queue) {
        $upload_then_process = false;
    }

    if (!$after_upload_processing) {
        resource_log($ref, LOG_CODE_TRANSFORMED, '', '', '', $lang['upload_file']);

        $exiftool_fullpath = get_utility_path("exiftool");

        # Process file upload for resource $ref
        if ($revert) {
            $original_filename = get_data_by_field($ref, $filename_field);

            # Field 8 is used in a special way for staticsync, don't overwrite.
            $test_for_staticsync = get_resource_data($ref);
            if ($test_for_staticsync['file_path'] != "") {
                $staticsync_mod = " and resource_type_field != 8";
            } else {
                $staticsync_mod = "";
            }

            ps_query("DELETE FROM resource_node WHERE resource= ?" . $staticsync_mod, ['i', $ref]);
            # clear 'joined' display fields which are based on metadata that is being deleted in a revert (original filename is reinserted later)
            $display_fields = get_resource_table_joins();
            if ($staticsync_mod != "") {
                $display_fields_new = array();
                for ($n = 0; $n < count($display_fields); $n++) {
                    if ($display_fields[$n] != 8) {
                        $display_fields_new[] = $display_fields[$n];
                    }
                }
                $display_fields = $display_fields_new;
            }
            $clear_fields = "";
            for ($x = 0; $x < count($display_fields); $x++) {
                $clear_fields .= "field" . $display_fields[$x] . "=''";
                if ($x < count($display_fields) - 1) {
                    $clear_fields .= ",";
                }
            }
            ps_query("UPDATE resource SET " . $clear_fields . " WHERE ref= ?", ['i', $ref]);
            $extension = ps_value("SELECT file_extension value FROM resource WHERE ref=?", array("i",$ref), "");
            $filename = get_resource_path($ref, true, "", false, $extension);
            $processfile['tmp_name'] = $filename;
        } else {
            # Work out which file has been posted
            if (isset($_FILES['userfile'])) {
                $processfile = $_FILES['userfile'];# Single upload (at least) needs this
            } elseif (isset($_FILES['Filedata'])) {
                $processfile = $_FILES['Filedata'];# Java upload (at least) needs this
            }

            # Work out the filename.
            if (isset($_REQUEST['file_name'])) {
                $filename = $_REQUEST['file_name'];
            } elseif ($file_path != "") {
                $filename = basename(urldecode($file_path)); # The file path was provided
                $filename = str_replace("RS_FORWARD_SLASH", "/", $filename);
                if (base64_encode(base64_decode($filename)) == $filename) {
                   // Should have been encoded by Uppy
                    $filename = base64_decode($filename);
                }
            } else {
                if (isset($processfile['name'])) {
                    $filename = $processfile['name']; # Standard uploads
                } else {
                    exit($lang["posted-file-not-found"]);
                }
            }

            if ($no_exif && isset($filename_field)) {
                $user_set_filename            = get_data_by_field($ref, $filename_field);
                $user_set_filename_path_parts = pathinfo($user_set_filename);

                // $user_set_filename is for an already existing resource or when original filename is a visible field
                // on the upload form
                if (trim($user_set_filename) != '') {
                    // Get extension of file just in case the user didn't provide one
                    $path_parts = pathinfo($filename);

                    if (isset($path_parts['extension'])) {
                        $uploaded_extension = $path_parts['extension'];
                    }

                    if (
                        isset($user_set_filename_path_parts['extension'])
                        && (
                            !isset($uploaded_extension)
                            || $uploaded_extension == $user_set_filename_path_parts['extension']
                        )
                    ) {
                        $filename = $user_set_filename;
                    }
                }
            }
        }

        # Work out extension
        if (!isset($extension)) {
            # first try to get it from the filename
            $extension = explode(".", $filename);
            if (count($extension) > 1) {
                $extension = trim(strtolower($extension[count($extension) - 1]));
            }
            # if not, try exiftool
            elseif ($exiftool_fullpath != false) {
                if ($file_path !== "") {
                    $cmd = "{$exiftool_fullpath} -filetype -s -s -s %%PATH%%";
                    $file_type_by_exiftool = run_command($cmd, false, ["%%PATH%%" => new CommandPlaceholderArg($file_path, 'is_valid_rs_path')]);
                } else {
                    $cmd = "{$exiftool_fullpath} -filetype -s -s -s %%PATH%%";
                    $file_type_by_exiftool = run_command($cmd, false, [
                        "%%PATH%%" => new CommandPlaceholderArg(
                            $processfile['tmp_name'],
                            fn($v) => is_valid_upload_path($v, [ini_get('upload_tmp_dir'),sys_get_temp_dir()])
                        )
                    ]);
                }

                if (strlen($file_type_by_exiftool) > 0) {
                    $extension = str_replace(" ", "_", trim(strtolower($file_type_by_exiftool)));
                    $filename = $filename . "." . $extension;
                } else {
                    debug("File type unknown.");
                    return false;
                }
            }
            # if no clue of extension by now, return false
            else {
                debug("File extension unknown.");
                return false;
            }
        }

        if (is_banned_extension($extension)) {
            debug("Banned extension.");
            return false;
        }

        $filepath = get_resource_path($ref, true, "", true, $extension);

        if (!$revert) {
            # Remove existing file, if present

            hook("beforeremoveexistingfile", "", array( "ref" => $ref ));

            $old_extension = ps_value("select file_extension value from resource where ref=?", array("i",$ref), "");
            if ($old_extension != "") {
                $old_path = get_resource_path($ref, true, "", true, $old_extension);
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }

            // also remove any existing extracted icc profiles
            $icc_path = get_resource_path($ref, true, '', false, 'icc');
            if (file_exists($icc_path)) {
                unlink($icc_path);
            }

            $iccx = 0; // if there is a -0.icc page, run through and delete as many as necessary.
            $finished = false;
            $badicc_path = str_replace(".icc", "-$iccx.icc", $icc_path);

            while (!$finished) {
                if (file_exists($badicc_path)) {
                    unlink($badicc_path);
                    $iccx++;
                    $badicc_path = str_replace(".icc", "-$iccx.icc", $icc_path);
                } else {
                    $finished = true;
                }
            }
            $iccx = 0;
        }
    } else {
        $resource = get_resource_data($ref);
        $extension = $resource['file_extension'];
        $filepath = get_resource_path($ref, true, "", true, $extension);
        $filename = get_data_by_field($ref, $filename_field);
    }

    if (
        !$revert
        && ($after_upload_processing || $filename != "")
    ) {
        if (!$after_upload_processing) {
            if ($file_path != "" && ($replace_batch_existing || !$deletesource)) {
                $result = process_file_upload(
                    new SplFileInfo($file_path),
                    new SplFileInfo($filepath),
                    ['file_move' => 'copy']
                )['success'];
            } elseif (!$replace_batch_existing && $file_path != "") {
                # File path has been specified. Let's use that directly.
                if (file_exists($file_path)) {
                    $result = process_file_upload(
                        new SplFileInfo($file_path),
                        new SplFileInfo($filepath),
                        []
                    )['success'];
                } else {
                    debug("File does not exist.");
                    return false;
                }
            } else {
                # Standard upload.
                if (!$revert) {
                    $result = process_file_upload($processfile, new SplFileInfo($filepath), [])['success'];
                } else {
                    $result = true;
                }
            }

            if (!$result) {
                debug("File processing faulure.");
                return false;
            }
        }

        if (!$upload_then_process || $after_upload_processing) {
            if (
                $camera_autorotation
                && $autorotate
                && (in_array($extension, $camera_autorotation_ext))
            ) {
                    AutoRotateImage($filepath);
            }

            if ($icc_extraction && $extension != "pdf" && !in_array($extension, $ffmpeg_supported_extensions)) {
                extract_icc_profile($ref, $extension);
            }
        }
    }

    // Store extension in the database and update file modified time.
    // Reset 'has_image' if $revert == false
    $set = $revert ? [] : ["has_image=?"];
    $setparams = $revert ? [] : ["i",RESOURCE_PREVIEWS_NONE];

    // Include file size
    $file_size = @filesize_unlimited($filepath);

    $set = array_merge($set, ["file_size=?","file_extension=?","preview_extension='jpg'","file_modified=NOW()","no_file=0","file_checksum=NULL","integrity_fail=0"]);
    $setparams = array_merge($setparams, ['i',$file_size,'s', $extension, 'i', $ref]);
    ps_query("UPDATE resource SET " . join(",", $set) . " WHERE ref= ?", $setparams);

    if (!$upload_then_process || $after_upload_processing) {
        # delete existing resource_dimensions
        ps_query("DELETE FROM resource_dimensions WHERE resource= ?", ['i', $ref]);

        # get file metadata
        if (!$no_exif) {
            debug("[upload_file()][ref={$ref}] Extracting embedded metadata...");
            extract_exif_comment($ref, $extension);
        } else {
            debug("[upload_file()][ref={$ref}] Not extracting embedded metadata!");
            if ($merge_filename_with_title && isset($processfile)) {
                $merge_filename_with_title_option = urlencode(getval('merge_filename_with_title_option', $merge_filename_with_title_default));
                $merge_filename_with_title_include_extensions = urlencode(getval('merge_filename_with_title_include_extensions', ''));
                $merge_filename_with_title_spacer = urlencode(getval('merge_filename_with_title_spacer', ''));

                $original_filename = '';
                if (isset($_REQUEST['file_name'])) {
                    $original_filename = $_REQUEST['file_name'];
                } else {
                    $original_filename = $processfile['name'];
                }

                if ($merge_filename_with_title_include_extensions == 'yes') {
                    $merged_filename = $original_filename;
                } else {
                    $merged_filename = strip_extension($original_filename);
                }

                global $view_title_field;
                $exif_fields = array_column(get_exiftool_fields($resource['resource_type']), 'ref');
                $oldval = get_data_by_field($ref, $view_title_field);

                if (strpos($oldval, $merged_filename) === false && in_array($view_title_field, $exif_fields)) {
                    switch (strtolower($merge_filename_with_title_option)) {
                        case strtolower($lang['merge_filename_title_do_not_use']):
                            // Do nothing since the user doesn't want to use this feature
                            break;

                        case strtolower($lang['merge_filename_title_replace']):
                            $newval = $merged_filename;
                            break;

                        case strtolower($lang['merge_filename_title_prefix']):
                            $newval = $merged_filename . $merge_filename_with_title_spacer . $oldval;
                            if ($oldval == '') {
                                $newval = $merged_filename;
                            }
                            break;

                        case strtolower($lang['merge_filename_title_suffix']):
                            $newval = $oldval . $merge_filename_with_title_spacer . $merged_filename;
                            if ($oldval == '') {
                                $newval = $merged_filename;
                            }
                            break;

                        default:
                            // Do nothing
                            break;
                    }

                    if (isset($newval)) {
                        update_field($ref, $view_title_field, $newval);
                    }
                }
            }
            autocomplete_blank_fields($ref, false);
        }
        # Extract text from documents (e.g. PDF, DOC)
        if (
            isset($extracted_text_field)
            && in_array($extension, $extracted_text_extensions)
            && !(isset($unoconv_path) && in_array($extension, $unoconv_extensions))
        ) {
            // We need to make sure we don't spin off a new offline job for this when upload_file() is being used in
            // upload_processing job handler
            if ($offline_job_queue && !$offline_job_in_progress) {
                $extract_text_job_data = array(
                    'ref'       => $ref,
                    'extension' => $extension,
                );

                job_queue_add('extract_text', $extract_text_job_data);
            } else {
                extract_text($ref, $extension);
            }
        }
    }

    # Store original filename in field, if set
    if (isset($filename_field)) {
        if (isset($amended_filename)) {
            $filename = $amended_filename;
        }
        if (!$revert && isset($filename)) {
            update_field($ref, $filename_field, $filename);
        } else {
            update_field($ref, $filename_field, $original_filename);
        }
    }
    if (!$upload_then_process || $after_upload_processing) {
        if (!$revert) {
            # Clear any existing video preview file or multi-page previews.
            for ($n = 2; $n <= $pdf_pages; $n++) {
                # Remove preview page.
                $path = get_resource_path($ref, true, "scr", false, "jpg", -1, $n, false);
                if (file_exists($path)) {
                    unlink($path);
                }
                # Also try the watermarked version.
                $path = get_resource_path($ref, true, "scr", false, "jpg", -1, $n, true);
                if (file_exists($path)) {
                    unlink($path);
                }
                # Remove preview page.
                $path = get_resource_path($ref, true, "pre", false, "jpg", -1, $n, false);
                if (file_exists($path)) {
                    unlink($path);
                }
                # Also try the watermarked version.
                $path = get_resource_path($ref, true, "pre", false, "jpg", -1, $n, true);
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            # Remove any video preview (except if the actual resource is in the preview format).
            if ($extension != $ffmpeg_preview_extension) {
                $path = get_resource_path($ref, true, "", false, $ffmpeg_preview_extension);
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            # Remove any preview-only file
            $path = get_resource_path($ref, true, "pre", false, $ffmpeg_preview_extension);
            if (file_exists($path)) {
                unlink($path);
            }


            # Remove any MP3 (except if the actual resource is an MP3 file).
            if ($extension != "mp3") {
                $path = get_resource_path($ref, true, "", false, "mp3");
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $checksum_required = true;
            if ($file_upload_block_duplicates && !$file_checksums_offline && generate_file_checksum($ref, $extension, false)) {
                $checksum_required = false;
            }
            start_previews($ref, $extension);
        }

        # Update file dimensions
        get_original_imagesize($ref, $filepath, $extension);
    }

    if ($upload_then_process && !$after_upload_processing) {
        # Add this to the job queue for offline processing

        $job_data = array();
        $job_data["resource"] = $ref;
        $job_data["extract"] = !$no_exif;
        $job_data["revert"] = $revert;
        $job_data["autorotate"] = $autorotate;

        if (isset($upload_then_process_holding_state)) {
            $job_data["archive"] = ps_value("SELECT archive value from resource where ref=?", array("i",$ref), "");
            update_archive_status($ref, $upload_then_process_holding_state);
        }

        $job_code = $ref . md5($job_data["resource"] . strtotime('now'));
        $job_failure_lang = "upload processing fail " . ": " . str_replace(array('%ref','%title'), array($ref,$filename), $lang["ref-title"]);
        job_queue_add("upload_processing", $job_data, $userref, '', '', $job_failure_lang, $job_code);
    }

    hook("uploadfilesuccess", "", array( "resource_ref" => $ref ));

    # Update disk usage
    update_disk_usage($ref);

    if (!$after_upload_processing) {
        # Log this activity.
        $log_ref = resource_log($ref, "u", 0);
        hook("upload_image_after_log_write", "", array($ref,$log_ref));
    }

    return true;
}

/**
 * Extract and process EXIF, IPTC, and FITS metadata for a resource.
 *
 * This function retrieves metadata from an image file, including EXIF, IPTC, and FITS metadata,
 * and updates the corresponding fields in the database. The process includes support for embedded
 * geolocation data, handling character encoding, and applying user-configurable options for specific fields.
 * If ExifTool is available, it is used for more comprehensive metadata extraction. Otherwise, the PHP
 * `exif_read_data` function is used as a fallback.
 *
 * @param int $ref The resource reference ID.
 * @param string $extension (optional) The file extension for the image, used to validate processing compatibility.
 * @return bool Returns `false` if the file does not exist; true on successful completion
 */
function extract_exif_comment($ref, $extension = "")
{
    global $lang;
    debug_function_call('extract_exif_comment', func_get_args());
    if (PHP_SAPI !== "cli") {
        set_processing_message(str_replace("[resource]", $ref, $lang["processing_extracting_metadata"]));
    }

    # Extract the EXIF comment from either the ImageDescription field or the UserComment
    # Also parse IPTC headers and insert
    # EXIF headers
    $exifoption = getval("no_exif", ""); // This may have been set to a non-standard value if allowing per field selection
    debug("[extract_exif_comment()][ref={$ref}] POSTED no_exif = " . json_encode($exifoption));
    if ($exifoption == "yes") {
        $exifoption = "no";
    } // Sounds odd but previously was no_exif so logic reversed
    if ($exifoption == "") {
        $exifoption = "yes";
    }
    debug("[extract_exif_comment()][ref={$ref}] exifoption = " . json_encode($exifoption));

    $image = get_resource_path($ref, true, "", false, $extension);
    if (!file_exists($image)) {
        return false;
    }
    hook("pdfsearch");

    global $exif_comment,$exiftool_no_process,$exiftool_resolution_calc, $disable_geocoding, $embedded_data_user_select_fields,$filename_field,$lang;
    resource_log($ref, LOG_CODE_TRANSFORMED, '', '', '', $lang['exiftooltag']);

    $processfile['name'] = '';

    $exiftool_fullpath = get_utility_path("exiftool");
    if (($exiftool_fullpath != false) && !in_array($extension, $exiftool_no_process)) {
        debug("[extract_exif_comment()][ref={$ref}] Extension valid for ExifTool processing");
        $resource = get_resource_data($ref);
        hook("beforeexiftoolextraction");

        if ($exiftool_resolution_calc) {
            # see if we can use exiftool to get resolution/units, and dimensions here.
            # Dimensions are normally extracted once from the view page, but for the original file, it should be done here if possible,
            # and exiftool can provide more data.
            exiftool_resolution_calc($image, $ref);
        }

        $read_from = get_exiftool_fields($resource['resource_type'], NODE_NAME_STRING_SEPARATOR, true);

        # run exiftool to get all the valid fields. Use -s -s option so that
        # the command result isn't printed in columns, which will help in parsing
        # We then split the lines in the result into an array
        $command = "{$exiftool_fullpath} -s -s -f -m -d \"%Y-%m-%d %H:%M:%S\" -a -G1 %%IMAGE%%";
        $output = run_command($command, false, ["%%IMAGE%%" => new CommandPlaceholderArg($image, 'is_valid_rs_path')]);
        $metalines = explode("\n", $output);

        $metadata = array(); # an associative array to hold metadata field/value pairs

        if (!$disable_geocoding) {
            # Set vars
            $dec_long = 0;
            $dec_lat = 0;
        }

        # go through each line and split field/value using the first
        # occurrance of ": ".  The keys in the associative array is converted
        # into uppercase for easier lookup later
        foreach ($metalines as $metaline) {
            $pos = stripos($metaline, ": ");
            if ($pos) { #get position of first ": ", return false if not exist
                # add to the associative array, also clean up leading/trailing space & single quote (on windows sometimes)

                # Extract group name and tag name.
                $s = explode("]", substr($metaline, 0, $pos));
                if (count($s) > 1 && strlen($s[0]) > 1) {
                    # Extract value
                    $value = strip_tags(trim(substr($metaline, $pos + 2)));
                    # Replace '..' with line feed - either Exiftool itself or Adobe Bridge replaces line feeds with '..'
                    $value = str_replace('....', chr(10) . chr(10), $value); // Two new line feeds in ExifPro are replaced with 4 dots '....'
                    $value = str_replace('...', chr(46) . chr(10), $value); # Three dots together is interpreted as a full stop then line feed, not the other way round
                    $value = str_replace('..', chr(10), $value);

                    # Convert to UTF-8 if not already encoded
                    $encoding = mb_detect_encoding($value, "UTF-8", true);
                    if ($encoding != "UTF-8") {
                        if (!$encoding) {
                            debug("extract_exif_comment: Unable to detect encoding for value in " . substr($metaline, 0, $pos) . " - possible invalid character. Attempting to convert to UTF-8 anyway.");
                            $value = mb_convert_encoding($value, 'UTF-8');
                        } else {
                            debug("extract_exif_comment: non-utf-8 value found. Extracted value: " . $value);
                            $value = mb_convert_encoding($value, 'UTF-8', $encoding);
                        }
                        debug("extract_exif_comment: Converted value: " . $value);
                    }

                    # Extract group name and tag name
                    $groupname = strtoupper(substr($s[0], 1));
                    $tagname = strtoupper(trim($s[1]));

                    if (!$disable_geocoding) {
                        if ($tagname == 'GPSLATITUDE' && preg_match("/^(?<degrees>\d+) deg (?<minutes>\d+)' (?<seconds>\d+\.?\d*)\"/", $value, $latitude)) {
                            $dec_lat = $latitude['degrees'] + ($latitude['minutes'] / 60) + ($latitude['seconds'] / (60 * 60));
                            if (strpos($value, 'S') !== false) {
                                $dec_lat = -1 * $dec_lat;
                            }
                        } elseif ($tagname == 'GPSLONGITUDE' && preg_match("/^(?<degrees>\d+) deg (?<minutes>\d+)' (?<seconds>\d+\.?\d*)\"/", $value, $longitude)) {
                            $dec_long = $longitude['degrees'] + ($longitude['minutes'] / 60) + ($longitude['seconds'] / (60 * 60));
                            if (strpos($value, 'W') !== false) {
                                $dec_long = -1 * $dec_long;
                            }
                        }
                    }
                    debug("Exiftool: extracted field before escape check '$groupname:$tagname', value is '" . $value . "'");
                    # Store both tag data under both tagname and groupname:tagname, to support both formats when mapping fields.
                    $metadata[$tagname] = $value;
                    $metadata[$groupname . ":" . $tagname] = $value;

                    if (strpos($groupname, "-") !== false) {
                        // Remove XMP sub namespace for XMP data if it has been entered without full qualified namespace to accommodate multiple file formats
                        $groupname = substr($groupname, 0, (strpos($groupname, "-")));
                        $metadata[$groupname . ":" . $tagname] = $value;
                    }

                    debug("[extract_exif_comment()][ref={$ref}] Extracted field '{$groupname}:{$tagname}', value = {$value}");
                }
            }
        }

        // We try to fetch the original filename from database.
        $resources = ps_query("SELECT resource.file_path FROM resource WHERE resource.ref = ?", ['i', $ref]);

        if ($resources) {
            $resource = $resources[0];
            if ($resource['file_path']) {
                $metadata['FILENAME'] = mb_basename($resource['file_path']);
            }
        }

        if (isset($metadata['FILENAME'])) {
            $metadata['STRIPPEDFILENAME'] = strip_extension($metadata['FILENAME']);
        }

        # Geolocation Metadata Support
        if (!$disable_geocoding && $dec_long != 0 && $dec_lat != 0) {
            ps_query("UPDATE resource SET geo_long= ?,geo_lat= ? WHERE ref= ?", ['d', $dec_long, 'd', $dec_lat, 'i', $ref]);
        }

        # Update portrait_landscape_field (when reverting metadata this was getting lost)
        update_portrait_landscape_field($ref);

        # now we lookup fields from the database to see if a corresponding value
        # exists in the uploaded file
        $exif_updated_fields = array();
        for ($i = 0; $i < count($read_from); $i++) {
            debug("[extract_exif_comment()][ref={$ref}] Looking up metadata field #{$read_from[$i]['ref']} exiftool mappings...");
            $field = explode(",", $read_from[$i]['exiftool_field']);
            foreach ($field as $subfield) {
                $subfield = strtoupper($subfield); // convert to upper case for easier comparision
                if (in_array($subfield, array_keys($metadata)) && $metadata[$subfield] != "-" && trim($metadata[$subfield]) != "") {
                    $read = true;
                    $value = $metadata[$subfield];
                    debug("[extract_exif_comment()][ref={$ref}] Found embedded field mapping for '{$subfield}' with value '{$value}'");

                    # Dropdown box or checkbox list?
                    if (in_array($read_from[$i]["type"], array(FIELD_TYPE_CHECK_BOX_LIST,FIELD_TYPE_DROP_DOWN_LIST,FIELD_TYPE_RADIO_BUTTONS))) {
                        # Check that the value is one of the options and only insert if it is an exact match.

                        # The use of safe_file_name and strtolower ensures matching takes place on alphanumeric characters only and ignores case.

                        # First fetch all options in all languages
                        $options = trim_array(explode(NODE_NAME_STRING_SEPARATOR, strtolower($read_from[$i]["options"])));

                        # If not in the options list, do not read this value
                        $s = trim_array(explode(",", $value));
                        $value = ""; # blank value
                        for ($n = 0; $n < count($s); $n++) {
                            if (trim($s[0]) != "" && (in_array(strtolower($s[$n]), $options))) {
                                $value .= "," . $s[$n];
                            }
                            # Translate the option and compare the traslated strings to the value
                            foreach ($options as $option) {
                                if (trim($s[0]) != "" && (in_array(strtolower($s[$n]), i18n_get_translations($option)))) {
                                    $value .= "," . $option;
                                }
                            }
                        }
                    }

                    if (in_array($read_from[$i]["type"], array(FIELD_TYPE_DATE, FIELD_TYPE_DATE_AND_OPTIONAL_TIME))) {
                        // RS expects dates to use hyphen separator.
                        debug("-- Data from exiftool command- field: " . $read_from[$i]["ref"] . ' type: ' . $read_from[$i]["type"] . " value: $value");
                        if (strpos($value, ':') == 4) {
                            $date_time_parts = explode(' ', $value);
                            $date_time_parts[0] = str_replace(':', '-', $date_time_parts[0]);
                            if ($read_from[$i]["type"] == FIELD_TYPE_DATE) {
                                $value = $date_time_parts[0];
                            } else {
                                $value = implode(' ', $date_time_parts);
                            }
                            debug("-- Converted- field: " . $read_from[$i]["ref"] . ' type: ' . $read_from[$i]["type"] . " value: $value");
                        }

                        $invalid_date = check_date_format($value);

                        if (!empty($invalid_date)) {
                            $invalid_date = str_replace("%field%", $read_from[$i]['name'], $invalid_date);
                            $invalid_date = str_replace("%row% ", "", $invalid_date);

                            debug("EXIF - " . $invalid_date);
                            continue;
                        }
                    }

                    # Read the data.
                    if ($read) {
                        if ($read_from[$i]['exiftool_filter'] != "") {
                            eval(eval_check_signed($read_from[$i]['exiftool_filter']));
                        }

                        $exiffieldoption = $exifoption;
                        debug("[extract_exif_comment()][ref={$ref}] exiffieldoption = " . json_encode($exiffieldoption));

                        if ($exifoption == "custom"  || (isset($embedded_data_user_select_fields)  && in_array($read_from[$i]['ref'], $embedded_data_user_select_fields))) {
                            debug("EXIF - custom option for field " . $read_from[$i]['ref'] . " : " . $exifoption);
                            $exiffieldoption = getval("exif_option_" . $read_from[$i]['ref'], $exifoption);
                        }

                        debug("EXIF - option for field " . $read_from[$i]['ref'] . " : " . $exiffieldoption);

                        if ($exiffieldoption == "no") {
                            continue;
                        } elseif ($exiffieldoption == "append") {
                            $spacechar = ($read_from[$i]["type"] == 2 || $read_from[$i]["type"] == 3) ? ", " : " ";
                            $oldval = get_data_by_field($ref, $read_from[$i]['ref']);
                            if (strpos($oldval, $value) !== false) {
                                continue;
                            }
                            $newval =  $oldval . $spacechar . iptc_return_utf8($value) ;
                        } elseif ($exiffieldoption == "prepend") {
                            $spacechar = ($read_from[$i]["type"] == 2 || $read_from[$i]["type"] == 3) ? ", " : " ";
                            $oldval = get_data_by_field($ref, $read_from[$i]['ref']);
                            if (strpos($oldval, $value) !== false) {
                                continue;
                            }
                            $newval =  iptc_return_utf8($value) . $spacechar . $oldval;
                        } else {
                            $newval =  iptc_return_utf8($value);
                        }

                        global $merge_filename_with_title, $merge_filename_with_title_default, $lang, $view_title_field;
                        if ($merge_filename_with_title && $read_from[$i]['ref'] == $view_title_field) {
                            $merge_filename_with_title_option             = urldecode(getval('merge_filename_with_title_option', $merge_filename_with_title_default));
                            $merge_filename_with_title_include_extensions = urldecode(getval('merge_filename_with_title_include_extensions', ''));
                            $merge_filename_with_title_spacer             = urldecode(getval('merge_filename_with_title_spacer', ''));

                            if (isset($_REQUEST['file_name'])) {
                                $original_filename = $_REQUEST['file_name'];
                            } else {
                                $original_filename = "";
                            }

                            if ($merge_filename_with_title_include_extensions == 'yes') {
                                $merged_filename = $original_filename;
                            } else {
                                $merged_filename = strip_extension($original_filename);
                            }

                            $oldval = get_data_by_field($ref, $read_from[$i]['ref']);
                            if ($value == "" || strpos($oldval, $value) !== false) {
                                continue;
                            }

                            switch (strtolower($merge_filename_with_title_option)) {
                                case strtolower($lang['merge_filename_title_do_not_use']):
                                    $newval = $oldval;
                                    break;

                                case strtolower($lang['merge_filename_title_replace']):
                                    $newval = $merged_filename;
                                    break;

                                case strtolower($lang['merge_filename_title_prefix']):
                                    $newval = $merged_filename . $merge_filename_with_title_spacer . $oldval;
                                    if ($oldval == '') {
                                        $newval = $merged_filename;
                                    }
                                    break;
                                case strtolower($lang['merge_filename_title_suffix']):
                                    $newval = $oldval . $merge_filename_with_title_spacer . $merged_filename;
                                    if ($oldval == '') {
                                        $newval = $merged_filename;
                                    }
                                    break;

                                default:
                                    // Do nothing
                                    break;
                            }
                        }

                        if (isset($newval)) {
                            update_field($ref, $read_from[$i]['ref'], $newval);
                            debug("[extract_exif_comment()][ref={$ref}] Updated field #{$read_from[$i]['ref']} with value '{$newval}'");
                        }
                            $exif_updated_fields[] = $read_from[$i]['ref'];


                            hook("metadata_extract_addition", "all", array($ref,$newval,$read_from,$i));

                            // newval needs to be unset in order to avoid carrying over the value from one exif field to
                            // another
                            unset($newval);
                    }
                } else {
                    debug("[extract_exif_comment()][ref={$ref}] Unable to find embedded field mapping for subfield '{$subfield}'");
                    // Process if no embedded title is found:
                    global $merge_filename_with_title, $merge_filename_with_title_default, $lang, $view_title_field;
                    if ($merge_filename_with_title && $read_from[$i]['ref'] == $view_title_field) {
                        $merge_filename_with_title_option = urlencode(getval('merge_filename_with_title_option', $merge_filename_with_title_default));
                        $merge_filename_with_title_include_extensions = urlencode(getval('merge_filename_with_title_include_extensions', ''));
                        $merge_filename_with_title_spacer = urlencode(getval('merge_filename_with_title_spacer', ''));

                        $original_filename = '';
                        if (isset($_REQUEST['file_name'])) {
                            $original_filename = $_REQUEST['file_name'];
                        } elseif (isset($processfile['name'])) {
                            $original_filename = $processfile['name'];
                        }

                        if ($merge_filename_with_title_include_extensions == 'yes') {
                            $merged_filename = $original_filename;
                        } else {
                            $merged_filename = strip_extension($original_filename);
                        }

                        $oldval = get_data_by_field($ref, $read_from[$i]['ref']);
                        if ($value == "" || strpos($oldval, $value) !== false) {
                            continue;
                        }

                        switch (strtolower($merge_filename_with_title_option)) {
                            case strtolower($lang['merge_filename_title_do_not_use']):
                                // Do nothing since the user doesn't want to use this feature
                                break;

                            case strtolower($lang['merge_filename_title_replace']):
                                $newval = $merged_filename;
                                break;

                            case strtolower($lang['merge_filename_title_prefix']):
                                $newval = $merged_filename . $merge_filename_with_title_spacer . $oldval;
                                if ($oldval == '') {
                                    $newval = $merged_filename;
                                }
                                break;
                            case strtolower($lang['merge_filename_title_suffix']):
                                $newval = $oldval . $merge_filename_with_title_spacer . $merged_filename;
                                if ($oldval == '') {
                                    $newval = $merged_filename;
                                }
                                break;

                            default:
                                // Do nothing
                                break;
                        }

                        if (isset($newval)) {
                            update_field($ref, $read_from[$i]['ref'], $newval);
                        }
                        $exif_updated_fields[] = $read_from[$i]['ref'];
                    }
                }
            }
        }
        if (!in_array($filename_field, $exif_updated_fields)) { // We have not found an embedded value for this field so we need to modify the $filename variable which will be used to set the data later in the upload_file function
            $exiffilenameoption = getval("exif_option_" . $filename_field, $exifoption);
            debug("EXIF - custom option for filename field " . $filename_field . " : " . $exiffilenameoption);
            if ($exiffilenameoption != "yes") { // We are not using the extracted filename as usual
                $uploadedfilename = $_REQUEST['file_name'];

                global $userref, $amended_filename;
                $entered_filename = get_data_by_field(-$userref, $filename_field);
                debug("EXIF - got entered file name " . $entered_filename);
                if ($exiffilenameoption == "no") { //Use the entered value
                    $amended_filename = $entered_filename;

                    if (trim($amended_filename) == '') {
                        $amended_filename = $uploadedfilename;
                    }

                    if (strpos($amended_filename, $extension) === false) {
                        $amended_filename .= '.' . $extension;
                    }
                } elseif ($exiffilenameoption == "append") {
                    $amended_filename =  $entered_filename . $uploadedfilename;
                } elseif ($exiffilenameoption == "prepend") {
                    $amended_filename =  strip_extension($uploadedfilename) . $entered_filename . "." . $extension;
                }
                debug("EXIF - created new file name " . $amended_filename);
            }
        }
    } elseif (isset($exif_comment)) {
        #
        # Exiftool is not installed. As a fallback we grab some predefined basic fields using the PHP function
        # exif_read_data()
        #
        debug("[extract_exif_comment()][ref={$ref}] ExifTool not installed, trying exif_read_data()...");
        if (function_exists("exif_read_data") && !in_array($extension, $exiftool_no_process)) {
            $data = exif_read_data($image);
        } else {
            $data = false;
        }

        if ($data !== false) {
            $comment = "";

            if (isset($data["ImageDescription"])) {
                $comment = $data["ImageDescription"];
            }
            if (($comment == "") && (isset($data["COMPUTED"]["UserComment"]))) {
                $comment = $data["COMPUTED"]["UserComment"];
            }
            if ($comment != "") {
                # Convert to UTF-8
                $comment = iptc_return_utf8($comment);

                # Save comment
                global $exif_comment;
                update_field($ref, $exif_comment, $comment);
            }
            if (isset($data["Model"])) {
                # Save camera make/model
                global $exif_model;
                update_field($ref, $exif_model, $data["Model"]);
            }
            if (isset($data["DateTimeOriginal"])) {
                # Save camera date/time
                global $exif_date;
                $date = $data["DateTimeOriginal"];
                # Reformat date to ISO standard
                $date = substr($date, 0, 4) . "-" . substr($date, 5, 2) . "-" . substr($date, 8, 11);
                update_field($ref, $exif_date, $date);
            }
        }

        # Try IPTC headers
        $GLOBALS["use_error_exception"] = true;
        try {
            getimagesize($image, $info);
        } catch (Throwable $e) {
            debug("extract_exif_comment: unable to get IPTC headers");
        }
        unset($GLOBALS["use_error_exception"]);

        if (isset($info["APP13"])) {
            $iptc = iptcparse($info["APP13"]);

            # Look for iptc fields, and insert.
            $fields = ps_query("select ref, type, iptc_equiv from resource_type_field where length(iptc_equiv)>0", array(), "schema");
            for ($n = 0; $n < count($fields); $n++) {
                $iptc_equiv = $fields[$n]["iptc_equiv"];
                if (isset($iptc[$iptc_equiv][0])) {
                    # Found the field
                    if (count($iptc[$iptc_equiv]) > 1) {
                        # Multiple values (keywords)
                        $value = "";
                        for ($m = 0; $m < count($iptc[$iptc_equiv]); $m++) {
                            if ($m > 0) {
                                $value .= ", ";
                            }
                            $value .= $iptc[$iptc_equiv][$m];
                        }
                    } else {
                        $value = $iptc[$iptc_equiv][0];
                    }

                    $value = iptc_return_utf8($value);

                    # Date parsing
                    if ($fields[$n]["type"] == 4) {
                        $value = substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
                    }

                    if (trim($value) != "") {
                        update_field($ref, $fields[$n]["ref"], $value);
                    }
                }
            }
        }
    }

    // Extract FITS metadata and overwrite any existing metadata (e.g from Exiftool, IPTC etc.)
    extractFitsMetadata($image, $ref);

    // Autocomplete any blank fields without overwriting any existing metadata
    autocomplete_blank_fields($ref, false);

    return true;
}

/**
 * Convert IPTC metadata text to UTF-8 encoding.
 *
 * This function attempts to detect the character encoding of IPTC metadata text and converts it to UTF-8.
 * If conversion is not possible due to missing libraries or if the encoding is unknown, the text is returned as-is.
 * It uses a predefined list of character encodings for the conversion attempts.
 *
 * @param string $text The IPTC metadata text to be converted.
 * @return string The converted text in UTF-8 encoding, or the original text if conversion fails.
 */
function iptc_return_utf8($text)
{
    # For the given $text, return the utf-8 equiv.
    # Used for iptc headers to auto-detect the character encoding.
    global $iptc_expectedchars,$mysql_charset;

    # No inconv library? Return text as-is
    if (!function_exists("iconv")) {
        return $text;
    }

    # No expected chars set? Return as is
    if ($iptc_expectedchars == "" || $mysql_charset == "utf8") {
        return $text;
    }

    $try = array("UTF-8","ISO-8859-1","Macintosh","Windows-1252");
    for ($n = 0; $n < count($try); $n++) {
        if ($try[$n] == "UTF-8") {
            $trans = $text;
        } else {
            $trans = @iconv($try[$n], "UTF-8", $text);
        }
        for ($m = 0; $m < strlen($iptc_expectedchars); $m++) {
            if (strpos($trans, substr($iptc_expectedchars, $m, 1)) !== false) {
                return $trans;
            }
        }
    }
    return $text;
}

/**
 * Generate previews for a resource, including thumbnails and alternative image sizes.
 *
 * This function creates or updates image previews for a resource, such as thumbnails,
 * based on the specified parameters. It supports a variety of configurations, including
 * resizing with ImageMagick or GD library, generating checksums, and setting file attributes.
 * If the file size exceeds a specified limit, it can queue the preview generation as an offline job.
 *
 * @param int $ref The resource ID for which previews are generated.
 * @param bool $thumbonly (optional) If `true`, only the thumbnail will be generated. Default is `false`.
 * @param string $extension (optional) The file extension for the resource. Default is "jpg".
 * @param bool $previewonly (optional) If `true`, only preview images will be generated. Default is `false`.
 * @param bool $previewbased (optional) If `true`, previews are generated based on existing previews rather than original files. Default is `false`.
 * @param int $alternative (optional) If set, specifies an alternative file ID to generate previews for. Default is `-1`.
 * @param bool $ignoremaxsize (optional) If `true`, the file size limit for preview generation is ignored. Default is `false`.
 * @param bool $ingested (optional) If `true`, marks the resource as already ingested into the system. Default is `false`.
 * @param bool $checksum_required (optional) If `true`, generates a checksum for the file. Default is `true`.
 * @param array $onlysizes (optional) Specifies an array of preview sizes to generate. If empty, all sizes are generated.
 * @return bool Returns `true` if previews were generated successfully; `false` otherwise.
 */
function create_previews($ref, $thumbonly = false, $extension = "jpg", $previewonly = false, $previewbased = false, $alternative = -1, $ignoremaxsize = false, $ingested = false, $checksum_required = true, $onlysizes = array())
{
    global $imagemagick_path, $preview_generate_max_file_size, $previews_allow_enlarge, $lang, $ffmpeg_preview_gif;
    global $previews_allow_enlarge, $offline_job_queue, $preview_no_flatten_extensions, $preview_keep_alpha_extensions;

    if (is_null($extension)) {
        debug("create_previews - failed for resource $ref - extension provided is null.");
        return false;
    }

    # FStemplate support - do not allow previews from the template to be changed.
    # Exception for the creation of alternative file previews which don't affect the template system.
    $is_resource_template = resource_file_readonly($ref);
    if ($alternative == -1 && $is_resource_template ) {
        return false;
    }

    # Used to preemptively create folder e.g. when using $originals_separate_storage
    # Not for template resources as will create empty folder based on template scramble key.
    if (!$is_resource_template){
        get_resource_path($ref, true, "pre", true);
    }

    if (!is_numeric($ref)) {
        trigger_error("Parameter 'ref' must be numeric!");
    }
    if (PHP_SAPI !== "cli") {
        set_processing_message(str_replace("[resource]", $ref, $lang["processing_creating_previews"]));
    }

    hook('create_previews_extra', '', array($ref));

    // keep_for_hpr will be set to true if necessary in preview_preprocessing.php to indicate that an intermediate jpg can serve as the hpr.
    // otherwise when the file extension is a jpg it's assumed no hpr is needed.

    resource_log($ref, LOG_CODE_TRANSFORMED, '', '', '', $lang['createpreviews']);
    debug_function_call("create_previews", func_get_args());

    $imversion = get_imagemagick_version();
    // Set correct syntax for commands to remove alpha channel
    if ($imversion[0] >= 7) {
        $alphaoff = "-alpha off";
    } else {
        $alphaoff = "+matte";
    }

    if (!$previewonly) {
        // make sure the extension is the same as the original so checksums aren't done for previews
        $o_ext = ps_value("SELECT file_extension value FROM resource WHERE ref=?", ["i",$ref], "");
        if ($extension == $o_ext && $checksum_required) {
            debug("create_previews - generate checksum for $ref");
            generate_file_checksum($ref, $extension);
        }
    }
    # first reset preview tweaks to 0
    ps_query("UPDATE resource SET preview_tweaks = '0|1' WHERE ref = ?", ['i', $ref]);
    // for compatibility with transform plugin, remove any
    // transform previews for this resource when regenerating previews
    $tpdir = get_temp_dir() . "/transform_plugin";
    if (is_dir($tpdir) && file_exists("$tpdir/pre_$ref.jpg")) {
        unlink("$tpdir/pre_$ref.jpg");
    }

    # pages/tools/update_previews.php?previewbased=true
    # use previewbased to avoid touching original files (to preserve manually-uploaded preview images
    # when regenerating previews (i.e. for watermarks)
    $file = get_preview_source_file($ref, $extension, $previewonly, $previewbased, $alternative, $ingested);

    debug("File source is $file");

    # Make sure the file exists, if not update preview_attempts so that we don't keep trying to generate a preview
    if (!file_exists($file)) {
        ps_query("UPDATE resource SET preview_attempts=IFNULL(preview_attempts,0) + 1 WHERE ref= ?", ['i', $ref]);
        return false;
    }

    if (!$ingested) {
        // When creating previews for non-ingested static sync files, they're already on the system so there's not much
        // to validate for at this point
        $GLOBALS['preview_preprocessing_file_validator'] = CommandPlaceholderArg::alwaysValid(...);
    }

    # If configured, make sure the file is within the size limit for preview generation
    if (isset($preview_generate_max_file_size) && !$ignoremaxsize) {
        $filesize = filesize_unlimited($file) / (1024 * 1024);# Get filesize in MB
        if ($filesize > $preview_generate_max_file_size) {
            if ($offline_job_queue) {
                $create_previews_job_data = array(
                    'resource' => $ref,
                    'thumbonly' => false,
                    'extension' => $extension
                );
                $create_previews_job_failure_text = str_replace('%RESOURCE', $ref, $lang['jq_create_previews_failure_text']);
                job_queue_add('create_previews', $create_previews_job_data, '', '', '', $create_previews_job_failure_text);
            }

            return false;
        }
    }

    # Locate imagemagick.
    $convert_fullpath = get_utility_path("im-convert");
    if (!$convert_fullpath) {
        debug("ERROR: Could not find ImageMagick 'convert' utility at location '$imagemagick_path'");
        return false;
    }

    $generateall = !($thumbonly || $previewonly || (count($onlysizes) > 0));

    if (in_array(strtolower($extension), ["jpg","jpeg","png"]) || (strtolower($extension) == "gif" && !$ffmpeg_preview_gif)) {
        if (isset($imagemagick_path)) {
            return create_previews_using_im($ref, $thumbonly, $extension, $previewonly, $previewbased, $alternative, $ingested, $onlysizes);
        } else {
            # ----------------------------------------
            # Use the GD library to perform the resize
            # ----------------------------------------

            // Determine dimensions of the image, if it fails return 
            // false as future GD functions won't work either
            if ((list($sw,$sh) = try_getimagesize($file)) === false) {
                return false;   
            }

            # For resource $ref, (re)create the various preview sizes listed in the table preview_sizes
            # Only create previews where the target size IS LESS THAN OR EQUAL TO the source size.
            # Set thumbonly=true to (re)generate thumbnails only.

            $ps = get_sizes_to_generate($extension, [$sw,$sh], $thumbonly, $previewonly, $onlysizes);

            for ($n = 0; $n < count($ps); $n++) {
                # fetch target width and height
                $tw = $ps[$n]["width"];
                $th = $ps[$n]["height"];
                $id = $ps[$n]["id"];

                # Find the target path
                $path = get_resource_path($ref, true, $ps[$n]["id"], false, "jpg", -1, 1, false, "", $alternative);
                if (file_exists($path) && !$previewbased) {
                    unlink($path);
                }
                # Also try the watermarked version.
                $wpath = get_resource_path($ref, true, $ps[$n]["id"], false, "jpg", -1, 1, true, "", $alternative);
                if (file_exists($wpath)) {
                    unlink($wpath);
                }

                # only create previews where the target size IS LESS THAN OR EQUAL TO the source size.
                # or when producing a small thumbnail (to make sure we have that as a minimum)
                if ($previews_allow_enlarge || $sw > $tw || $sh > $th || $id == "thm" || $id == "col") {
                    # Calculate width and height.
                    if ($sw > $sh) {
                        $ratio = ($tw / $sw);
                    } # Landscape
                    else {
                        $ratio = ($th / $sh);
                    } # Portrait
                    $tw = floor($sw * $ratio);
                    $th = floor($sh * $ratio);

                    # ----------------------------------------
                    # Use the GD library to perform the resize
                    # ----------------------------------------

                    $target = imagecreatetruecolor($tw, $th);

                    if ($extension == "png") {
                        $source = @imagecreatefrompng($file);
                        if ($source === false) {
                            return false;
                        }
                    } elseif ($extension == "gif") {
                        $source = @imagecreatefromgif($file);
                        if ($source === false) {
                            return false;
                        }
                    } else {
                        $source = @imagecreatefromjpeg($file);
                        if ($source === false) {
                            return false;
                        }
                    }

                    imagecopyresampled($target, $source, 0, 0, 0, 0, $tw, $th, $sw, $sh);
                    imagejpeg($target, $path, 90);

                    if ($ps[$n]["id"] == "thm") {
                        extract_mean_colour($target, $ref);
                    }
                    imagedestroy($target);
                } elseif (($id == "pre") || ($id == "thm") || ($id == "col")) {
                    # If the source is smaller than the pre/thm/col, we still need these sizes; just copy the file
                    copy($file, get_resource_path($ref, true, $id, false, $extension, -1, 1, false, "", $alternative));
                    if ($id == "thm") {
                        ps_query("UPDATE resource SET thumb_width= ?,thumb_height= ? WHERE ref= ?", ['i', $sw, 'i', $sh, 'i', $ref]);
                    }
                }
            }
            # flag database so a thumbnail appears on the site
            if ($alternative == -1) {
                // Not for alternatives
                $has_image = $generateall || $previewonly ? RESOURCE_PREVIEWS_ALL : RESOURCE_PREVIEWS_MINIMAL;
                ps_query("UPDATE resource SET has_image=?,preview_extension='jpg',preview_attempts=0,file_modified=now() WHERE ref= ?", ['i',$has_image,'i', $ref]);
            }
        }
    } else {
        # If using ImageMagick, call preview_preprocessing.php which makes use of ImageMagick and other tools
        # to attempt to extract a preview.
        global $no_preview_extensions;
        if (isset($imagemagick_path) && !in_array(strtolower($extension), $no_preview_extensions)) {
            $preview_preprocessing_success = false;
            include __DIR__ . "/preview_preprocessing.php";
            // $created_previews will have been set in preview_preprocessing.php to indicate succces/failure
            if (!$preview_preprocessing_success) {
                ps_query("UPDATE resource SET preview_attempts = ifnull(preview_attempts, 0) + 1 where ref = ?", array("i", $ref));
                return false;
            }
        }
    }

    if ($alternative == -1 && isset($GLOBALS["image_alternatives"]) && $generateall) {
        // Create alternatives
        create_image_alternatives(
            $ref,
            ["extension" => $extension,
            "file" => $file,
            "previewonly" => $previewonly,
            "previewbased" => $previewbased,
            "ingested" => $ingested],
        );
    }

    # Flag database so a thumbnail appears on the site
    if ($alternative == -1) {
        // Not for alternatives
        if (!isset($has_image)) {
            # preview_preprocessing.php may provide a value e.g. 0 for file type where no image is expected but preview creation successful.
            # For example, a .wma file is expected to produce an .mp3 preview and this has no image.
            $has_image = $generateall || $previewonly ? RESOURCE_PREVIEWS_ALL : RESOURCE_PREVIEWS_MINIMAL;
        }
        ps_query("UPDATE resource SET has_image=?,preview_extension='jpg',preview_attempts=0,file_modified=now() WHERE ref= ?", ['i',$has_image,'i', $ref]);
    }

    hook('afterpreviewcreation', '', array($ref, $alternative, $generateall));
    return true;
}

/**
 * Calculate and update the mean color values for a resource image.
 * Calculate and update the mean color values for a resource image.
 *
 * This function calculates the mean red, green, and blue color values for a given image by sampling
 * pixels in a grid pattern. It adjusts for brightness and excludes grayscale pixels to determine the dominant colors.
 * Additionally, it updates the thumbnail dimensions and color key in the resource record.
 *
 * @param GdImage $image The image resource to analyze for mean color.
 * @param int $ref The resource ID for which the color data is updated.
 * @return void
 */
function extract_mean_colour($image, $ref)
{
    # for image $image, calculate the mean colour and update this to the image_red, image_green, image_blue tables
    # in the resources table.
    # Also - we insert the height and width of the thumbnail at this stage as all information is available and we
    # are already performing an update on the resource record.

    $width = imagesx($image);
    $height = imagesy($image);
    $totalred = 0;
    $totalgreen = 0;
    $totalblue = 0;
    $total = 0;

    for ($y = 0; $y < 20; $y++) {
        for ($x = 0; $x < 20; $x++) {
            $rgb = imagecolorat($image, floor($x * ($width / 20)), floor($y * ($height / 20)));
            $red = ($rgb >> 16) & 0xFF;
            $green = ($rgb >> 8) & 0xFF;
            $blue = $rgb & 0xFF;

            # calculate deltas (remove brightness factor)
            $cmax = max($red, $green, $blue);
            $cmin = min($red, $green, $blue);
            if ($cmax == $cmin) {
                $cmax = 10;
                $cmin = 0;
            } # avoid division errors
            if (abs($cmax - $cmin) >= 20) { # ignore gray/white/black
                $red = floor((($red - $cmin) / ($cmax - $cmin)) * 1000);
                $green = floor((($green - $cmin) / ($cmax - $cmin)) * 1000);
                $blue = floor((($blue - $cmin) / ($cmax - $cmin)) * 1000);

                $total++;
                $totalred += $red;
                $totalgreen += $green;
                $totalblue += $blue;
            }
        }
    }
    if ($total == 0) {
        $total = 1;
    }
    $totalred = floor($totalred / $total);
    $totalgreen = floor($totalgreen / $total);
    $totalblue = floor($totalblue / $total);

    $colkey = get_colour_key($image);

    update_portrait_landscape_field($ref, $image);

    ps_query(
        "update resource set image_red= ?, image_green= ?, image_blue= ?,colour_key= ?,thumb_width= ?, thumb_height= ? where ref= ?",
        [
        'i', $totalred,
        'i', $totalgreen,
        'i', $totalblue,
        's', $colkey,
        'i', $width,
        'i', $height,
        'i', $ref
        ]
    );
}

/**
 * Update the portrait or landscape orientation field for a resource.
 *
 * This function determines whether a resource image is in portrait, landscape, or square orientation
 * and updates the specified field accordingly. If no image resource is provided, it attempts to load
 * the thumbnail image for the resource.
 *
 * @param int $ref The resource ID to update.
 * @param resource|null $image (optional) An image resource for orientation analysis. If not provided,
 *                             the function will load the resource thumbnail.
 * @return void
 */
function update_portrait_landscape_field($ref, $image = null)
{
    # updates portrait_landscape_field

    global $portrait_landscape_field,$lang;
    if (isset($portrait_landscape_field)) {
        if (!$image) {
            $thumbfile = get_resource_path($ref, true, "thm", false, "jpg");
            if (!file_exists($thumbfile)) {
                return;
            }
            $image = @imagecreatefromjpeg($thumbfile);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        # Write 'Portrait' or 'Landscape' to the appropriate field.
        if ($width > $height) {
            $portland = $lang["landscape"];
        } elseif ($height > $width) {
            $portland = $lang["portrait"];
        } elseif ($height == $width) {
            $portland = $lang["square"];
        }
        update_field($ref, $portrait_landscape_field, $portland);
    }
}

/**
 * Generate a color key for an image based on dominant colors.
 *
 * This function extracts a color key that represents the dominant colors in an image,
 * similar to a soundex code for colors. It maps the colors in the image to predefined
 * color categories (e.g., black, white, red, etc.), calculates the closest match for each
 * sampled pixel, and returns a five-character color key based on the most frequent colors.
 *
 * @param GdImage $image The image resource to analyze.
 * @return string A five-character string representing the dominant colors in the image.
 */
function get_colour_key($image)
{
    # Extracts a colour key for the image, like a soundex.
    $width = imagesx($image);
    $height = imagesy($image);
    $colours = array(
    "K" => array(array(0,0,0)),         # Black
    "W" => array(array(255,255,255)),   # White
    "E" => array(array(200,200,200),
                array(140,140,140),
                array(100,100,100)),    # Grey
    "R" => array(array(255,0,0),        # Red
                array(128,0,0),         # Dark Red
                array(180,0,40)),       # Dark Red
    "G" => array(array(0,255,0),        # Green
                array(0,128,0),         # Dark Green
                array(80,120,90),       # Faded Green
                array(140,170,90)),     # Pale Green
    "B" => array(array(0,0,255),        # Blue
                array(0,0,128),         # Dark Blue
                array(90,90,120),       # Dark Blue
                array(60,60,90),        # Dark Blue
                array(90,140,180)),     # Light Blue
    "C" => array(array(0,255,255),      # Cyan
                array(0,200,200)),      # Cyan
    "M" => array(array(255,0,255)),     # Magenta
    "Y" => array(array(255,255,0),      # Yellow
                array(180,160,40),      # Yellow
                array(210,190,60)),     # Yellow
    "O" => array(array(255,128,0),      # Orange
                array(200,100,60)),     # Orange
    "P" => array(array(255,128,128),    # Pink
                array(200,180,170),     # Pink
                array(200,160,130),     # Pink
                array(190,120,110)),    # Pink
    "N" => array(array(110,70,50),      # Brown
                array(180,160,130),     # Pale Brown
                array(170,140,110)),    # Pale Brown
    );
    $table = array();
    $depth = 50;
    for ($y = 0; $y < $depth; $y++) {
        for ($x = 0; $x < $depth; $x++) {
            $rgb = imagecolorat($image, floor($x * ($width / $depth)), floor($y * ($height / $depth)));
            $red = ($rgb >> 16) & 0xFF;
            $green = ($rgb >> 8) & 0xFF;
            $blue = $rgb & 0xFF;
            # Work out which colour this is
            $bestdist = 99999;
            $bestkey = "";
            reset($colours);
            foreach ($colours as $key => $colour_value) {
                foreach ($colour_value as $value) {
                    $distance = sqrt(pow(abs($red - $value[0]), 2) + pow(abs($green - $value[1]), 2) + pow(abs($blue - $value[2]), 2));
                    if ($distance < $bestdist) {
                        $bestdist = $distance;
                        $bestkey = $key;
                    }
                }
            }
            # Add this colour to the colour table.
            if (array_key_exists($bestkey, $table)) {
                $table[$bestkey]++;
            } else {
                $table[$bestkey] = 1;
            }
        }
    }
    asort($table);
    reset($table);
    $colkey = "";
    foreach ($table as $key => $value) {
        $colkey .= $key;
    }
    $colkey = substr(strrev($colkey), 0, 5);
    return $colkey;
}

/**
 * Apply rotation and gamma adjustments to all preview images of a resource.
 *
 * This function adjusts the preview images of a resource by rotating or applying gamma correction.
 * It primarily modifies the screen resolution preview, then scales and updates other preview sizes
 * accordingly. For video resources, it also rotates snapshot images. Updates to rotation and gamma
 * values are recorded in the database to allow future reconstruction of adjustments.
 *
 * @param int $ref The resource ID for which previews are tweaked.
 * @param int $rotateangle The angle in degrees to rotate the preview images.
 * @param float $gamma The gamma correction factor (values > 1 to lighten, values < 1 to darken).
 * @param string $extension (optional) The file extension for the preview images. Default is "jpg".
 * @param int $alternative (optional) The ID of an alternative file to tweak previews for. Default is `-1`.
 * @param string $resource_ext (optional) The file extension of the original resource, used for video snapshot rotation.
 * @return bool Returns `false` if the main preview file does not exist; otherwise, updates previews and returns true.
 */
function tweak_preview_images($ref, $rotateangle, $gamma, $extension = "jpg", $alternative = -1, $resource_ext = "")
{
    # Use the screen resolution version for processing
    global $ffmpeg_supported_extensions, $lang;

    $file = get_resource_path($ref, true, "scr", false, $extension, -1, 1, false, '', $alternative);
    $top = "scr";
    if (!file_exists($file)) {
        # Some images may be too small to have a scr.  Try pre:
        $file = get_resource_path($ref, true, "pre", false, $extension, -1, 1, false, '', $alternative);
        $top = "pre";
    }

    if (!file_exists($file)) {
        return false;
    }

    set_processing_message(str_replace("[resource]", $ref, $lang["processing_updating_previews"]));

    $source = imagecreatefromjpeg($file);
    # Apply tweaks
    if ($rotateangle != 0) {
        # Use built-in function if available, else use function in this file
        if (function_exists("imagerotate")) {
            $source = imagerotate($source, $rotateangle, 0);
        } else {
            $source = AltImageRotate($source, $rotateangle);
        }
    }

    if ($gamma != 0) {
        imagegammacorrect($source, 1.0, $gamma);
    }

    # Save source image and fetch new dimensions

    imagejpeg($source, $file, 95);

    list($tw,$th) = try_getimagesize($file);

    # Save all images
    $ps = ps_query("SELECT " . columns_in("preview_size") . " FROM preview_size WHERE (internal=1 OR allow_preview=1) AND id <> ?", ['s', $top]);
    for ($n = 0; $n < count($ps); $n++) {
        # fetch target width and height
        $file = get_resource_path($ref, true, $ps[$n]["id"], false, $extension, -1, 1, false, '', $alternative);
        if (file_exists($file)) {
            list($sw,$sh) = try_getimagesize($file);

            if ($rotateangle != 0) {
                $temp = $sw;
                $sw = $sh;
                $sh = $temp;
            }

            # Rescale image
            $target = imagecreatetruecolor($sw, $sh);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $sw, $sh, $tw, $th);
            if ($extension == "png") {
                imagepng($target, $file);
            } elseif ($extension == "gif") {
                imagegif($target, $file);
            } else {
                imagejpeg($target, $file, 95);
            }
        }
    }

    if ($rotateangle != 0 && $alternative == -1) {
        # Swap thumb heights/widths
        $ts = ps_query("SELECT thumb_width,thumb_height FROM resource WHERE ref= ?", ['i', $ref]);
        ps_query(
            "UPDATE resource SET thumb_width= ?,thumb_height= ? WHERE ref= ?",
            [
                'i', $ts[0]["thumb_height"],
                'i', $ts[0]["thumb_width"],
                'i', $ref
                ]
        );

        global $portrait_landscape_field,$lang;
        if (isset($portrait_landscape_field)) {
            # Write 'Portrait' or 'Landscape' to the appropriate field.
            if ($ts[0]["thumb_height"] >= $ts[0]["thumb_width"]) {
                $portland = $lang["landscape"];
            } else {
                $portland = $lang["portrait"];
            }
            update_field($ref, $portrait_landscape_field, $portland);
        }
    }
    # Update the modified date to force the browser to reload the new thumbs.
    $current_preview_tweak = '';
    if ($alternative == -1) {
        ps_query("UPDATE resource SET file_modified=NOW() WHERE ref= ?", ['i', $ref]);

    # record what was done so that we can reconstruct later if needed
    # current format is rotation|gamma. Additional could be tacked on if more manipulation options are added
        $current_preview_tweak = ps_value("select preview_tweaks value from resource where ref = ?", array("i",$ref), "");
    }

    if (strlen($current_preview_tweak) == 0) {
            $oldrotate = 0;
            $oldgamma = 1;
    } else {
        list($oldrotate,$oldgamma) = explode('|', $current_preview_tweak);
    }
        $newrotate = $oldrotate + $rotateangle;
    if ($newrotate > 360) {
        $newrotate = $newrotate - 360;
    } elseif ($newrotate < 0) {
        $newrotate = 360 + $newrotate;
    } elseif ($newrotate == 360) {
        $newrotate = 0;
    }
    if ($gamma > 0) {
        $newgamma = $oldgamma +  $gamma - 1;
    } else {
        $newgamma = $oldgamma;
    }
        global $watermark;
    if ($watermark) {
        tweak_wm_preview_images($ref, $rotateangle, $gamma, "jpg", $alternative);
    }
    if ($alternative == -1) {
        ps_query("update resource set preview_tweaks = ? where ref = ?", ['s', $newrotate . '|' . $newgamma, 'i', $ref]);
    }

    if (
        $rotateangle != 0
        && $resource_ext != ""
        && in_array($resource_ext, $ffmpeg_supported_extensions)
    ) {
            # Find snapshots for video files so they can be rotated with the thumbnail
            $video_snapshots = get_video_snapshots($ref, true, false);
        foreach ($video_snapshots as $snapshot) {
            $snapshot_source = imagecreatefromjpeg($snapshot);
            # Use built-in function if available, else use function in this file
            if (function_exists("imagerotate")) {
                $snapshot_source = imagerotate($snapshot_source, $rotateangle, 0);
            } else {
                $snapshot_source = AltImageRotate($snapshot_source, $rotateangle);
            }
            imagejpeg($snapshot_source, $snapshot, 95);
        }
    }
    return true;
}

/**
 * Apply rotation and gamma adjustments to all watermarked preview images of a resource.
 *
 * This function modifies watermarked preview images by applying a specified rotation angle
 * and gamma correction factor. Each preview size defined in the database is processed, adjusting
 * the watermarked version accordingly. The function resizes and updates each adjusted preview image.
 *
 * @param int $ref The resource ID for which watermarked previews are tweaked.
 * @param int $rotateangle The angle in degrees to rotate the watermarked preview images.
 * @param float $gamma The gamma correction factor to apply (values > 1 to lighten, values < 1 to darken).
 * @param string $extension (optional) The file extension for the preview images. Default is "jpg".
 * @param int $alternative (optional) The ID of an alternative file to tweak previews for. Default is `-1`.
 * @return bool Returns `false` if a watermarked file does not exist; otherwise, updates previews and returns true.
 */
function tweak_wm_preview_images($ref, $rotateangle, $gamma, $extension = "jpg", $alternative = -1)
{

    $ps = ps_query("select ref,id,width,height,padtosize,name,internal,allow_preview,allow_restricted,quality from preview_size where (internal=1 or allow_preview=1)");
    for ($n = 0; $n < count($ps); $n++) {
        $wm_file = get_resource_path($ref, true, $ps[$n]["id"], false, $extension, -1, 1, true, '', $alternative);
        if (!file_exists($wm_file)) {
            return false;
        }
        list($sw,$sh) = try_getimagesize($wm_file);

        $wm_source = imagecreatefromjpeg($wm_file);

        # Apply tweaks
        if ($rotateangle != 0) {
            # Use built-in function if available, else use function in this file
            if (function_exists("imagerotate")) {
                $wm_source = imagerotate($wm_source, $rotateangle, 0);
            } else {
                $wm_source = AltImageRotate($wm_source, $rotateangle);
            }
        }

        if ($gamma != 0) {
            imagegammacorrect($wm_source, 1.0, $gamma);
        }
        imagejpeg($wm_source, $wm_file, 95);
                list($tw,$th) = try_getimagesize($wm_file);
        if ($rotateangle != 0) {
            $temp = $sw;
            $sw = $sh;
            $sh = $temp;
        }

        # Rescale image
        $wm_target = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($wm_target, $wm_source, 0, 0, 0, 0, $sw, $sh, $tw, $th);
        imagejpeg($wm_target, $wm_file, 95);
    }
    return true;
}

/**
 * Rotate an image resource by a specified angle without using built-in rotation functions.
 *
 * This function manually rotates an image resource by 90, -90, or 180 degrees, creating
 * a new image with the rotated pixels. If the angle is not one of these specified values,
 * the original image is returned unmodified.
 *
 * @param GdImage $src_img The source image resource to rotate.
 * @param int $angle The angle in degrees to rotate the image (90, -90, or 180).
 * @return resource The rotated image resource, or the original if the angle is unsupported.
 */
function AltImageRotate($src_img, $angle)
{

    if ($angle == 270) {
        $angle = -90;
    }

    $src_x = imagesx($src_img);
    $src_y = imagesy($src_img);
    if ($angle == 90 || $angle == -90) {
        $dest_x = $src_y;
        $dest_y = $src_x;
    } else {
        $dest_x = $src_x;
        $dest_y = $src_y;
    }

    $rotate = imagecreatetruecolor($dest_x, $dest_y);
    imagealphablending($rotate, false);

    switch ($angle) {
        case 90:
            for ($y = 0; $y < ($src_y); $y++) {
                for ($x = 0; $x < ($src_x); $x++) {
                    $color = imagecolorat($src_img, $x, $y);
                    imagesetpixel($rotate, $dest_x - $y - 1, $x, $color);
                }
            }
            break;
        case -90:
            for ($y = 0; $y < ($src_y); $y++) {
                for ($x = 0; $x < ($src_x); $x++) {
                    $color = imagecolorat($src_img, $x, $y);
                    imagesetpixel($rotate, $y, $dest_y - $x - 1, $color);
                }
            }
            break;
        case 180:
            for ($y = 0; $y < ($src_y); $y++) {
                for ($x = 0; $x < ($src_x); $x++) {
                    $color = imagecolorat($src_img, $x, $y);
                    imagesetpixel($rotate, $dest_x - $x - 1, $dest_y - $y - 1, $color);
                }
            }
            break;
        default:
            $rotate = $src_img;
    }
    return $rotate;
}

/**
 * Convert a base64-encoded image to a JPEG file.
 *
 * This function decodes a base64-encoded image string and saves it as a JPEG file.
 *
 * @param string $imageData The base64-encoded image data.
 * @param string $outputfile The path to save the decoded JPEG file.
 * @return void
 * @throws Exception if the output file cannot be opened for writing.
 */
function base64_to_jpeg($imageData, $outputfile)
{

    $jpeg = fopen($outputfile, "wb") or die("can't open");
    fwrite($jpeg, base64_decode($imageData));
    fclose($jpeg);
}

/**
* Extracts JPG previews from INDD files when these have been set with a preview
* Note: it requires ExifTool >= 9.50
*
* @param string $filename
*
* @return array|bool
*/
function extract_indd_pages($filename)
{
    $exiftool_fullpath = get_utility_path('exiftool');
    if ($exiftool_fullpath) {
        $cmd = $exiftool_fullpath . ' -b -j -pageimage %%FILENAME%%';
        $array = run_command(
            $cmd,
            false,
            ["%%FILENAME%%" => new CommandPlaceholderArg($filename, "is_safe_basename")],
        );
        $array = json_decode($array);
        if (isset($array[0]->PageImage)) {
            if (is_array($array[0]->PageImage)) {
                return $array[0]->PageImage;
            } else {
                return array($array[0]->PageImage);
            }
        }
    }

    return false;
}

/**
 * Generate or update the checksum for a resource file.
 *
 * This function calculates a unique checksum for a specified resource file. It either
 * generates the checksum based on file contents and updates the resource record,
 * or clears any existing checksum if the file does not exist. It also sets metadata
 * indicating the checksum's last verification date and integrity status.
 *
 * @param int $resource The ID of the resource for which the checksum is generated.
 * @param string $extension The file extension of the resource.
 * @param bool $anyway (optional) If `true`, forces checksum generation regardless of configuration settings.
 * @return bool Returns `true` if the checksum was generated; `false` if the file does not exist or was not generated.
 */
function generate_file_checksum($resource, $extension, $anyway = false)
{
    global $file_checksums;
    global $file_checksums_offline;
    $generated = false;

    debug("generate_file_checksum(resource = $resource, extension = $extension, anyway = $anyway)");
    if (($file_checksums && !$file_checksums_offline) || $anyway) { // do it if file checksums are turned on, or if requestor said do it anyway
        # Generates a unique checksum for the given file, based either on the first 50K and the file size or the full file.
        $path = get_resource_path($resource, true, "", false, $extension);
        if (file_exists($path)) {
            $checksum = get_checksum($path);
            ps_query("UPDATE resource SET file_checksum = ?, last_verified = NOW(), integrity_fail = 0 WHERE ref= ?", ['s', $checksum, 'i', $resource]);
            $generated = true;
        }
    }

    if ($generated) {
        return true;
    } else {
        # if we didn't generate a new file checksum, clear any existing one so that it will not be incorrect
        # The lack of checksum will also be used as the trigger for the offline process
        clear_file_checksum($resource);
        return false;
    }
}

/**
 * Clear the checksum value for a specified resource.
 *
 * @param int $resource The ID of the resource to clear the checksum for.
 * @return bool Returns `true` if the checksum was cleared successfully; `false` if the resource ID is invalid.
 */
function clear_file_checksum($resource)
{
    if (strlen($resource) > 0 && is_numeric($resource)) {
        ps_query("UPDATE resource SET file_checksum='' WHERE ref= ?", ['i', $resource]);
        return true;
    } else {
        return false;
    }
}

/**
 * Check for duplicate files based on checksum.
 *
 * This function calculates the checksum of a file and checks if any existing resources
 * have the same checksum, indicating a possible duplicate. If a duplicate is found and
 * the resource is not marked for replacement, the function returns the list of duplicate
 * resource IDs.
 *
 * @param string $filepath The file path of the file to check.
 * @param int $replace_resource The resource ID to replace, if applicable, to avoid detecting it as a duplicate.
 * @return array An array of duplicate resource IDs, or an empty array if no duplicates are found.
 */
function check_duplicate_checksum($filepath, $replace_resource)
{
    global $file_upload_block_duplicates,$file_checksums_50k;
    if ($file_upload_block_duplicates) {
        # Generate the ID
        if ($file_checksums_50k) {
            # Fetch the string used to generate the unique ID
            $use = filesize_unlimited($filepath) . "_" . file_get_contents($filepath, false, null, 0, 50000);
            $checksum = md5($use);
        } else {
            $checksum = md5_file($filepath);
        }
        $duplicates = ps_array("select ref value from resource where file_checksum=?", array("s",$checksum));
        if (count($duplicates) > 0 && !($replace_resource && in_array($replace_resource, $duplicates))) {
            return $duplicates;
        }
    }
    return array();
}

/**
 * Upload and generate previews for a resource.
 *
 * This function uploads a user-provided preview image for a specified resource, ensuring
 * the file is in JPEG format. After moving the file to its temporary location, it generates
 * previews and cleans up the temporary file if not in use for transcoding.
 *
 * @param int $ref The resource ID for which the preview is uploaded.
 * @return bool Returns `true` if the preview upload and processing are successful, `false` if the file is not in JPEG format.
 */
function upload_preview($ref)
{
    hook("removeannotations", "", array($ref));

    $temp_file = get_resource_path($ref, true, 'tmp', true, 'jpg');

    if (
        !process_file_upload(
            $_FILES['userfile'],
            new SplFileInfo($temp_file),
            ['allow_extensions' => ['jpg', 'jpeg']]
        )['success']
    ) {
        return false;
    }

    resource_log($ref, LOG_CODE_UPLOADED_PREVIEW, '', $_FILES['userfile']['name']);
    chmod($temp_file, 0777);
    create_previews($ref, false, 'jpg', true);

    if (
        file_exists($temp_file)
        && !ps_value('SELECT is_transcoding value FROM resource WHERE ref = ?', ['i', $ref], false)
    ) {
        unlink($temp_file);
    }

    return true;
}

/**
* Extract text from the resource and save to the configured field
*
* @uses resource_log()
* @uses get_resource_path()
* @uses debug()
* @uses run_command()
* @uses hook()
* @uses update_field()
*
* @param integer $ref        Resource ref
* @param string  $extension  File extension
* @param string  $path       Path can be set to use an alternate file, for example, in the case of unoconv
*
* @return  bool   Returns false on error else true.
*/
function extract_text($ref, $extension, $path = "")
{
    global $extracted_text_field,$pdftotext_path,$lang;

    resource_log($ref, LOG_CODE_TRANSFORMED, '', '', '', $lang['embedded_metadata_extract_option']);

    $text = "";
    if ($path == "") {
        $path = get_resource_path($ref, true, "", false, $extension);
    }

    if (!file_exists($path)) {
        debug("ERROR: Unable to extract text for resource $ref. The source file does not exist at: $path");
        return false;
    }

    # Microsoft OfficeOpen (docx,xlsx) extraction
    if (in_array(strtolower($extension), ["docx","xlsx"])) {
        // DOCX files are zip files and the content is in word/document.xml.
        // Extract this then remove tags.
        $element = strtolower($extension) == "xlsx" ? "xl/sharedStrings.xml" : "word/document.xml";
        $cmd = "unzip -p %%PATH%% %%ELEMENT%%";
        $args = [
            '%%PATH%%' => new CommandPlaceholderArg($path, 'is_valid_rs_path'),
            '%%ELEMENT%%' => new CommandPlaceholderArg($element, [CommandPlaceholderArg::class, 'alwaysValid']),
        ];
        $text = run_command($cmd, false, $args);

        # Remove tags, but add newlines as appropriate (without this, separate text blocks are joined together with no spaces).
        $text = str_replace("<", "\n<", $text);
        $text = trim(strip_tags($text));
        while (strpos($text, "\n\n") !== false) {
            $text = str_replace("\n\n", "\n", $text);
        } # condense multiple line breaks

        # Replace XML encoded characters with the correct unencoded variants
        $text = strtr($text, [
            '&quot;' => '"',
            '&apos;' => "'",
            '&amp;'  => '&',
            '&lt;'   => '<',
            '&gt;'   => '>'
        ]);
    }

    # OpenOffice Text (ODT)
    if ($extension == "odt" || $extension == "ods" || $extension == "odp") {
        # ODT files are zip files and the content is in content.xml.
        # We extract this then remove tags.
        $cmd = "unzip -p %%PATH%% \"content.xml\"";
        $text = run_command($cmd, false, ['%%PATH%%' => new CommandPlaceholderArg($path, 'is_valid_rs_path')]);

        # Remove tags, but add newlines as appropriate (without this, separate text blocks are joined together with no spaces).
        $text = str_replace("<", "\n<", $text);
        $text = trim(strip_tags($text));
        while (strpos($text, "\n\n") !== false) {
            $text = str_replace("\n\n", "\n", $text);
        } # condense multiple line breaks

        # Replace XML encoded characters with the correct unencoded variants
        $text = strtr($text, [
            '&quot;' => '"',
            '&apos;' => "'",
            '&amp;'  => '&',
            '&lt;'   => '<',
            '&gt;'   => '>'
        ]);
    }

    # PDF extraction using pdftotext (part of the XPDF project)
    if (($extension == "pdf" || $extension == "ai") && isset($pdftotext_path)) {
        $command = get_utility_path('pdftotext');
        if (!$command) {
            debug("ERROR: pdftotext executable not found at '$pdftotext_path'");
            return false;
        }
        $cmd = "{$command} -enc UTF-8 -nopgbrk %%PATH%% -";
        $text = run_command($cmd, false, ['%%PATH%%' => new CommandPlaceholderArg($path, 'is_valid_rs_path')]);
    }

    # HTML extraction
    if ($extension == "html" || $extension == "htm") {
        $text = strip_tags(file_get_contents($path));
    }

    # TXT extraction
    if ($extension == "txt") {
        $text = file_get_contents($path);
    }

    if ($extension == "zip") {
        # Zip files - map the field
        $cmd = "unzip -l %%PATH%%";
        $text = run_command($cmd, false, ['%%PATH%%' => new CommandPlaceholderArg($path, 'is_valid_rs_path')]);
    }

    hook("textextraction", "all", array($extension,$path));

    # Save the extracted text.
    if ($text != "") {
        $modified_text = hook("modifiedextractedtext", '', array($text));
        if (!empty($modified_text)) {
            $text = $modified_text;
        }

        # Final trim to tidy
        $text = trim($text);

        // Convert text
        if (!mb_check_encoding($text, "UTF-8")) {
            $curenc = mb_detect_encoding($text, mb_list_encodings(), true);
            if ($curenc == "ISO-8859-1") {
                // Safer to use 1252 here as most problematic files seem to have this
                $curenc = "Windows-1252";
            }
            $text = mb_convert_encoding($text, "UTF-8", $curenc);
        }

        # Save text
        update_field($ref, $extracted_text_field, $text);
    }
    return true;
}

/**
 * Get the orientation of an image file using ExifTool.
 *
 * This function retrieves the orientation metadata of an image file. If orientation information
 * is not available, it attempts to retrieve the rotation metadata. The function uses ExifTool
 * for the extraction and processes the result to return the orientation angle in degrees.
 *
 * @param string $file The path to the image file.
 * @return int The orientation angle in degrees (0, 90, 180, 270), or 0 if no orientation information is available.
 */
function get_image_orientation($file)
{
    $exiftool_fullpath = get_utility_path('exiftool');
    if (!$exiftool_fullpath) {
        return 0;
    }

    $cmd = $exiftool_fullpath . ' -s -s -s -orientation %%FILE%%';
    $orientation = run_command($cmd, false, ['%%FILE%%' => new CommandPlaceholderArg($file, 'is_valid_rs_path')]);
    $orientation = str_replace('Rotate', '', $orientation);

    if (strpos($orientation, 'CCW')) {
        $orientation = trim(str_replace('CCW', '', 360 - $orientation));
    } else {
        $orientation = trim(str_replace('CW', '', $orientation));
    }

    // Failed or no orientation available try rotation as well
    if (!is_numeric($orientation)) {
        $cmd = $exiftool_fullpath . " -s3 -rotation %%FILE%%";
        $orientation = run_command($cmd, false, ['%%FILE%%' => new CommandPlaceholderArg($file, 'is_valid_rs_path')]);
        if (!is_numeric($orientation)) {
            return 0;
        }
    }

    return $orientation;
}

/**
 * Automatically rotate an image based on its orientation metadata.
 *
 * This function uses ImageMagick to rotate an image to the correct orientation. If the image's
 * orientation is determined using ExifTool, the function will rotate the image accordingly. It
 * preserves custom metadata when applicable.
 *
 * @param string $src_image The path to the source image that needs to be rotated.
 * @param int|bool $ref The resource ID for the image; used to fetch orientation data if needed.
 *                      Pass `false` if no resource ID is available.
 * @return bool Returns `true` if the image was successfully rotated and saved, or `false` on failure.
 */
function AutoRotateImage($src_image, $ref = false)
{
    # use $ref to pass a resource ID in case orientation data needs to be taken
    # from a non-ingested image to properly rotate a preview image
    global $imagemagick_path, $camera_autorotation_ext;

    $src_image = safe_file_name($src_image);
    debug("AutoRotateImage(src_image = $src_image, ref = $ref)");

    if (!isset($imagemagick_path)) {
        return false;
        # for the moment, this only works for imagemagick
        # note that it would be theoretically possible to implement this
        # with a combination of exiftool and GD image rotation functions.
    }

    # Locate imagemagick.
    $convert_fullpath = get_utility_path("im-convert");
    if (!$convert_fullpath) {
        return false;
    }

    $exploded_src = explode('.', $src_image);
    $ext = $exploded_src[count($exploded_src) - 1];
    $triml = strlen($src_image) - (strlen($ext) + 1);
    $noext = substr($src_image, 0, $triml);

    if (count($camera_autorotation_ext) > 0 && (!in_array(strtolower($ext), $camera_autorotation_ext))) {
        # if the autorotation extensions are set, make sure it is allowed for this extension
        return false;
    }

    $exiftool_fullpath = get_utility_path("exiftool");
    $new_image = $noext . '-autorotated.' . $ext;

    if ($ref != false) {
        # use the original file to get the orientation info
        $extension = ps_value("SELECT file_extension value FROM resource WHERE ref=?", array("i",$ref), '');
        $file = get_resource_path($ref, true, "", false, $extension, -1, 1, false, "", -1);
        # get the orientation
        $orientation = get_image_orientation($file);
        if ($orientation != 0) {
            $cmd = $convert_fullpath . '%%SOURCE%% -rotate %%ORIENTATION%% %%DESTINATION%%';
            $params = [
                '%%SOURCE%%' => new CommandPlaceholderArg($src_image, 'is_valid_rs_path'),
                '%%ORIENTATION%%' => new CommandPlaceholderArg('+' . (int) $orientation, [CommandPlaceholderArg::class, 'alwaysValid']),
                '%%DESTINATION%%' => new CommandPlaceholderArg($new_image, 'is_valid_rs_path'),
            ];
            run_command($cmd, false, $params);
        }
    } else {
        $orientation = get_image_orientation($src_image);
        if ($orientation != 0) {
            $cmd = $convert_fullpath . ' %%SOURCE%% -auto-orient %%DESTINATION%%';
            $params = [
                '%%SOURCE%%' => new CommandPlaceholderArg($src_image, 'is_valid_rs_path'),
                '%%DESTINATION%%' => new CommandPlaceholderArg($new_image, 'is_valid_rs_path'),
            ];
            run_command($cmd, false, $params);
        }
    }

    if (!file_exists($new_image)) {
        return false;
    }

    if (!$ref) {
        # preserve custom metadata fields with exiftool
        # save the new orientation
        $cmd = $exiftool_fullpath .  ' -s -s -s -orientation -n %%SOURCE%%';
        $old_orientation = run_command($cmd, false, ['%%SOURCE%%' => new CommandPlaceholderArg($src_image, 'is_valid_rs_path')]);

        $exiftool_copy_command = $exiftool_fullpath . " -TagsFromFile %%SOURCE%% -all:all %%DESTINATION%%";
        $params = [
            '%%SOURCE%%' => new CommandPlaceholderArg($src_image, 'is_valid_rs_path'),
            '%%DESTINATION%%' => new CommandPlaceholderArg($new_image, 'is_valid_rs_path'),
        ];
        run_command($exiftool_copy_command, false, $params);

        # If orientation was empty there's no telling if rotation happened, so don't assume.
        # Also, don't go through this step if the old orientation was set to normal
        if ($old_orientation != '' && $old_orientation != 1) {
            $fix_orientation = $exiftool_fullpath . " -Orientation=1 -n %%DESTINATION%%";
            $params = [
                '%%DESTINATION%%' => new CommandPlaceholderArg($new_image, 'is_valid_rs_path'),
            ];
            run_command($fix_orientation, false, $params);
        }

        # we'll remove the exiftool created file copy (as a result of using -TagsFromFile)
        if (file_exists($new_image . '_original')) {
            unlink(safe_file_name($new_image) . '_original');
        }
    }

    unlink($src_image);
    rename($new_image, $src_image);
    return true;
}

/**
* Provides ability to extract the ICC profile for a specified resource/ alternative file
*
* @uses get_resource_path()
* @uses extract_icc()
*
* @param  integer  $ref          Resource ref
* @param  string   $extension    Extension of the file
* @param  integer  $alternative  Resource alternative ref. Default -1 as per get_resource_path() defintion
*
* @return boolean
*/
function extract_icc_profile($ref, $extension, $alternative = -1)
{
    // this is provided for compatibility. However, we are now going to rely on the caller to tell us the
    // path of the file. extract_icc() is where the real work will happen.
    $infile = get_resource_path($ref, true, '', true, $extension, true, 1, false, '', $alternative);

    if (extract_icc($infile, $ref)) {
        return true;
    }

    return false;
}

/**
 * Extracts the ICC profile from an image file using ImageMagick.
 *
 * This function attempts to extract the ICC profile from the specified image file and saves it as a
 * separate file with the `.icc` extension. It checks if the ICC profile already exists and will
 * remove it before extraction. The function is compatible with both Windows and non-Windows
 * environments.
 *
 * @param string $infile The path to the input image file from which to extract the ICC profile.
 * @param string $ref Optional. The resource ID associated with the image file. Used to determine the
 *                    output path for the extracted ICC profile.
 * @return bool Returns `true` if the ICC profile was successfully extracted and saved, or `false` on failure.
 */
function extract_icc($infile, $ref = '')
{
    global $config_windows, $syncdir;

    # Locate imagemagick, or fail this if it isn't installed
    $convert_fullpath = get_utility_path("im-convert");
    if (!$convert_fullpath) {
        return false;
    }

    if ($config_windows) {
        $stderrclause = '';
    } else {
        $stderrclause = '2>&1';
    }
    $path_parts = pathinfo($infile);
    if (!is_valid_rs_path($infile)) {
        return false;
    }

    if ($syncdir === "" || strpos($infile, $syncdir) === false) {
        $outfile = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.' . $path_parts['extension'] . '.icc';
    } else {
        $outfile = get_resource_path($ref, true, '', false, 'icc', -1);
    }

    if (file_exists($outfile)) {
        // extracted profile already existed. We'll remove it and start over
        unlink($outfile);
    }
    $cmd = $convert_fullpath . " %%INFILE%% %%OUTFILE%% " .  $stderrclause;

    // Detect the ":" prefix in $infile used for RAW images
    $infile = ((!$config_windows && preg_match('/^\w\w\w:/', $infile, $matches) === 1) ? "" : '') . $infile . "[0]";
    // Path to $infile is checked using is_valid_rs_path() above
    $cmdparams = [
        "%%INFILE%%" => new CommandPlaceholderArg($infile, [CommandPlaceholderArg::class, 'alwaysValid']),
        "%%OUTFILE%%" => new CommandPlaceholderArg($outfile, 'is_valid_rs_path')
    ];
    $cmdout = run_command($cmd, false, $cmdparams);
    if (preg_match("/no color profile is available/", $cmdout) || !file_exists($outfile) || filesize_unlimited($outfile) == 0) {
    // the icc profile extraction failed. So delete file.
        if (file_exists($outfile)) {
            unlink($outfile);
        }
        return false;
    }

    return file_exists($outfile);
}

/**
 * Retrieves the version of ImageMagick installed on the system.
 *
 * This function checks the version of ImageMagick by executing the `convert` command.
 * It can return the version information as either an array or a string, depending on the
 * value of the $array parameter.
 *
 * @param bool $array Optional. If set to true, the function returns an array containing the
 *                    major, minor, revision, and patch version numbers. If false, it returns
 *                    a version string in the format "major.minor.revision-patch".
 * @return mixed Returns an array with version numbers if $array is true, a version string if false,
 *               or false if ImageMagick is not installed or the version cannot be determined.
 */
function get_imagemagick_version($array = true)
{
    // return version number of ImageMagick, or false if it is not installed or cannot be determined.
    // will return an array of major/minor/version/patch if $array is true, otherwise just the version string

    # Locate imagemagick, or return false if it isn't installed
    $convert_fullpath = get_utility_path("im-convert");
    if (!$convert_fullpath) {
        return false;
    }

    $versionstring = run_command($convert_fullpath . " --version");
    // example:
    //          Version: ImageMagick 6.5.0-0 2011-02-18 Q16 http://www.imagemagick.org
        //          Copyright: Copyright (C) 1999-2009 ImageMagick Studio LLC

    if (preg_match("/^Version: +ImageMagick (\d+)\.(\d+)\.(\d+)-(\d+) /", $versionstring, $matches)) {
        $majorver = $matches[1];
        $minorver = $matches[2];
        $revision = $matches[3];
        $patch = $matches[4];
        if ($array) {
            return array($majorver,$minorver,$revision,$patch);
        } else {
            return "$majorver.$minorver.$revision-$patch";
        }
    } else {
        return false;
    }
}

/**
* Function used to get new width & height for an image in order to maintain
* aspect ratio while it will fit within a desired dimension.
*
* @param string  $image_path    The full path to the image (can be physical / URL)
* @param integer $target_width
* @param integer $target_height
* @param boolean $enlarge_image Specify whether images smaller than our target
*                               should be enlarged or not. Default is FALSE
*
* @return array New dimensions which can be used to resize the image and offset values client
*               code can use for consistent visual display
*/
function calculate_image_dimensions($image_path, $target_width, $target_height, $enlarge_image = false)
{
    $identify_fullpath = get_utility_path("im-identify");
    if ($identify_fullpath !== false) {
        list($source_width, $source_height) = getFileDimensions($identify_fullpath, '', $image_path, pathinfo($image_path, PATHINFO_EXTENSION));
    } else {
        $source_width = $source_height = 0;
    }

    $return = array(
        'portrait'  => false,
        'landscape' => false,
    );

    // Check orientation and calculate ratio
    if ($source_width > $source_height) {
        // Landscape
        $return['landscape'] = true;
        if (!$enlarge_image && $target_width > $source_width) {
            $ratio = 1;
        } else {
            $ratio = $target_width / $source_width;
        }

        $return['new_width']  = floor($source_width * $ratio);
        $return['new_height'] = floor($source_height * $ratio);
        // If result is larger and unable to enlarge then fix the height and compute the width
        if (!$enlarge_image && ($return['new_height'] > $target_height)) {
            $return['new_height'] = $target_height;
            $ratio = $target_height / $source_height;
            $return['new_width']  = floor($source_width * $ratio);
        }
    } else {
        // Portrait or square
        $return['portrait'] = true;
        if (!$enlarge_image && $target_height > $source_height) {
            $ratio = 1;
        } else {
            $ratio = $target_height / $source_height;
        }

        $return['new_width']  = floor($source_width * $ratio);
        $return['new_height'] = floor($source_height * $ratio);
        // If result is larger and unable to enlarge then fix the width and compute the height
        if (!$enlarge_image && ($return['new_width'] > $target_width)) {
            $return['new_width'] = $target_width;
            $ratio = $target_width / $source_width;
            $return['new_height']  = floor($source_height * $ratio);
        }
    }

    $return['x_offset']   = ceil(($target_height - $return['new_height']) / 2);
    $return['y_offset']   = ceil(($target_width - $return['new_width']) / 2);

    return $return;
}

/**
 * Upload file from a URL and add it to a resource.
 *
 * @param  int      $ref                  Resource ID
 * @param  bool     $no_exif=false        Don't extract exif data - true to disable extraction
 * @param  bool     $revert=false         Delete all data and re-extract embedded data
 * @param  bool     $autorotate=false     Autorotate images - alters embedded orientation data in uploaded file
 * @param  string   $url=""               File URL
 * @param  string   $key=""               Optional key to distinguish betweeen simultaneous requests from same user with same filename
 *
 * @return  bool
 *
 */
function upload_file_by_url(int $ref, bool $no_exif = false, bool $revert = false, bool $autorotate = false, string $url = "", string $key = "")
{
    debug("upload_file_by_url(ref = $ref, no_exif = $no_exif,revert = $revert, autorotate = $autorotate, url = $url)");

    # FStemplate support - do not allow samples from the template to be replaced
    if (resource_file_readonly($ref)) {
        return false;
    }

    if (!(checkperm('c') || checkperm('d') || hook('upload_file_permission_check_override'))) {
        return false;
    }

    global $userref;
    $resource_data = get_resource_data($ref);

    if (!is_array($resource_data)) {
        # No valid resource found.
        return false;
    }

    if (isset($resource_data["lock_user"]) && $resource_data["lock_user"] > 0 && $resource_data["lock_user"] != $userref) {
        return false;
    }

    $file_path = temp_local_download_remote_file($url, $key);
    if ($file_path === false) {
        return false;
    }

    $upload_result = upload_file($ref, $no_exif, $revert, $autorotate, $file_path);   # Process as a normal upload...
    remove_empty_temp_directory($file_path);

    return $upload_result;
}

/**
 * Delete all preview files for specified resource id or resource data array
 *
 * @param $resource array|integer Resource array or resource ID to delete preview files for
 *
 */
function delete_previews($resource, $alternative = -1)
{
    global $ffmpeg_supported_extensions, $watermark;

    // If a resource array has been passed we already have the extensions
    if (is_array($resource)) {
        $resource_data = $resource;
        $extension = $resource["file_extension"];
        $resource = $resource["ref"];
    } else {
        $resource_data = get_resource_data($resource, false);
        if (!$resource_data) {
            return;
        }
        $extension = $resource_data["file_extension"];
    }

    $fullsizejpgpath = get_resource_path($resource, true, "", false, "jpg", -1, 1, false, "", $alternative);
    // Delete the full size original if not a JPG resource
    if ($extension !== "" && strtolower((string) $extension) != "jpg" && file_exists($fullsizejpgpath) && $alternative == -1) {
        unlink($fullsizejpgpath);
    }

    $dirinfo = pathinfo($fullsizejpgpath);
    $resourcefolder = $dirinfo["dirname"];

    if (!in_array($extension, NON_PREVIEW_EXTENSIONS)) {
        $is_original_preview = is_original_preview($resource);
        $presizes = ps_array("SELECT id value FROM preview_size ORDER BY (height * width) DESC");
        $pagecount = get_page_count($resource_data, $alternative);
        foreach ($presizes as $presize) {
            for ($page = 1; $page <= $pagecount; $page++) {
                $previewpath = get_resource_path($resource, true, $presize, false, "jpg", -1, $page, false, "", $alternative);
                if (file_exists($previewpath)) {
                    // Preserve manually uploaded previews as alt files
                    if (!$is_original_preview && $page == 1 && in_array($presize, ['thm','col','pre','scr'])) {
                        $alt_file = add_alternative_file($resource, '', '', '', 'jpg', filesize_unlimited($previewpath));
                        $new_path = get_resource_path($resource, true, '', true, 'jpg', -1, 1, false, "", $alt_file);
                        copy($previewpath, $new_path);
                        $is_original_preview = true;
                    }
                    unlink($previewpath);
                }
                if ($watermark !== '') {
                    $wm_path = get_resource_path($resource, true, $presize, false, "jpg", -1, $page, true, "", $alternative);
                    if (file_exists($wm_path)) {
                        unlink($wm_path);
                    }
                }
            }
        }
    }

    if (in_array($extension, $ffmpeg_supported_extensions) || $extension == 'gif') {
        remove_video_previews($resource);
    }

    $delete_prefixes = [];
    $delete_prefixes[] = "original_copy_";
    $delete_prefixes[] = "resized_";
    $delete_prefixes[] = "tile_";
    $delete_prefixes[] = "tmp_";

    if (!file_exists($resourcefolder)) {
        return;
    }

    $allfiles = new DirectoryIterator($resourcefolder);
    foreach ($allfiles as $fileinfo) {
        if (!$fileinfo->isDot()) {
            $filename = $fileinfo->getFilename();
            foreach ($delete_prefixes as $delete_prefix) {
                if (substr($filename, strlen($resource), strlen($delete_prefix)) == $delete_prefix) {
                    unlink($resourcefolder . DIRECTORY_SEPARATOR . $filename);
                }
            }
        }
    }
}

/**
 * Get dimensions of image file
 *
 * @param  string $identify_fullpath        path to IM identify command
 * @param  string $prefix                   prefix - used by camera RAW files
 * @param  string $file                     path to file
 * @param  string $extension                file extension
 * @return array width and height of image, elements are null if not possible e.g. not an image file
 */
function getFileDimensions($identify_fullpath, $prefix, $file, $extension)
{

    # Stop empty $prefix from causing extraneous quotes being passed to IM,
    # this is ignored on Linux but fails on Windows
    if ($prefix) {
        $identcommand = $identify_fullpath . ' -format %wx%h %%PREFIX%%%%SOURCE%%[0]';
        $params = [
            '%%PREFIX%%' => new CommandPlaceholderArg(
                $prefix,
                fn($val): bool => preg_match('/^\w\w\w:$|^$/', $val, $matches)
            ),
            '%%SOURCE%%' => new CommandPlaceholderArg($file, 'is_valid_rs_path'),
        ];
    } else {
        $identcommand = $identify_fullpath . ' -format %wx%h %%SOURCE%%[0]';
        $params = [
            '%%SOURCE%%' => new CommandPlaceholderArg($file, 'is_valid_rs_path'),
        ];
    }

    # Get image's dimensions.
    $identoutput = run_command($identcommand, false, $params, 30);
    if (strtolower($extension) == "svg") {
        list($w, $h) = getSvgSize($file);
    } elseif (!empty($identoutput)) {
        $wh = explode("x", $identoutput);
        $w = $wh[0];
        $h = $wh[1];
    } else {
        // we really need dimensions here, so fallback to php's method
        if (
            is_readable($file)
            && filesize_unlimited($file) > 0
            && !in_array($extension, config_merge_non_image_types())
        ) {
            list($w,$h) = try_getimagesize($file) ?: [null,null];
        } else {
            $w = null;
            $h = null;
            debug("getFileDimensions: Unable to get image size for file: $file");
        }
    }
    return array($w, $h);
}

/**
* Get SVG size by reading the file and looking for dimensions at either width and height OR at Viewbox attribute(s)
*
* @param string $file_path Path to SVG file (should work with both a physical path and a URL)
*
* @return array where first element is width and second is height represented as strings
*/
function getSvgSize($file_path)
{
    if (!file_exists($file_path)) {
        trigger_error("getSvgSize(): file_path does not exist!");
    }

    $svg_size   = array(0, 0);
    $xml        = new SimpleXMLElement($file_path, LIBXML_PARSEHUGE, true);
    $attributes = $xml->attributes();

    // This information should be available in either width and height attributes and/ or viewBox
    if (isset($attributes->width) && isset($attributes->height)) {
        $svg_size[0] = (string) $attributes->width;
        $svg_size[1] = (string) $attributes->height;
        // Remove non numeric unit values if present
        $svg_size[0] = preg_replace("/[^.0-9]/", "", $svg_size[0]);
        $svg_size[1] = preg_replace("/[^.0-9]/", "", $svg_size[1]);
    } elseif (isset($attributes->viewBox) && trim($attributes->viewBox) !== '') {
        // Note: viewBox coordinates can be separated by either space and/ or a comma
        list($min_x, $min_y, $width, $height) = preg_split("/(\s|,)/", $attributes->viewBox);

        if (isset($width) && isset($height)) {
            $svg_size[0] = (string) $width;
            $svg_size[1] = (string) $height;
        }
    }

    return $svg_size;
}

/**
* Replace preview image with preview image from another resource/alternaive file
*
* @param int $ref               Resource to replace preview image for
* @param int $previewresource   Preview source resource ref
* @param int $previewalt        Preview source resource alternative ref
*
* @return boolean
*/
function replace_preview_from_resource($ref, $previewresource, $previewalt)
{
    # FStemplate support - do not allow samples from the template to be replaced
    if (resource_file_readonly($ref)) {
        return false;
    }
    $prepath = get_resource_path($ref, true, "tmp", true);
    foreach (array("","hpr") as $usesize) {
        $usepath = get_resource_path($ref, true, $usesize, true, 'jpg', true, 1, false, '', $previewalt);
        if (file_exists($usepath)) {
            break;
        }
    }

    debug("Copying " . $usepath . " to  " . $prepath);
    if (file_exists($usepath)) {
        $result = copy($usepath, $prepath);
        if ($result === true) {
            create_previews($ref, false, 'jpg', true);
            return true;
        }
    }
    return false;
}

/**
 * Get the source file to use for creating a preview
 *
 * @param  int $ref             Resource ID
 * @param  string $extension    Resource extension
 * @param  bool $previewonly    Create previews only
 * @param  bool $previewbased   Use existing preview as source
 * @param  int $alternative     Alternative file reference
 * @param  bool $ingested       Has file been ingested?
 *
 * @return string
 */
function get_preview_source_file($ref, $extension, $previewonly, $previewbased, $alternative, $ingested)
{
    global $autorotate_no_ingest;

    $foundpreviewsource = false;
    if ($previewbased || ($autorotate_no_ingest && !$ingested)) {
        $sourcesizes =  get_all_image_sizes(true);
        $sourcesizes =  array_reverse($sourcesizes, true);
        foreach ($sourcesizes as $sourcesize) {
            $file = get_resource_path($ref, true, $sourcesize["id"], false, "jpg", -1, 1, false, "", $alternative);
            if (file_exists($file)) {
                $foundpreviewsource = true;
                break;
            }
        }
    }

    if ($previewonly) {
        # We're generating based on a new preview (scr) image.
        $file = get_resource_path($ref, true, "tmp", false, "jpg");
    } elseif (!$foundpreviewsource) {
        //  Use original file
        $file = get_resource_path($ref, true, "", false, $extension, -1, 1, false, "", $alternative);
    }

    return $file;
}

/**
* Compute image tile regions based on a scale factor.
*
* IMPORTANT: Please note that the origin position (0,0) is the upper left-most pixel of the image
*
* @see https://iiif.io/api/image/2.1/#region
* @see https://iiif.io/api/image/2.1/#size
*
* @param int $sf Scale factor
* @param int $sw Source image width
* @param int $sh Source image height
*
* @return array Returns array of tile data:
* - id -> tile region identifier. Usually used as the size argument with {@see get_resource_path()}
* - x -> represents the number of pixels from the origin (ie. position zero) on the horizontal axis
* - y -> represents the number of pixels from the origin (ie. position zero) on the vertical axis
* - w -> width of the region in pixels
* - h -> height of the region in pixels
* - row -> represents the row grid position of the region (used with DZI compliant systems)
* - column -> represents the column grid position of the region (used with DZI compliant systems)
*/
function compute_tiles_at_scale_factor(int $sf, int $sw, int $sh)
{
    global $preview_tile_size, $preview_tile_scale_factors;

    $debug_id = uniqid();
    debug(sprintf('[fct=compute_tiles_at_scale_factor id=%s] Computing tiles for image with size: width = %s x height = %s and scale_factor = %s', $debug_id, $sw, $sh, $sf));

    if (!($sf > 0 && in_array($sf, $preview_tile_scale_factors) && $sw > 0 && $sh > 0)) {
        return [];
    }

    $tile_region = $preview_tile_size * $sf;
    debug(sprintf('[fct=compute_tiles_at_scale_factor id=%s] Tile region size = %s @ scale = %s', $debug_id, $tile_region, $sf));
    if ($tile_region > $sh && $tile_region > $sw) {
        debug(sprintf('[fct=compute_tiles_at_scale_factor id=%s] Scaled tile (@scale=%s) too large for source image', $debug_id, $sf));
        return [];
    }

    $tiles = [];
    /**
    * @var int $y Represents the number of pixels from the origin (ie. position zero) on the vertical axis
    */
    $y = 0;
    /**
    * @var int $x Represents the number of pixels from origin (ie. position zero) on the horizontal axis
    */
    $x = 0;
    $row = 0;
    $column = 0;
    while ($y < $sh) {
        $tileh = $tile_region;
        if (($y + $tile_region) > $sh) {
            debug(sprintf('[fct=compute_tiles_at_scale_factor id=%s] Tile taller than area, reducing height by %s', $debug_id, $y));
            $tileh = $sh - $y;
        }

        while ($x < $sw) {
            $tilew = $tile_region;
            if (($x + $tile_region) > $sw) {
                debug(sprintf('[fct=compute_tiles_at_scale_factor id=%s] Tile wider than area, reducing width by %s', $debug_id, $x));
                $tilew = $sw - $x;
            }

            $tile_id = sprintf('%s_%s_%s_%s_%s', $sf, $x, $y, $tilew, $tileh);
            $tile = [
                'id' => "tile_{$tile_id}",
                'x' => $x,
                'y' => $y,
                'w' => $tilew,
                'h' => $tileh,
                'row' => $row,
                'column' => $column,
            ];
            $tiles[] = $tile;
            debug(sprintf('[fct=compute_tiles_at_scale_factor id=%s] Computed tile: @scale = %s, x = %s, y = %s, tile_id = %s', $debug_id, $sf, $x, $y, $tile_id));

            // Advance to next X point on the grid
            $x = $x + $tile_region;
            ++$column;
        }

        // Advance to next Y point on the grid and reset X
        $x = 0;
        $y = $y + $tile_region;
        ++$row;
        $column = 0;
    }

    return $tiles;
}

/**
 * Perform the requested action on the original file to create a new file
 *
 * @param  string  $sourcepath  Path to source file
 * @param  string  $newpath     Path to new file
 * @param  array   $actions     Array of actions to perform
 *
 * @return boolean  Image created successfully?
 */
function transform_file(string $sourcepath, string $outputpath, array $actions)
{
    debug_function_call(__FUNCTION__, func_get_args());
    global $imagemagick_colorspace, $imagemagick_preserve_profiles, $cropperestricted;
    global $cropper_allow_scale_up;
    global $image_quality_presets, $preview_no_flatten_extensions;
    global $exiftool_no_process;

    $command = get_utility_path("im-convert");
    $identify_fullpath = get_utility_path("im-identify");
    if ($command === false || $identify_fullpath === false) {
        return false;
    }

    $cmd_args = [];
    $imversion = get_imagemagick_version(false); # Return version in string format

    // Set correct syntax for commands to remove alpha channel
    if (version_compare($imversion, "7", ">=")) {
        $alphaoff = " -alpha off";
    } else {
        $alphaoff = " +matte";
    }
    $sf_parts = pathinfo($sourcepath);
    $of_parts = pathinfo($outputpath);

    $profile = '';
    if (isset($actions['profile']) && is_array($actions['profile']) && !empty($actions['profile'])) {
        foreach ($actions['profile'] as $i => $profile_data) {
            if (is_bool($profile_data['strip']) && trim($profile_data['path']) !== '') {
                $profile_placeholder = "%profile{$i}";
                $cmd_args[$profile_placeholder] = new CommandPlaceholderArg($profile_data['path'], 'is_safe_basename');
                $profile .= sprintf(
                    ' %sprofile %s',
                    ($profile_data['strip'] ? '+' : '-'),
                    $profile_placeholder
                );
            }
        }
    } elseif (isset($actions['icc_profile']) && is_array($actions['icc_profile'])) {
        $profile .= $actions['icc_profile']['command'];
        $cmd_args = array_merge($cmd_args, $actions['icc_profile']['cmdparams']);
        unset($actions["srgb"]);
    } elseif (!isset($actions["rgb"]) && !$imagemagick_preserve_profiles) {
        $cmd_args['%imagemagick_colorspace'] = $imagemagick_colorspace;
        $profile = ' +profile icc -colorspace %imagemagick_colorspace'; # By default, strip the colour profiles ('+' is remove the profile, confusingly)
    }

    list($origwidth, $origheight) = getFileDimensions($identify_fullpath, '', $sourcepath, pathinfo($sourcepath, PATHINFO_EXTENSION));

    $keep_transparency = false;
    if (isset($actions['background']) && $actions['background'] !== '') {
        $cmd_args['%background'] = $actions['background'];
        $cmd_args['%sourcepath'] = new CommandPlaceholderArg($sourcepath, 'is_valid_rs_path');
        $command .= ' -background %background %sourcepath[0]';
    } elseif (strtoupper($of_parts["extension"]) == "PNG" || strtoupper($of_parts["extension"]) == "GIF") {
        $keep_transparency = true;
        $cmd_args['%sourcepath'] = new CommandPlaceholderArg($sourcepath, 'is_valid_rs_path');
        $command .= ' -background transparent %sourcepath[0]';
    } else {
        $cmd_args['%sourcepath'] = new CommandPlaceholderArg($sourcepath, 'is_valid_rs_path');
        $command .= ' %sourcepath[0]';
    }

    if (array_key_exists('transparent', $actions)) {
        $cmd_args['%transparent'] = new CommandPlaceholderArg($actions['transparent'], 'is_valid_imagemagick_color');
        $command .= ' -transparent%transparent';
    }

    if (array_key_exists('auto_orient', $actions)) {
        $command .= ' -auto-orient';
    }

    $quality = isset($actions["quality"]) ? $actions["quality"] : "";
    if ($quality != "" && in_array($quality, $image_quality_presets) && in_array(strtoupper($of_parts["extension"]), array("PNG","JPG"))) {
        $cmd_args['%quality'] = (int) $quality;
        $command .= ' -quality %quality%';
    }

    $colorspace1 = "";
    if (isset($actions["srgb"]) && $actions["srgb"] !== false) {
        if (version_compare($imversion, "6.7.5-5", ">=")) {
            $colorspace1 = " -colorspace sRGB ";
        } else {
            $colorspace1 = " -colorspace RGB ";
        }
    }

    if (isset($actions["resolution"]) && is_int_loose($actions["resolution"]) && $actions["resolution"] != 0) {
        $cmd_args['%resolution'] = $actions['resolution'];
        $command .= ' -units PixelsPerInch -density %resolution';
    }

    if (
        in_array(strtolower($sf_parts['extension']), $preview_no_flatten_extensions)
        ||
        (isset($actions["noflatten"]) && $actions["noflatten"] == "true")
    ) {
        $flatten = "";
    } else {
        $flatten = ' -flatten';
    }

    $command .= $colorspace1 . $flatten;

    if (isset($actions["gamma"]) && is_int_loose($actions["gamma"]) && $actions["gamma"] <> 50) {
        $cmd_args['%gamma'] = new CommandPlaceholderArg(round($actions['gamma'] / 50, 2), 'is_numeric');
        $command .= ' -gamma %gamma';
    }

    if ($sf_parts['extension'] == "psd" && !$keep_transparency) {
        $command .= $alphaoff;
    }

    // Transform actions need to be performed in order the user performed them since they are not commutative
    $tfparams = "";
    // Set var to keep track of rotations so we know if image has swapped height/width.
    // This is needed to calculate the crop co-ordinates
    $swaphw = 0;
    foreach ($actions["tfactions"] as $tfaction) {
        switch ($tfaction) {
            case "r90":
                $tfparams .= " -rotate 90 ";
                $swaphw += 1;
                break;

            case "r180":
                $tfparams .= " -rotate 180 ";
                break;

            case "r270":
                $tfparams .= " -rotate 270 ";
                $swaphw += 1;
                break;

            case "x":
                $tfparams .= " -flop ";
                break;

            case "y":
                $tfparams .= " -flip ";
                break;

            default:
                // No transform action
                break;
        }
    }

    $command .= $tfparams;

    if (isset($actions["crop"]) && $actions["crop"] && !$cropperestricted) {
        // Need to mathematically convert to the original size
        $xfactor = $swaphw % 2 == 0 ? $origwidth / $actions["cropwidth"] : $origheight / $actions["cropwidth"];
        $yfactor = $swaphw % 2 == 0 ? $origheight / $actions["cropheight"] : $origwidth / $actions["cropheight"];

        debug(" xfactor:  " . $xfactor);
        debug(" yfactor:  " . $yfactor);
        $finalxcoord = round(($actions["xcoord"] * $xfactor), 0);
        $finalycoord = round(($actions["ycoord"] * $yfactor), 0);

        // Ensure that new ratio of crop matches that of the specified size or we may end up missing the target size
        // If landscape crop, set the width first, then base the height on that
        if ((isset($actions["new_width"]) && (int) $actions["new_width"] > 0) && (isset($actions["new_height"]) && (int) $actions["new_height"] > 0)) {
            // Target dimensions passed, so use those to calculate the final width/height 
            $desiredratio = (int) $actions["new_width"] / (int) $actions["new_height"];
        } else {
            $desiredratio = (int) $actions["width"] / (int) $actions["height"];
        }

        if ($desiredratio > 1) {
            $finalwidth  = round($actions["width"] * $xfactor, 0);
            $finalheight = round($finalwidth / $desiredratio, 0);
        } else {
            $finalheight = round($actions["height"] * $yfactor, 0);
            $finalwidth = round($finalheight *  $desiredratio, 0);
        }

        debug(sprintf('[transform_file] $actions["width"] = %s', $actions["width"]));
        debug(sprintf('[transform_file] $actions["height"] = %s', $actions["height"]));
        debug(sprintf('[transform_file] $finalxcoord = %s', $finalxcoord));
        debug(sprintf('[transform_file] $finalycoord = %s', $finalycoord));
        debug(sprintf('[transform_file] $actions["cropwidth"] = %s', $actions["cropwidth"]));
        debug(sprintf('[transform_file] $actions["cropheight"] = %s', $actions["cropheight"]));
        debug(sprintf('[transform_file] $origwidth = %s', $origwidth));
        debug(sprintf('[transform_file] $origheight = %s', $origheight));
        debug(sprintf('[transform_file] $actions["new_width"] = %s', $actions["new_width"]));
        debug(sprintf('[transform_file] $actions["new_height"] = %s', $actions["new_height"]));
        debug(sprintf('[transform_file] $finalwidth = %s', $finalwidth));
        debug(sprintf('[transform_file] $finalheight = %s', $finalheight));

        $cmd_args['%finalwidth'] = $finalwidth;
        $cmd_args['%finalheight'] = $finalheight;
        $cmd_args['%finalxcoord'] = $finalxcoord;
        $cmd_args['%finalycoord'] = $finalycoord;
        $command .= ' -crop %finalwidthx%finalheight+%finalxcoord+%finalycoord';
    }

    if (isset($actions["repage"]) && $actions["repage"]) {
        $command .= " +repage"; // force imagemagick to repage image to fix canvas and offset info
    }

    // Did the user request a width? If so, tack that on
    if ((isset($actions["new_width"]) && (int)$actions["new_width"] > 0) || (isset($actions["new_height"]) && (int)$actions["new_height"] > 0)) {
        $scalewidth = is_numeric($actions["new_width"]) ? true : false;
        $scaleheight = is_numeric($actions["new_height"]) ? true : false;

        if (!$cropper_allow_scale_up && (!isset($actions["preview"]) || $actions["preview"] === false)) {
            // sanity checks
            // don't allow a specified size larger than the natural crop size
            // or the original size of the image
            if (isset($actions["crop"]) && $actions["crop"]) {
                $checkwidth  = $actions["new_width"];
                $checkheight = $actions["new_height"];
            } else {
                $checkwidth     = $actions["origwidth"];
                $checkheight    = $actions["origheight"];
            }

            if (is_numeric($actions["new_width"]) && $actions["new_width"] > $checkwidth) {
                // if the requested width is greater than the original or natural size, ignore
                $actions["new_width"] = '';
                $scalewidth = false;
            }

            if (is_numeric($actions["new_height"]) && $actions["new_height"] > $checkheight) {
                // if the requested height is greater than original or natural size, ignore
                $actions["new_height"] = '';
                $scaleheight = false;
            }
        }

        if ($scalewidth || $scaleheight) {
            // add scaling command
            // note that there is a minor issue here: may be rounding
            // errors when the crop box is scaled up from preview size to original   size
            // if so and the resulting match doesn't quite match the required width and
            // height, there may be a tiny amount of distortion introduced as the
            // program scales up or down by a few pixels. This should be
            // imperceptible, but perhaps worth revisiting at some point.
            $cmd_args['%new_width'] = (int) $actions['new_width'];
            $command .= ' -scale %new_width';
            if ($actions["new_height"] > 0) {
                $cmd_args['%new_height'] = (int) $actions['new_height'];
                $command .= 'x%new_height';

                // Transform preview generation relies on IM to try to keep aspect ratio, so do not bypass it
                if (!array_key_exists('preview', $actions) || $actions['preview'] !== true) {
                    $command .= "!";
                }
            }
        }
    }

    if (
        isset($actions['resize']['width'], $actions['resize']['height'])
        && is_numeric($actions['resize']['width']) && is_numeric($actions['resize']['height'])
        && $actions['resize']['width'] > 0
    ) {
        # Apply resize ('>' means: never enlarge)
        $command .= ' -resize %resize_dimensions';
        $resize_width = (int) $actions['resize']['width'];
        $resize_height = (int) $actions['resize']['height'];
        $cmd_args['%resize_dimensions'] = new CommandPlaceholderArg(
            $resize_width . ($resize_height > 0 ? "x{$resize_height}" : '') . '>',
            [CommandPlaceholderArg::class, 'alwaysValid']
        );
    }

    $cmd_args['%outputpath'] = new CommandPlaceholderArg($outputpath, 'is_valid_rs_path');
    $command .= $profile . ' %outputpath';

    run_command($command, false, $cmd_args);

    if (file_exists($outputpath)) {
        // See if we have got exiftool
        $exiftool_fullpath = get_utility_path("exiftool");
        if (($exiftool_fullpath != false) && !in_array($of_parts["extension"], $exiftool_no_process)) {
            $exifcommand = $exiftool_fullpath . ' -m -overwrite_original -E -Orientation#=1 ';
            $exifargs = ['%outputfile%' => new CommandPlaceholderArg($outputpath, 'is_valid_rs_path')];

            if (isset($actions["resolution"]) && $actions["resolution"] != "") {
                // Target the Photoshop specific PPI data
                $exifcommand .= " -Photoshop:XResolution=%resolution%";
                $exifcommand .= " -Photoshop:YResolution=%resolution%";
                $exifargs["%resolution%"]  = $actions["resolution"];
            }

            $exifcommand .= " %outputfile%";
            run_command($exifcommand, false, $exifargs);
        }
    }

    return file_exists($outputpath);
}

/**
* For a given resource reference, remove the video pre size and all snapshots.
*
* @param  int   $resource   Resource to remove video previews.
*
* @return void
*/
function remove_video_previews(int $resource): void
{
    global $ffmpeg_preview_extension;

    # Remove pre size video
    $pre_video_size = get_resource_path($resource, true, "pre", false, $ffmpeg_preview_extension, -1, 1, false, "", -1);
    if (file_exists($pre_video_size)) {
        unlink($pre_video_size);
    }

    # Remove snapshots
    $directory = dirname($pre_video_size);
    foreach (glob($directory . "/*") as $filetoremove) {
        if (strpos($filetoremove, 'snapshot_') !== false) {
            unlink($filetoremove);
        }
    }
}

/**
 * Create preview sizes via create_previews() and/or generate jobs as necessary
 *
 * @param int $ref                  Resource ID
 * @param string $extension         File extension
 *
 * @return int                      0 Preview creation failed
 *                                  1 All previews have been created
 *                                  2 Minimal previews created and offline jobs/scripts are required
 *                                   to create the full set of previews/image/video $alternatives
 *
 */
function start_previews(int $ref, string $extension = ""): int
{
    global $lang, $minimal_previews_sizes;

    $minimal_previews = false;
    $resource_data = get_resource_data($ref, false);
    delete_previews($resource_data);
    if (trim($extension) == "") {
        $extension = $resource_data["file_extension"];
    }
    $ingested = empty($resource_data['file_path']);
    if ($GLOBALS["offline_job_queue"]) {
        $create_previews_job_data = [
            'resource' => $ref,
            'thumbonly' => false,
            'extension' => $resource_data["file_extension"],
            'previewonly' => false,
            'previewbased' => false,
            'alternative' => -1,
            'ignoremaxsize' => true,
        ];
        $create_previews_job_failure_text = str_replace('%RESOURCE', $ref, $lang['jq_create_previews_failure_text']);
        job_queue_add('create_previews', $create_previews_job_data, '', '', '', $create_previews_job_failure_text);
        $minimal_previews = true;
    } elseif (
        $GLOBALS["enable_thumbnail_creation_on_upload"] === false
        || (int) $resource_data["file_size"] / (1024 * 1024) >= (int) ($GLOBALS["preview_generate_max_file_size"] ?? PHP_INT_MAX)
    ) {
        // These configs require use of a cron task to run batch/create_previews.php
        ps_query("UPDATE resource SET has_image = ? WHERE ref= ?", ['i',RESOURCE_PREVIEWS_NONE,'i', $ref]);
        $minimal_previews = true;
    }

    if ($minimal_previews) {
        if (!in_array($extension, $GLOBALS["minimal_preview_creation_exclude_extensions"])) {
            // Extension hasn't been excluded from immediate preview creation
            $success = create_previews(
                $ref, 
                false, 
                in_array($extension, NON_PREVIEW_EXTENSIONS) ? 'jpg' : $extension, 
                false, 
                in_array($extension, NON_PREVIEW_EXTENSIONS), 
                -1, 
                true, 
                $ingested, 
                false, 
                $minimal_previews_sizes
            );
            return $success ? 2 : 0;
        }
        return 2;
    }
    // No offline preview creation - create the full set of previews immediately
    ps_query("UPDATE `resource` SET has_image = 0, preview_attempts = 0 WHERE ref = ?;", ['i', $ref]);
    $success = create_previews(
        $ref, 
        false, 
        in_array($resource_data["file_extension"], NON_PREVIEW_EXTENSIONS) ? 'jpg' : $resource_data["file_extension"],  
        false, 
        in_array($resource_data["file_extension"], NON_PREVIEW_EXTENSIONS), 
        -1, 
        false, 
        $ingested);
    return $success ? 1 : 0;
}

/**
 * Get an array of preview size IDs to generate
 *
 * @param string $extension         File extension ('hpr' is only required for non-JPG images)
 * @param array $dimensions         Image source dimensions in format [width,height]
 * @param bool $thumbonly           Generate 'thm' and 'col' only
 * @param bool $previewonly         Generate 'scr', 'pre', 'thm' and 'col' only
 * @param array $onlysizes          Array of requested size IDs to generate
 *
 * @return array|bool   Array of size IDs or false on failure.
 *
 */
function get_sizes_to_generate(
    string $extension,
    array $dimensions,
    bool $thumbonly = false,
    bool $previewonly = false,
    array $onlysizes = []
) {
    $sw = (int) ($dimensions[0] ?? 0);
    $sh = (int) ($dimensions[1] ?? 0);

    if ($sw == 0 || $sh == 0) {
        return false;
    }
    $getsizes = [];
    $params = [];
    if ($thumbonly) {
        $onlysizes = ['thm','col'];
    } elseif ($previewonly) {
        $onlysizes = ['thm','col','pre','scr'];
    }

    // Construct query
    if (count($onlysizes) > 0) {
        $onlysizes = array_filter($onlysizes, function ($v) {
            return ctype_lower($v) || ($GLOBALS["iiif_custom_sizes"] && substr($v, 0, 8) == "resized_");
        });
        $validsizecount = count($onlysizes);
        if ($validsizecount === 0) {
            return false;
        }
        $getsizes = array_fill(0, $validsizecount, "?");
        $params = ps_param_fill($onlysizes, 's');
    }

    $condition = count($getsizes) > 0 ? " WHERE id IN (" . implode(",", $getsizes) . ")" : "";
    $ps = ps_query(
        "SELECT " . columns_in("preview_size") . " FROM preview_size " . $condition . " ORDER BY width DESC",
        $params
    );

    if (
        (count($onlysizes) === 0 || in_array("tiles", $onlysizes))
        && $GLOBALS["preview_tiles"]
        && $GLOBALS["preview_tiles_create_auto"]
        && !in_array($extension, config_merge_non_image_types())
    ) {
        // Ensure that scales are in order
        natsort($GLOBALS["preview_tile_scale_factors"]);

        debug("create_previews - adding tiles to generate list: source width: "
            . $sw . " source height: " . $sh);
        foreach ($GLOBALS["preview_tile_scale_factors"] as $scale) {
            foreach (compute_tiles_at_scale_factor($scale, $sw, $sh) as $tile) {
                $tile['width'] = floor($tile['w'] / $scale);
                $tile['height'] = floor($tile['h'] / $scale);
                $tile['type'] = 'tile';
                $tile['internal'] = 1;
                $tile['allow_preview'] = 0;
                $ps[] = $tile;
            }
        }
    }

    if (count($onlysizes) == 1 && substr($onlysizes[0], 0, 8) == "resized_") {
        $o = count($ps);
        $size_req = explode("_", substr($onlysizes[0], 8));
        $customx = $size_req[0];
        $customy = $size_req[1];

        debug("create_previews - creating custom size width: " . $customx . " height: " . $customy);
        $ps[$o]['id'] = $onlysizes[0];
        $ps[$o]['width'] = $customx;
        $ps[$o]["height"] = $customy;
        $ps[$o]['type'] = 'resize';
    }
    return $ps;
}

/**
 * Generates alternative image files for a specified resource.
 *
 * This function checks the existing alternative files and creates new ones based on the specified
 * parameters. It can also force the creation of alternatives even if they already exist. It uses
 * ImageMagick to process the images according to the specified configurations.
 *
 * @param int $ref The resource ID for which alternatives are being generated.
 * @param array $params An associative array containing the following keys:
 *                      - file: The file path of the original image.
 *                      - extension: The extension of the original image (default is "jpg").
 *                      - previewonly: A boolean indicating if only previews should be created.
 *                      - previewbased: A boolean indicating if the alternatives are based on previews.
 *                      - ingested: A boolean indicating if the resource has been ingested (default is true).
 * @param bool $force Optional. If set to true, will force the generation of alternative files even if they exist.
 * @return bool Returns true if alternatives were generated successfully, false otherwise.
 */
function create_image_alternatives(int $ref, array $params, $force = false)
{
    global $lang;
    // Handle alternative image file generation
    $convert_fullpath = get_utility_path("im-convert");
    if ($convert_fullpath === false) {
        return false;
    }
    $imversion = get_imagemagick_version();

    // Set correct syntax for commands to remove alpha channel
    $alphaoff = "-alpha off";
    if ($imversion[0] < 7) {
        $alphaoff = "+matte";
    }
    // Check parameters
    $file = (string) ($params["file"] ?? "");
    $extension = (string) ($params["extension"] ?? "jpg");
    $previewonly = (bool) ($params["previewonly"] ?? false);
    $previewbased = (bool) ($params["previewbased"] ?? false);
    $ingested = (bool) ($params["ingested"] ?? true);

    if ($file === "") {
        $file = get_preview_source_file($ref, $extension, $previewonly, $previewbased, -1, $ingested);
    }

    # Check against the resource extension as extension might refer to a jpg preview file
    $resource_extension = ps_value('SELECT file_extension value FROM resource WHERE ref=?', array("i",$ref), '');
    $arrexisting = get_alternative_files($ref);
    for ($n = 0; $n < count($GLOBALS["image_alternatives"]); $n++) {
        $alternate_config = $GLOBALS["image_alternatives"][$n];
        debug("Considering image alternative. Name: ''" . $alternate_config['name'] . "', description: '" . ($alternate_config['description'] ?? "") . "'");
        $exts = array_filter(explode(',', trim($alternate_config['source_extensions'])));
        if (!in_array($resource_extension, $exts)) {
            // Not required for this resource extension
            continue;
        }

        foreach ($arrexisting as $existing) {
            if (
                $alternate_config['name'] == $existing["name"]
                && (!isset($alternate_config['description']) || $alternate_config['description'] == $existing["description"])
                && $alternate_config['target_extension'] == $existing["file_extension"]
            ) {
                if ($force) {
                    debug("Deleting existing image alternative for resource #" . $ref . ", alternative id #" . $existing['ref']);
                    delete_alternative_file($ref, $existing["ref"]);
                } else {
                    debug("Skipping creation of image alternative " . $alternate_config['name'] . " for resource #" . $ref . " as already exists. Alternative id #" . $existing['ref']);
                    continue 2;
                }
            }
        }

        if (PHP_SAPI !== "cli") {
            set_processing_message(str_replace(["[resource]","[name]"], [$ref,$alternate_config["name"]], $lang["processing_alternative_image"]));
        }
        // Create the alternative file.
        $aref  = add_alternative_file($ref, $alternate_config['name'], $alternate_config['description'] ?? "");
        $apath = get_resource_path($ref, true, '', true, $alternate_config['target_extension'], -1, 1, false, '', $aref);

        $source_profile = '';
        if (isset($alternate_config['icc']) && $alternate_config['icc'] === true) {
            $iccpath = get_resource_path($ref, true, '', false, 'icc');

            global $icc_extraction, $ffmpeg_supported_extensions;

            if (!file_exists($iccpath) && $extension != 'pdf' && !in_array($extension, $ffmpeg_supported_extensions)) {
                // extracted profile doesn't exist. Try extracting.
                extract_icc_profile($ref, $extension);
            }

            if (file_exists($iccpath) && can_apply_icc_profile(get_resource_path($ref, true, "", false, $extension, -1))) {
                $source_profile = ' -strip -profile ' . $iccpath;
            }
        }

        $source_params = ' ';
        if (isset($alternate_config['source_params']) && '' !== trim($alternate_config['source_params'])) {
            $source_params = ' ' . $alternate_config['source_params'] . ' ';
        }

        # Process the image
        if (
            $imversion[0] > 5
            || ($imversion[0] == 5 && $imversion[1] > 5)
            || ($imversion[0] == 5 && $imversion[1] == 5 && $imversion[2] > 7 )
        ) {
            // Use the new imagemagick command syntax (file then parameters)
            // Note that $source_params can't be set as a run_command() parameter or the whole thing will be escaped
            $command = $convert_fullpath . $source_params . " %%FILE%% ";
            $command .= ($extension == 'psd') ? '[0] ' .
                (!in_array(strtolower($extension), $GLOBALS["preview_keep_alpha_extensions"]) ? $alphaoff : "")
                 : '';
            $command = ($extension == 'pdf') ? rtrim($command) . '[0] ' : $command;
            $command .= $source_profile . ' ' . $alternate_config['params'] . " %%APATH%%";
        } else {
            // Use the old imagemagick command syntax (parameters then file)
            $command = $convert_fullpath . $source_profile . ' ' . $alternate_config['params'] . ' %%FILE%% %%APATH%%';
        }
        $cmdparams = [
            '%%FILE%%' => new CommandPlaceholderArg($file, 'is_valid_rs_path'),
            '%%APATH%%' => new CommandPlaceholderArg($apath, 'is_valid_rs_path'),
        ];
        $output = run_command($command, false, $cmdparams);

        if (file_exists($apath)) {
            # Update the database with the new file details.
            $file_size = filesize_unlimited($apath);
            ps_query(
                "UPDATE resource_alt_files SET file_name = ?, file_extension = ?, file_size = ?, creation_date=now() WHERE ref = ?",
                [
                's', $alternate_config['filename'] . '.' . $alternate_config['target_extension'],
                's', $alternate_config['target_extension'],
                'i', $file_size,
                'i', $aref
                ]
            );
        }
    }
    return true;
}

/**
 * Input validation helper function for ImageMagick's color values (e.g. blue, #ddddff and rgb(255,255,255))
 *
 * @see https://imagemagick.org/script/command-line-options.php#transparent
 * @see https://imagemagick.org/script/command-line-options.php#fill
 */
function is_valid_imagemagick_color(string $val): bool
{
    return preg_match('/^#?[a-zA-Z]+$|^rgb\(\d{1,3},\d{1,3},\d{1,3}\)$/', $val);
}

/**
 * With $icc_extraction enabled, determine whether we should apply the icc profile that has been found.
 * Note: This doesn't prevent ICC profile extraction.
 *
 * @param   string   $original_file_path    Path to the original file from which the ICC profile was extracted.
 *
 * @return bool
 */
function can_apply_icc_profile(string $original_file_path): bool
{
    global $icc_extraction, $excluded_icc_profiles;
    if (!$icc_extraction) {
        return false;
    }

    if (count($excluded_icc_profiles) > 0) {
        $identify_fullpath = get_utility_path("im-identify");
        if (!$identify_fullpath) {
            return false;
        }

        $cmdparams["%%INFILE%%"] = new CommandPlaceholderArg($original_file_path, 'is_valid_rs_path');
        $profile_found = run_command($identify_fullpath . ' -format "%[profile:icc]" %%INFILE%%', false, $cmdparams);

        if (in_array($profile_found, $excluded_icc_profiles)) {
            return false; // ICC profile found will not be applied.
        }
    }

    return true;
}

/**
 * Check if system is using ICC profiles and prepare ImageMagick command for use by transform_file() to enable
 * system wide configuration for $icc_extraction to be applied when transforming a file, such as by format chooser or image tools.
 *
 * @param  int      $ref                  Resource id.
 * @param  string   $original_file_path   Path to the original file for the resource.
 */
function transform_apply_icc_profile(int $ref, string $original_file_path): array
{
    if (!can_apply_icc_profile($original_file_path)) {
        return array();
    }

    $iccpath = get_resource_path($ref, true, '', false, 'icc', -1, 1, false, "", -1);
    if (!file_exists($iccpath)) {
        return array();
    }

    global $icc_preview_options, $icc_preview_profile_embed, $icc_preview_profile, $imagemagick_preserve_profiles;

    if ($imagemagick_preserve_profiles) {
        // Don't touch the ICC profile
        return array();
    }

    $targetprofile = __DIR__ . '/../iccprofiles/' . $icc_preview_profile;

    $transform_actions['icc_profile']['command'] = " -strip -profile %%ICCPATH%% " . $icc_preview_options . " -profile %%TARGETPROFILE%% " . ($icc_preview_profile_embed ? " " : " -strip ");
    $transform_actions['icc_profile']['cmdparams']["%%ICCPATH%%"] = new CommandPlaceholderArg($iccpath, 'is_valid_rs_path');
    $transform_actions['icc_profile']['cmdparams']["%%TARGETPROFILE%%"] = new CommandPlaceholderArg($targetprofile, 'file_exists');

    return $transform_actions;
}

/**
 * Generate previews for a resource using ImageMagick.
 *
 * This function creates image previews for a resource by leveraging ImageMagick to handle
 * resizing and generating thumbnails, medium, large, and custom-sized previews.
 * It provides flexible options to control the generation process, such as limiting previews to specific sizes,
 * applying watermarks, handling color profiles, and managing alternative files.
 * The function includes extensive support for non-standard image formats and configurations.
 *
 * @param int $ref The resource ID for which previews are generated.
 * @param bool $thumbonly (optional) If `true`, only the thumbnail will be generated. Default is `false`.
 * @param string $extension (optional) The file extension for the resource. Default is "jpg".
 * @param bool $previewonly (optional) If `true`, only preview images will be generated. Default is `false`.
 * @param bool $previewbased (optional) If `true`, previews are generated based on existing previews rather than original files. Default is `false`.
 * @param int $alternative (optional) Specifies an alternative file ID to generate previews for, if any. Default is `-1`.
 * @param bool $ingested (optional) If `true`, marks the resource as already ingested into the system. Default is `false`.
 * @param array $onlysizes (optional) Specifies an array of preview sizes to generate. If empty, all sizes are generated.
 * @return bool Returns `true` if previews were generated successfully; `false` otherwise.
 */
function create_previews_using_im(
    int $ref,
    bool $thumbonly = false,
    string $extension = "jpg",
    bool $previewonly = false,
    bool $previewbased = false,
    int $alternative = -1,
    bool $ingested = false,
    array $onlysizes = []
): bool {
    global $keep_for_hpr,$imagemagick_path,$imagemagick_preserve_profiles,$imagemagick_quality,$default_icc_file;
    global $autorotate_no_ingest,$always_make_previews,$previews_allow_enlarge,$alternative_file_previews;
    global $imagemagick_mpr, $imagemagick_mpr_preserve_profiles, $imagemagick_mpr_preserve_metadata_profiles, $config_windows;
    global $camera_autorotation_ext, $watermark, $syncdir, $preview_no_flatten_extensions, $preview_keep_alpha_extensions;
    global $watermark_single_image, $lang, $icc_extraction, $icc_preview_profile, $icc_preview_options, $ffmpeg_supported_extensions;

    debug_function_call(__FUNCTION__, func_get_args());
    if (!is_numeric($ref)) {
        debug("Parameter 'ref' must be numeric!");
        return false;
    }

    if (isset($imagemagick_path)) {
        $file = get_preview_source_file($ref, $extension, $previewonly, $previewbased, $alternative, $ingested);
        if ($previewonly) {
            // Original file is the new file if generating previews from a new preview source file.
            $origfile = $file;
        } else {
            $origfile = get_resource_path($ref, true, "", false, $extension, -1, 1, false, "", $alternative);
        }

        // Function to check if $onlysizes is set, and if so don't remove other preview sizes.
        $f_unlink_size = fn($check_size) => count($onlysizes) == 0 || (count($onlysizes) > 0 && in_array($check_size, $onlysizes));

        $removesizes = ["hpr","lpr","scr"];
        $allpresizes = [];
        $filestounlink = [];
        foreach ($removesizes as $removesize) {
            $allpresizes[$removesize] = get_resource_path($ref, true, $removesize, false, "jpg", -1, 1, false, "", $alternative);
            if (!$previewbased && $f_unlink_size($removesize)) {
                $filestounlink[] = $allpresizes[$removesize];
                // Add watermarked path
                $filestounlink[] = get_resource_path($ref, true, $removesize, false, "jpg", -1, 1, true, "", $alternative);
            }
        }
        foreach ($filestounlink as $filetounlink) {
            if (file_exists($filetounlink)) {
                try_unlink($filetounlink);
            }
        }

        $prefix = '';
        // Camera RAW images need prefix
        if (preg_match('/^(dng|nef|x3f|cr2|crw|mrw|orf|raf|dcr)$/i', $extension, $rawext)) {
            $prefix = $rawext[0] . ':';
        } elseif (!$config_windows && preg_match('/^\w\w\w:/', $file) === 1) {
            $prefix = $extension . ':';
        }

        // Locate imagemagick.
        $identify_fullpath = get_utility_path("im-identify");
        $convert_fullpath = get_utility_path("im-convert");
        if (!$identify_fullpath) {
            debug("ERROR: Could not find ImageMagick 'identify' utility at location '$imagemagick_path'.");
            return false;
        }
        if (!$convert_fullpath) {
            debug("ERROR: Could not find ImageMagick 'convert' utility at location '$imagemagick_path'.");
            return false;
        }
        $imversion = get_imagemagick_version();
        // Set correct syntax for commands to remove alpha channel
        if ($imversion[0] >= 7) {
            $alphaoff = " -alpha off";
        } else {
            $alphaoff = " +matte";
        }

        // Check source file
        list($sw, $sh) = getFileDimensions($identify_fullpath, $prefix, $file, $extension);
        if (is_null($sw) || is_null($sh)) {
            // This is not a valid image
            return false;
        }

        if ($extension == "svg") {
            $o_width  = $sw;
            $o_height = $sh;
        }

        $generateall = !($thumbonly || $previewonly || (count($onlysizes) > 0));
        $ps = get_sizes_to_generate($extension, [$sw,$sh], $thumbonly, $previewonly, $onlysizes);
        if (!$ps) {
            return false;
        }
        if (count($ps) == 1 && $watermark == "") {
            // No point using MPR if generating a single file
            $imagemagick_mpr = false;
        }

        if ($imagemagick_mpr) {
            // need to check that we're using IM and not GM
            $version = run_command($convert_fullpath . " -version");
            if (strpos($version, "GraphicsMagick") !== false) {
                $imagemagick_mpr = false;
            } else {
                $mpr_parts = [];

                $mpr_metadata_profiles = '';
                if (!empty($imagemagick_mpr_preserve_metadata_profiles)) {
                    $mpr_metadata_profiles = "!" . implode(",!", $imagemagick_mpr_preserve_metadata_profiles) . ",*";
                }
            }
        }

        // Set up common command parameters
        $cmdparams["%%IMAGEMAGICK_COLORSPACE%%"] = new CommandPlaceholderArg($GLOBALS["imagemagick_colorspace"], null);
        $cmdparams["%%QUALITY%%"] = new CommandPlaceholderArg($imagemagick_quality, 'is_int_loose');

        if (trim($watermark ?? "") !== "") {
            $cmdparams["%%WMFILE%%"] = new CommandPlaceholderArg($watermark, "is_safe_basename");
            // Get watermark dimensions
            list($wmw, $wmh) = getFileDimensions($identify_fullpath, '', $watermark, 'jpeg');


            // Set standard params for watermark
            if (isset($watermark_single_image)) {
                $cmdparams["%%WMPOSITION%%"] = new CommandPlaceholderArg(
                    $watermark_single_image['position'] ?? "Center",
                    fn($val): bool => in_array(
                        $val,
                        ["NorthWest","North","NorthEast","West","Center","East","SouthWest","South","SouthEast"]
                    )
                );
            }
        }

        if (isset($default_icc_file)) {
            $cmdparams["%%DEFAULT_ICC_FILE%%"] = new CommandPlaceholderArg($default_icc_file, 'is_safe_basename');
        }

        $use_icc_profile = can_apply_icc_profile($origfile);

        $created_count = 0;
        $mpr_edited = false;
        $override_size = false;
        $icc_transform_complete = false;

        if ($imagemagick_mpr) {
            // Order of generation matters with MPR mode - need HPR and tiles first or the watermark command sections interfere with the tiles
            usort($ps, function ($a, $b) {
                return (
                    ($a["id"] === "hpr" || (substr($a["id"], 0, 4) === "tile" && $b["id"] !== "hpr")) // HPR and tiles first
                    || ($a["width"] > $b["width"])  // Order by size descending
                ) ?  -1 : 1;
            });
        }
        for ($n = 0; $n < count($ps); $n++) {
            if (PHP_SAPI !== "cli" && !$imagemagick_mpr) {
                // All previews are created with one command in MPR mode
                set_processing_message(str_replace(["[resource]","[name]"], [$ref,($ps[$n]["name"] ?? "???")], $lang["processing_creating_preview"]));
            }

            # If this is just a jpg resource, we avoid the hpr size because the resource itself is an original sized jpg.
            # If preview_preprocessing indicates the intermediate jpg should be kept as the hpr image, do that.
            if ($keep_for_hpr && $ps[$n]['id'] == "hpr") {
                rename($file, $allpresizes["hpr"]); // $keep_for_hpr is switched to false below
                $override_size = true; // Prevent using original file when hpr size is smaller than pre size - always create pre size.
            }

            # If we've already made the LPR or SCR then use those for the remaining previews.
            # As we start with the large and move to the small, this will speed things up.
            $using_original = false;
            $mpr_delete = false;
            if (in_array(strtolower($extension), $preview_keep_alpha_extensions)) {
                // These need to use original source for transparency
                $file = $origfile;
                $using_original = true;
                // Reset icc flag as still required when using original as source
                $icc_transform_complete = false;
            } else {
                if (isset($ps[$n]['type']) && $ps[$n]['type'] == "tile") {
                    // Alway use original or HPR size as source
                    $file = file_exists($allpresizes["hpr"]) ? $allpresizes["hpr"] : $origfile;
                    if ($file == $origfile) {
                        $using_original = true;
                        $icc_transform_complete = false;
                    }
                } elseif ($file != $allpresizes["hpr"]) {
                    // Get a source file with dimensions sufficient to create the required size. Unusually wide
                    // or tall images can mean that the height/width of the larger sizes is less than the required
                    // target height/width
                    foreach (array_values(array_merge($allpresizes, [$origfile])) as $pre_source) {
                        if (file_exists($pre_source)) {
                            list($checkw,$checkh) = try_getimagesize($pre_source);
                            if (
                                $checkw > $ps[$n]['width'] && $checkh > $ps[$n]['height']
                                || $override_size
                                || $pre_source == $origfile
                            ) {
                                // If $pre_source == $origfile get icc profile again as this size maybe used as source for smaller sizes
                                $file = $pre_source;
                                if ($file == $origfile) {
                                    $using_original = true;
                                    // Reset icc flag as still required when using original as source
                                    $icc_transform_complete = false;
                                } else {
                                    $using_original = false;
                                    $icc_transform_complete = true;
                                }
                                debug("Using source file : '" . $pre_source  . "'");
                                break;
                            }
                        }
                    }
                }
            }

            // HPR? Use the original image size instead of the hpr.
            if ($ps[$n]["id"] == "hpr") {
                if ($sw >= 65500 || $sh >= 65500) {
                    # Source image exceeds maximum JPEG dimensions
                    $ps[$n]["width"] = 65500;
                    $ps[$n]["height"] = 65500;
                } else {
                    $ps[$n]["width"] = $sw;
                    $ps[$n]["height"] = $sh;
                }
            }

            if (
                $prefix === "cr2:"
                || $prefix === "nef:"
                || in_array(strtolower($extension), $preview_no_flatten_extensions)
                || getval("noflatten", "") !== ""
            ) {
                // Option -flatten removes all transparency; option +matte turns off alpha channel (+matte is deprecated and has been replaced by -alpha off)
                // Extensions for which the alpha/matte channel should not be disabled (and therefore option -flatten is unnecessary)
                $flatten = "";
            } else {
                $flatten = " -background white -flatten";
            }

            $addcheckbdpre = "";
            $addcheckbdafter = "";
            if (in_array(strtolower($extension), $preview_keep_alpha_extensions)) {
                // Add checkerboard code
                $cb_scale = 100;
                $cb_width = $sw;
                $cb_height = $sh;
                if (($sw > 1200 || $sh > 1200) && $sw > 0 && $sh > 0) {
                    // Scale the checkerboard for larger images to make it more visible
                    $cb_width = $sw / 6;
                    $cb_height = $sh / 6;
                    $cb_scale = 600;
                }
                $addcheckbdpre = "-size " . ceil($cb_width) . "x" . ceil($cb_height);
                if ($extension == "svg") {
                    $addcheckbdpre .= " tile:pattern:checkerboard -background none -modulate 150,100 -scale " . $cb_scale . "% -crop %%CB_CROP%% +repage ";
                } else {
                    $addcheckbdpre .= " tile:pattern:checkerboard -modulate 150,100 -scale " . $cb_scale . "% -crop %%CB_CROP%% +repage ";
                }
                $cmdparams["%%CB_CROP%%"] = new CommandPlaceholderArg(
                    (int)$sw . "x" . (int)$sh . "+0+0",
                    [CommandPlaceholderArg::class, 'alwaysValid']
                );
                $addcheckbdafter = "-compose over -composite ";
            }

            $useprefix = !$config_windows && preg_match('/^\w\w\w:/', $file) === 1;
            if (
                !$imagemagick_mpr
                || !$mpr_edited
            ) {
                // Only add initial binary path source etc. if not using MPR or if this is the first part of the MPR command
                $command = $convert_fullpath . " {$addcheckbdpre} %%SOURCEFILE%%[0] ";
                if ($imagemagick_mpr) {
                    $command .= ' -quiet -depth ' . $GLOBALS['imagemagick_mpr_depth'];
                }
                $sourcefile = ($useprefix ? $extension . ':' : '') . $file;
                $cmdparams["%%SOURCEFILE%%"] =  new CommandPlaceholderArg($sourcefile, 'is_valid_rs_path');
                if (strtolower($extension) === 'svg') {
                    $command .= "  -transparent none ";
                }
                $command .= $flatten . ' -quality ' . $imagemagick_quality;
            } else {
                // MPR - source for all but the first is the MPR file
                $command =  " mpr:" . $ref;
            }

            // Get target width and height
            $tw = $ps[$n]["width"];
            $th = $ps[$n]["height"];
            $id = $ps[$n]["id"];

            # Add crop if generating a tile
            $crop = false;
            if (isset($ps[$n]['type']) && $ps[$n]['type'] == "tile") {
                $cropx = $ps[$n]["x"];
                $cropy = $ps[$n]["y"];
                $cropw = $ps[$n]["w"];
                $croph = $ps[$n]["h"];
                $crop = true;
            }
            if ($imagemagick_mpr && $id == 'hpr' && $tw == 999999 && isset($o_width)) {
                // Pass on the original dimensions
                $tw = $o_width;
                $th = $o_height;
            }

            debug("Contemplating " . $ps[$n]["id"] . " (sw=$sw, tw=$tw, sh=$sh, th=$th, extension=$extension)");

            # Find the target path
            $path = get_resource_path($ref, true, $ps[$n]["id"], ($imagemagick_mpr ? true : false), "jpg", -1, 1, false, "", $alternative);
            $cmdparams["%%TARGET%%"] = new CommandPlaceholderArg($path, 'is_valid_rs_path');

            # Delete any file at the target path. Unless using the previewbased option, in which case we need it.
            if (
                !hook("imagepskipdel")
                && !$keep_for_hpr
                && !$previewbased
                && file_exists($path)
            ) {
                    debug("Deleting file at path: $path");
                    unlink($path);
            }
            if ($keep_for_hpr) {
                $keep_for_hpr = false;
            }

            # Also try the watermarked version.
            $wpath = get_resource_path($ref, true, $ps[$n]["id"], false, "jpg", -1, 1, true, "", $alternative);
            if (file_exists($wpath)) {
                unlink($wpath);
            }

            # Always make a screen size for non-JPEG extensions regardless of actual image size
            # This is because the original file itself is not suitable for full screen preview, as it is with JPEG files.
            #
            # Always make preview sizes for smaller file sizes.
            #
            # Always make pre/thm/col sizes regardless of source image size.
            if (
                $imagemagick_mpr // Always load the profile rotated transformed file into memory
                || (in_array($id, ["hpr","scr"]) && !is_jpeg_extension($extension))
                || ($id == 'scr' && $extension == 'jpg' && $watermark !== '')
                || $previews_allow_enlarge
                || $sw > $tw
                || $sh > $th
                || in_array($id, array_merge($always_make_previews, ["pre","thm","col"]))
                || hook('force_preview_creation', '', [$ref, $ps, $n, $alternative])
            ) {
                if (!isset($ps[$n]['type']) || $ps[$n]['type'] !== "tile") {
                    // Log the size being created but not the path
                    resource_log(RESOURCE_LOG_APPEND_PREVIOUS, LOG_CODE_TRANSFORMED, '', '', '', "Generating preview size " . $ps[$n]["id"]);
                }
                debug("Generating preview size " . $ps[$n]["id"] . " to " . $path);

                # Use existing ICC profile if present
                if ($icc_extraction && !$previewbased) {
                    $iccpath = get_resource_path($ref, true, '', false, 'icc', -1, 1, false, "", $alternative);
                    if (
                        !file_exists($iccpath)
                        && !isset($iccfound)
                        && $extension != "pdf"
                        && !in_array($extension, $ffmpeg_supported_extensions)
                    ) {
                        // Extracted profile doesn't exist. Try extracting.
                        if (extract_icc_profile($ref, $extension, $alternative)) {
                            $iccfound = true;
                        } else {
                            $iccfound = false;
                        }
                    }
                }
                $profile = '';
                if (
                    $use_icc_profile
                    && !$icc_transform_complete
                    && !$previewbased
                    && file_exists($iccpath)
                    && (!$imagemagick_mpr || ($imagemagick_mpr_preserve_profiles && in_array($id, ["pre","thm","col","scr"])))
                ) {
                    global $icc_preview_profile_embed;
                    // we have an extracted ICC profile, so use it as source
                    $targetprofile = __DIR__ . '/../iccprofiles/' . $icc_preview_profile;
                    $profile = " -strip -profile %%ICCPATH%% " . $icc_preview_options . " -profile %%TARGETPROFILE%% ";

                    if ($imagemagick_mpr && $mpr_metadata_profiles !== '') {
                        $profile .= " +profile %%MPR_METADATA_PROFILES%%";
                        $cmdparams["%%MPR_METADATA_PROFILES%%"] = new CommandPlaceholderArg(
                            $mpr_metadata_profiles,
                            fn($m) => preg_match('/[^\\!\\*,\w]/', $m) === 0 // Allows word characters, !, * and commas only
                        );
                    }
                    $profile .= $icc_preview_profile_embed ? " " : " -strip ";
                    $cmdparams["%%ICCPATH%%"] = new CommandPlaceholderArg($iccpath, 'is_valid_rs_path');
                    $cmdparams["%%TARGETPROFILE%%"] = new CommandPlaceholderArg($targetprofile, 'file_exists');

                    // Consider ICC transformation complete, if one of the sizes has been rendered that will be used for the later sizes or the MPR version that will be used as the source has been created
                    if ($id == 'hpr' || $id == 'lpr' || $id == 'scr') {
                        $icc_transform_complete = true;
                    }
                } else {
                    // use existing strategy for color profiles
                    // Preserve colour profiles? (omit for smaller sizes or if using MPR and not first transform)
                    if (
                        ($imagemagick_preserve_profiles || $imagemagick_mpr_preserve_profiles)
                        && !in_array($id, ["thm", "col", "pre", "scr"])
                        && (!isset($ps[$n]['type']) || !in_array($ps[$n]['type'], array('tile', 'resize')))
                    ) {
                        $profile = "";
                    } elseif (!empty($default_icc_file)) {
                        $profile = " -profile %%DEFAULT_ICC_FILE%%";
                    } else {
                        // By default, strip the colour profiles ('+' is remove the profile, confusingly)
                        $profile = " -strip -colorspace %%IMAGEMAGICK_COLORSPACE%% ";
                    }
                }

                $command .= !in_array(strtolower($extension), $preview_keep_alpha_extensions)
                    ? $alphaoff  . " " . $profile
                    : $profile;

                if ($crop) {
                    // Add crop argument for tiling
                    $command .= " -crop %%CROPDIMENSIONS%% ";
                    $cmdparams["%%CROPDIMENSIONS%%"] = (int)$cropw . "x" . (int)$croph . "+" . (int)$cropx . "+" . (int)$cropy;
                }

                $command .= " -resize %%TARGETDIMENSIONS%% ";
                if (
                    !$imagemagick_mpr
                    || !$mpr_edited
                ) {
                    // Only required before first command
                    $command .= $addcheckbdafter;
                }

                $command .= " [WATERMARK_INSERT] "; // Placeholder for converting command to include watermarked
                if ($imagemagick_mpr && ($n < count($ps) - 1 || $watermark !== '')) {
                    // This is needed for all but the final last output file
                    $command .= " -write";
                }
                $command .=  " %%TARGET%% ";

                // Watermarks are not required at this stage
                $runcommand = str_replace("[WATERMARK_INSERT]", "", $command);

                $cmdparams["%%TARGETDIMENSIONS%%"] = new CommandPlaceholderArg(
                    (int)$tw . "x" . (int)$th
                    . (($previews_allow_enlarge && $id != "hpr" || $crop) ? "" : ">"),
                    [CommandPlaceholderArg::class, 'alwaysValid']
                );

                if (!hook("imagepskipthumb")) {
                    $rotatethis = false;
                    if (
                        $autorotate_no_ingest
                        && !$ingested
                        && in_array($extension, $camera_autorotation_ext)
                        && strpos($file, $syncdir) === 0
                    ) {
                        // If this is the first file generated or the original file is used as the source,
                        // for non-ingested resources check rotation
                        $rotatethis = true;
                    }

                    if (!$imagemagick_mpr) {
                        run_command($runcommand, false, $cmdparams);
                        $created_count++;
                        if ($rotatethis && ($created_count == 1 || $using_original)) {
                            // first preview created for non-ingested file...auto-rotate
                            if (in_array($id, ["pre","thm","col","scr"])) {
                                AutoRotateImage($path, $ref);
                            } else {
                                AutoRotateImage($path);
                            }
                        }
                    } else {
                        if ($rotatethis && ($n == 0 || $using_original)) {
                            $file = get_resource_path($ref, true, "", false, $extension, -1, 1, false, "", -1);
                            $orientation = get_image_orientation($file);
                            if ($orientation != 0) {
                                $runcommand .= ' -rotate %%ORIENTATION%% ';
                                $cmdparams = [
                                    '%%ORIENTATION%%' => new CommandPlaceholderArg('+' . (int) $orientation, [CommandPlaceholderArg::class, 'alwaysValid']),
                                ];
                            }
                        }
                        if ($imagemagick_mpr && !$mpr_edited) {
                            // Replace the MPR source with this version
                            $runcommand .= " -write mpr:" . $ref;
                            $mpr_edited = true;
                        }
                        // Add to array that will be joined together and run at the end
                        $mpr_parts[] = escape_command_args($runcommand, $cmdparams);
                    }

                    # Add a watermarked image too?
                    if (
                        !hook("replacewatermarkcreation", "", [$ref, $ps, $n, $alternative, $profile, $command])
                    ) {
                        if (
                            ($alternative == -1 || ($alternative !== -1 && $alternative_file_previews))
                            && $watermark !== ''
                            && ($ps[$n]["internal"] == 1 || $ps[$n]["allow_preview"] == 1)
                            && (!isset($ps[$n]['type']) || $ps[$n]['type'] !== "tile")
                        ) {
                            $wmpath = get_resource_path($ref, true, $ps[$n]["id"], false, "jpg", -1, 1, true, '', $alternative);
                            $cmdparams["%%TARGET%%"] = new CommandPlaceholderArg($wmpath, "is_safe_basename");
                            if (file_exists($wmpath)) {
                                unlink($wmpath);
                            }
                            // Set up the watermark code.
                            if (isset($watermark_single_image)) {
                                // Generate the command for a single watermark instead of a tiled one
                                $wm_scale = $watermark_single_image['scale'];
                                if ($id == "hpr" && ($tw > $sw || $th > $sh)) {
                                    // reverse them as the watermark geometry should be as big as the image itself
                                    // hpr size is 999999 width/height - the geometry would be huge even if we are scaling it
                                    // for watermarks
                                    $temp_tw = $tw;
                                    $temp_th = $th;

                                    $tw = $sw;
                                    $th = $sh;

                                    $sw = $temp_tw;
                                    $sh = $temp_th;

                                    debug("create_previews_using_im: reversed sw - sh with tw - th : $sw - $sh with $tw - $th");
                                }
                                // Work out minimum of target dimensions, by calulating targets dimensions based on actual file ratio to get minimum dimension, essential to calulate correct values based on ratio of watermark
                                if ($sw > $sh) {
                                    // Landscape
                                    $tmin = min($tw * ($sh / $sw), $th);
                                } elseif ($sw < $sh) {
                                    // Portrait
                                    $tmin = min($th * ($sw / $sh), $tw);
                                } else {
                                    // Square
                                    $tmin = min($tw, $th);
                                }
                                $wm_scale = $watermark_single_image['scale'];
                                if ($wmw > $wmh) {
                                    // Landscape
                                    $wm_scaled_height = ($tmin * ($wmh / $wmw)) * ($wm_scale / 100);
                                    $wm_scaled_width = $tmin * ($wm_scale / 100);
                                } elseif ($wmw < $wmh) {
                                    // Portrait
                                    $wm_scaled_width = ($tmin * ($wmw / $wmh)) * ($wm_scale / 100);
                                    $wm_scaled_height = $tmin * ($wm_scale / 100);
                                } else {
                                    // Square
                                    $wm_scaled_width = $tmin * ($wm_scale / 100);
                                    $wm_scaled_height = $tmin * ($wm_scale / 100);
                                }

                                // Command example: convert input.jpg watermark.png -gravity Center -geometry 40x40+0+0 -resize 1100x800 -composite wm_version.jpg
                                $watermarkcode = " %%WMFILE%% -gravity %%WMPOSITION%% -geometry %%WM_SCALED_DIMS%% -resize %%TARGETDIMENSIONS%% -composite ";
                                $cmdparams["%%WM_SCALED_DIMS%%"] = new CommandPlaceholderArg(
                                    (int)$wm_scaled_width . "x" . (int)$wm_scaled_height . "+0+0",
                                    [CommandPlaceholderArg::class, 'alwaysValid']
                                );
                            } else {
                                // Generate the command for a tiled watermark
                                $cmdparams["%%REC_DIMENSIONS%%"] = new CommandPlaceholderArg("rectangle 0,0 " . (int) $tw . "," . (int)$th, [CommandPlaceholderArg::class, 'alwaysValid']);
                                $cmdparams["%%TARGETDIMENSIONSWM%%"] = new CommandPlaceholderArg(
                                    (int)$tw . "x" . (int)$th
                                    . (($previews_allow_enlarge && $id != "hpr") ? "" : ">"),
                                    [CommandPlaceholderArg::class, 'alwaysValid']
                                );
                                $watermarkcode = " -tile %%WMFILE%% -draw %%REC_DIMENSIONS%% ";
                            }

                            if ($imagemagick_mpr) {
                                // This will use the previous command's output as the source
                                if ($n < count($ps) - 1) {
                                    // This is needed for all but the final last output file
                                    $command = $watermarkcode . " -write %%TARGET%% " ;
                                    $mpr_parts[] = escape_command_args($command, $cmdparams);
                                    // Delete these versions from MPR after writing or multiple extra files are generated
                                    $mpr_parts[] = " -delete 0 -delete 1";
                                } else {
                                    // Final file to be generated, no '-write' or deletions required
                                    $command = $watermarkcode . " %%TARGET%%" ;
                                    $mpr_parts[] = escape_command_args($command, $cmdparams);
                                }
                            } else {
                                $command = str_replace("[WATERMARK_INSERT]", $watermarkcode, $command);
                                run_command($command, false, $cmdparams);
                            }
                        } else {
                            // No watermarked version required
                            if ($imagemagick_mpr) {
                                if ($n < (count($ps) - 1)) {
                                    // Delete this version from MPR after writing
                                    $mpr_parts[] = " -delete 0 ";
                                }
                                if ($mpr_delete) {
                                    // Delete the working disk image
                                    $mpr_parts[] = " +delete ";
                                }
                            }
                        }
                    } // End hook replacewatermarkcreation
                } // End hook imagepskipthumb
            } // End of 'always make these sizes'
        } // End of foreach size

        // Run the mpr command if set
        if ($imagemagick_mpr) {
            $mpr_command = implode(" ", $mpr_parts);
            $modified_mpr_command = hook('modify_mpr_command', '', array($mpr_command, $ref, $extension));
            if ($modified_mpr_command != '') {
                $mpr_command = $modified_mpr_command;
            }
            run_command($mpr_command);
        }

         # For the thumbnail image, call extract_mean_colour() to save the colour/size information
         $thumbpath = get_resource_path($ref, true, "thm", false, "jpg", -1, 1, false, "", $alternative);
        if (file_exists($thumbpath)) {
            $GLOBALS["use_error_exception"] = true;
            try {
                $target = imagecreatefromjpeg($thumbpath);
            } catch (Throwable $e) {
                $target = false;
                debug('Error when opening thm size for calling extract_mean_colour(): ' . $e->getMessage());
            }
            unset($GLOBALS["use_error_exception"]);
        } else {
            $target = false;
        }

        $resource_data = get_resource_data($ref);
        if ($target && $alternative == -1) {
            // Do not run for alternative uploads
            extract_mean_colour($target, $ref);
            // Flag database. If this was run for e.g. a video or PDF it is not the full set of previews
            $has_image = (!in_array($resource_data["file_extension"], $ffmpeg_supported_extensions) && $resource_data["file_extension"] != "pdf" && ($generateall || $previewonly)) ? RESOURCE_PREVIEWS_ALL : RESOURCE_PREVIEWS_MINIMAL;

            ps_query("UPDATE resource SET has_image=?, preview_extension='jpg', preview_attempts=0, file_modified=NOW() WHERE ref = ?", ["i",$has_image, "i", $ref]);
        } else {
            if (!$target) {
                ps_query("UPDATE resource SET preview_attempts=IFNULL(preview_attempts,0) + 1 WHERE ref = ?", ["i",$ref]);
            }
        }

        if (
            $alternative == -1
            && isset($GLOBALS["image_alternatives"])
            && $generateall
            && ($resource_data["file_extension"] ?? "") === $extension
        ) {
            // Create alternatives
            create_image_alternatives(
                $ref,
                ["extension" => $extension,
                "file" => $file,
                "previewonly" => $previewonly,
                "previewbased" => $previewbased,
                "ingested" => $ingested],
            );
        }
        hook('afterpreviewcreation', '', array($ref, $alternative, $generateall));
        return true;
    } else {
        return false;
    }
}
