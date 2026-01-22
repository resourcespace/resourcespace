<?php
global $k;
$k = $k ?? "";
?>
<script type="text/javascript">

jQuery(document).ready(function() {
 jQuery.fn.reverse = [].reverse;
 jQuery(document).keyup(function (e)
  { 
    if(jQuery("input,textarea").is(":focus"))
    {
       // don't listen to keyboard arrows when focused on form elements
       <?php hook("keyboardnavtextfocus");?>
    }
    else if (jQuery('#lightbox').is(':visible'))
        {
        // Don't listen to keyboard arrows if viewing resources in lightbox
        }
    else
        {
        var share='<?php echo escape($k) ?>';
        var modAlt=e.altKey;
        var modShift=e.shiftKey;
        var modCtrl=e.ctrlKey;
        var modMeta=e.metaKey;
        var modOn=(modAlt || modShift || modCtrl || modMeta);

         switch (e.which) 
         {

            <?php hook("addhotkeys"); //this comes first so overriding the below is possible ?>
            // Left arrow
            case <?php echo $keyboard_navigation_prev; ?>:
                if (jQuery('.prevLink').length > 0) {
                    jQuery('.prevLink').click();
                    break;
                }
                if (<?php if ($keyboard_navigation_pages_use_alt) {
                    echo "modAlt&&";
                    } ?>(jQuery('.prevPageLink').length > 0)) {
                    jQuery('.prevPageLink').click();
                    break;
                }
            // Right arrow
            case <?php echo $keyboard_navigation_next; ?>:
                if (jQuery('.nextLink').length > 0) {
                    jQuery('.nextLink').click();
                    break;
                }
                if (<?php if ($keyboard_navigation_pages_use_alt) {
                    echo "modAlt&&";
                    } ?>(jQuery('.nextPageLink').length > 0)) {
                    jQuery('.nextPageLink').click();
                    break;
                } 
            case <?php echo $keyboard_navigation_add_resource; ?>: if (jQuery('.addToCollection').length > 0) jQuery('.addToCollection:not(.ResourcePanelIcons .addToCollection)').click();
                     break;
            case <?php echo $keyboard_navigation_prev_page; ?>: if (jQuery('.prevLink').length > 0) jQuery('.prevLink').click();
                     break;
            case <?php echo $keyboard_navigation_next_page; ?>: if (jQuery('.nextLink').length > 0) jQuery('.nextLink').click();
                     break;
            case <?php echo $keyboard_navigation_all_results; ?>: if (jQuery('.upLink').length > 0) jQuery('.upLink').click();
                     break;
            case <?php echo $keyboard_navigation_toggle_thumbnails; ?>: if (jQuery('#toggleThumbsLink').length > 0) jQuery('#toggleThumbsLink').click();
                     break;
            case <?php echo $keyboard_navigation_zoom; ?>: if (jQuery('.enterLink').length > 0) window.location=jQuery('.enterLink').attr("href");
                     break;
            case <?php echo $keyboard_navigation_close; ?>: ModalClose();
                     break;
            case <?php echo $keyboard_navigation_view_all; ?>: if(!modOn){CentralSpaceLoad('<?php echo $baseurl;?>/pages/search.php?search=!collection'+document.getElementById("currentusercollection").innerHTML+'&k='+share,true)};
                     break;
            <?php if (($pagename == 'search' && $keyboard_navigation_video_search) || ($pagename == 'view' && $keyboard_navigation_video_view) || ($pagename == 'preview' && $keyboard_navigation_video_preview)) {?>
                case <?php echo $keyboard_navigation_video_search_play_pause?>:
                    <?php if ($pagename == 'view' || $pagename == 'preview') { ?>
                        vidActive=document.getElementById('introvideo<?php echo $ref?>');
                    <?php } else { ?>
                        vidActive=document.getElementById('introvideo'+vidActiveRef);
                    <?php } ?>
                    //console.log("active="+vidActive);
                    videoPlayPause(vidActive);
                    break;

                case <?php echo $keyboard_navigation_video_search_forwards?>:
                    //console.log("forward button pressed");
                    //console.log("Player is "+vidActive);
                    // clear
                    clearInterval(intervalRewind);
                    // get current playback rate
                    curPlayback=vidActive.playbackRate();
                    //console.log("Current playback rate is "+curPlayback);
                    if(playback=='forward'){
                        newPlayback=curPlayback+1;
                    }
                    else{
                        newPlayback=1;
                    }
                    playback='forward';
                    //console.log("New playback rate is "+newPlayback);
                    vidActive.playbackRate(newPlayback);
                    break;
            <?php } ?>
         }

     }
 });
});
</script>
