<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    http_response_code(403);
    exit(escape($lang['error-permissiondenied']));
}

$plugin_name = 'video_scenes';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}
include_once dirname(__DIR__) . '/include/video_scene_functions.php';

$page_def = [];
foreach (['threshold', 'api_key', 'model'] as $setting) {
    $name = 'video_scene_' . $setting;
    $page_def[] = config_add_text_input($name, $lang[$name], $setting === 'api_key');
}
$page_def[] = config_add_boolean_select('video_scene_audio', $lang['video_scene_audio']);
$page_def[] = config_add_text_input('video_scene_audio_overlap', $lang['video_scene_audio_overlap']);
$page_def[] = config_add_text_input(
    'video_scene_prompt',
    $lang['video_scene_prompt'],
    false,
    700,
    true,
    null,
    false,
    false,
    'video_scene_prompt_help'
);
$page_def[] = config_add_single_ftype_select(
    'video_scene_search_field',
    $lang['video_scene_search_field'],
    700,
    false,
    $TEXT_FIELD_TYPES
);
// This revision's plugin form renderer supports text inputs, but not integer inputs.
$page_def[] = config_add_text_input('video_scene_timeout', $lang['video_scene_timeout']);

$invalid_config = false;
if (getval('submit', '') !== '' || getval('save', '') !== '') {
    if (!enforcePostRequest(false)) {
        exit();
    }
    $settings = [];
    foreach ($page_def as $definition) {
        $settings[$definition[1]] = getval($definition[1], '');
    }
    $invalid_config = !video_scene_valid_config($settings);
    if (!$invalid_config) {
        config_gen_setup_post($page_def, $plugin_name);
    } else {
        foreach ($settings as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        // The shared form renderer otherwise displays a saved notification for any save POST.
        $_POST['save'] = '';
        $_POST['submit'] = '';
    }
}

if ($invalid_config) {
    http_response_code(400);
}
include '../../../include/header.php';
?>
<link rel="stylesheet" href="<?php echo escape($baseurl_short . 'plugins/video_scenes/css/setup.css?v=1.4'); ?>">
<?php
if ($invalid_config) {
    ?>
    <div class="FormError" role="alert"><?php echo escape($lang['video_scene_invalid_config']); ?></div>
    <?php
}
config_gen_setup_html(
    $page_def,
    $plugin_name,
    null,
    $lang['video_scene_title'],
    '<p>' . escape($lang['video_scene_intro']) . '</p>'
);
include '../../../include/footer.php';
