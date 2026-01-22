<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

if ((!db_use_multiple_connection_modes() && $execution_lockout) || !checkperm("a")) {
    exit("Permission denied.");
}

$find = getval("find", "");
$order_by = getval("orderby", "");
$url_params = ($order_by ? "&orderby={$order_by}" : "") . ($find ? "&find={$find}" : "");

$ref = getval("ref", "");
$copyreport = getval("copyreport", "");

# create new record from callback
$new_report_name = getval("newreportname", "");
if ($new_report_name != "" && enforcePostRequest(false)) {
    ps_query("INSERT into report (name) values(?)", array("s",$new_report_name));
    $ref = sql_insert_id();
    log_activity(null, LOG_CODE_CREATED, $new_report_name, 'report', 'name', $ref);

    redirect($baseurl_short . "pages/admin/admin_report_management_edit.php?ref={$ref}{$url_params}");    // redirect to prevent repost and expose form data
    exit;
} elseif ($copyreport != "" && enforcePostRequest(false)) {
    // Copy report?
    ps_query("INSERT into report (name, query) select concat('" . $lang["copy_of"] . " ',name), query from report where ref=?", array("i",$ref));
    $from_ref = $ref;
    $ref = sql_insert_id();
    $new_copied_name = ps_value("SELECT `name` AS 'value' FROM `report` WHERE `ref`=?", array("i",$ref), '');
    log_activity($lang["copy_of"] . ' ' . $from_ref, LOG_CODE_COPIED, $new_copied_name, 'report', 'name', $ref, null, '');
} elseif (!ps_value("select ref as value from report where ref=?", array("i",$ref), false)) {
    redirect("{$baseurl_short}pages/admin/admin_report_management.php?{$url_params}");      // fail safe by returning to the report management page if duff ref passed
    exit;
}

if (getval("deleteme", false) && enforcePostRequest(false)) {
    log_activity(null, LOG_CODE_DELETED, null, 'report', 'name', $ref);
    ps_query("DELETE from report where ref=?", array("i",$ref));
    redirect("{$baseurl_short}pages/admin/admin_report_management.php?{$url_params}");      // return to the report management page
    exit;
}

$name = getval("name", "");
$query = getval("query", "");
if (getval("save", false)) {
    if (strlen(trim($query)) == 0) {
        $error = $lang["report_query_required"];
    }
    if (!isset($error) && enforcePostRequest(false)) {
        log_activity(null, LOG_CODE_EDITED, $name, 'report', 'name', $ref, null, ps_value("SELECT `name` AS value FROM `report` WHERE ref=?", array("i",$ref), ""));
        log_activity(null, LOG_CODE_EDITED, $query, 'report', 'query', $ref, null, ps_value("SELECT `query` AS value FROM `report` WHERE ref=?", array("i",$ref), ""), null, true);

        $support_non_correlated_sql = (int) (mb_strpos($query, REPORT_PLACEHOLDER_NON_CORRELATED_SQL) !== false);

        $parameters = array("s",$name, "s",$query, "i",$support_non_correlated_sql, "i",$ref);
        ps_query("UPDATE report SET name = ?, query = ?, support_non_correlated_sql = ? WHERE ref = ?", $parameters);
        redirect("{$baseurl_short}pages/admin/admin_report_management.php?{$url_params}");
        exit;
    }
}

$record = ps_query("select " . columns_in("report") . " from report where ref=?", array("i",$ref));
$record = $record[0];

include "../../include/header.php";

$url_params_edit = array(
    "ref" => $ref,
    "orderby" => $order_by,
    "find" => $find
);

if (isset($error)) { ?>
    <div class="FormError">!! <?php echo $error?> !!</div>
    <?php
}
?>

<form
    method="post"
    enctype="multipart/form-data"
    action="<?php echo generateURL($baseurl_short . 'pages/admin/admin_report_management_edit.php', $url_params_edit);?>"
    id="mainform"
    onSubmit="return CentralSpacePost(this,true);" class="FormWide">
    <?php generateFormToken("mainform"); ?>
    <div class="BasicsBox">
        <h1><?php echo escape($lang["page-title_report_management_edit"]); ?></h1>
        <?php
        $links_trail = array(
            array(
                'title' => $lang["systemsetup"],
                'href'  => $baseurl_short . "pages/admin/admin_home.php",
                'menu' =>  true
            ),
            array(
                'title' => $lang["page-title_report_management"],
                'href'  => $baseurl_short . "pages/admin/admin_report_management_edit.php?" . $url_params
            ),
            array(
                'title' => $lang["page-title_report_management_edit"]
            )
        );

        renderBreadcrumbs($links_trail);
        ?>

        <p>
            <?php
            echo escape($lang['page-subtitle_report_management_edit']);
            render_help_link("resourceadmin/custom_reports");
            ?>
        </p>

        <input type="hidden" name="save" value="1">

        <div class="Question">
            <label for="reference"><?php echo escape($lang["property-reference"]); ?></label>
            <div class="Fixed"><?php echo (int)$ref; ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="name"><?php echo escape($lang["property-name"]); ?></label>
            <input name="name" type="text" class="stdwidth" value="<?php echo $record['name']; ?>"> 
            <div class="clearerleft"></div>
        </div>

        <div class="Question">          
            <label for="query"><?php echo escape($lang["property-query"]); ?></label>
            <textarea name="query" class="stdwidth" style="height: 300px;"><?php echo $record['query']; ?></textarea>
            <div class="clearerleft"></div>     
        </div>

        <div class="Question">
            <label><?php echo escape($lang["fieldtitle-tick_to_delete_report"]); ?></label>
            <input name="deleteme" type="checkbox" value="yes">
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">
            <input name="buttonsave" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["save"]); ?>&nbsp;&nbsp;">
        </div>
    </div>
</form>

<?php
include "../../include/footer.php";
