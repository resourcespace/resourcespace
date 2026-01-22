<?php

function HookOpenai_image_editViewAfterresourceactions () {
    global $ref,$access,$lang,$resource,$cropper_allowed_extensions,$baseurl_short,$search,$offset,
    $order_by,$sort,$k,$imagemagick_path;

    if (
        $access==0
        && (int) $resource['has_image'] !== RESOURCE_PREVIEWS_NONE
    ) {
        $urlparams = array(
            "ref"       =>  $ref
        );
        $url = generateURL($baseurl_short . 'plugins/openai_image_edit/pages/edit.php',$urlparams);
        ?>
        <li><a href='<?php echo $url;?>'>
        <?php echo "<i class='icon-wand-sparkles'></i>&nbsp;" . escape($lang['openai_image_edit__edit_with_ai']); ?>
        </a></li>
        <?php
        return true;
    }

}

?>
