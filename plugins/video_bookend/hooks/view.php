<?php

function HookVideo_bookendViewAfterresourceactions()
    {
    global $ref,$access,$lang,$resource,$baseurl_short;

    $original_file_path=get_resource_path($ref,true,"",false,$resource['file_extension']);
    if(file_exists($original_file_path))
        {
        if (function_exists('mime_content_type'))
            {
            $original_file_mime_type = mime_content_type($original_file_path);
            }
        else
            {
            $original_file_mime_type = get_mime_type($original_file_path)[0];
            }
            
        if ($access==0 && (int) $resource["has_image"] !== RESOURCE_PREVIEWS_NONE && substr($original_file_mime_type,0,6)=="video/")
            {
            ?>
            <li><a onClick='return CentralSpaceLoad(this,true);' href='<?php echo $baseurl_short;?>plugins/video_bookend/pages/bookend.php?ref=<?php echo $ref?>'>
            <?php echo "<i class='icon-move-horizontal'></i>&nbsp;" . $lang['bookend'];?>
            </a></li>
            <?php
            return true;
            }
        }
    }