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
        ['title' => $lang["job_configure"] . ": " . $lang["job_list_recreate_previews"]]
    ];
} else {
    $parent_page = "{$baseurl_short}pages/manage_jobs.php";
    $breadcrumbs = [
        ['title' => $lang['systemsetup'],       'href' => "{$baseurl_short}pages/admin/admin_home.php", 'menu' => true],
        ['title' => $lang["manage_jobs_title"], 'href' => $parent_page, 'menu' => false],
        ['title' => $lang["job_configure"] . ": " . $lang["job_list_recreate_previews"]]
    ];
}

// Form input options
$image_size_options    = ['all' => 'All'] + array_column(get_all_image_sizes(true, false),'name','id');
$resource_type_options = ['all' => 'All'] + array_column(get_resource_types(),'name','ref');

// Process form input
$save            = getval('save', '');
$process_type    = getval('process_type', 'resources');
$refs            = getval('refs', '');
$image_sizes     = getval('image_sizes', array_keys($image_size_options), false, "is_array");
$resource_types  = getval('resource_types', array_keys($resource_type_options), false, "is_array");
$use_existing    = (bool) getval('use_existing', false);
$video_update    = (bool) getval('video_update', false);
$delete_existing = (bool) getval('delete_existing', false);

$job_add_error = false;

if ($save != '' && enforcePostRequest(false)) {

    $valid_data = true;

    // Validate here
    $processed_refs = parse_int_ranges($refs, 0, false, true);

    if ($refs == '') {
        $field_errors['refs'][] = escape($lang["oj_common_error_required"]);
        $valid_data = false;
    } elseif (!empty($processed_refs['errors'])) {
        $field_errors['refs'] = $processed_refs['errors'];
        $valid_data = false;
    }

    if ($delete_existing) {
        // Delete existing means various parameters should be ignored - set them to default
        $image_sizes = array_keys($image_size_options);
        $resource_types = array_keys($resource_type_options);
        $use_existing = false;
        $video_update = false;
    }

    if ($valid_data) {
        $job_data = [
            "process_type" => $process_type,
            "refs" => $refs,
            "sizes" => $image_sizes,
            "types" => $resource_types,
            "use_existing" => $use_existing,
            "video_update" => $video_update,
            "delete_existing" => $delete_existing,
        ];

        // Create the job
        $job_added = job_queue_add("recreate_previews", $job_data);
        
        if (!is_int_loose($job_added)) {
            $job_add_error = true;
        } else {
            // Log that job has been added to user activity log, and then redirect
            log_activity("Added recreate_previews job $job_added", 
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
        echo escape($lang["job_configure"] . ": " . $lang["job_list_recreate_previews"]);
        render_help_link('user/manage_jobs'); 
        ?>
    </h1>

    <?php renderBreadcrumbs($breadcrumbs); ?>

    <form method="post" id="recreate_previews_form" action="<?php echo generateURL("{$baseurl_short}pages/offline_jobs/recreate_previews.php", ["job_user" => $job_user]); ?>">
        <?php generateFormToken("recreate_previews_form"); ?>
        <div class="Question">
            <label for="process_type"><?php echo escape($lang['oj_recreate_previews_target']); ?> *</label>
            <select class="stdwidth" name="process_type">
                <option value="resources" <?php echo ($process_type == "resources") ? "selected" : ''; ?>><?php echo escape($lang["resources"]); ?></option>
                <option value="collections" <?php echo ($process_type == "collections") ? "selected" : ''; ?>><?php echo escape($lang["collections"]); ?></option>
            </select>
            <div class="clearerleft"></div>
        </div>
        <div class="Question<?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('refs', $field_errors)) {
                    echo " SaveError";
                } ?>">
            <label for="refs"><?php echo escape($lang['oj_recreate_previews_refs']); ?> *</label>
            <input type="text" name="refs" class="stdwidth" value="<?php echo escape($refs); ?>">
            <?php
                if (isset($field_errors) && is_array($field_errors) && array_key_exists('refs', $field_errors)) {
                    foreach ($field_errors['refs'] as $error_message) {
                        echo '<span class="job-configure-error"><i class="icon-triangle-alert"></i> ' . escape($error_message) . '</span>';
                    }                    
                } 
            ?>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_recreate_previews_refs_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <div class="Question">
            <label><?php echo escape($lang['oj_recreate_previews_image_sizes']); ?> *</label>
            <div class="tickset">
                <div class="Inline">
                    <?php
                    foreach ($image_size_options as $size_id => $image_size_option) {
                    ?>
                    <input type="checkbox" name="image_sizes[]" 
                            id="image_sizes_<?php echo escape($size_id); ?>"
                            <?php echo ($size_id != "all") ? 'class="image_sizes"' : ""; ?>
                            value="<?php echo escape($size_id); ?>"
                            <?php echo (in_array($size_id, $image_sizes) ? " checked " : ""); ?>/>
                    <label for="image_sizes_<?php echo escape($size_id); ?>"><?php echo escape($image_size_option); ?></label>
                    <?php
                    }
                    ?>
                </div>
            </div>
            <div class="clearerleft"></div>
            <script>
                jQuery(document).ready(function () {
                    jQuery('#image_sizes_all').on('change', function () {
                        jQuery('.image_sizes').prop('checked', this.checked);
                    });

                    jQuery('.image_sizes').on('change', function () {
                        jQuery('#image_sizes_all').prop(
                            'checked',
                            jQuery('.image_sizes:checked').length === jQuery('.image_sizes').length
                        );
                    });

                    jQuery('.image_sizes').trigger('change');
                });
            </script>
        </div>
        <div class="Question">
            <label><?php echo escape($lang['oj_recreate_previews_resource_types']); ?> *</label>
            <div class="tickset">
                <div class="Inline">
                    <?php
                    foreach ($resource_type_options as $type_id => $resource_type_option) {
                    ?>
                    <input type="checkbox" name="resource_types[]" 
                            id="resource_types_<?php echo escape($type_id); ?>"
                            <?php echo ($type_id != "all") ? 'class="resource_types"' : ""; ?>
                            value="<?php echo escape($type_id); ?>"
                            <?php echo (in_array($type_id, $resource_types) ? " checked " : ""); ?>/>
                    <label for="resource_types_<?php echo escape($type_id); ?>"><?php echo escape($resource_type_option); ?></label>
                    <?php
                    }
                    ?>
                </div>
            </div>
            <div class="clearerleft"></div>
            <script>
                jQuery(document).ready(function () {
                    jQuery('#resource_types_all').on('change', function () {
                        jQuery('.resource_types').prop('checked', this.checked);
                    });

                    jQuery('.resource_types').on('change', function () {
                        jQuery('#resource_types_all').prop(
                            'checked',
                            jQuery('.resource_types:checked').length === jQuery('.resource_types').length
                        );
                    });

                    jQuery('.resource_types').trigger('change');
                });
            </script>
        </div>
        <div class="Question">
            <label for="use_existing"><?php echo escape($lang['oj_recreate_previews_use_existing']); ?></label>
            <input name="use_existing" id="use_existing" type="checkbox" value="1"<?php echo ($use_existing ? 'checked="checked"' : ''); ?>>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_recreate_previews_use_existing_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
            <div class="Question">
            <label for="video_update"><?php echo escape($lang['oj_recreate_previews_video_update']); ?></label>
            <input name="video_update" id="video_update" type="checkbox" value="1"<?php echo ($video_update ? 'checked="checked"' : ''); ?>>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_recreate_previews_video_update_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        </div>
            <div class="Question">
            <label for="delete_existing"><?php echo escape($lang['oj_recreate_previews_delete_existing']); ?></label>
            <input name="delete_existing" id="delete_existing" type="checkbox" value="1"<?php echo ($delete_existing ? 'checked="checked"' : ''); ?>>
            <div class="FormHelp JobFormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["oj_recreate_previews_delete_existing_help"]); ?></div>
            </div>
            <div class="clearerleft"></div>
        </div>
        <script>
            jQuery(function ($) {
                const $deleteExisting = $('#delete_existing');

                // All controls that should be disabled when delete_existing is checked
                const $targets = $(
                    '#use_existing, ' +
                    '#video_update, ' +
                    '#image_sizes_all, ' +
                    '#resource_types_all, ' +
                    'input[name="image_sizes[]"], ' +
                    'input[name="resource_types[]"]'
                );

                // Grey out Question containers
                const $targetQuestions = $targets.closest('.Question');

                function refreshDeleteMode() {
                    const deleting = $deleteExisting.is(':checked');

                    // Disable when checked, enable when unchecked
                    $targets.prop('disabled', deleting);
                    $targetQuestions.toggleClass('is-disabled', deleting);
                }

                // Run when checkbox changes
                $deleteExisting.on('change', refreshDeleteMode);

                // Run once on page load
                refreshDeleteMode();
            });
        </script>
        <div class="QuestionSubmit">
            <label for="save"></label>
            <input type="submit" name="save" value="<?php echo escape($lang["oj_common_create_job"]); ?>" />
        </div>
    </form>
</div>

<?php
include '../../include/footer.php';