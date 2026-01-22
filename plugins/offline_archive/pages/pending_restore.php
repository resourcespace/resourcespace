<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('i'))
    {
    exit (escape($lang['error-permissiondenied']));
    }

// Handle removed resources
$removeref=getval("remove",0,true);
if($removeref > 0 && is_numeric($removeref))
    {
    ps_query("UPDATE resource SET pending_restore=0 WHERE ref = ?", ['i', $removeref]);
    resource_log($removeref,"",0,$lang['offline_archive_resource_log_restore_removed'],"","");
    $resulttext=$lang["offline_archive_resources_restore_cancel_confirmed"];
    }

$title_field = "field".$view_title_field;
$pendingrestores=ps_query("SELECT ref, $title_field, file_size FROM resource WHERE pending_restore='1'");


include '../../../include/header.php';
?>
<div class='BasicsBox'>
<?php

if (isset($resulttext))
    {
    echo "<div class=\"projectSaveStatus\">" . escape($resulttext) . "</div>";
    }
?>
<div>
<a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/administer_archive.php" onClick="return CentralSpaceLoad(this,true);" ><?php echo  LINK_CARET . escape($lang["offline_archive_administer_archive"]); ?></a>
</div>


<p>
<h1><?php echo escape($lang["offline_archive_restore_pending"]); ?></h1>
</p>

<form id="cancel_restore_form" name="form1" method="post" action="<?php echo $baseurl ?>/plugins/offline_archive/pages/pending_restore.php">
<input type="hidden" name="remove" id="remove" value="">
<div class="Listview">
    <table id="offline_archive_table" class="ListviewStyle offline_archive_table">
        <tr class="ListviewTitleStyle">
            <th style="width:150px"><?php echo  escape($lang['property-reference']); ?></th>
            <th><?php echo escape($lang['property-title']); ?></th>
            <th><?php echo escape($lang['offline_archive_archive_ref']); ?></th>
            <th><?php echo escape($lang["tools"]); ?><th>
        </tr>
        <?php
        foreach ($pendingrestores as $pendingrestore)
            {
            $ref=$pendingrestore['ref'];
            $archivecode = get_data_by_field($ref,$offline_archive_archivefield);
            $tdlink = "<a href='"  . generateURL($baseurl_short . "?r=" . $ref) . "' onclick='return ModalLoad(this,true)'>%%TEXT%%</a>";
            ?>
            <tr>
                <td><?php echo str_replace("%%TEXT%%", (int)$ref,$tdlink);?></td>
                <td><?php echo str_replace("%%TEXT%%",escape($pendingrestore[$title_field]),$tdlink);?></td>
                <td><?php echo escape($archivecode);?></td>
                <td><a href="#" onClick="if(confirm('<?php escape($lang["offline_archive_cancel_confirm"]); ?>')){CentralSpacePost(document.getElementById('cancel_restore_form'),true);};jQuery('#remove').val('<?php echo (int)$ref ?>');return false;"><?php echo LINK_CARET . escape($lang["offline_archive_cancel_restore"]); ?></a></td>
            </tr>
            <?php
            }
        ?>
    </table>
</div>

</form>
</div>
<?php
include '../../../include/footer.php';