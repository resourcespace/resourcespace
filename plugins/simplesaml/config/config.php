<?php

$simplesaml_version = "2.3.6";
$simplesaml_check_phpversion = "8.1";
$simplesaml_site_block = false;
$simplesaml_login = true;
$simplesaml_allow_public_shares = true;
$simplesaml_allowedpaths = array();
$simplesaml_allow_standard_login = true;

$simplesaml_username_attribute = "uid";
$simplesaml_fullname_attribute = "cn";
$simplesaml_email_attribute = "mail";
$simplesaml_username_suffix = "";
$simplesaml_group_attribute = "groups";
$simplesaml_update_group = false;
$simplesaml_fallback_group = 2;
$simplesaml_groupmap = array();
$simplesaml_sp = "resourcespace-sp";
$simplesaml_fullname_separator = ",";
$simplesaml_username_separator = ".";
$simplesaml_prefer_standard_login = true;
$simplesaml_custom_attributes = '';
//$simplesaml_lib_path = ''; // This must now must be set in the primary config file
$simplesaml_create_new_match_email = false;
$simplesaml_allow_duplicate_email = false;
$simplesaml_multiple_email_notify = "";

// Enable ResourceSpace to be configured with additional local authorisation of users based upon an extra attribute
// (ie. assertion/ claim) in the response from the IdP. This assertion will be used by the plugin to determine whether
// the user is allowed to log in to ResourceSpace or not
$simplesaml_authorisation_claim_name = '';
$simplesaml_authorisation_claim_value = '';

/*
Options are:-
 - 0 for Local SAML configuration files (legacy)
 - 1 for ResourceSpace configuration file (config.php)
 - 2 for Metadata URL (XML)
*/
$simplesaml_rsconfig = 1;
$simplesaml_check_idp_cert_expiry = true;

/*
To upgrade to version 2.3.5 without forcing the IDP to re-exchange the SP metadata the plugin will configure SSP with
the old route usin www instead of public. See https://simplesamlphp.org/docs/devel/simplesamlphp-upgrade-notes-2.0.html

The web server MUST still be capable of responding to requests for the www route for this to work by rewriting it to the
public folder instead (an alias).
*/
$simplesaml_use_www = true;

// When using ResourceSpace to store SAML config these setttings are initialised and set in the following pages:-
// plugins/simplesaml/lib/lib/_autoload.php (normally a symlink for src/_autoload.php)
// plugins/simplesaml/lib/src/_autoload.php
// plugins/simplesaml/include/resourcespace/config/config.php
// plugins/simplesaml/include/resourcespace/config/authsources.php
// plugins/simplesaml/include/resourcespace/metadata/saml20-idp-remote.php

// Set some defaults to ease setup
global $baseurl_short, $email_from, $application_name, $scramble_key, $storagedir;
$samlid = hash('sha256', "saml" . $scramble_key);
$samltempdir = get_temp_dir(false, "simplesaml");
$simplesaml_config_defaults = [
    'technicalcontact_name' =>  $application_name,
    'technicalcontact_email' =>  $email_from,
    'secretsalt' =>  $samlid,
    'cachedir' =>  $samltempdir,
    'datadir' => "{$storagedir}/simplesamldata",
    'loggingdir' => $samltempdir,
    'logging.logfile' => 'saml_' . md5($samlid) . '.log',
    'logging.handler' => 'file',
    'admin.protectmetadata' => false,
    'timezone' => null,
    'session.cookie.secure' => true,
    'session.cookie.samesite' => 'Lax',
];

$simplesaml_metadata_url = "";