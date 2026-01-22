<?php

/**
 * User management start page (part of team center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkPermission_manage_users()) {
    exit(escape($lang["error-permissiondenied"]));
}

$offset = getval("offset", 0, true);
$find = trim(getval("find", ""));
$order_by = getval("order_by", "u.username");
$group = getval("group", 0, true);
$approval_state_text = array(0 => $lang["notapproved"],1 => $lang["approved"], 2 => $lang["disabled"]);
$backurl = getval("backlink", "");

# Pager
$per_page = getval("per_page_list", get_per_page_cookie(), true);
set_per_page_cookie($per_page);


if (array_key_exists("find", $_POST)) {
    $offset = 0;
} # reset page counter when posting

if (getval("newuser", "") != "" && !hook("replace_create_user_save") && enforcePostRequest(getval("ajax", false))) {
    $new = new_user(getval("newuser", ""));
    if ($new === false) {
        $error = $lang["useralreadyexists"];
    } elseif ($new == -2) {
        $error = $lang["userlimitreached"];
    } else {
        hook("afterusercreated");
        redirect($baseurl_short . "pages/team/team_user_edit.php?ref=" . $new);
    }
}

function show_team_user_filter_search()
{
    global $baseurl_short,$lang,$group,$find;
    $groups = get_usergroups(true);
    ?>
    <div class="BasicsBox">
        <form method="get" action="<?php echo $baseurl_short?>pages/team/team_user.php">
            <div class="Question">  
                <label for="group"><?php echo escape($lang["group"]); ?></label>
                <div class="tickset">
                    <div class="Inline">
                        <select name="group" id="group" onChange="this.form.submit();">
                            <option value="0" <?php echo ($group == 0) ? " selected" : ''; ?>>
                                <?php echo escape($lang["all"]); ?>
                            </option>
                            <?php for ($n = 0; $n < count($groups); $n++) { ?>
                                <option
                                    value="<?php echo (int) $groups[$n]["ref"]; ?>"
                                    <?php echo ($group == $groups[$n]["ref"]) ? " selected" : ''; ?>>
                                    <?php echo escape($groups[$n]["name"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="clearerleft"></div>
            </div>
        </form>
    </div>

    <div class="BasicsBox">
        <form method="get" action="<?php echo $baseurl_short?>pages/team/team_user.php">
            <div class="Question">
                <label for="find"><?php echo escape($lang["searchusers"])?></label>
                <div class="tickset">
                    <div class="Inline">
                        <input type=text name="find" id="find" value="<?php echo escape($find) ?>" maxlength="100" class="shrtwidth" />
                    </div>
                    <div class="Inline">
                        <input name="Submit" type="submit" value="<?php echo escape($lang["searchbutton"])?>" />
                    </div>
                </div>
                <div class="clearerleft"></div>
            </div>
        </form>
    </div>
    <?php
}

include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["manageusers"]); ?></h1>
    <?php
    // Breadcrumbs links
    if (strpos($backurl, "pages/admin/admin_group_management.php") !== false) {
        // Came from Manage user groups page
        $links_trail = array(
            array(
                'title' => $lang["systemsetup"],
                'href'  => $baseurl_short . "pages/admin/admin_home.php",
                'menu' =>  true
            ),
            array(
                'title' => $lang["page-title_user_group_management"],
                'href'  => $backurl
            )
        );
    } else {
        // Assume we came from Manage users page
        $links_trail = array(
            array(
                'title' => $lang["teamcentre"],
                'href'  => $baseurl_short . "pages/team/team_home.php",
                'menu' =>  true
            )
        );
    }

    $links_trail[] = array(
        'title' => $lang["manageusers"],
    );

    renderBreadcrumbs($links_trail);
    ?>

    <p class="PageIntrotext">
        <?php
        echo escape(text("introtext"));
        render_help_link('systemadmin/creating-users');
        ?>
    </p>

    <?php if (isset($error)) { ?>
        <div class="FormError">!! <?php echo $error?> !!</div>
    <?php }

    if ($team_user_filter_top) {
        show_team_user_filter_search();
    }

    $groups     = get_usergroups(true);
    # Fetch users
    $usersfound = false;

    $users_sql  = get_users($group, $find, $order_by, true, $offset + $per_page, "", true);
    $users      = sql_limit_with_total_count($users_sql, $per_page, $offset);
    $results    = $users["total"];
    $usersfound = count($users["data"]) > 0;

    if ($usersfound == 0) {
        // No results, go to last page
        $offset     = floor(MAX(($results - 1), 0) / $per_page) * $per_page;
        $users      = sql_limit_with_total_count($users_sql, $per_page, $offset);
        $results    = $users["total"];
    }

    $users      = $users["data"];
    $totalpages = ceil($results / $per_page);
    $curpage    = floor($offset / $per_page) + 1;

    $pageurl = generateURL(
        $baseurl_short . "pages/team/team_user.php",
        ["group"     => $group,
        "order_by"  => $order_by,
        "find"      => $find]
    );

    $jumpcount = 1;

    # Create an a-z index
    $atoz = "<div class=\"InpageNavLeftBlock\">";

    if ($find == "") {
        $atoz .= "<span class='Selected'>";
    }

    $atoz .= "<a href=\"" . $baseurl . "/pages/team/team_user.php?order_by=u.username&group=" . $group . "&find=\" onClick=\"return CentralSpaceLoad(this);\">" . $lang["viewall"] . "</a>";

    if ($find == "") {
        $atoz .= "</span>";
    }

    $atoz .= "&nbsp;&nbsp;";

    for ($n = ord("A"); $n <= ord("Z"); $n++) {
        if ($find == chr($n)) {
            $atoz .= "<span class='Selected'>";
        }
        $atoz .= "<a href=\"" . $baseurl . "/pages/team/team_user.php?order_by=u.username&group=" . $group . "&find=" . chr($n) . "\" onClick=\"return CentralSpaceLoad(this);\">&nbsp;" . chr($n) . "&nbsp;</a> ";
        if ($find == chr($n)) {
            $atoz .= "</span>";
        }
        $atoz .= " ";
    }

    $atoz .= "</div>";
    ?>

    <div class="TopInpageNav">
        <div class="TopInpageNavLeft">
            <?php echo $atoz?>
            <div class="InpageNavLeftBlock">
                <?php echo escape($lang["resultsdisplay"])?>:
                <?php
                for ($n = 0; $n < count($list_display_array); $n++) {
                    if ($per_page == $list_display_array[$n]) {
                        ?>
                        <span class="Selected"><?php echo (int) $list_display_array[$n]; ?></span>
                        <?php
                    } else {
                        $url = generateURL(
                            $baseurl_short . "pages/team/team_user.php",
                            ["group"     => $group,
                            "order_by"  => $order_by,
                            "find"      => $find],
                            ["per_page_list" => (int)$list_display_array[$n]]
                        );
                        ?>
                        <a
                            href="<?php echo $url;?>"
                            onClick="return CentralSpaceLoad(this);"
                        ><?php echo urlencode((int)$list_display_array[$n])?>
                        </a>
                        <?php
                    }
                    ?>&nbsp;|<?php
                }

                if ($per_page == 99999) { ?>
                    <span class="Selected"><?php echo escape($lang["all"])?></span>
                    <?php
                } else {
                    $url = generateURL(
                        $baseurl_short . "pages/team/team_user.php",
                        ["group"     => $group,
                        "order_by"  => $order_by,
                        "find"      => $find],
                        ["per_page_list" => 99999]
                    );
                    ?>
                    <a
                        href="<?php echo $url; ?>"
                        onClick="return CentralSpaceLoad(this);"
                        ><?php echo escape($lang["all"])?>
                    </a>
                    <?php
                } ?>
            </div>
        </div>
        <?php pager(false, true, ['url' => generateURL($baseurl_short . "pages/team/team_user.php", ["group" => $group, "order_by"  => $order_by, "find" => $find]), 'per_page' => $per_page]); ?>
        <div class="clearerleft"></div>
    </div>

    <div class="Listview">
        <?php
        function addColumnHeader($orderName, $labelKey)
        {
            global $baseurl, $group, $order_by, $find, $lang;

            if ($order_by == $orderName) {
                $image = '<span class="ASC"></span>';
            } elseif ($order_by == $orderName . ' desc') {
                $image = '<span class="DESC"></span>';
            } else {
                $image = '';
            }

            $column_header_url = generateURL(
                $baseurl . "/pages/team/team_user.php",
                ["offset"   => "0",
                "group"     => $group,
                "order_by"  => $orderName . ($order_by == $orderName ? ' desc' : ''),
                "find"      => $find]
            );
            ?>
            <th>
                <a href="<?php echo $column_header_url?>" onClick="return CentralSpaceLoad(this);">
                    <?php echo escape($lang[$labelKey]) . $image ?>
                </a>
            </th>
            <?php
        }
        ?>

        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <?php
                addColumnHeader('u.username', 'username');
                addColumnHeader('u.fullname', 'fullname');
                addColumnHeader('g.name', 'group');
                addColumnHeader('email', 'email');
                addColumnHeader('created', 'created');
                addColumnHeader('approved', 'status');
                addColumnHeader('last_active', 'lastactive');
                ?>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"])?></div>
                </th>
            </tr>

            <?php
            // Parse $url var as this is being manipulated by the pager(). This allows us to build correct URLs later on (e.g for team_user_edit_url)
            $url_parse = parse_url($pageurl);
            $url_qs = [];
            if (isset($url_parse['query'])) {
                parse_str($url_parse['query'], $url_qs);
            }

            for ($n = 0; ($n < count($users) && $n < ($offset + $per_page)); $n++) {
                $team_user_edit_params = array(
                    'ref' => $users[$n]["ref"],
                    'backurl' => generateURL($url_parse['path'], $url_qs, ['offset' => $offset]),
                );

                $team_user_edit_url = generateURL("{$baseurl}/pages/team/team_user_edit.php", $team_user_edit_params);

                $team_user_log_params = array(
                    'actasuser' => $users[$n]["ref"],
                    'backurl' => generateURL($url_parse['path'], $url_qs, ['offset' => $offset]),
                );

                $team_user_log_url = generateURL("{$baseurl}/pages/admin/admin_system_log.php", $team_user_log_params);

                ?>
                <tr>
                    <td>
                        <div class="ListTitle">
                            <a href="<?php echo $team_user_edit_url; ?>" onClick="return CentralSpaceLoad(this, true);">
                                <?php echo escape($users[$n]["username"]); ?>
                            </a>
                        </div>
                    </td>
                    <td><?php echo escape((string)$users[$n]["fullname"]); ?></td>
                    <td><?php echo escape(i18n_get_translated($users[$n]["groupname"])); ?></td>
                    <td><?php echo htmlentities((string)$users[$n]["email"]); ?></td>
                    <td><?php echo nicedate($users[$n]["created"]); ?></td>
                    <td><?php echo $approval_state_text[$users[$n]["approved"]]; ?></td>
                    <td><?php echo nicedate($users[$n]["last_active"], true, true, true); ?></td>
                    <td>
                        <div class="ListTools">
                            <a href="<?php echo $team_user_log_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                                <i class="icon-history"></i>&nbsp;<?php echo escape($lang["log"])?>
                            </a>
                            &nbsp;
                            <a href="<?php echo $team_user_edit_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                                <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"])?>
                            </a>
                            <?php
                            if ($userref != $users[$n]["ref"]) {
                                // Add message link
                                $message_link_url = generateURL(
                                    $baseurl_short . "pages/user/user_message.php",
                                    ["msgto" => $users[$n]["ref"]]
                                );
                                ?>
                                <a
                                    href=<?php echo $message_link_url ?>
                                    onClick="return CentralSpaceLoad(this,true);"
                                >
                                    <i class="icon-mail"></i>&nbsp;<?php echo escape($lang["message"])?>
                                </a>
                                <?php
                            }
                            ?>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>

    <div class="BottomInpageNav">
        <div class="BottomInpageNavLeft">
            <strong><?php echo escape($lang["total"] . ": " . $results); ?> </strong>
            <?php echo escape($lang["users"]); ?>
        </div>

        <?php pager(false); ?>
    </div>
</div>

<?php if (!$team_user_filter_top) {
    show_team_user_filter_search();
} ?>

<div class="BasicsBox">
    <form id="new_user_form" method="post" action="<?php echo $baseurl_short?>pages/team/team_user.php">
        <?php generateFormToken("create_new_user"); ?>
        <div class="Question">
            <label for="newuser"><?php echo escape($lang["createuserwithusername"])?></label>
            <div class="tickset">
                <div class="Inline">
                    <input type=text name="newuser" id="newuser" maxlength="50" class="shrtwidth" />
                </div>
                <div class="Inline">
                    <input name="Submit" id="create_user_button" type="submit" value="<?php echo escape($lang["create"])?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<?php
hook('render_options_to_create_users');

if ($user_purge) {
    ?>
    <div class="BasicsBox">
        <div class="Question">
            <label><?php echo escape($lang["purgeusers"])?></label>
            <div class="Fixed">
                <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl ?>/pages/team/team_user_purge.php">
                    <?php echo LINK_CARET . escape($lang["purgeusers"])?>
                </a>
            </div>
            <div class="clearerleft"></div>
        </div>
    </div>
    <?php
}
?>

<div class="BasicsBox">
    <div class="Question">
        <label><?php echo escape($lang["usersonline"])?></label>
        <div class="Fixed">
            <?php
            $active = get_active_users();
            for ($n = 0; $n < count($active); $n++) {
                if ($n > 0) {
                    echo", ";
                }
                echo "<b><a href='" . generateURL($baseurl . '/pages/team/team_user_edit.php', ['ref' => $active[$n]['ref'], 'backurl' => generateURL($url, ['offset' => $offset])]) . "' onClick='return CentralSpaceLoad(this,true);'>" . escape($active[$n]["username"]) . "</a></b> (" . escape($active[$n]["t"]) . ")";
            }
            ?>
        </div>
        <div class="clearerleft"></div>
    </div>
</div>

<?php
include "../../include/footer.php";