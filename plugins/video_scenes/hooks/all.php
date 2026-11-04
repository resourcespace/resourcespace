<?php

include_once dirname(__DIR__) . '/include/video_scene_functions.php';
include_once dirname(__DIR__, 3) . '/include/job_functions.php';

/**
 * Queue analysis when original video previews are created, including manual recreation.
 *
 * @param int  $ref          Resource reference.
 * @param int  $alternative Alternative file reference; -1 means original.
 * @param bool $generateall Whether all preview sizes were requested.
 * @return void
 */
function HookVideo_scenesAllAfterpreviewcreation($ref, $alternative, $generateall = false)
{
    video_scene_on_preview_creation((int) $ref, (int) $alternative);
}

/**
 * Queue uploaded videos even when preview generation is disabled or deferred.
 *
 * @param int $resource_ref Uploaded resource reference supplied by the upload hook.
 * @return void
 */
function HookVideo_scenesAllUploadfilesuccess($resource_ref)
{
    video_scene_on_upload((int) $resource_ref);
}

/**
 * Remove scene data when the resource is permanently deleted.
 *
 * @param array $resource Resource being deleted after core permission checks.
 * @return void
 */
function HookVideo_scenesAllDelete_resource_extra($resource)
{
    video_scene_delete_resource((int) $resource['ref'], $resource);
}
