<?php

/**
 * View my own requests
 *
  */

include "../include/boot.php";
include "../include/authenticate.php";
include "../include/request_functions.php";

$offset = getval("offset", 0, true);

include "../include/header.php";
?>

<div class="BasicsBox"> 
    <h1>
        <?php
        echo escape($lang["myrequests"]);
        render_help_link("resourceadmin/user-resource-requests");
        ?>
    </h1>
    <p><?php echo escape(text("introtext")); ?></p>
 
    <?php
    $requests = get_user_requests();

    # pager
    $per_page = 10;
    $results = count($requests);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $url = "requests.php?";
    $jumpcount = 1;
    ?>

    <div class="TopInpageNav"><?php pager(); ?></div>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th><?php echo escape($lang["requestorderid"]); ?></th>
                <th><?php echo escape($lang["description"]); ?></th>
                <th><?php echo escape($lang["date"]); ?></th>
                <th><?php echo escape($lang["itemstitle"]); ?></th>
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
                    <td><?php echo escape($requests[$n]["comments"]); ?></td>
                    <td><?php echo escape(nicedate($requests[$n]["created"], true)); ?></td>
                    <td><?php echo escape($requests[$n]["c"]); ?></td>
                    <td><?php echo escape($lang["resourcerequeststatus" . $requests[$n]["status"]]); ?></td>
                    <td>
                        <div class="ListTools">
                            <?php if ($requests[$n]["collection_id"] > 0) { // only show tools if the collection still exists
                                ?>
                                <a href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!collection" . $requests[$n]["collection"])?>" onclick="return CentralSpaceLoad(this,true);">
                                    <?php echo LINK_CARET . escape($lang["action-view"]); ?>
                                </a>
                                <?php if (!checkperm("b")) { ?>
                                    &nbsp;
                                    <a
                                        href="<?php echo $baseurl_short?>pages/collections.php?collection=<?php echo $requests[$n]["collection"];
                                        echo ($autoshow_thumbs) ? "&amp;thumbs=show" : ''; ?>"
                                        onclick="return CollectionDivLoad(this);"
                                    >
                                        <?php echo LINK_CARET . escape($lang["action-select"]); ?>
                                    </a>
                                    <?php
                                }
                            } // end of if collection still exists ?>
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
include "../include/footer.php";
?>
