<?php
include "../../../include/boot.php";

include "../../../include/authenticate.php";
include "../inc/flickr_functions.php";

# Check for access token before loading page header.
include __DIR__ . "/../lib/phpFlickr.php";
$flickr = new phpFlickr($flickr_api_key,$flickr_api_secret);
flickr_get_access_token($userref,(isset($_GET['oauth_verifier']) && $_GET['oauth_verifier'] != ''));

include "../../../include/header.php";

$theme=getval("theme","");
$private=getval("permission","");
$publish_type=getval("publish_type","");
$id="flickr_".$theme;
$progress_file=get_temp_dir(false,$id) . "/progress_file.txt";

?>
<h1><?php echo escape($lang["flickr_title"]); ?></h1>
<p><?php echo strip_tags_and_attributes($lang["flickr_publishing_in_progress"]); ?></p>

<div id='flickr_publish'>
        
    <input type=hidden name="id" value="<?php echo escape($id) ?>">
    <iframe id="progressiframe" style="display:none;"></iframe>
    <div class="clearerleft"></div>
    
    <div id="flickr_status">
        <div class="Fixed" id="flickr_progress_current" ><?php echo escape($lang["flickr_processing"]); ?></div><div class="clearerleft"></div>
        <div class="Fixed" id="flickr_progress_processed" ></div><div class="clearerleft"></div>
        <div class="Fixed" id="flickr_progress_added" ></div><div class="clearerleft"></div>
        <div class="Fixed" id="flickr_progress_updated" ></div><div class="clearerleft"></div>
        <div class="Fixed" id="flickr_progress_error" ></div><div class="clearerleft"></div>
        <div class="Fixed" id="flickr_progress_done" ></div><div class="clearerleft"></div>
    </div>
    <?php if($flickr_nice_progress_previews || $flickr_nice_progress_metadata){
        ?><h2>Resource Details</h2><?php
    }
    if($flickr_nice_progress_previews){
        // get max dimensions of thm
        $max_size=ps_query("select width, height from preview_size where id = 'thm'", array());
        $width=$max_size[0]['width'];
        $height=$max_size[0]['height'];
        // show previews of file being processed
        ?>
        <div id="image_preview_container" style="width:<?php echo $width?>px;height:<?php echo $height?>px;">
            <div id="image_preview">
                <img alt="" id="image_processing" src="">
            </div>
        </div>
<?php }
            
    if($flickr_nice_progress_metadata){?>
        <div id="image_metadata">
            <label for="image_title">Title:</label>
            <span id="image_title" name="image_title"></span>
            <div class="clearerleft"></div>
            
            <label for="image_description">Description:</label>
            <span id="image_description" name="image_description"></span>
            <div class="clearerleft"></div>
            
            <label for="image_keywords">Keywords:</label>
            <span id="image_keywords" name="image_keywords"></span>
            <div class="clearerleft"></div>
            
            <label for="image_resourceid">Flickr Resource ID:</label>
            <span id="image_resourceid" name="image_resourceid"></span>
            <div class="clearerleft"></div>
            
            <label for="image_photoid">Flickr Photo ID:</label>
            <span id="image_photoid" name="image_photoid"></span>
            <div class="clearerleft"></div>
        </div>
    <?php } ?>
</div>

<script>
    function getResultsProcessed(data,word){
        var find=String(word);
        var letterCount=find.length;
        var start=data.lastIndexOf(find) + letterCount;
        var chop=data.slice(start);
        var end=chop.split(' ')[0];
        return end;
    }
    function trimFlickerData(data){
        var end=data.lastIndexOf(" | processed=");
        return data.slice(0,end);
    }
    function flickr_ajax_progress(){
        var flickr_previews='<?php echo $flickr_nice_progress_previews?>';
        var flickr_metadata='<?php echo $flickr_nice_progress_metadata?>';
        var resource_info='';
        
        var results_processed=0;
        var results_new_publish=0;
        var results_no_publish=0;
        var results_update_publish=0;
        var ifrm = document.getElementById('progressiframe');
        
        ifrm.src = "<?php echo generateURL(
            $baseurl_short . "plugins/flickr_theme_publish/pages/flickr_publish.php",
            ["theme"=>$theme,"private"=>$private,"publish_type"=>$publish_type,"start_publish"=>"true"]
        );?>";

        progress= jQuery("progress3").PeriodicalUpdater("<?php echo $baseurl_short?>plugins/flickr_theme_publish/pages/flickr_publish_progress.php?id=<?php echo urlencode($id) ?>", {
            method: 'post',          // method; get or post
            data: '',               //  e.g. {name: "John", greeting: "hello"}
            minTimeout: <?php echo $flickr_nice_progress_min_timeout?>,       // starting value for the timeout in milliseconds
            maxTimeout: <?php echo $flickr_nice_progress_max_timeout?>,       // maximum length of time between requests
            multiplier: 1,          // the amount to expand the timeout by if the response hasn't changed (up to maxTimeout)
            type: 'text'           // response type - text, xml, json, etc.  
        }, function(remoteData, success, xhr, handle) {
            console.log("Returned: "+remoteData);
            var updateCount=false;
            if(remoteData.indexOf("no publish")!=-1){
                console.log("No Publish: "+remoteData);
                resource_info=remoteData.substr(11);
                databk=remoteData;
                remoteData=trimFlickerData(remoteData);
                updateCount=true;
            }
            else if(remoteData.indexOf("adding")!=-1){
                resource_info=remoteData.substr(7);
            }
            else if(remoteData.indexOf("added")!=-1){
                resource_info=remoteData.substr(6);
                databk=remoteData;
                remoteData=trimFlickerData(remoteData);
                updateCount=true;
            }
            else if(remoteData.indexOf("updated")!=-1){
                resource_info=remoteData.substr(8);
                databk=remoteData;
                remoteData=trimFlickerData(remoteData);
                updateCount=true;
            }
            else if(remoteData.indexOf("<?php echo escape($lang["done"]); ?>")!=-1){
                progress.stop();
                results_processed=getResultsProcessed(remoteData);
                databk=remoteData;
                remoteData=trimFlickerData(remoteData);
                updateCount=true;
            }
            else if(remoteData.indexOf("permissions")!=-1){
                var newData='<?php echo escape($lang["setting-permissions"]); ?>';
                if(<?php echo escape($private)?>==1){remoteData=newData.replace("%permission",'<?php echo escape($lang["flickr_private"]); ?>');}
                else{remoteData=newData.replace("%permission",'<?php echo escape($lang["flickr_public"]); ?>');}
            }
            if(updateCount==true){
                // update counts
                results_processed=getResultsProcessed(databk,"processed=");
                results_new_publish=getResultsProcessed(databk,"new_publish=");jQuery('#flickr_progress_added').html(results_new_publish+' <?php echo escape($lang['flickr_published']); ?>');
                results_no_publish=getResultsProcessed(databk,"no_publish=");jQuery('#flickr_progress_error').html(results_no_publish+' <?php echo escape($lang['flickr_no_published']); ?>');
                results_update_publish=getResultsProcessed(databk,"update_meta=");jQuery('#flickr_progress_updated').html(results_update_publish+' <?php echo escape($lang['flickr_updated']); ?>');
            }
            
            // number of processed
            if (results_processed==1){
                var message=results_processed+' <?php echo escape($lang['photoprocessed']); ?>';
            }
            else{ 
                var message=results_processed+' <?php echo escape($lang['photosprocessed']); ?>';
            }
            // Upper case first letter of first word
            remoteData = remoteData.slice(0,1).toUpperCase() + remoteData.slice(1);
            
            jQuery('#flickr_progress_current').html(remoteData);
            jQuery('#flickr_progress_processed').html(message);
                
            if((flickr_metadata==true || flickr_previews==true) && resource_info!=''){
                
                resource_id=resource_info.substr(0, resource_info.indexOf(' - '));
            
                if(resource_id!=''){
                    jQuery.ajax({
                        type: "POST",
                        url: "<?php echo $baseurl_short?>plugins/flickr_theme_publish/pages/flickr_preview.php",
                        data: {
                            ref: resource_id,
                            <?php echo generateAjaxToken('flickr_ajax_progress'); ?>
                        },
                        dataType:"json",
                        success: function(data){
                            if(flickr_previews==true){
                                jQuery("#image_processing").attr("src",data[0]);
                            }
                            if(flickr_metadata==true){
                                jQuery("#image_title").html(data[1]);
                                jQuery("#image_description").html(data[2]);
                                jQuery("#image_keywords").html(data[3]);
                                jQuery("#image_resourceid").html(resource_id);
                                jQuery("#image_photoid").html(data[4]);
                            }
                        }
                    });
                }
            }
        });
            
    }
    
    flickr_ajax_progress();
</script>
