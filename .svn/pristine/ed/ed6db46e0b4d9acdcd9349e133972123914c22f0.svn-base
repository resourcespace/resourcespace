<?php

include __DIR__ . "/../../../include/boot.php";
include_once __DIR__ . "/../../../include/image_processing.php";

if (!function_exists("get_api_key")) {
    include __DIR__ . "/../../../include/api_functions.php";
}

include __DIR__ . "/rssfeed.php";

# Get parameters
$user = base64_decode(getval("user", ""));
$sign = getval("sign", "");

# Remove the sign and authmode parameters as these would not have been present when signed on the client.
$strip_params = array("sign","authmode");
parse_str($_SERVER["QUERY_STRING"], $params);
foreach ($strip_params as $strip_param) {
    unset($params[$strip_param]);
}
$query = urldecode(http_build_query($params));

# Authenticate based on the provided signature.
if (!check_api_key($user, $query, $sign)) {
    header("HTTP/1.0 403 Forbidden.");
    echo "HTTP/1.0 403 Forbidden.";
    exit;
}

function xmlentities($text)
{
    return htmlentities($text, ENT_XML1);
}

# Log them in.
setup_user(get_user(get_user_by_username($user)));

$search = getval("search", "");

# Append extra search parameters
$country = getval("country", "");
if ($country != "") {
    $search = (($search == "") ? "" : join(", ", split_keywords($search)) . ", ") . "country:" . $country;
}
$year = getval("year", "");
if ($year != "") {
    $search = (($search == "") ? "" : join(", ", split_keywords($search)) . ", ") . "year:" . $year;
}
$month = getval("month", "");
if ($month != "") {
    $search = (($search == "") ? "" : join(", ", split_keywords($search)) . ", ") . "month:" . $month;
}
$day = getval("day", "");
if ($day != "") {
    $search = (($search == "") ? "" : join(", ", split_keywords($search)) . ", ") . "day:" . $day;
}


if (strpos($search, "!") === false) {
    setcookie("search", $search, 0, '', '', false, true);
} # store the search in a cookie if not a special search
$offset = getval("offset", 0, true);
if (strpos($search, "!") === false) {
    setcookie("saved_offset", $offset, 0, '', '', false, true);
}
if ((!is_numeric($offset)) || ($offset < 0)) {
    $offset = 0;
}

$order_by = getval("order_by", "date");
if (strpos($search, "!") === false) {
    setcookie("saved_order_by", $order_by, 0, '', '', false, true);
}
$display = getval("display", "thumbs");
setcookie("display", $display, 0, '', '', false, true);
$per_page = getval("per_page", get_per_page_cookie());
set_per_page_cookie($per_page);
$archive = getval("archive", 0);
if (strpos($search, "!") === false) {
    setcookie("saved_archive", $archive, 0, '', '', false, true);
}
$jumpcount = 0;

# fetch resource types from query string and generate a resource types cookie
if (getval("resetrestypes", "") == "") {
    $restypes = getval("restypes", "");
} else {
    $restypes = "";
    reset($_GET);foreach ($_GET as $key => $value) {
        if (substr($key, 0, 8) == "resource") {
            if ($restypes != "") {
                $restypes .= ",";
            } $restypes .= substr($key, 8);
        }
    }
    setcookie("restypes", $restypes, 0, '', '', false, true);

    # This is a new search, log this activity
    if ($archive == 2) {
        daily_stat("Archive search", 0);
    } else {
        daily_stat("Search", 0);
    }
}

# If returning to an old search, restore the page/order by
if (!array_key_exists("search", $_GET)) {
    $offset = getval("saved_offset", 0);
    setcookie("saved_offset", $offset, 0, '', '', false, true);
    $order_by = getval("saved_order_by", "relevance");
    setcookie("saved_order_by", $order_by, 0, '', '', false, true);
    $archive = getval("saved_archive", 0);
    setcookie("saved_archive", $archive, 0, '', '', false, true);
}

$refs = array();

# Special query? Ignore restypes
if (strpos($search, "!") !== false) {
    $restypes = "";
}

$result = do_search($search, $restypes, "relevance", $archive, 100, "desc", false, DEPRECATED_STARSEARCH);

# Create a title for the feed
$searchstring = "search=" . urlencode($search) . "&restypes=" . urlencode($restypes) . "&archive=" . urlencode($archive);
if (substr($search, 0, 11) == "!collection") {
    $collection = substr($search, 11);
    $collection = explode(" ", $collection);
    $collection = $collection[0];
    $collectiondata = get_collection($collection);
}
$feed_title = xmlentities($applicationname . " - " . get_search_title($searchstring));

$r = new RSSFeed($feed_title, $baseurl, xmlentities(str_replace("%search%", $searchstring, $lang["filtered_resource_update_for"])));

// rss fields can include any of thumbs, list, xlthumbs display fields, or data_joins.
$all_field_info = get_fields_for_search_display($rss_fields);

$n = 0;
$df = [];
foreach ($rss_fields as $display_field) {
    # Find field in selected list
    for ($m = 0; $m < count($all_field_info); $m++) {
        if ($all_field_info[$m]["ref"] == $display_field) {
            $field_info = $all_field_info[$m];
            $df[$n]['ref'] = $display_field;
            $df[$n]['name'] = $field_info['name'];
            $df[$n]['title'] = $field_info['title'];
            $df[$n]['type'] = $field_info['type'];
            $df[$n]['value_filter'] = $field_info['value_filter'];
            $n++;
        }
    }
}
$n = 0;

# loop and display the results
if (is_array($result)) {
    for ($n = 0; $n < count($result); $n++) {
        # if result item does not contain resource information continue
        if ($result[$n] == 0) {
            continue;
        }

        $ref = $result[$n]["ref"];
        $title = xmlentities(i18n_get_translated($result[$n]["field" . $view_title_field]));
        $creation_date = $result[$n]["creation_date"];

        $year = (int)substr($creation_date, 0, 4);
        $month = (int)substr($creation_date, 5, 2);
        $day = (int)substr($creation_date, 8, 2);
        $hour = (int)substr($creation_date, 11, 2);
        $min = (int)substr($creation_date, 14, 2);
        $sec = (int)substr($creation_date, 17, 2);
        $pubdate = date('D, d M Y H:i:s +0100', mktime($hour, $min, $sec, $month, $day, $year));

        $url = $baseurl . "/pages/view.php?ref=" . $ref;

        $imgurl = "";
        $imgurl = get_resource_path($result[$n]['ref'], true, "col", false);
        if ((int) $result[$n]['has_image'] === RESOURCE_PREVIEWS_NONE) {
            $imgurl = $baseurl . "/gfx/no_preview/default.png";
        } else {
            $imgurl = get_resource_path($result[$n]['ref'], false, "col", false);
        }
        $add_desc = "";
        foreach ($rss_fields as $rssfield) {
            if (is_array($result[$n])) {
                if (isset($result[$n]['field' . $rssfield])) {
                    $value = i18n_get_translated($result[$n]['field' . $rssfield]);
                } else {
                    $value = i18n_get_translated(get_data_by_field($result[$n]['ref'], $rssfield));
                }
                if ($value != "" && $value != ",") {
                    // allow for value filters
                    for ($x = 0; $x < count($df); $x++) {
                        if ($df[$x]['ref'] == $rssfield) {
                            $plugin = "../../value_filter_" . $df[$x]['name'] . ".php";
                            if ($df[$x]['value_filter'] != "") {
                                eval(eval_check_signed($df[$x]['value_filter']));
                            } elseif (file_exists($plugin)) {
                                include $plugin;
                            } elseif ($df[$x]["type"] == 4 || $df[$x]["type"] == 6 || $df[$x]["type"] == 10) {
                                $value = NiceDate($value, true, false);
                            }
                            if ($rss_show_field_titles) {
                                $add_desc .= $df[$x]['title'] . ": ";
                            }
                        }
                    }

                    $add_desc .= xmlentities(strip_tags($value)) . "<![CDATA[<br/>]]>";
                }
            }
        }

        $description = "<![CDATA[<img src='$imgurl' align='left' height='75'  border='0' />]]>" . $add_desc;

        $val["pubDate"] = $pubdate;
        $val["guid"] = $ref;

        $r->AddArticle($title, $url, $description, $val);
    }

    $r->Output();
} else {
    $r->Output(); // empty
}
