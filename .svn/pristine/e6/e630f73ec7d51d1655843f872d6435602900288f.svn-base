<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

include "../../include/header.php";

$find = getval("find", "");
$order_by = getval("orderby", "name");
$order_by_list = ["ref","ref desc","name","name desc","support_non_correlated_sql","support_non_correlated_sql desc"];

if (!in_array(strtolower($order_by), $order_by_list)) {
    $order_by[0] = "name";
}

$url_params = array("find" => $find, "orderby" => $order_by);
$url = generateURL($baseurl . "/pages/admin/admin_report_management.php", $url_params);

$find_sql = "";

if ($find != "") {
    $find_sql = " WHERE ref LIKE ? OR name LIKE ?";
    $sql_params = ["s","%" . $find . "%","s","%" . $find . "%"];
}

$reports = ps_query("SELECT ref, `name`, support_non_correlated_sql FROM report {$find_sql} ORDER BY {$order_by}", $sql_params ?? []);
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["page-title_report_management"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["page-title_report_management"],
        )
    );

    renderBreadcrumbs($links_trail);
    ?>
    
    <p>
        <?php
        echo escape($lang['page-subtitle_report_management_edit']);
        render_help_link("resourceadmin/reports-and-statistics");
        ?>
    </p>

    <form
        method="post"  
        id="copy_report" 
        action="admin_report_management_edit.php"
        onsubmit="return CentralSpacePost(this, true);">
        <input type="hidden" name="copyreport" value="true">
        <input type="hidden" name="ref" value="">
        <?php generateFormToken("copy_report"); ?>
    </form>

    <script>
        function copyReport(ref) {
            frm = document.forms["copy_report"];
            frm.ref.value=ref;
            frm.submit();   
        }
    </script>

    <?php
    function addColumnHeader($orderName, $labelKey)
    {
        global $baseurl, $order_by, $find, $lang;

        if ($order_by == $orderName) {
            $image = '<span class="ASC"></span>';
        } elseif ($order_by == $orderName . ' desc') {
            $image = '<span class="DESC"></span>';
        } else {
            $image = '';
        }
        ?>
        <th>
            <a
                href="<?php echo $baseurl ?>/pages/admin/admin_report_management.php?<?php
                    echo ($find != "") ? "&find=" . escape($find) : ''; ?>&orderby=<?php
                    echo $orderName . ($order_by == $orderName ? '+desc' : ''); ?>"
                onClick="return CentralSpaceLoad(this);">
                <?php echo escape($lang[$labelKey]) . $image ?>
            </a>
        </th>
        <?php
    }
    ?>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <?php
                addColumnHeader("ref", "property-reference");
                addColumnHeader("name", "property-name");
                addColumnHeader('support_non_correlated_sql', 'property-support_non_correlated_sql');
                ?>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                </th>
            </tr>

            <?php
            foreach ($reports as $report) {
                $support_non_correlated_sql = ((bool)$report['support_non_correlated_sql'] === true);
                $edit_url_extra = array();
                $edit_url_extra = ($find == "" ? $edit_url_extra : array_merge($edit_url_extra, array("find" => $find)));
                $edit_url_extra = ($order_by == "name" ? $edit_url_extra : array_merge($edit_url_extra, array("orderby" => $order_by)));
                $edit_url = generateURL("{$baseurl_short}pages/admin/admin_report_management_edit.php", array("ref" => $report["ref"]), $edit_url_extra);
                $view_url = "{$baseurl_short}pages/team/team_report.php?report={$report['ref']}&backurl=" . urlencode($url);
                $a_href = (!(!db_use_multiple_connection_modes() && $execution_lockout) ? $edit_url : $view_url);
                ?>
                <tr>
                    <td>
                        <a href="<?php echo $a_href; ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($report["ref"]); ?>
                        </a>
                    </td>
                    <td>
                        <a href="<?php echo $a_href; ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($report["name"]); ?>
                        </a>
                    </td>
                    <td><?php echo escape($support_non_correlated_sql ? $lang['yes'] : $lang['no']); ?></td>
                    <td>
                        <div class="ListView ListTools" align="right">
                            <?php
                            if (!$support_non_correlated_sql) { ?>
                                <a href="<?php echo $view_url; ?>" onclick="return CentralSpaceLoad(this, true);">
                                    <i class="icon-sheet"></i>&nbsp;<?php echo escape($lang["action-view"]); ?>
                                </a>
                                <?php
                            }

                            if (db_use_multiple_connection_modes() || !$execution_lockout) { ?>
                                <a href="<?php echo $edit_url; ?>" onclick="return CentralSpaceLoad(this, true);">
                                    <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?>
                                </a>
                                <a href="javascript:copyReport('<?php echo $report["ref"]; ?>')">
                                    <i class="icon-copy"></i>&nbsp;<?php echo escape($lang["copy"]); ?>
                                </a>
                                <?php
                            }
                            ?>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
</div><!-- end of BasicsBox -->

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short?>pages/admin/admin_report_management.php" onSubmit="return CentralSpacePost(this,false);">
        <?php generateFormToken("admin_report_management_find"); ?>
        <div class="Question">
            <label for="find"><?php echo escape($lang["property-search_filter"]); ?></label>
            <input name="find" type="text" class="medwidth" value="<?php echo escape($find); ?>">
            <input name="save" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>">
            <div class="clearerleft"></div>
        </div>
        <?php if ($find != "") { ?>
            <div class="QuestionSubmit">
                <input
                    name="buttonsave"
                    type="button"
                    onclick="CentralSpaceLoad('admin_report_management.php',false);"
                    value="<?php echo escape($lang["clearbutton"]); ?>"
                >
            </div>
        <?php } ?>
    </form>
</div>

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short; ?>pages/admin/admin_report_management_edit.php" onSubmit="return CentralSpacePost(this,false);">
        <?php generateFormToken("admin_report_management"); ?>
        <div class="Question">
            <label for="name"><?php echo escape($lang['action-title_create_report_called']); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <input name="newreportname" type="text" value="" class="shrtwidth">
                </div>
                <div class="Inline">
                    <input name="Submit" type="submit" value="<?php echo escape($lang["create"]); ?>" onclick="return (this.form.elements[0].value!='');">
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>

        <?php
        if ($order_by) { ?>
            <input type="hidden" name="orderby" value="<?php echo escape($order_by); ?>">
            <?php
        }

        if ($find) { ?>
            <input type="hidden" name="find" value="<?php echo escape($find); ?>">
            <?php
        }
        ?>
    </form>
</div>

<?php
include "../../include/footer.php";

