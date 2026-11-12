<?php

// Google VR View was registered as vr_view in ResourceSpace. Accept the historical
// help-page name too for installations that used a custom registration.
$panoramic_legacy_names = ['vr_view', 'googlevrview'];
$panoramic_settings = [
    'restypes', 'autopan', 'vr_mode_off', 'projection_field', 'projection_value',
    'stereo_field', 'stereo_value', 'yaw_only_field', 'yaw_only_value', 'orig_image', 'orig_video',
];

foreach ($panoramic_legacy_names as $panoramic_legacy_name) {
    $panoramic_old = ps_query('SELECT * FROM plugins WHERE name = ?', ['s', $panoramic_legacy_name]);
    if ($panoramic_old === []) {
        continue;
    }
    $panoramic_old = $panoramic_old[0];
    $panoramic_config = get_plugin_config($panoramic_legacy_name);
    // Match include_plugin_config() for installations still storing only serialized settings.
    if (!is_array($panoramic_config) && !empty($panoramic_old['config'])) {
        $panoramic_config = unserialize(base64_decode($panoramic_old['config']), ['allowed_classes' => false]);
    }
    $panoramic_migrated = [];
    foreach ($panoramic_settings as $panoramic_setting) {
        foreach ([$panoramic_legacy_name . '_', 'vr_view_'] as $panoramic_prefix) {
            if (
                is_array($panoramic_config)
                && array_key_exists($panoramic_prefix . $panoramic_setting, $panoramic_config)
            ) {
                $panoramic_migrated['panoramic_viewer_' . $panoramic_setting]
                    = $panoramic_config[$panoramic_prefix . $panoramic_setting];
                break;
            }
        }
    }
    db_begin_transaction('migrate_panoramic_viewer');
    $panoramic_existing = ps_query('SELECT name FROM plugins WHERE name = ?', ['s', 'panoramic_viewer']);
    if ($panoramic_existing === []) {
        // Rename the row in place, retaining enabled state, groups and priority.
        ps_query(
            'UPDATE plugins SET name = ?, title = ?, descrip = ?, config_url = ?, info_url = ?, '
                . 'inst_version = CASE WHEN inst_version >= 0 THEN ? ELSE inst_version END WHERE name = ?',
            [
                's', 'panoramic_viewer', 's', 'Panoramic Viewer',
                's', '360 degree image and video previews using Photo Sphere Viewer.',
                's', 'plugins/panoramic_viewer/pages/setup.php', 's', '',
                'd', 1.0, 's', $panoramic_legacy_name,
            ]
        );
        clear_query_cache('plugins');
    } else {
        // An administrator may already have configured the replacement. Its settings win.
        $panoramic_migrated = array_replace($panoramic_migrated, get_plugin_config('panoramic_viewer') ?? []);
        ps_query('DELETE FROM plugins WHERE name = ?', ['s', $panoramic_legacy_name]);
    }
    set_plugin_config('panoramic_viewer', $panoramic_migrated);
    db_end_transaction('migrate_panoramic_viewer');
    clear_query_cache('plugins');
}
