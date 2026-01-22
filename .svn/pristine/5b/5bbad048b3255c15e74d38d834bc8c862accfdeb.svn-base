<?php
include '../../../../include/boot.php';
include '../../../../include/authenticate.php';

if (!job_trigger_permission_check()) {
    exit("Permission denied.");
}

$job_user = getval("job_user", 0, true);
$plugin = getval("plugin", 0, true);

if ($plugin) {
    // Accessed from plugin setup page, so adjust breadcrumbs accordingly
    $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php");
    $breadcrumbs = [
        ['title' => $lang['systemsetup'],         'href' => "{$baseurl_short}pages/admin/admin_home.php",  'menu' => true],
        ['title' => $lang['pluginmanager'],       'href' => "{$baseurl_short}pages/team/team_plugins.php", 'menu' => false],
        ['title' => $lang['openai_gpt_title'], 'href' => "{$baseurl_short}plugins/openai_gpt/pages/setup.php", 'menu' => false],
        ['title' => $lang["job_configure"] . ": " . $lang["openai_gpt_process_existing"]]
    ];
} else {
    if ($job_user == $userref) {
        $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php", ["job_user" => $job_user]);
        $breadcrumbs = [
            ['title' => $userfullname == "" ? $username : $userfullname, 'href' => "{$baseurl_short}pages/user/user_home.php", 'menu' => true],
            ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
            ['title' => $lang["job_configure"] . ": " . $lang["openai_gpt_process_existing"]]
        ];
    } else {
        $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php");
        $breadcrumbs = [
            ['title' => $lang['systemsetup'],       'href' => "{$baseurl_short}pages/admin/admin_home.php", 'menu' => true],
            ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
            ['title' => $lang["job_configure"] . ": " . $lang["openai_gpt_process_existing"]]
        ];
    }
}

// Build list of GPT enabled fields
$gpt_fields = openai_gpt_get_configured_fields();

// Process form input
$save               = getval('save', '');
$field_ref          = getval('field_ref', 0, "is_positive_or_zero_int_loose");
$collection_refs    = getval('collection_refs', '');
$overwrite          = (bool) getval('overwrite', false);

$job_add_error = false;

if ($save != '' && enforcePostRequest(false)) {
    
    $valid_data = true;

    if ($field_ref == 0) {
        $field_errors['field_ref'][] = escape($lang["oj_common_error_required"]);
        $valid_data = false;
    } elseif (!in_array($field_ref, array_column($gpt_fields, "ref"))) {
        $field_errors['field_ref'][] = escape(str_replace("%FIELD_REF%", $field_ref, $lang["oj_common_error_invalid_field_ref"]));
        $valid_data = false;
    }

    $processed_collection_refs = parse_int_ranges($collection_refs, 0, true);
    
    if (!empty($processed_collection_refs['errors'])) {
        $field_errors['collection_refs'] = $processed_collection_refs['errors'];
        $valid_data = false;
    }

    if ($valid_data) {
        $job_data = [
            "field_ref" => $field_ref,
            "collection_refs" => $collection_refs,
            "overwrite" => $overwrite,
        ];

        // Create the job
        $job_added = job_queue_add("process_gpt_existing", $job_data);
        
        if (!is_int_loose($job_added)) {
            $job_add_error = true;
        } else {
            // Log that job has been added to user activity log, and then redirect
            log_activity("Added process_gpt_existing job $job_added", 
                            LOG_CODE_JOB_ADDED,
                            json_encode($job_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'job_queue', 'job_data', $job_added, null, "", null, true);
            redirect($parent_page);
        }
    }
}

include '../../../../include/header.php';

if ($job_add_error) {
    toast_notification(ToastNotificationType::Error, $job_added);
}

?>

<div class='BasicsBox'>
    <h1>
        <?php
        echo escape($lang["job_configure"] . ": " . $lang["openai_gpt_process_existing"]);
        render_help_link('user/manage_jobs'); 
        ?>
    </h1>

    <?php renderBreadcrumbs($breadcrumbs); ?>

    <form method="post" id="process_gpt_existing_form" action="<?php echo generateURL("{$baseurl_short}plugins/openai_gpt/pages/offline_jobs/process_gpt_existing.php", ["job_user" => $job_user, "plugin" => $plugin]); ?>">
        <?php generateFormToken("process_gpt_existing_form"); ?>
        <div class="Question<?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('field_ref', $field_errors)) {
                    echo " SaveError";
                } ?>">
            <label for="field_ref"><?php echo escape($lang["openai_gpt_process_existing_field_ref"]); ?> *</label>
            <select class="stdwidth" name="field_ref">
                <option value="0" <?php echo ($field_ref == 0) ? "selected" : ''; ?>></option>
                <?php
                    foreach ($gpt_fields as $gpt_field) {
                        echo "<option value=\"" . $gpt_field["ref"] . "\" " . (($field_ref == $gpt_field["ref"]) ? "selected" : '') . ">";
                        echo escape($gpt_field["ref"] . " - " . $gpt_field["title"]);
                        echo "</option>";
                    }
                ?>
            </select>
            <?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('field_ref', $field_errors)) {
                    foreach ($field_errors['field_ref'] as $error_message) {
                        echo '<span class="job-configure-error"><i class="icon-triangle-alert"></i> ' . escape($error_message) . '</span>';
                    }                    
                } 
            ?>  
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["openai_gpt_process_existing_field_ref_help"]); ?></div>
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
                <div class="FormHelpInner"><?php echo escape($lang["openai_gpt_process_existing_collection_refs_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="Question">
            <label for="overwrite"><?php echo escape($lang["openai_gpt_process_existing_overwrite"]); ?></label>
            <input name="overwrite" id="overwrite" type="checkbox" value="1"<?php echo ($overwrite ? 'checked="checked"' : ''); ?>>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["openai_gpt_process_existing_overwrite_help"]); ?></div>
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
include '../../../../include/footer.php';