<?php

include_once __DIR__ . '/../include/search_notifications_functions.php';

function HookRse_search_notificationsAllRender_search_actions_add_option($options)
    {
    global $lang, $watched_searches_url, $search, $restypes, $archive, $k, $system_read_only, $anonymous_login, $username;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"]))
        {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
        }

    if (isset($anonymous_login) && $username == $anonymous_login)
        {
        return $options;
        }

    if($k != '' || $system_read_only)
        {
        return false;
        }

    // Prevent watch if search criteria are absent
    if(    (!isset($search)   || $search == '') 
        && (!isset($restypes) || $restypes == '') 
        && (!isset($archive)  || $archive == '') )
        {
        return array();
        }

    $data_attr_url = generateURL(
        $watched_searches_url,
        array(
            'callback' => 'add',
            'search'   => $search,
            'restypes' => $restypes,
            'archive'  => $archive,
        )
    );

    $option = array(
        'value'     => 'watch_this_search',
        'label'     => $lang['search_notifications_watch_this_search'],
        'data_attr' => array(
            'url' => $data_attr_url,
        ),
        'category' => ACTIONGROUP_ADVANCED
    );

    array_push($options, $option);

    return $options;
    }

function HookRse_search_notificationsAllRender_actions_add_collection_option($top_actions,array $options)
    {
    if($top_actions)
        {
        return;
        }
    
    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"]))
        {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
        }

    return HookRse_search_notificationsAllRender_search_actions_add_option($options);
    }
