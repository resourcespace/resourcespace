<?php

declare(strict_types=1);

include_once dirname(__DIR__, 3) . '/include/boot.php';
include_once RESOURCESPACE_BASE_PATH . '/include/authenticate.php';
if (!checkperm('a')) {
    http_response_code(401);
    exit(escape($lang['error-permissiondenied']));
}

$plugin_name = 'brand_guidelines';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$image_sizes = array_diff_key(
    array_column(get_all_image_sizes(true), 'name', 'id'),
    array_flip(['col', 'hpr'])
);

$page_def[] = config_add_single_select(
    'brand_guidelines_fallback_size_for_thumbnail',
    $lang['brand_guidelines_fallback_size_thm'],
    $image_sizes
);

$page_def[] = config_add_single_select(
    'brand_guidelines_fallback_size_for_half_width',
    $lang['brand_guidelines_fallback_size_half_width'],
    $image_sizes
);

$page_def[] = config_add_single_select(
    'brand_guidelines_fallback_size_for_full_width',
    $lang['brand_guidelines_fallback_size_full_width'],
    $image_sizes
);

// Render setup page ritual
config_gen_setup_post($page_def, $plugin_name);
include_once RESOURCESPACE_BASE_PATH . '/include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $lang['brand_guidelines_configuration']);
include_once RESOURCESPACE_BASE_PATH . '/include/footer.php';
