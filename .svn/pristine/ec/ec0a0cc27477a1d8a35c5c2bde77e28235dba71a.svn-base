<?php

function getPreviewURLForType($resource, $type, $alternative = -1, $page = 1)
    {
    global $baseurl, $use_watermark, $ffmpeg_preview_extension, $ffmpeg_supported_extensions;

    if (!isset($use_watermark)) {
        $use_watermark = check_use_watermark();
    }

    if ($alternative !== -1)
        {
        // Don't use lightbox_preview for video alternative files. Link should use preview.php to play video.
        $alt_file_info = get_alternative_file($resource['ref'], $alternative);
        if (in_array($alt_file_info['file_extension'], $ffmpeg_supported_extensions))
            {
            return false;
            }
        }

    if ($alternative === -1 && $resource['file_extension'] == $ffmpeg_preview_extension)
        {
        return false;
        }
    elseif(resource_has_access_denied_by_RT_size($resource['resource_type'], $type))
        {
        return $baseurl . '/gfx/no_preview/default.png';
        }

    $path = get_resource_path(
        $resource['ref'],
        true,
        $type,
        false,
        $resource['preview_extension'],
        -1,
        $page,
        $use_watermark,
        '',
        $alternative
    );

    if(!file_exists($path))
        {
        return false;
        }

    return get_resource_path(
        $resource['ref'],
        false,
        $type,
        false,
        $resource['preview_extension'],
        -1,
        $page,
        $use_watermark,
        '',
        $alternative);
    }

function getPreviewURL($resource, $alternative = -1, $page = 1)
    {
    // We are looking for the resource image preview
    if ($alternative === -1 && (int) $resource['has_image'] === RESOURCE_PREVIEWS_NONE) {
        return false;
    } // Otherwise we're looking for the alternative image preview, so we don't care about whether or not the resource has an image

    // Try 'scr' first
    $url = getPreviewURLForType($resource, 'scr', $alternative, $page);
    if (!$url || !resource_download_allowed($resource['ref'],'scr',$resource['resource_type'],$alternative))
        {
        // and then 'pre'
        $url = getPreviewURLForType($resource, 'pre', $alternative, $page);
        }

    return $url;
    }

function addLightBox($selector, $url = "", $title = "", $set = "")
    {
    ?>
    <script>
    jQuery(document).ready(function() {
        jQuery('<?php echo $selector ?>')
                <?php if ($url != "")
                    { ?>
                    .attr('href', '<?php echo $url ?>')
                    <?php }
                ?>
                .attr('data-title', "<?php echo escape(str_replace(array("\r","\n","\\")," ", strip_tags(i18n_get_translated($title)))); ?>")
                .attr('data-lightbox', 'lightbox<?php if ($set != "") {echo $set;} ?>');
    });
    </script>
    <?php
    }

?>
