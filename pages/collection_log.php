<?php
include "../include/boot.php";
include "../include/authenticate.php";

$offset = getval("offset", 0, true);
$ref = getval("ref", "", true);

# Check access
if (!collection_readable($ref)) {
    exit($lang["no_access_to_collection"]);
}

if ((!is_numeric($offset)) || ($offset < 0)) {
    $offset = 0;
}

# pager
$per_page = getval("per_page_list_log", get_per_page_cookie(), true);
set_per_page_cookie($per_page);

include "../include/header.php";
$log     = get_collection_log($ref, $offset + $per_page);
$results = $log["total"];
$log     = $log["data"];
$totalpages = ceil($results / $per_page);
$curpage = floor($offset / $per_page) + 1;
$url = $baseurl . "/pages/collection_log.php?ref=" . $ref;
$jumpcount = 1;

# Fetch and translate collection name
$colinfo = get_collection($ref);
$colname = i18n_get_collection_name($colinfo);

if (!checkperm("b")) {
    # Add selection link to collection name.
    $colname = "<a href=\"" . $baseurl_short . "pages/collections.php?collection=" . $ref . "\" onClick=\"return CollectionDivLoad(this);\">" . $colname . "</a>";
}
?>

<div class="BasicsBox">
    <h1>
        <?php
        echo str_replace("%collection", $colname, escape($lang["collectionlogheader"]));
        render_help_link("user/collection-options");
        ?>
    </h1>

    <?php
    $intro = text("introtext");
    if ($intro != "") {
        ?>
        <p><?php echo escape($intro); ?></p>
        <?php
    }
    ?>

    <div class="TopInpageNav">
        <div class="InpageNavLeftBlock">
            <?php echo escape($lang["resultsdisplay"]); ?>:
            <?php
            for ($n = 0; $n < count($list_display_array); $n++) {
                if ($per_page == $list_display_array[$n]) {
                    ?>
                    <span class="Selected"><?php echo $list_display_array[$n]; ?></span>
                    <?php
                } else {
                    ?>
                    <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $url; ?>&per_page_list_log=<?php echo $list_display_array[$n]; ?>">
                        <?php echo $list_display_array[$n]; ?>
                    </a>
                    <?php
                } ?>&nbsp;|
            <?php }

            if ($per_page == 99999) {
                ?>
                <span class="Selected"><?php echo escape($lang["all"]); ?></span>
                <?php
            } else {
                ?>
                <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $url; ?>&per_page_list_log=99999">
                    <?php echo escape($lang["all"]); ?>
                </a>
                <?php
            } ?>

        </div>
        <?php pager(false); ?>
    </div>

    <div class="Listview">
        <table class="ListviewStyle">
            <!--Title row-->    
            <tr class="ListviewTitleStyle">
                <th><?php echo escape($lang["date"]); ?></th>
                <th><?php echo escape($lang["user"]); ?></th>
                <th><?php echo escape($lang["action"]); ?></th>
                <th><?php echo escape($lang["resourceid"]); ?></th>
                <th>
                    <?php
                    $field = get_fields(array($view_title_field));
                    if (!empty($field[0]["title"])) {
                        echo lang_or_i18n_get_translated($field[0]["title"], "fieldtitle-");
                    }
                    ?>
                </th>
                <?php hook("log_extra_columns_header"); ?>
            </tr>

            <?php
            for ($n = $offset; (($n < count($log)) && ($n < ($offset + $per_page))); $n++) {
                if (!isset($lang["collectionlog-" . $log[$n]["type"]])) {
                    $lang["collectionlog-" . $log[$n]["type"]] = "";
                }
                ?>
                <!--List Item-->
                <tr>
                    <td><?php echo escape(nicedate($log[$n]["date"], true, true, true)) ?></td>
                    <td><?php echo escape((string) $log[$n]["fullname"])?></td>
                    <td>
                        <?php
                        echo escape($lang["collectionlog-" . $log[$n]["type"]]) ;
                        if ($log[$n]["notes"] != "") {
                            ##  notes field contains user IDs, collection references and /or standard texts
                            ##  Translate the standard texts
                            $standard = array('#all_users', '#new_resource');
                            $translated   = array($lang["all_users"], $lang["new_resource"]);
                            $newnotes = " - " . str_replace($standard, $translated, $log[$n]["notes"]);
                            echo escape($newnotes);
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($log[$n]['resource'] != 0) { ?>
                            <a onClick="return CentralSpaceLoad(this,true);" href='<?php echo $baseurl_short?>pages/view.php?ref=<?php echo urlencode($log[$n]["resource"]) ?>'>
                                <?php echo $log[$n]["resource"]; ?>
                            </a>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($log[$n]['resource'] != 0) { ?>
                            <a onClick="return CentralSpaceLoad(this,true);" href='<?php echo $baseurl_short?>pages/view.php?ref=<?php echo urlencode($log[$n]["resource"]) ?>'>
                                <?php echo i18n_get_translated($log[$n]["title"])?>
                            </a>
                        <?php } ?>
                    </td>
                    <?php hook("log_extra_columns_row", "", array($log[$n], $colinfo)); ?>
                </tr> 
            <?php } ?>
        </table>
    </div> <!-- End of Listview -->

    <div class="BottomInpageNav"><?php pager(false); ?></div>
</div> <!-- End of BasicsBox -->

<?php
include "../include/footer.php";
?>
