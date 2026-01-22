<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm_user_edit($userref)) {
    redirect($baseurl_short . "login.php?error=error-permissions-login&url={$baseurl_short}pages/admin/admin_system_log.php");
    exit;
}

$log_search = getval("log_search", "");
$backurl = getval("backurl", "");
$requesteduser = getval('actasuser', 0, true);
$actasuser = $requesteduser === $userref ? $userref : $requesteduser;

// Filter by a particular table and its reference
$table = getval('table', '');
$table_reference = getval('table_reference', 0, true);
$tables_data = array(
    'resource_type_field' => array(
        'display_title' => $lang['field'],
        'title_column' => 'title',
        'get_data_function' => 'get_resource_type_field',
        'get_data_function_params' => array($table_reference),
    ),
    'user' => array(
        'display_title' => $lang['user'],
        'title_column' => 'fullname',
        'get_data_function' => 'get_user',
        'get_data_function_params' => array($table_reference),
    ),
    'resource' => array(
        'display_title' => "resource",
        'title_column' => 'field' . $view_title_field,
        'get_data_function' => 'get_resource_data',
        'get_data_function_params' => array($table_reference),
    ),
    'collection' => array(
        'display_title' => $lang['collection'],
        'title_column' => 'name',
        'get_data_function' => 'get_collection',
        'get_data_function_params' => array($table_reference),
    )
);

// TODO: over time, these can be put under tables_data once we can use the referenced information (ie. if there is a function to do so - see examples above)
$no_reference_data_tables = ps_array(
    '
        SELECT DISTINCT remote_table AS "value"
          FROM activity_log
         WHERE remote_table IS NOT NULL AND remote_table <> ""
    ',
    array(),
    ""
);

if (!checkperm('a') || $requesteduser == $actasuser && $requesteduser != 0) {
    $log_tables_where_statements = array(
        'activity_log' => "`activity_log`.`user`='{$actasuser}' AND ",
        'resource_log' => "`resource_log`.`user`='{$actasuser}' AND ",
        'collection_log' => "`collection_log`.`user`='{$actasuser}' AND ",
    );
} else {
    // Admins see all user activity by default
    $log_tables_where_statements = array(
        'activity_log' => "TRUE AND ",
        'resource_log' => "TRUE AND ",
        'collection_log' => "TRUE AND ",
    );
}

// Add date restriction
$curmonth   = date('m');
$curyear    = date('Y');
$logmonth   = getval("logmonth", ($log_search != "" ? "" : $curmonth), true);
$logyear    = getval("logyear", ($log_search != "" ? "" : $curyear), true);

// Add filtering if not searching
if ($logmonth != 0 || $logyear != 0) {
    $logmonth = (int)$logmonth;
    $logyear = (int)$logyear;
    $monthstart = $logmonth == 0 ? 1 : $logmonth;
    $monthend = $logmonth == 0 ? 12 : $logmonth;
    $datevals = " BETWEEN CAST('$logyear-$monthstart-01' AS DATETIME) 
        AND CAST( CONCAT( LAST_DAY('$logyear-$monthend-01'),' 23:59:59') AS DATETIME) ";
    $log_tables_where_statements['activity_log']    .= "(logged " . $datevals . ") AND ";
    $log_tables_where_statements['resource_log']    .= "(date " . $datevals . ") AND ";
    $log_tables_where_statements['collection_log']  .= "(date " . $datevals . ") AND ";
}

// Paging functionality
$url = generateURL(
    "{$baseurl_short}pages/admin/admin_system_log.php",
    array(
        'log_search' => $log_search,
        'backurl' => $backurl,
        'actasuser' => $requesteduser,
        'table' => $table,
        'table_reference' => $table_reference,
        'logmonth' => $logmonth,
        'logyear' => $logyear,
    )
);
$offset = (int) getval('offset', 0, true);
$per_page = (int) getval('per_page_list', $default_perpage_list, true);
$all_records = get_activity_log($log_search, null, null, $log_tables_where_statements, $table, $table_reference, true);
$totalpages = ceil($all_records / $per_page);
$curpage = floor($offset / $per_page) + 1;
$jumpcount = 0;
// End of paging functionality

include "../../include/header.php";
?>

<script>
    jQuery(document).ready(function() {
        jQuery('#logyear').change(function() {
            if (jQuery(this).val() == 0) {
                jQuery('#logmonth').val(0);
            }
        });

        jQuery('#logmonth').change(function() {
            if (jQuery(this).val()!=0 && jQuery('#logyear').val()==0) {
                jQuery('#logyear').val(<?php echo $curyear?>);
            }
        });
    });
</script>

<div class="BasicsBox">
    <?php
    $title = $lang["systemlog"];
    if ($table != '' && $table_reference > 0 && array_key_exists($table, $tables_data)) {
        $table_data = $tables_data[$table];
        $table_reference_data = call_user_func_array($table_data['get_data_function'], $table_data['get_data_function_params']);

        if ($table_reference_data !== false) {
            $title .= " - {$table_data['display_title']}: {$table_reference_data[$table_data['title_column']]}";
        }
    }

    // Breadcrumbs
    if (strpos($backurl, 'pages/admin/admin_resource_type_fields.php') !== false) {
        $links_trail = [
            ['title' => $lang["systemsetup"], 'href' => "{$baseurl_short}pages/admin/admin_home.php"],
            ['title' => $lang["admin_resource_type_fields"], 'href' => $backurl],
        ];
    } elseif (strpos($backurl, "pages/team/team_user.php") !== false) {
        // Arrived from Manage users page
        $links_trail = array(
            array(
                'title' => $lang["teamcentre"],
                'href'  => $baseurl_short . "pages/team/team_home.php",
                'menu' =>  true
            ),
            array(
                'title' => $lang["manageusers"],
                'href'  => $backurl
            )
        );
    } elseif (strpos($backurl, "pages/team/team_user_edit.php") !== false) {
        // Arrived from edit user page. This may also have a separate backurl
        $back2url = $baseurl_short . "pages/team/team_user.php";
        $url_parse = parse_url($backurl);
        if (isset($url_parse['query'])) {
            parse_str($url_parse['query'], $url2_qs);
            if (strpos($url2_qs["backurl"] ?? "", "pages/team/team_user.php") !== false) {
                $back2url = $url2_qs["backurl"];
            }
        }

        $links_trail = array(
            array(
                'title' => $lang["teamcentre"],
                'href'  => $baseurl_short . "pages/team/team_home.php",
                'menu' =>  true
            ),
            array(
                'title' => $lang["manageusers"],
                'href'  => $back2url,
            ),
            array(
                'title' => $lang["edituser"],
                'href'  => $backurl
            )
        );
    } else {
        $links_trail = [
            ['title' => $lang["systemsetup"], 'href' => "{$baseurl_short}pages/admin/admin_home.php"]
        ];
    }
    $links_trail[] = array(
        'title' => escape($title)
    );
    ?>

    <h1><?php echo escape($title); ?></h1>
    <?php renderBreadcrumbs($links_trail); ?>
    <h1>
        <form class="ResultsFilterTopRight" method="get">
            <input type="hidden" name="actasuser" value="<?php echo $actasuser; ?>">
            <input type="hidden" name="backurl" value="<?php echo urlencode($backurl); ?>">
            <input type="hidden" name="table" value="<?php echo escape($table); ?>">
            <input type="hidden" name="table_reference" value="<?php echo $table_reference; ?>">
            <input type="hidden" name="logyear" value="<?php echo $logyear; ?>">
            <input type="hidden" name="logmonth" value="<?php echo $logmonth; ?>">
            <input type="text" name="log_search" placeholder="<?php echo escape($log_search); ?>">
            <input type="submit" name="searching" value="<?php echo escape($lang["searchbutton"]); ?>">
            <?php if ($log_search != "") { ?>
                <input type="submit" name="clear_search" value="<?php echo escape($lang["clearbutton"]); ?>">
            <?php } ?>
        </form>
    </h1>

    <?php
    $select_table_url = generateURL(
        "{$baseurl_short}pages/admin/admin_system_log.php",
        array(
            'log_search' => $log_search,
            'backurl' => $backurl,
            'actasuser' => $requesteduser
        )
    );
    ?>

    <form id="TableFilterForm" method="get" action="<?php echo $select_table_url; ?>">
        <?php generateFormToken('TableFilterForm'); ?>
        <div class="Question" id="QuestionFilter">
            <div class="SplitSearch">
                <select class="SplitSearch" id="logmonth" name="logmonth">
                    <?php
                    // Not filtered by default when searching, add option to filter by month
                    echo "<option " .  ($logmonth == "" ? " selected" : "") . " value='0'>" . escape($lang["anymonth"]) . "</option>\n";
                    for ($m = 1; $m <= 12; $m++) {
                        echo "<option " .  ($m == $logmonth ? " selected" : "") . " value=\"" .  sprintf("%02d", $m) . "\">" . escape($lang["months_list"][$m - 1]) . "</option>\n";
                    }
                    ?>
                </select>    
            </div>

            <div class="SplitSearch" id="Questionyear">
                <select class="SplitSearch" id="logyear" name="logyear">
                    <?php
                    // Not filtered by default when searching, add option to filter by month
                    echo "<option " .  ($logyear == "" ? " selected" : "") . " value='0'>" . escape($lang["anyyear"]) . "</option>\n";
                    for ($n = $curyear; $n >= $minyear; $n--) {
                        echo "<option " .  ($n == $logyear ? " selected" : "") . " value=\"" .  $n . "\">" . $n . "</option>\n";
                    }
                    ?>
                </select>
            </div>

            <?php if ($table_reference == "") { ?>
                <select class="SplitSearch" name="table">
                    <option value=""><?php echo escape($lang['filter_by_table']); ?></option>
                    <?php foreach ($tables_data as $select_table => $select_table_data) { ?>
                        <option
                            value="<?php echo $select_table; ?>"
                            <?php echo $select_table == $table ? " selected" : ""; ?>
                        >
                            <?php echo $select_table; ?>
                        </option>
                        <?php
                    }

                    foreach ($no_reference_data_tables as $no_reference_data_table) {
                        if (!isset($tables_data[$no_reference_data_table])) { ?>
                            <option
                                value="<?php echo $no_reference_data_table; ?>"
                                <?php echo $no_reference_data_table == $table ? " selected" : ""; ?>
                            >
                                <?php echo $no_reference_data_table; ?>
                            </option>
                            <?php
                        }
                    }
                    ?>
                </select>
            <?php } else { ?>
                <input type="hidden" name="table" value="<?php echo escape($table);?>">
                <?php
            }

            if ($table_reference != '') {
                ?>
                <input type="hidden" name="table_reference" value="<?php echo $table_reference;?>">
                <?php
            }

            if ($log_search != '') {
                ?>
                <input type="hidden" name="log_search" value="<?php echo escape($log_search);?>">
                <?php
            }
            ?>

            <input type="button" id="datesubmit" class="searchbutton" value="<?php echo escape($lang['filterbutton']); ?>" onclick="return CentralSpacePost(document.getElementById('TableFilterForm'));">
            <div class="clearerleft"></div>
        </div>
    </form>

    <div class="TopInpageNav">
        <div class="TopInpageNavLeft">&nbsp;</div>
        <?php pager(false); ?>
        <div class="clearerleft"></div>
    </div>

    <div class="Listview">
        <table class="ListviewStyle">
            <tbody>
                <tr class="ListviewTitleStyle">
                    <th><?php echo escape($lang['fieldtype-date_and_time']); ?></th>
                    <th><?php echo escape($lang['user']); ?></th>
                    <th><?php echo escape($lang['property-operation']); ?></th>
                    <th><?php echo escape($lang['fieldtitle-notes']); ?></th>
                    <th><?php echo escape($lang['property-resource-field']); ?></th>
                    <th><?php echo escape($lang['property-old_value']); ?></th>
                    <th><?php echo escape($lang['property-new_value']); ?></th>
                    <th><?php echo escape($lang['difference']); ?></th>
                    <?php if ($table == '' || $table_reference == 0) { ?>
                        <th><?php echo escape($lang['property-table']); ?></th>
                    <?php } ?>
                    <th><?php echo escape($lang['property-column']); ?></th>
                    <?php if ($table == '' || $table_reference == 0) { ?>
                        <th><?php echo escape($lang['property-table_reference']); ?></th>
                    <?php } ?>
                </tr>

                <?php
                $original_permitted_html_tags = $permitted_html_tags;
                $permitted_html_tags = array("html", "body");
                $activity_log_records = get_activity_log($log_search, $offset, $per_page, $log_tables_where_statements, $table, $table_reference);
                foreach ($activity_log_records as $record) {
                    ?>
                    <tr>
                        <td><?php echo escape((string) nicedate($record['datetime'], true, true, true)); ?></td>
                        <td><?php echo escape((string) $record['user']); ?></td>
                        <td><?php echo escape((string) $record['operation']); ?></td>
                        <td><?php echo hook("userdisplay", "", array(array("access_key" => $record['access_key'],'username' => $record['user']))) ? "" : escape((string) $record['notes']); ?></td>
                        <td><?php echo escape((string) $record['resource_field']); ?></td>
                        <td><?php echo escape((string) $record['old_value']); ?></td>
                        <td><?php echo escape((string) $record['new_value']); ?></td>
                        <td><?php echo strip_tags_and_attributes($record['difference'], array("pre")); ?></td>
                        <?php if ($table == '' || $table_reference == 0) { ?>
                            <td><?php echo escape((string) $record['table']); ?></td>
                        <?php } ?>
                        <td><?php echo escape((string) $record['column']); ?></td>
                        <?php
                        if ($table != '' && $table_reference == 0 && array_key_exists($record['table'], $tables_data)) {
                            $record_table_data = $tables_data[$record['table']];
                            $record_table_reference_data = call_user_func_array(
                                $record_table_data['get_data_function'],
                                array($record['table_reference'])
                            );

                            if ($record_table_reference_data !== false) {
                                ?>
                                <td><?php echo escape($record_table_reference_data[$record_table_data['title_column']]); ?></td>
                                <?php
                            }
                        } elseif ($table == '' || $table_reference == 0) {
                            $ref = escape((string) $record['table_reference']);

                            switch ($record['column']) {
                                case "ref":
                                    if ($record['table'] == "resource") {
                                        ?>
                                        <td>
                                            <a
                                                href="<?php echo "$baseurl/pages/view.php?ref=$ref" ?>"
                                                title="View resource"
                                                onclick="return ModalLoad(this,true);"
                                                ><?php echo $ref ?>
                                            </a>
                                        </td>
                                        <?php
                                    } elseif ($record['table'] == "collection") {
                                        ?>
                                        <td>
                                            <a
                                                href="<?php echo "$baseurl/pages/search.php?search=!$ref" ?>"
                                                title="View collection"
                                                onclick="return CentralSpaceLoad(this,true);"
                                                ><?php echo $ref ?>
                                            </a>
                                        </td>
                                        <?php
                                    }
                                    break;
                                case "job_data":
                                    if ($record['table'] == "job_queue") {
                                        ?>
                                        <td>
                                            <a
                                                href="<?php echo "$baseurl/pages/job_details.php?job=$ref" ?>"
                                                title="View job"
                                                onclick="return ModalLoad(this,true);"
                                                ><?php echo $ref ?>
                                            </a>
                                        </td>
                                        <?php
                                    }
                                    break;
                                default:
                                    echo "<td>$ref</td>";
                                    break;
                            }
                        }
                        ?>
                    </tr>
                    <?php
                }
                $permitted_html_tags = $original_permitted_html_tags;
                ?>
            </tbody>
        </table>
    </div><!-- end of ListView -->

    <div class="BottomInpageNav">
        <div class="BottomInpageNavRight">  
            <?php pager(false, false); ?>
        </div>
    </div>
</div> <!-- End of BasicBox -->

<?php
include "../../include/footer.php";
