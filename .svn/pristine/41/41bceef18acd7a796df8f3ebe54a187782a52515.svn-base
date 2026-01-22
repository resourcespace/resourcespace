<?php

/**
 * Report creation page (part of Team Center)
 */

include "../../include/boot.php";

// Unsubscribe bypasses t permission so anon access needs to be disabled to ensure user logs in
unset($anonymous_login);
include "../../include/authenticate.php";

if (getval('unsubscribe', '') == '' && !checkperm("t")) {
    exit("Permission denied.");
}

include "../../include/reporting_functions.php";

set_time_limit(0);
$report = getval("report", "");
$show_date_field = true;

if ($report != "") {
    $show_date_field = report_has_date_by_id($report);
}

$period = getval("period", $reporting_periods_default[0]);
$period_init = $period;
$backurl = getval('backurl', '');
$backurl_path = parse_url($backurl, PHP_URL_PATH);
$backurl_query = parse_url($backurl, PHP_URL_QUERY);

$from = getval("from", "");
$to = getval("to", "");
$output = "";

$search_params = [];
$run_report_on_search_results = false;

if ("{$baseurl_short}pages/search.php" === $backurl_path) {
    $run_report_on_search_results = true;
    parse_str($backurl_query, $search_params);
}

# Execute report.
if ($report != "" && (getval("createemail", "") == "")) {
    $download = getval("download", "") != "";
    list($from_y, $from_m, $from_d, $to_y, $to_m, $to_d) = array_values(report_process_period([
            'period' => $period,
            'period_days' => getval('period_days', ''),
            'from-y' => getval('from-y', ''),
            'from-m' => getval('from-m', ''),
            'from-d' => getval('from-d', ''),
            'to-y' => getval('to-y', ''),
            'to-m' => getval('to-m', ''),
            'to-d' => getval('to-d', ''),
        ]));
    $output = do_report($report, $from_y, $from_m, $from_d, $to_y, $to_m, $to_d, $download, false, false, $search_params);
}

include "../../include/header.php";

if (getval('createemail', '') != '' && enforcePostRequest(getval("ajax", false))) {
    if ($report != "") {
        $report_receiver      = getval('report_receiver', '');
        $user_group_selection = array();

        switch ($report_receiver) {
            case 'specific_user_groups':
                $user_group_selection = getval('user_group_selection', [], false, 'is_array');
                break;
        }

        # Create a new periodic e-mail report
        create_periodic_email($userref, $report, $period, getval('email_days', 1, true), $user_group_selection, $search_params);
        ?>
        <script type="text/javascript">
            alert("<?php echo escape($lang["newemailreportcreated"]); ?>");
        </script>
        <?php
    } else {
        ?>
        <script type="text/javascript">
            alert("<?php echo escape($lang["report-select-required"]); ?>");
        </script>
        <?php
    }
}

$delete = getval('delete', '');

if ($delete != '') {
    if ('yes' == getval('delete_confirmed', '') && enforcePostRequest(getval("ajax", false))) {
        delete_periodic_report($delete);
        ?>
        <div class="BasicsBox">
            <h1><?php echo escape($lang['deleted']); ?></h1>
            <p><?php echo escape($lang['report_periodic_email_deletion_confirmed']); ?></p>
        </div>
        <?php
    } else {
        ?>
        <div class="BasicsBox">
            <h2><?php echo escape($lang['report_periodic_email_delete_title']); ?></h2>
            <form method="post" action="<?php echo $baseurl_short; ?>pages/team/team_report.php?delete=<?php echo urlencode($delete); ?>">
                <?php generateFormToken("delete_periodic_report"); ?>
                <div class="Question">
                    <label for="delete_confirmed"><?php echo escape($lang['report_periodic_email_delete_confirmation']); ?></label>
                    <input id="delete_confirmed" type="checkbox" name="delete_confirmed" value="yes" />
                    <div class="clearerleft"></div>
                </div>
                <div class="QuestionSubmit">
                    <input name="save" type="submit" value="<?php echo escape($lang['comments_submit-button-label']); ?>" />
                </div>
            </form>
        </div>
        <?php
    }

    include '../../include/footer.php';
    exit();
}

$unsubscribe = getval('unsubscribe', '');

if ($unsubscribe != '') {
    if ('yes' == getval('unsubscription_confirmed', '') && enforcePostRequest(getval("ajax", false))) {
        $unsubscribe_user = getval("unsubscribe_user", $userref, true);
        unsubscribe_user_from_periodic_report($unsubscribe_user, $unsubscribe);
        ?>
        <div class="BasicsBox">
            <h1><?php echo escape($lang["unsubscribed"]); ?></h1>
            <p><?php echo escape($lang["youhaveunsubscribedreport"]); ?></p>
        </div>
        <?php
    } else {
        ?>
        <div class="BasicsBox">
            <h2><?php echo escape($lang['report_periodic_email_unsubscribe_title']); ?></h2>
            <form method="post" action="<?php echo $baseurl_short; ?>pages/team/team_report.php?unsubscribe=<?php echo urlencode($unsubscribe); ?>">
                <?php generateFormToken("unsubscribe_user_from_periodic_report"); ?>
                <div class="Question">
                    <label for="unsubscription_confirmed"><?php echo escape($lang['report_periodic_email_unsubscribe_confirmation']); ?></label>
                    <input id="unsubscription_confirmed" type="checkbox" name="unsubscription_confirmed" value="yes" />
                    <div class="clearerleft"></div>
                </div>
                <div class="QuestionSubmit">
                    <input name="save" type="submit" value="<?php echo escape($lang['comments_submit-button-label']); ?>" />
                </div>
            </form>
        </div>
        <?php
    }
} else {
    # Normal behaviour.
    ?>
    <div class="BasicsBox">
        <h1><?php echo escape($lang['viewreports']); ?></h1>
        <?php
        if ($run_report_on_search_results) {
            $links_trail = [
                [
                    'title' => $lang['searchresults'],
                    'href'  => generateURL("{$baseurl_short}pages/search.php", $search_params),
                ],
            ];
        } elseif (mb_strpos($backurl, "pages/admin/admin_report_management.php") !== false) {
            // Arrived from Manage reports page
            $links_trail = array(
                array(
                    'title' => $lang["systemsetup"],
                    'href'  => $baseurl_short . "pages/admin/admin_home.php",
                    'menu' =>  true
                ),
                array(
                    'title' => $lang["page-title_report_management"],
                    'href'  => $baseurl_short . "pages/admin/admin_report_management.php"
                )
            );
        } else {
            $links_trail = array(
                array(
                    'title' => $lang["teamcentre"],
                    'href'  => $baseurl_short . "pages/team/team_home.php",
                    'menu' =>  true
                )
            );
        }

        $links_trail[] = ['title' => $lang['viewreports']];
        renderBreadcrumbs($links_trail);

        $reports = get_reports();
        $report_options = [];

        foreach ($reports as $report_opt) {
            // Filter out reports not valid for the context you're in:
            // - if running report on search results, then drop the ones that don't have support for non-correlated SQL
            // - if viewing reports normally (from Admin), then remove the ones that support search results
            if ($run_report_on_search_results != $report_opt['support_non_correlated_sql']) {
                continue;
            }
            $report_options[] = $report_opt;
        }
        $error = (empty($report_options) ? $lang['report_error_no_reports_supporting_search_results'] : '');
        ?>
        <p>
            <?php
            echo escape(text("introtext"));
            render_help_link('resourceadmin/reports-and-statistics');
            ?>
        </p>
        <?php render_top_page_error_style($error); ?>
        <form method="post" action="<?php echo $baseurl ?>/pages/team/team_report.php" onSubmit="if (!do_download) {return CentralSpacePost(this);}">
            <?php generateFormToken("team_report"); ?>
            <input type="hidden" name="backurl" value="<?php echo escape($backurl); ?>">
            <div class="Question">
                <script>
                    function show_hide_date() {
                        reports = document.getElementById('report');
                        selected_report = reports.options[reports.selectedIndex];
                        show_date = selected_report.dataset.contains_date;
                        if (show_date == 0) {
                            document.getElementById('date_period').style.display='none';
                        } else {
                            document.getElementById('date_period').style.display='block';
                        }
                    }
                </script>
                <label for="report"><?php echo escape($lang["viewreport"]); ?></label>
                <select id="report" name="report" class="stdwidth" onchange="show_hide_date(); update_view_as_search_results_btn(this);">
                    <option value="" selected disabled hidden><?php echo escape($lang['select']); ?></option>
                    <?php
                    foreach ($report_options as $report_opt) {
                        echo sprintf(
                            '<option value="%s" data-contains_date=%d data-view_as_search_results=%s %s>%s</option>',
                            $report_opt['ref'],
                            ($report_opt['contains_date'] ? 1 : 0),
                            (int) ($report_opt['has_thumbnail'] && !$report_opt['support_non_correlated_sql']),
                            ($report_opt['ref'] == $report ? ' selected' : ''),
                            escape($report_opt['name'])
                        );
                    }
                    ?>
                </select>

                <div class="clearerleft"></div>
            </div>

            <?php include "../../include/date_range_selector.php"; 

            if (!$show_date_field) { ?>
                <script>
                    document.getElementById('date_period').style.display='none';
                </script>
            <?php
            } ?>

            <!-- E-mail Me function -->
            <div id="EmailMe" <?php echo ($period_init == -1) ? 'style="display:none;"' : ''; ?>>
                <div class="Question">
                    <label for="email"><?php echo escape($lang['emailperiodically']); ?></label>
                    <input type="checkbox" onclick="
                        if (this.checked) {
                            document.getElementById('EmailSetup').style.display = 'block';

                            // Copy reporting period to e-mail period
                            if (document.getElementById('period').value == 0) {
                                // Copy from specific day box
                                document.getElementById('email_days').value = document.getElementById('period_days').value;
                            } else {
                                document.getElementById('email_days').value = document.getElementById('period').value;        
                            }
                        } else {
                            document.getElementById('EmailSetup').style.display = 'none';
                        }
                        ">
                    <div class="clearerleft"></div>
                </div>

                <div id="EmailSetup" style="display:none;">
                    <!-- E-mail Period select -->
                    <div class="Question">
                        <div class="Fixed" style="width: 400px;">
                            <?php
                            $textbox = "<input type=\"text\" id=\"email_days\" name=\"email_days\" size=\"4\" value=\"7\">";
                            echo str_replace("?", $textbox, escape($lang["emaileveryndays"]));
                            ?>
                            <br />
                            <br />
                            <label for="report_for_me_only">
                                <input id="report_for_me_only" type="radio" name="report_receiver" value="user_only" onClick="document.getElementById('user_group_selection').style.display = 'none';" checked /> <?php echo escape($lang['report_periodic_email_option_me']); ?>
                            </label>
                            <?php if (checkperm('m')) { ?>
                                <br />
                                <label for="selected_user_groups">
                                    <input id="selected_user_groups" type="radio" name="report_receiver" value="specific_user_groups" onClick="document.getElementById('user_group_selection').style.display = 'block';" /> <?php echo escape($lang['report_periodic_email_option_selected_user_groups']); ?>
                                </label>
                                <?php
                                render_user_group_multi_select('user_group_selection', array(), 10, 'display: none;');
                            } ?>
                            <div class="clearerleft"></div>
                            <br />
                            <input name="createemail" type="submit" onClick="do_download=true;" value="<?php echo escape($lang["create"]); ?>" />
                        </div>
                        <div class="clearerleft"></div>
                    </div>
                    <!-- End of E-mail Period Select -->
                </div><!-- End of EmailSetup -->
            </div>
            <!-- End of E-mail Me function -->

            <script language="text/javascript">
                var do_download = false;
            </script>

            <div class="QuestionSubmit" id="SubmitBlock">       
                <input name="save" type="submit" onClick="do_download=false;" value="<?php echo escape($lang["viewreport"]); ?>" />
                <input name="download" type="submit" onClick="do_download=true;" value="<?php echo escape($lang["downloadreport"]); ?>" />
                <input name="view_as_search_results"
                    class="DisplayNone"
                    onclick="return report_view_as_search_results_btn(this);"
                    type="submit"
                    value="<?php echo escape($lang['action-view_as_search_results']); ?>">
            </div>
        </form>
        <?php echo $output; ?>
    </div>
    <script>
        jQuery(function() {
            update_view_as_search_results_btn(jQuery('#report'));
        });

        function update_view_as_search_results_btn(el) {
            let report = jQuery(el).find('option:selected');
            let report_id = report.val();
            let view_as_search_results_btn = jQuery('#SubmitBlock input[name=view_as_search_results]');

            if (report.data('view_as_search_results')) {
                let period = jQuery('#period').find('option:selected').val();

                // e.g for period: p7 (last 7 days)
                let report_period_data = 'p' + period;

                if (period == 0) {
                    // e.g for period days: p0d23 (specific number of days - 23)
                    report_period_data += 'd' + jQuery('#period_days').val();
                } else if (period == -1) {
                    data_range = jQuery('#DateRange');

                    // e.g for period date range: p-1fyXXXXfmXXfdXXtyXXXXtmXXtdXX
                    report_period_data += 'fy' + data_range.find('input[name="from-y"]').val();
                    report_period_data += 'fm' + data_range.find('select[name="from-m"] option:selected').val();
                    report_period_data += 'fd' + data_range.find('select[name="from-d"] option:selected').val();

                    report_period_data += 'ty' + data_range.find('input[name="to-y"]').val();
                    report_period_data += 'tm' + data_range.find('select[name="to-m"] option:selected').val();
                    report_period_data += 'td' + data_range.find('select[name="to-d"] option:selected').val();
                }

                view_as_search_results_btn.data('url-report', {
                    search: '!report' + report_id + report_period_data,
                    archive: '<?php echo escape(implode(',', get_workflow_states())); ?>'
                });

                view_as_search_results_btn.removeClass('DisplayNone');
                return;
            }

            view_as_search_results_btn.data('url-report', {});
            view_as_search_results_btn.addClass('DisplayNone');
            return;
        }

        function report_view_as_search_results_btn(el) {
            update_view_as_search_results_btn(jQuery('#report'));
            return CentralSpaceLoad(GenerateRsUrlFromElement(el, 'pages/search.php', 'url-report'), true);
        }
    </script>
    <?php
}

include "../../include/footer.php";