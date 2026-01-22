<?php

function HookTransformViewAfterresourceactions (){
    global $ref,$access,$lang,$resource,$cropper_allowed_extensions,$baseurl_short,$search,$offset,
    $order_by,$sort,$k,$imagemagick_path;

    if(!isset($imagemagick_path))
        {
        return false;
        }

    if (
        $access==0
        && (int) $resource['has_image'] !== RESOURCE_PREVIEWS_NONE
        && in_array(strtoupper($resource['file_extension']??""),$cropper_allowed_extensions)
    ) {
        $urlparams = array(
            "ref"       =>  $ref,
            "search"    =>  $search,
            "offset"    =>  $offset,
            "order_by"  =>  $order_by,
            "sort"      =>  $sort,
            "k"         =>  $k
        );
        $crop_url = generateURL($baseurl_short . 'plugins/transform/pages/crop.php',$urlparams);
        ?>
        <li><a onClick='return CentralSpaceLoad(this,true);' href='<?php echo $crop_url;?>'>
        <?php echo "<i class='icon-crop'></i>&nbsp;" . escape($lang['imagetools']); ?>
        </a></li>
        <?php
        return true;
    }

}

?>
