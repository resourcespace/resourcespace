<?php

use Vimeo\Vimeo;
use Vimeo\Exceptions\VimeoUploadException;
// Use same file cache config as main TUS config
\TusPhp\Config::set(__DIR__ . '/../../../include/tusconfig.php');

function init_vimeo_api($client_id, $client_secret, $redirect_uri)
    {
    global $baseurl, $lang, $vimeo_publish_vimeo_link_field;

    if('' == $client_id || '' == $client_secret || 0 == $vimeo_publish_vimeo_link_field)
        {
        exit("{$lang['vimeo_publish_not_configured']} <a href=\"{$baseurl}/plugins/vimeo_publish/pages/setup.php\">$baseurl/plugins/vimeo_publish/pages/setup.php</a>");
        }

    get_access_token($client_id, $client_secret, $redirect_uri);
    }


function get_access_token($client_id, $client_secret, $redirect_uri)
    {
    global $userref, $vimeo_publish_allow_user_accounts,$vimeo_publish_system_token,$vimeo_publish_system_state;

    // Response variables from Vimeo
    $vimeo_state_response = getval('state', '');
    $vimeo_code_response  = getval('code', '');
    
    if($vimeo_publish_allow_user_accounts)
        {
        $vimeo_details = ps_query("SELECT vimeo_access_token, vimeo_state FROM user WHERE `ref` = ?", array("i", $userref));
        $access_token  = isset($vimeo_details[0]['vimeo_access_token']) ? $vimeo_details[0]['vimeo_access_token'] : '';
        $state         = isset($vimeo_details[0]['vimeo_state']) ? $vimeo_details[0]['vimeo_state'] : '';
        }
    else
        {
        $access_token  = $vimeo_publish_system_token != "" ? $vimeo_publish_system_token : "";
        $state         = $vimeo_publish_system_state != "" ? $vimeo_publish_system_state : "";
        }

    // User has an access token, no need to continue
    if('' !== $access_token)
        {
        return $access_token;
        }

    // Require user to log in and allow application to use their account
    if('' === $access_token && '' === $vimeo_state_response && '' === $vimeo_code_response)
        {
        $state = base64_encode(openssl_random_pseudo_bytes(30));

        if($vimeo_publish_allow_user_accounts)
            {
            ps_query("UPDATE `user` SET `vimeo_state` = ? WHERE `ref` = ?", array("s", $state, "i", $userref));
            }
        else
            {
            // System wide user, update the config
            $vimeo_publish_config = get_plugin_config("vimeo_publish");
            $vimeo_publish_config["vimeo_publish_system_state"] = $state;            
            set_plugin_config("vimeo_publish",$vimeo_publish_config);
            }

        $vimeo_lib = new Vimeo($client_id, $client_secret);
        $authentication_url = $vimeo_lib->buildAuthorizationEndpoint($redirect_uri, 'public upload edit', $state);
        header("Location: " . $authentication_url);
        exit();
        }

    if($state !== $vimeo_state_response)
        {
        die('States did not match. Please contact a developer for this issue');
        }

    $access_token = '';

    $vimeo_lib = new Vimeo($client_id, $client_secret);
    $token     = $vimeo_lib->accessToken($vimeo_code_response, $redirect_uri);

    if(200 == $token['status'])
        {
        $access_token = $token['body']['access_token'];
        if($vimeo_publish_allow_user_accounts)
            {
            ps_query("UPDATE `user` SET `vimeo_access_token` = ? WHERE `ref` = ?", array("s", $access_token, "i", $userref));
            }
        elseif(checkperm('a'))
            {
            // System wide user, update the config
            $vimeo_publish_config = get_plugin_config("vimeo_publish");
            $vimeo_publish_config["vimeo_publish_system_token"] = $access_token;            
            set_plugin_config("vimeo_publish",$vimeo_publish_config);
            }
        }

    return $access_token;
    }


function delete_vimeo_token($user_ref=0)
    {
    global $userref, $vimeo_publish_allow_user_accounts;
    if($userref == $user_ref)
        {
        ps_query("UPDATE user SET vimeo_access_token = NULL, vimeo_state = NULL WHERE ref = ?", array("i", $user_ref));
        }
    elseif($user_ref==0 && checkperm('a'))
        {
        // Not user specific, clear system config
        $vimeo_publish_config = get_plugin_config("vimeo_publish");
        $vimeo_publish_config["vimeo_publish_system_token"] = '';            
        set_plugin_config("vimeo_publish",$vimeo_publish_config);
        }
    }


function get_vimeo_user($client_id, $client_secret, $access_token, array &$vimeo_user_data)
    {
    $vimeo_lib = new Vimeo($client_id, $client_secret, $access_token);

    $user = $vimeo_lib->request('/me');

    if(200 != $user['status'])
        {
        return false;
        }

    // Return most important bits for the user
    $vimeo_user_data['name']              = $user['body']['name'];
    $vimeo_user_data['link']              = $user['body']['link'];
    $vimeo_user_data['account']           = $user['body']['account'];
    $vimeo_user_data['upload_quota_free'] = $user['body']['upload_quota']['space']['free'];

    return true;
    }


function vimeo_upload($client_id, $client_secret, $access_token, $ref, $file_path, $rs_vimeo_link_field, &$new_video_id, &$error)
    {
    $vimeo_lib = new Vimeo($client_id, $client_secret, $access_token);
    try
        {
        //  Send this to the API library.
        $uri = $vimeo_lib->upload($file_path);

        //  Now that we know where it is in the API, let's get the info about it so we can find the link.
        $video_data = $vimeo_lib->request($uri);
    
        // If successfull, save the link for this resource
        if(200 == $video_data['status'])
            {
            // example URI: "/videos/154836329"
            $new_video_id = substr($video_data['body']['uri'], 8);
            update_field($ref, $rs_vimeo_link_field, $video_data['body']['link']);

            return true;
            }
        }
    catch(VimeoUploadException $e)
        {
        $error = $e->getMessage();
        }

    return false;
    }


function set_video_information($client_id, $client_secret, $access_token, $video_id, $parameters)
    {
    $allowed_params = array('name', 'description');
    $request_params = array();

    foreach($allowed_params as $allowed_param)
        {
        if(array_key_exists($allowed_param, $parameters))
            {
            $request_params[$allowed_param] = $parameters[$allowed_param];
            }
        }

    $vimeo_lib = new Vimeo($client_id, $client_secret, $access_token);
    $video_data = $vimeo_lib->request('/videos/' . $video_id, $request_params, 'PATCH');

    if(200 == $video_data['status'])
        {
        return true;
        }

    return false;
    }