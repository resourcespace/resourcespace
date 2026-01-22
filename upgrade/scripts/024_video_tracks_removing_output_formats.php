<?php

if (!is_plugin_activated('video_tracks')) {
    set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'SKipping...Video Tracks plugin is disabled!');
    return;
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Start Video Tracks plugin configuration upgrade...');

$video_tracks_plugin_config = get_plugin_config('video_tracks');

if (
    isset($video_tracks_plugin_config['video_tracks_output_formats_saved'])
    && !empty($video_tracks_plugin_config['video_tracks_output_formats_saved'])
) {
    register_plugin_language('video_tracks');
    $config_to_copy = plugin_decode_complex_configs($video_tracks_plugin_config['video_tracks_output_formats_saved']);
    $notification_users = array_column(get_notification_users('SYSTEM_ADMIN'), 'ref');

    $msg = str_replace(
        [
            '%nl%',
            '[output_formats_config]'
        ],
        [
            PHP_EOL,
            sprintf('$video_tracks_output_formats = %s;%s', var_export($config_to_copy, true), PHP_EOL)
        ],
        $lang['video_tracks_upgrade_msg_deprecated_output_format']
    );

    message_add(
        $notification_users,
        "{$lang['upgrade_script']} #024: {$msg}",
        '',
        null,
        MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN,
        MESSAGE_DEFAULT_TTL_SECONDS
    );
}

unset(
    $video_tracks_plugin_config['video_tracks_output_formats_saved'],
    $video_tracks_plugin_config['video_tracks_export_folder']
);

if (!is_null($video_tracks_plugin_config)) {
    set_plugin_config('video_tracks', $video_tracks_plugin_config);
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Finished Video Tracks plugin configuration upgrade!');
