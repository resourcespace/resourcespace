<?php
$thumbnail = get_resource_preview($result[$n], ["pre","thm"], $access, $use_watermark);
$strip_width = $thumbnail !== false ? (int) $thumbnail["width"] : 150;
$strip_height = $thumbnail !== false ? (int) $thumbnail["height"] : 150;
?>
<a
    id="ResourceStrip<?php echo $ref ?>"
    class="ImageStripLink"
    href="<?php echo $url; ?>"  
    onClick="return <?php echo $resource_view_modal ? 'Modal' : 'CentralSpace'; ?>Load(this, true);" 
    title=""
    style="--width: <?php echo escape($strip_width); ?>; --height: <?php echo escape($strip_height); ?>;"
    >
    <?php if ($thumbnail !== false) { ?>
        <?php echo render_preview_image($thumbnail['url'], [
            'class' => 'ImageBorder ImageStrip', 'width' => $strip_width, 'height' => $strip_height,
        ]); ?>
    <?php } else { ?>
        <?php echo render_preview_image($baseurl_short . 'gfx/no_preview/default.png', [
            'class' => 'ImageStrip', 'border' => 0, 'width' => $strip_width, 'height' => $strip_height,
            'alt' => i18n_get_translated($result[$n]['field' . $view_title_field] ?? ''),
        ]); ?>
    <?php } ?>
</a> 
