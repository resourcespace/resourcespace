<?php

/**
 * Manage related keywords page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("k")) {
    exit("Permission denied.");
}

include "../../include/research_functions.php";

$offset = getval("offset", 0, true);
$find = getval("find", "");

if (array_key_exists("find", $_POST)) {
    $offset = 0;
} # reset page counter when posting

include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1>
        <?php
        echo escape($lang["managerelatedkeywords"]);
        render_help_link('resourceadmin/related-keywords');
        ?>
    </h1>
    
    <p><?php echo escape(text("introtext")); ?></p>
    
    <?php
    $keywords = get_grouped_related_keywords($find);

    # pager
    $per_page = $default_perpage_list;
    $results = count($keywords);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $url = "team_related_keywords.php?find=" . urlencode($find);
    $jumpcount = 1;
    ?>
    
    <div class="TopInpageNav"><?php pager();  ?></div>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th><?php echo escape($lang["keyword"]); ?></th>
                <th><?php echo escape($find == "" ? $lang["relatedkeywords"] : $lang["matchingrelatedkeywords"]); ?></th>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                </th>
            </tr>

            <?php
            for ($n = $offset; (($n < count($keywords)) && ($n < ($offset + $per_page))); $n++) {
                $edit_url = generateURL(
                    $baseurl_short . "pages/team/team_related_keywords_edit.php",
                    ["keyword" => $keywords[$n]["keyword"]]
                );
                ?>
                <tr>
                    <td>
                        <div class="ListTitle">
                            <a href="<?php echo $edit_url?>"><?php echo escape($keywords[$n]["keyword"])?>
                        </div>
                    </td>
                    <td><?php echo tidy_trim(escape($keywords[$n]["related"]), 45)?></td>
                    <td>
                        <div class="ListTools">
                            <a 
                                onClick="return CentralSpaceLoad(this,true);" 
                                href="<?php echo $edit_url?>"
                            >
                                <?php echo '<i class="icon-square-pen"></i>&nbsp' . escape($lang["action-edit"])?>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
    <div class="BottomInpageNav"><?php pager(false); ?></div>
</div>

<div class="BasicsBox">
    <form method="GET" action="<?php echo $baseurl_short?>pages/team/team_related_keywords.php">
        <div class="Question">
            <label for="find"><?php echo escape($lang["searchkeyword"]); ?></label>
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

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short?>pages/team/team_related_keywords_edit.php">
        <?php generateFormToken("related_keywords"); ?>
        <div class="Question">
            <label for="create"><?php echo escape($lang["newkeywordrelationship"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <input type=text name="keyword" id="keyword" value="" maxlength="100" class="shrtwidth" />
                </div>
                <div class="Inline">
                    <input name="createsubmit" type="submit" value="<?php echo escape($lang["create"]); ?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<?php
include "../../include/footer.php";
?>
