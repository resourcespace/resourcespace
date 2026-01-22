<?php
#
# offline_archive setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('i'))
    {
    exit (escape($lang['error-permissiondenied']));
    }
include '../../../include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($lang["offline_archive_administer_archive"]); ?></h1>
    <div class="VerticalNav">
        <ul>
            <li>
                <a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/view_pending.php" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET . escape($lang["offline_archive_view_pending"]); ?></a>
            </li>
            <li>
                <a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/pending_restore.php" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET . escape($lang["offline_archive_view_pending_restore"]); ?></a>
            </li>
            <li>
                <a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/view_archives.php" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET . escape($lang["offline_archive_view_completed"]); ?></a>
            </li>
            <li>
                <a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/restore.php" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET . escape($lang["offline_archive_restore_resources"]); ?></a>
            </li>
        </ul>
    </div>
</div>
<?php
include '../../../include/footer.php';