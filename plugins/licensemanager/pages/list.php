<?php
include __DIR__."/../../../include/boot.php";
include __DIR__."/../../../include/authenticate.php";

$is_admin = checkperm("a");

if (!$is_admin && !checkperm("lm")) {
    exit ("Permission denied.");
}

global $baseurl;

# Check if it's necessary to upgrade the database structure
include __DIR__ . "/../upgrade/upgrade.php";


$offset=getval("offset",0,true);
if (array_key_exists("findtext",$_POST)) {$offset=0;} # reset page counter when posting
$findtext=getval("findtext","");

$delete = getval("delete","");
$license_status = getval("license_status", "all");

if ($delete!="" && enforcePostRequest(false)) {
    # Delete consent
    licensemanager_delete_license($delete);
}



include __DIR__."/../../../include/header.php";

$url_params = array(
    'search'         => getval('search',''),
    'order_by'       => getval('order_by',''),
    'collection'     => getval('collection',''),
    'offset'         => getval('offset',0),
    'restypes'       => getval('restypes',''),
    'archive'        => getval('archive',''),
    'license_status' => getval('license_status', '')
);
?>
<div class="BasicsBox"> 
<h1><?php echo escape($lang["managelicenses"]); ?></h1>
<?php
    $links_trail = array(
        array(
            'title' => !$is_admin ? escape($lang["home"]) : escape($lang["teamcentre"]),
            'href'  => $baseurl_short . (!$is_admin ? "pages/home.php" : "pages/team/team_home.php"),
            'menu'  => !$is_admin ? false : true
        ),
        array(
            'title' => $lang["managelicenses"]
        )
    );

    renderBreadcrumbs($links_trail); ?>
    
<form method=post id="licenselist" action="<?php echo $baseurl_short ?>plugins/licensemanager/pages/list.php" onSubmit="CentralSpacePost(this);return false;">
<?php generateFormToken("licenselist"); ?>
<input type=hidden name="delete" id="licensedelete" value="">
 
<?php 

$licenses = licensemanager_get_all_licenses($findtext, $license_status);

# pager
$per_page = $default_perpage_list;
$results=count($licenses);
$totalpages=ceil($results/$per_page);
$curpage=floor($offset/$per_page)+1;
$url="list.php?findtext=".urlencode($findtext)."&offset=". $offset;
$jumpcount=1;
?>

<p><a href="<?php echo $baseurl_short ?>plugins/licensemanager/pages/edit.php?ref=new" onClick="CentralSpaceLoad(this);return false;"><?php echo LINK_PLUS_CIRCLE . $lang["new_license"]; ?></a></p>


<div class="Listview">
<table class="ListviewStyle">
<tr class="ListviewTitleStyle">
<th><?php echo escape($lang["license_id"]); ?></a></th>
<th><?php echo escape($lang["type"]); ?></a></th>
<th><?php echo escape($lang["licensor_licensee"]); ?></a></th>
<th><?php echo escape($lang["indicateusagemedium"]); ?></a></th>
<th><?php echo escape($lang["description"]); ?></a></th>
<th><?php echo escape($lang["fieldtitle-expiry_date"]); ?></a></th>
<th><div class="ListTools"><?php echo escape($lang["tools"]); ?></div></th>
</tr>

<?php
for ($n=$offset;(($n<count($licenses)) && ($n<($offset+$per_page)));$n++)
    {
    $license=$licenses[$n];
    $license_usage_mediums = trim_array(explode(", ", $license["license_usage"]));
    $translated_mediums = "";
    $url_params['ref'] = $license["ref"];
    ?>
    <tr>
    <td>
            <?php echo $license["ref"]; ?></td>
            <td><?php echo escape($license["outbound"] ? $lang["outbound"] : $lang["inbound"]); ?></td>
            <td><?php echo escape($license["holder"]); ?></td>
            <td><?php
                foreach ($license_usage_mediums as $medium)
                    {
                    $translated_mediums = $translated_mediums . lang_or_i18n_get_translated($medium, "license_usage-") . ", ";
                    }
                $translated_mediums = substr($translated_mediums, 0, -2); # Remove the last ", "
                echo $translated_mediums;
                ?>
            </td>
            <td><?php echo escape($license["description"]); ?></td>
            <td><?php echo escape($license["expires"] == "" ? $lang["no_expiry_date"] : nicedate($license["expires"])); ?></td>
        
            <td>
                <div class="ListTools">
                    <a href="<?php echo generateURL($baseurl_short . "pages/search.php", ['search' => '!license' . $license['ref']]); ?>" onClick="return CentralSpaceLoad(this,true);">
                        <i class="icon-search"></i>&nbsp;<?php echo escape($lang['license_view_linked_resources_short']); ?>
                    </a>
                    <a href="<?php echo generateURL($baseurl_short . "plugins/licensemanager/pages/edit.php",$url_params); ?>" onClick="return CentralSpaceLoad(this,true);"><i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?></a>
                    <a href="<?php echo generateURL($baseurl_short . "plugins/licensemanager/pages/delete.php",$url_params); ?>" onClick="return CentralSpaceLoad(this,true);"><i class="icon-trash-2"></i>&nbsp;<?php echo escape($lang["action-delete"]); ?></a>
                </div>
            </td>
    </tr>
    <?php
    }
?>

</table>
</div>
<div class="BottomInpageNav"><?php pager(true); ?></div>

        <div class="Question">  
            <label for="license_status"><?php echo escape($lang["license_status"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <select name="license_status" id="license_status" onChange="this.form.submit();">
                        <option value="all" <?php echo ($license_status == 'all') ? " selected" : ''; ?>>
                            <?php echo escape($lang["license_status_all"]); ?>
                        </option>
                        <option value="active" <?php echo ($license_status == 'active') ? " selected" : ''; ?>>
                            <?php echo escape($lang["license_status_active"]); ?>
                        </option>
                        <option value="expiring" <?php echo ($license_status == 'expiring') ? " selected" : ''; ?>>
                            <?php echo escape($lang["license_status_expiring"]); ?>
                        </option>
                        <option value="expired" <?php echo ($license_status == 'expired') ? " selected" : ''; ?>>
                            <?php echo escape($lang["license_status_expired"]); ?>
                        </option>
                    </select>
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="find"><?php echo escape($lang["licensesearch"]); ?><br/></label>
            <div class="tickset">
             <div class="Inline">           
            <input type=text placeholder="<?php echo escape($lang['searchbytext']); ?>" name="findtext" id="findtext" value="<?php echo escape($findtext)?>" maxlength="100" class="shrtwidth" />
            
            <input type="button" value="<?php echo escape($lang['clearbutton']); ?>" onClick="$('findtext').value='';CentralSpacePost(document.getElementById('licenselist'));return false;" />
            <input name="Submit" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["searchbutton"]); ?>&nbsp;&nbsp;" />
             
            </div>
            </div>
            <div class="clearerleft"> 
            </div>
        </div>

</form>
<?php

include __DIR__."/../../../include/footer.php";