<?php

/**
 * Manage resource request page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("R")) {
    exit("Permission denied.");
}

include "../../include/request_functions.php";
include "../../include/header.php";

$offset = getval("offset", 0, true);
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["managerequestsorders"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["teamcentre"],
            'href'  => $baseurl_short . "pages/team/team_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["managerequestsorders"],
            'help'  => "resourceadmin/user-resource-requests"
        )
    );

    renderBreadcrumbs($links_trail);

    $requests = get_requests();

    # pager
    $per_page = 20;
    $results = count($requests);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $url = "team_request.php?";
    $jumpcount = 1;

    ?>
    <div class="TopInpageNav">
        <?php pager();  ?>
        <br style="clear:left" />
        <br />
    </div>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th><?php echo escape($lang["requestorderid"]); ?></th>
                <th><?php echo escape($lang["username"]); ?></th>
                <th><?php echo escape($lang["fullname"]); ?></th>
                <th><?php echo escape($lang["date"]); ?></th>
                <th><?php echo escape($lang["itemstitle"]); ?></th>
                <th><?php echo escape($lang["type"]); ?></th>
                <th><?php echo escape($lang["assignedto"]); ?></th>
                <th><?php echo escape($lang["status"]); ?></th>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                </th>
            </tr>

            <?php
            $statusname = array("","","","");
            $requesttypes = array("","","","");

            for ($n = $offset; (($n < count($requests)) && ($n < ($offset + $per_page))); $n++) {
                ?>
                <tr>
                    <td><?php echo escape($requests[$n]["ref"]); ?></td>
                    <td><?php echo escape((string) $requests[$n]["username"]); ?></td>
                    <td><?php echo escape((string) $requests[$n]["fullname"]); ?></td>
                    <td><?php echo escape(nicedate($requests[$n]["created"], true, true, true));?></td>
                    <td><?php echo escape($requests[$n]["c"]); ?></td>
                    <td><?php echo escape($lang["resourcerequesttype" . $requests[$n]["request_mode"]]); ?></td>
                    <td><?php echo escape((string) $requests[$n]["assigned_to_username"]); ?></td>
                    <td><?php echo escape($lang["resourcerequeststatus" . $requests[$n]["status"]]); ?></td>
                    <td>
                        <div class="ListTools">
                            <a href="<?php echo $baseurl_short?>pages/team/team_request_edit.php?ref=<?php echo escape($requests[$n]["ref"]); ?>" onClick="return <?php echo $modal_default ? "Modal" : "CentralSpace"; ?>Load(this,true);">
                                <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div><!--end of Listview -->
    <div class="BottomInpageNav"><?php pager(false); ?></div>
</div><!-- end of BasicsBox -->

<?php
include "../../include/footer.php";
