<?php

function HookVr_viewPreviewFullpreviewresultnav()
    {
    global $ref,$resource, $ffmpeg_supported_extensions, $access, $vr_view_projection_field, $vr_view_projection_value, $NODE_FIELDS, $use_vr_view, $vr_view_orig_video, $vr_view_orig_image;
    
    $use_vr_view = VrViewUseVR($resource);
    
    if(!$use_vr_view)
        {
        return false;
        }
        
    if($access !=0 && in_array($resource['file_extension'],$ffmpeg_supported_extensions))
        {
        // Set 'is_transcoding' to prevent standard low quality video previews of 360 videos if we don't have access - should just show image
        $use_vr_view = false;
        $resource['is_transcoding'] = 1;
        return false;
        }
    return true;
    }
    
   
function HookVr_viewPreviewcustomflvplay()
    {
    global $ref, $resource, $baseurl, $access, $use_vr_view;
    global $vr_view_restypes, $vr_view_metadata, $ffmpeg_supported_extensions, $ffmpeg_preview_extension, $vr_view_orig_video, $userref;
    
    if(!$use_vr_view || $access !=0)
        {
        return false;
        }
    
    $context = (getval("modal","") != "") ? "Modal" : "CentralSpace";
    
    if ($vr_view_orig_video && $access == 0)
        {
        $preview_ext = $resource['file_extension'];
        $preview_size = "";
        }
    else
        {
        if (isset($resource['is_transcoding']) && $resource['is_transcoding']!=0)
            {
            return false;
            }
        $preview_ext = $ffmpeg_preview_extension;
        $preview_size = "pre";
        }
    
    $sourcefile = get_resource_path($ref,true,$preview_size,false,$preview_ext);
    
    if(file_exists($sourcefile))
        {
        $sourcepath = generateURL($baseurl . '/pages/download.php', [
           'ref' => $ref,
           'size' => $preview_size,
           'ext' => $preview_ext,
           'access_key' => generate_temp_download_key($userref, $ref, $preview_size)
        ]);
        // Show the player
        $vrview = VrViewRenderPlayer($ref,$sourcepath, true, 852,600,"PreviewImageLarge",$context);
        if($vrview)
            {
            return true;
            }
        }
        
    return false;
    }

function HookVr_viewPreviewReplacepreviewimage()
    {
    global $ref, $resource, $baseurl, $access, $use_vr_view;
    global $vr_view_restypes, $vr_view_metadata, $vr_view_orig_image, $vr_view_orig_video, $userref;
    
    $context = (getval("modal","") != "") ? "Modal" : "CentralSpace";

    if(!$use_vr_view || $access !=0)
        {
        return false;
        }
    
    if($vr_view_orig_image && $access == 0 && in_array(strtolower($resource['file_extension']),array("png","jpg","jpeg","gif")))
        {
        $preview_ext = $resource["file_extension"];
        $preview_size = "";
        }
    else
        {
        $preview_ext = "jpg";
        $preview_size = "lpr";
        }
    
    $sourcefile = get_resource_path($ref,true,$preview_size,false,$preview_ext);
    if(file_exists($sourcefile))
        {
        $sourcepath = generateURL($baseurl . '/pages/download.php', [
            'ref' => $ref,
            'size' => $preview_size,
            'ext' => $preview_ext,
            'access_key' => generate_temp_download_key($userref, $ref, $preview_size)
            ]);
        // Show the player
        $vrview = VrViewRenderPlayer($ref,$sourcepath,false,852,600,"PreviewImageLarge",$context);
        if($vrview)
            {
            return true;
            }
        }
            
    return false;
    }
