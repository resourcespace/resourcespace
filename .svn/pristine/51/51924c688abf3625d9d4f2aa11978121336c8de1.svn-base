<?php

/**
 * Team center home page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";
if (!checkperm("t")) {
    exit("Permission denied.");
}

$overquota = overquota();

# Work out free space / usage for display
if (!file_exists($storagedir)) {
    mkdir($storagedir, 0777);
}

if (isset($disksize)) { # Use disk quota rather than real disk size
    $avail = $disksize * 1000 * 1000 * 1000;
    $used = get_total_disk_usage();
    $free = $avail - $used;
} else {
    $avail = disk_total_space($storagedir);
    $free = disk_free_space($storagedir);
    $used = $avail - $free;
}

if ($free < 0) {
    $free = 0;
}

include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["teamcentre"]);?></h1>
    <?php if (getval("modal", "") == "") { ?>
        <p>
            <?php
            echo escape(text("introtext"));
            render_help_link('resourceadmin/quick-start-guide');
            ?>
        </p>
    <?php } ?>
    
    <div class="<?php echo $tilenav ? "TileNav" : "VerticalNav TileReflow"; ?>">
        <ul>
            <?php if (checkperm("c")) { ?>
                <li title="<?php echo escape($lang["manageresources-tooltip"]); ?>">
                    <a
                        href="<?php echo $baseurl_short?>pages/team/team_resource.php"
                        <?php if (getval("modal", "") != "") {
                            # If a modal, open in the same modal
                            ?>
                            onClick="return ModalLoad(this,true,true,'right');"
                        <?php } else { ?>
                            onClick="return CentralSpaceLoad(this,true);"
                        <?php } ?>
                    >
                        <i aria-hidden="true" class="icon-files"></i>
                        <br /><?php echo escape($lang["manageresources"]); ?>
                    </a>
                </li>
                <?php
            }

            if (checkperm("R")) { ?>
                <li title="<?php echo escape($lang["managerequestsorders-tooltip"]); ?>">
                    <a href="<?php echo $baseurl_short ?>pages/team/team_request.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-shopping-cart"></i>
                        <br /><?php echo escape($lang["managerequestsorders"]); ?>
                        <?php
                        $condition = "";
                        $params = array();

                        if (checkperm("Rb")) {
                            $condition = "and assigned_to=?";
                            $params[] = "i";
                            $params[] = $userref;
                        } # Only show pending for this user?

                        $pending = ps_value("select count(*) value from request where status = 0 $condition", $params, 0);

                        if ($pending > 0) {
                            ?>
                            &nbsp;<span class="Pill"><?php echo $pending ?></span>
                            <?php
                        }
                        ?>
                    </a>
                </li>
            <?php } ?>

            <?php if (checkperm("r") && $research_request) { ?>
                <li title="<?php echo escape($lang["manageresearchrequests-tooltip"]); ?>">
                    <a href="<?php echo $baseurl_short?>pages/team/team_research.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-circle-question-mark"></i>
                        <br /><?php echo escape($lang["manageresearchrequests"]); ?>
                        <br>
                        <?php
                        $unassigned = ps_value("select count(*) value from research_request where status = 0", array(), 0);
                        if ($unassigned > 0) {
                            ?>&nbsp;<span class="Pill"><?php echo $unassigned ?></span><?php
                        }
                        ?>
                    </a> 
                </li>
            <?php }

            if (checkperm('u')) {
                ?>
                <li title="<?php echo escape($lang["manageusers-tooltip"]); ?>">
                    <a href="<?php echo $baseurl_short; ?>pages/team/team_user.php" onClick="return CentralSpaceLoad(this, true);">
                        <i aria-hidden="true" class="icon-users-round"></i>
                        <br /><?php echo escape($lang['manageusers']); ?>
                    </a>
                </li>
                <?php
            }

            // Manage dash tiles
            if (
                $home_dash
                && (
                    // All user tiles
                    ((checkperm('h') && !checkperm('hdta')) || (checkperm('dta') && !checkperm('h')))
                    // User group tiles
                    || (checkperm('h') && checkperm('hdt_ug'))
                )
            ) {
                ?>
                <li title="<?php echo escape($lang["manage_dash_tiles-tooltip"]); ?>">
                    <a href="<?php echo $baseurl_short; ?>pages/team/team_dash_admin.php" onClick="return CentralSpaceLoad(this, true);">
                        <i aria-hidden="true" class="icon-layout-dashboard"></i>
                        <br /><?php echo escape($lang['manage_dash_tiles']); ?>
                    </a>
                </li>
                <?php
            }

            // Manage external shares
            if (checkperm('ex') || checkperm('a')) {
                ?>
                <li title="<?php echo escape($lang["manage_external_shares-tooltip"]); ?>">
                    <a href="<?php echo $baseurl_short; ?>pages/manage_external_shares.php" onClick="return CentralSpaceLoad(this, true);">
                        <i aria-hidden="true" class="icon-share-2"></i>
                        <br /><?php echo escape($lang['manage_external_shares']); ?>
                    </a>
                </li>
                <?php
            }
            ?>

            <li title="<?php echo escape($lang["rse_analytics-tooltip"]); ?>">
                <a href="<?php echo $baseurl_short?>pages/team/team_analytics.php" onClick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-chart-pie"></i>
                    <br /><?php echo escape($lang["rse_analytics"]); ?>
                </a>
            </li>
            
            <li title="<?php echo escape($lang["viewreports-tooltip"]); ?>">
                <a href="<?php echo $baseurl_short?>pages/team/team_report.php" onClick="return CentralSpaceLoad(this,true);">
                    <i aria-hidden="true" class="icon-table"></i>
                    <br /><?php echo escape($lang["viewreports"]); ?>
                </a>
            </li>

            <?php
            hook("customteamfunction");

            # Get failed job count
            $pending = count(job_queue_get_jobs("", STATUS_ERROR, 0));
            # Include a link to the System Setup area for those with the appropriate permissions.
            if (checkperm("a")) { ?>
                <li title="<?php echo escape($lang["systemsetup-tooltip"]); ?>">
                    <a href="<?php echo $baseurl_short?>pages/admin/admin_home.php"
                        <?php if (getval("modal", "") != "") {
                            # If a modal, open in the same modal
                            ?>
                            onClick="return ModalLoad(this,true,true,'right');"
                        <?php } else { ?>
                            onClick="return CentralSpaceLoad(this,true);"
                        <?php } ?>
                    >
                        <i aria-hidden="true" class="icon-settings"></i>
                        <br /><?php echo escape($lang["systemsetup"]); ?>
                    </a>
                    <br>
                    <span class="Pill <?php echo ($pending == 0) ? 'DisplayNone' : '' ?>"><?php echo $pending;?></span>
                </li>
                <?php
                hook("customteamfunctionadmin");
            } ?>
        </ul>
    </div>

    <p class="clearerleft">
        <i aria-hidden="true" class="icon-hard-drive"></i>&nbsp;<?php echo escape($lang["diskusage"]); ?>: <b><?php echo round(($avail ? $used / $avail : 0) * 100, 0)?>%</b>
        &nbsp;&nbsp;&nbsp;<span class="sub"><?php echo formatfilesize($used)?> / <?php echo formatfilesize($avail)?></span>
    </p>
</div>

<?php
include "../../include/footer.php";
?>
