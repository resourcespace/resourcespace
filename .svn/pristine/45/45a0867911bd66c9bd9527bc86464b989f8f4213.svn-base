<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('i'))
    {
    exit (escape($lang['error-permissiondenied']));
    }

$resources=getval("resources","");
$collection=getval("collection","",true);

$valid=true;

if($resources!="")
    {
    $restoreresources=explode(",",$resources);
    array_filter($restoreresources,"is_int_loose");
    }
elseif($collection!="")
    {
    $restoreresources=get_collection_resources($collection);
    $resources=implode(",",$restoreresources);  
    }
else
    {
    $valid=false;
    }
    
if ($valid)
    {
    $title_field="field".$view_title_field;
    $resourcedetails=ps_query("SELECT ref, $title_field, file_size FROM resource WHERE ref IN (". ps_param_insert(count($restoreresources)) . ")", ps_param_fill($restoreresources, 'i'));

    // Handle entered resources
    if(getval("restore_confirm","")!="" && enforcePostRequest(false))
        {
        ps_query("UPDATE resource SET pending_restore=1 WHERE ref IN (". ps_param_insert(count($restoreresources)). ") and archive=2", ps_param_fill($restoreresources, 'i'));
        foreach($resourcedetails as $resourcedetail)
            {resource_log($resourcedetail["ref"],"",0,$lang['offline_archive_resource_log_restore_set'],"","");}
        $resulttext=$lang["offline_archive_resources_restore_confirmed"];
        }

    }

include '../../../include/header.php';
?>

<div class='BasicsBox'>
<?php
if (isset($resulttext))
    {
    echo "<div class=\"offlinearchiveSaveStatus\">" . escape($resulttext) . "</div>";
    }
?>
<div>
<a href="<?php echo $baseurl ?>/plugins/offline_archive/pages/administer_archive.php" onClick="return CentralSpaceLoad(this,true);" ><?php echo LINK_CARET . escape($lang["offline_archive_administer_archive"]) ?></a>
</div>


<p>
<h1><?php echo escape($lang["offline_archive_resource_restore"]) ?></h1>
</p>

<?php if ($resources != "" && $valid)
        {
        $codes=array();
        ?>
        <div class="Listview">
            <table id="offline_archive_table" class="ListviewStyle offline_archive_table">
                <?php 
                echo '<tr class="ListviewTitleStyle">';
                echo '<th style="width:150px"></th>';
                echo '<th style="width:150px">';
                echo escape($lang['property-reference']);
                echo '</th><th>';
                echo escape($lang['property-title']);
                echo '</th><th>';   
                echo escape($lang['offline_archive_archive_ref']);
                echo '</th>';
                echo "</tr>";   
                    
                foreach ($resourcedetails as $resourcedetail)
                    {
                    $ref=$resourcedetail['ref'];
                    $thumbpath=get_resource_path($ref,false,"col",false,"jpg");
                    $archivecode = get_data_by_field($ref,$offline_archive_archivefield);
                    if($archivecode!=""){$codes[]=$archivecode;}
                    echo '<tr onclick="window.location=\'' . $baseurl . '/?r=' . (int)$ref . '\';">
                    <td>
                    <img src="' . $thumbpath . '">
                    </td>
                    <td>
                    ' . $ref . '</td>
                    <td>';
                    echo escape($resourcedetail[$title_field]);
                    echo '</td>
                    <td>';
                    echo escape($archivecode);
                    echo '</tr>';                   
                    }
                
            
                ?>
            </table>
        </div>
    <?php
        }?>

<form id="resource_restore" id="resource_restore_form" name="resource_restore_form" method="post" action="<?php echo $baseurl ?>/plugins/offline_archive/pages/restore.php">

    <?php generateFormToken("resource_restore_form"); ?>
    <?php if($resources=="")
        {
        echo "<div><p>" . escape($lang['offline_archive_input_text']) . "</p></div>";
        ?>
        <div class="Question">      
            <label for="resources"><?php echo escape($lang['offline_archive_input_resources']) ?></label>
            <input class="stdwidth" name="resources" id="resources" type="text" >   
        </div>
        <div class="Question">      
            <label for="collection"><?php echo escape($lang['offline_archive_input_collection']) ?></label>
            <input class="stdwidth" name="collection" id="collection" type="text" > 
        </div>
        <div class="QuestionSubmit">    
            <input name="restore" id="restore" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["offline_archive_restore_resources"]); ?>&nbsp;&nbsp;">
        </div>

        <?php
        }
    else
        {
        ?>
        <input name="resources" type="hidden" value="<?php echo escape($resources) ?>">
        <div class="QuestionSubmit">    
            <input name="restore_confirm" id="restore_confirm" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["offline_archive_restore_confirm"]); ?>&nbsp;&nbsp;">
        </div>
        <?php
        }
        ?>
</form>
</div>

<?php
include '../../../include/footer.php';