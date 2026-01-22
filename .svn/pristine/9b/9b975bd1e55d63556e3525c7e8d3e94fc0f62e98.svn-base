<?php
include "../include/boot.php";
include "../include/authenticate.php";

$ref = getval("ref", "", true);
$k = getval("k", "");
$modal = (getval("modal", "") == "true");

$filter_by_type = trim(getval("filter_by_type", ""));
$filter_by_usageoption = getval("filter_by_usageoption", null, true);
$filter_url_params = array(
    "filter_by_type" => $filter_by_type,
    "filter_by_usageoption" => $filter_by_usageoption,
);

// Logs can sometimes contain confidential information and the user looking at them must have admin permissions set.
// Some log records can be viewed by all users. Ensure access control by allowing only white listed log codes to bypass
// permissions checks.
$safe_log_codes = array(LOG_CODE_DOWNLOADED);
$resource_access = get_resource_access($ref);
$bypass_permission_check = in_array($filter_by_type, $safe_log_codes) && in_array($resource_access, array(0, 1));

if (!checkperm('v') && !$bypass_permission_check) {
    die($lang['log-adminpermissionsrequired']);
}

# fetch the current search (for finding simlar matches)
$search = getval("search", "");
$order_by = getval("order_by", "relevance");
$search_offset = getval("search_offset", 0, true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$archive = getval("archive", 0, true);
$default_sort_direction = "DESC";

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
$offset = getval("offset", 0, true);
$per_page = getval("per_page", get_per_page_cookie(), true);
$per_page = ($per_page == 0) ? $default_perpage_list : $per_page;
set_per_page_cookie($per_page);
// When filtering by download records only the table output will be slightly different, showing only the following columns:
// date, user, usage option and usage reason
$filter_dld_records_only = ($filter_by_type == LOG_CODE_DOWNLOADED);

# next / previous resource browsing
$go = getval("search_go", "");

if ($go != "") {
    $origref = $ref; # Store the reference of the resource before we move, in case we need to revert this.

    # Re-run the search and locate the next and previous records.
    $modified_result_set = hook("modifypagingresult");
    if ($modified_result_set) {
        $result = $modified_result_set;
    } else {
        $result = do_search($search, $restypes, $order_by, $archive, 240 + $search_offset + 1, $sort, false, DEPRECATED_STARSEARCH);
    }

    if (is_array($result)) {
        # Locate this resource
        $pos = -1;
        for ($n = 0; $n < count($result); $n++) {
            if (isset($result[$n]["ref"]) && $result[$n]["ref"] == $ref) {
                $pos = $n;
            }
        }

        if ($pos != -1) {
            if (($go == "previous") && ($pos > 0)) {
                $ref = $result[$pos - 1]["ref"];
            }
            if (($go == "next") && ($pos < ($n - 1))) {
                $ref = $result[$pos + 1]["ref"];
                if (($pos + 1) >= ($search_offset + 72)) {
                    $search_offset = $pos + 1;
                }
            } # move to next page if we've advanced far enough
        } else {
            ?>
            <script type="text/javascript">
            alert('<?php echo escape($lang["resourcenotinresults"]); ?>');
            </script>
            <?php
        }
    }

    if ($k != "" && !check_access_key($ref, $k)) {
        $ref = $origref;
    } # cancel the move.
}

$url_params = array(
    "ref" => $ref,
    "search" => $search,
    "search_offset" => $search_offset,
    "order_by" => $order_by,
    "sort" => $sort,
    "archive" => $archive,
    "k" => $k,
    "search_go" => $go,
);

if ($ref === "") {
    $error = $lang['resourcenotfound'];
    include "../include/header.php";
    $onload_message = array("title" => $lang["error"], "text" => $error);
    include "../include/footer.php";
    exit();
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <?php
    if (getval("context", false) == 'Modal') {
        $previous_page_modal = true;
    } else {
        $previous_page_modal = false;
    }

    if (!$modal) {
        ?>
        <p>
            <a href="<?php echo generateURL($baseurl_short . "pages/view.php", $url_params);?>" onclick="return CentralSpaceLoad(this,true);">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
        <?php
    } elseif ($previous_page_modal) {
        ?>
        <p>
            <a href="<?php echo generateURL($baseurl_short . "pages/view.php", $url_params);?>"  onclick="return ModalLoad(this,true);">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
        <?php
    }
    ?>

    <div class="RecordHeader">
        <div class="BackToResultsContainer">
            <div class="backtoresults">
                <a href="<?php echo generateURL("{$baseurl_short}pages/log.php", array_merge($url_params, $filter_url_params), array("search_go" => "previous")) . hook("nextpreviousextraurl"); ?>" onclick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this, true);">
                    <?php echo LINK_CARET_BACK . escape($lang["previousresult"])?>
                </a>
                <?php
                hook("viewallresults");
                if ($k == "") { ?>
                    |
                    <a href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($search_offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?>&archive=<?php echo urlencode($archive)?>&k=<?php echo urlencode($k)?>" onclick="return CentralSpaceLoad(this, true);">
                        <?php echo escape($lang["viewallresults"]); ?>
                    </a>
                <?php } ?>
                |
                <a href="<?php echo generateURL("{$baseurl_short}pages/log.php", array_merge($url_params, $filter_url_params), array("search_go" => "next")) . hook("nextpreviousextraurl"); ?>" onclick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this, true);">
                    <?php echo escape($lang["nextresult"])?>&nbsp;<?php echo LINK_CARET ?>
                </a>
                <?php if ($modal) { ?>
                    &nbsp;&nbsp;<a class="maxLink icon-maximize-2" href="<?php echo generateURL("{$baseurl_short}pages/log.php", array_merge($url_params, $filter_url_params)); ?>" onclick="return CentralSpaceLoad(this);" title="<?php echo escape($lang["maximise"]); ?>"></a>
                    &nbsp;<a href="#" class="closeLink icon-x" onclick="ModalClose();" title="<?php echo escape($lang["close"]); ?>"></a>
                <?php } ?>
            </div>
        </div>
        <h1>
            <?php
            echo escape("{$lang["resourcelog"]} : {$lang["resourceid"]} {$ref}");
            render_help_link("user/logs");
            ?>
        </h1>
    </div>

    <?php
    $fetchrows = $offset + $per_page;
    $filters = array();

    if ($filter_by_type != "") {
        $filters["r.type"] = $filter_by_type;
    }

    if (!is_null($filter_by_usageoption) && $filter_by_usageoption >= 0) {
        $filters["r.usageoption"] = $filter_by_usageoption;
    }

    if ($bypass_permission_check) {
        $log = bypass_permissions(array("v"), "get_resource_log", array($ref, $fetchrows, $filters));
    } else {
        $log = get_resource_log($ref, $fetchrows, $filters);
    }

    # Calculate pager vars.
    $results    =   $log["total"];
    $log        =   $log["data"];
    $totalpages =   ceil($results / $per_page);
    $curpage    =   floor($offset / $per_page) + 1;
    $url        =  generateURL(
        "{$baseurl_short}pages/log.php",
        array_merge($url_params, $filter_url_params)
    ) . hook("nextpreviousextraurl");

    $headers = array(
        "date" => array("name" => $lang["date"],"sortable" => false, "width" => "170px"),
        "user" => array("name" => $lang["user"],"sortable" => false),
    );

    if ($filter_dld_records_only) {
        $headers["usagemedium"] = array("name" => $lang["indicateusagemedium"],"sortable" => false);
        $headers["usage"] = array("name" => $lang["usage"],"sortable" => false);
    } else {
        $headers["action"] = array("name" => $lang["action"],"sortable" => false);
        $headers["field"] = array("name" => $lang["field"],"sortable" => false, "html" => true);
    }

    hook("log_extra_columns_header");

    $tabledata = array(
        "class" => "LogTable",
        "headers" => $headers,
        "defaulturl" => $baseurl . "/pages/log.php",
        "params" => array_merge($url_params, $filter_url_params),
        "pager" => array("current" => $curpage,"total" => $totalpages, "per_page" => $per_page, "break" => false),
        "modal" => $modal,
        "data" => array()
    );

    for ($n = $offset; (($n < count($log)) && ($n < ($offset + $per_page))); $n++) {
        $logentry = array();
        $logentry["date"] = nicedate($log[$n]["date"], true, true, true);

        if (!isset($lang["log-" . $log[$n]["type"]])) {
            $lang["log-" . $log[$n]["type"]] = "";
        }

        $logusertext = $log[$n]["access_key"] != "" ?
            ($lang["externalusersharing"] . ": " . $log[$n]["access_key"] . " " . $lang["viauser"] . " " . (empty($log[$n]["shared_by"]) ? $log[$n]["fullname"] : $log[$n]["shared_by"]))
            : (trim((string) $log[$n]["fullname"]) !== "" ? $log[$n]["fullname"] : $lang["system_user_default"]);
        $logentry["user"] = hook("userdisplay", "", array($log[$n])) ? "" : $logusertext;

        if ($filter_dld_records_only) {
            if (isset($download_usage_options[$log[$n]["usageoption"]]) && $log[$n]["usageoption"] != -1 && $log[$n]["usageoption"] >= 0) {
                $logentry["usage"] = nl2br(escape(i18n_get_translated($download_usage_options[$log[$n]["usageoption"]])));
            }
            $logentry["usagemedium"]  = escape($log[$n]["notes"]);
            $tabledata["data"][] = $logentry;
        } else {
            $logentry["action"]     = escape($lang["log-" . $log[$n]["type"]] . " " . $log[$n]["notes"]);
            $logentry["field"]      = escape($log[$n]["title"]);
            $logentry["rowlink"]    = generateURL("{$baseurl_short}pages/log_entry.php", array_merge($url_params, $filter_url_params), array("ref" => $log[$n]["ref"]));
            $tabledata["data"][] = $logentry;
        }
    }
    ?>
    <div id='log_container'>
        <?php render_table($tabledata); ?>
    </div>
</div>

<?php
include "../include/footer.php";