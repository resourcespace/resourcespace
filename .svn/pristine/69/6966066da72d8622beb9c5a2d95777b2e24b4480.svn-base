<?php
#
# ResourceSpace Analytics - list my reports
#
include '../../include/boot.php';
include '../../include/authenticate.php';

global $baseurl;

$offset = getval("offset", 0, true);
if (array_key_exists("findtext", $_POST)) {
    $offset = 0;
} # reset page counter when posting
$findtext = getval("findtext", "");

$delete = getval("delete", "");
if ($delete != "" && enforcePostRequest(false)) {
    # Delete report
    ps_query("delete from user_report where ref= ? and user= ?", ['i', $delete, 'i', $userref]);
}

include __DIR__ . "/../../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["rse_analytics"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["teamcentre"],
            'href'  => $baseurl_short . "pages/team/team_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["rse_analytics"],
            'help'  => 'resourceadmin/analytics'
        )
    );

    renderBreadcrumbs($links_trail);

    $search_sql = "";
    $params = ['i', $userref];

    if ($findtext != "") {
        $search_sql = "and name like CONCAT('%', ? ,'%')";
        $params[] = 's';
        $params[] = $findtext;
    }

    $reports = ps_query("select " . columns_in("user_report") . " from user_report where user= ? $search_sql order by ref", $params);

    # pager
    $per_page = $default_perpage_list;
    $results = count($reports);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $url = "team_analytics.php?findtext=" . urlencode($findtext) . "&offset=" . $offset;
    $jumpcount = 1;
    ?>

    <div class="TopInpageNav">
        <a href="<?php echo $baseurl_short ?>pages/team/team_analytics_edit.php" onClick="return CentralSpaceLoad(this);">
            <?php echo LINK_CARET . escape($lang["report_create_new"]); ?>
        </a>

        <?php pager(); ?>
    </div>

    <form method=post id="reportsform" onSubmit="return CentralSpacePost(this,true);">
        <?php generateFormToken("reportsform"); ?>
        <input type=hidden name="delete" id="reportdelete" value="">
    </form>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th><?php echo escape($lang["report_name"]); ?></th>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                </th>
            </tr>

            <?php for ($n = $offset; (($n < count($reports)) && ($n < ($offset + $per_page))); $n++) { ?>
                <tr>
                    <td>
                        <div class="ListTitle">
                            <a href="team_analytics_edit.php?ref=<?php echo $reports[$n]["ref"]; ?>" onclick="return CentralSpaceLoad(this,true);">
                                <?php echo escape($reports[$n]["name"]);?>
                            </a>
                        </div>
                    </td>
                    <td>
                        <div class="ListTools">
                            <a href="team_analytics_edit.php?ref=<?php echo $reports[$n]["ref"]; ?>&backurl=<?php echo urlencode($url . "&offset=" . $offset . "&findtext=" . $findtext)?>" onclick="return CentralSpaceLoad(this,true);">
                                <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?>
                            </a>
                        <a href="#" onclick="if (confirm('<?php echo escape($lang["confirm-deletion"]); ?>')) {document.getElementById('reportdelete').value='<?php echo $reports[$n]["ref"]; ?>';document.getElementById('reportsform').submit();} return false;">
                            <i class="icon-trash-2"></i>&nbsp;<?php echo escape($lang["action-delete"]); ?>
                        </a>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
    <div class="BottomInpageNav"><?php pager(true); ?></div>
</div>

<div class="BasicsBox">
    <form method="post" onSubmit="return CentralSpacePost(this,true);">
        <?php generateFormToken("team_analytics_search"); ?>
        <div class="Question">
            <label for="find"><?php echo escape($lang["find"]) ?><br/></label>
            <div class="tickset">
                <div class="Inline">
                    <input
                        type=text
                        placeholder="<?php echo escape($lang['searchbytext'])?>"
                        name="findtext"
                        id="findtext"
                        value="<?php echo escape($findtext)?>"
                        maxlength="100"
                        class="shrtwidth"
                    />
                    <input type="button" value="<?php echo escape($lang['clearbutton']); ?>" onClick="jQuery('#findtext').val('');form.submit();" />
                    <input name="Submit" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<?php
include __DIR__ . "/../../include/footer.php";

