<?php
function HookImage_textPreviewPreviewimage()
    {
    global $ext, $resource, $image_text_restypes, $baseurl, $ref, $url, $use_watermark, $k, $alternative, $image_text_filetypes,$page;
    
    # Return if not configured for this resource type, no image or using watermark
    if(!in_array($resource['resource_type'], $image_text_restypes) || (int) $resource["has_image"] === RESOURCE_PREVIEWS_NONE || !in_array(strtoupper($ext), $image_text_filetypes) || $use_watermark){return false;}
    
    $path=get_resource_path($ref,true,"scr",false,$ext,-1,$page,"","",$alternative);
    
        if (!file_exists($path)){$size="pre";} else { $size="scr";} 
    
    $url=$baseurl."/pages/download.php" . "?ref=" . urlencode($ref)  . "&size=" . urlencode($size) . "&ext=" . urlencode($ext) . "&k=" . urlencode($k) . "&alternative=" . urlencode($alternative) ."&noattach=true";
            
    return false; 
    }
    