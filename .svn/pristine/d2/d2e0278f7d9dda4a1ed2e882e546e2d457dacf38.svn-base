<?php
include "../include/boot.php";
include "../include/authenticate.php";

if (!checkperm("R")) {
    exit("Permission denied.");
}

$ref = getval("ref", "", true);
$k = getval("k", "");

# fetch the current search
$search = getval("search", "");
$order_by = getval("order_by", "relevance");
$offset = getval("offset", 0, true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$archive = getval("archive", 0, true);
$default_sort_direction = "DESC";
$per_page = getval("per_page", 0, true);

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
$curpos = getval("curpos", '');
$modal = (getval("modal", "") == "true");

$urlparams = get_search_params();
$urlparams["ref"] = $ref;
$urlparams["modal"] = $modal ? "true" : "";
$urlparams["curpos"] = $curpos;

if (getval("context", false) == 'Modal') {
    $previous_page_modal = true;
} else {
    $previous_page_modal = false;
}

# next / previous resource browsing
$go = getval("go", "");

if ($go != "") {
    $origref = $ref; # Store the reference of the resource before we move, in case we need to revert this.

    # Re-run the search and locate the next and previous records.
    $result = do_search($search, $restypes, $order_by, $archive, -1, $sort, false, DEPRECATED_STARSEARCH);
    if (is_array($result)) {
        # Locate this resource
        $pos = -1;
        for ($n = 0; $n < count($result); $n++) {
            if ($result[$n]["ref"] == $ref) {
                $pos = $n;
            }
        }

        if ($pos != -1) {
            if (($go == "previous") && ($pos > 0)) {
                $ref = $result[$pos - 1]["ref"];
                if (($pos - 1) < $offset) {
                    $offset = $offset - $per_page;
                }
            }

            if (($go == "next") && ($pos < ($n - 1))) {
                $ref = $result[$pos + 1]["ref"];
                if (($pos + 1) >= ($offset + $per_page)) {
                    $offset = $pos + 1;
                }
            } # move to next page if we've advanced far enough
        } elseif ($curpos != "") {
            if (($go == "previous") && ($curpos > 0) && isset($result[$curpos - 1]["ref"])) {
                $ref = $result[$curpos - 1]["ref"];
                if (($pos - 1) < $offset) {
                    $offset = $offset - $per_page;
                }
            }

            if (($go == "next") && ($curpos < ($n)) && isset($result[$curpos]["ref"])) {
                $ref = $result[$curpos]["ref"];
                if (($curpos) >= ($offset + $per_page)) {
                    $offset = $curpos + 1;
                }
            }  # move to next page if we've advanced far enough
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

    $urlparams["curpos"] = $curpos;
    $urlparams["ref"] = $ref;
    $urlparams["offset"] = $offset;
}
$extraparams = hook("nextpreviousextraurl");

include "../include/header.php";
?>

<div class="BasicsBox">
    <?php if ($previous_page_modal) { ?>
        <p>
            <a onclick="return ModalLoad(this,true);" href="<?php echo generateURL($baseurl_short . "pages/view.php", $urlparams);?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
    <?php } else { ?>
        <p>
            <a onclick="ModalClose();return false;" href="<?php echo generateURL($baseurl_short . "pages/view.php", $urlparams);?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
        <?php
    }
    ?>

    <div class="backtoresults">
        <a onclick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);" href="<?php echo generateURL($baseurl_short . "pages/request_log.php", $urlparams, array("go" => "previous")) . escape($extraparams) ?>">
            <?php echo LINK_CARET_BACK . escape($lang["previousresult"])?>
        </a>
        <?php
        hook("viewallresults");
        if ($k == "" && !$modal) { ?>
            |
            <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl_short . "pages/search.php", $urlparams); ?>">
                <?php echo escape($lang["viewallresults"]); ?>
            </a>
        <?php } ?>
        |
        <a onclick="return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);" href="<?php echo generateURL($baseurl_short . "pages/request_log.php", $urlparams, array("go" => "next")) . escape($extraparams) ?>">
            <?php echo escape($lang["nextresult"]); ?>&nbsp;&gt;
        </a>
    </div>

    <h1>
        <?php
        echo escape($lang["requestlog"] . " : " . $lang["resourceid"] . " " .  $ref);
        render_help_link("resourceadmin/user-resource-requests");
        ?>
    </h1>

    <div class="Listview">
        <table class="ListviewStyle">
            <!--Title row-->    
            <tr class="ListviewTitleStyle">
                <th width="5%"><?php echo escape($lang["date"]); ?></th>
                <th width="5%"><?php echo escape($lang["requestorderid"]); ?></th>
                <th width="5%"><?php echo escape($lang["user"]); ?></th>
                <th width="25%"><?php echo escape($lang["comments"]); ?></th>
                <th width="10%"><?php echo escape($lang["status"]); ?></th>
                <th width="25%"><?php echo escape($lang["approvalreason"]); ?></th>
                <th width="25%"><?php echo escape($lang["declinereason"]); ?></th>
            </tr>

            <?php
            $log = ps_query("SELECT rq.created date, rq.ref ref, u.fullname username, rq.comments, rq.status status, rq.reason reason, rq.reasonapproved reasonapproved 
                    FROM request rq left outer join user u on u.ref=rq.user left outer join collection_resource cr on cr.collection=rq.collection 
                    WHERE cr.resource=?", array("i",$ref));

            for ($n = 0; $n < count($log); $n++) {
                ?>
                <!--List Item-->
                <tr>
                    <td nowrap><?php echo nicedate($log[$n]["date"], true, true)?></td>
                    <td nowrap><?php echo $log[$n]["ref"]; ?></td>
                    <td nowrap><?php echo $log[$n]["username"]; ?></td>
                    <td><?php echo escape($log[$n]["comments"]) ?></td>
                    <td nowrap>
                        <?php
                        switch ($log[$n]["status"]) {
                            case 0:
                                echo escape($lang["resourcerequeststatus0"]);
                                break;
                            case 1:
                                echo escape($lang["resourcerequeststatus1"]);
                                break;
                            case 2:
                                echo escape($lang["resourcerequeststatus2"]);
                                break;
                        }
                        ?>
                    </td>
                    <td><?php echo $log[$n]["reasonapproved"]; ?></td>
                    <td><?php echo $log[$n]["reason"]; ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
</div>

<?php
include "../include/footer.php";
?>
