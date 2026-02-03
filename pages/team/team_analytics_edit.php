<?php
#
# ResourceSpace Analytics - list my reports
#

include '../../include/boot.php';
include '../../include/authenticate.php';

$ref = getval("ref", "", true);
$print = (getval("print", "") != ""); # Print mode?

if ($ref != "" && $_SERVER['REQUEST_METHOD'] == "GET") {
    # Load a saved report
    $report = ps_query("select " . columns_in("user_report") . " from user_report where ref= ? and user= ?", ['i', $ref, 'i', $userref]);
    if (count($report) == 0) {
        exit("Report not found.");
    }
    $report = $report[0];
    $params = unserialize($report['params']);

    // Remove params that should not persist as we are just loading an existing report
    unset($params[$CSRF_token_identifier]);
    unset($params['save']);

    $_POST = $params;
}

if (!checkperm("t")) {
    exit("Permission denied.");
}

$offset = getval("offset", 0, true);
$findtext = getval("findtext", "");
$activity_type = getval("activity_type", "");

$resource_type = getval("resource_type", "");
$period = getval("period", $reporting_periods_default[1]);
$period_init = $period;
$period_days = getval("period_days", "");
$from_y = getval("from-y", "");
$from_m = getval("from-m", "");
$from_d = getval("from-d", "");
$to_y = getval("to-y", "");
$to_m = getval("to-m", "");
$to_d = getval("to-d", "");
$groupselect = getval("groupselect", "viewall");
$collection = getval("collection", "");
$external = getval("external", "");


if ($groupselect == "select" && isset($_POST["groups"]) && is_array($_POST["groups"])) {
    $groups = @$_POST["groups"];
} else {
    $groups = array();
        $groupselect = "viewall";
}

if (isset($_POST["graph_types"])) {
    $graph_types = $_POST["graph_types"];
} else {
    $graph_types = array();
}

# Save report
if (getval("name", "") != "" && getval("save", "") != "" && enforcePostRequest(getval("ajax", false))) {
    if ($ref == "") {
        # New report
        ps_query("insert into user_report(name,user) values (?, ?)", ['s', getval("name", ""), 'i', $userref]);
        $ref = sql_insert_id();
    }
    # Saving
    unset($_POST[$CSRF_token_identifier]);
    unset($_POST['save']);
    $params = serialize($_POST);
    ps_query(
        "update user_report set `name`= ?,`params`= ? where ref= ? and user= ?",
        ['s', getval("name", ""),
         's', $params,
         'i', $ref,
         'i', $userref]
    );
}

# Define a list of activity types for which "object_ref" refers to the resource ref and therefore collection filtering will work.
$resource_activity_types = array("Add resource to collection","Create resource","E-mailed resource","Print story","Removed resource from collection","Resource download","Resource edit","Resource upload","Resource view");

if ($print) {
    ?><html>
        <head>
            <style>
                a, .CollapsibleSectionHead {
                    display:none;
                }
            </style>

        <link href="<?php echo $baseurl ?>/css/global.css" rel="stylesheet" type="text/css" media="screen,projection,print" />
        <link href="<?php echo $baseurl ?>/css/light.css" rel="stylesheet" type="text/css" media="screen,projection,print" />

        <!-- Load jQuery and jQueryUI -->
        <script src="<?php echo $baseurl . $jquery_path; ?>?css_reload_key=<?php echo $css_reload_key; ?>"></script>
        <script src="<?php echo $baseurl . $jquery_ui_path?>?css_reload_key=<?php echo $css_reload_key; ?>" type="text/javascript"></script>
        <link type="text/css" href="<?php echo $baseurl?>/css/smoothness/jquery-ui.min.css?css_reload_key=<?php echo $css_reload_key?>" rel="stylesheet" />
        <script src="<?php echo $baseurl?>/lib/js/jquery.ui.touch-punch.min.js"></script>
        <script type="text/javascript" src="<?php echo $baseurl?>/lib/js/jquery.tshift.min.js"></script>
        <script type="text/javascript" src="<?php echo $baseurl?>/lib/js/jquery-periodical-updater.js"></script>

        <!-- Chart.js for graphs -->
        <script language="javascript" type="module" src="<?php echo $baseurl_short; ?>lib/js/chartjs-4-4-0.js"></script>
        <script language="javascript" type="module" src="<?php echo $baseurl_short; ?>lib/js/date-fns.js"></script>
        <script language="javascript" type="module" src="<?php echo $baseurl_short; ?>lib/js/chartjs-adapter-date-fns.js"></script>

    </head>
    <body onload="window.setTimeout('window.print();',3000);">
    <?php
} else {
    include __DIR__ . "/../../include/header.php";
}
?>

<div class="BasicsBox">
    <h1><?php echo escape($ref != "" ? $lang["edit_report"] : $lang["new_report"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["teamcentre"],
            'href'  => $baseurl_short . "pages/team/team_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["rse_analytics"],
            'href'  => $baseurl_short . "pages/team/team_analytics.php?offset=" . $offset . "&findtext=" . $findtext
        ),
        array(
            'title' => $ref != "" ? $lang["edit_report"] : $lang["new_report"]
        )
    );

    if (!$print) {
        renderBreadcrumbs($links_trail);
    }
    ?>
    <p>
        <a target="blank" href="team_analytics_edit.php?ref=<?php echo $ref ?>&print=true"><i class="icon-printer"></i> <?php echo escape($lang["print_report"]); ?></a>
    </p>

    <h1 id="ReportHeader" class="CollapsibleSectionHead <?php echo ($ref == "") ? 'expanded' : 'collapsed'; ?>">
        <?php
        echo escape($ref == "" ? $lang["new_report"] : $lang["edit_report"]);
        render_help_link('resourceadmin/analytics');
        ?>
    </h1>

<div class="CollapsibleSection" id="ReportForm" <?php echo ($ref != "") ? 'style="display:none;"' : ''; ?>>
    <form method="post" id="mainform" onsubmit="return CentralSpacePost(this);">
        <?php generateFormToken("mainform"); ?>
        <input type="hidden" name="ref" value="<?php echo $ref?>">

        <div class="Question">
            <label for="report_name"><?php echo escape($lang["report_name"]); ?></label>
            <input type="text" class="stdwidth" id="report_name" name="name" value="<?php echo escape(getval("name", isset($report["name"]) ? $report["name"] : "")) ?>"/>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="activity_type"><?php echo escape($lang["activity"]); ?></label>
            <select id="activity_type" name="activity_type" class="stdwidth">
                <option value=""><?php echo escape($lang["all_activity"]); ?></option>
                <?php
                $types = get_stats_activity_types();
                for ($n = 0; $n < count($types); $n++) {
                    if (!isset($lang["stat-" . strtolower(str_replace(" ", "", $types[$n]))])) {
                        $lang["stat-" . strtolower(str_replace(" ", "", $types[$n]))] = str_replace("[type]", $types[$n], $lang["log-missinglang"]);
                    }
                    ?>
                    <option
                        <?php echo ($activity_type == $types[$n]) ? "selected" : ''; ?>
                        value="<?php echo $types[$n]; ?>">
                        <?php echo escape($lang["stat-" . strtolower(str_replace(" ", "", $types[$n]))]); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <?php include "../../include/usergroup_select.php" ?>

        <div class="Question">
            <label for="resource_type"><?php echo escape($lang["report_resource_type"]); ?></label>
            <select id="resource_type" name="resource_type" class="stdwidth">
                <option value=""><?php echo escape($lang["all_resource_types"]); ?></option>
                <?php $resource_types = get_resource_types();
                foreach ($resource_types as $type) {
                    ?>
                    <option
                        value="<?php echo escape($type['ref']) ?>"
                        <?php if ($resource_type == $type['ref']) { ?>
                            selected
                        <?php } ?>>
                        <?php echo escape($type['name'])?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <?php include "../../include/date_range_selector.php" ?>

        <div class="Question">
            <label for="report_collection"><?php echo escape($lang["report_filter_to_collection"]); ?></label>
            <select name="collection" id="report_collection" class="stdwidth" onChange="document.getElementById('mainform').submit();">
                <option value=""><?php echo escape($lang["report_all_resources"]); ?></option>
                <option value="" disabled="disabled" style="background-color:#ccc;">--- <?php echo escape($lang["mycollections"]); ?> ---</option>
                <?php
                $list = get_user_collections($userref);
                for ($n = 0; $n < count($list); $n++) {
                    ?>
                    <option value="<?php echo escape($list[$n]["ref"]) ?>"
                        <?php if ($collection == $list[$n]["ref"]) { ?>
                            selected
                        <?php } ?>>
                        <?php echo escape($list[$n]["name"])?>
                    </option>
                    <?php
                }
                ?>
                <option value="" disabled="disabled" style="background-color:#ccc;">--- <?php echo escape($lang["themes"]); ?> ---</option>
                <?php
                $list = search_public_collections("", "name", "ASC", false, false);
                for ($n = 0; $n < count($list); $n++) {
                    ?>
                    <option value="<?php echo escape($list[$n]["ref"]) ?>"
                        <?php if ($collection == $list[$n]["ref"]) {
                            ?>selected<?php
                        } ?>>
                        <?php echo escape($list[$n]["name"])?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>
            
        <div class="Question">
            <label for="report_external"><?php echo escape($lang["report_external_options"]); ?></label>
            <select name="external" id="report_external" class="stdwidth" onChange="document.getElementById('mainform').submit();">
                <?php for ($n = 0; $n <= 2; $n++) { ?>
                    <option
                        value="<?php echo $n ?>"
                        <?php if ($n == $external) { ?>
                            selected
                        <?php } ?>>
                        <?php echo escape($lang["report_external_option" . $n]); ?>
                    </option>
                <?php } ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="graph_types"><?php echo escape($lang["report_graph_types"]); ?></label>
            <table cellpadding=2 cellspacing=0>
                <tr>
                    <td width="1">
                        <input
                            type="checkbox"
                            id="pie_check"
                            name="graph_types[]"
                            value="pie"
                            <?php if (in_array("pie", $graph_types) || count($graph_types) == 0) { ?>
                                checked
                            <?php } ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="pie_check" ><?php echo escape($lang["report_breakdown_pie"]); ?></label>
                    </td>
                    <td width="1">
                        <input
                            type="checkbox"
                            id="piegroup_check"
                            name="graph_types[]"
                            value="piegroup"
                            <?php if (in_array("piegroup", $graph_types) || count($graph_types) == 0) { ?>
                                checked
                            <?php } ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="piegroup_check" ><?php echo escape($lang["report_user_group_pie"]); ?></label>
                    </td>
                    <td width="1">
                        <input
                            type="checkbox"
                            id="pieresourcetype_check"
                            name="graph_types[]"
                            value="pieresourcetype"
                            <?php if (in_array("pieresourcetype", $graph_types) || count($graph_types) == 0) { ?>
                                checked
                            <?php } ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="pieresourcetype_check" ><?php echo escape($lang["report_resource_type_pie"]); ?></label>
                    </td>
                    <td width="1">
                        <input
                            type="checkbox"
                            id="line_check"
                            name="graph_types[]"
                            value="line"
                            <?php if (in_array("line", $graph_types) || count($graph_types) == 0) { ?>
                                checked
                            <?php } ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="line_check" ><?php echo escape($lang["report_time_line"]); ?></label>
                    </td>
                    <td width="1">
                        <input
                            type="checkbox"
                            id="summary_check"
                            name="graph_types[]"
                            value="summary"
                            <?php if (in_array("summary", $graph_types) || count($graph_types) == 0) { ?>
                                checked
                            <?php } ?>
                        />
                    </td>
                    <td>
                        <label class="customFieldLabel" for="summary_check" ><?php echo escape($lang["report_summary_block"]); ?></label>
                    </td>
                </tr>
            </table>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">
            <input type="hidden" name="save" value="save report">   
            <input name="update" type="submit" value="<?php echo escape($lang["update_report"]); ?>" />
            <input name="save" type="submit" onClick="if (document.getElementById('report_name').value=='') {alert('<?php echo addslashes($lang["report_please_enter_name"]) ?>');}" value="<?php echo escape($lang["save_report"]); ?>" />
        </div>
    </form>
</div>

<div class="ReportSheet">
    <div style="page-break-inside: avoid;">
        <?php
        # ------------------ Draw selected graphs
        $types = get_stats_activity_types();
        $counter = 0;

        for ($n = 0; $n < count($types); $n++) {
            if (($activity_type == "" || $activity_type == $types[$n]) && ($collection == "" || in_array($types[$n], $resource_activity_types))) {
                $graph_params = array(
                    "report"        => $ref,
                    "n"             => $n,
                    "activity_type" => $types[$n],
                    "groups"        => join(",", $groups),
                    "from-y"        => $from_y,
                    "from-m"        => $from_m,
                    "from-d"        => $from_d,
                    "to-y"          => $to_y,
                    "to-m"          => $to_m,
                    "to-d"          => $to_d,
                    "period"        => $period,
                    "period_days"   => $period_days,
                    "collection"    => $collection,
                    "resource_type" => $resource_type,
                    "external"      => $external,
                    "print"         => $print ?? 0
                );

                # Show the object breakdown for certain types only.
                $show_breakdown = false;
                $show_pieresourcetype = false;

                if (in_array($types[$n], array("Keyword usage","Keyword added to resource", "User session"))) {
                    $show_breakdown = true;
                }

                if (!(in_array("pie", $graph_types) || count($graph_types) == 0)) {
                    $show_breakdown = false;
                }

                $show_piegroup = (in_array("piegroup", $graph_types) || count($graph_types) == 0);
                if (in_array($types[$n], array("Add resource to collection","Create resource","Removed resource from collection","Resource download", "Resource edit","Resource upload","Resource view"))) {
                    $show_pieresourcetype = true;
                }

                if (!(in_array("pieresourcetype", $graph_types) || count($graph_types) == 0)) {
                    $show_pieresourcetype = false;
                }

                $show_line = (in_array("line", $graph_types) || count($graph_types) == 0);
                $show_summary = (in_array("summary", $graph_types) || count($graph_types) == 0);
                if ($show_breakdown) {
                    ?>
                    <div class="pie" id="pie<?php echo $n ?>" style="float:left;width:24%;height:300px;"><?php echo escape($lang["loading"]); ?></div>
                    <?php
                }

                if ($show_piegroup) {
                    ?>
                    <div class="pie" id="piegroup<?php echo $n ?>" style="float:left;width:24%;height:300px;"><?php echo escape($lang["loading"]); ?></div>
                    <?php
                }

                if ($show_pieresourcetype) {
                    ?>
                    <div class="pie" id="pieresourcetype<?php echo $n ?>" style="float:left;width:24%;height:300px;"><?php echo escape($lang["loading"]); ?></div>
                    <?php
                }

                if ($show_line) {
                    ?>
                    <div class="line" id="line<?php echo $n ?>" style="float:left;width:<?php
                    // Set width of line graph based on number of pie charts
                    $pie_counter = 0;
                    $line_width = 99;
                    if ($show_breakdown) {
                        $pie_counter++;
                    }
                    if ($show_piegroup) {
                        $pie_counter++;
                    }
                    if ($show_pieresourcetype) {
                        $pie_counter++;
                    }
                    if ($pie_counter == 1) {
                        $line_width = 75;
                    } elseif ($pie_counter == 2) {
                        $line_width = 50;
                    }
                    echo $line_width . "%";
                    ?>;height:300px;"><?php echo escape($lang["loading"]); ?></div>
                    <?php
                }
                
                if ($show_summary) {
                    ?>
                    <div id="summary<?php echo $n ?>" style="float:left;width:99%;height:100px;"><?php echo escape($lang["loading"]); ?></div>
                    <?php
                }
                
                if ($activity_type == "") {
                    ?>
                    </div>
                    <hr style="clear:both;" />
                    <div style="page-break-inside: avoid;">
                    <?php
                } ?>

                <script>
                jQuery(function () {
                    <?php if ($show_breakdown) { ?>
                        jQuery('#pie<?php echo $n ?>').load("<?php echo generateURL($baseurl_short . "pages/team/ajax/graph.php", $graph_params, ["type" => "pie"])?>");
                    <?php }
                    if ($show_piegroup) { ?>
                        jQuery('#piegroup<?php echo $n ?>').load("<?php echo generateURL($baseurl_short . "pages/team/ajax/graph.php", $graph_params, ["type" => "piegroup"])?>");
                    <?php }
                    if ($show_pieresourcetype) { ?>
                        jQuery('#pieresourcetype<?php echo $n ?>').load("<?php echo generateURL($baseurl_short . "pages/team/ajax/graph.php", $graph_params, ["type" => "pieresourcetype"])?>");
                    <?php }
                    if ($show_line) { ?>
                        jQuery('#line<?php echo $n ?>').load("<?php echo  generateURL($baseurl_short . "pages/team/ajax/graph.php", $graph_params, ["type" => "line"])?>");
                    <?php }
                    if ($show_summary) { ?>
                        jQuery('#summary<?php echo $n ?>').load("<?php echo generateURL($baseurl_short . "pages/team/ajax/graph.php", $graph_params, ["type" => "summary"]) ?>");
                    <?php } ?>
                });
                </script>

                <?php
                $counter++;
            }
        }
        if ($counter == 0) {
            echo "<p>" . escape($lang["report_no_matching_activity_types"]) . "</p>";
        }
        ?>
    </div>
    <div style="clear:both;"></div>
</div>

<script>
    registerCollapsibleSections();
</script>

</div>
<?php
if ($print) {
    ?>
    </body>
    </html>
    <?php
} else {
    include __DIR__ . "/../../include/footer.php";
}
