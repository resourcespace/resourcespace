<?php

/**
 * Render scenes below the resource preview in full-page and modal resource views.
 *
 * @return bool False so other plugins may render their panels.
 */
function video_scene_render_panel()
{
    global $ref, $resource, $lang, $baseurl_short, $k, $context, $display;

    if (!video_scene_can_view($resource)) {
        return false;
    }
    $scenes = ps_query(
        'SELECT ref, start_time, end_time, description FROM resource_scene '
        . 'WHERE resource = ? ORDER BY start_frame, ref',
        ['i', (int) $ref]
    );
    if ($scenes === []) {
        return false;
    }
    $show_thumbnails = !check_use_watermark('', $ref);
    $can_edit = video_scene_can_edit($resource);
    $asset_url = $baseurl_short . 'plugins/video_scenes/';
    ?>
    <link rel="stylesheet"
          href="<?php echo escape(generateURL($asset_url . 'css/video-scene.css', ['v' => '1.3'])); ?>">
    <script src="<?php echo escape(generateURL($asset_url . 'js/video-scene.js', ['v' => '1.2'])); ?>"></script>
    <div class="RecordBox video-scene-panel"
         data-player-id="<?php echo escape($context . '_' . $display . '_introvideo' . $ref); ?>"
         data-unavailable="<?php echo escape($lang['video_scene_play_unavailable']); ?>"
         data-empty-description="<?php echo escape($lang['video_scene_no_description']); ?>"
         data-save-failed="<?php echo escape($lang['video_scene_description_save_failed']); ?>"
         data-saved="<?php echo escape($lang['video_scene_description_saved']); ?>">
        <div class="RecordPanel">
            <h2><?php echo escape($lang['video_scene_title']); ?></h2>
            <p class="video-scene-message" role="status" hidden></p>
            <div class="video-scene-scroll">
                <table class="ListviewStyle video-scene-table">
                    <thead>
                        <tr>
                            <?php if ($show_thumbnails) { ?>
                                <th scope="col"><?php echo escape($lang['video_scene_thumbnail']); ?></th>
                            <?php } ?>
                            <th scope="col"><?php echo escape($lang['video_scene_time']); ?></th>
                            <th scope="col"><?php echo escape($lang['video_scene_description']); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scenes as $scene) {
                            $timestamp = video_scene_timestamp((float) $scene['start_time']);
                            $play_label = str_replace('%time', $timestamp, $lang['video_scene_play']);
                            $edit_label = str_replace('%time', $timestamp, $lang['video_scene_edit_description']);
                            $description = $scene['description'] !== ''
                                ? $scene['description'] : $lang['video_scene_no_description'];
                            ?>
                            <tr>
                                <?php if ($show_thumbnails) {
                                    $thumbnail_url = generateURL($asset_url . 'pages/thumbnail.php', [
                                        'ref' => $ref, 'scene' => $scene['ref'], 'k' => $k,
                                    ]);
                                    ?>
                                    <td>
                                        <button type="button" class="video-scene-play video-scene-thumbnail-button"
                                                data-start="<?php echo escape((string) $scene['start_time']); ?>"
                                                aria-label="<?php echo escape($play_label); ?>">
                                            <img class="video-scene-thumbnail" loading="lazy" width="96" height="54"
                                                 src="<?php echo escape($thumbnail_url); ?>" alt="">
                                        </button>
                                    </td>
                                <?php } ?>
                                <td>
                                    <button type="button" class="video-scene-play video-scene-time"
                                            data-start="<?php echo escape((string) $scene['start_time']); ?>"
                                            aria-label="<?php echo escape($play_label); ?>">
                                        <i class="fas fa-play" aria-hidden="true"></i>
                                        <?php echo escape($timestamp); ?>
                                    </button>
                                </td>
                                <td class="video-scene-description">
                                    <div class="video-scene-description-display">
                                        <span class="video-scene-description-text"
                                            ><?php echo escape($description); ?></span>
                                        <?php if ($can_edit) { ?>
                                            <button type="button" class="video-scene-edit"
                                                    aria-label="<?php echo escape($edit_label); ?>">
                                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                                                <?php echo escape($lang['action-edit']); ?>
                                            </button>
                                        <?php } ?>
                                    </div>
                                    <?php if ($can_edit) { ?>
                                        <form class="video-scene-description-form" method="post" hidden
                                              action="<?php echo escape($asset_url . 'pages/edit_description.php'); ?>">
                                            <?php generateFormToken('video_scene_description'); ?>
                                            <input type="hidden" name="ref" value="<?php echo escape($ref); ?>">
                                            <input type="hidden" name="scene"
                                                   value="<?php echo escape($scene['ref']); ?>">
                                            <input type="hidden" name="ajax" value="true">
                                            <textarea name="description" rows="4" maxlength="16000"
                                                aria-label="<?php echo escape($lang['video_scene_description']); ?>"
                                                ><?php echo escape($scene['description']); ?></textarea>
                                            <button type="submit"><?php echo escape($lang['save']); ?></button>
                                            <button type="button" class="video-scene-edit-cancel"
                                                ><?php echo escape($lang['cancel']); ?></button>
                                            <p class="video-scene-edit-error" role="alert" hidden></p>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    return false;
}
