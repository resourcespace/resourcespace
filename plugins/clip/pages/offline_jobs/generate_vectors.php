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
        ['title' => $lang['systemsetup'],        'href' => "{$baseurl_short}pages/admin/admin_home.php",  'menu' => true],
        ['title' => $lang['pluginmanager'],      'href' => "{$baseurl_short}pages/team/team_plugins.php", 'menu' => false],
        ['title' => $lang['clip-configuration'], 'href' => "{$baseurl_short}plugins/clip/pages/setup.php", 'menu' => false],
        ['title' => $lang["job_configure"] . ": " . $lang["clip-generate_vectors"]]
    ];
} else {
    if ($job_user == $userref) {
        $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php", ["job_user" => $job_user]);
        $breadcrumbs = [
            ['title' => $userfullname == "" ? $username : $userfullname, 'href' => "{$baseurl_short}pages/user/user_home.php", 'menu' => true],
            ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
            ['title' => $lang["job_configure"] . ": " . $lang["clip-generate_vectors"]]
        ];
    } else {
        $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php");
        $breadcrumbs = [
            ['title' => $lang['systemsetup'],       'href' => "{$baseurl_short}pages/admin/admin_home.php", 'menu' => true],
            ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
            ['title' => $lang["job_configure"] . ": " . $lang["clip-generate_vectors"]]
        ];
    }
}

// Process form input
$save               = getval('save', '');
$limit              = getval('limit', 10000);

$job_add_error = false;

if ($save != '' && enforcePostRequest(false)) {
    
    $valid_data = true;
    
    if ((int) $limit > 100000 || (int) $limit <= 0 || !is_positive_or_zero_int_loose($limit)) {
        $field_errors['limit'][] = escape($lang["clip-job_limit_error"]);
        $valid_data = false;
    }

    if ($valid_data) {
        $job_data = [
            "limit" => (int) $limit,
        ];

        // Create the job
        $job_added = job_queue_add("generate_vectors", $job_data);
        
        if (!is_int_loose($job_added)) {
            $job_add_error = true;
        } else {
            // Log that job has been added to user activity log, and then redirect
            log_activity("Added generate_vectors job $job_added", 
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
        echo escape($lang["job_configure"] . ": " . $lang["clip-generate_vectors"]);
        render_help_link('user/manage_jobs'); 
        ?>
    </h1>

    <?php renderBreadcrumbs($breadcrumbs); ?>

    <form method="post" id="generate_vectors_form" action="<?php echo generateURL("{$baseurl_short}plugins/clip/pages/offline_jobs/generate_vectors.php", ["job_user" => $job_user, "plugin" => $plugin]); ?>">
        <?php generateFormToken("generate_vectors_form"); ?>
        <div class="Question<?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('limit', $field_errors)) {
                    echo " SaveError";
                } ?>">
            <label for="limit"><?php echo escape($lang["clip-job_limit"]); ?></label>
            <input type="text" name="limit" class="stdwidth" value="<?php echo escape($limit); ?>">
            <?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('limit', $field_errors)) {
                    foreach ($field_errors['limit'] as $error_message) {
                        echo '<span class="job-configure-error"><i class="icon-triangle-alert"></i> ' . escape($error_message) . '</span>';
                    }                    
                } 
            ?>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["clip-job_limit_help"]); ?></div>
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