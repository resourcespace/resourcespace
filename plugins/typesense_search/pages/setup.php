<?php

/**
 * typesense_search setup page.
 */

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    header('HTTP/1.1 401 Unauthorized');
    exit($lang['error-permissiondenied']);
}

$plugin_name = 'typesense_search';

if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$plugin_page_heading = $lang['typesense_search_configuration'] ?? 'Typesense search configuration';

$page_def[] = config_add_section_header($lang['typesense_search_server']);

$page_def[] = config_add_text_input(
    'typesense_search_host',
    $lang['typesense_search_host'] 
);

$page_def[] = config_add_text_input(
    'typesense_search_port',
    $lang['typesense_search_port']
);

$page_def[] = config_add_text_input(
    'typesense_search_protocol',
    $lang['typesense_search_protocol'] 
);

$page_def[] = config_add_text_input(
    'typesense_search_api_key',
    $lang['typesense_search_api_key'] 
);

$page_def[] = config_add_text_input(
    'typesense_search_collection',
    $lang['typesense_search_collection'] 
);

$page_def[] = config_add_text_input(
    'typesense_search_timeout',
    $lang['typesense_search_timeout'] 
);

config_gen_setup_post($page_def, $plugin_name);

include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';
