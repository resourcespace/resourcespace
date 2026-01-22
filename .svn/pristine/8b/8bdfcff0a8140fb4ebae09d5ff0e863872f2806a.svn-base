<?php

/**
 * Resource management team center page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("t")) {
    exit("Permission denied.");
}

include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["manageresources"]); ?></h1>

    <div class="VerticalNav">
        <ul>
            <?php
            // Check if user can create resources
            if (checkperm("c")) {
                // Test if Add Single Resource is allowed.
                if ($upload_methods['single_upload']) {
                    ?>
                    <li>
                        <i aria-hidden="true" class="icon-file"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/edit.php?ref=-<?php echo $userref?>&amp;noupload=true&amp;recordonly=true" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["addresource"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if ($upload_methods['in_browser_upload']) {
                    // Test if Add Resource Batch - In Browser is allowed.
                    $url = ($upload_then_edit) ? "upload_batch.php" : "edit.php?ref=-$userref&amp;uploader=batch";
                    ?>
                    <li>
                        <i aria-hidden="true" class="icon-upload"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/<?php echo $url?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["addresourcebatchbrowser"]); ?>
                        </a>
                    </li>
                    <?php
                }

                $no_exif = '';
                if (!$metadata_read_default) {
                    $no_exif = '&no_exif=yes';
                }
                ?>

                <li>
                    <i aria-hidden="true" class="icon-files"></i>&nbsp;
                    <a href="<?php echo $baseurl_short?>pages/upload_replace_batch.php" onClick="return CentralSpaceLoad(this,true);">
                        <?php echo escape($lang["replaceresourcebatch"]); ?>
                    </a>
                </li>    

                <li>
                    <i aria-hidden="true" class="icon-copy"></i>&nbsp;
                    <a href="<?php echo $baseurl_short?>pages/team/team_copy.php" onClick="return CentralSpaceLoad(this,true);">
                        <?php echo escape($lang["copyresource"]); ?>
                    </a>
                </li>

                <?php if (checkperm("e-2")) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-user-round-plus"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/search.php?search=&archive=-2&resetrestypes=true" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["viewuserpendingsubmission"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if (checkperm("e-1")) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-user-round-plus"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/search.php?search=&archive=-1&resetrestypes=true" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["viewuserpending"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if (checkperm("e-2")) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-user-round-plus"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/search.php?search=!contributions<?php echo $userref?>&archive=-2&resetrestypes=true" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["viewcontributedps"]); ?>
                        </a>
                    </li>
                    <?php
                }

                # If deleting resources is configured AND the deletion state is '3' (deleted) AND the user has permission to edit resources in this state, then show a link to list deleted resources.
                if (isset($resource_deletion_state) && $resource_deletion_state == 3 && checkperm("e3")) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-trash-2"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/search.php?search=&archive=3&resetrestypes=true" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["viewdeletedresources"]); ?>
                        </a>
                    </li>
                    <?php
                }

                if ($file_checksums) {
                    // File checksums must be enabled for duplicate searching to work
                    // also, rememember that it only works for resources that have a checksum
                    // so if you're using offline generation of checksum hashes, make sure they have been updated
                    // before running this search.
                    ?>
                    <li>
                        <i aria-hidden="true" class="icon-files"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!duplicates")?>" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["viewduplicates"]); ?>
                        </a>
                    </li>
                    <?php
                } ?>

                <li>
                    <i aria-hidden="true" class="icon-funnel"></i>&nbsp;
                    <a href="<?php echo $baseurl_short?>pages/search.php?search=<?php echo urlencode("!unused")?>" onClick="return CentralSpaceLoad(this,true);">
                        <?php echo escape($lang["viewuncollectedresources"]); ?>
                    </a>
                </li>

                <?php if (checkperm('a')) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-file-question-mark"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/search.php?search=!noningested" onClick="return CentralSpaceLoad(this,true);" title="<?php echo escape($lang["team_resource_non_ingested_search"]); ?>">
                            <?php echo escape($lang["team_resource_non_ingested_search"]); ?>
                        </a>
                    </li><?php
                } ?>

                <li>
                    <i aria-hidden="true" class="icon-triangle-alert"></i>&nbsp;
                    <a href="<?php echo $baseurl_short?>pages/search.php?search=!integrityfail" onClick="return CentralSpaceLoad(this,true);" title="<?php echo escape($lang["team_resource_integrity_fail_info"]); ?>">
                        <?php echo escape($lang["team_resource_integrity_fail"]); ?>
                    </a>
                </li>

                <?php if (checkperm("i")) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-archive"></i>&nbsp;
                        <a href="<?php echo $baseurl?>/pages/team/team_archive.php" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["managearchiveresources"]); ?>
                        </a>
                    </li>
                    <?php
                }

                // Check if user can manage keywords and fields
                if (checkperm("k")) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-link"></i>&nbsp;
                        <a href="<?php echo $baseurl_short?>pages/team/team_related_keywords.php" onClick="return CentralSpaceLoad(this,true);">
                            <?php echo escape($lang["managerelatedkeywords"]); ?>
                        </a>
                    </li>
                    <?php
                }
            }
        hook("menuitem");
        ?>
        </ul>
    </div>
</div>

<?php
include "../../include/footer.php";
?>
