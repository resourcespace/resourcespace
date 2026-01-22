<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";

$ref      = getval('ref', 0, true);
$resource = getval('resource', 0, true);

# Set default values for the creation of a new record
$new_record = true;
$usage_data = array(
    'resource'       => $resource,
    'usage_location' => '',
    'usage_medium'   => '',
    'description'    => '',
    'usage_date'     => date('Y-m-d')
);

# Fetch usage data
if ($ref > 0)
    {
    $usage_data = ps_query("SELECT * FROM resource_usage WHERE ref = ?", array("i",$ref));

    if(count($usage_data) == 0)
        {
        exit('Usage not found.');
        }

    $new_record = false;

    $usage_data = $usage_data[0];
    $resource   = $usage_data['resource'];
    }

if(getval('submitted', '') != '' && enforcePostRequest(false))
    {
    $usage_location = getval('usage_location', '');
    $usage_medium   = implode(', ', getval('usage_medium', [], false, 'is_array'));
    $description    = getval('description', '');

    # Construct usage date
    $usage_date = getval('usage_date_year', '') . '-' . getval('usage_date_month', '') . '-' . getval('usage_date_day', '-');

    $resource_data = get_resource_data($resource);
    if($new_record && $resource_data !== false && resource_download_allowed($resource, "", $resource_data["resource_type"]))
        {
        # New record 
        $parameters=array("i",$resource, "s",$usage_location, "s",$usage_medium, "s",$description, "s",$usage_date);
        ps_query("INSERT INTO resource_usage (resource, usage_location, usage_medium, description, usage_date) 
                    VALUES (?, ?, ?, ?, ?)", $parameters);

        $ref = sql_insert_id();

        resource_log($resource, '', '', $lang['new_usage'] . ' ' . $ref);
        }
    elseif(!$new_record && get_edit_access($resource))
        {
        # Existing record   
        $parameters=array("s",$usage_location, "s",$usage_medium, "s",$description, "s",$usage_date, "i",$ref, "i",$resource);
        ps_query("UPDATE resource_usage SET usage_location = ?, usage_medium = ?, description = ?, usage_date = ? WHERE ref = ? AND resource = ?", $parameters);
        
        resource_log($resource, '', '', $lang['edit_usage'] . ' ' . $ref);
        }

    redirect('pages/view.php?ref=' . $resource);
    }
       
include "../../../include/header.php";
?>
<div class="BasicsBox">
    <p>
        <a href="<?php echo $baseurl_short; ?>pages/view.php?ref=<?php echo escape($resource); ?>" onClick="return CentralSpaceLoad(this, true);">&lt;&nbsp;<?php echo escape($lang['backtoresourceview']); ?></a>
    </p>
    <h1><?php echo escape($new_record ? $lang['new_usage'] : $lang['edit_usage']); ?></h1>

    <form method="post" action="<?php echo $baseurl_short?>plugins/resource_usage/pages/edit.php" onSubmit="return CentralSpacePost(this, true);">
        <?php generateFormToken("resource_usage_editForm"); ?>
        <input type=hidden name="submitted" value="true">
        <input type=hidden name="ref" value="<?php echo escape($ref); ?>">
        <input type=hidden name="resource" value="<?php echo escape($resource); ?>">

    <div class="Question">
        <label><?php echo escape($lang['usage_ref']); ?></label>
        <div class="Fixed"><?php echo escape($new_record ? $lang['usage_id_new'] : $ref); ?></div>
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label><?php echo escape($lang['resourceid']); ?></label>
        <div class="Fixed"><?php echo escape($usage_data['resource']); ?></div>
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label><?php echo escape($lang['usage_location']); ?></label>
        <input class="stdwidth" type="text" name="usage_location" value="<?php echo escape($usage_data['usage_location']); ?>">
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label><?php echo escape($lang['usage_medium']); ?></label>
        <fieldset class="MultiRTypeSelect">
        <?php
        $s = trim_array(explode(',', $usage_data['usage_medium']));
        foreach($resource_usage_mediums as $medium)
            {
            ?>
            <input type="checkbox" name="usage_medium[]" value="<?php echo $medium; ?>" <?php if(in_array($medium, $s)) { ?>checked<?php } ?>>&nbsp;<?php echo $medium; ?>
            <br>
            <?php
            }

        // Old mediums might have been removed from the options
        // Nonetheless we should still show it as being checked
        foreach($s as $old_medium)
            {
            if('' !== trim($old_medium) && !in_array($old_medium, $resource_usage_mediums))
                {
                ?>
                <input type="checkbox" name="usage_medium[]" value="<?php echo $old_medium; ?>" checked>&nbsp;<?php echo $old_medium; ?>
                <br>
                <?php
                }
            }
        ?>
        </fieldset>
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label for="description"><?php echo escape($lang['description']); ?></label>
        <textarea id="description" class="stdwidth" name="description" rows="4"><?php echo escape($usage_data["description"]); ?></textarea>
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label><?php echo escape($lang['usage_date']); ?></label>
        <select name="usage_date_day" class="SearchWidth" style="width:98px;">
        <?php
        for($n = 1; $n <= 31; $n++)
            {
            $m = str_pad($n, 2, '0', STR_PAD_LEFT);
            ?>
            <option <?php if($n == substr($usage_data['usage_date'], 8, 2)) { ?>selected<?php } ?> value="<?php echo $m; ?>"><?php echo $m;?></option><?php
            }
        ?>
        </select>

        <select name="usage_date_month" class="SearchWidth" style="width:98px;">
        <?php
        for($n = 1; $n <= 12; $n++)
            {
            $m = str_pad($n, 2, '0', STR_PAD_LEFT);
            ?>
            <option <?php if($n == substr($usage_data['usage_date'], 5, 2)) { ?>selected<?php } ?> value="<?php echo $m; ?>"><?php echo escape($lang['months_list'][$n - 1]); ?></option>
            <?php
            }
        ?>
        </select>
        
        <select name="usage_date_year" class="SearchWidth" style="width:98px;">
        <?php
        $y = date('Y') + 30;
        for($n = $minyear; $n <= $y; $n++)
            {
            ?><option <?php if($n == substr($usage_data['usage_date'], 0, 4)) { ?>selected<?php } ?>><?php echo $n; ?></option>
            <?php
            }
        ?>
        </select>
        <div class="clearerleft"></div>
    </div>

    <div class="QuestionSubmit">        
        <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang['save']); ?>&nbsp;&nbsp;" />
    </div>
    </form>
</div>
<?php       
include "../../../include/footer.php";
