<?php
include '../../include/boot.php';
include '../../include/authenticate.php';

if (!job_trigger_permission_check()) {
    exit("Permission denied.");
}

$job_user = getval("job_user", 0, true);

if ($job_user == $userref) {
    $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php", ["job_user" => $job_user]);
    $breadcrumbs = [
        ['title' => $userfullname == "" ? $username : $userfullname, 'href' => "{$baseurl_short}pages/user/user_home.php", 'menu' => true],
        ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
        ['title' => $lang["job_configure"] . ": " . $lang["job_list_exif_data"]]
    ];
} else {
    $parent_page = "{$baseurl_short}pages/manage_jobs.php";
    $breadcrumbs = [
        ['title' => $lang['systemsetup'],       'href' => "{$baseurl_short}pages/admin/admin_home.php", 'menu' => true],
        ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
        ['title' => $lang["job_configure"] . ": " . $lang["job_list_exif_data"]]
    ];
}

// Process form input
$save               = getval('save', '');
$field_refs         = getval('field_refs', '');
$collection_refs    = getval('collection_refs', '');
$blanks             = (bool) getval('blanks', false);
$overwrite          = (bool) getval('overwrite', false);

$job_add_error = false;

if ($save != '' && enforcePostRequest(false)) {
    
    $valid_data = true;   

    if ($field_refs == '') {
        $field_errors['field_refs'][] = escape($lang["oj_common_error_required"]);
        $valid_data = false;
    }

    $processed_field_refs = parse_csv_to_list_of_type($field_refs, "is_positive_int_loose");

    if (empty($processed_field_refs)) {
        $field_errors['field_refs'][] = escape($lang["oj_common_error_invalid"]);
        $valid_data = false;
    }
    
    // Check if each value exists as a field
    foreach ($processed_field_refs as $field_ref) {

        $fieldref_info = get_resource_type_field($field_ref);

        if (!$fieldref_info) {
            $field_errors['field_refs'][] = escape(str_replace("%FIELD_REF%", $field_ref, $lang["oj_common_error_invalid_field_ref"]));
            $valid_data = false;
        }
    }

    $processed_collection_refs = parse_int_ranges($collection_refs, 0, true);
    
    if (!empty($processed_collection_refs['errors'])) {
        $field_errors['collection_refs'] = $processed_collection_refs['errors'];
        $valid_data = false;
    }

    if ($valid_data) {
        $job_data = [
            "field_refs" => implode(",", $processed_field_refs),
            "collection_refs" => $collection_refs,
            "blanks" => $blanks,
            "overwrite" => $overwrite,
        ];

        // Create the job
        $job_added = job_queue_add("update_exiftool_field", $job_data);
        
        if (!is_int_loose($job_added)) {
            $job_add_error = true;
        } else {
            // Log that job has been added to user activity log, and then redirect
            log_activity("Added update_exiftool_field job $job_added", 
                            LOG_CODE_JOB_ADDED,
                            json_encode($job_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'job_queue', 'job_data', $job_added, null, "", null, true);
            redirect($parent_page);
        }
    }
}

include '../../include/header.php';

if ($job_add_error) {
    toast_notification(ToastNotificationType::Error, $job_added);
}

?>

<div class='BasicsBox'>
    <h1>
        <?php
        echo escape($lang["job_configure"] . ": " . $lang["job_list_exif_data"]);
        render_help_link('user/manage_jobs'); 
        ?>
    </h1>

    <?php renderBreadcrumbs($breadcrumbs); ?>

    <form method="post" id="update_exiftool_field_form" action="<?php echo generateURL("{$baseurl_short}pages/offline_jobs/update_exiftool_field.php", ["job_user" => $job_user]); ?>">
        <?php generateFormToken("update_exiftool_field_form"); ?>
        <div class="Question<?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('field_refs', $field_errors)) {
                    echo " SaveError";
                } ?>">
            <label for="field_refs"><?php echo escape($lang["oj_common_field_refs"]); ?> *</label>
            <input type="text" name="field_refs" class="stdwidth" value="<?php echo escape($field_refs); ?>">
            <?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('field_refs', $field_errors)) {
                    foreach ($field_errors['field_refs'] as $error_message) {
                        echo '<span class="job-configure-error"><i class="icon-triangle-alert"></i> ' . escape($error_message) . '</span>';
                    }                    
                } 
            ?>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_exif_data_field_refs_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="Question<?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('collection_refs', $field_errors)) {
                    echo " SaveError";
                } ?>">
            <label for="collection_refs"><?php echo escape($lang["oj_common_collection_refs"]); ?></label>
            <input type="text" name="collection_refs" class="stdwidth" value="<?php echo escape($collection_refs); ?>">
            <?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('collection_refs', $field_errors)) {
                    foreach ($field_errors['collection_refs'] as $error_message) {
                        echo '<span class="job-configure-error"><i class="icon-triangle-alert"></i> ' . escape($error_message) . '</span>';
                    }                    
                } 
            ?>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_exif_data_collection_refs_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="Question">
            <label for="blanks"><?php echo escape($lang["oj_exif_data_blanks"]); ?></label>
            <input name="blanks" id="blanks" type="checkbox" value="1"<?php echo ($blanks ? 'checked="checked"' : ''); ?>>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_exif_data_blanks_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
            <div class="Question">
            <label for="overwrite"><?php echo escape($lang["oj_exif_data_overwrite"]); ?></label>
            <input name="overwrite" id="overwrite" type="checkbox" value="1"<?php echo ($overwrite ? 'checked="checked"' : ''); ?>>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_exif_data_overwrite_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="QuestionSubmit">
            <label for="save"></label>
            <input type="submit" name="save" value="<?php echo escape($lang["oj_common_create_job"]); ?>" />
        </div>
    </form>
</div>

<?php
include '../../include/footer.php';