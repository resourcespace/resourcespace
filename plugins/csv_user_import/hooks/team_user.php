<?php
function HookCsv_user_importTeam_userRender_options_to_create_users()
{
    global $baseurl, $lang;
    ?>
    <div class="BasicsBox">
        <div class="Question">
            <label><?php echo escape($lang['csv_user_import_batch_user_import']); ?></label>
            <div class="Fixed">
                <a href="<?php echo $baseurl; ?>/plugins/csv_user_import/pages/csv_user_import.php" onClick="return CentralSpaceLoad(this, true);">
                    <?php echo LINK_CARET . $lang['csv_user_import_import']; ?>
                </a>
            </div>
            <div class="clearerleft"></div>
        </div>
    </div>
    <?php
}