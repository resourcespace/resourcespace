<?php
# English
# Language File for the ResourceSpace YouTube Plugin
# -------
#
#
$lang["youtube_publish_title"]="YouTube Publishing";
$lang["youtube_publish_linktext"]="Publish to YouTube";
$lang["youtube_publish_configuration"]="Publish to YouTube - Setup";
$lang["youtube_publish_notconfigured"] = "YouTube upload plugin not configured. Please ask your administrator to configure the plugin at";
$lang["youtube_publish_legal_warning"] = "By clicking 'OK' you certify that you own all rights to the content or that you are authorized by the owner to make the content publicly available on YouTube, and that it otherwise complies with the YouTube Terms of Service located at http://www.youtube.com/t/terms.";
$lang['youtube_publish_resource_types_to_include']="Select valid YouTube Resource Types";
$lang["youtube_publish_mappings_title"]="ResourceSpace - YouTube field mappings";
$lang["youtube_publish_title_field"]="Title field";
$lang["youtube_publish_descriptionfields"]="Description fields";
$lang["youtube_publish_keywords_fields"]="Tag fields";
$lang["youtube_publish_url_field"]="Metadata field to store YouTube URL";
$lang["youtube_publish_allow_multiple"]="Allow multiple uploads of the same resource?";
$lang["youtube_publish_log_share"]="Shared on YouTube";
$lang["youtube_publish_unpublished"]="unpublished"; 
$lang["youtube_publishloggedinas"]="You will be publishing to the YouTube account : %youtube_username%"; # %youtube_username% will be replaced, e.g. You will be publishing to the YouTube account : My own RS channel
$lang["youtube_publish_change_login"]="Use a different YouTube account";
$lang["youtube_publish_accessdenied"]="You do not have permission to publish this resource";
$lang["youtube_publish_alreadypublished"]="This resource has already been published to YouTube.";
$lang["youtube_access_failed"]="Failed to access YouTube upload service interface. Please contact your administrator or check your configuration. ";
$lang["youtube_publish_video_title"]="Video title";
$lang["youtube_publish_video_description"]="Video description";
$lang["youtube_publish_video_tags"]="Video tags";
$lang["youtube_publish_access"]="Set access";
$lang["youtube_public"]="public";
$lang["youtube_private"]="private";
$lang["youtube_publish_public"]="Public";
$lang["youtube_publish_private"]="Private";
$lang["youtube_publish_unlisted"]="Unlisted";
$lang["youtube_publish_button_text"]="Publish";
$lang["youtube_publish_authentication"]="Authentication";
$lang["youtube_publish_use_oauth2"]="Use OAuth 2.0?";
$lang["youtube_publish_oauth2_advice"]="YouTube OAuth 2.0 Instructions";
$lang["youtube_publish_oauth2_advice_desc"]="<p>To set up this plugin you need to setup OAuth 2.0 as all other authentication methods are officially deprecated. For this you need to register your ResourceSpace site as a project with Google and get an OAuth client id and secret. There is no cost involved.</p><ul><li>Log onto Google and goto your dashboard: <a href=\"https://console.developers.google.com\" target=\"_blank\">https://console.developers.google.com</a>.</li><li>Create a new project (name and ID don't matter, they are for your reference).</li><li>Click 'ENABLE API'S AND SERVICES' scroll down to ‘YouTube Data API' option.</li><li>Click 'Enable'.</li><li>On the left hand side Select 'Credentials'.</li><li>Then click 'CREATE CREDENTIALS' AND select 'Oauth client ID' in the drop down menu.</li><li>You will be then presented with the 'Create OAuth client ID' page.</li><li>To continue we first need to click the blue button 'Configure consent screen'.</li><li>Fill in the relevant information and save.</li><li>You will be then redirected back to the 'Create OAuth client ID' page.</li><li>Select 'Web application' under 'Application type' and fill in the 'Authorized Javascript origins' with your system base URL and the redirect URL with the callback URL specified at the top of this page and click 'Create'.</li><li>You will then be presented with a screen showing your newly created 'client ID' and 'client secret'.</li><li>Note down the client ID and secret then enter these details below.</li></ul>";
$lang["youtube_publish_developer_key"]="Developer key"; 
$lang["youtube_publish_oauth2_clientid"]="Client ID";
$lang["youtube_publish_oauth2_clientsecret"]="Client Secret";
$lang["youtube_publish_base"]="Base URL";
$lang["youtube_publish_callback_url"]="Callback URL";
$lang["youtube_publish_username"]="YouTube Username";
$lang["youtube_publish_password"]="YouTube Password";
$lang["youtube_publish_existingurl"] = "Existing YouTube URL :- ";
$lang["youtube_publish_notuploaded"] = "Not uploaded";
$lang["youtube_publish_failedupload_error"] = "Upload error";
$lang["youtube_publish_success"] = "Video successfully published!";
$lang["youtube_publish_renewing_token"] = "Renewing access token";
$lang["youtube_publish_category"]="Category";
$lang["youtube_publish_category_error"]="Error retrieving YouTube categories: - ";
$lang["youtube_chunk_size"]="Chunk size to use when uploading to YouTube (MB)";
$lang["youtube_publish_add_anchor"]="Add anchor tags to URl when saving to YouTube URL metadata field?";

$lang["page-title_youtube_publish_setup"]           = "Setup Plugin";
$lang["page-title_youtube_publish_youtube_upload"]  = "Publish Resource to Youtube";