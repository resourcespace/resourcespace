<?php

/**
 * Match a configured value against a resource's node or comma-separated text field.
 *
 * @param int    $ref   Resource reference.
 * @param int    $field Metadata field reference; zero disables the condition.
 * @param string $value Exact value to match.
 * @return bool Whether the configured value is present.
 */
function panoramic_viewer_field_matches(int $ref, int $field, string $value): bool
{
    global $NODE_FIELDS;

    if ($field <= 0) {
        return false;
    }
    $definition = get_resource_type_field($field);
    if (!is_array($definition)) {
        return false;
    }
    $values = in_array($definition['type'], $NODE_FIELDS)
        ? array_column(get_resource_nodes($ref, $field, true), 'name')
        : explode(',', get_data_by_field($ref, $field));

    return in_array($value, $values);
}

/**
 * Check resource type and the optional metadata activation condition.
 * Access to the selected preview must be checked separately before rendering.
 *
 * @param array $resource Resource data, including ref and resource_type.
 * @return bool Whether panoramic preview is configured for this resource.
 */
function panoramic_viewer_enabled(array $resource): bool
{
    global $panoramic_viewer_restypes, $panoramic_viewer_projection_field, $panoramic_viewer_projection_value;

    return isset($resource['ref'], $resource['resource_type'])
        && in_array($resource['resource_type'], $panoramic_viewer_restypes)
        && (!$panoramic_viewer_projection_field || panoramic_viewer_field_matches(
            (int) $resource['ref'],
            (int) $panoramic_viewer_projection_field,
            $panoramic_viewer_projection_value
        ));
}

/**
 * Render a responsive viewer placeholder. The local bundle initialises it and
 * disposes the viewer when AJAX navigation or modal closure removes the element.
 *
 * @param int    $ref    Resource reference.
 * @param string $source Authorised local download URL.
 * @param bool   $video  Whether the source is a browser-playable video.
 * @return bool Always true once the placeholder has been rendered.
 */
function panoramic_viewer_render(int $ref, string $source, bool $video = false): bool
{
    global $panoramic_viewer_stereo_field, $panoramic_viewer_stereo_value;
    global $panoramic_viewer_yaw_only_field, $panoramic_viewer_yaw_only_value;
    global $panoramic_viewer_autopan, $panoramic_viewer_vr_mode_off, $lang;

    $controls = [];
    foreach ($lang as $key => $value) {
        if (strpos($key, 'panoramic_viewer_control_') === 0) {
            $controls[substr($key, strlen('panoramic_viewer_control_'))] = $value;
        }
    }
    $config = [
        'lang' => $controls,
        'source' => $source,
        'video' => $video,
        'autopan' => (bool) $panoramic_viewer_autopan,
        'vrModeOff' => (bool) $panoramic_viewer_vr_mode_off,
        'stereo' => panoramic_viewer_field_matches(
            $ref,
            (int) $panoramic_viewer_stereo_field,
            $panoramic_viewer_stereo_value
        ),
        'yawOnly' => panoramic_viewer_field_matches(
            $ref,
            (int) $panoramic_viewer_yaw_only_field,
            $panoramic_viewer_yaw_only_value
        ),
        'error' => $lang['panoramic_viewer_error'],
    ];
    ?>
    <td id="PreviewImageLarge" class="panoramic-viewer-wrapper">
        <?php if ($config['stereo']) { ?>
            <p role="status"><?php echo escape($lang['panoramic_viewer_stereo_notice']); ?></p>
        <?php } ?>
        <div class="panoramic-viewer" data-panoramic-viewer="<?php echo escape(json_encode($config)); ?>"
             role="region" aria-label="<?php echo escape($lang['panoramic_viewer_title']); ?>">
            <p role="status"><?php echo escape($lang['panoramic_viewer_loading']); ?></p>
        </div>
    </td>
    <?php
    return true;
}

/**
 * Select an existing, permitted source and render it through the download endpoint.
 * Original video falls back to this path even when no transcoded preview exists.
 *
 * @param array $resource Resource data.
 * @param int   $access   Effective resource access from the preview page.
 * @return bool Whether a panoramic viewer was rendered; false keeps the ordinary preview.
 */
function panoramic_viewer_preview(array $resource, int $access): bool
{
    global $baseurl, $userref, $ffmpeg_supported_extensions, $ffmpeg_preview_extension;
    global $panoramic_viewer_orig_image, $panoramic_viewer_orig_video;

    if ($access !== 0 || !panoramic_viewer_enabled($resource)) {
        return false;
    }
    $ref = (int) $resource['ref'];
    $extension = strtolower($resource['file_extension']);
    $video = in_array($extension, $ffmpeg_supported_extensions);
    if ($video) {
        $size = $panoramic_viewer_orig_video ? '' : 'pre';
        $extension = $panoramic_viewer_orig_video ? $extension : $ffmpeg_preview_extension;
        // Original browser playback is limited to these containers; codec support varies by browser.
        if (!in_array(strtolower($extension), ['mp4', 'webm', 'ogv', 'ogg', 'm4v', 'mov'])) {
            return false;
        }
    } else {
        $original = $panoramic_viewer_orig_image && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $size = $original ? '' : 'lpr';
        $extension = $original ? $extension : 'jpg';
    }

    if (
        !resource_download_allowed($ref, $size, $resource['resource_type'])
        || !file_exists(get_resource_path($ref, true, $size, false, $extension))
    ) {
        return false;
    }

    $source = generateURL($baseurl . '/pages/download.php', [
        'ref' => $ref,
        'size' => $size,
        'ext' => $extension,
        'noattach' => 'true',
        'k' => getval('k', ''),
        'access_key' => generate_temp_download_key($userref, $ref, $size),
    ]);
    return panoramic_viewer_render($ref, $source, $video);
}
