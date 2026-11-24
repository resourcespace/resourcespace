<?php

/**
 * Disable planar annotations for panoramic resources.
 * Retain still previews for restricted panoramic videos.
 *
 * @return bool Whether the hook replaces the normal rendering.
 */
function HookPanoramic_viewerPreviewFullpreviewresultnav()
{
    global $resource, $access, $ffmpeg_supported_extensions, $annotate_enabled;

    if (!panoramic_viewer_enabled($resource)) {
        return false;
    }
    $annotate_enabled = false;

    if ($access != 0 && in_array($resource['file_extension'], $ffmpeg_supported_extensions)) {
        // Retain the ordinary still preview for restricted video resources.
        $resource['is_transcoding'] = 1;
    }
    // Keep ResourceSpace's back link and resource navigation available.
    return false;
}

/**
 * Replace the normal video player when a permitted panoramic source exists.
 *
 * @return bool Whether the hook replaces the normal rendering.
 */
function HookPanoramic_viewerPreviewCustomflvplay()
{
    global $resource, $access;
    return panoramic_viewer_preview($resource, (int) $access);
}

/**
 * Replace the still preview when a permitted panoramic source exists.
 *
 * @return bool Whether the hook replaces the normal rendering.
 */
function HookPanoramic_viewerPreviewReplacepreviewimage()
{
    global $resource, $access;
    return panoramic_viewer_preview($resource, (int) $access);
}
