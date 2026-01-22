<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('i'))
    {
    exit (escape($lang['error-permissiondenied']));
    }
$completedarchives=ps_query("SELECT archive_code, archive_date, archive_status FROM offline_archive ORDER BY archive_date DESC");
$offline_archive_fieldshort=ps_value("SELECT name AS value FROM resource_type_field WHERE ref= ?",['i', $offline_archive_archivefield],"");


include '../../../include/header.php';
?>
<div class='BasicsBox'>

<div>
    <a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/administer_archive.php" onClick="return CentralSpaceLoad(this,true);" ><?php echo LINK_CARET . escape($lang["offline_archive_administer_archive"]) ?></a>
</div>

<p><h1><?php echo escape($lang["offline_archive_view_completed"]) ?></h1><p>

<div class="Listview">
    <table id="offline_archive_table" class="ListviewStyle offline_archive_table">
        <tr class="ListviewTitleStyle">
            <th style="width:150px"><?php echo escape($lang['offline_archive_archive_ref']); ?></th>
            <th><?php echo escape($lang['offline_archive_archive_status']); ?></th>
            <th><?php echo escape($lang['offline_archive_archive_date']); ?></th>
            <th><?php echo escape($lang['offline_archive_view_associated']); ?></th>
        </tr>
        <?php
        foreach ($completedarchives as $completedarchive)
            {?>
            <tr>
                <td><?php echo escape($completedarchive['archive_code']); ?></td>
                <td><?php echo escape($lang["offline_archive_statustype"][$completedarchive['archive_status']]); ?></td>
                <td><?php echo $completedarchive['archive_date']; ?></td>
                <td><?php echo '<a href="' . $baseurl . '/pages/search.php?search=' . urlencode($offline_archive_fieldshort) . '%3A' . urlencode($completedarchive['archive_code']) . '&archive=2" onClick="return CentralSpaceLoad(this,true);">' . LINK_CARET .  escape($lang["offline_archive_view_associated"]) . '&nbsp;&nbsp;</a>'; ?></td>
            </tr>
            <?php
            }
        ?>
    </table>
</div>
</div>
<?php
include '../../../include/footer.php';