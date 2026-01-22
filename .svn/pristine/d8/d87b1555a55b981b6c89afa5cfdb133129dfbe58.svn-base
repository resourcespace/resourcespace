<?php

function HookLicensemanagerTeam_homeCustomteamfunction()
{
global $lang,$baseurl_short;
if (!checkperm("a") && !checkperm("lm")) {return false;}
    ?>
    <li title="<?php echo escape($lang["managelicenses-tooltip"]); ?>"><a href="<?php echo $baseurl_short?>plugins/licensemanager/pages/list.php" onClick="return CentralSpaceLoad(this,true);"><i aria-hidden="true" class="icon-scroll"></i><br /><?php echo escape($lang["managelicenses"]); ?></a></li>
    <?php
    }