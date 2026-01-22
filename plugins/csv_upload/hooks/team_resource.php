<?php

function HookCsv_uploadTeam_resourceMenuitem()
{
    global $baseurl,$lang;

    if (checkperm("c")) {
        ?>
        <li>
            <?php echo UPLOAD_ICON ?>&nbsp;
            <a href="<?php echo $baseurl ?>/plugins/csv_upload/pages/csv_upload.php" onClick="CentralSpaceLoad(this,true);return false;">
            <?php echo escape($lang["csv_upload_nav_link"]); ?>
            </a>
        </li>
        <?php
    }
}
