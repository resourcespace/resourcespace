<?php
include '../include/boot.php';
include '../include/authenticate.php';

$job_user    = getval("job_user", 0, true);

if ($job_user != $userref && !checkperm('a')) {
    // User does not have permission to see other user's jobs
    $job_user = $userref;
}

$job_status     = getval("job_status", -1, true);
$job_type       = getval("job_type", "");
$job_orderby    = getval("job_orderby", "priority");
$job_boost      = getval("job_boost", 0, true);
$job_sort       = (strtoupper(getval("job_sort", "ASC")) == "DESC") ? "DESC" : "ASC";
$job_find       = getval("job_find", "");

if (!checkperm('a') || $job_user == $userref) {
    $pagetitle  = $lang["my_jobs"];
    $breadcrumbs = [
        ['title' => $userfullname == "" ? $username : $userfullname, 'href' => "{$baseurl_short}pages/user/user_home.php", 'menu' => true],
        ['title' => $pagetitle]];
} else {
    $pagetitle  = $lang["manage_jobs_title"];
    $breadcrumbs = [
        ['title' => $lang['systemsetup'], 'href' => "{$baseurl_short}pages/admin/admin_home.php", 'menu' => true],
        ['title' => $pagetitle]];
}

$deletejob = getval("delete_job", 0, true);
$resetjob = getval("reset_job", 0, true);

$triggerjob = getval("trigger_job", "");

// Build list of offline jobs - including from plugins
$offline_job_list_full = $offline_job_list;
$offline_job_list_hook = hook('addjobtriggerpage', '', [], true);

if (!empty($offline_job_list_hook)) {
    $offline_job_list_full = array_merge($offline_job_list, $offline_job_list_hook);
}

if ($triggerjob !== "" && enforcePostRequest(true) && job_trigger_permission_check()) {

    $filtereddata = array_filter($offline_job_list_full, function ($row) use ($triggerjob) {
        return isset($row['script_name']) && $row['script_name'] === $triggerjob;
    });

    $triggerjobdata = reset($filtereddata);

    if (!empty($triggerjobdata)) {

        if ($triggerjobdata['plugin'] === null) {
            redirect(generateURL("pages/offline_jobs/{$triggerjob}.php", ['job_user' => $job_user]));
        } else {
            redirect(generateURL("plugins/" . $triggerjobdata['plugin'] . "/pages/offline_jobs/{$triggerjob}.php", ['job_user' => $job_user]));        
        }
    }
}

if ($deletejob > 0 && enforcePostRequest(true)) {
    $deletejobdetail = job_queue_get_job($deletejob);
    if (checkperm('a') || $deletejobdetail["user"] == $userref) {
        job_queue_delete($deletejob);
    }
} elseif ($resetjob > 0 && enforcePostRequest(true)) {
    $resetjobdetail = job_queue_get_job($resetjob);
    if (checkperm('a') || $resetjobdetail["user"] == $userref) {
        clear_process_lock("job_{$resetjob}");
        job_queue_update($resetjob, array(), 1, date('Y-m-d H:i:s'), get_job_type_priority($resetjobdetail["type"]));
    }
} elseif ($job_boost > 0 && enforcePostRequest(true) && checkperm('a')) {
    clear_process_lock("job_{$job_boost}");
    job_queue_update($job_boost, array(), 1, date('Y-m-d H:i:s'), JOB_PRIORITY_IMMEDIATE);
} elseif (getval("purge_jobs", '') != '' && enforcePostRequest(true) && checkperm('a')) {
    job_queue_purge(STATUS_COMPLETE);
    job_queue_purge(STATUS_ERROR);
}

$jobs = job_queue_get_jobs($job_type, $job_status, $job_user, '', $job_orderby, $job_sort, $job_find);
$endedjobs = 0;
$per_page = getval("per_page", get_per_page_cookie(), true);
$per_page = (!in_array($per_page, array_merge($list_display_array, [99999]))) ? $default_perpage_list : $per_page;
set_per_page_cookie($per_page);
$jobcount   = count($jobs);
$totalpages = ceil($jobcount / $per_page);
$offset     = getval("offset", 0, true);

if ($offset > $jobcount) {
    $offset = 0;
}

$curpage = floor($offset / $per_page) + 1;
$curparams = array(
    "job_user" => $job_user,
    "job_status" => $job_status,
    "job_type" => $job_type,
    "job_orderby" => $job_orderby,
    "job_sort" => $job_sort,
    "job_find" => $job_find
);

$url = generateURL($baseurl . "/pages/manage_jobs.php", $curparams);

$tabledata = array(
    "class" => "JobTable",
    "headers" => array(
        "ref" => array("name" => $lang["property-reference"],"sortable" => true),
        "type" => array("name" => $lang["job_queue_type"],"sortable" => true),
        "fullname" => array("name" => $lang["user"],"sortable" => true),
        "status" => array("name" => $lang["status"],"sortable" => true),
        "start_date" => array("name" => $lang["date"],"sortable" => true),
        "tools" => array("name" => $lang["tools"],"sortable" => false)
    ),

    "orderbyname" => "job_orderby",
    "orderby" => $job_orderby,
    "sortname" => "job_sort",
    "sort" => $job_sort,

    "defaulturl" => $baseurl . "/pages/manage_jobs.php",
    "params" => $curparams,
    "pager" => array("current" => $curpage,"total" => $totalpages, "per_page" => $per_page, "break" => false),
    "data" => array()
);

if (checkperm('a')) {
    $priorityheader = array("name" => $lang["job_priority"],"sortable" => true,"html" => true, "width" => "40px");
    $tabledata["headers"] = array_merge(array("priority" => $priorityheader), $tabledata["headers"]);
}

if (!checkperm('a')) {
    unset($tabledata["headers"]["fullname"]);
}

for ($n = 0; $n < $jobcount; $n++) {
    if (in_array($jobs[$n]["status"], array(STATUS_ERROR,STATUS_COMPLETE))) {
        $endedjobs++;
    }

    if ($n >= $offset && ($n < $offset + $per_page)) {
        $tablejob = array();
        $tablejob["ref"] = $jobs[$n]["ref"];
        $tablejob["type"] = $jobs[$n]["type"];

        if (checkperm('a')) {
            // Only required if can see jobs for different users
            $tablejob["fullname"] = $jobs[$n]["fullname"];

            // Add priority column
            switch ($jobs[$n]["priority"]) {
                case JOB_PRIORITY_IMMEDIATE:
                    $priorityicon = "icon-zap";
                    $prioritytitle = $lang["job_priority_immediate"];
                    break;

                case JOB_PRIORITY_USER:
                    $priorityicon = "icon-circle-arrow-up";
                    $prioritytitle = $lang["job_priority_user"];
                    break;

                case JOB_PRIORITY_SYSTEM:
                    $priorityicon = "icon-circle-arrow-right";
                    $prioritytitle = $lang["job_priority_system"];
                    break;

                case JOB_PRIORITY_COMPLETED:
                default:
                    $priorityicon = "icon-circle-arrow-down";
                    $prioritytitle = $lang["job_priority_completed"];
                    break;
            }
            $tablejob["priority"] = "<span class='" . $priorityicon . "' title='" . $prioritytitle . "'></span>";
        }

        $tablejob["status"] = isset($lang["job_status_" . $jobs[$n]["status"]]) ? $lang["job_status_" . $jobs[$n]["status"]] : $jobs[$n]["status"];
        $tablejob["start_date"] = nicedate($jobs[$n]["start_date"], true, true, true);

        if (
            $jobs[$n]["status"] == STATUS_ERROR
            || (
                !in_array($jobs[$n]["status"], array(STATUS_COMPLETE,STATUS_INPROGRESS))
                && $jobs[$n]["start_date"] < date("Y-m-d H:i:s", time() - 24 * 60 * 60)
            )
        ) {
            $tablejob["alerticon"] = "icon-triangle-alert";
            $tablejob["alerticontitle"] = $lang["job_status_error"];
        }

        $tablejob["tools"] = array();
        $tablejob["tools"][] = array(
            "icon" => "icon-trash-2",
            "text" => $lang["action-delete"],
            "url" => "#",
            "modal" => false,
            "onclick" => "update_job(\"" . $jobs[$n]["ref"] . "\",\"delete_job\");return false;"
        );

        if (checkperm('a')) {
            $tablejob["tools"][] = array(
                "icon" => "icon-info",
                "text" => $lang["job_details"],
                "url" => generateURL($baseurl . "/pages/job_details.php", array("job" => $jobs[$n]["ref"])),
                "modal" => true,
            );
            $tablejob["tools"][] = array(
                "icon" => "icon-rocket",
                "text" => $lang["job_boost"],
                "url" => "#",
                "onclick" => "update_job(\"" . $jobs[$n]["ref"] . "\",\"job_boost\");return false;",
                "modal" => true,
            );
        }

        if (checkperm('a') && $jobs[$n]["status"] != STATUS_ACTIVE) {
            $tablejob["tools"][] = array(
                "icon" => "icon-undo",
                "text" => $lang["job_reset"],
                "url" => "#",
                "modal" => false,
                "onclick" => "update_job(\"" . $jobs[$n]["ref"] . "\",\"reset_job\");return false;"
            );
        }

        $tabledata["data"][] = $tablejob;
    }
}

include '../include/header.php';
?>

<script>
    function update_job(ref, action) {
        var temp_form = document.createElement("form");
        temp_form.setAttribute("id", "jobform");
        temp_form.setAttribute("method", "post");
        temp_form.setAttribute("action", '<?php echo $url ?>');

        var i = document.createElement("input");
        i.setAttribute("type", "hidden");
        i.setAttribute("name", action);
        i.setAttribute("value", ref);
        temp_form.appendChild(i);

        <?php if ($CSRF_enabled) { ?>
            var csrf = document.createElement("input");
            csrf.setAttribute("type", "hidden");
            csrf.setAttribute("name", "<?php echo $CSRF_token_identifier; ?>");
            csrf.setAttribute("value", "<?php echo generateCSRFToken($usersession, "jobform"); ?>");
            temp_form.appendChild(csrf);
        <?php } ?>
        
        document.getElementById('job_list_container').appendChild(temp_form);
        CentralSpacePost(document.getElementById('jobform'),false);
    }
</script>

<div class='BasicsBox'>
    <h1>
        <?php
        echo escape($pagetitle);
        render_help_link('user/manage_jobs'); ?>
    </h1>

    <?php
    $introtext = text("introtext");
    if ($introtext != "") {
        echo "<p>" . escape($introtext) . "</p>";
    }

    renderBreadcrumbs($breadcrumbs);

    if (checkperm('a') && $endedjobs > 0) {
        echo "<p><a href='#' onclick='if(confirm(\"" . escape($lang["job_confirm_purge"]) . "\")){update_job(true,\"purge_jobs\");}'>" . LINK_CARET . escape($lang["jobs_action_purge_complete"]) . "</a></p>";
    }
    
    if (job_trigger_permission_check()) {
    ?>
    <form id="JobTriggerForm" method="POST" action="<?php echo $url; ?>">
        <?php generateFormToken('JobFilterForm'); ?>
        <div class="Question" id="QuestionJobTrigger">
            <label for="trigger_job"><?php echo escape($lang["job_trigger"]); ?></label>
            <select class="stdwidth" id="trigger_job" name="trigger_job">
                <?php
                foreach ($offline_job_list_full as $offline_job) {
                    
                    if (isset($offline_job['type'])) {
                        if ($offline_job['type'] == 'group_start') {
                            echo "<optgroup label=\"" . (isset($offline_job["lang_string"]) ? escape($lang[$offline_job['lang_string']]) : escape($offline_job['name'])) . "\">";
                        } else {
                            echo "</optgroup>";
                        }                        
                    } else {
                        echo "<option value=\"" .  escape($offline_job["script_name"]) . "\">" . (isset($offline_job["lang_string"]) ? escape($lang[$offline_job['lang_string']]) : escape($offline_job['name'])) . "</option>\n";
                    }                    
                }
                ?>
            </select>
            <input type="submit" id="trigger_job_button" value="<?php echo escape($lang["job_configure"]); ?>">
            <div class="clearerleft"></div> 
        </div>
    </form>
    <?php
    }
    ?>
    <form id="JobFilterForm" method="POST" action="<?php echo $url; ?>">
        <?php generateFormToken('JobFilterForm');

        $single_user_select_field_id = "job_user";
        $single_user_select_field_value = $job_user;
        ?>
        <div id="QuestionJobFilter">
            <div class="Question" id="QuestionJobType">
                <label for="job_type"><?php echo escape($lang["job_filter_type"]); ?></label>
                <select class="stdwidth" id="job_type" name="job_type">
                    <?php
                    // Not filtered by default when searching, add option to filter by month
                    echo "<option " .  ($job_type == 0 ? " selected" : "") . " value=''>" . escape($lang["all"]) . "</option>\n";
                    $alljobtypes = array_unique(array_column($jobs, "type"));
                    foreach ($alljobtypes as $avail_jobtype) {
                        echo "<option " .  ($avail_jobtype == $job_type ? " selected" : "") . " value=\"" .  escape($avail_jobtype) . "\">" . escape($avail_jobtype) . "</option>\n";
                    }
                    ?>
                </select>
                <div class="clearerleft"></div> 
            </div>

            <div class="Question" id="QuestionJobStatus">
                <label for="job_status"><?php echo escape($lang["job_filter_status"]); ?></label>
                <select class="stdwidth" id="job_status" name="job_status">
                    <?php
                    // Not filtered by default when searching, add option to filter by month
                    echo "<option " .  ($job_status == -1 ? " selected" : "") . " value='-1'>" . escape($lang["all"]) . "</option>\n";
                    foreach (array(0,1,2,3,5) as $status) {
                        echo "<option " .  ($status == $job_status ? " selected" : "") . " value=\"" .  $status . "\">" . escape($lang["job_status_" . $status]) . "</option>\n";
                    }
                    ?>
                </select>
                <div class="clearerleft"></div>   
            </div>

            <?php if (checkperm('a')) {?>
                <div class="Question" id="QuestionJobUser">
                    <label><?php echo escape($lang["job_filter_user"]); ?></label>
                    <?php include __DIR__ . "/../include/user_select.php" ?> 
                    <div class="clearerleft"></div>
                </div>
            <?php } ?>

            <div class="Question" id="QuestionJobFilterSubmit">
                <input type="button" id="filter" class="searchbutton" value="<?php echo escape($lang['filterbutton']); ?>" onclick="return CentralSpacePost(document.getElementById('JobFilterForm'));">
                <input type="button" id="clearfilter" class="searchbutton" value="<?php echo escape($lang['clearbutton']); ?>" onclick="addUser();jQuery('#job_status').val('-1');jQuery('#job_type').val('');return CentralSpacePost(document.getElementById('JobFilterForm'));">
                <div class="clearerleft"></div>
            </div>
        </div>
    </form>

    <div id='job_list_container' class='BasicsBox'>
        <?php render_table($tabledata); ?>
    </div>
</div>

<?php
include '../include/footer.php';

