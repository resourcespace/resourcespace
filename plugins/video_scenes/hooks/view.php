<?php

include_once dirname(__DIR__) . '/include/video_scene_functions.php';
include_once dirname(__DIR__) . '/include/video_scene_render.php';

/**
 * Render the scene panel on resource pages.
 *
 * @return bool False so other plugins may render their panels.
 */
function HookVideo_scenesViewCustompanels()
{
    return video_scene_render_panel();
}
