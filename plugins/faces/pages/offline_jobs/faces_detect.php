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
        ['title' => $lang['faces-configuration'], 'href' => "{$baseurl_short}plugins/faces/pages/setup.php", 'menu' => false],
        ['title' => $lang["job_configure"] . ": " . $lang["faces_detect_faces"]]
    ];
} else {
    if ($job_user == $userref) {
        $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php", ["job_user" => $job_user]);
        $breadcrumbs = [
            ['title' => $userfullname == "" ? $username : $userfullname, 'href' => "{$baseurl_short}pages/user/user_home.php", 'menu' => true],
            ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
            ['title' => $lang["job_configure"] . ": " . $lang["faces_detect_faces"]]
        ];
    } else {
        $parent_page = generateURL("{$baseurl_short}pages/manage_jobs.php");
        $breadcrumbs = [
            ['title' => $lang['systemsetup'],       'href' => "{$baseurl_short}pages/admin/admin_home.php", 'menu' => true],
            ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
            ['title' => $lang["job_configure"] . ": " . $lang["faces_detect_faces"]]
        ];
    }
}

// Process form input
$save = getval('save', '');

$job_add_error = false;

if ($save != '' && enforcePostRequest(false)) {
    
    $valid_data = true;

    if ($valid_data) {
        $job_data = [];

        // Create the job
        $job_added = job_queue_add("faces_detect", $job_data);
        
        if (!is_int_loose($job_added)) {
            $job_add_error = true;
        } else {
            // Log that job has been added to user activity log, and then redirect
            log_activity("Added faces_detect job $job_added", 
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
        echo escape($lang["job_configure"] . ": " . $lang["faces_detect_faces"]);
        render_help_link('user/manage_jobs'); 
        ?>
    </h1>

    <?php renderBreadcrumbs($breadcrumbs); ?>

    <p><?php echo escape( $lang["faces_detect_faces_intro"]); ?></p>

    <form method="post" id="faces_detect_form" action="<?php echo generateURL("{$baseurl_short}plugins/faces/pages/offline_jobs/faces_detect.php", ["job_user" => $job_user, "plugin" => $plugin]); ?>">
        <?php generateFormToken("faces_detect_form"); ?>
        <div class="QuestionSubmit">
            <label for="save"></label>
            <input type="submit" name="save" value="<?php echo escape($lang["oj_common_create_job"]); ?>" />
        </div>
    </form>
</div>

<?php
include '../../../../include/footer.php';