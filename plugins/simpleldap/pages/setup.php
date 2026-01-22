<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php"; if (!checkperm("u")) {exit ("Permission denied.");}


$plugin_name="simpleldap";
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
    

if (getval("submit","")!="" || getval("save","")!="" || getval("testConnflag","")!="" && enforcePostRequest(false))
    {
    $simpleldap['fallbackusergroup'] = getval('fallbackusergroup','');
    $simpleldap['domain'] = getval('domain','');
    $simpleldap['emailsuffix'] = getval('emailsuffix','');
    $simpleldap['ldapserver'] = getval('ldapserver','');
    $simpleldap['ldap_encoding'] = getval('ldap_encoding', '');
    $simpleldap['port'] = getval('port','');
    $simpleldap['basedn']= getval('basedn','');
    $simpleldap['loginfield'] = getval('loginfield','');
    $simpleldap['usersuffix'] = getval('usersuffix','');
    $simpleldap['createusers'] = getval('createusers','');
    $simpleldap['ldapgroupfield'] = getval('ldapgroupfield','');
    $simpleldap['email_attribute'] = getval('email_attribute','');
    $simpleldap['phone_attribute'] = getval('phone_attribute','');
    $simpleldap['update_group'] = getval('update_group','');
    $simpleldap['create_new_match_email'] = getval('create_new_match_email','');
    $simpleldap['allow_duplicate_email'] = getval('allow_duplicate_email','');
    $simpleldap['notification_email'] = getval('notification_email','');
    $simpleldap['ldaptype'] = getval('ldaptype','');
    $simpleldap['LDAPTLS_REQCERT_never'] = getval('LDAPTLS_REQCERT_never', false);
    
    
    
    $ldapgroups = $_REQUEST['ldapgroup'];
    $rsgroups = $_REQUEST['rsgroup'];
    $priority = $_REQUEST['priority'];

    if (count($ldapgroups) > 0)
        {
        ps_query('delete from simpleldap_groupmap where rsgroup is not null');
        }

    for ($i=0; $i < count($ldapgroups); $i++)
        {
        if ($ldapgroups[$i] <> '' && $rsgroups[$i] <> '' && is_numeric($rsgroups[$i]))
            {
            ps_query("replace into simpleldap_groupmap (ldapgroup,rsgroup,priority) values (?, ?, ?)", 
                [
                's', $ldapgroups[$i],
                'i', $rsgroups[$i],
                'i', (($priority[$i]!="")? $priority[$i] : null)
                ]    
            );      
            }
        } 


    if (getval("submit","")!="" || getval("save","")!="")
        {
        set_plugin_config("simpleldap",array("simpleldap"=>$simpleldap));
        }
        
    if (getval("submit","")!="")
        {
        redirect("pages/team/team_plugins.php");
        }
    }



// retrieve list if groups for use in mapping dropdown
$rsgroups = ps_query('select ref, name from usergroup order by name asc');

include "../../../include/header.php";

// if some of the values aren't set yet, fudge them so we don't get an undefined error
// this may be important for updates to the plugin that introduce new variables
foreach(
    array(
        'ldapserver',
        'domain',
        'port',
        'basedn',
        'loginfield',
        'usersuffix',
        'emailsuffix',
        'fallbackusergroup',
        'email_attribute',
        'phone_attribute',
        'update_group',
        'create_new_match_email',
        'allow_duplicate_email',
        'notification_email',
        'ldaptype',
        'LDAPTLS_REQCERT_never',
        'ldap_encoding',
    ) as $thefield
)
    {
    if(!isset($simpleldap[$thefield]))
        {
        $simpleldap[$thefield] = '';
        }
    }


if(getval("testConnflag","")!="" && getval("submit","")=="" && getval("save","")=="")
        {
        ?>
        <div class="BasicsBox"> 
        <?php
        echo "<h1>" . escape($lang["simpleldap_test"]) . " " . escape($simpleldap['ldapserver']) . ":" . escape($simpleldap['port']) . "</h1>";
        
        debug("LDAP - Connecting to LDAP server: " . $simpleldap['ldapserver'] . " on port " . $simpleldap['port']);
        $dstestconn=  @fsockopen($simpleldap['ldapserver'], $simpleldap['port'], $errno, $errstr, 5);
        
        if($dstestconn)
            {
            fclose($dstestconn);
            debug("LDAP - Connected to LDAP server ");
            ?>
            <div class="Question">
            <label for="ldapuser"><?php echo escape($lang["simpleldap_username"]); ?></label><input id='ldapuser' type="text" name='ldapuser'>
            </div>
            
            <div class="Question">
            <label for="ldappassword"><?php echo escape($lang["simpleldap_password"]); ?></label><input id='ldappassword' type="password" name='ldappassword'>
            </div>      

            <?php
            if(!isset($simpleldap['ldaptype']) || $simpleldap['ldaptype']==1) 
                {?>
                <div class="Question">
                <label for="ldapdomain"><?php echo escape($lang["simpleldap_domain"]); ?></label>
                    <select id='ldapdomain' name='ldapdomain'>
                    <?php
                    $binddomains=explode(";",$simpleldap['domain']);
                    foreach ($binddomains as $binddomain)
                        {
                        echo "<option value'" . escape($binddomain)  . "'>" . escape($binddomain) . "</option>";
                        }               
                    ?>
                    </select>
                </div>  
                <?php
                }
            }
            ?>
        
        <input type="submit" onClick="simpleldap_test();return false;" name="testauth" value="<?php echo escape($lang["simpleldap_test_auth"]); ?>" <?php if (!$dstestconn){echo "disabled='true'";} ?>>        
        <input type="submit" onClick="ModalClose();return false;" name="cancel" value="<?php echo escape($lang["cancel"]); ?>">
        
        <br /><br />
        <!--<textarea id="simpleldaptestresults" class="Fixed" rows=15 cols=100 style="display: none; width: 100%; border: solid 1px;" ></textarea>-->
        
        <script>
        function simpleldap_test()
            {
            jQuery('.resultrow').remove();
            jQuery('#testgetuserresult').html('');
            testurl= '<?php echo get_plugin_path("simpleldap",true) . "/pages/ajax_test_auth.php";?>',
            user = jQuery('#ldapuser').val();
            password = jQuery('#ldappassword').val();
            userdomain = jQuery('#ldapdomain').val();
            var post_data = {
                ajax: true,
                ldapserver: '<?php echo escape($simpleldap['ldapserver']) ?>',
                port: '<?php echo escape($simpleldap['port']) ?>',
                ldaptype: '<?php echo escape($simpleldap['ldaptype']) ?>',
                domain: '<?php echo escape($simpleldap['domain']) ?>',
                loginfield: '<?php echo escape($simpleldap['loginfield']) ?>',                
                basedn: '<?php echo escape($simpleldap['basedn']) ?>',    
                ldapgroupfield: '<?php echo escape($simpleldap['ldapgroupfield']) ?>',
                email_attribute: '<?php echo escape($simpleldap['email_attribute']) ?>',
                phone_attribute: '<?php echo escape($simpleldap['phone_attribute']) ?>',  
                emailsuffix: '<?php echo escape($simpleldap['emailsuffix']) ?>',  
                LDAPTLS_REQCERT_never: '<?php echo escape($simpleldap['LDAPTLS_REQCERT_never']) ?>',      
                ldapuser: user,
                ldappassword: password,
                userdomain: userdomain,
                <?php echo generateAjaxToken("simpleldap_test"); ?>
            };
            
            jQuery.ajax({
                  type: 'POST',
                  url: testurl,
                  data: post_data,
                  dataType: 'json', 
                  success: function(response){
                        if(response.complete === true){
                        
                        jQuery('#testbindresult').html(response.bindsuccess);
                        if(response.success){
                            jQuery('#testgetuserresult').html('<?php echo escape($lang["status-ok"]); ?> (' + response.binduser + ')');
                        }
                        else {
                            jQuery('#testgetuserresult').html('<?php echo escape($lang["status-fail"]); ?>');
                        }
                            
                                                
                        returnmessage = response.message;
                        if(response.success) {                      
                            returnmessage += "<tr class='resultrow'><td><?php echo escape($lang["email"]); ?>: </td><td>" + response.email + "</td></tr>";
                            returnmessage += "<tr class='resultrow'><td><?php echo escape($lang["simpleldap_telephone"]); ?>: </td><td>" + response.phone + "</td></tr>";
                            returnmessage += "<tr class='resultrow'><td><?php echo escape($lang["simpleldap_memberof"]); ?>";
                            for (var i = 0, len = response.memberof.length; i < len; i++) {
                              returnmessage += "</td><td>" + response.memberof[i]  + "</td></tr><tr class='resultrow'><td>";
                            }       
                            returnmessage += "</td></tr>";
                        }
                        jQuery('#blankrow').before(returnmessage);
                    }
                    else if(response.complete === false && response.message && response.message.length > 0) {
                        jQuery('#testgetuserdata').html('<?php echo escape($lang["error"]); ?> : ' + response.message);
                    }
                    else {
                        jQuery('#testgetuserdata').html('<?php echo escape($lang["error"]); ?>');
                    }
                },
                  error: function(xhr, textStatus, error){
                      jQuery('#simpleldaptestresults').html(textStatus + ":&nbsp;" + xhr.status    + "&nbsp;" + error  );
                }
            });
            
            }
        
        </script>
        <?php
        
        echo "<table class='InfoTable' style='width: 100%' ><tbody>";
        echo "<tr><td width='40%'><h2>" .  escape($lang["simpleldap_test_title"]) . "</h2></td><td width='60%'><h2>" . escape($lang["simpleldap_result"]) . "</h2></td></tr>";
        echo "<tr><td>" . escape($lang["simpleldap_connection"]) . " " . escape($simpleldap['ldapserver']) . ":" . escape($simpleldap['port']) . "</td><td id='testconnectionresult'>" . escape(($dstestconn) ? $lang["status-ok"] : $lang["status-fail"]) . "</td></tr>";
        echo "<tr><td>" . escape($lang["simpleldap_bind"]) . "</td><td id='testbindresult'></td></tr>";
        echo "<tr><td>" . escape($lang["simpleldap_retrieve_user"]) . "</td><td id='testgetuserresult'></td></tr>";
        echo "<tr id='blankrow'><td colspan='2' ></td></tr>";               
        echo "</tbody></table>";
        ?>
        </div>
        <?php
        exit();
        }   
        


?>
<div class="BasicsBox"> 
  <h2>&nbsp;</h2>
 
<?php 
if (!function_exists('ldap_connect'))
    {
    echo "<div class=\"PageInformal\">" . escape($lang["simpleldap_extension_required"]) . "</div>";
    }
    
?>
 <h1>SimpleLDAP Configuration</h1>
  
<form id="form1" name="form1" enctype= "multipart/form-data" method="post" action="<?php echo get_plugin_path("simpleldap",true) . "/pages/setup.php";?>">
<?php
generateFormToken("simpleldap_setup");
config_single_select("ldaptype", $lang['simpleldap_ldaptype'], $simpleldap['ldaptype'], array(1=>"Active Directory",2=>"Oracle Directory"));
config_boolean_field(
    'LDAPTLS_REQCERT_never',
    $lang['simpleldap_LDAPTLS_REQCERT_never_label'],
    $simpleldap['LDAPTLS_REQCERT_never']);
config_text_field("ldapserver",$lang['ldapserver'],$simpleldap['ldapserver'],60);
config_text_field("port",$lang['port'],$simpleldap['port'],5);
config_text_field("ldap_encoding", $lang['ldap_encoding'], $simpleldap['ldap_encoding'], 60);
config_text_field("domain",$lang['domain'],$simpleldap['domain'],60);
config_text_field("emailsuffix",$lang['emailsuffix'],$simpleldap['emailsuffix'],60);
config_text_field("email_attribute",$lang['email_attribute'],$simpleldap['email_attribute'],60);
config_text_field("phone_attribute",$lang['phone_attribute'],$simpleldap['phone_attribute'],60);
config_text_field("basedn",$lang['basedn'],$simpleldap['basedn'],60);
config_text_field("loginfield",$lang['loginfield'],$simpleldap['loginfield'],30);
config_text_field("usersuffix",$lang['usersuffix'],$simpleldap['usersuffix'],30);
config_text_field("ldapgroupfield",$lang['groupfield'],$simpleldap['ldapgroupfield'],30);
config_boolean_field("createusers",$lang['createusers'],$simpleldap['createusers']);
config_boolean_field("create_new_match_email",$lang['simpleldap_create_new_match_email'],$simpleldap['create_new_match_email']);
config_boolean_field("allow_duplicate_email",$lang['simpleldap_allow_duplicate_email'],$simpleldap['allow_duplicate_email']);
config_boolean_field("update_group",$lang['simpleldap_update_group'],$simpleldap['update_group']);
config_text_field("notification_email",$lang['simpleldap_notification_email'],$simpleldap['notification_email'],60);
?>

<div class="Question">
    <label for="fallbackusergroup"><?php echo escape($lang['fallbackusergroup']); ?></label>
    <select name='fallbackusergroup'><option value=''></option>
    <?php   
        foreach ($rsgroups as $rsgroup){
            echo  "<option value='" . $rsgroup['ref'] . "'";
            if ($simpleldap['fallbackusergroup'] == $rsgroup['ref']){
                echo " selected";
            }
            echo ">". $rsgroup['name'] . "</option>\n";
        } 
    ?></select>
</div>
<div class="clearerleft"></div>



<div class="Question">
<h3><?php echo escape($lang['ldaprsgroupmapping']); ?></h3>
<table id='groupmaptable'>
<tr><th>
<strong><?php echo escape($lang['ldapvalue']); ?></strong>
</th><th>
<strong><?php echo escape($lang['rsgroup']); ?></strong>
</th><th>
<strong><?php echo escape($lang['simpleldappriority']); ?></strong>
</th>
</tr>

<?php
    $grouplist = ps_query('select ldapgroup,rsgroup, priority from simpleldap_groupmap order by priority desc');
    for($i = 0; $i < count($grouplist)+1; $i++){
        if ($i >= count($grouplist)){
            $thegroup = array();
            $thegroup['ldapgroup'] = '';
            $thegroup['rsgroup'] = '';
            $thegroup['priority'] = '';
            $rowid = 'groupmapmodel';
        } else {
            $thegroup = $grouplist[$i];
            $rowid = "row$i";
        }
?>
<tr id='<?php echo $rowid; ?>'>
   <td><input type='text' name='ldapgroup[]' value='<?php echo $thegroup['ldapgroup']; ?>' /></td>
   <td><select name='rsgroup[]'><option value=''></option>
    <?php   
        foreach ($rsgroups as $rsgroup){
            echo  "<option value='" . $rsgroup['ref'] . "'";
            if ($thegroup['rsgroup'] == $rsgroup['ref']){
                echo " selected";
            }
            echo ">". $rsgroup['name'] . "</option>\n";
        } 
    ?></select>
    </td>
    <td><input type='text' name='priority[]' value='<?php echo $thegroup['priority']; ?>' /></td>
</tr>
<?php } ?>
</table>

<a onclick='addGroupMapRow()'><?php echo escape($lang['addrow']); ?></a>
</div>


<div class="Question">
    <input type="hidden" name="testConnflag" id="testConnflag" value="" />
    <input type="submit" name="testConn" onclick="jQuery('#testConnflag').val('true');ModalPost(this.form,true);return false;" value="<?php echo escape($lang['simpleldap_test']); ?>" />
 </div>
<div class="clearerleft"></div>

<div class="Question">
<input type="submit" name="save" value="<?php echo escape($lang["save"]); ?>">
<input type="submit" name="submit" value="<?php echo escape($lang["plugins-saveandexit"]); ?>">

</div>
<div class="clearerleft"></div>

</form>
</div>  

<script language="javascript">
        function addGroupMapRow() {
 
            var table = document.getElementById("groupmaptable");
 
            var rowCount = table.rows.length;
            var row = table.insertRow(rowCount);
 
            row.innerHTML = document.getElementById("groupmapmodel").innerHTML;
        }
</script> 



<?php include "../../../include/footer.php";
