<?php

function HookGrant_editEditeditbeforeheader()
    {
    global $ref, $usergroup, $grant_edit_groups, $collection, $lang, $items;

    // Do we have access to do any of this, or is it a template
    if (!in_array($usergroup, $grant_edit_groups) || $ref<0) {
        return false;
    }

    // Check for Ajax POST to delete users
    $grant_edit_action=getval("grant_edit_action","");
    if ($grant_edit_action!="") {
        if ($grant_edit_action=="delete") {
            $remove_user=getval("remove_user","",true);
            $remove_group = getval("remove_group", "", true);
            if ($remove_user != "") {
                ps_query(
                    "DELETE FROM grant_edit WHERE resource = ? AND user = ?",
                    ["i",$ref,"i",$remove_user]
                );
                exit ("SUCCESS");
            }
            if ($remove_group != "") {
                ps_query(
                    "DELETE FROM grant_edit WHERE resource = ? AND usergroup = ?",
                    ['i', $ref, 'i', $remove_group]
                );
                exit ("SUCCESS");
            }
        }
        exit("FAILED");
    }

    # If 'users' is specified (i.e. access is private) then rebuild users list
    $users=getval("users",false);
    if ($users!=false) {
        # Build a new list and insert
        $smart_groups = resolve_userlist_groups_smart($users);
        $users = resolve_userlist_groups($users);
        $ulist = array_unique(trim_array(explode(",",$users)));
        $urefs = ps_array(
            "SELECT ref value FROM user WHERE username IN (" . ps_param_insert(count($ulist)) . ")",
            ps_param_fill($ulist,"s")
        );
        $resources_added = [];

        $grant_edit_expiry=getval("grant_edit_expiry","");
        if (count($urefs)>0) {
            if ((int)$collection > 0 || count($items??[])>1) {
                foreach ($items as $collection_resource) {
                    $parameters = array();
                    $insertvalue = array();
                    foreach ($urefs as $uref) {
                        $insertvalue[] = "(? ,? ,?)";
                        $expiry = ($grant_edit_expiry == "") ? null : $grant_edit_expiry;
                        $parameters = array_merge($parameters, array("i",$collection_resource,"i",$uref,"s",$expiry));
                    }
                    ps_query(
                        "DELETE FROM grant_edit WHERE resource = ? AND user IN (" . ps_param_insert(count($urefs)) . ")",
                        array_merge(["i",$collection_resource], ps_param_fill($urefs,"i"))
                    );
                    ps_query(
                        "INSERT INTO grant_edit(resource,user,expiry) VALUES " . implode(",", $insertvalue),
                        $parameters
                    );
                    if(!in_array($collection_resource,$resources_added)) {
                    $resources_added[]=$collection_resource;
                    }
                }
            } else {
                $parameters = array();
                foreach ($urefs as $uref) {
                    $insertvalue[] = "(? ,? ,?)";
                    $expiry = ($grant_edit_expiry == "") ? null : $grant_edit_expiry;
                    $parameters = array_merge($parameters, array("i",$ref,"i",$uref,"s",$expiry));
                }
                ps_query(
                    "DELETE FROM grant_edit WHERE resource = ? AND user IN (" . ps_param_insert(count($urefs)) . ")",
                    array_merge(["i",$ref], ps_param_fill($urefs,"i"))
                );
                ps_query(
                    "INSERT INTO grant_edit(resource,user,expiry) VALUES " . implode(",", $insertvalue),
                    $parameters
                );

                if(!in_array($ref,$resources_added)) {
                $resources_added[]=$ref;
                }
            }
        }
        if ($smart_groups !== '') {
            $groups = explode(',', $smart_groups);
            if ((int)$collection > 0){
                $resources = $items;
            } else {
                $resources = [$ref];
            }
            foreach ($resources as $resource){
                $insert_string = [];
                $params = [];
                foreach ($groups as $group){
                    $insert_string[] = '(?, ?, ?)';
                    $params = array_merge(
                        $params,
                        ['i', $resource, 'i', trim($group), 's', ($grant_edit_expiry == '' ? null : $grant_edit_expiry)]
                    );
                }
                ps_query(
                    'DELETE FROM grant_edit WHERE resource = ? AND usergroup IN (' . ps_param_insert(count($groups)) . ')',
                    array_merge(['i', $resource], ps_param_fill($groups, 'i'))
                );
                ps_query(
                    'INSERT INTO grant_edit (resource, usergroup, expiry) VALUES ' . implode(',', $insert_string),
                    $params
                );

                if(!in_array($resource,$resources_added)) {
                    $resources_added[]=$resource;
                }
            }
        }

        foreach ($resources_added as $resource) {
            $expiry = ($grant_edit_expiry!="")?nicedate($grant_edit_expiry):$lang['never'];
            resource_log($resource,'s',"","Grant Edit -  " . $users . " - " . $lang['expires'] . ": " . $expiry);
        }
    }

    return true;
    }

/**
 * Needed to prevent user changing the archive stat otherwise a user with temporary edit access to an active resource
 * could change it from active to pending submission
 *
 * @return bool
 */
function HookGrant_editEditEditstatushide()
    {
    global $status, $resource;
    if(!checkperm("e" . $resource["archive"]))
        {return true;}
    return false;
    }

function HookGrant_editEditAppendcustomfields()
    {
    global $ref,$lang,$baseurl,$grant_editusers, $multiple, $usergroup, $grant_edit_groups, $collapsible_sections;
    global $sharing_userlists;

    // Do we have access to see this?
    if(!in_array($usergroup, $grant_edit_groups) || $ref<0){return;}

    $grant_editusers  = ps_query("SELECT ea.user, u.fullname, u.username, ea.expiry FROM grant_edit ea LEFT JOIN user u ON u.ref = ea.user WHERE ea.resource = ? AND ea.user IS NOT NULL AND (ea.expiry IS NULL OR ea.expiry >= NOW()) ORDER BY expiry, u.username", array("i",$ref));
    $grant_editgroups = ps_query('SELECT u.ref, u.name, ea.expiry FROM grant_edit ea LEFT JOIN usergroup u on u.ref = ea.usergroup WHERE ea.usergroup IS NOT NULL AND ea.resource = ? AND (ea.expiry is NULL OR ea.expiry >= NOW()) ORDER BY expiry', ['i', $ref]);
    ?>
    <h2 id="resource_custom_access" <?php echo ($collapsible_sections) ? ' class="CollapsibleSectionHead"' : ''; ?>><?php echo escape($lang["grant_edit_title"]); ?></h2>
    <?php

    if ($multiple)
        { ?>
        <div class="Question" id="editmultiple_grant_edit">
            <input name="editthis_grant_edit" id="editthis_grant_edit" value="yes" type="checkbox" onClick="var q=document.getElementById('grant_edit_fields');if (q.style.display!='block') {q.style.display='block';} else {q.style.display='none';}">
            <label id="editthis_grant_edit_label" for="editthisenhancedaccess>"><?php echo escape($lang["grant_edit_title"]); ?></label>
        </div><?php
        }

    if(count($grant_editusers)>0 && !$multiple)
        {
        ?>

        <div class="Question" id="question_grant_edit" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
            <label><?php echo escape($lang["grant_edit_list"]); ?></label>
            <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
            <th><?php echo escape($lang['user']);?></th>
            <th><?php echo escape($lang['expires']);?></th>
            </tr>
            <?php
            foreach($grant_editusers as $grant_edituser)
                {
                echo "<tr id='grant_edit" . $grant_edituser['user'] . "'>
						<td>" . (($grant_edituser['fullname']!="")?$grant_edituser['fullname']:$grant_edituser['username']) . "</td>
						<td>" . (($grant_edituser['expiry']!="")?nicedate($grant_edituser['expiry']):$lang['never'])  . "</td>
						<td><a href='#' onclick='if (confirm(\"" . $lang['grant_edit_delete_user'] . " " . (($grant_edituser['fullname']!="")?$grant_edituser['fullname']:$grant_edituser['username']) . "\")){remove_grant_edit(" . $grant_edituser['user'] . ");}'>&gt;&nbsp;" . $lang['action-delete']  . "</a></td>
					  </tr>
					";
                }
            ?>
            </table>
        </div>
        <script>
        function remove_grant_edit(user)
            {
            jQuery.ajax({
                async: true,
                url: '<?php echo $baseurl ?>/pages/edit.php',
                type: 'POST',
                data: {
                    ref:'<?php echo $ref ?>',
                    grant_edit_action:'delete',
                    remove_user:user,
                    <?php echo generateAjaxToken('remove_grant_edit'); ?>
                },
                timeout: 4000,
                success: function(result) {
                    if(result == 'SUCCESS')
                        {
                        jQuery('#grant_edit' + user).remove();
                        }
                    },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    response = "err--" + XMLHttpRequest.status + " -- " + XMLHttpRequest.statusText;
                    response = DOMPurify.sanitize(response);
                    },
            });
            }

        </script>
        <?php
        }
    if (count($grant_editgroups) > 0 && !$multiple){
        ?>

        <div class="Question" id="question_grant_edit" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
            <label><?php echo escape($lang["grant_edit_group_list"]); ?></label>
            <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
            <th><?php echo escape($lang['user_group']);?></th>
            <th><?php echo escape($lang['expires']);?></th>
            </tr>
            <?php
            foreach($grant_editgroups as $grant_editgroup)
                {
                echo "<tr id='grant_edit" . (int) $grant_editgroup['ref'] . "'>
						<td>" . escape($grant_editgroup['name']) . "</td>
						<td>" . escape(($grant_editgroup['expiry'] != "") ? nicedate($grant_editgroup['expiry']) : $lang['never'])  . "</td>
						<td><a href='#' onclick='if (confirm(\"" . escape($lang['grant_edit_delete_user']) . " " . escape($grant_editgroup['name']) . "\")){remove_grant_edit_group(" . (int) $grant_editgroup['ref'] . ");}'>&gt;&nbsp;" . escape($lang['action-delete'])  . "</a></td>
					  </tr>
					";
                }
            ?>
            </table>
        </div>
        <script>
        function remove_grant_edit_group(group)
            {
            jQuery.ajax({
                async: true,
                url: '<?php echo $baseurl ?>/pages/edit.php',
                type: 'POST',
                data: {
                    ref:'<?php echo $ref ?>',
                    grant_edit_action:'delete',
                    remove_group:group,
                    <?php echo generateAjaxToken('remove_grant_edit'); ?>
                },
                timeout: 4000,
                success: function(result) {
                    if(result == 'SUCCESS')
                        {
                        jQuery('#grant_edit' + group).remove();
                        }
                    },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    response = "err--" + XMLHttpRequest.status + " -- " + XMLHttpRequest.statusText;
                    response = DOMPurify.sanitize(response);
                    },
            });
            }

        </script>
        <?php
    }

    $sharing_userlists=false;
    ?>
    <div id="grant_edit_fields" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
        <div class="Question" id="grant_edit_select" >
            <label for="users"><?php echo escape($lang["grant_edit_add"]); ?></label><?php include "../include/user_select.php"; ?>
            <div class="clearerleft"> </div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["grant_edit_date"]); ?></label>
            <select name="grant_edit_expiry" class="stdwidth">
            <option value=""><?php echo escape($lang["never"]); ?></option>
            <?php for ($n=1;$n<=150;$n++)
                {
                $date=time()+(60*60*24*$n);
                ?><option <?php $d=date("D",$date);if (($d=="Sun") || ($d=="Sat")) { ?>style="background-color:#cccccc"<?php } ?> value="<?php echo date("Y-m-d",$date)?>" <?php if(substr(getval("editexpiration",""),0,10)==date("Y-m-d",$date)){echo "selected";}?>><?php echo nicedate(date("Y-m-d",$date),false,true)?></option>
                <?php
                }
            ?>
            </select>
            <div class="clearerleft"> </div>
        </div>
    </div>

    <?php
    return false;
    }

