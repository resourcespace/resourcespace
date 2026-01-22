<?php

use function ImageBanks\getProviders;
use function ImageBanks\listProviderInstanceNames;

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if(!checkperm('a'))
    {
    header('HTTP/1.1 401 Unauthorized');
    exit($lang['error-permissiondenied']);
    }

$plugin_name = 'image_banks';
if(!in_array($plugin_name, $plugins))
    {
    plugin_activate_for_setup($plugin_name);
    }

$dependency_errors = [];
$page_def = array();
$providers_select_list = [];

[$providers, $errors] = getProviders($image_banks_loaded_providers);
foreach($providers as $provider)
    {
    $provider_name = $provider->getName();

    if ($provider->checkDependencies() !== [])
        {
        $dependency_errors[] = str_replace('%PROVIDER', $provider_name, "{$lang['image_banks_provider_unmet_dependencies']}");
        }

    $providers_select_list = array_merge($providers_select_list, listProviderInstanceNames($provider));
    $page_def[] = config_add_section_header($provider_name);
    $page_def = $provider->buildConfigPageDefinition($page_def);
    }

$page_def = array_merge(
    [
        config_add_multi_select(
            'image_banks_selected_providers',
            $lang['image_banks_label_select_providers'],
            $providers_select_list,
            false,
            420
        )
    ],
    $page_def
);

// Render setup page ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
array_map('render_top_page_error_style', array_merge($dependency_errors, $errors));
config_gen_setup_html($page_def, $plugin_name, null, $lang['image_banks_configuration']);
include '../../../include/footer.php';