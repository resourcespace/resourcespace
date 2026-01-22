<?php
$lang['vimeo_publish_configuration']        = 'Vimeo publish plugin setup';
$lang['vimeo_publish_resource_tool_link']   = 'Publish to Vimeo';
$lang['vimeo_publish_base']                 = 'Base URL';
$lang['vimeo_publish_callback_url']         = 'Callback URL';
$lang['vimeo_publish_authentication']       = 'Authentication';
$lang['vimeo_publish_vimeo_instructions']   = 'Vimeo OAuth 2.0 Instructions';
$lang['vimeo_publish_oauth2_client_id']     = 'Client ID';
$lang['vimeo_publish_oauth2_client_secret'] = 'Client Secret';

$lang['vimeo_publish_rs_field_mappings']         = 'ResourceSpace - Vimeo field mappings';
$lang['vimeo_publish_video_details']             = 'Vimeo - video details';
$lang['vimeo_publish_vimeo_link']                = 'Vimeo link';
$lang['vimeo_publish_video_title']               = 'Video title';
$lang['vimeo_publish_video_description']         = 'Video description';
$lang['vimeo_publish_resource_types_to_include'] = 'Select valid video resource types for Vimeo';

$lang['vimeo_publish_publish_as_user'] = 'You will be publishing to Vimeo as: ';
$lang['vimeo_publish_delete_token']    = 'Use a different Vimeo account (ensure you are logged out of this Vimeo account first)';
$lang['vimeo_publish_button_text']     = 'Publish';
$lang['vimeo_publish_legal_warning']   = 'By clicking \'OK\' you certify that you own all rights to the content or that you are authorized by the owner to make the content publicly available on Vimeo and that it otherwise complies with the Vimeo\'s Terms of Service located at https://vimeo.com/terms';

// Vimeo API instructions:
$lang['vimeo_api_instructions_condition_1'] = 'You will need to register ResourceSpace as an app with Vimeo and get an OAuth client ID and Secret';
$lang['vimeo_api_instructions_condition_2'] = 'Log on to Vimeo with any valid Vimeo account (this does not need to be related to your Vimeo account), then go to <a href="https://developer.vimeo.com/" target="_blank">https://developer.vimeo.com/</a>';
$lang['vimeo_api_instructions_condition_3'] = 'Click on "New App" in the top right';
$lang['vimeo_api_instructions_condition_4'] = 'Fill in all the details.';
$lang['vimeo_api_instructions_condition_5'] = 'For "App URL" use the Base URL (located before these instructions)';
$lang['vimeo_api_instructions_condition_6'] = 'For "App Callback URL" use the Callback URL (located before these instructions)';
$lang['vimeo_api_instructions_condition_7'] = 'Once you have created the app, open it and click on "Authentication" tab in order to find Client ID and Secret';
$lang['vimeo_api_instructions_condition_8'] = 'Copy the client ID and Secret and paste these details below';
$lang['vimeo_api_instructions_condition_9'] = 'Make a request to be allowed to upload videos to Vimeo. Make sure you provide enough information regarding what kind of videos you are going to upload';

// Errors
$lang['vimeo_publish_no_vimeoAPI_files']          = 'ResourceSpace seems to not be able to access Vimeo\'s PHP API files!';
$lang['vimeo_publish_access_denied']              = 'Access denied!';
$lang['vimeo_publish_not_configured']             = 'ResourceSpace plugin "vimeo_publish" has not been configured. Please go to: ';
$lang['vimeo_publish_resource_already_published'] = 'Resource with ID  [ref] has already been published to Vimeo. You can check it out at [vimeo_url]';
$lang['vimeo_publish_resource_published']         = 'Resource has been published to Vimeo. You can check it out at [vimeo_url]';

// Support for use of a single system wide Vimeo account
$lang['vimeo_publish_account_options']      = "Vimeo account options";
$lang["vimeo_publish_allow_user_accounts"]  = "Allow users to publish to their own Vimeo accounts. If false then access to a single Vimeo account must be configured below";
$lang['vimeo_publish_set_account']          = 'Set Vimeo account';

$lang["page-title_vimeo_publish_setup"]     = "Setup Vimeo Publish Plugin";
$lang["page-title_vimeo_publish_vimeo_api"] = "Publish to Vimeo";