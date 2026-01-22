<?php

function HookFalcon_linkAllInitialise()
{
    global $falcon_link_fieldvars;
    config_register_core_fieldvars("Falcon link plugin", $falcon_link_fieldvars);
}

function HookFalcon_linkAllRender_actions_add_collection_option($top_actions, $options, $collection_data)
{
    global $count_result,$lang,$baseurl_short, $usergroup, $falcon_link_usergroups;

    $c = count($options);

    if (in_array($usergroup, $falcon_link_usergroups)) {
        $data_attribute['url'] =  $baseurl_short . "plugins/falcon_link/pages/falcon_link.php?collection=" . urlencode($collection_data['ref']);
        $options[$c]['value'] = 'falcon_link_manage';
        $options[$c]['label'] = $lang["falcon_link_manage"];
        $options[$c]['data_attr'] = $data_attribute;
        return $options;
    }
}
