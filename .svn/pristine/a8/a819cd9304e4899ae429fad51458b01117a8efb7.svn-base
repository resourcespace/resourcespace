<?php

function HookFlickr_theme_publishAllRender_actions_add_collection_option($top_actions,$options,$collection_data)
    {
    global $lang, $baseurl_short, $pagename;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"]))
        {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
        }    

    $result=get_collection_resources($collection_data['ref']);
    $count_result=count($result);
    
    if ($count_result>0) # Don't show the option if the collection is empty.
        {
            $lang_string=$lang["publish_to_flickr"];
            $unpublished = ps_value("select count(*) value from resource join collection_resource on resource.ref = collection_resource.resource where collection_resource.collection = ? and flickr_photo_id is null", array("i",$collection_data["ref"]), 0);
            if ($unpublished>0) {
                $lang_string.=" <strong>(" . ($unpublished==1 ? $lang["unpublished-1"] : str_replace("%number", $unpublished, $lang["unpublished-2"])) . ")</strong>";
            }
        $data_attribute['url'] = sprintf('%splugins/flickr_theme_publish/pages/sync.php?theme=%s',
            $baseurl_short,
            urlencode($collection_data["ref"])
        );
        
        // Add new option
        $c=count($options);
        $options[$c]['value']='flickr_publish';
        $options[$c]['label']=$lang_string;
        $options[$c]['data_attr']=$data_attribute;
        $options[$c]['category']=ACTIONGROUP_ADVANCED;
    
        return $options;
        }
    }
    
    