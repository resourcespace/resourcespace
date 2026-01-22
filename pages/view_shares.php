<?php
include "../include/boot.php";
include "../include/authenticate.php";

if (checkperm("b")) {
    exit("Permission denied");
}

$offset = getval("offset", 0, true);
$per_page = getval("per_page_list", get_per_page_cookie(), true);
set_per_page_cookie($per_page);

include "../include/header.php";
?>

<div class="BasicsBox">
    <p>
        <a href="<?php echo $baseurl_short?>pages/collection_manage.php" onclick="return CentralSpaceLoad(this,true);">
            <?php echo LINK_CARET_BACK . escape($lang["managecollectionslink"]); ?>
        </a>
    </p>

    <h1>
        <?php
        echo escape($lang["shared_collections"]);
        render_help_link("user/sharing-resources");
        ?>
    </h1>

    <?php
    $collections = get_user_collections($userref, "!shared");
    $results = count($collections);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $jumpcount = 1;
    $url = $baseurl_short . "pages/view_shares.php?coluser=" . $userref;
    ?>
    
    <div class="TopInpageNav"><?php pager(false); ?></div>
    
    <?php for ($n = $offset; (($n < count($collections)) && ($n < ($offset + $per_page))); $n++) { ?>
        <div class="RecordBox">
            <div class="RecordPanel">
                <div class="RecordHeader">
                    <table>
                        <tr>
                            <td style="margin:0px;padding:0px;">
                                <h1 class="shared_collection_title">
                                    <a href="<?php echo $baseurl_short?>pages/search.php?search=!collection<?php echo $collections[$n]['ref']; ?>" onclick="return CentralSpaceLoad(this);" >
                                        <?php echo i18n_get_collection_name($collections[$n]); ?>
                                    </a>
                                </h1>
                            </td>
                        </tr>
                    </table>
                    <div class="clearerright"></div>
                </div><!-- End of RecordHeader --> 

                <div class="Listview">
                    <table class="ListviewStyle">
                        <tr class="ListviewTitleStyle">
                            <th width="15%"><?php echo escape($lang["sharedwith"]); ?></th>
                            <th width="15%"><?php echo escape($lang["access"]); ?></th>
                            <th width="40%"><?php echo escape($lang["fieldtitle-notes"]); ?></th>
                            <th width="30%">
                                <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                            </th>
                        </tr>

                        <?php
                        // Display row for each share/attached user
                        $colref = $collections[$n]["ref"];

                        // Check for external shares
                        $extshares = ps_query("SELECT access, expires 
                            FROM external_access_keys 
                            WHERE collection=? and (expires is null or expires>now()) group by collection", array("i",$colref));

                        if (count($extshares) != 0) {
                            foreach ($extshares as $extshare) { ?>
                                <tr>
                                    <td><?php echo escape($lang["external"]); ?></td>
                                    <td><?php echo escape($extshare["access"] == 0 ? $lang["access0"] : $lang["access1"]); ?></td>
                                    <td><?php echo escape(str_replace("%date%", (($extshare["expires"] != "") ? nicedate($extshare["expires"]) : $lang["never"]), $lang["expires-date"])); ?></td>
                                    <td>
                                        <div class="ListTools">
                                            <a onclick="return CentralSpaceLoad(this, true);"
                                                href="<?php echo $baseurl . '/pages/collection_share.php?ref=' . escape($collections[$n]["ref"]); ?>">
                                                <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        }

                        // Check for attached users
                        $colusers = ps_query("SELECT u.fullname, u.username FROM user_collection uc LEFT JOIN user u on u.ref=uc.user and user<>? WHERE uc.collection=?", array("i", $userref, "i", $colref));

                        if (count($colusers) != 0) {
                            ?>
                            <tr>
                                <td><?php echo escape($lang["users"]); ?></td>
                                <td><?php echo escape(($collections[$n]["allow_changes"] == 0) ? $lang["view"] : $lang["addremove"]); ?></td>
                                <td>
                                    <?php echo escape($lang["users"]) . ":"; ?>
                                    <br />
                                    <?php
                                    foreach ($colusers as $coluser) {
                                        echo escape(($coluser["fullname"] != "") ? $coluser["fullname"] : (string) $coluser["username"]) . "<br />";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="ListTools">
                                        <a
                                            onclick="return CentralSpaceLoad(this, true);"
                                            href="<?php echo $baseurl . '/pages/collection_edit.php?ref=' . escape($collections[$n]["ref"]); ?>"
                                        >
                                            <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }

                        if (in_array($collections[$n]["type"], $COLLECTION_PUBLIC_TYPES)) {
                            if ($collections[$n]["type"] == COLLECTION_TYPE_FEATURED) {
                                ?>
                                <tr>
                                    <td><?php echo escape($lang["theme"]); ?></td>
                                    <td><?php echo escape(($collections[$n]["allow_changes"] == 0) ? $lang["view"] : $lang["addremove"]); ?></td>
                                    <td><?php echo escape($lang["notavailableshort"]); ?></td>
                                    <td>
                                        <div class="ListTools">
                                            <a onclick="return CentralSpaceLoad(this, true);" href="<?php echo $baseurl . '/pages/collection_edit.php?ref=' . escape($collections[$n]["ref"]); ?>">
                                                <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            } else {
                                ?>
                                <tr>
                                    <td><?php echo escape($lang["public"]); ?></td>
                                    <td><?php echo escape(($collections[$n]["allow_changes"] == 0) ? $lang["view"] : $lang["addremove"]); ?></td>
                                    <td><?php echo escape($lang["notavailableshort"]); ?></td>
                                    <td>
                                        <div class="ListTools">
                                            <a
                                                onclick="return CentralSpaceLoad(this, true);"
                                                href="<?php echo $baseurl . '/pages/collection_edit.php?ref=' . escape($collections[$n]["ref"]); ?>"
                                            >
                                                <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </table>
                </div><!-- End of Listview --> 
                <div class="PanelShadow"></div>
            </div> <!-- End of RecordPanel -->
        </div> <!--  End of RecordBox -->
        <?php
    }
    ?>  
    <div class="BottomInpageNav"><?php pager(false); ?></div>
</div><!--  End of BasicsBox -->

<?php
include "../include/footer.php";
?>
