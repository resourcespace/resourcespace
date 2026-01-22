<?php
// vimeo_publish setup page
include '../../../include/boot.php';
include '../../../include/authenticate.php';
include_once '../include/vimeo_publish_functions.php';

if(!checkperm('a'))
    {
    exit($lang['error-permissiondenied']);
    }
    
// Specify the name of this plugin and the heading to display for the page.
$plugin_name         = 'vimeo_publish';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$plugin_page_heading = $lang['vimeo_publish_configuration'];

use Vimeo\Vimeo;
use Vimeo\Exceptions\VimeoUploadException;

// Build Insructions from language strings:
$vimeo_api_instructions = '<div class="Question"><ul>';
$vimeo_api_instruction_conditions = 1;
while(isset($lang['vimeo_api_instructions_condition_' . $vimeo_api_instruction_conditions]))
    {
    $vimeo_api_instructions .= '<li>' . $lang['vimeo_api_instructions_condition_' . $vimeo_api_instruction_conditions] . '</li>';
    $vimeo_api_instruction_conditions++;
    }
$vimeo_api_instructions .= '</ul><div class="clearerleft"></div></div>';

$page_def[] = config_add_html("<p><strong>{$lang['vimeo_publish_base']}:</strong> {$baseurl}<br>");
$page_def[] = config_add_html("<strong>{$lang['vimeo_publish_callback_url']}:</strong> {$vimeo_callback_url}</p>");

if(1 < $vimeo_api_instruction_conditions)
    {
    $page_def[] = config_add_section_header($lang['vimeo_publish_vimeo_instructions']);
    $page_def[] = config_add_html($vimeo_api_instructions);
    }

// OAuth 2.0 - Authentication credentials
$page_def[] = config_add_section_header($lang['vimeo_publish_authentication']);
$page_def[] = config_add_text_input('vimeo_publish_client_id', $lang['vimeo_publish_oauth2_client_id']);
$page_def[] = config_add_text_input('vimeo_publish_client_secret', $lang['vimeo_publish_oauth2_client_secret']);

$page_def[] = config_add_section_header($lang['vimeo_publish_account_options']);
$page_def[] = config_add_boolean_select("vimeo_publish_allow_user_accounts",$lang["vimeo_publish_allow_user_accounts"]);

$page_def[]     = config_add_text_input('vimeo_publish_system_token', "",false,420,false,null,false,true);

$vimeo_user_data = array();
if($vimeo_publish_system_token != "" && get_vimeo_user($vimeo_publish_client_id, $vimeo_publish_client_secret, $vimeo_publish_system_token, $vimeo_user_data))
    {
    $usertext = $vimeo_user_data['name'] . " (" . ucfirst($vimeo_user_data['account']) . " account - " . formatfilesize($vimeo_user_data['upload_quota_free']) . " free)";
    // Can't use CentralSpaceLoad() here or API call will fail
    $usertext .= "<br/><a href='" . generateURL($baseurl_short . "plugins/vimeo_publish/pages/vimeo_api.php",array("delete_token" => "true", "systemtoken" => "true")) . "' >" . $lang['vimeo_publish_delete_token'] . "</a>"; 
    }
else
    {
    $usertext = "<a href='" . generateURL($baseurl_short . "plugins/vimeo_publish/pages/vimeo_api.php",array("delete_token" => "true", "systemtoken" => "true")) . "' >" . $lang['vimeo_publish_set_account'] . "</a>"; 
    }
$userquestion = "<div class='Question'>
        <label>" . $lang['vimeo_publish_publish_as_user'] . "</label>
        <div id='vimeo_user_details' class='Fixed'>" . $usertext . "</div>
        <div class='clearerleft'></div>
    </div>";
$page_def[] = config_add_html($userquestion);

// ResourceSpace - metadata mappings
$page_def[] = config_add_section_header($lang['vimeo_publish_rs_field_mappings']);
$page_def[] = config_add_single_ftype_select('vimeo_publish_vimeo_link_field', $lang['vimeo_publish_vimeo_link']);
$page_def[] = config_add_single_ftype_select('vimeo_publish_video_title_field', $lang['vimeo_publish_video_title']);
$page_def[] = config_add_single_ftype_select('vimeo_publish_video_description_field', $lang['vimeo_publish_video_description']);
$page_def[] = config_add_multi_rtype_select('vimeo_publish_restypes', $lang['vimeo_publish_resource_types_to_include']);


// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';
