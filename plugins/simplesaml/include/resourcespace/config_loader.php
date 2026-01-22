<?php

declare(strict_types=1);

// Inject ResourceSpace configuration to provide all the SimpleSAMLPHP config, SP and IdP details
// set with $simplesamlconfig
$rsconfigloaded = getenv('SIMPLESAMLPHP_RESOURCESPACE_CONFIG_LOADED');
$resourcespace_boot_file = dirname(__DIR__, 4) . '/include/boot.php';
if (!$rsconfigloaded && file_exists($resourcespace_boot_file) && !defined("SYSTEM_UPGRADE_LEVEL")) {
    $suppress_headers = true;
    include $resourcespace_boot_file;
    putenv('SIMPLESAMLPHP_RESOURCESPACE_CONFIG_LOADED=1');
}

if (isset($GLOBALS['simplesaml_rsconfig']) && $GLOBALS['simplesaml_rsconfig']) {
    // Set to use the ResourceSpace files load the config and metadata into SimpleSAML
    $simplesaml_resourcespace_dir = dirname(__DIR__, 2) . '/include/resourcespace';
    putenv('SIMPLESAMLPHP_CONFIG_DIR=' . realpath("{$simplesaml_resourcespace_dir}/config/"));
    $GLOBALS['simplesamlconfig']['config']['metadatadir'] = realpath("{$simplesaml_resourcespace_dir}/metadata/");
}
