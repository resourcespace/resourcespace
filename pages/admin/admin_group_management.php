<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

include "../../include/header.php";

$find = getval("find", "");
$filter_by_parent = getval("filterbyparent", "");
$filter_by_permissions = getval("filterbypermissions", "");

$sql_permission_filter_params = array();

if ($filter_by_permissions != "") {
    foreach (explode(",", $filter_by_permissions) as $permission) {
        $permission = trim($permission);
        if ($permission == "") {
            continue;
        }
        if (isset($sql_permission_filter)) {
            $sql_permission_filter .= " and 
            ";
        } else {
            $sql_permission_filter = "(";
        }
        # The filter will include usergroups with this permission either at the usergroup level or (if permissions are inherited) at the parent usergroup level
        $sql_permission_filter .= " ( FIND_IN_SET(binary ?,usergroup.permissions) OR ( FIND_IN_SET('permissions', usergroup.inherit_flags) AND FIND_IN_SET(binary ?,parentusergroup.permissions) ) ) ";
        $sql_permission_filter_params = array_merge($sql_permission_filter_params, array("s",$permission, "s",$permission));
    }
    $sql_permission_filter .= ")";
}

$offset = getval("offset", 0, true);
$order_by = getval("orderby", "name");

$sql_where = "";
$sql_params = array();

if ($find != "") {
    $sql_where = " and (usergroup.ref like ? or usergroup.name like ? or parentusergroup.name like ?)";
    $sql_params = array_merge($sql_params, array("s", "%" . $find . "%", "s", "%" . $find . "%", "s", "%" . $find . "%"));
}
if ($filter_by_parent != "") {
    $sql_where .= " and parentusergroup.ref = ?";
    $sql_params = array_merge($sql_params, array("i", $filter_by_parent));
}
if ($filter_by_permissions != "") {
    $sql_where .= " and $sql_permission_filter";
    $sql_params = array_merge($sql_params, $sql_permission_filter_params);
}

$offset = getval("offset", 0, true);
$order_by = getval("orderby", "name");

if (!in_array($order_by, array("ref","name","users","pname","ref desc","name desc","users desc","pname desc"))) {
    $order_by = "name";
}

$groups = ps_query(
    "
	select 
		usergroup.ref as ref,
		usergroup.name as name,
		count(user.ref) as users,
		if (usergroup.parent is not null and usergroup.parent<>'' and usergroup.parent<>'0' and (parentusergroup.name is null or parentusergroup.name=''),usergroup.ref,parentusergroup.ref) as pref,
		if (usergroup.parent is not null and usergroup.parent<>'' and usergroup.parent<>'0' and (parentusergroup.name is null or parentusergroup.name=''),'orphaned',parentusergroup.name) as pname,
		(usergroup.parent is not null and usergroup.parent<>'' and usergroup.parent<>'0' and (parentusergroup.name is null or parentusergroup.name='')) as orphaned
	from
		usergroup 
	left outer join usergroup parentusergroup
	on 	
		usergroup.parent=parentusergroup.ref
	left outer join user
	on
		usergroup.ref=user.usergroup where true" . $sql_where .
    " group by
		usergroup.ref
	order by {$order_by}",
    $sql_params
);

# pager
$per_page = $default_perpage_list;
$results = count($groups);
$totalpages = ceil($results / $per_page);
$curpage = floor($offset / $per_page) + 1;
$url = "admin_group_management.php";
$url_params = array("find" => $find,"orderby" => $order_by);

function addColumnHeader($orderName, $labelKey)
{
    global $baseurl, $order_by, $filter_by_parent, $filter_by_permissions, $find, $lang;

    if ($order_by == $orderName) {
        $image = '<span class="ASC"></span>';
    } elseif ($order_by == $orderName . ' desc') {
        $image = '<span class="DESC"></span>';
    } else {
        $image = '';
    }
    ?>

    <th>
        <a href="<?php echo $baseurl ?>/pages/admin/admin_group_management.php?<?php
        if ($find != "") {
            ?>&find=<?php echo escape($find);
        }
        if ($filter_by_parent != "") {
            ?>&filterbyparent=<?php echo escape($filter_by_parent);
        }
        if ($filter_by_permissions != "") {
            ?>&filterbypermissions=<?php echo escape($filter_by_permissions);
        }
        ?>&orderby=<?php echo $orderName . ($order_by == $orderName ? '+desc' : ''); ?>"
            onClick="return CentralSpaceLoad(this);"><?php echo escape($lang[$labelKey]) . $image ?>
        </a>
    </th>
    <?php
}
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["page-title_user_group_management"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["page-title_user_group_management"],
        )
    );

    renderBreadcrumbs($links_trail);
    ?>
    <p>
        <?php
        echo escape($lang['page-subtitle_user_group_management']);
        render_help_link("systemadmin/creating-user-groups");
        ?>
    </p>
    
    <div class="TopInpageNav">
        <div class="TopInpageNavLeft">
            <div class="InpageNavLeftBlock">&nbsp;</div>        
        </div>
        <?php pager(false); ?>
        <div class="clearerleft"></div>             
    </div>
    
    <div class="Listview">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <?php addColumnHeader("ref", "property-reference"); ?>
                <?php addColumnHeader("name", "property-user_group"); ?>
                <?php addColumnHeader("users", "users"); ?>
                <?php addColumnHeader("pname", "property-user_group_parent"); ?>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                </th>
            </tr>

            <?php
            $url_params = array(
                "offset" => $offset ?? '',
                "orderby" => $order_by ?? '',
                "filterbyparent" => $filter_by_parent ?? '',
                "find" => $find ?? '',
                "filterbypermissions" => $filter_by_permissions ?? ''
            );

            for ($n = $offset; (($n < count($groups)) && ($n < ($offset + $per_page))); $n++) {
                $edit_url = generateURL($baseurl_short . "pages/admin/admin_group_management_edit.php", array_merge(["ref" => $groups[$n]["ref"]], $url_params));
                $users_url = generateURL($baseurl_short . "pages/team/team_user.php", ["group" => $groups[$n]["ref"], "backlink" => generateURL($baseurl_short . "pages/admin/admin_group_management.php", $url_params)]);
                ?>
                <tr>
                    <td>
                        <a href="<?php echo $edit_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($groups[$n]["ref"]); ?>
                        </a>
                    </td>

                    <td>
                        <a href="<?php echo $edit_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($groups[$n]["name"]); ?>
                        </a>
                    </td>
                
                    <td>                    
                        <a href="<?php echo $users_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($groups[$n]["users"]); ?>
                        </a>
                    </td>

                    <td>
                        <?php if ($groups[$n]["orphaned"]) { ?>
                            <a href="<?php echo $edit_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                                &lt;<?php echo escape((string)$groups[$n]["pname"]) ;?>&gt;
                            </a>
                        <?php } else { ?>
                            <a
                                href="<?php echo $baseurl_short; ?>pages/admin/admin_group_management.php?filterbyparent=<?php echo $groups[$n]["pref"]; ?>"
                                onClick="return CentralSpaceLoad(this,false);">
                                <?php echo escape((string)$groups[$n]["pname"]); ?>
                            </a>
                        <?php } ?>
                    </td>

                    <td>
                        <div class="ListTools">
                            <a href="<?php echo $edit_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                                <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?>
                            </a>
                            &nbsp;
                            <a href="<?php echo $users_url; ?>" onClick="return CentralSpaceLoad(this,true);">
                                <i class="icon-users-round"></i>&nbsp;<?php echo escape($lang["users"]); ?>
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
        $url = "admin_group_management.php";
        $url_params = array("find" => $find,"orderby" => $order_by);
        pager(false);
        ?>
    </div>
</div><!-- end of BasicsBox -->

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short?>pages/admin/admin_group_management.php" onSubmit="return CentralSpacePost(this,false);">
        <?php generateFormToken("admin_group_management_find"); ?>
        <input type="hidden" name="orderby" value="<?php echo $order_by; ?>">

        <div class="Question">
            <label for="find"><?php echo escape($lang["property-search_filter"]); ?></label>
            <input name="find" type="text" class="medwidth" value="<?php echo escape($find); ?>">
            <input name="save" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="filterbyparent"><?php echo escape($lang['action-title_filter_by_parent_group']); ?></label>
            <div class="tickset">
                <select name="filterbyparent" class="medwidth" onchange="this.form.submit();">
                    <option value="">
                        <?php if ($filter_by_parent != "") {
                            echo escape($lang["removethisfilter"]);
                        } ?>
                    </option>

                    <?php
                    $groups = ps_query("
                        SELECT 	distinct 	
                            parentusergroup.ref AS ref,
                            parentusergroup.name AS name
                        FROM 
                            usergroup 
                        LEFT OUTER JOIN usergroup parentusergroup
                        ON 			
                            usergroup.parent=parentusergroup.ref		
                        WHERE parentusergroup.ref IS NOT null
                        ORDER BY usergroup.name");

                    foreach ($groups as $group) { ?>
                        <option
                            <?php if ($filter_by_parent != "" && $filter_by_parent == $group['ref']) { ?>
                                selected="true"
                            <?php } ?>
                            value="<?php echo $group['ref']; ?>">
                            <?php echo $group['name']; ?>
                        </option>
                        <?php
                    } ?>
                </select>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="filterbypermissions"><?php echo escape($lang["action-title_filter_by_permissions"]); ?></label>
            <input name="filterbypermissions" type="text" class="medwidth" value="<?php echo escape($filter_by_permissions); ?>">
            <input name="save" type="submit" value="<?php echo escape($lang["action-title_apply"]); ?>">
            <div class="clearerleft"></div>
        </div>

        <div class="FormHelp">
            <div class="FormHelpInner"><?php echo escape($lang["fieldhelp-permissions_filter"]); ?></div>
        </div>

        <?php if ($find != "" || $filter_by_permissions != "" || $filter_by_parent != "") { ?>
            <div class="QuestionSubmit">
                <input
                    name="buttonsave"
                    type="submit"
                    onclick="CentralSpaceLoad('admin_group_management.php?orderby=<?php echo $order_by; ?>',false);"
                    value="<?php echo escape($lang["clearall"]); ?>"
                >
            </div>
        <?php } ?>
    </form>
</div>

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short; ?>pages/admin/admin_group_management_edit.php" onSubmit="return CentralSpacePost(this,false);">
        <?php generateFormToken("admin_group_management"); ?>
        <div class="Question">
            <label for="name"><?php echo escape($lang['action-title_create_user_group_called']); ?></label>         
            <div class="tickset">
                <div class="Inline">
                    <input name="newusergroupname" type="text" value="" class="shrtwidth">  
                </div>
                <div class="Inline">
                    <input name="Submit" type="submit" value="<?php echo escape($lang["create"]); ?>" onclick="return (this.form.elements[0].value!='');">
                </div>
            </div>          
            <div class="clearerleft"></div>     
        </div>

        <?php if ($offset) { ?>
            <input type="hidden" name="offset" value="<?php echo $offset; ?>">
            <?php
        }

        if ($order_by) { ?>
            <input type="hidden" name="order_by" value="<?php echo $order_by; ?>">
            <?php
        }
        ?> 
    </form>
</div>

<?php
include "../../include/footer.php";
?>
