<?php

function HookConditional_termsViewDownloadlink($baseparams, $view_in_browser = false)
{
    global $baseurl, $lang, $resource, $conditional_terms_field, $conditional_terms_value, $fields, $search, $order_by, $archive, $sort, $offset, $download_usage;

    $additional_params = array();

    $conditional_terms_resource_field_values = get_data_by_field($resource['ref'], $conditional_terms_field, false);
    $conditional_terms_field_info = get_resource_type_field($conditional_terms_field);
    if ($conditional_terms_field_info === false || !is_array($conditional_terms_resource_field_values)) {
        return false;
    }

    if ($conditional_terms_field_info['type'] === FIELD_TYPE_CATEGORY_TREE) {
        $resource_values_to_test = get_node_strings($conditional_terms_resource_field_values, false);
    } else {
        $resource_values_to_test = array_column($conditional_terms_resource_field_values, 'name');
    }

    if (!in_array($conditional_terms_value, $resource_values_to_test)) {
        return false;
    }

    if (!$view_in_browser) {
        $redirect = "pages/download_progress.php";
    } else {
        $redirect = "pages/download.php";
        $additional_params = array(
            'direct' => '1',
            'noattach' => 'true',
            );
    }

    if ($download_usage) {
        $redirect = "pages/download_usage.php";
    }

    // Build return url
    $link_params = array();
    $baseparams = explode("&", $baseparams);
    foreach ($baseparams as $param) {
        $key_value = explode("=", $param);
        $link_params[$key_value[0]] = $key_value[1];
    }

    $redirect_params = $link_params;

    // Build redirect url
    $redirect_params = array_merge($redirect_params, array(
        'search' => $search,
        'offset' => $offset,
        'archive' => $archive,
        'sort' => $sort,
        'order_by' => $order_by
    ));

    $redirect_url = generateURL($redirect, $redirect_params, $additional_params);

    $link_params = array_merge($link_params, array('search' => $search, 'url' => $redirect_url));
    $return_url = generateURL($baseurl . '/pages/terms.php', $link_params, array('noredir' => 'true'));

    ?>href="<?php echo $return_url ;?>"<?php

    return true;
}
