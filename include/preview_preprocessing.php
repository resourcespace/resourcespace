<?php

#
# This file contains the integration code with ImageMagick
# It also contains integration code for those types that we need ImageMagick to be able to process
# for example types that use GhostScript or FFmpeg.
#

use Montala\ResourceSpace\CommandPlaceholderArg;

global $imagemagick_path, $imagemagick_preserve_profiles, $imagemagick_quality, $imagemagick_colorspace,
$ghostscript_path, $pdf_pages, $unoconv_path, $pdf_resolution, $pdf_dynamic_rip, $extracted_text_extensions,
$ffmpeg_audio_extensions, $ffmpeg_audio_params,$ffmpeg_supported_extensions, $ffmpeg_global_options,
$ffmpeg_snapshot_fraction, $ffmpeg_snapshot_seconds, $lang, $dUseCIEColor, $blender_path, $ffmpeg_preview_gif, $debug_log, $debug_log_override;

resource_log($ref, LOG_CODE_TRANSFORMED, '', '', '', $lang['createpreviews'] . ":\n");

# Locate utilities
$exiftool_fullpath = get_utility_path("exiftool");
$ghostscript_fullpath = get_utility_path("ghostscript");

global $keep_for_hpr;
$preprocess = true; // indicate that an intermediate jpg is being made, so that image_processing doesn't skip the hpr

if (!$previewonly) {
    $file = get_resource_path($ref, true, "", false, $extension, -1, 1, false, "", $alternative);
    $target = get_resource_path($ref, true, "", true, "jpg", -1, 1, false, "", $alternative);
} else {
    # Use temporary preview source/destination - user has uploaded a file intended to replace the previews only.
    $file = get_resource_path($ref, true, "tmp", false, $extension);
    $target = get_resource_path($ref, true, "tmp", false, "jpg");
}

# Check if GIF is to be treated as a video type for the creation of previews
if ($ffmpeg_preview_gif) {
    $ffmpeg_supported_extensions[] = 'gif';
}

# Set up ImageMagick
putenv("MAGICK_HOME=" . $imagemagick_path);

# Set up target file
if (file_exists($target)) {
    unlink($target);
}

# Locate imagemagick.
$convert_fullpath = get_utility_path("im-convert");
if (!$convert_fullpath) {
    exit("Could not find ImageMagick 'convert' utility at location '$imagemagick_path'");
}

debug("Starting preview preprocessing. File extension is $extension.", $ref);

hook("metadata");

/* ----------------------------------------
    Plugin-added preview support
   ----------------------------------------
*/
$preview_preprocessing_results = hook("previewsupport", "", array( "extension" => $extension ,"file" => $file,"target" => $target));
if (is_array($preview_preprocessing_results)) {
    if (isset($preview_preprocessing_results['file'])) {
        $file = $preview_preprocessing_results['file'];
    }
    if (isset($preview_preprocessing_results['extension'])) {
        $extension = $preview_preprocessing_results['extension'];
    }
    if (isset($preview_preprocessing_results['keep_for_hpr'])) {
        $keep_for_hpr = $preview_preprocessing_results['keep_for_hpr'];
    }
}

/* ----------------------------------------
    Try InDesign - for CS5 (page previews)
   ----------------------------------------
*/
if (
    $exiftool_fullpath != false
    && $extension == "indd"
    && !isset($newfile)
) {
        $indd_thumbs = extract_indd_pages($file);
        $filesize_indd = filesize_unlimited($file);
        $pagescommand = "";
    if (is_array($indd_thumbs)) {
        $n = 0;
        foreach ($indd_thumbs as $indd_page) {
            $n++;
            # Set up target file and create a jpg for each preview embedded in indd file.
            $size = "";
            if ($n > 1) {
                $size = "scr";
            } # Use screen size for other previews.
            $target = get_resource_path($ref, true, $size, false, "jpg", -1, $n, false, "", $alternative);
            if (file_exists($target)) {
                unlink($target);
            }

            base64_to_jpeg(str_replace("base64:", "", $indd_page), $target);

            if (file_exists($target) && $n == 1) {
                # Set the first preview to be the cover preview image.
                $newfile = $target;
            } else {
                # Watermark creation for additional pages.
                global $watermark;
                $scr_size = ps_query("SELECT width,height FROM preview_size WHERE id='scr'");
                if (empty($scr_size)) {
                    # since this is not an application required size we can't assume there's a record for it
                    $scr_size = ps_query("SELECT width,height FROM preview_size WHERE id='pre'");
                }
                $scr_width = $scr_size[0]['width'];
                $scr_height = $scr_size[0]['height'];
                if (
                    !hook("replacewatermarkcreation", "", array($ref,$size,$n,$alternative))
                    && $watermark !== ''
                    && $alternative == -1
                ) {
                        $path = get_resource_path($ref, true, $size, false, "", -1, $n, true, "", $alternative);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                        $command2 = $convert_fullpath . " \"$target\"[0] -quality $imagemagick_quality -resize " . escapeshellarg($scr_width) . "x" . escapeshellarg($scr_height) . " -tile " . escapeshellarg($watermark) . " -draw \"rectangle 0,0 $scr_width,$scr_height\" " . escapeshellarg($path);
                        $output = run_command($command2);
                }
            }
        }

            # Set (new resource) / update (recreate previews) page count for multi page preview if more than one preview present.
            $sql = "SELECT count(*) AS value FROM `resource_dimensions` WHERE resource = ?";
            $query = ps_value($sql, array("i",$ref), 0);

        if ($query == 0) {
            $parameters = array("i",$ref, "i",$n, "i",$filesize_indd);
            ps_query("INSERT INTO resource_dimensions (resource, page_count, file_size) VALUES (?, ?, ?)", $parameters);
        } else {
            $parameters = array("i",$n, "i",$ref);
            ps_query("UPDATE resource_dimensions SET page_count = ? WHERE resource = ?", $parameters);
        }

            $n = 0;
    }
}

/* ----------------------------------------
    Try PhotoshopThumbnail
   ----------------------------------------
*/
# Note: for good results, Photoshop Preferences must be set to save Preview image at Extra Large size.
if (($extension == "psd" || $extension == "psb") && !isset($newfile)) {
    global $photoshop_thumb_extract;
    if ($photoshop_thumb_extract) {
        if ($exiftool_fullpath != false) {
            $cmd = $exiftool_fullpath . ' -b -PhotoshopThumbnail ' . escapeshellarg($file) . ' > ' . $target;
            $output = run_command($cmd);
        }
        if (file_exists($target)) {
            #if the file contains an image, use it; if it's blank, it needs to be erased because it will cause an error in ffmpeg_processing.php
            if (filesize_unlimited($target) > 0) {
                $newfile = $target;
            } else {
                unlink($target);
            }
        }
    }
}

/* ----------------------------------------
    Try RAW preview extraction via exiftool
   ----------------------------------------
*/
if (($extension == "cr2" || $extension == "nef" || $extension == "dng" || $extension == "raf" || $extension == "rw2" || 'arw' == $extension) && !isset($newfile)) {
    global $cr2_thumb_extract;
    global $nef_thumb_extract;
    global $dng_thumb_extract;
    global $rw2_thumb_extract;
    global $raf_thumb_extract;
    global $arw_thumb_extract;

    if (
        (
            ($extension == "cr2" && $cr2_thumb_extract) ||
            ($extension == "nef" && $nef_thumb_extract) ||
            ($extension == "dng" && $dng_thumb_extract) ||
            ($extension == "rw2" && $rw2_thumb_extract) ||
            ($extension == "raf" && $raf_thumb_extract) ||
            ($extension == "arw" && $arw_thumb_extract)
        ) &&
        $exiftool_fullpath != false
    ) {
        // Run command to output all previews in order of binary data size
        $cmd_preview_list = $exiftool_fullpath . " %%FILE%% -preview:all";
        $output_preview = run_command($cmd_preview_list, false, ['%%FILE%%' => new CommandPlaceholderArg($file, 'is_valid_rs_path')]);

        if (strpos($output_preview, "Jpg From Raw") === 0) {
            $bin_tag = "-jpgfromraw";
        } elseif (strpos($output_preview, "Other Image") === 0) {
            $bin_tag = "-otherimage";
        } elseif (strpos($output_preview, "Preview Image") === 0) {
            $bin_tag = "-previewimage";
        } else {
            $bin_tag = "-thumbnailimage";
        }

        // Attempt extraction
        $cmd = $exiftool_fullpath . " -b %%BIN_TAG%% %%FILE%% -w %d%f.jpg";
        $wait = run_command($cmd, false, ['%%FILE%%'    => new CommandPlaceholderArg($file, 'is_valid_rs_path'),
                                          '%%BIN_TAG%%' => new CommandPlaceholderArg($bin_tag, [CommandPlaceholderArg::class, 'alwaysValid']),
                                         ]);
        $extractedpreview = preg_replace('"\.' . pathinfo($file, PATHINFO_EXTENSION) . '$"', '.jpg', $file);

        if ($target != $extractedpreview && file_exists($extractedpreview)) {
            rename($extractedpreview, $target);
        }

        if (filesize_unlimited($target) > 0) {
            $orientation = get_image_orientation($file);
            if ($orientation != 0) {
                $mogrify_fullpath = get_utility_path("im-mogrify");
                if ($mogrify_fullpath != false) {
                    $command = $mogrify_fullpath . ' -rotate +%%ORIENTATION%% %%TARGET%%';
                    $cmdparams = [
                        "%%ORIENTATION%%" => new CommandPlaceholderArg($orientation, 'is_positive_int_loose'),
                        "%%TARGET%%"      => new CommandPlaceholderArg($target, 'is_valid_rs_path')
                    ];
                    $wait = run_command($command, false, $cmdparams);
                }
            }
            $newfile = $target;
            $keep_for_hpr = true;
        } elseif (file_exists($target)) {
            unlink($target);
        }
    }
}

/* ----------------------------------------
        Try Apple iWork Formats
        The following are to generate previews for the Apple iWork files such
as Apple Pages, Apple Keynote, and Apple Numbers.
   ----------------------------------------
*/
if ((($extension == "pages") || ($extension == "numbers") || (!isset($unoconv_path) && $extension == "key")) && !isset($newfile)) {
    $cmd = "unzip -p " . escapeshellarg($file) . " \"QuickLook/Thumbnail.jpg\" > $target";
    $output = run_command($cmd);
    $newfile = $target;
}

/* ----------------------------------------
    Unoconv is a python-based utility to run files through OpenOffice. It is available in Ubuntu.
    This adds conversion of office docs to PDF format and adds them as alternative files (this behaviour can be disabled by
    adding the extension to non_image_types list)
    One could also see the potential to base previews on the PDFs for paging and better quality for most of these formats.
   ----------------------------------------
*/
global $unoconv_extensions;
$using_unoconv = false;
if (in_array($extension, $unoconv_extensions) && $extension != 'pdf' && isset($unoconv_path) && !isset($newfile)) {
    $unocommand = get_utility_path('unoconvert');
    $unoconvert = true;
    if (!$unocommand) {
        $unocommand = get_utility_path('unoconv');
        $unoconvert = false; // Legacy mode for unoconv
    }
    if (!$unocommand) {
        exit("unoconv/unoconvert executable not found");
    }

    $using_unoconv = true; # Prevent falling back to other less detailed preview options.
    $path_parts = pathinfo($file);
    $basename_minus_extension = remove_extension($path_parts['basename']);
    $file_validator = $GLOBALS['preview_preprocessing_file_validator'] ?? is_valid_rs_path(...);
    $pdffile = new CommandPlaceholderArg("{$path_parts['dirname']}/{$basename_minus_extension}.pdf", $file_validator);
    $file = new CommandPlaceholderArg($file, $file_validator);
    unset($GLOBALS['preview_preprocessing_file_validator']);

    if ($unoconvert) {
        // Use newer unoconvert utility (note - does not have a verbose mode)
        $output = run_command("{$unocommand} %file %pdffile", false, ['%file' => $file,'%pdffile' => $pdffile], 180);
    } else {
        // Legacy support for unoconv
        $output = run_command("{$unocommand} " . ($debug_log || $debug_log_override ? '-v' : '') . " --format=pdf %file", false, ['%file' => $file], 180);
    }

    debug('Preview_preprocessing : ' . $output);

    # Check for extracted text - if found, it has already been extracted from the uploaded file so don't replace it with the text from this pdf.
    global $extracted_text_field;
    $extract_pdf_text = false;
    if (isset($extracted_text_field)) {
        $extract_pdf_text = true;
        $current_extracted_text = get_data_by_field($ref, $extracted_text_field);
        if (!empty($current_extracted_text)) {
            $extract_pdf_text = false;
        }
    }

    $no_alt_condition = (
        $GLOBALS['non_image_types_generate_preview_only']
        && in_array($extension, $GLOBALS['non_image_types'])
        && in_array($extension, config_merge_non_image_types())
    );

    if (file_exists($pdffile) && $no_alt_condition) {
        // Set vars so we continue generating previews as if this is a PDF file
        $extension = "pdf";
        $file = $pdffile;
        $unoconv_fake_pdf_file = true;

        // We need to avoid a job spinning off another job because create_previews() can run as an offline job and it
        // includes preview_preprocessing.php.
        if (
            $extract_pdf_text
            && in_array($extension, $extracted_text_extensions)
            && $alternative == -1
        ) {
            global $offline_job_queue, $offline_job_in_progress;
            if ($offline_job_queue && !$offline_job_in_progress) {
                $extract_text_job_data = array(
                    'ref'       => $ref,
                    'extension' => $extension,
                    'path'      => $file,
                );

                job_queue_add('extract_text', $extract_text_job_data);
            } else {
                extract_text($ref, $extension, $pdffile);
            }
        }
    } elseif (file_exists($pdffile)) {
        # Attach this PDF file as an alternative download.
        ps_query("delete from resource_alt_files where resource = ? and unoconv='1'", array("i",$ref));
        $alt_ref = add_alternative_file($ref, "PDF version");
        $alt_path = get_resource_path($ref, true, "", false, "pdf", -1, 1, false, "", $alt_ref);
        global $lang;
        $alt_description = $lang['unoconv_pdf'];
        copy($pdffile, $alt_path);
        unlink($pdffile);

        $parameters = array("s",$ref . "-converted.pdf", "s",$alt_description, "i",filesize_unlimited($alt_path), "i",$ref, "i",$alt_ref);
        ps_query("UPDATE resource_alt_files 
                    SET file_name=?, description=?, file_extension='pdf', file_size=?, unoconv='1' where resource=? and ref=?", $parameters);

        # Set vars so we continue generating thumbs/previews as if this is a PDF file
        $extension = "pdf";
        $file = $alt_path;

        // We need to avoid a job spinning off another job because create_previews() can run as an offline job and it
        // includes preview_preprocessing.php.
        if ($extract_pdf_text && $alternative == -1) {
            global $offline_job_queue, $offline_job_in_progress;
            if ($offline_job_queue && !$offline_job_in_progress) {
                $extract_text_job_data = array(
                    'ref'       => $ref,
                    'extension' => $extension,
                    'path'      => $alt_path,
                );

                job_queue_add('extract_text', $extract_text_job_data);
            } else {
                extract_text($ref, $extension, $alt_path);
            }
        }
    } else {
        debug("Preview preprocessing: Attempt to create previews with Unoconv for ref $ref failed.");
        $using_unoconv = false;
    }
}

/* ----------------------------------------
    Calibre E-book processing
   ----------------------------------------
*/
global $calibre_extensions;
global $calibre_path;
if (in_array($extension, $calibre_extensions) && isset($calibre_path) && !isset($newfile)) {
    $calibrecommand = get_utility_path('calibre');
    if (!$calibrecommand) {
        exit("Calibre executable not found at '$calibre_path'");
    }

    $path_parts = pathinfo($file);
    $basename_minus_extension = remove_extension($path_parts['basename']);
    $pdffile = $path_parts['dirname'] . "/" . $basename_minus_extension . ".pdf";

    $wait = run_command(
        "{$calibrecommand} %file %pdffile ",
        false,
        [
            '%file' => $file,
            '%pdffile' => $pdffile,
        ]
    );

    if (file_exists($pdffile)) {
        # Attach this PDF file as an alternative download.
        ps_query("delete from resource_alt_files where resource = ? and unoconv='1'", array("i",$ref));
        $alt_ref = add_alternative_file($ref, "PDF version");
        $alt_path = get_resource_path($ref, true, "", false, "pdf", -1, 1, false, "", $alt_ref);
        global $lang;
        $alt_description = $lang['calibre_pdf'];
        copy($pdffile, $alt_path);
        unlink($pdffile);

        $parameters = array("s",$ref . "-converted.pdf", "s",$alt_description, "i",filesize_unlimited($alt_path), "i",$ref, "i",$alt_ref);
        ps_query("UPDATE resource_alt_files 
                    SET file_name=?, description=?, file_extension='pdf', file_size=? ,unoconv='1' where resource=? and ref=?", $parameters);

        # Set vars so we continue generating thumbs/previews as if this is a PDF file
        $extension = "pdf";
        $file = $alt_path;
    }
}

/* ----------------------------------------
    Try OpenDocument Format
   ----------------------------------------
*/
if (!$using_unoconv && (($extension == "odt") || ($extension == "ott") || ($extension == "odg") || ($extension == "otg") || ($extension == "odp") || ($extension == "otp") || ($extension == "ods") || ($extension == "ots") || ($extension == "odf") || ($extension == "otf") || ($extension == "odm") || ($extension == "oth")) && !isset($newfile)) {
    $cmd = "unzip -p " . escapeshellarg($file) . " \"Thumbnails/thumbnail.png\" > $target";
    $output = run_command($cmd);

    $odcommand = $convert_fullpath . " \"$target\"[0]  \"$target\"";
    $output = run_command($odcommand);

    if (file_exists($target)) {
        $newfile = $target;
    }
}

/* ----------------------------------------
    Try Microsoft OfficeOpenXML Format
    Also try Micrsoft XPS... the sample document I've seen uses the same path for the preview,
    so it will likely work in most cases, but I think the specs allow it to go anywhere.
   ----------------------------------------
*/
if (!$using_unoconv && (($extension == "docx") || ($extension == "xlsx") || ($extension == "pptx") || ($extension == "xps")) && !isset($newfile) && in_array($extension, $unoconv_extensions)) {
    $cmd = "unzip -p " . escapeshellarg($file) . " \"docProps/thumbnail.jpeg\" > $target";
    $output = run_command($cmd);
    $newfile = $target;
}

/* ----------------------------------------
    Try Blender 3D. This runs Blender on the command line to render the first frame of the file.
   ----------------------------------------
*/
if ($extension == "blend" && isset($blender_path) && !isset($newfile)) {
    $blendercommand = get_utility_path('blender');
    if (!$blendercommand) {
        exit("Could not find blender application. '$blendercommand'");
    }

    $error = run_command(
        "{$blendercommand} -b %file -F JPEG -o %target -f 1",
        false,
        [
            '%file' => $file,
            '%target' => $target,
        ]
    );

    if (file_exists($target . "0001")) {
        copy($target . "0001", "$target");
        unlink($target . "0001");
        $newfile = $target;
    }
    if (file_exists($target . "0001.jpg")) {
        copy($target . "0001.jpg", "$target");
        unlink($target . "0001.jpg");
        $newfile = $target;
    }
}

/* ----------------------------------------
    Try MP3 preview extraction via exiftool
   ----------------------------------------
*/
if (($extension == "mp3" || $extension == "flac") && !isset($newfile)) {
    if ($exiftool_fullpath != false) {
        $cmd = $exiftool_fullpath . ' -b -picture ' . escapeshellarg($file) . ' > ' . $target;
        $output = run_command($cmd);
    }
    if (file_exists($target)) {
        #if the file contains an image, use it; if it's blank, it needs to be erased because it will cause an error in ffmpeg_processing.php
        if (filesize_unlimited($target) > 0) {
            $newfile = $target;
        } else {
            unlink($target);
            $preview_preprocessing_success = true;
        }
    }
}

/* ----------------------------------------
    Try text file to JPG conversion
   ----------------------------------------
*/
# Support text files simply by rendering them on a JPEG.
if ($extension == "txt" && !isset($newfile)) {
    $text = wordwrap(file_get_contents($file), 90);
    $width = 650;
    $height = 850;
    $font = __DIR__ . "/../gfx/fonts/vera.ttf";
    $im = imagecreatetruecolor($width, $height);
    $col = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $width, $height, $col);
    $col = imagecolorallocate($im, 0, 0, 0);
    imagettftext($im, 9, 0, 10, 25, $col, $font, $text);
    imagejpeg($im, $target);
    $newfile = $target;
}

/* ----------------------------------------
    Try FFMPEG for video files
   ----------------------------------------
*/
$ffmpeg_fullpath = get_utility_path('ffmpeg');
$php_fullpath    = get_utility_path("php");

global $ffmpeg_preview,$ffmpeg_preview_seconds,$ffmpeg_preview_extension,$ffmpeg_preview_options,
       $ffmpeg_preview_min_width, $ffmpeg_preview_min_height, $ffmpeg_preview_max_width,
       $ffmpeg_preview_max_height, $ffmpeg_preview_force, $ffmpeg_snapshot_frames, $h264_profiles;

debug('FFMPEG-VIDEO: ####################################################################');
debug('FFMPEG-VIDEO: Start trying FFMPeg for video files -- resource ID ' . $ref);

if (($ffmpeg_fullpath != false) && !isset($newfile) && in_array($extension, $ffmpeg_supported_extensions)) {
    debug('FFMPEG-VIDEO: Start process for creating previews...');

    if ($alternative == -1) {
        //If we are recreating previews, we should remove the previously created snapshots
        $directory = dirname(get_resource_path($ref, true, "pre", true));
        foreach (glob($directory . "/*") as $filetoremove) {
            if (strpos($filetoremove, 'snapshot_') !== false) {
                unlink($filetoremove);
            }
        }
    }

    $snapshottime = 1;
    $duration = 0; // Set this as default if the duration is not determined so that previews will always work
    $cmd = $ffmpeg_fullpath . ' -i ' . escapeshellarg($file);
    $out = run_command($cmd, true);

    debug("FFMPEG-VIDEO: Running information command: {$cmd}");

    if (preg_match('/Duration: (\d+):(\d+):(\d+)\.\d+, start/', $out, $match)) {
        $duration = $match[1] * 3600 + $match[2] * 60 + $match[3];
        debug("FFMPEG-VIDEO: \$duration = {$duration} seconds");

        if (isset($ffmpeg_snapshot_seconds)) { // Overrides the other settings
            if ($ffmpeg_snapshot_seconds < $duration) {
                $snapshottime = $ffmpeg_snapshot_seconds;
            }
        } elseif (10 < $duration) {
            $snapshottime = floor($duration * (isset($ffmpeg_snapshot_fraction) ? $ffmpeg_snapshot_fraction : 0.1));
        }

        // Generate snapshots for the whole video (not for alternatives)
        // Custom target used ONLY for captured snapshots during the video
        if ($generateall && 1 < $ffmpeg_snapshot_frames && -1 == $alternative) {
            $snapshot_scale           = '';
            $escaped_file             = escapeshellarg($file);
            $escaped_target           = escapeshellarg(get_resource_path($ref, true, 'snapshot', false, 'jpg', -1, 1, false, ''));
            $snapshot_points_distance = max($duration / $ffmpeg_snapshot_frames, 1);

            // Find video resolution, figure out whether it is landscape/ portrait and adjust the scaling for the snapshots accordingly
            include_once __DIR__ . '/video_functions.php';

            $video_resolution = get_video_resolution($file);

            if ($exiftool_fullpath != false) {
                // Has it been rotated?
                $command = $exiftool_fullpath . " -s -s -s -Rotation " . escapeshellarg($file);
                $rotation = run_command($command);
                if ($rotation == "90" || $rotation == "270") {
                    $orig_video_resolution = $video_resolution;
                    $video_resolution['width']  = $orig_video_resolution['height'];
                    $video_resolution['height'] = $orig_video_resolution['width'];
                }
            }

            $snapshot_size    = ps_query('SELECT width, height FROM preview_size WHERE id = "pre"');

            if (isset($snapshot_size[0]) && 0 < count($snapshot_size[0])) {
                $snapshot_width  = $snapshot_size[0]['width'];
                $snapshot_height = $snapshot_size[0]['height'];
            }

            if ($video_resolution['width'] > $video_resolution['height'] && isset($snapshot_width) && $video_resolution['width'] >= $snapshot_width) {
                // Landscape
                $snapshot_scale = "-vf scale={$snapshot_width}:-1";
            } elseif ($video_resolution['width'] < $video_resolution['height'] && isset($snapshot_height) && $video_resolution['height'] >= $snapshot_height) {
                // Portrait
                $snapshot_scale = "-vf scale=-1:{$snapshot_height}";
            } else {
                // Square
                $snapshot_scale = "-vf scale={$snapshot_width}:-1";
            }

            for ($snapshot_point = 0, $i = 1; $snapshot_point <= $duration; $snapshot_point += $snapshot_points_distance, $i++) {
                $escaped_snapshot_target = str_replace('snapshot', "snapshot_{$i}", $escaped_target);

                $cmd = "{$ffmpeg_fullpath} {$ffmpeg_global_options} -loglevel error -y -ss {$snapshot_point} -i {$escaped_file} {$snapshot_scale} -frames:v 1 {$escaped_snapshot_target}";
                run_command($cmd);
            }
        }
    }

    if ('mxf' == $extension || $duration == 0) {
        $snapshottime = 0;
    }

    if (!hook('previewpskipthumb', '', array($file))) {
        $scale = '';
        if ($exiftool_fullpath != false) {
            $cmdparams = ["%%FILEPATH%%" => new CommandPlaceholderArg($file, 'is_valid_rs_path')];
            $output = run_command($exiftool_fullpath . ' -s3 -ImageWidth -ImageHeight %%FILEPATH%%', false, $cmdparams);
            $dimensions = explode("\n", $output);
            if (count(array_filter($dimensions, 'is_int_loose')) == 2) {
                $rotation = get_image_orientation($file);
                if ($rotation != 0 && $rotation != 180) {
                    $dimensions = array_reverse($dimensions);
                }
                $scale = '-vf scale=' . escapeshellarg(implode(':', $dimensions));
            }
        }

        $cmdparams = [
            "%%SNAPSHOTTIME%%" => new CommandPlaceholderArg($snapshottime, 'is_int_loose'),
            "%%FILEPATH%%" => new CommandPlaceholderArg($file, 'is_valid_rs_path'),
            "%%TARGET%%" => new CommandPlaceholderArg($target, 'is_valid_rs_path'),
        ];
        $output = run_command($ffmpeg_fullpath . ' ' . $ffmpeg_global_options . ' -y  -loglevel error -ss %%SNAPSHOTTIME%% -i %%FILEPATH%% ' . $scale . ' -f image2 -vframes 1 %%TARGET%%', false, $cmdparams); // $scale can't be a parameter - see how it is constructed above

        debug("FFMPEG-VIDEO: Get snapshot: {$cmd}");
    }

    if (file_exists($target)) {
        $newfile = $target;
        debug('FFMPEG-VIDEO: $newfile = ' . $newfile);
    }

    if ($ffmpeg_preview && ($extension != $ffmpeg_preview_extension || $ffmpeg_preview_force)) {
        debug('FFMPEG-VIDEO: include ffmpeg_processing.php file...');
        include __DIR__ . "/ffmpeg_processing.php";
    }

    debug('FFMPEG-VIDEO: ####################################################################');
}

/* ----------------------------------------
    Try FFMPEG for audio files
   ----------------------------------------
*/
if (($ffmpeg_fullpath != false) && in_array($extension, $ffmpeg_audio_extensions)) {
    # Produce the MP3 preview.
    $mp3file = get_resource_path($ref, true, "", false, "mp3", 1, 1, false, "", $alternative);

    $cmd = $ffmpeg_fullpath . " -y -i " . escapeshellarg($file) . " " . $ffmpeg_audio_params . " " . escapeshellarg($mp3file);
    $output = run_command($cmd);

    if (!file_exists($mp3file)) {
        $preview_preprocessing_success = false;
        echo debug("Failed to process resource " . $ref . " - MP3 creation failed.");
    } else {
        $preview_preprocessing_success = true;
        # Preview creation successful for mp3 alternative. No image is expected in this scenario.
        $has_image = 0;
    }
}

/* ----------------------------------------
    Try ImageMagick
   ----------------------------------------
*/
if ((!isset($newfile)) && (!in_array($extension, array_merge($ffmpeg_audio_extensions, array('mp3')))) && (!in_array($extension, $ffmpeg_supported_extensions))) {
    $prefix = "";

    # Preserve colour profiles?
    $profile = "+profile icc -colorspace " . $imagemagick_colorspace; # By default, strip the colour profiles ('+' is remove the profile, confusingly)
    if ($imagemagick_preserve_profiles) {
        $profile = "";
    }

    # CR2 files need a cr2: prefix
    if ($extension == "cr2") {
        $prefix = "cr2:";
    }

    $photoshop_eps = false;
    global $photoshop_eps_miff;
    if (
        $photoshop_eps_miff
        && $extension == "eps" # Recognize Photoshop EPS(F) pixel data files
    ) {
        $eps_file = fopen($file, 'r');
        $i = 0;
        while (!$photoshop_eps && ($eps_line = fgets($eps_file)) && ($i < 100)) {
            if (@preg_match("/%%BoundingBox: [0-9]+ [0-9]+ ([0-9]+) ([0-9]+)/i", $eps_line, $regs)) {
                $eps_bbox_x = $regs[1];
                $eps_bbox_y = $regs[2];
            }
            if (@preg_match("/%ImageData: ([0-9]+) ([0-9]+)/i", $eps_line, $regs)) {
                $eps_data_x = $regs[1];
                $eps_data_y = $regs[2];
            }
            if (@preg_match("/%BeginPhotoshop:/i", $eps_line)) {
                $photoshop_eps = true;
            }
            $i++;
        }
        if ($photoshop_eps) {
            $eps_density_x = $eps_data_x / $eps_bbox_x * 72;
            $eps_density_y = $eps_data_y / $eps_bbox_y * 72;
            $eps_target = get_resource_path($ref, true, "", false, "miff");
            $nfcommand = $convert_fullpath . ' -compress zip -colorspace ' . $imagemagick_colorspace . ' -quality 100 -density ' . sprintf("%.1f", $eps_density_x) . 'x' . sprintf("%.1f", $eps_density_y) . ' ' . escapeshellarg($file) . '[0] ' . escapeshellarg($eps_target);
            $output = run_command($nfcommand);
            if (file_exists($eps_target)) {
                $extension = 'miff';
            }
        }
    }
    if (($extension == "pdf") || (($extension == "eps") && !$photoshop_eps) || ($extension == "ai") || ($extension == "ps")) {
        debug("PDF multi page preview generation starting", RESOURCE_LOG_APPEND_PREVIOUS);
        $preview_preprocessing_success = false;

        # For EPS/PS/PDF files, use GS directly and allow multiple pages.
        # EPS files are always single pages:
        if (in_array($extension, ["eps","ai","ps"]) || !$generateall) {
            $pdf_pages = 1;
        }

        $resolution = $pdf_resolution;

        # Get preview sizes from DB
        $preview_sizes = get_all_image_sizes(true);

        $pre_size = array_values(array_filter($preview_sizes, function ($var) {
            return $var['id'] == 'pre';
        }));

        $scr_size = array_values(array_filter($preview_sizes, function ($var) {
            return $var['id'] == 'scr';
        }));

        $pre_width  = $pre_size[0]['width'];
        $pre_height = $pre_size[0]['height'];

        # Since scr is not an application required size we can't assume there's a record for it
        # so fallback to pre dimensions
        if (empty($scr_size)) {
            $scr_width  = $pre_size[0]['width'];
            $scr_height = $pre_size[0]['height'];
        } else {
            $scr_width  = $scr_size[0]['width'];
            $scr_height = $scr_size[0]['height'];
        }

        if ($pdf_dynamic_rip) {
           /* We want to rip at ~150 dpi by default because it provides decent
           * quality previews and speed in the end. It is not always efficient to just
           * rip at 150, though, because for very large pages, a lot of pixels
           * get wasted when we resize to 850 pixels. Also, if the page size is
           * quite small, ripping at 150 may not provide enough quality for the
           * scr size preview. So, use PDFinfo to calculate a rip resolution
           * that will give us a source bitmap of approximately 1600 pixels.
           */

            if ($extension == "pdf") {
                $pdfinfocommand = "pdfinfo " . escapeshellarg($file);
                $pdfinfo = run_command($pdfinfocommand);
                $pdfinfo = explode("\n", $pdfinfo);
                $pdfinfo = preg_grep("/\bPage\b.+\bsize\b/", $pdfinfo);
                sort($pdfinfo);

                if (isset($pdfinfo[0])) {
                    $pdfinfo = $pdfinfo[0];
                } else {
                    $pdfinfo = "";
                }

                if ($pdfinfo != "") {
                    $pdfinfo = explode(":", $pdfinfo);
                    $wh = explode("x", $pdfinfo[1]);
                    $w = round(trim($wh[0]));
                    $h = explode(" ", $wh[1]);
                    $h = round(trim($h[1]));
                    if ($w > $h) {
                        $pdf_max_dim = $w;
                    } else {
                        $pdf_max_dim = $h;
                    }

                    if ($pdf_max_dim != 0) {
                        #Determine largest of generated sizes
                        $pdf_target_width  = max($scr_width, $pre_width);
                        $pdf_target_height = max($scr_height, $pre_height);
                        $resolution = ceil((max($pdf_target_width, $pdf_target_height) * 2) / ($pdf_max_dim / 72));
                    } else {
                        $resolution = $pdf_resolution;
                    }
                }
            }

            if ($extension == "eps") {
                # Locate imagemagick.
                $identify_fullpath = get_utility_path("im-identify");
                if (!$identify_fullpath) {
                    debug("ERROR: Could not find ImageMagick 'identify' utility at location '$imagemagick_path'.");
                    return false;
                }

                $pdfinfocommand = $identify_fullpath . " " . escapeshellarg($file);
                $pdfinfo = run_command($pdfinfocommand);
                $pdfinfo = explode(" ", $pdfinfo);
                if (isset($pdfinfo[2])) {
                    $pdfinfo = $pdfinfo[2];
                    $pdfinfo = explode("+", $pdfinfo);
                    $pdfinfo = $pdfinfo[0];
                } else {
                    $pdfinfo = "";
                }
                if ($pdfinfo != "") {
                    $pdfinfo = str_replace("x", " ", $pdfinfo);
                    $pdfinfo = explode(" ", trim($pdfinfo));
                    if ($pdfinfo[0] > $pdfinfo[1]) {
                        $pdf_max_dim = $pdfinfo[0];
                    } else {
                        $pdf_max_dim = $pdfinfo[1];
                    }
                     #Determine largest of generated sizes
                     $pdf_target_width  = max($scr_width, $pre_width);
                     $pdf_target_height = max($scr_height, $pre_height);
                     $resolution = ceil((max($pdf_target_width, $pdf_target_height) * 2) / ($pdf_max_dim / 72));
                }
            }
        }

     # Create multiple pages.
        for ($n = 1; $n <= $pdf_pages; $n++) {
            # Set up target file
            $size = "";

            if ($n > 1) {
                $size = "scr";
            }

            if ($extension == "eps" && in_array(strtolower($extension), $preview_keep_alpha_extensions)) {
                $target = get_resource_path($ref, true, $size, false, "png", -1, $n, false, "", $alternative);
            } else {
                $target = get_resource_path($ref, true, $size, false, "jpg", -1, $n, false, "", $alternative);
            }

            if (file_exists($target)) {
                unlink($target);
            }

            if ($dUseCIEColor) {
                $dUseCIEColor = " -dUseCIEColor ";
            } else {
                $dUseCIEColor = "";
            }

            $cmdparams['%%RESOLUTION%%']    = new CommandPlaceholderArg((int) $resolution, 'is_positive_int_loose');
            $cmdparams["%%TARGET%%"]        = new CommandPlaceholderArg($target, 'is_safe_basename');
            $cmdparams["%%PAGENUM%%"]       = new CommandPlaceholderArg($n, 'is_positive_int_loose');
            $cmdparams["%%SOURCE%%"]        = new CommandPlaceholderArg($file, 'is_valid_rs_path');

            if ($extension == "eps" && in_array(strtolower($extension), $preview_keep_alpha_extensions)) {
                $gscommand2 = $ghostscript_fullpath . " -dBATCH -r%%RESOLUTION%% " . $dUseCIEColor . " -dNOPAUSE -sDEVICE=pngalpha -sOutputFile=%%TARGET%% -dFirstPage=%%PAGENUM%% -dLastPage=%%PAGENUM%% -dEPSCrop -dUseCropBox %%SOURCE%%";
            } else {
                $gscommand2 = $ghostscript_fullpath . " -dBATCH -r%%RESOLUTION%% " . $dUseCIEColor . " -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=%%QUALITY%% -sOutputFile=%%TARGET%% -dFirstPage=%%PAGENUM%% -dLastPage=%%PAGENUM%% -dEPSCrop -dUseCropBox %%SOURCE%%";
                $cmdparams["%%QUALITY%%"] = new CommandPlaceholderArg($imagemagick_quality, 'is_positive_int_loose');
            }

            $output = run_command($gscommand2, false, $cmdparams);

            # Stop trying when after the last page
            if (strstr($output, 'FirstPage > LastPage')) {
                break;
            }

            debug("PDF multi page preview: page $n, executing " . $gscommand2);

            # Set that this is the file to be used.
            if (file_exists($target) && $n == 1) {
                $newfile = $target;
                $pagecount = $n;
                debug("Page $n generated successfully", RESOURCE_LOG_APPEND_PREVIOUS);
            }

            # resize directly to the screen size (no other sizes needed)
            if (file_exists($target) && $n != 1) {
                $command2 = $convert_fullpath . " " . $prefix . escapeshellarg($target) . "[0] -quality $imagemagick_quality -resize "
                . escapeshellarg($scr_width) . "x" . escapeshellarg($scr_height) . " " . escapeshellarg($target);
                $output = run_command($command2);
                $pagecount = $n;

                # Add a watermarked image too?
                global $watermark, $watermark_single_image;
                if (
                    !hook("replacewatermarkcreation", "", array($ref,$size,$n,$alternative))
                    && $watermark !== '' 
                    && $alternative == -1
                ) {
                    $wmpath = get_resource_path($ref, true, $size, false, "", -1, $n, true, "", $alternative);
                    if (file_exists($wmpath)) {
                        unlink($wmpath);
                    }

                    if (!isset($watermark_single_image)) {
                        // Watermark is tiled
                        $command2 = $convert_fullpath . " \"$target\"[0] $profile -quality $imagemagick_quality -resize "
                        . escapeshellarg($scr_width) . "x" . escapeshellarg($scr_height) . " -tile " . escapeshellarg($watermark)
                        . " -draw \"rectangle 0,0 $scr_width,$scr_height\" " . escapeshellarg($wmpath);
                        $output = run_command($command2);
                    } else {
                        // Watermark is a single image
                        // The watermark geometry will be based on the shortest scr dimension scaled to the configured percentage
                        $wm_scale = $watermark_single_image['scale'] / 100;

                        if ($scr_width < $scr_height) {
                            // Portrait; scaled length is based on width
                            $wm_scaled_length = $scr_width * $wm_scale;
                            $wm_geometry = "x{$wm_scaled_length}+0+0";
                        } elseif ($scr_width > $scr_height) {
                            // Landscape; scaled length is based on height
                            $wm_scaled_length = $scr_height * $wm_scale;
                            $wm_geometry = "{$wm_scaled_length}x+0+0";
                        } else {
                            // Square; scaled length can be based on width or height; using width is purely arbitrary
                            $wm_scaled_length = $scr_width * $wm_scale;
                            $wm_geometry = "x{$wm_scaled_length}+0+0";
                        }

                        $command2_wm = sprintf(
                            '%s %s[0] -flatten %s -gravity %s -geometry %s -resize %s -composite %s',
                            $convert_fullpath,
                            escapeshellarg($target),
                            escapeshellarg($watermark),
                            escapeshellarg($watermark_single_image['position']),
                            escapeshellarg("{$wm_geometry}"),
                            escapeshellarg("{$scr_width}x{$scr_height}"),
                            escapeshellarg($wmpath)
                        );

                        $output = run_command($command2_wm);
                    }
                }

                // Generate path for pre copy of page
                $pre_target = get_resource_path($ref, true, "pre", false, "jpg", -1, $n, false, "", $alternative);

                // Copy scr to be used as source
                copy($target, $pre_target);

                // Resize to pre dimensions
                $command3 = $convert_fullpath . " " . $prefix . escapeshellarg($pre_target) . "[0] -quality $imagemagick_quality -resize "
                . escapeshellarg($pre_width) . "x" . escapeshellarg($pre_height) . " " . escapeshellarg($pre_target);
                $output = run_command($command3);

                // Copy and resize watermarked image if it exists
                if (isset($wmpath) && file_exists($wmpath)) {
                    // Generate path for pre copy of page
                    $pre_target_wm = get_resource_path($ref, true, "pre", false, "", -1, $n, true, "", $alternative);

                    // Copy watermarked scr to be used as source
                    copy($wmpath, $pre_target_wm);

                    // Resize to pre dimensions
                    $command4 = $convert_fullpath . " " . $prefix . escapeshellarg($pre_target_wm) . "[0] -quality $imagemagick_quality -resize "
                    . escapeshellarg($pre_width) . "x" . escapeshellarg($pre_height) . " " . escapeshellarg($pre_target_wm);
                    $output = run_command($command4);
                }
            }

            # Splitting of PDF files to multiple resources
            global $pdf_split_pages_to_resources;
            if (file_exists($target) && $pdf_split_pages_to_resources) {
                # Create a new resource based upon the metadata/type of the current resource.
                $copy = copy_resource($ref, -1, $lang["createdfromsplittingpdf"]);

                # Find out the path to the original file.
                $copy_path = get_resource_path($copy, true, "", true, "pdf");

                # Extract this one page to a new resource.
                $gscommand2 = $ghostscript_fullpath . " -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($copy_path) . "  -dFirstPage=" . $n . " -dLastPage=" . $n . " " . escapeshellarg($file);
                $output = run_command($gscommand2);

                # Update the file extension
                ps_query("update resource set file_extension='pdf' where ref=?", array("i",$copy));

                # Create preview for the page.
                $pdf_split_pages_to_resources = false; # So we don't get stuck in a loop creating split pages for the single page PDFs.
                create_previews($copy, false, "pdf");
                $pdf_split_pages_to_resources = true;
            }
        }
        // set page number and record filesize
        $filesize = filesize_unlimited($file);
        if (isset($pagecount) && $alternative != -1) {
            ps_query("UPDATE resource_alt_files SET page_count = ?, file_size = ? where ref = ?", array("i", $pagecount, "i", $filesize, "i", $alternative));
        } elseif (isset($pagecount)) {
            $sql = "SELECT count(*) AS value FROM `resource_dimensions` WHERE resource = ?";
            $query = ps_value($sql, array("i", $ref), 0);

            if ($query == 0) {
                ps_query("INSERT INTO resource_dimensions (resource, page_count, file_size) VALUES (?, ?, ?)", array("i", $ref, "i", $pagecount, "i", $filesize));
            } else {
                ps_query("UPDATE resource_dimensions SET page_count = ?, file_size = ? WHERE resource = ?", array("i", $pagecount, "i", $filesize, "i", $ref));
            }
        } elseif (!isset($pagecount) && $pdf_pages === 0) {
            // ResourceSpace may be configured to not preview PDF pages but during minimal preview creation it will
            // create a job for other previews (see start_previews()) so this operation is simply seen as successful.
            $preview_preprocessing_success = true;
        }
    } else {
        # Not a PDF file, so single extraction only.
        $preview_preprocessing_success = create_previews_using_im($ref, false, $extension, $previewonly, false, $alternative, $ingested, $onlysizes);
    }
}

$non_image_types = config_merge_non_image_types();

# If a file has been created, generate previews just as if a JPG was uploaded.
if (isset($newfile) && file_exists($newfile)) {
    if ($GLOBALS['non_image_types_generate_preview_only'] && in_array($extension, config_merge_non_image_types())) {
        $file_used_for_previewonly = get_resource_path($ref, true, "tmp", false, "jpg");
        // Don't create tiles for these
        $GLOBALS['preview_tiles'] = false;
        
        // Remove targeted file if it already exists to prevent permissions errors
        if (file_exists($file_used_for_previewonly)) {
            unlink($file_used_for_previewonly);
        }

        if (copy($newfile, $file_used_for_previewonly)) {
            $previewonly = true;
            debug("preview_preprocessing: changing previewonly = true for non-image file");
        }
    }

    if ($extension == "eps" && in_array(strtolower($extension), $preview_keep_alpha_extensions)) {
        $preview_preprocessing_success = create_previews($ref, false, "png", false, false, $alternative, $ignoremaxsize, true, $checksum_required, $onlysizes);
    } else {
        $preview_preprocessing_success = create_previews($ref, false, "jpg", false, false, $alternative, $ignoremaxsize, true, $checksum_required, $onlysizes);
    }

    if (
        $GLOBALS['non_image_types_generate_preview_only']
        && in_array($extension, $GLOBALS['non_image_types'])
        && file_exists($file_used_for_previewonly)
    ) {
        unlink($file_used_for_previewonly);
    }

    if (isset($unoconv_fake_pdf_file) && $unoconv_fake_pdf_file) {
        unlink($file);
    }
}
