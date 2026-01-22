<?php
function HookTransformCollection_editColleditformbottom (){
    global $ref;
    global $lang;
    global $cropper_enable_batch;
    global $baseurl_short;

    if ($cropper_enable_batch){
    ?>
<div class="Question">
<label><?php echo escape($lang['batchtransform']); ?></label>
<div class="Fixed">
<a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>plugins/transform/pages/collection_transform.php?collection=<?php echo $ref?>"><?php echo escape($lang["transform"]); ?> &gt;</a>
</div>
<div class="clearerleft"> </div>
</div>

    <?php
    }
}

?>
