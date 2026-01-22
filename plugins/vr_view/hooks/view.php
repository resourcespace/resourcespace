<?php
function HookVr_viewViewaftergetresourcedataview($ref,$resource)
    {
    // Disable annotations if we are in VR View mode
    global $annotate_enabled;
    if(!is_array($resource))
        {
        return false;
        }
        
    $use_vr_view = VrViewUseVR($resource);
    if($use_vr_view)
        {
        $annotate_enabled = false; 
        }
        
    return false;
    }
    
function HookVr_viewViewReplacepreviewlink(string $preview_title)
    {
    global $resource, $baseurl_short, $urlparams, $modal, $lang, $plugins;
    $use_vr_view = VrViewUseVR($resource);
    
    if(!$use_vr_view)
        {
        return false;
        }

    // Disable lightbox plugin as this will change the preview link
    $plugins = array_diff($plugins,array("lightbox_preview"));
    $plugins = array_values($plugins); 
    ?>
    <div id="previewimagewrapper">
        <a id="previewimagelink"
           class="enterLink"
           href="<?php echo generateURL($baseurl_short . "pages/preview.php", $urlparams, array("ext"=>$resource["preview_extension"])) . "&" . hook("previewextraurl") ?>"
           title="<?php echo escape($preview_title); ?>"
           style="position:relative;"
           onclick="return ModalLoad(this,false,true,top);">
    <?php
    return true;
    }

    
function HookVr_viewViewRenderinnerresourcepreview()
    {
    global $resource, $ffmpeg_supported_extensions, $vr_view_restypes;
    if(VrViewUseVR($resource))
        {
        // Set this to prevent standard video preview of 360 video
        $resource['is_transcoding'] = 1;
        }
        
    // Return false so that preview support continues as normal without video. This saves bandwidth being used rendering a pointless preview
    return false;
    }
