<?php

include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../include/header.php";

$introtext = text("introtext");
?>
<div class="BasicsBox"> 
    <h1><?php echo escape(($userfullname == "" ? $username : $userfullname)) ?></h1>

    <?php if (trim($introtext) != "") { ?>
        <p><?php echo escape($introtext); ?></p>
    <?php } ?>
  
    <div class="<?php echo $tilenav ? "TileNav" : "VerticalNav TileReflow"; ?>">
        <ul>
            <?php if (1 == $useracceptedterms || !$terms_login) { ?>
                <li title="<?php echo escape($lang["profile-tooltip"]); ?>">
                    <a id="profile_link" href="<?php echo $baseurl_short?>pages/user/user_profile_edit.php" onClick="return CentralSpaceLoad(this,true);">
                        <i aria-hidden="true" class="icon-circle-user-round"></i>
                        <br /><?php echo escape($lang["profile"]); ?>
                    </a>
                </li>
    
                <?php if ($allow_password_change && !checkperm("p") && $userorigin == "") { ?>
                    <li title="<?php echo escape($lang["password-tooltip"]); ?>">
                        <a href="<?php echo $baseurl_short?>pages/user/user_change_password.php" onClick="return CentralSpaceLoad(this,true);">
                            <i aria-hidden="true" class="icon-key-round"></i>
                            <br /><?php echo escape($lang["password"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if (!$disable_languages && $show_language_chooser) { ?>
                    <li title="<?php echo escape($lang["languageselection-tooltip"]); ?>">
                        <a id="language_link" href="<?php echo $baseurl_short?>pages/change_language.php" onClick="return CentralSpaceLoad(this,true);">
                            <i aria-hidden="true" class="icon-languages"></i>
                            <br /><?php echo escape($lang["languageselection"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if (!(!checkperm("d") && !(checkperm('c') && checkperm('e0')))) { ?>
                    <li title="<?php echo escape($lang["mycontributions-tooltip"]); ?>">
                        <a id="contribute_link" href="<?php echo $baseurl_short?>pages/contribute.php" onClick="return CentralSpaceLoad(this,true);">
                            <i aria-hidden="true" class="icon-user-round-plus"></i>
                            <br /><?php echo escape($lang["mycontributions"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if (!checkperm('b')) { ?>
                    <li id="MyCollectionsUserMenuItem" title="<?php echo escape($lang["mycollections-tooltip"]); ?>">
                        <a href="<?php echo $baseurl_short; ?>pages/collection_manage.php" onClick="return CentralSpaceLoad(this, true);">
                            <i aria-hidden="true" class="icon-shopping-bag"></i>
                            <br /><?php echo escape($lang['mycollections']); ?>
                        </a>
                    </li>
                    <?php
                }

                if ($actions_on) { ?>
                    <li title="<?php echo escape($lang["actions_myactions-tooltip"]); ?>">
                        <a id="user_actions_link" href="<?php echo $baseurl_short; ?>pages/user/user_actions.php" onClick="return CentralSpaceLoad(this, true);">
                            <i aria-hidden="true" class="icon-square-check"></i>
                            <br /><?php echo escape($lang['actions_myactions']); ?>
                            <span style="display: none;" class="ActionCountPill Pill"></span>
                        </a>
                    </li>
                    <?php
                }
                ?>

                <script>message_poll();</script>
                <?php
                if ($offline_job_queue) {
                    $failedjobs = job_queue_get_jobs("", STATUS_ERROR, (checkperm('a') ? 0 : $userref));
                    $failedjobcount = count($failedjobs);
                    echo "<li title='" . escape($lang['my_jobs-tooltip']) . "'>";
                    echo "<a id='user_jobs_link' href='" . generateURL("{$baseurl_short}pages/manage_jobs.php", ['job_user' => $userref]) . "' onClick='return CentralSpaceLoad(this, true);'><i aria-hidden='true' class='icon-list-todo'></i><br />" . escape($lang['my_jobs']) . ($failedjobcount > 0 ? "&nbsp;<span class='FailedJobCountPill Pill'>" . escape($failedjobcount) . "</span>" : "") . "</a>";
                    echo "</li>";
                }

                if ($allow_share) {
                    echo "<li title='" . escape($lang['my_shares-tooltip']) . "'>";
                    echo "<a id='manages_shares_link'  href='" . generateURL("{$baseurl_short}pages/manage_external_shares.php", ['share_user' => $userref]) . "' onClick='return CentralSpaceLoad(this, true);'><i aria-hidden='true' class='icon-share-2'></i><br />" . escape($lang['my_shares']) . "</a>";
                    echo "</li>";
                }

                if ($home_dash && checkPermission_dashmanage()) { ?>
                    <li title="<?php echo escape($lang["dash-tooltip"]); ?>">
                        <a id='user_dash_edit_link'href="<?php echo $baseurl_short?>pages/user/user_dash_admin.php" onClick="return CentralSpaceLoad(this,true);">
                            <i aria-hidden="true" class="icon-layout-dashboard"></i>
                            <br /><?php echo escape($lang["dash"]);?>
                        </a>
                    </li>
                    <?php
                }

                if ($user_preferences) { ?>
                    <li title="<?php echo escape($lang["userpreferences-tooltip"]); ?>">
                        <a id='user_preferences_link' href="<?php echo $baseurl_short?>pages/user/user_preferences.php" onClick="return CentralSpaceLoad(this,true);">
                            <i aria-hidden="true" class="icon-settings"></i>
                            <br /><?php echo escape($lang["userpreferences"]);?>
                        </a>
                    </li>
                    <?php
                }

                hook('user_home_additional_links');
            # Log out
            }

            if (!isset($password_reset_mode) || !$password_reset_mode) { ?>
                <li title="<?php echo escape($lang["logout-tooltip"]); ?>">
                    <a href="<?php echo $baseurl?>/login.php?logout=true&amp;nc=<?php echo time()?>">
                        <i aria-hidden="true" class="icon-log-out"></i>
                        <br /><?php echo escape($lang["logout"]); ?>
                    </a>
                </li>
                <?php
            }
            ?>
        </ul>
    </div>
</div>

<?php
include "../../include/footer.php";
