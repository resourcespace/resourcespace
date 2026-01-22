<?php
function HookResource_usageViewCustompanels()
    {
    global $lang,$baseurl_short,$ref,$edit_access,$k, $resource;
    
    if($k != '')
        {
        return false;
        }

    $usages = ps_query("SELECT * FROM resource_usage WHERE resource = ? ORDER BY ref", array("i",$ref));
    ?>
    <div class="RecordBox">
    <div class="RecordPanel">
    <div class="Title"><?php echo escape($lang['resource_usage']); ?></div>
    <?php
    if(resource_download_allowed($ref, "", $resource["resource_type"]))
        {
        ?>    
        <p><?php echo LINK_PLUS ?><a href="<?php echo $baseurl_short; ?>plugins/resource_usage/pages/edit.php?resource=<?php echo $ref; ?>" onClick="return CentralSpaceLoad(this, true);"><?php echo escape($lang['new_usage']); ?></a></p>
        <?php
        }

    if(count($usages) > 0)
        {
        ?>
        <div class="Listview">
            <table class="ListviewStyle">
                <tr class="ListviewTitleStyle">
                    <th><?php echo escape($lang['usage_ref']); ?></a></th>
                    <th><?php echo escape($lang['usage_location']); ?></a></th>
                    <th><?php echo escape($lang['usage_medium']); ?></a></th>
                    <th><?php echo escape($lang['description']); ?></a></th>
                    <th><?php echo escape($lang['usage_date']); ?></a></th>
    <?php
    if($edit_access)
        {
        ?>
        <th><div class="ListTools"><?php echo escape($lang['tools']); ?></div></th>
        <?php
        }
        ?>
        </tr>

    <?php
    foreach($usages as $usage)
        {
        ?>
        <tr>
        <td><?php echo $usage['ref']; ?></td>
        <td><?php echo $usage['usage_location']; ?></td>
        <td><?php echo $usage['usage_medium']; ?></td>
        <td><?php echo $usage['description']; ?></td>
        <td><?php echo nicedate($usage['usage_date']); ?></td>
    
        <?php 
        if($edit_access)
            {
            ?>
            <td>
                <div class="ListTools">
                    <a href="<?php echo $baseurl_short ?>plugins/resource_usage/pages/edit.php?ref=<?php echo $usage['ref']; ?>&resource=<?php echo $ref ?>" onClick="return CentralSpaceLoad(this,true);">&gt;&nbsp;<?php echo escape($lang["action-edit"]); ?></a>
                    <a href="<?php echo $baseurl_short ?>plugins/resource_usage/pages/delete.php?ref=<?php echo $usage['ref']; ?>&resource=<?php echo $ref ?>" onClick="return CentralSpaceLoad(this,true);">&gt;&nbsp;<?php echo escape($lang["action-delete"]); ?></a>
                </div>
            </td>
            <?php
            }
            ?>
        </tr>
        <?php
        }
        ?>
        </table>
        </div>
        <?php
        }
        ?>
    </div>
    </div>
    <?php
    # Allow further custom panels
    return false;
    }