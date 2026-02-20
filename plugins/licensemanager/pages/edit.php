<?php
include "../../../include/boot.php";
include_once "../../../include/authenticate.php";
include "../include/file_functions.php";

# Check if it's necessary to upgrade the database structure
include __DIR__ . "/../upgrade/upgrade.php";

$ref=getval("ref","");
$resource=getval("resource","");
$file_path=get_license_file_path((int) $ref);

# Check access
if ($resource!="")
    {
    $edit_access=get_edit_access($resource);
    if (!$edit_access && !checkperm("lm")) {exit("Access denied");} # Should never arrive at this page without edit access
    }
else
    {
    # Editing all licenses via Manage Licenses - admin only
    if (!checkperm("a") && !checkperm("lm")) {exit("Access denied");} 
    }

$url_params = array(
    'search'     => getval('search',''),
    'order_by'   => getval('order_by',''),
    'collection' => getval('collection',''),
    'offset'     => getval('offset',0),
    'restypes'   => getval('restypes',''),
    'archive'    => getval('archive','')
);
    # Added from resource page?
    if ($resource!="") 
        {
        $url_params['ref'] = $resource;
        $redirect_url = generateURL($baseurl_short . "pages/view.php",$url_params);
        }
    else
        {
        # Added from Manage Licenses
        $redirect_url = generateURL($baseurl_short . "plugins/licensemanager/pages/list.php",$url_params);
        }
if (getval("submitted","")!="")
    {
    # Save license data
    
    # Construct expiry date
    $expires = getval("expires","");

    if ($expires == "") {
        $expires = null;
    }
    
    # No expiry date ticked? Insert null
    if (getval("no_expiry_date", "") == "yes") {
        $expires = null;
    }

    # Construct usage
    $license_usage="";
    if (isset($_POST["license_usage"])) {$license_usage=join(", ",$_POST["license_usage"]);}
    
    if ($ref=="new")
        {
        # New record 
        ps_query(
            "insert into license (outbound,holder,license_usage,description,expires) values (?, ?, ?, ?, ?)",
            [
                's', getval('outbound', ''),
                's', getval('holder', ''),
                's', $license_usage,
                's', getval('description',''),
                's', $expires
            ]
        );  
        $ref=sql_insert_id();
        $file_path=get_license_file_path((int) $ref); // get updated path

        # Add to all the selected resources
        if (getval("resources","")!="")
            {
            $resources=explode(", ",getval("resources",""));
            foreach ($resources as $r)
                {
                $r=trim($r);
                if (is_numeric($r))
                    {
                    ps_query("insert into resource_license(resource,license) values (?, ?)", ['i', $r, 'i', $ref]);
                    resource_log($r,"","",$lang["new_license"] . " " . $ref);
                    }
                }
            }
        }
    else
        {

        // Determine the previous expiry date and expiration_notice_sent flag
        $previous_data = ps_query("select expires, expiration_notice_sent from license where ref = ?", ['i', $ref]);

        if (!empty($previous_data) && count($previous_data) === 1) {

            //If expiry date has changed
            if ($previous_data[0]['expires'] !== $expires) {
                $expiration_notice_sent = 0;
            } else {
                $expiration_notice_sent = (int) $previous_data[0]['expiration_notice_sent'];
            }

        }

        # Existing record   
        ps_query(
            "update license set outbound= ?,holder= ?, license_usage= ?,description= ?,expires= ?, expiration_notice_sent= ? where ref= ?",
            [
                's', getval('outbound', ''),
                's', getval('holder', ''),
                's', $license_usage,
                's', getval('description',''),
                's', $expires,
                'i', $expiration_notice_sent,
                'i', $ref
            ]
        );

        # Add all the selected resources
        ps_query("delete from resource_license where license= ?", ['i', $ref]);
        $resources=explode(",",getval("resources",""));

        if (getval("resources","")!="")
            {
            foreach ($resources as $r)
                {
                $r=trim($r);
                if (is_numeric($r))
                    {
                    ps_query("insert into resource_license(resource,license) values (?, ?)", ['i', $r, 'i', $ref]);
                    resource_log($r,"","",$lang["new_license"] . " " . $ref);
                    }
                }
            }
        }
        
    # Handle file upload
    global $banned_extensions;
    if (isset($_FILES["file"]) && $_FILES["file"]["tmp_name"]!="") {
        $process_file_upload = process_file_upload($_FILES['file'], new SplFileInfo($file_path), []);

        if ($process_file_upload['success']) {
            ps_query("UPDATE license set file=? where ref=?",array("s",$_FILES["file"]["name"], "i",$ref));
        } else {
            error_alert(
                match ($process_file_upload['error']) {
                    ProcessFileUploadErrorCondition::InvalidExtension => str_replace(
                        '[filetype]',
                        parse_filename_extension($_FILES['file']['name']),
                        $lang['error_upload_invalid_file']
                    ),
                    default => $process_file_upload['error']->i18n($lang),
                },
                true
            );
            exit();
        }
    }

    # Handle file clear
    if (getval("clear_file","")!="")
        {
        if (file_exists($file_path)) {unlink($file_path);}  
        ps_query("UPDATE license set file='' where ref=?",array("i",$ref));
        }
    
    redirect($redirect_url);
    }


# Fetch license data
if ($ref=="new")
    {
    # Set default values for the creation of a new record.
    $license=array(
        "outbound"=>1,
        "holder"=>"",       
        "license_usage"=>"",
        "description"=>"",
        "expires"=>"",
        "file" =>""
        );
    if ($resource=="") {$resources=array();} else {$resources=array($resource);}
    }
else
    {
    $license=ps_query("select * from license where ref= ?", ['i', $ref]);
    if (count($license)==0) {exit("License not found.");}
    $license=$license[0];
    $resources=ps_array("select distinct resource value from resource_license where license= ? order by resource", ['i', $ref]);
    }
        
include "../../../include/header.php";
?>
<div class="BasicsBox">

<?php if ($resource!="") { ?>
<p><a href="<?php echo $redirect_url ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?></a></p>
<?php } else { ?>
<p><a href="<?php echo $redirect_url ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["back"]); ?></a></p>
<?php } ?>


<h1><?php echo escape($ref == "new" ? $lang["new_license"] : $lang["edit_license"]); ?></h1>

<form method="post" action="<?php echo $baseurl_short?>plugins/licensemanager/pages/edit.php" enctype="multipart/form-data">
<input type=hidden name="submitted" value="true">
<input type=hidden name="ref" value="<?php echo escape($ref)?>">
<input type=hidden name="resource" value="<?php echo escape($resource)?>">
<?php generateFormToken("licensemanager_edit"); ?>

<div class="Question"><label><?php echo escape($lang["license_id"]); ?></label><div class="Fixed"><?php echo escape($ref == "new" ? $lang["licensemanager_new"] : $ref); ?></div>
<div class="clearerleft"> </div></div>

<div class="Question"><label><?php echo escape($lang["type"]); ?></label>
<div class="Fixed">
<input type=radio name="outbound" id="outbound_1" value="1" <?php if ($license["outbound"]==1) { ?>checked<?php } ?> /> <strong><?php echo escape($lang["outbound"]); ?></strong> <?php echo escape($lang["outbound_license_description"]); ?><br>
<input type=radio name="outbound" id="outbound_0" value="0" <?php if ($license["outbound"]==0) { ?>checked<?php } ?> /> <strong><?php echo escape($lang["inbound"]); ?></strong> <?php echo escape($lang["inbound_license_description"]); ?>
</div>
<div class="clearerleft"> </div></div>


<div class="Question">
    <label for="holder"><?php echo escape($lang["licensor_licensee"]); ?></label>
    <input type=text class="stdwidth" name="holder" id="holder" value="<?php echo escape($license["holder"])?>" />
    <div class="clearerleft"></div>
</div>

<div class="Question"><label><?php echo escape($lang["indicateusagemedium"]); ?></label>
<table>
<?php
$s=trim_array(explode(",",$license["license_usage"]));
$allchecked=true;
foreach ($license_usage_mediums as $medium)
    {
    ?>
    <tr>
        <td>
            <label>
                <input type="checkbox" class="license_usage" name="license_usage[]" value="<?php echo $medium ?>" <?php if (in_array($medium, $s)) { ?>checked<?php } else {$allchecked=false;} ?>>&nbsp;<?php echo lang_or_i18n_get_translated($medium, "license_usage-") ?>
            </label>
        </td>
    </tr>
    <?php
    }
?>

<!-- Option to tick all mediums -->
<tr>
    <td>
        <label>
            <input type="checkbox" onChange="jQuery('.license_usage').prop('checked',this.checked);" <?php if ($allchecked) { ?>checked<?php } ?>/>
            <?php echo escape($lang["selectall"]); ?>
        </label>
    </td>
</tr>

</table>

<div class="clearerleft"> </div></div>

<div class="Question">
    <label for="description"><?php echo escape($lang["description"]); ?></label>
    <textarea rows="4" class="stdwidth" name="description" id="description"><?php echo escape((string) $license["description"]) ?></textarea>
    <div class="clearerleft"></div>
</div>

<div class="Question">
    <label for="expires"><?php echo escape($lang["fieldtitle-expiry_date"]); ?></label>
    <input type=date name="expires" id="expires" min="<?php echo escape($minyear) . "-01-01"; ?>" max="<?php echo date("Y") + 30 . "-12-31"; ?>" value="<?php echo escape((string) $license["expires"]); ?>" />

    <!-- Option for no expiry date -->
    &nbsp;&nbsp;&nbsp;&nbsp;
    <input
        type="checkbox"
        name="no_expiry_date"
        value="yes"
        id="no_expiry"
        <?php echo ($license["expires"] == "") ? " checked" : ''; ?>
        onChange="jQuery('#expires').attr('disabled',this.checked);"
    />
    <?php
    echo escape($lang["no_expiry_date"]);
    if ($license["expires"] == "") {
        ?>
        <script>jQuery('#expires').attr('disabled',true);</script>
        <?php
    } ?>

    <div class="clearerleft"></div>
</div>


<div class="Question">
    <label for="resources"><?php echo escape($lang["linkedresources"]); ?></label>
    <textarea class="stdwidth" rows="3" name="resources" id="resources"><?php echo escape(join(", ",$resources))?></textarea>
    <div class="clearerleft"> </div>
</div>

<div class="Question" id="file">
    <label for="file"><?php echo escape($lang["file"]); ?></label>
    <?php
    
    if($license["file"]!="")
        {
        $download_url = generateURL("download.php",["resource"=>$resource,"ref"=>$ref]);
        ?>
        <span><i class="icon-file"></i> <a href="<?php echo $download_url;?>"><?php echo escape($license['file']); ?></a></span>
        &nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" name="clear_file" value="<?php echo escape($lang["clearbutton"]); ?>" onclick="return confirm('<?php echo escape($lang["confirmdeletelicensefile"]); ?>');">
        <?php
        }
    else
        {
        ?>
        <input type="file" name="file" style="width:300px">
        <input type="submit" name="upload_file" value="<?php echo escape($lang['upload']); ?>">
        <?php
        }
        ?>
    <div class="clearerleft"></div>
</div>


<div class="QuestionSubmit">        
<input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["save"]); ?>&nbsp;&nbsp;" />
</div>
</form>
</div>

<?php	  
include "../../../include/footer.php";
?>
