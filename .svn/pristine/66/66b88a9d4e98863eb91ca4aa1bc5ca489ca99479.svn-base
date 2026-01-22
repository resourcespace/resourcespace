<?php

function HookStencilvgViewAfterresourceactions (){
    global $ref,$access,$lang,$resource,$baseurl_short,$search,$offset,
    $order_by,$sort,$k,$imagemagick_path;

    // Require ImageMagick
    if(!isset($imagemagick_path))
        {
        return false;
        }
 
    if ($access==0 && strtoupper($resource['file_extension'])=="SVG")
    {
        $urlparams = array(
            "ref"       =>  $ref,
            "search"    =>  $search,
            "offset"    =>  $offset,
            "order_by"  =>  $order_by,
            "sort"      =>  $sort,
            "k"         =>  $k
        );
        $input_url = generateURL($baseurl_short . 'plugins/stencilvg/pages/input.php',$urlparams);
        ?>
        <li><a onClick='return CentralSpaceLoad(this,true);' href=<?php echo $input_url;?>>
        <?php echo "<i class='icon-wand-sparkles'></i>&nbsp;" .$lang['stencilvg-go'];?>
        </a></li>
        <?php
        return true;
    }

}

?>
