<?php

// Get authsources config from ResourceSpace config
global $simplesamlconfig;
if (isset($simplesamlconfig['authsources'])) {
    foreach ($simplesamlconfig['authsources'] as $name => &$authsource) {
        // SSP v2.0 configuration changes - https://simplesamlphp.org/docs/devel/simplesamlphp-upgrade-notes-2.0.html
        if ($name !== 'admin' && !isset($authsource['entityID'])) {
            $authsource['entityID'] = sprintf(
                '%s/plugins/simplesaml/lib/%s/module.php/saml/sp/metadata.php/%s',
                $GLOBALS['baseurl'],
                $GLOBALS['simplesaml_use_www'] ? 'www' : 'public',
                $name
            );
        }

        if (isset($authsource['NameIDPolicy']) && !is_array($authsource['NameIDPolicy'])) {
            debug("simplesaml: Invalid type for configuration 'NameIDPolicy' found for authsource '{$name}'. Now the value is an array - [ 'Format' => the format, 'AllowCreate' => true or false ]");
            $authsource['NameIDPolicy'] = $authsource['NameIDPolicy'] === false
                ? []
                : ['Format' => $authsource['NameIDPolicy'], 'AllowCreate' => true];
        }

        if (isset($authsource['database.slaves'])) {
            debug("simplesaml: Deprecated configuration 'database.slaves' found for authsource '{$name}'. Change it to 'database.secondaries'!");
            $authsource['database.secondaries'] = $authsource['database.slaves'];
        }
    }
    unset($authsource);

    $config = $simplesamlconfig['authsources'];
}
