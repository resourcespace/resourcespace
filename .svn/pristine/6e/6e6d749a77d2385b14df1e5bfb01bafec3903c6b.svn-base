<?php

/** @var array $config */
require dirname(__DIR__, 3) . '/lib/config/config.php.dist';

// Set SimpleSAML config from ResourceSpace config options
global $simplesamlconfig, $simplesaml_config_defaults;

// Configure SimpleSAMLPHP default settings because the logic depends on the simplesaml plugins' own settings.
// Point to the canonical URL (source: https://simplesamlphp.org/docs/stable/simplesamlphp-install.html)
$simplesaml_config_defaults['baseurlpath'] = sprintf(
    '%s/plugins/simplesaml/lib/%s/',
    $GLOBALS['baseurl'],
    $GLOBALS['simplesaml_use_www'] ? 'www' : 'public'
);

foreach ($simplesaml_config_defaults as $setting => $value) {
    $config[$setting] = $value;
}

foreach ($simplesamlconfig['config'] as $option => $configvalue) {
    $config[$option] = $configvalue;
}

// Plain-text admin-passwords are no longer allowed to be used in SSP (since v2.3) but ResourceSpace existing config
// may still use it so convert it now on the fly (let it use the best algorithm available)
if (is_null(password_get_info($config['auth.adminpassword'])['algo'])) {
    $cached_adminpassword_hash = get_sysvar('simplesaml_auth.adminpassword_hash', '');
    if ($cached_adminpassword_hash !== '' && !is_null(password_get_info($cached_adminpassword_hash)['algo'])) {
        $config['auth.adminpassword'] = $cached_adminpassword_hash;
    } else {
        $hasher = new Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher(4, 65536, null, null);
        $config['auth.adminpassword'] = $hasher->hash($config['auth.adminpassword']);
        set_sysvar('simplesaml_auth.adminpassword_hash', $config['auth.adminpassword']);
    }
}
