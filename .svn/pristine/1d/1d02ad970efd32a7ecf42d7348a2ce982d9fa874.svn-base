<?php

function HookAntivirusTeam_homeCustomteamfunctionadmin()
{
    global $lang, $baseurl_short, $antivirus_path;

    if (!isset($antivirus_path) || trim($antivirus_path) == '') { ?>
        <li>
            <a href="<?php echo $baseurl_short; ?>/pages/help.php?page=plugins/antivirus"
                onClick="return <?php echo getval('modal', '') != '' ? 'Modal' : 'CentralSpace'; ?>Load(this, true);">
                <i aria-hidden="true" class="icon-triangle-alert"></i>
                <br />
                <?php echo escape($lang['antivirus_av_not_setup_error']); ?>
            </a>
        </li>
    <?php }
}
