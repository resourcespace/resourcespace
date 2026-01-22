<?php
include '../include/boot.php';
include '../include/authenticate.php';

if (!checkperm('a')) {
    error_alert($lang["error-permissiondenied"], false, 401);
    exit();
}

$job = getval("job", 0, true);
$job_details = job_queue_get_job($job);

if (!is_array($job_details) || count($job_details) == 0) {
    error_alert($lang['job_action_not_found'], false);
    exit();
}

$hide_data = array("failure_text","success_text");

if (!triggerable_job_check($job_details['type'])) {
    // Hide progress that comes from log file for jobs which don't create a log file
    $hide_data[] = "progress";
}

$log_file_path      = get_job_queue_log_path($job_details["type"], $job_details["job_code"], $job_details["ref"]);
$log_file_streaming = false;
$log_file_download  = false;

if (file_exists($log_file_path)) {
    if ($job_details["status"] == STATUS_INPROGRESS) {
        $log_file_streaming = true;
    } else {
        $log_file_download = true;
    }
}

?>
<div class="RecordBox">
    <div class="RecordPanel">
        <div class="RecordHeader">
            <div class="backtoresults"> 
                <a href="#" onclick="ModalClose();" class="closeLink icon-x" title="<?php echo escape($lang["close"]); ?>"></a>
            </div>
            <h1><?php echo escape($lang["job_text"] . " #" . $job_details["ref"]); ?></h1>
          </div>
    </div>

    <?php

    if ($log_file_streaming) {
    ?>
        <div class="BasicsBox">
            <h2 class="CollapsibleSectionHead collapsed" id="StreamLogSectionHead"><?php echo escape($lang["jobs_action_stream_log"]); ?></h2>
            <div class="CollapsibleSection" id="StreamLogSection" style="display: none;">
            <div id="job-details-log"></div>

            <script>
                jQuery(document).ready(function () {
                    registerCollapsibleSections(false, function(element) {
                        const id = element.id;
                        if (id === "StreamLogSection") toggleLogStreaming();
                    });

                    // Initial load with backlog
                    pollLog(true);

                    // Clear the timer if the job details modal is closed
                    jQuery('#CentralSpace').on('ModalClosed', function() {
                        window.job_details.clearTimer();
                    });
                });

                window.job_details = window.job_details || {};

                window.job_details.initTimer = function (callback, ms = 1000) {    
                    // Only start the timer if it hasn’t been created yet
                    if (window.job_details.timerId) return window.job_details.timerId;
                    window.job_details.timerId = setInterval(callback, ms);
                    return window.job_details.timerId;
                };

                window.job_details.clearTimer = function () {
                    clearInterval(window.job_details.timerId);
                    window.job_details.timerId = null;
                };

                function toggleLogStreaming() {
                    if (jQuery("#StreamLogSection").is(":visible")) {

                        // Scroll to bottom of log div
                        jQuery("#job-details-log").scrollTop(jQuery("#job-details-log")[0].scrollHeight);

                        window.job_details.initTimer(() => {
                                                pollLog(false);
                                                }, 1000);

                    } else {
                        window.job_details.clearTimer();
                    }
                }

                var offset   = 0;
                var inode    = 0;
                var polling  = false;
                var errorCnt = 0;
                var lastRotated = false;

                function appendLine(text, cls) {

                    const log = jQuery('#job-details-log');

                    jQuery('<div>')
                        .addClass('line' + (cls ? ' ' + cls : ''))
                        .text(text)
                        .appendTo(log);

                    if (log.is(":visible")) {
                        const atBottom = log[0].scrollHeight - log.scrollTop() - log.innerHeight() < 80;
                        if (atBottom) log.scrollTop(log[0].scrollHeight);
                    }
                }

                async function pollLog(initial = false) {
                    if (polling) return;
                    polling = true;

                    const params = new URLSearchParams({
                        job: <?php echo (int) $job_details["ref"]; ?>,
                        type: "stream",
                        offset: String(offset),
                        inode: String(inode),
                    });

                    if (initial) params.set('lines', '200');

                    try {
                        const res = await fetch('ajax/job_log_file.php?' + params.toString(), {
                            cache: 'no-store'
                        });

                        if (!res.ok) {
                            appendLine(`HTTP ${res.status}`, 'error');
                            window.job_details.clearTimer();
                            return;
                        }

                        const data = await res.json();

                        if (typeof data.inode === 'number') {
                            inode = data.inode;
                        }

                        if (typeof data.offset === 'number') {
                            offset = data.offset;
                        }

                        if (data.error) {
                            appendLine(`${data.error}`, 'error');
                            return;
                        }

                        if (data.rotated && !lastRotated) {
                            appendLine('Log file rotated or truncated; restarting from beginning', 'info');
                            offset = 0;
                        }

                        if (Array.isArray(data.lines)) {
                            for (const line of data.lines) {
                                appendLine(line.text, line.type);
                            }
                        }

                        if (data.done === true) {
                            appendLine(`Job status: ${data.status}; stopping updates`, 'info');
                            window.job_details.clearTimer();
                        }

                        lastRotated = !!data.rotated;
                        errorCnt = 0; // reset on success

                    } catch (e) {
                        errorCnt++;
                        appendLine(`Request failed (${errorCnt}): ${e}`, 'error');
                    } finally {
                        polling = false;
                    }
                }
            </script>
        </div>
        <?php

        } elseif ($log_file_download) {
            echo '<div class="BasicsBox">';  
            $download_url = GenerateURL($baseurl . "/pages/job_log_download.php", ['job' => (int) $job_details["ref"]]);
            echo "<a href=\"$download_url\">" . $lang["jobs_action_download_log"] . "</a><br /><br />";
        } else {
            echo '<div class="BasicsBox">';
        }


        ?>
        <script>
            jQuery(document).ready(function () {

                jQuery("#job_progress_refresh_btn").on("click", function () {

                    const $btn = jQuery(this);
                    $btn.addClass("lucide--spin");

                    var params = new URLSearchParams({
                        job: <?php echo $job_details["ref"]; ?>,
                        type: "progress"
                    });

                    jQuery.ajax({
                        url: 'ajax/job_log_file.php?' + params.toString(),
                        method: "GET",
                        success: function (response) {

                            if (response.percentage != null) {
                                jQuery("#job_progress")
                                    .empty()
                                    .append(jQuery("<strong>").text(response.percentage + "%"))
                                    .append(document.createTextNode(" " + (response.time || "")));
                            } else {
                                jQuery("#job_progress").text(response.last_line || "");
                            }
                        },
                        error: function () {
                            jQuery("#job_progress").text("<?php echo escape($lang["jobs_action_missing_job_progress"]); ?>");
                        },
                        complete: function () {
                            $btn.removeClass("lucide--spin");
                        }
                    });

                });

                jQuery("#job_progress_refresh_btn").trigger("click");

            });
        </script>
        <div class="Listview">
            <table class="ListviewStyle">
                <tr class="ListviewTitleStyle">
                    <th><?php echo escape($lang["job_data"]); ?></th>
                    <th><?php echo escape($lang["job_value"]); ?></th>
                </tr>
                <?php

                $job_status = $job_details["status"];
                $job_details["status"] = isset($lang["job_status_" . $job_status]) ? $lang["job_status_" . $job_status] : $job_status;

                $job_user = $job_details["user"];
                $job_username = $job_details["username"];
                $job_fullname = $job_details["fullname"];

                unset($job_details["user"], $job_details["username"], $job_details["fullname"]);

                $job_details["user"] = "#$job_user - $job_username";

                if ($job_fullname != "") {
                    $job_details["user"] .= " ($job_fullname)";
                }

                foreach ($job_details as $name => $value) {
                    if (in_array($name, $hide_data)) {
                        continue;
                    }

                    echo "<tr><td width='50%'>";
                    echo escape(ucwords(str_replace("_", " ", $name)));
                    echo "</td><td width='50%'>";
                    
                    if ($name == "job_data") {
                        $job_data = json_decode($value, true);
                        foreach ($job_data as $job_data_name => &$job_data_value) {
                            if (is_array($job_data_value) && count($job_data_value) > 100) {
                                $job_data_short = array();
                                $job_data_count = count($job_data_value);
                                $job_data_short[$job_data_name] = array_slice($job_data_value, 0, 10);
                                $job_data_short["(additional elements)"] = $job_data_count . " total elements";
                                $job_data_value = $job_data_short;
                            } elseif (is_string($job_data_value) && strlen($job_data_value) > 100) {
                                // If a job data element is e.g. a search result set it can be very large
                                $job_data_value = mb_strcut($job_data_value, 0, 100);
                            }
                        }
                        render_array_in_table_cells($job_data);
                    } elseif ($name == "progress") {
                        echo "<span id='job_progress'></span> <span id='job_progress_refresh_btn' class='icon-refresh-cw'></span>";
                    } elseif ($name == "priority") {
                        switch ($value) {
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
                        echo "<span class='" . $priorityicon . "' title='" . escape($prioritytitle) . "'></span>"  . escape($prioritytitle);
                    } else {
                        echo escape($value);
                    }
                    echo "</td></tr>";
                }
                ?>
            </table>
        </div>
    </div>
</div>