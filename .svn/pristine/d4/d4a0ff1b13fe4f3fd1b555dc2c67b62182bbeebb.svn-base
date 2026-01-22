<?php

if (is_anonymous_user()) {
    return;
}

# AJAX user selection.

if (!isset($userstring)) {
    $userstring = "";
}

// $autocomplete_user_scope needs to be set if we have more than one user select field on a page
if (!isset($autocomplete_user_scope)) {
    $autocomplete_user_scope = "";
}

?>
<table class="user_select_table" cellpadding="0" cellspacing="0">
    <!-- autocomplete -->
    <?php if (isset($user_select_internal) && $user_select_internal) { ?>
        <tr>
            <td>
                <input type="text" onpaste="return false;" class="stdwidth" value="<?php echo escape($lang["starttypingusername"]); ?>" id="<?php echo escape($autocomplete_user_scope); ?>autocomplete" name="autocomplete_parameter" onFocus="if(this.value == '<?php echo escape($lang['starttypingusername']); ?>') {this.value = ''}" onBlur="if(this.value == '') {this.value = '<?php echo escape($lang['starttypingusername']); ?>';}" />
            </td>           
        </tr>
    <?php } else { ?>
        <tr>
            <td>
                <input type="text" onpaste="return false;" class="medwidth" value="<?php echo escape($lang["starttypingusername"]); ?>" id="<?php echo escape($autocomplete_user_scope); ?>autocomplete" name="autocomplete_parameter" onFocus="if(this.value == '<?php echo escape($lang['starttypingusername']); ?>') {this.value = ''}" onBlur="if(this.value == '') {this.value = '<?php echo escape($lang['starttypingusername']); ?>';}" />
            </td>
            <td>
                <input id="<?php echo escape($autocomplete_user_scope); ?>adduserbutton" type=button value="+" class="medcomplementwidth" onClick="<?php echo escape($autocomplete_user_scope); ?>addUser();" />
            </td>
        </tr>
        <?php
    } ?>

    <?php if (isset($single_user_select_field_id)) { ?>
        <tr>
            <td colspan="2" align="left">
                <input type="text" readonly="readonly" class="stdwidth" name="<?php echo escape($autocomplete_user_scope); ?>users" id="<?php echo escape($autocomplete_user_scope); ?>users" value="<?php
                if (isset($single_user_select_field_value)) {
                    $found_single_user_select_field_value = ps_value("select username as value from user where ref=?", array("s",$single_user_select_field_value), '');
                    echo escape($found_single_user_select_field_value);
                }
                ?>" />

                <?php if ($found_single_user_select_field_value != '') { ?>
                    <script>jQuery("#<?php echo escape($autocomplete_user_scope); ?>adduserbutton").attr('value', '<?php echo escape($lang["clearbutton"]); ?>');</script>
                <?php } ?>

                <input type="hidden" id="<?php echo escape($single_user_select_field_id); ?>" name="<?php echo escape($single_user_select_field_id); ?>" value="<?php
                if (isset($single_user_select_field_value)) {
                    echo escape($single_user_select_field_value);
                } ?>" />
            </td>
        </tr>
    <?php } else { ?>
        <!-- user string -->
        <tr>
            <td colspan="2" align="left">
                <textarea
                    rows=6
                    class="stdwidth"
                    name="<?php echo escape($autocomplete_user_scope); ?>users"
                    id="<?php echo escape($autocomplete_user_scope); ?>users"
                    <?php if (!$sharing_userlists) {?>
                        onChange="this.value=this.value.replace(/[^,] /g,function replacespaces(str) {return str.substring(0,1) + ', ';});"
                    <?php } else { ?>
                        onChange="<?php echo escape($autocomplete_user_scope); ?>addUser();<?php echo escape($autocomplete_user_scope); ?>checkUserlist();<?php echo escape($autocomplete_user_scope);
                        echo escape($autocomplete_user_scope); ?>updateUserSelect();"
                    <?php } ?>><?php echo escape($userstring); ?></textarea>
            </td>
        </tr>
        <?php
    }

    if ($sharing_userlists) { ?>
        <tr>
            <td>
                <div id="<?php echo escape($autocomplete_user_scope); ?>userlist_name_div" style="display:none;">
                    <input type="text" class="medwidth" value="<?php echo escape($lang['typeauserlistname']); ?>" id="<?php echo escape($autocomplete_user_scope); ?>userlist_name_value" name="userlist_parameter" onClick="this.value='';" />
                </div>
            </td>

            <td>
                <div id="<?php echo escape($autocomplete_user_scope); ?>userlist_+" style="display:none;">
                    <input type=button value="<?php echo escape($lang['saveuserlist']); ?>" class="medcomplementwidth" onClick="<?php echo escape($autocomplete_user_scope); ?>saveUserList();" />
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <select
                    id="<?php echo escape($autocomplete_user_scope); ?>userlist_select"
                    class="medwidth"
                    onchange="
                        document.getElementById('<?php echo escape($autocomplete_user_scope); ?>users').value = document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_select').value;
                        document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_name_div').style.display = 'none';
                        document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_+').style.display = 'none';
                        if (document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_select').value == '') {
                            document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_delete').style.display='none';
                        } else {
                            document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_delete').style.display='inline';
                        }"
                ></select>
            </td>
            <td>
                <input type=button id="<?php echo escape($autocomplete_user_scope); ?>userlist_delete" value="<?php echo escape($lang['deleteuserlist']); ?>" style="display:none;" class="medcomplementwidth" onClick="<?php echo escape($autocomplete_user_scope); ?>deleteUserList();" />
            </td>
        </tr>
    <?php } ?>
</table>

<script type="text/javascript">
    function <?php echo escape($autocomplete_user_scope); ?>addUser(event, ui) {
        var username = document.getElementById("<?php echo escape($autocomplete_user_scope); ?>autocomplete").value;
        var users = document.getElementById("<?php echo escape($autocomplete_user_scope); ?>users");

        if (typeof ui !== 'undefined') {
            username = ui.item.value;
        }
        
        if (username.indexOf("<?php echo escape($lang["group"]); ?>") != -1) {
            if ((confirm("<?php echo escape($lang["confirmaddgroup"]); ?>")) == false) {
                return false;
            }
            
        }

        if (username.indexOf("<?php echo escape($lang["groupsmart"]); ?>") != -1) {
            if ((confirm("<?php echo escape($lang["confirmaddgroupsmart"]); ?>")) == false) {
                return false;
            }
        }

        <?php if (isset($single_user_select_field_id)) { ?>
            var user_ref = '';
            jQuery.ajax({
                url: '<?php echo $baseurl; ?>/pages/ajax/autocomplete_user.php?getuserref=' + username,
                type: 'GET',
                async: false,
                success: function(ref, textStatus, xhr) {
                    if (xhr.status == 200 && ref > 0) {
                        user_ref=ref;
                    }
                }
            });

            var single_user_field = document.getElementById("<?php echo escape($single_user_select_field_id); ?>");
            single_user_field.value = user_ref;
            users.value = '';

            if (user_ref == '') {
                username = '';
                jQuery("#<?php echo escape($autocomplete_user_scope); ?>adduserbutton").attr('value', '+');
            } else {
                jQuery("#<?php echo escape($autocomplete_user_scope); ?>adduserbutton").attr('value', '<?php echo escape($lang["clearbutton"]); ?>');
            }
            <?php
            if (isset($single_user_select_field_onchange)) {
                echo $single_user_select_field_onchange;
            }
        }
        ?>

        if (username != "") {
            if (users.value.length != 0) {
                users.value += ", ";}
            users.value += username;
         }
            
        document.getElementById("<?php echo escape($autocomplete_user_scope); ?>autocomplete").value = "";
        
        <?php if ($sharing_userlists) { ?>
            var parameters = 'userstring=' + users.value;
            var newstring = jQuery.ajax("<?php echo $baseurl; ?>/pages/ajax/username_list_update.php",
                {
                    data: parameters,
                    complete: function(modified) {users.value=modified.responseText;    checkUserlist();}
                }
            );
        <?php } ?>
        return false;
    }

    <?php
    # Limit results - default is users, groups and smart groups.
    $limit_results = '';
    if (isset($single_user_select_field_id)) {
        $limit_results = '?nogroups=true';
    } elseif (isset($no_smart_groups)) {
        $limit_results = '?nosmartgroups=true';
    }
    ?>

    jQuery(document).ready(function () {
        jQuery('#<?php echo escape($autocomplete_user_scope); ?>autocomplete').autocomplete({
            source: "<?php echo $baseurl; ?>/pages/ajax/autocomplete_user.php<?php echo escape($limit_results); ?>",
            select: <?php echo escape($autocomplete_user_scope); ?>addUser,
            classes: {
                "ui-autocomplete": "userselect"
            }
        });
    });

    <?php if ($sharing_userlists) { ?>
        <?php echo escape($autocomplete_user_scope); ?>updateUserSelect();
        jQuery("#userlist_name_value").autocomplete({
            source:"<?php echo $baseurl; ?>/pages/ajax/autocomplete_userlist.php"
        });
    <?php } ?>

    <?php if ($sharing_userlists) { ?>    
        function checkUserlist() {
            // conditionally add option to save userlist if string is new
            var userstring = document.getElementById("<?php echo escape($autocomplete_user_scope); ?>users").value;
            var sel = document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_select').options;
            var newstring = true;

            for (n = 0; n <= sel.length-1; n++) {
                //alert (document.getElementById('<?php echo escape($autocomplete_user_scope); ?>users').value+'='+sel[n].value);
                if (document.getElementById('<?php echo escape($autocomplete_user_scope); ?>users').value==sel[n].value) {
                    sel[n].selected = true;
                    document.getElementById("userlist_delete").style.display = 'inline';
                    newstring = false;
                    break;
                }
            }

            if (newstring) {
                document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_name_div").style.display = 'block';
                document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_+").style.display = 'block';
                document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_select').value = "";    
                document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_name_value').value = ''; 
                document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_name_value').placeholder = '<?php echo escape($lang['typeauserlistname'])?>';
                document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_delete").style.display = 'none';
            } else {
                document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_name_div").style.display = 'none';
                document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_+").style.display = 'none';
            }
        }

        function <?php echo escape($autocomplete_user_scope); ?>saveUserList() {
            var parameters = 'userref=<?php echo escape($userref); ?>&userstring=' + document.getElementById("<?php echo escape($autocomplete_user_scope); ?>users").value+'&userlistname='+document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_name_value").value;
            jQuery.ajax("<?php echo $baseurl; ?>/pages/ajax/userlist_save.php",
                {
                data: parameters,
                complete: function(){
                    document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_name_div").style.display='none';
                    document.getElementById("<?php echo escape($autocomplete_user_scope); ?>userlist_+").style.display='none';
                    <?php echo escape($autocomplete_user_scope); ?>updateUserSelect();
                    }
                }
            );
        }

        function <?php echo escape($autocomplete_user_scope); ?>deleteUserList() {
            var parameters = 'delete=true&userlistref='+document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_select').options[document.getElementById('<?php echo escape($autocomplete_user_scope); ?>userlist_select').selectedIndex].id;
            jQuery.ajax("<?php echo $baseurl; ?>/pages/ajax/userlist_save.php", {
                data: parameters,
                complete: function() {
                    <?php echo escape($autocomplete_user_scope); ?>updateUserSelect();
                }
            });
        }

        function <?php echo escape($autocomplete_user_scope); ?>updateUserSelect() {
            var parameters = 'userref=<?php echo escape($userref); ?>&userstring='+document.getElementById("<?php echo escape($autocomplete_user_scope); ?>users").value;
            jQuery("#userlist_select").load("<?php echo $baseurl; ?>/pages/ajax/userlist_select_update.php",
                parameters,
                function() {
                    <?php echo escape($autocomplete_user_scope); ?>checkUserlist();
                }
            );
        }
        <?php
    } ?>
</script>


