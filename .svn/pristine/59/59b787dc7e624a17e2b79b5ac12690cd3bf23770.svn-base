<?php
function Hookvm2rsUpload_batchUpload_page_bottom()
    {
    global $userref, $vm2rs_field_id, $lang;
    $ref_user = 0 - $userref;
    $vimeo_copy_path = get_data_by_field($ref_user, $vm2rs_field_id);
    
    if ($vimeo_copy_path == "")
        {
        return false;
        }
    elseif (preg_match("/vimeo.com\/[a-z1-9.-_]+/", $vimeo_copy_path))
        {
        preg_match("/vimeo.com\/([a-z1-9.-_]+)/", $vimeo_copy_path, $matches);
        }
    elseif (preg_match("/vimeo.com(.+)v=([^&]+)/", $vimeo_copy_path))
        {
        preg_match("/v=([^&]+)/", $vimeo_copy_path, $matches);
        }
    else
        {
        return false;
        }

    $vmthumb_id = $matches[1];

    $imgid = $vmthumb_id;

    $data = json_decode(file_get_contents('http://vimeo.com/api/oembed.json?url=https://vimeo.com/'. $imgid) );

    if($data)
        {
        $thumb_path = $data->thumbnail_url;
        }

    echo "<h1>" . $lang['vm2rs_thumb'] . "</h1>";

    echo escape($thumb_path);
    }
 