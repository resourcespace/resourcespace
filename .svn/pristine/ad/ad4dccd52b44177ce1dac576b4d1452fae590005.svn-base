<?php
function HookRss2SearchRender_search_actions_add_option($options)
    {
    global $baseurl_short, $search, $restypes, $archive, $lang, $username, $userref, $api_scramble_key, $k;

    $c = count($options);

    if ($k=='')
        {
        $querystring = "user=" . base64_encode($username) . "&search=" . $search . "&restypes=" .$restypes;
        $querystring .= "&archive=" . $archive;
        $private_key = get_api_key($userref);

        // Sign the query using the private key, this needs to be done un-encoded so that it is checked correctly
        $sign = hash("sha256",$private_key . $querystring);

        $query_params = [
            "user" => base64_encode($username),
            "search" => $search,
            "restypes" => $restypes,
            "archive" => $archive,
            "sign" => $sign
        ];

        $url = generateURL($baseurl_short . "plugins/rss2/pages/rssfilter.php", $query_params);

        $data_attribute['url'] = $url;
        $data_attribute['no-ajax'] = true;
        $options[$c]['value'] = 'rss';
        $options[$c]['label'] = $lang["rss_feed_for_search_filter"];
        $options[$c]['data_attr'] = $data_attribute;
        $options[$c]['category'] = ACTIONGROUP_ADVANCED;
        $options[$c]['order_by'] = 500;

        return $options;
        }
    }
