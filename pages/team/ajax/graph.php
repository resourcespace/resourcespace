<?php
#
# ResourceSpace Analytics - draw a graph
#
include '../../../include/boot.php';
include '../../../include/authenticate.php';
include '../../../include/reporting_functions.php';
include '../../../include/dash_functions.php';

$report = getval("report", "");
$activity_type = getval("activity_type", "");
$resource_type = getval("resource_type", "");
$period = getval("period", $reporting_periods_default[1]);
$period_init = $period;

if ($period == 0) {
    # Specific number of days specified.
    $period = getval("period_days", "");
    if (!is_numeric($period) || $period < 1) {
        $period = 1;
    } # Invalid period specified.
}

if ($period == -1) {
    # Specific date range specified.
    $from_y = getval("from-y", "");
    $from_m = getval("from-m", "");
    $from_d = getval("from-d", "");

    $to_y = getval("to-y", "");
    $to_m = getval("to-m", "");
    $to_d = getval("to-d", "");
} else {
    # Work out the from and to range based on the provided period in days.
    $start = time() - (60 * 60 * 24 * $period);

    $from_y = date("Y", $start);
    $from_m = date("m", $start);
    $from_d = date("d", $start);

    $to_y = date("Y");
    $to_m = date("m");
    $to_d = date("d");
}

$groups = getval("groups", "");
$collection = getval("collection", "");
$external = getval("external", "0");
$n = getval("n", "");
$type = getval("type", "");
$from_dash = getval("from_dash", "") != "";
$print = (bool) getval("print", false);

# Rendering a tile? Set "n" or the graph sequence number to the tile number, so all graph IDs are unique on the dash page.
$tile = getval("tile", "");
$user_tile = getval("user_tile", 0, true);

$available_tiles = get_user_available_tiles($userref);
if (
    !checkperm('t') 
    && (
        (count($available_tiles) > 0 
        && !in_array($tile, array_column($available_tiles, 'ref'))) 
        || count($available_tiles) == 0
    )
) {
    exit(escape($lang['error-permissiondenied']));
}

if ($tile != "") {
    $n = $tile;
}

$condition = "where
activity_type=? and
(
d.year>?
or
(d.year=? and d.month>?)
or
(d.year=? and d.month=? and d.day>=?)
)
and
(
d.year<?
or
(d.year=? and d.month<?)
or
(d.year=? and d.month=? and d.day<=?)
)";
$params = array("s",$activity_type,"s",$from_y,"s",$from_y,"s",$from_m,"s",$from_y,"s",$from_m,"s",$from_d,"s",$to_y,"s",$to_y,"s",$to_m,"s",$to_y,"s",$to_m,"s",$to_d);

if ($groups != "") {
    $groups_explode = explode(",", $groups);
    $condition .= " and d.usergroup in (" . ps_param_insert(count($groups_explode)) . ")";
    $params = array_merge($params, ps_param_fill($groups_explode, "i"));
}

// Activity types in table daily_stat where object_ref refers to a resource ID
$resource_activity_types = array(
    'Add resource to collection',
    'Create resource',
    'Removed resource from collection',
    'Resource download',
    'Resource edit',
    'Resource upload',
    'Resource view');

// Add extra SQL condition if filtering by resource type
if ($resource_type != "" && in_array($activity_type, $resource_activity_types)) {
    $condition .= " and d.object_ref in (select resource.ref from resource join resource_type where resource.resource_type=resource_type.ref and resource_type.ref=?)";
    $params[] = "i";
    $params[] = $resource_type;
}

$join = "";
# Using a subquery has proven to be faster for collection limitation (at least with MySQL 5.5 and MyISAM)... left the original join method here in case that proves to be faster with MySQL 5.6 and/or a switch to InnoDB.
#if ($collection!="") {$join.=" join collection_resource cr on cr.collection='$collection' and d.object_ref=cr.resource ";}
if ($collection != "") {
    $condition .= " and d.object_ref in (select cr.resource from collection_resource cr where cr.collection=?)";
    $params[] = "i";
    $params[] = $collection;
}

# External conditions
# 0 = external shares are ignored
# 1 = external shares are combined with the user group of the sharing user
# 2 = external shares are reported as a separate user group
if ($external == 0) {
    $condition .= " and external=0";
}

$css_color_scheme = $_COOKIE['css_color_scheme'] ?? 'light';
$graph_dark_mode = false;

if (
    isset($user_pref_appearance)
    && (
        $user_pref_appearance == "dark" ||
        ($user_pref_appearance == "device" && $css_color_scheme == "dark")
    )
) {
    $graph_dark_mode = true;
}

if (!$from_dash) {
    $title = get_translated_activity_type($activity_type);
    if (isset($lang["report-graph-by-" . $type])) {
        $title .= " " . $lang["report-graph-by-" . $type];
    }
    ?>
    <h2>
        <?php
        echo escape($title);
        # Add to dash tile function
        if ($report !== "") {
            $graph_params = [
                'activity_type' => $activity_type,
                'groups'        => $groups,
                'period'        => getval('period', ''),
                'period_days'   => getval('period_days', ''),
                'collection'    => $collection,
                'external'      => $external,
                'type'          => $type,
                'resource_type' => $resource_type,
                'from_dash'     => true,
                'from-y'        => $from_y,
                'from-m'        => $from_m,
                'from-d'        => $from_d,
                'to_y'          => $to_y,
                'to_m'          => $to_m,
                'to_d'          => $to_d,
            ];
            $graph_url       = generateURL('pages/team/ajax/graph.php', ['tltype' => 'conf', 'tlstyle' => 'analytics'], $graph_params);
            $add_to_dash_url = generateURL(
                    $baseurl_short . 'pages/dash_tile.php',
                    [
                        'create'         => true,
                        'title'          => $title,
                        'nostyleoptions' => true,
                        'link'           => $report !== '' ? generateURL('pages/team/team_analytics_edit.php', ['ref' => $report]) : '',
                        'url'            => $graph_url
                    ]
                ); 
            ?>
            &nbsp;&nbsp;
            <a
                style="white-space:nowrap;"
                class="ReportAddToDash"
                href="<?php echo $add_to_dash_url; ?>"
                onClick="return CentralSpaceLoad(this,true);">
                <i aria-hidden="true" class="icon-square-plus"></i>&nbsp;<?php echo  escape($lang["report_add_to_dash"]) ?>
            </a>
        <?php 
        }
        ?>
    </h2>
    <?php
} else {
    # Dash
    # Load title
    $title = getval("tltitle", ps_value("select title value from dash_tile where ref=?", array("i",$tile), ""));
    ?>
    <div style="padding:10px 15px">
        <h2 style="font-size:120%;margin:0;padding:<?php echo $from_dash ? "0" : "0 0 8px 0"; ?>;background:none;white-space: nowrap;overflow: hidden;
    text-overflow: ellipsis;"><?php echo escape($title) ?></h2>
    <?php
}

if ($type != "summary") {
    $id = "placeholder" . $type . $n;
    ?><!-- Start chart canvas -->
    <div
        <?php if ($from_dash) { ?>
            style="width:220px;height:105px;"
        <?php } elseif ($print) { ?>
            style="width:50%;height:40%;"
        <?php } else { ?>
            style="width:100%;height:80%;"
        <?php } ?>>
        <?php if ($type == 'line') { ?>
            <script>
                const chartstyling<?php echo escape($id)?> = {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false,
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit:'day',
                                displayFormats :{
                                    day: 'dd-MM-YYY',
                                }
                            },
                            unit: 'seconds',
                            ticks: {
                                <?php if ($from_dash) { ?>
                                    display: false,
                                <?php } else { ?>
                                    color: '<?php echo $graph_dark_mode ? "white": "default"; ?>',
                                <?php } ?>
                            },
                            grid: {
                                color: '<?php echo $from_dash ? "#00000033" : ($graph_dark_mode ? "dimgray": "lightgray"); ?>'
                            }
                        },
                        y: {
                            ticks: {color: '<?php echo $from_dash ? '#FFFFFF' : ($graph_dark_mode ? "white": "default"); ?>',},
                            grid: {
                                color: '<?php echo $from_dash ? "#00000033" : ($graph_dark_mode ? "dimgray": "lightgray"); ?>'
                            }
                        }
                    }
                };
            </script>
        <?php } else { ?>
            <script>
                const chartstyling<?php echo escape($id)?> = {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let value = context.raw;
                                    let sum = context.dataset.data.reduce(function(s,a){return s+a;},0);

                                    let label = Math.round(value/sum*100) + "% (" + value + ")";
                                    return label;
                                }
                            }
                        }
                    },
                };
            </script>
        <?php } ?>

    <canvas 
        id="<?php echo escape($id) ?>"
        style="margin-left:auto; margin-right:auto; display:block;
            <?php if ($from_dash) { ?>
                width:220px;height:105px;
            <?php } else { ?>
                width:100%;height:80%;
            <?php } ?>"
    ></canvas>
    <?php
}

if ($type == "pie") {
    if ($activity_type == "Keyword usage" || $activity_type == "Keyword added to resource") {
        $join_table = "keyword";
        $join_display = "keyword";
    }
    if ($activity_type == "User session") {
        $join_table = "user";
        $join_display = "fullname";
    }

    $data = ps_query("select d.object_ref,j." . $join_display . " name,sum(count) c from daily_stat d join $join_table j on d.object_ref=j.ref $join $condition group by object_ref,j." . $join_display . " order by c desc limit 50", $params); // Note only params in $condition need to be prepared statement parameters - the rest are hardcoded above.

    # Work out total so we can add an "other" block.
    $total = ps_value("select sum(count) value from daily_stat d $join $condition", $params, 0);
    if (count($data) == 0) {
        ?>
        <p><?php echo escape($lang["report_no_data"]) ?></p>
        <script>jQuery("#placeholder<?php echo escape($type . $n) ?>").hide();</script>
        <?php
        exit();
    }

    render_pie_graph($id, $data, $total);
}

if ($type == "piegroup") {
    # External conditions
    # 0 = external shares are ignored
    # 1 = external shares are combined with the user group of the sharing user
    # 2 = external shares are reported as a separate user group

    # External mode 2 support - return the usergroup as '-1' if externally shared
    $usergroup_resolve = "d.usergroup";
    $name_resolve = "ug.name";

    if ($external == 2) {
        $usergroup_resolve = "if(d.external=0,d.usergroup,-1)";
        $name_resolve = "if(d.external=0,ug.name,'" . $lang["report_external_share"] . "')";
    }

    $data = ps_query("select $usergroup_resolve as usergroup,$name_resolve as `name`,sum(count) c from daily_stat d left outer join usergroup ug on d.usergroup=ug.ref $join $condition group by $usergroup_resolve, $name_resolve order by c desc", $params);

    if (count($data) == 0) {
        ?>
        <p><?php echo escape($lang["report_no_data"]) ?></p>
        <script>jQuery("#placeholder<?php echo escape($type . $n) ?>").hide();</script>
        <?php
        exit();
    }
    render_pie_graph($id, $data);
}

if ($type == "pieresourcetype") {
    // Pie chart to break down resource activities by type

    $data = ps_query("
    SELECT
        ret.name as name,
        sum(count) c
    FROM
        daily_stat d
    JOIN
        resource res on d.object_ref=res.ref
    JOIN
        resource_type ret ON res.resource_type=ret.ref
        $join $condition
    GROUP BY
        ret.name
    ORDER BY
        c desc", $params);

    // No data found
    if (count($data) == 0) {
        ?>
        <p class='analytics-nodata'><?php echo escape($lang["report_no_data"]) ?></p>
        <script>jQuery("#placeholder<?php echo escape($type . $n) ?>").hide();</script>
        <?php
        exit();
    }

    render_pie_graph($id, $data);
}

if ($type == "line") {
    $data = ps_query("select unix_timestamp(concat(year,'-',month,'-',day))*1000 t,sum(count) c from daily_stat d $join $condition group by year,month,day order by t", $params);
    if (count($data) == 0) {
        ?>
        <p><?php echo escape($lang["report_no_data"]) ?></p>
        <script>jQuery("#placeholder<?php echo escape($type . $n) ?>").hide();</script>
        <?php
        exit();
    }

    # Find zero days and fill in the gaps

    $day_ms = (60 * 60 * 24 * 1000); # One day in milliseconds.
    $last_t = (strtotime($from_y . "-" . $from_m . "-" . $from_d) * 1000) - $day_ms;
    $newdata = array();

    foreach ($data as $row) {
        if ($row["t"] > 0) {
            if ($last_t != 0 && ($row["t"] - $last_t) > $day_ms) {
                for ($m = $last_t + $day_ms; $m < $row["t"]; $m += $day_ms) {
                    $newdata[(string)$m] = 0;
                }
            }
            $newdata[$row["t"]] = $row["c"];
            $last_t = $row["t"];
        }
    }
    render_bar_graph($id, $newdata);
}

if ($type == "summary") {
    # Define styles locally for dash display
    if ($from_dash) { ?>
        <style>
            .ReportSummary {background: none; color: inherit;}
            .ReportSummary td {padding: 0; display: block; border: none; color: inherit;}
            .ReportMetric {font-size: 200%; padding-left: 5px; color: inherit; background: none;}
        </style>
        <?php
    } ?>

    <table style="width:100%;" class="ReportSummary">
        <tr>
            <td>
                <?php if ($from_dash) {
                    echo "<span style=\"display:block;\">" . escape($lang["report_total"]) . "</span>";
                } else {
                    echo escape($lang["report_total"]);
                } ?>
                <span class="ReportMetric">
                    <?php echo escape(ps_value(
                        "SELECT
                            IFNULL(format(sum(count),0),0) `value`
                        FROM
                            daily_stat d $join $condition",
                        $params,
                        0
                    )); ?>
                </span>
            </td>
            <td>
                <?php if ($from_dash) {
                    echo "<span style=\"display:block;\">" . escape($lang["report_average"]) . "</span>";
                } else {
                    echo escape($lang["report_average"]);
                } ?>
                <span class="ReportMetric">
                    <?php echo escape(ps_value(
                        "SELECT
                            IFNULL(format(avg(c),1),0) `value`
                        FROM
                            (SELECT
                                year,month,day,sum(count) c
                            FROM
                                daily_stat d
                            $join
                            $condition
                            GROUP BY year,month,day) intable",
                        $params,
                        0
                    )); ?>
                </span>
            </td>
        </tr>
    </table>
    <?php
}

if ($from_dash) {
    if ($tile > 0) {
        # Update $tile and $usertile for generate_dash_tile_toolbar purposes
        $usertile = get_user_tile($user_tile, $userref);
        $tile = get_tile($tile);
        $tile["no_edit"] = true;
        $tile_id = (is_array($usertile)) ? "contents_user_tile" . $usertile["ref"] : "contents_tile" . $tile["ref"];
        generate_dash_tile_toolbar($tile, $tile_id);
    }
    ?>
    </div>
    <?php
}
?>
</div>
<!-- End chart canvas -->