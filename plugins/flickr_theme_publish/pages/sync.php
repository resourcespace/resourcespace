<?php
session_start();
include "../../../include/boot.php";

include "../../../include/authenticate.php";
include "../inc/flickr_functions.php";
include __DIR__ . "/../lib/phpFlickr.php";


if (getval("publish_all","")!="" || getval("publish_new","")!="" || isset($_GET['oauth_verifier']))
    {
    # Get a Flickr token first
    $flickr = new phpFlickr($flickr_api_key,$flickr_api_secret);
    flickr_get_access_token($userref,(isset($_GET['oauth_verifier']) && $_GET['oauth_verifier'] != ''));
    }

include "../../../include/header.php";

$theme=getval("theme","");

$id="flickr_" . $userref . "_" .$theme;
$progress_file=get_temp_dir(false,$id) . "/progress_file.txt";

?>
<h1><?php echo escape($lang["flickr_title"]); ?></h1>
<?php
if(!metadata_field_view_access($flickr_title_field))
    {
    render_top_page_error_style(str_replace('%id', $flickr_title_field, $lang['flickr_warn_no_title_access']));
    include "../../../include/footer.php";
    exit();
    }

    if($flickr_nice_progress){
    ?>
    <script>
        function flickr_open_nice_progress(id,publishType){
            permission=jQuery('select[name="private"]').val();
            url='<?php echo $baseurl?>/plugins/flickr_theme_publish/pages/sync_progress.php?theme='+id+'&publish_type='+publishType+'&permission='+permission;
            window.open(url).focus();
        }
    </script>
    <?php
}

# Handle clear photo IDs
if (getval("clear_photoid","")!="")
    {
    ps_query("update resource set flickr_photo_id = null where ref in (select resource from collection_resource where collection = ?)", array("i",$theme));
    
    }

# Handle log out
if (getval("logout","")!="")
    {
    ps_query("update user set flickr_token = '', flickr_token_secret = '' where ref = ?", array("i",$userref));
    }

if (getval("publish_all","")!="" || getval("publish_new","")!="")
    {
    $photoset_array=flickr_get_photoset();  
    $photoset_name=$photoset_array[0];
    $photoset=$photoset_array[1];   
    }
    
    
if (getval("publish_all","")!="" && enforcePostRequest(false))
    {
    # Perform sync publishing all (updating any existing)
    sync_flickr("!collection" . $theme,false,$photoset,$photoset_name,getval("private",""));
    }
elseif (getval("publish_new","")!="" && enforcePostRequest(false))
    {
    # Perform sync publishing new only.
    sync_flickr("!collection" . $theme,true,$photoset,$photoset_name,getval("private",""));
    }
else
    {
    # Display option for sync
    $unpublished=ps_value("select count(*) value from resource join collection_resource on resource.ref = collection_resource.resource where collection_resource.collection = ? and flickr_photo_id is null", array("i",$theme), 0);
    
    # Count for all resources in selection
    $all=ps_value("select count(*) value from resource join collection_resource on resource.ref = collection_resource.resource where collection_resource.collection = ?", array("i",$theme), 0);

    
    ?>
    <form method="post" id='flickr_publish'>
        <?php generateFormToken("flickr_publish"); ?>
    <!-- Public/private? -->
    <p><?php echo escape($lang["flickr_publish_as"]); ?>
    <select name="private">
    <option value="1" <?php if (getval("private","")==1) { ?>selected<?php } ?>><?php echo escape($lang["flickr-publish-private"]) . "&nbsp;&nbsp;" ?></option>
    <option value="0"><?php echo escape($lang["flickr-publish-public"]) . "&nbsp;&nbsp;" ?></option>
    </select>
    </p>
    

    <p><?php echo escape($lang["publish_new_help"]); ?></p>
    <?php if($flickr_nice_progress){
        ?><input <?php if ($unpublished==0) { ?>disabled<?php } ?> type="button" name="publish_new" id="publish_new" onclick="flickr_open_nice_progress('<?php echo escape($theme); ?>','new')" value="<?php echo escape($unpublished == 1 ? $lang["publish_new-1"] : str_replace("?",$unpublished,$lang["publish_new-2"])); ?>"><?php
    }
    else{
        ?><input <?php if ($unpublished==0) { ?>disabled<?php } ?> type="submit" name="publish_new" id="publish_new" value="<?php echo escape($unpublished==1 ? $lang["publish_new-1"] : str_replace("?",$unpublished,$lang["publish_new-2"])); ?>"><?php
    }?>



    <p>&nbsp;</p>
    <?php
    if ($all-$unpublished>0)
        {
        ?>
        <p><?php echo escape($lang["publish_all_help"]); ?></p>
        <?php if($flickr_nice_progress)
            {
            ?><input <?php if ($unpublished==0 && $all==0) { ?>disabled<?php } ?> type="button" name="publish_all" id="publish_all" onclick="flickr_open_nice_progress('<?php echo escape($theme); ?>','all')" value="<?php echo escape(str_replace(array("$","?"),array($unpublished,$all-$unpublished),$lang["publish_all"])); ?>"><?php
            }
        else
            {
            ?><input <?php if ($unpublished==0 && $all==0) { ?>disabled<?php } ?> type="submit" name="publish_all" id="publish_all" value="<?php echo escape(str_replace(array("$","?"),array($unpublished,$all-$unpublished),$lang["publish_all"])); ?>"><?php
            }
        }
    ?>

    <br /><br /><br /><br /><br /><hr /><h2><?php echo escape($lang["clear-flickr-photoids"]); ?></h2>
    <p><?php echo escape($lang["flickr_clear_photoid_help"]); ?></p>
    <input type="submit" name="clear_photoid" value="<?php echo escape($lang["action-clear-flickr-photoids"]); ?>">
    
    </form>
    <?php
    }

include "../../../include/footer.php";