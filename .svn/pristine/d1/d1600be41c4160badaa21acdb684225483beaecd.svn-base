<?php

ini_set('zlib.output_compression', 'off'); // disable PHP output compression since it breaks collection downloading
include __DIR__ . "/../include/boot.php";

// External access support (authenticate only if no key provided, or if invalid access key provided)
$k = getval("k", "");
if (($k == "") || (!check_access_key_collection(getval("collection", "", true), $k))) {
    include "../include/authenticate.php";
}
include_once __DIR__ . "/../include/csv_export_functions.php";
include_once __DIR__ . "/../include/pdf_functions.php";
ob_end_clean();
$collection = getval("collection", "", true);
if ($k != "") {
    $usercollection = $collection;
}
$size = getval("size", "original");
$submitted = getval("submitted", "") !== "";
$includetext = getval("includetext", "") === "true";
$useoriginal = getval("use_original", "") !== "";
$collectiondata = get_collection($collection);
$tardownload = getval("tardownload", "");
$include_csv_file = getval("include_csv_file", "") !== "";
$include_alternatives = getval("include_alternatives", "") !== "";
$email = getval('email', '');

if ($k != "" || (isset($anonymous_login) && $username == $anonymous_login)) {
    // Disable offline jobs as there is currently no way to notify the user upon job completion
    $offline_job_queue = false;
}

/// Get collection resources
$result = do_search("!collection" . $collection);
$modified_result = hook("modifycollectiondownload");
if (is_array($modified_result)) {
    $result = $modified_result;
}

// Check size?
$totalsize = 0;
$totalsizeerror = false;

if (in_array($size, ['original', 'largest'])) {
    // Estimate the total volume of files to zip if using largest or originals
    for ($n = 0; $n < count($result); $n++) {
        $totalsize += $result[$n]['file_size'];
    }
    if ($submitted && $totalsize > $collection_download_max_size) {
        $totalsizeerror = true;
        $onload_message = array("title" => $lang["error"], "text" => $lang["collection_download_too_large"]);
    }
}

$show_download_format_selector = true;

// Determine if the download format selector should be shown
if ($collection_download_tar_size === 0 || $config_windows) {
    $show_download_format_selector = false;
}

// Determine if TAR should be the default option
if ($show_download_format_selector && ($collection_download_tar_option || ($totalsize >= $collection_download_tar_size * 1024 * 1024))) {
    $tar_as_default = true;
} else {
    $tar_as_default = false;
}

// Determine selected download format selector value if submitted
if ($submitted && $show_download_format_selector) {
    $tar_selected = ($tardownload == 'on');
} else {
    $tar_selected = $tar_as_default;
}

$settings_id = (isset($collection_download_settings) && count($collection_download_settings) > 1) ? getval("settings", "") : 0;
$usage = getval('usage', '-1', true);
$usagecomment = getval('usagecomment', '');

// set the time limit to unlimited, default 300 is not sufficient here.
set_time_limit(0);

$archiver_fullpath = get_utility_path("archiver");
if (!$collection_download) {
    exit(escape($lang["download-of-collections-not-enabled"]));
} elseif (!$use_zip_extension) {
    if (!$archiver_fullpath) {
        exit(escape($lang["archiver-utility-not-found"]));
    }
    if (!isset($collection_download_settings)) {
        exit(escape($lang["collection_download_settings-not-defined"]));
    } elseif (!is_array($collection_download_settings)) {
        exit(escape($lang["collection_download_settings-not-an-array"]));
    }
    if (!isset($archiver_listfile_argument)) {
        exit(escape($lang["listfile-argument-not-defined"]));
    }
}

// Should the configured archiver be used
$archiver = (
        $collection_download
        && $archiver_fullpath != false
        && (isset($archiver_listfile_argument))
        && (isset($collection_download_settings)
    )
    ? is_array($collection_download_settings)
    : false
);

// This array will store all the available downloads.
$available_sizes = array();
$count_data_only_types = 0;

// Build the available sizes array
for ($n = 0; $n < count($result); $n++) {
    $ref = $result[$n]["ref"];
    // Load access level (0,1,2) for this resource
    $access = get_resource_access($result[$n]);

    // Get all possible sizes for this resource. If largest available has been requested then include internal or user could end up with no file depite being able to see the preview
    $sizes = get_all_image_sizes($size == "largest", $access >= 1);

    // Check availability of original file
    $p = get_resource_path($ref, true, "", false, $result[$n]["file_extension"]);
    if (file_exists($p) && (($access == 0) || ($access == 1 && $restricted_full_download)) && resource_download_allowed($ref, '', $result[$n]['resource_type'], -1, true)) {
        $available_sizes['original'][] = $ref;
    }

    // Check availability of each size and load it to the available_sizes array
    foreach ($sizes as $sizeinfo) {
        if (in_array($result[$n]['file_extension'], $ffmpeg_supported_extensions)) {
            $size_id = $sizeinfo['id'];
            // Video files will only have a 'pre' sized derivative so add to the sizes array
            $p = get_resource_path($ref, true, 'pre', false, $result[$n]['file_extension']);
            $size_id = 'pre';
            if (
                resource_download_allowed($ref, $size_id, $result[$n]['resource_type'], -1, true)
                && (hook('size_is_available', '', array($result[$n], $p, $size_id)) || file_exists($p))
            ) {
                $available_sizes[$sizeinfo['id']][] = $ref;
            }
        } elseif (in_array($result[$n]['file_extension'], array_merge($ffmpeg_audio_extensions, ['mp3']))) {
            // Audio files are ported to mp3 and do not have different preview sizes
            $p = get_resource_path($ref, true, '', false, 'mp3');
            if (
                resource_download_allowed($ref, '', $result[$n]['resource_type'], -1, true)
                && (hook('size_is_available', '', array($result[$n], $p, '')) || file_exists($p))
            ) {
                $available_sizes[$sizeinfo['id']][] = $ref;
            }
        } else {
            $size_id = $sizeinfo['id'];
            $size_extension = get_extension($result[$n], $size_id);
            $p = get_resource_path($ref, true, $size_id, false, $size_extension);
            if (
                resource_download_allowed($ref, $size_id, $result[$n]['resource_type'], -1, true)
                && (hook('size_is_available', '', array($result[$n], $p, $size_id)) || file_exists($p))
            ) {
                $available_sizes[$size_id][] = $ref;
            }
        }
    }

    if (in_array($result[$n]['resource_type'], $data_only_resource_types)) {
        $count_data_only_types++;
    }
}

if (isset($user_dl_limit) && intval($user_dl_limit) > 0) {
    $download_limit_check = get_user_downloads($userref, $user_dl_days);
    if ($download_limit_check + count($result) > $user_dl_limit) {
        $dlsummary = $download_limit_check . "/" . $user_dl_limit;
        $errormessage = $lang["download_limit_collection_error"] . " " . str_replace(array("[downloaded]","[limit]"), array($download_limit_check,$user_dl_limit), $lang['download_limit_summary']);
        if (getval("ajax", "") != "") {
            error_alert(escape($errormessage), true, 200);
        } else {
            include "../include/header.php";
            $onload_message = array("title" => $lang["error"],"text" => $errormessage);
            include "../include/footer.php";
        }
        exit();
    }
}

if (count($available_sizes) === 0 && $count_data_only_types === 0) {
    error_alert($lang["nodownloadcollection"], false);
    exit();
}

$used_resources = array();
$subbed_original_resources = array();
if ($submitted & !$totalsizeerror) {
    if ($exiftool_write && !$force_exiftool_write_metadata && !$tar_selected) {
        $exiftool_write_option = getval('write_metadata_on_download', '') == "yes";
    }
    $id = uniqid("Col" . $collection);
    $collection_download_data = [
        'archiver'                  => $archiver,
        'collection'                => $collection,
        'collectiondata'            => $collectiondata,
        'collection_resources'      => $result,
        'size'                      => $size,
        'exiftool_write_option'     => $exiftool_write_option,
        'useoriginal'               => $useoriginal,
        'id'                        => $id,
        'includetext'               => $includetext,
        'text'                      => $text ?? "",
        'count_data_only_types'     => $count_data_only_types,
        'usage'                     => $usage,
        'usagecomment'              => str_replace(array('\r','\n'), " ", $usagecomment),
        'settings_id'               => $settings_id,
        'include_csv_file'          => $include_csv_file,
        'include_alternatives'      => $include_alternatives,
        'collection_download_tar'   => $tar_selected,
        'k'                         => $k,
    ];

    // tar files are not an option with offline jobs
    if ($offline_job_queue && !$collection_download_data['collection_download_tar']) {
        // Only need to store resource IDS, not full search data
        $collection_download_data["result"] = array_column($result, "ref", "ref");

        $modified_job_data = hook("collection_download_modify_job", "", [$collection_download_data]);
        if (is_array($modified_job_data)) {
            $collection_download_data = $modified_job_data;
        }
        job_queue_add(
            'collection_download',
            $collection_download_data,
            '',
            '',
            $lang["oj-collection-download-success-text"],
            $lang["oj-collection-download-failure-text"],
            '',
            JOB_PRIORITY_USER
        );
        $job_created = true;
        $onload_message = [
            "title" => $lang['collection_download'],
            "text" => $lang['jq_notify_user_preparing_archive'],
        ];

    } else {
        $zipinfo = process_collection_download($collection_download_data);

        if (empty($zipinfo)) {
            error_alert(escape($lang["download_limit_collection_error"]), true, 200);
        }
        if ($zipinfo["completed"] ?? false) {
            // A tar file was requested and sent. Nothing further to do.
            collection_log($collection, LOG_CODE_COLLECTION_COLLECTION_DOWNLOADED, "", "tar - " . $size);
            exit();
        } else {
            // Get the file size of the archive.
            $filesize = filesize_unlimited($zipinfo["path"]);

            header("Content-Disposition: attachment; filename=" . $zipinfo["filename"]);
            if ($archiver) {
                header("Content-Type: " . $collection_download_settings[$settings_id]["mime"]);
            } else {
                header("Content-Type: application/zip");
            }
            if ($use_zip_extension) {
                header("Content-Transfer-Encoding: binary");
            }
            header("Content-Length: " . $filesize);

            ignore_user_abort(true); // collection download has a problem with leaving junk files when this script is aborted client side. This seems to fix that by letting the process run its course.
            set_time_limit(0);
            $sent = 0;
            $handle = fopen($zipinfo["path"], "r");
            // Now loop through the file and echo out chunks of file data
            while ($sent < $filesize) {
                echo fread($handle, $download_chunk_size);
                $sent += $download_chunk_size;
            }

            // File send complete, log to daily stat
            daily_stat('Downloaded KB', 0, floor($sent / 1024));

            // Remove archive.
            if ($use_zip_extension || $archiver) {
                $GLOBALS["use_error_exception"] = true;
                try {
                    $usertempdir = get_temp_dir(false, "rs_" . $GLOBALS["userref"] . "_" . $id);
                    rmdir($usertempdir);
                } catch (Exception $e) {
                    debug("collection_download: Attempt delete temp folder failed. Reason: {$e->getMessage()}");
                }
                unset($GLOBALS["use_error_exception"]);
            }
            collection_log($collection, LOG_CODE_COLLECTION_COLLECTION_DOWNLOADED, "", $size);
            hook('beforedownloadcollectionexit');
            exit();
        }
    }
}

include "../include/header.php";

?>
<script>
    jQuery(document).ready(function() {
        jQuery('#tardownload').on('change', function(){
            if (this.value == 'off') {
                console.log('Enabling');
                jQuery('#exiftool_question').slideDown();
                jQuery('#write_metadata_on_download').prop('disabled', false);
                jQuery('#archivesettings_question').slideDown();
                jQuery('#archivesettings').prop('disabled', false);
            } else {
                console.log('Disabling');
                jQuery('#exiftool_question').slideUp();
                jQuery('#write_metadata_on_download').prop('disabled', 'disabled');
                jQuery('#archivesettings_question').slideUp();
                jQuery('#archivesettings').prop('disabled', 'disabled');
            }
        });
    });
</script>
<div class="BasicsBox">
<?php if ($k != "") {
    $urlparams = [
        "search"    =>  "!collection" . $collection,
        "k"         =>  $k,
    ];
    ?>
    <p>
        <a href="<?php echo generateURL($baseurl_short . "pages/search.php", $urlparams); ?>" onclick="return CentralSpaceLoad(this,true);">
            <?php echo escape($lang['back'])?>
        </a>
    </p>
    <?php
} ?>

    <h1><?php echo escape($lang["downloadzip"]); ?></h1>
    <?php
    $intro = text("introtext");
    if ($intro != "") {
        ?>
        <p><?php echo strip_tags_and_attributes($intro); ?></p>
        <?php
    }
    ?>

    <form id='collection_download_form' action="<?php echo $baseurl_short; ?>pages/collection_download.php?collection=<?php echo escape($collection . (($k != '') ? '&k=' . $k : '')); ?>"  method=post>
        <?php generateFormToken("collection_download_form"); ?>
        <input type=hidden name="collection" value="<?php echo escape($collection); ?>">
        <input type=hidden name="usage" value="<?php echo escape($usage); ?>">
        <input type=hidden name="usagecomment" value="<?php echo escape($usagecomment); ?>">
        <input type=hidden name="k" value="<?php echo escape($k); ?>">
        <input type=hidden name="submitted" value="true">

        <?php
        hook("collectiondownloadmessage");

        if ($count_data_only_types !== count($result)) { ?>
            <div class="Question">
                <label for="downloadsize"><?php echo strip_tags_and_attributes($lang["downloadsize"], array('a'), array('href', 'target')); ?></label>
                <div class="tickset">
                    <?php
                    $maxaccess = collection_max_access($collection);
                    $sizes = get_all_image_sizes(false, $maxaccess >= 1);
                    $available_sizes = array_reverse($available_sizes, true);

                    // Analyze available sizes and present options
                    ?>
                    <select name="size" class="stdwidth" id="downloadsize">
                        <?php
                        if (array_key_exists('original', $available_sizes)) {
                            display_size_option('original', $lang['original'], true, $size == 'original');
                        }

                        display_size_option('largest', $lang['imagesize-largest'], true, $size == 'largest');

                        foreach ($available_sizes as $key => $value) {
                            foreach ($sizes as $size_row) {
                                if ($size_row['id'] == $key) {
                                    display_size_option($key, $size_row['name'], true, $key == $size);
                                    break;
                                }
                            }
                        }
                        ?>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <div class="clearerleft"></div>
            </div>
            <?php
        }
        if (
            !hook('replaceuseoriginal')
            && $count_data_only_types !== count($result)
        ) {
            ?>
            <div class="Question">
                <label for="use_original"><?php echo escape($lang['use_original_if_size']); ?>
                    <br />
                    <?php display_size_option('original', $lang['original'], false); ?>
                </label>
                <input type=checkbox
                    id="use_original"
                    name="use_original"
                    value="yes"
                    <?php if ($useoriginal) {
                        echo "checked";
                    } ?>
                >
                <div class="clearerleft"></div>
            </div>
            <?php
        }

        if ($zipped_collection_textfile) {
            ?>
            <div class="Question">
                <label for="includetext"><?php echo escape($lang["zippedcollectiontextfile"]); ?></label>
                <select name="includetext" class="shrtwidth" id="includetext"<?php echo (!empty($submitted)) ? ' disabled="disabled"' : ''; ?>>
                    <?php if ($zipped_collection_textfile_default_no) { ?>
                        <option value="false"><?php echo escape($lang["no"]); ?></option>
                        <option value="true"><?php echo escape($lang["yes"]); ?></option>
                    <?php } else { ?>
                        <option value="true"><?php echo escape($lang["yes"]); ?></option>
                        <option value="false"><?php echo escape($lang["no"]); ?></option>
                    <?php } ?>
                </select>
                <div class="clearerleft"></div>
            </div>
            <?php
        }
        ?>
        <!-- Add CSV file with the metadata of all the resources found in this collection -->
        <div class="Question">
            <label for="include_csv_file"><?php echo escape($lang['csvAddMetadataCSVToArchive']); ?></label>
            <input type="checkbox"
                id="include_csv_file"
                name="include_csv_file"
                value="yes"
                <?php if ($include_csv_file) {
                    echo "checked";
                } ?>
            >
            <div class="clearerleft"></div>
        </div>
        <!-- Alternatives? -->
        <div class="Question">
            <label for="include_alternatives"><?php echo escape($lang['collection_download_include_alternatives']); ?></label>
            <input type="checkbox"
                id="include_alternatives"
                name="include_alternatives"
                value="yes"
                <?php if ($include_alternatives) {
                    echo "checked";
                } ?>
            >
            <div class="clearerleft"></div>
        </div>

        <?php if ($exiftool_write && !$force_exiftool_write_metadata) { ?>
            <!-- Let user say (if allowed - ie. not enforced by system admin) whether metadata should be written to the file or not -->
            <div class="Question" id="exiftool_question" <?php echo $collection_download_tar_option ? "style=\"display:none;\"" : ''; ?>>
                <label for="write_metadata_on_download"><?php echo escape($lang['collection_download__write_metadata_on_download_label']); ?></label>
                <input type="checkbox"
                    id="write_metadata_on_download"
                    name="write_metadata_on_download"
                    value="yes"
                    <?php if (getval('write_metadata_on_download', '') !== '') {
                        echo "checked";
                    } ?>
                >
                <div class="clearerleft"></div>
            </div>
            <?php
        }

        if ($download_usage_email) {
            ?>
            <input type="hidden" name="email" value="<?php echo escape($email); ?>"/>
            <?php
        }

        // Legacy archiver settings
        if ($archiver && count($collection_download_settings) > 1) { ?>
            <div class="Question" id="archivesettings_question"
            <?php if ($collection_download_tar_option) {
                echo "style=\"display:none\"";
            } ?>
            >
                <label for="archivesettings"><?php echo escape($lang["archivesettings"]); ?></label>
                <div class="tickset">
                    <select name="settings" class="stdwidth" id="archivesettings" <?php
                    if ($submitted) {
                        echo ' disabled="disabled"';
                    } ?>
                    > <?php
                    foreach ($collection_download_settings as $key => $value) { ?>
                        <option value="<?php echo escape($key); ?>"><?php echo escape(lang_or_i18n_get_translated($value["name"], "archive-")); ?>
                        </option><?php
                    } ?>
                    </select>
                </div>
                <div class="clearerleft"></div>
            </div> <?php
        } ?>

        <!-- Tar file download option -->
        <div class="Question" <?php echo (!$show_download_format_selector) ? "style=\"display:none;\"" : ''; ?>>
            <label for="tardownload"><?php echo escape($lang["collection_download_format"]); ?></label>
            <div class="tickset">
                <select name="tardownload" class="stdwidth" id="tardownload" >
                    <option value="off" <?php echo (!$tar_selected) ? " selected" : ''; ?>>
                        <?php echo escape($lang["collection_download_no_tar"]); ?>
                    </option>
                    <option value="on" <?php echo ($tar_selected) ? " selected" : ''; ?>>
                        <?php echo escape($lang["collection_download_use_tar"]); ?>
                    </option>
                </select>
            
                <div class="clearerleft"></div>
            </div>
            <br />
            <div class="clearerleft"></div>
            <label for="tarinfo"></label>
            <div class="FormHelpInner tickset">
                <?php echo escape($lang["collection_download_tar_info"])  . "<br />" . strip_tags_and_attributes($lang["collection_download_tar_applink"], array('a'), array('href', 'target')); ?>
            </div>
            <div class="clearerleft"></div>
        </div>

           
        <div class="QuestionSubmit" id="downloadbuttondiv"> 
            <label for="download"> </label>
            <input
                type="submit"
                value="<?php echo escape($lang["action-download"]); ?>"
                <?php if ($job_created ?? false) {
                    echo "disabled";
                } ?>
            />
            <div class="clearerleft"></div>
        </div>

    </form>

</div>
<?php
include "../include/footer.php";