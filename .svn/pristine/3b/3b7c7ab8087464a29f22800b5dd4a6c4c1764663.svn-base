<?php
# Work out image to use.
if ($watermark !== '') {
    $use_watermark = check_use_watermark();
} else {
    $use_watermark = false;
}

$thumbnail = get_resource_preview($result[$n], ["pre","thm"], $access, $watermark);
?>
<a
    id="ResourceStrip<?php echo $ref ?>"
    class="ImageStripLink"
    href="<?php echo $url; ?>"  
    onClick="return <?php echo $resource_view_modal ? 'Modal' : 'CentralSpace'; ?>Load(this, true);" 
    title="">
    <?php if ($thumbnail !== false) { ?>
        <img 
            src="<?php echo $thumbnail["url"]; ?>" 
            class="ImageBorder ImageStrip" 
            alt=""
        />
    <?php } else { ?>
        <img
            class="ImageStrip" 
            border=0
            alt="<?php echo escape(i18n_get_translated($result[$n]['field' . $view_title_field] ?? "")); ?>"
            src="<?php echo $baseurl_short; ?>gfx/no_preview/default.png"
        />
    <?php } ?>
</a> 