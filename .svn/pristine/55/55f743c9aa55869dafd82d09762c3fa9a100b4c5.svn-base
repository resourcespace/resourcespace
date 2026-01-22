<?php

function HookConsentmanagerTeam_homeCustomteamfunction()
{
    global $lang,$baseurl_short;
    if (!checkperm("a") && !checkperm("cm")) {
        return false;
    }
    ?>
    <li title="<?php echo escape($lang["manageconsent-tooltip"]); ?>">
        <a href="<?php echo $baseurl_short?>plugins/consentmanager/pages/list.php" onClick="return CentralSpaceLoad(this,true);">
            <i aria-hidden="true" class="icon-user-check"></i>
            <br />
            <?php echo escape($lang["manageconsent"]); ?>
        </a>
    </li>
    <?php
}