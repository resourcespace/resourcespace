<?php

/**
 * Manage content string page (part of System area)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("o")) {
    exit("Permission denied.");
}

include "../../include/research_functions.php";

$offset = getval("offset", 0, true);

if (array_key_exists("findpage", $_POST) || array_key_exists("findname", $_POST) || array_key_exists("findtext", $_POST)) {
    $offset = 0;
} # reset page counter when posting

$findpage = getval("findpage", "");
$findname = getval("findname", "");
$findtext = getval("findtext", "");
$page = getval("page", "");
$name = getval("name", "");

$extended = false;

if ($findpage != "" || $findname != "" || $findtext != "") {
  # Extended view - show the language and user group columns when searching as multiple languages/groups may be returned rather than
  # the single entry returned when not searching.
    $extended = true;
    $groups = get_usergroups();
}

if ($page && $name && enforcePostRequest(false)) {
    redirect($baseurl_short . "pages/admin/admin_content_edit.php?page=$page&name=$name&offset=$offset&save=true&custom=1");
}

include "../../include/header.php";
?>

<div class="BasicsBox" style="position:relative;">
    <h1><?php echo escape($lang["managecontent"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["managecontent"],
        )
    );

    renderBreadcrumbs($links_trail);

    $int_text = text("introtext");
    echo empty($int_text) ? "" : "<p>" . escape($int_text) . "</p>";
    $text = get_all_site_text($findpage, $findname, $findtext);

    # pager
    $per_page = $default_perpage_list;
    $results = count($text);
    $totalpages = ceil($results / $per_page);
    $curpage = floor($offset / $per_page) + 1;
    $url = $baseurl_short . "pages/admin/admin_content.php?findpage=" . urlencode($findpage) . "&findname=" . urlencode($findname) . "&findtext=" . urlencode($findtext);
    $jumpcount = 1;
    ?>
    
    <div style="float:right;margin-top:-5px;">
        <div class="TopInpageNav">
            <?php pager();?>
        </div>
    </div>

    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th width="10%"><?php echo escape($lang["page"]); ?></th>
                <th width="25%"><?php echo escape($lang["name"]); ?></th>
                <?php if ($extended) { ?>
                    <th width="10%"><?php echo escape($lang["language"]); ?></th>
                    <th width="10%"><?php echo escape($lang["group"]); ?></th>
                <?php } ?>
                <th width="<?php echo $extended ? "40" : "55"; ?>%"><?php echo escape($lang["text"])?></th>
                <th width="10%"><div class="ListTools"><?php echo escape($lang["tools"]); ?></div></th>
            </tr>

            <?php
            for ($n = $offset; (($n < count($text)) && ($n < ($offset + $per_page))); $n++) {
                $url = $baseurl_short . "pages/admin/admin_content_edit.php?page=" . urlencode($text[$n]["page"]) . "&name=" . urlencode($text[$n]["name"]) . "&editlanguage=" . urlencode($text[$n]["language"]) . "&editgroup=" . (is_null($text[$n]["group"]) ? "" : urlencode($text[$n]["group"])) . "&findpage=" . urlencode($findpage) . "&findname=" . urlencode($findname) . "&findtext=" . urlencode($findtext) . "&offset=" . urlencode($offset);
                ?>
                <tr>
                    <td>
                        <div class="ListTitle">
                            <a href="<?php echo $url ?>">
                                <?php echo escape($text[$n]["page"] == "" || $text[$n]["page"] == "all" ? $lang["all"] : $text[$n]["page"]);?>
                            </a>
                        </div>
                    </td>
                    
                    <td>
                        <div class="ListTitle">
                            <a href="<?php echo $url ?>" onClick="return CentralSpaceLoad(this,true);">
                                <?php echo escape($text[$n]["name"])?>
                            </a>
                        </div>
                    </td>
                    
                    <?php if ($extended) {
                    # Extended view. Show the language and group when searching, as these variants are expanded out when searching.

                    # Resolve the user group name.
                        $group_resolved = $lang["deleted"];
                        if ($text[$n]["group"] == "") {
                            $group_resolved = $lang["all"];
                        } else {
                        # resolve
                            foreach ($groups as $group) {
                                if ($group["ref"] == $text[$n]["group"]) {
                                    $group_resolved = $group["name"];
                                }
                            }
                        }
                        ?>
                        <td><?php echo $text[$n]["language"]; ?></td>
                        <td><?php echo $group_resolved ?></td>
                    <?php } ?>
                    
                    <td>
                        <a href="<?php echo $url ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape(tidy_trim($text[$n]["text"], 100)); ?>
                        </a>
                    </td>
                    
                    <td>
                        <div class="ListTools">
                            <a href="<?php echo $url ?>" onClick="return CentralSpaceLoad(this,true);">
                                <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?> 
                            </a>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>

    <div class="BottomInpageNav">
        <?php
        $url = $baseurl_short . 'pages/admin/admin_content.php?findpage=' . urlencode($findpage) . '&findname=' . urlencode($findname) . '&findtext=' . urlencode($findtext);
        pager(false);
        ?>
    </div>
</div>

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short?>pages/admin/admin_content.php" onsubmit="return CentralSpacePost(this);">
        <?php generateFormToken("admin_content_find"); ?>
        <div class="Question">
            <label for="find"><?php echo escape($lang["searchcontent"]); ?><br/><?php echo escape($lang["searchcontenteg"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <input type=text placeholder="<?php echo escape($lang['searchbypage']); ?>" name="findpage" id="findpage" value="<?php echo escape($findpage)?>" maxlength="100" class="shrtwidth" />
                    <input type=text placeholder="<?php echo escape($lang['searchbyname']); ?>" name="findname" id="findname" value="<?php echo escape($findname)?>" maxlength="100" class="shrtwidth" />
                    <input type=text placeholder="<?php echo escape($lang['searchbytext']); ?>" name="findtext" id="findtext" value="<?php echo escape($findtext)?>" maxlength="100" class="shrtwidth" />
                    <input type="button" value="<?php echo escape($lang['clearall']); ?>" onClick="jQuery('#findtext').val('');jQuery('#findpage').val('');jQuery('#findname').val('');form.submit();" />
                    <input name="Submit" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<?php if ($site_text_custom_create) { ?>
    <div class="BasicsBox">
        <form method="post" action="<?php echo $baseurl_short?>pages/admin/admin_content.php">
        <input type="hidden" name="custom" value="1"/>
            <?php generateFormToken("admin_content_new"); ?>
            <div class="Question">
                <label for="find"><?php echo escape($lang["addnewcontent"]); ?></label>
                <div class="tickset">
                    <div class="Inline">
                        <input type=text name="page" id="page" maxlength="50" class="shrtwidth" />
                    </div>
                    <div class="Inline">
                        <input type=text name="name" id="name" maxlength="50" class="shrtwidth" />
                    </div>
                    <div class="Inline">
                        <input name="Submit" type="submit" value="<?php echo escape($lang["create"]); ?>" />
                    </div>
                </div>
                <div class="clearerleft"> </div>
            </div>
        </form>
    </div>
<?php } ?>

<?php
include "../../include/footer.php";
?>
