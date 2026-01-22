<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('i'))
    {
    exit(escape($lang['error-permissiondenied']));
    }

if(is_int($view_title_field))
    {
    $title_column= "field".$view_title_field;
    $pendingresources=ps_query("SELECT ref, $title_column, file_size FROM resource WHERE archive='1'");
    }
else
    {
    $pendingresources = [];
    }
$totalpendingsize=0;

// Handle create archive form post
if (getval("create_archive","")!="" && getval("archive_name","")!="" && enforcePostRequest(false))
    {
    $archive_name=getval("archive_name","") . date("Ymd", time());
    ps_query("INSERT INTO offline_archive (archive_code, archive_date, archive_status) VALUES (?,NOW(),0)", ['s', $archive_name]);
    $resulttext=$lang['offline_archive_archive_created'] . ": " . $archive_name;
    }

include '../../../include/header.php';
?>

<script>
jQuery(document).ready(function()
    {
    jQuery('#create_archive_form').submit(function() {
        if (jQuery('#archive_name').val()=="")
            {
            alert('<?php echo escape($lang['offline_archive_entername']) ?>');
            //jQuery('#archive_name').slideDown();
            jQuery('#archive_name').focus();
            return false;
            }
        });
    });

</script>

<div class="BasicsBox">

    <?php
    if (isset($resulttext))
        {
        echo "<div class=\"offlinearchiveSaveStatus\">" . escape($resulttext) . "</div>";
        }

    ?>
    <p>
        <a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/administer_archive.php" onClick="return CentralSpaceLoad(this,true);" ><?php echo LINK_CARET . escape($lang["offline_archive_administer_archive"]) ?></a>
    </p>

    <p>
    <h1><?php echo escape($lang["offline_archive_view_pending"]) ?></h1>
    </p>

    <form id="create_archive_form" id="offline_archive_form" name="offline_archive_form" method="post" action="<?php echo $baseurl ?>/plugins/offline_archive/pages/view_pending.php">
        <div class="Question">
            <label for="archive_name"><?php echo escape($lang['offline_archive_name']) ?></label>
            <input class="stdwidth" name="archive_name" id="archive_name" type="text" />
            <div class="clearer"> </div>
        </div>
        <div class="QuestionSubmit">
            <input name="create_archive" id="create_archive" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["offline_archive_createnew"]); ?>&nbsp;&nbsp;">
            <?php generateFormToken("offline_archive_form"); ?>
        </div>
    </form>

    <div>
    <?php
    echo '<p><a href="' . $baseurl . '/pages/search.php?search=&archive=1" onClick="return CentralSpaceLoad(this,true);">' . LINK_CARET . escape($lang["offline_archive_view_as_search"]) . '&nbsp;&nbsp;</a></p>';
    ?>
    </div>

    <div class="Listview">
        <table id="offline_archive_table" class="ListviewStyle offline_archive_table">
            <tr class="ListviewTitleStyle">
                <th style="width:150px"><?php echo escape($lang['property-reference']); ?></th>
                <th><?php echo escape($lang['property-title']); ?></th>
                <th style="width:150px"><?php echo escape($lang['fieldtitle-file_size']); ?></th>
            </tr>
            <?php
            foreach ($pendingresources as $pendingresource)
                {
                $tdlink = "<a href='"  . generateURL($baseurl_short . "?r=" . $pendingresource['ref']) . "' onclick='return ModalLoad(this,true)'>%%TEXT%%</a>";
                ?>
                <tr>
                    <td><?php echo str_replace("%%TEXT%%", (int) $pendingresource['ref'],$tdlink);?></td>
                    <td><?php echo str_replace("%%TEXT%%", escape((string)$pendingresource[$title_column]),$tdlink);?>
                    <td><?php echo formatfilesize((int)$pendingresource['file_size']); ?></td>
                </tr>
                <?php
                $totalpendingsize=$totalpendingsize + $pendingresource['file_size'];
                }?>
            <tr>
                <td><b><?php echo escape($lang['total']); ?></b></td>
                <td></td>
                <td><b><?php echo formatfilesize($totalpendingsize) ?></b></td>
            </tr>
        </table>
    </div>
</div> <!-- End of BasicsBox -->
<?php
include '../../../include/footer.php';