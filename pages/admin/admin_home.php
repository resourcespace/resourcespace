<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["systemsetup"]); ?></h1>
    <?php if (getval("modal", "") == "") { ?>
        <p><?php echo escape(text("introtext")); ?></p>
    <?php } ?>
    <div class="<?php echo $tilenav ? "TileNav" : "VerticalNav TileReflow"; ?>">
        <ul>
            <li title="<?php echo escape($lang['page-title_user_group_management-tooltip']); ?>">
                <a href="<?php echo $baseurl_short?>pages/admin/admin_group_management.php" onclick="return CentralSpaceLoad(this,true);" >
                    <i aria-hidden="true" class="icon-users-round "></i>
                    <br /><?php echo escape($lang['page-title_user_group_management']); ?>
                </a>
            </li>

            <li title="<?php echo escape($lang['resource_types_manage-tooltip']); ?>">
                <a href="<?php echo $baseurl_short?>pages/admin/admin_resource_types.php" onclick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-boxes "></i>
                    <br /><?php echo escape($lang["resource_types_manage"]); ?>
                </a>
            </li>

            <li title="<?php echo escape($lang['admin_resource_type_fields-tooltip']); ?>">
                <a href="<?php echo $baseurl_short?>pages/admin/admin_resource_type_fields.php" onclick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-form "></i>
                    <br /><?php echo escape($lang["admin_resource_type_fields"]); ?>
                </a>
            </li>

            <li title="<?php echo escape($lang['filter_manage-tooltip']); ?>">
                <a href="<?php echo $baseurl_short?>pages/admin/admin_filter_manage.php" onclick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-funnel "></i>
                    <br /><?php echo escape($lang["filter_manage"]); ?>
                </a>
            </li>

            <li title="<?php echo escape($lang['page-title_report_management-tooltip']); ?>">
                <a href="<?php echo $baseurl_short?>pages/admin/admin_report_management.php" onclick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-table "></i>
                    <br /><?php echo escape($lang['page-title_report_management']); ?>
                </a>
            </li>

            <li title="<?php echo escape($lang['page-title_size_management-tooltip']); ?>">
                <a href="<?php echo $baseurl_short?>pages/admin/admin_size_management.php" onclick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-files "></i>
                    <br /><?php echo escape($lang["page-title_size_management"]); ?>
                </a>
            </li>
            
            <?php if (checkperm("o")) { ?>
                <li title="<?php echo escape($lang['managecontent-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short?>pages/admin/admin_content.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-file-pen-line "></i>
                        <br /><?php echo escape($lang["managecontent"]); ?>
                    </a>
                </li>
            <?php } ?>
            
            <li title="<?php echo escape($lang['pluginssetup-tooltip']); ?>">
                <a href="<?php echo $baseurl_short?>pages/team/team_plugins.php" onClick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-plug "></i>
                    <br /><?php echo escape($lang["pluginssetup"]); ?>
                </a>
            </li>

            <?php
            if (checkperm('a')) {
                $failedjobs = job_queue_get_jobs("", STATUS_ERROR);
                $failedjobcount = count($failedjobs);
                ?>
                <li title="<?php echo escape($lang['manage_slideshow-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short; ?>pages/admin/admin_manage_slideshow.php" onClick="return CentralSpaceLoad(this, true);">
                        <i aria-hidden="true" class="icon-images "></i>
                        <br /><?php echo escape($lang['manage_slideshow']); ?>
                    </a>
                </li>

                <li title="<?php echo escape($lang['manage_jobs-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short; ?>pages/manage_jobs.php" onClick="return CentralSpaceLoad(this, true);">
                        <i aria-hidden="true" class="icon-list-todo "></i>
                        <br /><?php echo escape($lang['manage_jobs']);?>
                    </a>
                    
                    <?php if ($failedjobcount > 0) { ?>
                        &nbsp;<span class="Pill"><?php echo $failedjobcount ?></span>
                    <?php } ?>
                </li>
                <?php
            }

            // A place to add links to setup pages keeping them away from the more "sysadmin" type pages towards the bottom.
            hook("customadminsetup");

            if ('' != $mysql_bin_path && $system_download_config) { ?>
                <li title="<?php echo escape($lang['exportdata-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short?>pages/admin/admin_download_config.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-database "></i>
                        <br /><?php echo escape($lang["exportdata"]); ?>
                    </a>
                </li>
                <?php
            }

            if (checkperm('a')) {
                if ($enable_remote_apis) {
                    ?>
                    <li title="<?php echo escape($lang['api-test-tool-tooltip']); ?>">
                        <a href="<?php echo $baseurl_short?>pages/api_test.php" onClick="return CentralSpaceLoad(this,true);">
                            <i aria-hidden="true" class="icon-stethoscope "></i>
                            <br /><?php echo escape($lang["api-test-tool"]); ?>
                        </a>
                    </li>
                    <?php
                }
                ?>

                <li title="<?php echo escape($lang['system_tabs-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short; ?>pages/admin/tabs.php" onclick="return CentralSpaceLoad(this, true);">
                        <i aria-hidden="true" class="icon-app-window "></i>
                        <br /><?php echo escape($lang['system_tabs']); ?>
                    </a>
                </li>

                <li title="<?php echo escape($lang['installationcheck-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short?>pages/check.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-square-check "></i>
                        <br /><?php echo escape($lang["installationcheck"]); ?>
                    </a>
                </li>

                <li title="<?php echo escape($lang['systemlog-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short; ?>pages/admin/admin_system_log.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-clipboard-clock "></i>
                        <br /><?php echo escape($lang["systemlog"]); ?>
                    </a>
                </li>

                <li title="<?php echo escape($lang['system_performance-tooltip']); ?>">
                    <a href="<?php echo $baseurl_short?>pages/admin/admin_system_performance.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-zap "></i>
                        <br /><?php echo escape($lang["system_performance"]); ?>
                    </a>
                </li>

                <li title="<?php echo escape($lang['systemconfig-tooltip']); ?>">
                    <a href="<?php echo $baseurl; ?>/pages/admin/admin_system_config.php" onClick="return CentralSpaceLoad(this, true);">
                        <i aria-hidden="true" class="icon-settings "></i>
                        <br /><?php echo escape($lang['systemconfig']); ?>
                    </a>
                </li>
                <?php
            }

            hook("customadminfunction");
            ?>
        </ul>
    </div>
</div> <!-- End of BasicsBox -->

<?php
include "../../include/footer.php";