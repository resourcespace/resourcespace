<?php

/**
 * Disable planar annotations for configured panoramic resources.
 *
 * @param int $ref Resource reference.
 * @param array|false $resource Resource data.
 * @return bool Whether the hook replaces the normal rendering.
 */
function HookPanoramic_viewerViewAftergetresourcedataview($ref, $resource)
{
    global $annotate_enabled;
    if (is_array($resource) && panoramic_viewer_enabled($resource)) {
        $annotate_enabled = false;
    }
    return false;
}

/**
 * Open panoramic previews through the ResourceSpace modal.
 *
 * @param string $preview_title Accessible preview link title.
 * @return bool Whether the hook replaces the normal rendering.
 */
function HookPanoramic_viewerViewReplacepreviewlink(string $preview_title)
{
    global $resource, $baseurl_short, $urlparams, $plugins;
    if (!panoramic_viewer_enabled($resource)) {
        return false;
    }
    // The full preview must open in ResourceSpace rather than the lightbox viewer.
    $plugins = array_values(array_diff($plugins, ['lightbox_preview']));
    $url = generateURL($baseurl_short . 'pages/preview.php', $urlparams, ['ext' => $resource['preview_extension']]);
    $extra = hook('previewextraurl');
    if ($extra !== '') {
        $url .= '&' . $extra;
    }
    ?>
    <div id="previewimagewrapper">
        <a id="previewimagelink" class="enterLink" href="<?php echo escape($url); ?>"
           title="<?php echo escape($preview_title); ?>" style="position:relative;"
           onclick="return ModalLoad(this,false,true,top);">
    <?php
    return true;
}

/**
 * Keep the resource view thumbnail static until the full preview opens.
 *
 * @return bool Whether the hook replaces the normal rendering.
 */
function HookPanoramic_viewerViewRenderinnerresourcepreview()
{
    global $resource;
    if (panoramic_viewer_enabled($resource)) {
        // Use a still thumbnail here; initialise the panorama only in the full preview.
        $resource['is_transcoding'] = 1;
    }
    return false;
}
