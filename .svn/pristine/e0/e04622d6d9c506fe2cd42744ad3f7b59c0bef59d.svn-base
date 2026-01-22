<?php

include_once '../../include/boot.php';
include_once '../../include/authenticate.php';

$ajax = filter_var(getval('ajax', false), FILTER_VALIDATE_BOOLEAN);
if (!$ajax) {
    header('HTTP/1.1 400 Bad Request');
    die('AJAX only accepted!');
}

/* Variables that should be available for any cases below, otherwise
   they should be put in that use case only */
$return        = array();
$search_string = getval('search_string', '');

// Generate search tags based on a search string
if (filter_var(getval('generate_tags', false), FILTER_VALIDATE_BOOLEAN)) {
    /*
    Space is not part of config separators so we have to make sure we have it for this case
    Double space is used due to removal of quoted search strings which can lead to double spaces left
    in the search string
    */
    $tag_delimiters = array_merge(array(' ', '  '), $config_separators);

    // Quoted search detected, so anything within double quotes should allow for white spaces
    $double_quotes_pos = strpos($search_string, '"');
    if (false !== $double_quotes_pos) {
        $double_quotes_end_pos = strpos(substr($search_string, $double_quotes_pos + 1), '"');

        $quoted_text   = substr($search_string, $double_quotes_pos + 1, $double_quotes_end_pos);
        $search_string = str_replace("\"{$quoted_text}\"", '', $search_string);

        $return[] = $quoted_text;
    }

    $is_special_search = (false !== strpos($search_string, ':') ? true : false);
    if ($is_special_search || false !== strpos($search_string, ',')) {
        foreach (explode(',', $search_string) as $comma_split_keywords) {
            // Special search found, add that to return
            if (false !== strpos($comma_split_keywords, ':')) {
                $return[] = cleanse_string($comma_split_keywords, true);
                continue;
            }

            $return = array_merge($return, split_keywords($comma_split_keywords));
        }
    }

    if (!$is_special_search) {
        $return = array_merge($return, split_keywords($search_string));
    }

    header('Content-Type: application/json');
    echo json_encode($return);
    exit();
}
