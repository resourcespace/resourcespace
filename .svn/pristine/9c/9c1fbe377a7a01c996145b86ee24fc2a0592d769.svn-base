<?php

/**
 * Manage research requests page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("r")) {
    exit("Permission denied.");
}

include "../../include/research_functions.php";

$offset     = getval("offset", 0, true);
$find       = getval("find", "");
$order_by   = getval("order_by", "ref");
$sort       = getval("sort", "ASC") == "ASC" ? "ASC" : "DESC";
$revsort    = ($sort == "ASC") ? "DESC" : "ASC";

if (array_key_exists("find", $_POST)) {
    $offset = 0;
} # reset page counter when posting

if (getval("reload", "") != "") {
    refresh_collection_frame();
}

include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["manageresearchrequests"])?></h1>
    <p>
        <?php
        echo strip_paragraph_tags(strip_tags_and_attributes(text("introtext")));
        render_help_link('resourceadmin/user-research-requests');
        ?>
    </p>
    
    <?php
    $requests = get_research_requests($find, $order_by, $sort);

    # pager
    $per_page = 10;
    $results = count($requests);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $url = "team_research.php?find=" . urlencode($find) . "&order_by=" . $order_by . "&sort=" . $sort . "&find=" . urlencode($find) . "";
    $jumpcount = 1;
    ?>

    <div class="TopInpageNav"><?php pager(); ?></div>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th>
                    <a href="<?php echo $baseurl_short?>pages/team/team_research.php?offset=0&order_by=ref&sort=<?php echo $revsort?>&find=<?php echo urlencode($find)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["researchid"])?></a>
                </th>
                <th>
                    <a href="<?php echo $baseurl_short?>pages/team/team_research.php?offset=0&order_by=name&sort=<?php echo $revsort?>&find=<?php echo urlencode($find)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["nameofproject"])?></a>
                </th>
                <th>
                    <a href="<?php echo $baseurl_short?>pages/team/team_research.php?offset=0&order_by=created&sort=<?php echo $revsort?>&find=<?php echo urlencode($find)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["date"])?></a>
                </th>
                <th>
                    <a href="<?php echo $baseurl_short?>pages/team/team_research.php?offset=0&order_by=status&sort=<?php echo $revsort?>&find=<?php echo urlencode($find)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["status"])?></a>
                </th>
                <th>
                    <a href="<?php echo $baseurl_short?>pages/team/team_research.php?offset=0&order_by=assigned_to&sort=<?php echo $revsort?>&find=<?php echo urlencode($find)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["assignedto"])?></a>
                </th>
                <th>
                    <a href="<?php echo $baseurl_short?>pages/team/team_research.php?offset=0&order_by=collection&sort=<?php echo $revsort?>&find=<?php echo urlencode($find)?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["collectionid"])?></a>
                </th>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"])?></div>
                </th>
            </tr>

            <?php
            $statusname = array($lang["requeststatus0"],$lang["requeststatus1"],$lang["requeststatus2"]);
            for ($n = $offset; (($n < count($requests)) && ($n < ($offset + $per_page))); $n++) {
                ?>
                <tr>
                    <td><?php echo $requests[$n]["ref"]; ?></td>
                    <td>
                        <div class="ListTitle">
                            <a href="<?php echo $baseurl_short?>pages/team/team_research_edit.php?ref=<?php echo $requests[$n]["ref"]; ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape(i18n_get_translated($requests[$n]["name"]));?></a>&nbsp;
                        </div>
                    </td>
                    <td><?php echo nicedate($requests[$n]["created"], false, true)?></td>
                    <td><?php echo escape($statusname[$requests[$n]["status"]])?></td>
                    <td><?php echo (strlen((string) $requests[$n]["assigned_username"]) == 0) ? "-" : escape($requests[$n]["assigned_username"])?></td>
                    <td><?php echo (strlen((string) $requests[$n]["collection"]) == 0) ? "-" : escape($requests[$n]["collection"])?></td>
                    <td>
                        <div class="ListTools">
                            <a href="<?php echo $baseurl_short?>pages/team/team_research_edit.php?ref=<?php echo $requests[$n]["ref"]; ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo '<i class="icon-notebook-pen"></i>&nbsp' . escape($lang["editresearch"])?></a>&nbsp;&nbsp;
                            <a href="<?php echo $baseurl_short?>pages/collections.php?research=<?php echo $requests[$n]["ref"]; ?>" onClick="return CollectionDivLoad(this);"><?php echo '<i class="icon-shopping-bag"></i>&nbsp' . escape($lang["editcollection"])?></a>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
    <div class="BottomInpageNav">
        <div class="InpageNavLeftBlock">
            <a href="<?php echo $baseurl_short?>pages/research_request.php?assign=true" onClick="return CentralSpaceLoad(this,true);">
                <?php echo LINK_CARET ?><?php echo escape($lang["createresearchforuser"])?>
            </a>
        </div>
        <?php pager(false); ?>
    </div>
</div>

<div class="BasicsBox">
    <form method="GET" action="<?php echo $baseurl_short?>pages/team/team_research.php">
        <div class="Question">
            <label for="findpublic"><?php echo escape($lang["searchresearchrequests"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <input type=text name="find" id="find" value="<?php echo escape($find); ?>" maxlength="100" class="shrtwidth" />
                </div>
                <div class="Inline">
                    <input name="Submit" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<?php
include "../../include/footer.php";
?>
