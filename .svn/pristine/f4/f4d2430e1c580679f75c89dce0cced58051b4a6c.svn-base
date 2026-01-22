<?php 

function HookVideo_spliceAllInitialise()
    {
    global $videosplice_fieldvars;
    config_register_core_fieldvars("Video splice plugin",$videosplice_fieldvars);
    }

function HookVideo_spliceAllRender_actions_add_collection_option($top_actions, array $options){
    global $collection,$lang,$pagename,$baseurl_short, $videosplice_allowed_extensions;
 
    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"]))
        {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
        }

    $collection_resources = do_search("!collection" . $collection, '', 'collection', 0, -1, "ASC");
    $videos = [];
    foreach ($collection_resources as $resource) {
        if(in_array($resource['file_extension'], $videosplice_allowed_extensions)) {
            $videos[] = $resource;
        }
    }

    $min_access = collection_min_access($videos);
    unset($GLOBALS['hook_return_value']);

    if ($pagename=="collections" && count($videos) > 0 && $min_access === 0) {
        $option = array(
            "value" => "video_splice",
            "label" => $lang["action-splice"],
            "data_attr" => array(
                "url" => generateURL("{$baseurl_short}plugins/video_splice/pages/splice.php", array("collection" => $collection)),
            ),
            "category" => ACTIONGROUP_ADVANCED
        );

        $options[] = $option;
    }

    return $options;
}
