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
        ['title' => $lang["job_configure"] . ": " . $lang["job_list_purge_duplicates"]]
    ];
} else {
    $parent_page = "{$baseurl_short}pages/manage_jobs.php";
    $breadcrumbs = [
        ['title' => $lang['systemsetup'],       'href' => "{$baseurl_short}pages/admin/admin_home.php", 'menu' => true],
        ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
        ['title' => $lang["job_configure"] . ": " . $lang["job_list_purge_duplicates"]]
    ];
}

// Process form input
$save               = getval('save', '');
$collection_refs    = getval('collection_refs', '');
$manage_method      = getval('manage_method', 'lifo');
$dry_run            = (bool) getval('dry_run', false);
$delete_perm        = (bool) getval('delete_perm', false);


$job_add_error = false;

if ($save != '' && enforcePostRequest(false)) {
    
    $valid_data = true;

    $processed_collection_refs = parse_int_ranges($collection_refs, 0, true);
       
    if (!empty($processed_collection_refs['errors'])) {
        $field_errors['collection_refs'] = $processed_collection_refs['errors'];
        $valid_data = false;
    }

    if (!in_array($manage_method, ['lifo', 'fifo'])) {
        $field_errors['manage_method'][] = escape($lang["oj_common_error_invalid"]);
        $valid_data = false;
    }

    if ($dry_run && $delete_perm) {
        $field_errors['delete_perm'][] = escape($lang['oj_purge_duplicates_delete_error']);
        $valid_data = false;
    }

    if ($valid_data) {
        $job_data = [
            "collection_refs" => $collection_refs,
            "manage_method" => $manage_method,
            "dry_run" => $dry_run,
            "delete_perm" => $delete_perm,
        ];

        // Create the job
        $job_added = job_queue_add("purge_duplicates", $job_data);
        
        if (!is_int_loose($job_added)) {
            $job_add_error = true;
        } else {
            // Log that job has been added to user activity log, and then redirect
            log_activity("Added purge_duplicates job $job_added", 
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
        echo escape($lang["job_configure"] . ": " . $lang["job_list_purge_duplicates"]);
        render_help_link('user/manage_jobs'); 
        ?>
    </h1>

    <?php renderBreadcrumbs($breadcrumbs); ?>

    <p><?php echo strip_tags_and_attributes($lang["oj_purge_duplicates_intro"]); ?></p>

    <form method="post" id="purge_duplicates_form" action="<?php echo generateURL("{$baseurl_short}pages/offline_jobs/purge_duplicates.php", ["job_user" => $job_user]); ?>">
        <?php generateFormToken("purge_duplicates_form"); ?>
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
                <div class="FormHelpInner"><?php echo escape($lang["oj_purge_duplicates_collection_refs_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="Question">
            <label for="manage_method"><?php echo escape($lang["oj_purge_duplicates_manage_method"]); ?></label>
            <select class="stdwidth" name="manage_method">
                <option value="lifo" <?php echo ($manage_method == "lifo") ? "selected" : ''; ?>><?php echo escape($lang["oj_purge_duplicates_lifo"]); ?></option>
                <option value="fifo" <?php echo ($manage_method == "fifo") ? "selected" : ''; ?>><?php echo escape($lang["oj_purge_duplicates_fifo"]); ?></option>
            </select>
            <?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('manage_method', $field_errors)) {
                    foreach ($field_errors['manage_method'] as $error_message) {
                        echo '<span class="job-configure-error"><i class="icon-triangle-alert"></i> ' . escape($error_message) . '</span>';
                    }                    
                } 
            ?>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_purge_duplicates_manage_method_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="Question">
            <label for="dry_run"><?php echo escape($lang["oj_purge_duplicates_dry_run"]); ?></label>
            <input name="dry_run" id="dry_run" type="checkbox" value="1"<?php echo ($dry_run ? 'checked="checked"' : ''); ?>>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_purge_duplicates_dry_run_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="Question">
            <label for="delete_perm"><?php echo escape($lang["oj_purge_duplicates_delete_perm"]); ?></label>
            <input name="delete_perm" id="delete_perm" type="checkbox" value="1"<?php echo ($delete_perm ? 'checked="checked"' : ''); ?>>
            <?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('delete_perm', $field_errors)) {
                    foreach ($field_errors['delete_perm'] as $error_message) {
                        echo '<span class="job-configure-error"><i class="icon-triangle-alert"></i> ' . escape($error_message) . '</span>';
                    }                    
                } 
            ?>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_purge_duplicates_delete_perm_help"]); ?></div>
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