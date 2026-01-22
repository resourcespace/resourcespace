<?php

function HookImage_textViewDownloadbuttonreplace()
    {
    global $lang,$baseurl_short,$ext,$resource,$image_text_restypes,$baseurl,$ref,$altfiles,$n,$usergroup,$image_text_override_groups,$image_text_filetypes;
    global $order_by,$k,$search,$offset,$archive,$sort, $size_info_array;
      $size_info = $size_info_array;
    # Return if not configured for this resource type or user does not have the option to download without overlay
    if(!in_array($resource['resource_type'], $image_text_restypes) || !in_array($usergroup, $image_text_override_groups)){return false;}
    
    if (isset($altfiles[$n]["file_extension"]) && in_array(strtoupper($altfiles[$n]["file_extension"]), $image_text_filetypes) )
            {   
            ?>          
            
            <a id="downloadlink-no-caption" style="margin-bottom:5px" href="<?php echo $baseurl ?>/pages/terms.php?ref=<?php echo urlencode($ref)?>&search=<?php
                                    echo urlencode($search) ?>&k=<?php echo urlencode($k)?>&url=<?php
                                    echo urlencode("pages/download_progress.php?ref=" . $ref . "&ext=" . $altfiles[$n]["file_extension"] . "&k=" . $k . "&search=" . urlencode($search)
                                    . "&offset=" . $offset . "&alternative=" . $altfiles[$n]["ref"] . "&archive=" . $archive . "&sort=".$sort."&order_by="
                                    . urlencode($order_by)."&nooverlay=true")?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["image_text_download_clear"]); ?></a><?php
            }
            
        elseif (in_array(strtoupper($resource["file_extension"]), $image_text_filetypes) && isset($size_info["id"]))
            {
            if(isset($size_info["extension"])){$dlext=$size_info["extension"];}else{$dlext=$resource["file_extension"];}
                
            ?><a id="downloadlink-no-caption" style="margin-bottom:5px" href="<?php echo $baseurl ?>/pages/terms.php?ref=<?php echo urlencode($ref)?>&search=<?php
            echo urlencode($search) ?>&k=<?php echo urlencode($k)?>&url=<?php
                        echo urlencode("pages/download_progress.php?ref=" . $ref . "&size=" . $size_info["id"]
            . "&ext=" . $dlext . "&k=" . $k . "&search=" . urlencode($search)
            . "&offset=" . $offset . "&archive=" . $archive . "&sort=".$sort."&order_by="
            . urlencode($order_by) ."&nooverlay=true")?>" onClick="return CentralSpaceLoad(this,true);"><?php echo escape($lang["image_text_download_clear"]); ?></a><?php    
            }
    return false;
    }

    
       