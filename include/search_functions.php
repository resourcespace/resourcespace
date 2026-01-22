<?php
# Search functions
# Functions to perform searches (read only)
# - For resource indexing / keyword creation, see resource_functions.php

/**
 * Resolves the most commonly used keyword that sounds like the given keyword.
 *
 * This function attempts to find a keyword that phonetically matches the provided keyword
 * using the Soundex algorithm. If no Soundex match is found, it will suggest the most commonly
 * used keyword that starts with the same first few letters.
 *
 * @param string $keyword The keyword to resolve.
 * @return string|false Returns the matched keyword if found, or false if no match is found.
 */
function resolve_soundex($keyword)
{
    global $soundex_suggest_limit;
    $soundex = ps_value("SELECT keyword value FROM keyword WHERE soundex = ? AND keyword NOT LIKE '% %' AND hit_count >= ? ORDER BY hit_count DESC LIMIT 1", ["s",soundex($keyword),"i",$soundex_suggest_limit], false);
    if (($soundex === false) && (strlen($keyword) >= 4)) {
        # No soundex match, suggest words that start with the same first few letters.
        return ps_value("SELECT keyword value FROM keyword WHERE keyword LIKE ? AND keyword NOT LIKE '% %' ORDER BY hit_count DESC LIMIT 1", ["s",substr($keyword, 0, 4) . "%"], false);
    }
    return $soundex;
}

/**
 * Suggests search refinements based on common keywords from a set of resource references.
 *
 * This function analyzes the provided array of resource references and the original search query.
 * It identifies common keywords associated with the specified resources and suggests new search queries
 * by appending these keywords to the original search query, provided they are not already included in it.
 *
 * @param array $refs An array of resource references to analyze.
 * @param string $search The original search query.
 * @return array An array of suggested search refinements. Returns an empty array if no refinements can be suggested.
 */
function suggest_refinement($refs, $search)
{
    if (count($refs) == 0) {
        return array();
    } // Nothing to do, nothing to return
    $in = ps_param_insert(count($refs));
    $suggest = array();
    # find common keywords
    $refine = ps_query("SELECT k.keyword,count(k.ref) c FROM resource_node rn LEFT JOIN node n ON n.ref=rn.node LEFT JOIN node_keyword nk ON nk.node=n.ref LEFT JOIN keyword k on nk.keyword=k.ref WHERE rn.resource IN ($in) AND length(k.keyword)>=3 AND length(k.keyword)<=15 AND k.keyword NOT LIKE '%0%' AND k.keyword NOT LIKE '%1%' AND k.keyword NOT LIKE '%2%' AND k.keyword NOT LIKE '%3%' AND k.keyword NOT LIKE '%4%' AND k.keyword NOT LIKE '%5%' AND k.keyword NOT LIKE '%6%' AND k.keyword NOT LIKE '%7%' AND k.keyword NOT LIKE '%8%' AND k.keyword NOT LIKE '%9%' GROUP BY k.keyword ORDER BY c DESC LIMIT 5", ps_param_fill($refs, "i"));
    for ($n = 0; $n < count($refine); $n++) {
        if (strpos($search, $refine[$n]["keyword"]) === false) {
            $suggest[] = $search . " " . $refine[$n]["keyword"];
        }
    }
    return $suggest;
}

/**
 * Retrieves a list of fields suitable for advanced searching.
 *
 * This function queries the database for resource type fields that are marked for advanced searching.
 * It checks for visibility based on user permissions and whether the fields are hidden from the search.
 * If a designated date field is specified and not already included in the results, it will be added
 * to the beginning of the list if it matches the resource types of the other fields.
 *
 * @param bool $archive Whether to include fields related to archived resources. Defaults to false.
 * @param string $hiddenfields A comma-separated string of field references that should be hidden from the search.
 * @return array An array of searchable fields that can be used in an advanced search form.
 */
function get_advanced_search_fields($archive = false, $hiddenfields = "")
{
    global $FIXED_LIST_FIELD_TYPES, $date_field, $daterange_search;
    # Returns a list of fields suitable for advanced searching.
    $return = array();

    $date_field_already_present = false; # Date field not present in searchable fields array
    $date_field_data = null; # If set then this is the date field to be added to searchable fields array

    $hiddenfields = explode(",", $hiddenfields);

    $fields = ps_query("SELECT " . columns_in("resource_type_field", "f") . ", GROUP_CONCAT(rtfrt.resource_type) resource_types FROM resource_type_field f LEFT JOIN resource_type_field_resource_type rtfrt ON rtfrt.resource_type_field = f.ref  WHERE f.advanced_search=1 AND f.active=1 AND (f.keywords_index=1 AND length(f.name)>0) AND (f.global=1 OR rtfrt.resource_type IS NOT NULL) GROUP BY f.ref ORDER BY f.global DESC, f.order_by ASC", [], "schema"); // Constants do not need to be parameters in the prepared statement
    # Apply field permissions and check for fields hidden in advanced search
    for ($n = 0; $n < count($fields); $n++) {
        if (metadata_field_view_access($fields[$n]["ref"]) && !in_array($fields[$n]["ref"], $hiddenfields)) {
            $return[] = $fields[$n];
            if ($fields[$n]["ref"] == $date_field) {
                $date_field_already_present = true;
            }
        }
    }
    # If not already in the list of advanced search metadata fields, insert the field which is the designated searchable date ($date_field)
    if (
        !$date_field_already_present
        && $daterange_search
        && metadata_field_view_access($date_field)
        && !in_array($date_field, $hiddenfields)
    ) {
        $date_field_data = get_resource_type_field($date_field);
        if (!is_array($date_field_data) || is_null($date_field_data['ref'])) {
            debug("WARNING: Invalid \$date_field specified in config : " . $date_field);
            return $return;
        }
        # Insert searchable date field so that it appears as the first array entry for a given resource type
        $return1 = array();
        for ($n = 0; $n < count($return); $n++) {
            if (
                isset($date_field_data)
                && count(array_intersect(explode(",", (string)$return[$n]["resource_types"]), explode(",", (string)$date_field_data['resource_types']))) > 0
            ) {
                    $return1[] = $date_field_data;
                    $date_field_data = null; # Only insert it once
            }
            $return1[] = $return[$n];
        }
        # If not yet added because it's resource type differs from everything in the list then add it to the end of the list
        if (is_array($date_field_data)) {
            $return1[] = $date_field_data;
            $date_field_data = null; # Keep things tidy
        }
        return $return1;
    }

    # Designated searchable date_field is already present in the lost of advanced search metadata fields        }
    return $return;
}

/**
 * Retrieves a list of fields suitable for advanced searching within collections.
 *
 * This function constructs an array of fields specifically related to collections, including
 * collection title, keywords, and owner. It checks against a list of hidden fields to determine
 * which fields should be included in the return array for advanced searching.
 *
 * @param bool $archive Whether to include fields related to archived collections. Defaults to false.
 * @param string $hiddenfields A comma-separated string of field references that should be hidden from the search.
 * @return array An array of fields suitable for advanced searching in the context of collections.
 */
function get_advanced_search_collection_fields($archive = false, $hiddenfields = "")
{
    $return = array();

    $hiddenfields = explode(",", $hiddenfields);

    $fields[] = array("ref" => "collection_title", "name" => "collectiontitle", "display_condition" => "", "tooltip_text" => "", "title" => "Title", "type" => 0, "global" => 0, "resource_types" => 'Collections');
    $fields[] = array("ref" => "collection_keywords", "name" => "collectionkeywords", "display_condition" => "", "tooltip_text" => "", "title" => "Keywords", "type" => 0, "global" => 0, "resource_types" => 'Collections');
    $fields[] = array("ref" => "collection_owner", "name" => "collectionowner", "display_condition" => "", "tooltip_text" => "", "title" => "Owner", "type" => 0, "global" => 0, "resource_types" => 'Collections');
    # Apply field permissions and check for fields hidden in advanced search
    for ($n = 0; $n < count($fields); $n++) {
        if (!in_array($fields[$n]["ref"], $hiddenfields)) {
            $return[] = $fields[$n];
        }
    }

    return $return;
}

/**
 * Constructs a search query string from the posted search form data.
 *
 * This function takes the advanced search form fields and assembles them
 * into a search query string that can be used for a standard search. It
 * processes various input fields, including dates, keywords, and resource IDs,
 * while respecting user permissions and field visibility settings.
 *
 * @param array $fields An array of fields used in the search form.
 * @param bool $fromsearchbar Indicates if the search is initiated from a search bar.
 * @return string The constructed search query string based on the input data.
 */
function search_form_to_search_query($fields, $fromsearchbar = false)
{
    global $auto_order_checkbox,$checkbox_and,$resource_field_verbatim_keyword_regex;
    $search = "";
    if (getval("basicyear", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= "basicyear:" . getval("basicyear", "");
    }
    if (getval("basicmonth", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= "basicmonth:" . getval("basicmonth", "");
    }
    if (getval("basicday", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= "basicday:" . getval("basicday", "");
    }
    if (getval("startdate", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= "startdate:" . getval("startdate", "");
    }
    if (getval("enddate", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= "enddate:" . getval("enddate", "");
    }
    if (getval("start-y", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= "startdate:" . getval("start-y", "");
        if (getval("start-m", "") != "") {
            $search .= "-" . getval("start-m", "");
            if (getval("start-d", "") != "") {
                $search .= "-" . getval("start-d", "");
            } else {
                $search .= "-01";
            }
        } else {
            $search .= "-01-01";
        }
    }
    if (getval("end-y", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= "enddate:" . getval("end-y", "");
        if (getval("end-m", "") != "") {
            $search .= "-" . getval("end-m", "");
            if (getval("end-d", "") != "") {
                $search .= "-" . getval("end-d", "");
            } else {
                $search .= "-31";
            }
        } else {
            $search .= "-12-31";
        }
    }
    if (getval("allfields", "") != "") {
        if ($search != "") {
            $search .= ", ";
        }
        $search .= join(", ", explode(" ", getval("allfields", ""))); # prepend 'all fields' option
    }
    if (getval("resourceids", "") != "") {
        $listsql = "!list" . join(":", trim_array(split_keywords(getval("resourceids", ""))));
        $search = $listsql . " " . $search;
    }
    $full_text_search = getval(FULLTEXT_SEARCH_PREFIX, "");
    if ($full_text_search != "") {
        if ($search != "") {
            $search .= " ";
        }
        $full_text_search = str_replace("\"", FULLTEXT_SEARCH_QUOTES_PLACEHOLDER, $full_text_search);
        $search .= '"' . FULLTEXT_SEARCH_PREFIX . ':' . $full_text_search . '"';
    }

    for ($n = 0; $n < count($fields); $n++) {
        switch ($fields[$n]["type"]) {
            case FIELD_TYPE_TEXT_BOX_MULTI_LINE:
            case FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE:
            case FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE:
                $name = "field_" . $fields[$n]["ref"];
                $value = getval($name, "");
                if ($value != "") {
                    if (
                        isset($resource_field_verbatim_keyword_regex[$fields[$n]["ref"]])
                        && preg_match(
                            $resource_field_verbatim_keyword_regex[$fields[$n]["ref"]],
                            str_replace('*', '', $value)
                        )
                    ) {
                        // Keyword matches verbatim regex, do not split
                        $vs = [$value];
                    } else {
                        $vs = split_keywords($value, false, false, false, false, true);
                    }
                    for ($m = 0; $m < count($vs); $m++) {
                        if ($search != "") {
                            $search .= ", ";
                        }
                        $search .= ((strpos($vs[$m], "\"") === false) ? $fields[$n]["name"] . ":" . $vs[$m] : "\"" . $fields[$n]["name"] . ":" . substr($vs[$m], 1, -1) . "\""); // Move any quotes around whole field:value element so that they are kept together
                    }
                }
                break;

            case FIELD_TYPE_DROP_DOWN_LIST: # -------- Dropdowns / check lists
            case FIELD_TYPE_CHECK_BOX_LIST:
                if ($fields[$n]["display_as_dropdown"]) {
                    # Process dropdown box
                    $name = "field_" . $fields[$n]["ref"];
                    $value = getval($name, "");
                    if ($value !== "") {
                        if ($search != "") {
                            $search .= ", ";
                        }
                        $search .= ((strpos($value, " ") === false) ? $fields[$n]["name"] . ":" . $value : "\"" . $fields[$n]["name"] . ":" . substr($value, 1, -1) . "\"");
                    }
                } else {
                    # Process checkbox list
                    $options = array();
                    node_field_options_override($options, $fields[$n]['ref']);
                    $p = "";
                    $c = 0;
                    for ($m = 0; $m < count($options); $m++) {
                        $name = $fields[$n]["ref"] . "_" . md5($options[$m]);
                        $value = getval($name, "");
                        if ($value == "yes") {
                            $c++;
                            if ($p != "") {
                                $p .= ";";
                            }
                            $p .= mb_strtolower(i18n_get_translated($options[$m]), 'UTF-8');
                        }
                    }

                    if (($c == count($options) && !$checkbox_and) && (count($options) > 1)) {
                        # all options ticked - omit from the search (unless using AND matching, or there is only one option intended as a boolean selection)
                        $p = "";
                    }
                    if ($p != "") {
                        if ($search != "") {
                            $search .= ", ";
                        }
                        if ($checkbox_and) {
                            $p = str_replace(";", ", {$fields[$n]["name"]}:", $p); // this will force each and condition into a separate union in do_search (which will AND)
                            if ($search != "") {
                                $search .= ", ";
                            }
                        }
                        $search .= $fields[$n]["name"] . ":" . $p;
                    }
                }
                break;

            case FIELD_TYPE_DATE_AND_OPTIONAL_TIME:
            case FIELD_TYPE_EXPIRY_DATE:
            case FIELD_TYPE_DATE:
            case FIELD_TYPE_DATE_RANGE:
                $name = "field_" . $fields[$n]["ref"];
                $datepart = "";
                $value = "";
                if (strpos($search, $name . ":") === false) {
                    // Get each part of the date
                    $key_year = $name . "-y";
                    $value_year = getval($key_year, "");

                    $key_month = $name . "-m";
                    $value_month = getval($key_month, "");

                    $key_day = $name . "-d";
                    $value_day = getval($key_day, "");

                    // The following constructs full date yyyy-mm-dd or partial dates yyyy-mm or yyyy
                    // However yyyy-00-dd is interpreted as yyyy because its not a valid partial date

                    $value_date_final = "";
                    // Process the valid combinations, otherwise treat it as an empty date
                    if ($value_year != "" && $value_month != "" && $value_day != "") {
                        $value_date_final = $value_year . "-" . $value_month . "-" . $value_day;
                    } elseif ($value_year != "" && $value_month != "") {
                        $value_date_final = $value_year . "-" . $value_month;
                    } elseif ($value_year != "") {
                        $value_date_final = $value_year;
                    }

                    if ($value_date_final != "") {
                        // If search already has value, then attach this value separated by a comma
                        if ($search != "") {
                            $search .= ", ";
                        }
                        $search .= $fields[$n]["name"] . ":" . $value_date_final;
                    }
                }

                if (($date_edtf = getval("field_" . $fields[$n]["ref"] . "_edtf", "")) !== "") {
                    // We have been passed the range in EDTF format, check it is in the correct format
                    $rangeregex = "/^(\d{4})(-\d{2})?(-\d{2})?\/(\d{4})(-\d{2})?(-\d{2})?/";
                    if (!preg_match($rangeregex, $date_edtf, $matches)) {
                        //ignore this string as it is not a valid EDTF string
                        continue 2;
                    }
                    $rangedates = explode("/", $date_edtf);
                    $rangestart = str_pad($rangedates[0], 10, "-00");
                    $rangeendparts = explode("-", $rangedates[1]);
                    $rangeend = $rangeendparts[0] . "-" . (isset($rangeendparts[1]) ? $rangeendparts[1] : "12") . "-" . (isset($rangeendparts[2]) ? $rangeendparts[2] : "99");
                    $datepart = "start" . $rangestart . "end" . $rangeend;
                } else {
                    #Date range search - start date
                    if (getval($name . "_start-y", "") != "") {
                        $datepart .= "start" . getval($name . "_start-y", "");
                        if (getval($name . "_start-m", "") != "") {
                            $datepart .= "-" . getval($name . "_start-m", "");
                            if (getval($name . "_start-d", "") != "") {
                                $datepart .= "-" . getval($name . "_start-d", "");
                            } else {
                                $datepart .= "";
                            }
                        } else {
                            $datepart .= "";
                        }
                    }

                    #Date range search - end date
                    if (getval($name . "_end-y", "") != "") {
                        $datepart .= "end" . getval($name . "_end-y", "");
                        if (getval($name . "_end-m", "") != "") {
                            $datepart .= "-" . getval($name . "_end-m", "");
                            if (getval($name . "_end-d", "") != "") {
                                $datepart .= "-" . getval($name . "_end-d", "");
                            } else {
                                $datepart .= "-31";
                            }
                        } else {
                            $datepart .= "-12-31";
                        }
                    }
                }
                if ($datepart != "") {
                    if ($search != "") {
                        $search .= ", ";
                    }
                    $search .= $fields[$n]["name"] . ":range" . $datepart;
                }

                break;

            case FIELD_TYPE_TEXT_BOX_SINGLE_LINE: # -------- Text boxes
            default:
                $value = getval('field_' . $fields[$n]["ref"], '');
                if ($value != "") {
                    if (
                        isset($resource_field_verbatim_keyword_regex[$fields[$n]["ref"]])
                        && preg_match(
                            $resource_field_verbatim_keyword_regex[$fields[$n]["ref"]],
                            str_replace('*', '', $value)
                        )
                    ) {
                        // Keyword matches verbatim regex, do not split
                        $valueparts = [$value];
                    } else {
                        $valueparts = split_keywords($value, false, false, false, false, true);
                    }
                    foreach ($valueparts as $valuepart) {
                        if ($search != "") {
                            $search .= ", ";
                        }
                        // Move any quotes around whole field:value element so that they are kept together
                        $search .= (strpos($valuepart, "\"") === false) ? ($fields[$n]["name"] . ":" . $valuepart) : ("\"" . $fields[$n]["name"] . ":" . substr($valuepart, 1, -1) . "\"");
                    }
                }
                break;
        }
    }

    ##### NODES #####
    // Fixed lists will be handled separately as we don't care about the field
    // they belong to
    $node_ref = '';

    foreach (getval('nodes_searched', [], false, 'is_array') as $searchedfield => $searched_field_nodes) {
        // Fields that are displayed as a dropdown will only pass one node ID
        if (!is_array($searched_field_nodes) && '' == $searched_field_nodes) {
            continue;
        } elseif (!is_array($searched_field_nodes)) {
            $node_ref .= ', ' . NODE_TOKEN_PREFIX . $searched_field_nodes;
            continue;
        }

        $fieldinfo = get_resource_type_field($searchedfield);

        // For fields that are displayed as checkboxes
        $node_ref .= ', ';

        foreach ($searched_field_nodes as $searched_node_ref) {
            if ($fieldinfo["type"] == FIELD_TYPE_CHECK_BOX_LIST && $checkbox_and) {
                // Split into an additional search element to force a join since this is a separate condition
                $node_ref .= ', ';
            }
            $node_ref .= NODE_TOKEN_PREFIX . $searched_node_ref;
        }
    }

    $search = ('' == $search ? '' : join(', ', split_keywords($search, false, false, false, false, true))) . $node_ref;
    ##### END OF NODES #####

    $propertysearchcodes = array();
    global $advanced_search_properties;

    foreach ($advanced_search_properties as $advanced_search_property => $code) {
        $propval = getval($advanced_search_property, "");
        if ($propval != "") {
            $propertysearchcodes[] = $code . ":" . $propval;
        }
    }

    if (count($propertysearchcodes) > 0) {
        $search = '!properties' . implode(';', $propertysearchcodes) . ' ,' . $search;
    } else {
        // Allow a single special search to be prepended to the search string. For example, !contributions<user id>
        foreach ($_POST as $key => $value) {
            if ($key[0] == '!' && strlen($value) > 0) {
                $search = $key . $value . ',' . $search;
            }
        }
    }
    return $search;
}

/**
 * Refines the search string to eliminate duplicates and ensure proper formatting.
 *
 * This function addresses several issues related to searching, including:
 * - Eliminating duplicate terms from the search query.
 * - Preserving string search functionality when quotes are used.
 * - Formatting date-related keywords correctly.
 * - Adjusting keywords for advanced search fields and ensuring they carry over properly.
 * - Fixing bugs related to search separators and ensuring valid search syntax.
 *
 * @param string $search The original search string to be refined.
 * @return string The refined search string, with duplicates removed and properly formatted.
 */
function refine_searchstring($search)
{
    global $use_refine_searchstring;

    if (!$use_refine_searchstring) {
        return $search;
    }

    if (substr($search, 0, 1) == "\"" && substr($search, -1, 1) == "\"") {
        return $search;
    } // preserve string search functionality.

    global $noadd;
    $search = str_replace(",-", ", -", $search);
    $search = str_replace("\xe2\x80\x8b", "", $search);// remove any zero width spaces.

    $keywords = split_keywords($search, false, false, false, false, true);

    if (preg_match('/^[^\\s]+$/', $search)) {
        // No spaces, keep intact
        $keywords = [$search];
    } else {
        $keywords = split_keywords($search, false, false, false, false, true);
    }

    $orfields = get_OR_fields(); // leave checkbox type fields alone
    $dynamic_keyword_fields = ps_array("SELECT name value FROM resource_type_field where type=9", array(), "schema");

    $fixedkeywords = array();
    foreach ($keywords as $keyword) {
        if (strpos($keyword, "startdate") !== false || strpos($keyword, "enddate") !== false) {
            $keyword = str_replace(" ", "-", $keyword);
        }

        if (strpos($keyword, "!collection") === 0) {
            $collection = intval(substr($search, 11));
            $keyword = "!collection" . $collection;
        }

        if (strpos($keyword, ":") > 0) {
            $keywordar = explode(":", $keyword, 2);
            $keyname = $keywordar[0];
            if (substr($keyname, 0, 1) != "!") {
                if (substr($keywordar[1], 0, 5) == "range") {
                    $keywordar[1] = str_replace(" ", "-", $keywordar[1]);
                }
                if (!in_array($keyname, $orfields)) {
                    $keyvalues = explode(" ", str_replace($keywordar[0] . ":", "", $keywordar[1]));
                } else {
                    $keyvalues = array($keywordar[1]);
                }
                foreach ($keyvalues as $keyvalue) {
                    if (!in_array($keyvalue, $noadd)) {
                        $fixedkeywords[] = $keyname . ":" . $keyvalue;
                    }
                }
            } elseif (!in_array($keyword, $noadd)) {
                $keywords = explode(" ", $keyword);
                $fixedkeywords[] = $keywords[0];
            } // for searches such as !list
        } else {
            if (!in_array($keyword, $noadd)) {
                $fixedkeywords[] = $keyword;
            }
        }
    }
    $keywords = $fixedkeywords;
    $keywords = array_unique($keywords);
    $search = implode(", ", $keywords);
    $search = str_replace(",-", " -", $search); // support the omission search
    return $search;
}

/**
 * Compiles a list of actions based on the provided top actions and search parameters.
 *
 * This function generates an array of options for various actions that can be performed
 * on search results, such as saving searches to collections, saving to dashboards,
 * exporting results, editing resources, and running reports. The available actions depend
 * on user permissions and specific conditions.
 *
 * @param bool $top_actions Indicates whether to include top actions in the options.
 * @return array An array of action options, each containing value, label, data attributes,
 *               category, and order for sorting.
 */
function compile_search_actions($top_actions)
{
    $options = array();
    $o = 0;

    global $baseurl,$baseurl_short, $lang, $k, $search, $restypes, $order_by, $archive, $sort, $daylimit, $home_dash, $url,
           $allow_smart_collections, $resources_count, $show_searchitemsdiskusage, $offset,
           $collection, $usercollection, $internal_share_access, $system_read_only, $search_access;

    if (!isset($internal_share_access)) {
        $internal_share_access = false;
    }

    $urlparams = array(
        "search"        =>  $search,
        "collection"    =>  $collection,
        "restypes"      =>  $restypes,
        "order_by"      =>  $order_by,
        "archive"       =>  $archive,
        "access"        =>  $search_access,
        "sort"          =>  $sort,
        "daylimit"      =>  $daylimit,
        "offset"        =>  $offset,
        "k"             =>  $k
        );

    $omit_edit_all = false;

    #This is to stop duplicate "Edit all resources" caused on a collection search
    if (isset($search) && substr($search, 0, 11) == '!collection') {
        $omit_edit_all = true;
    }

    if (!checkperm('b') && ($k == '' || $internal_share_access)) {
        if ($top_actions && $usercollection != $collection) {
            $options[$o]['value'] = 'save_search_to_collection';
            $options[$o]['label'] = $lang['savethissearchtocollection'];
            $data_attribute['url'] = generateURL($baseurl_short . "pages/collections.php", $urlparams, array("addsearch" => $search));
            $options[$o]['data_attr'] = $data_attribute;
            $options[$o]['category']  = ACTIONGROUP_ADVANCED;
            $options[$o]['order_by']  = 70;
            $o++;
        }

        #Home_dash is on, AND NOT Anonymous use, AND (Dash tile user (NOT with a managed dash) || Dash Tile Admin)
        if ($top_actions && $home_dash && checkPermission_dashcreate()) {
            $option_name = 'save_search_to_dash';
            $extraparams = array();
            $extraparams["create"] = "true";
            $extraparams["tltype"] = "srch";
            $extraparams["freetext"] = "true";

            $data_attribute = array(
                'url'  => generateURL($baseurl_short . "pages/dash_tile.php", $urlparams, $extraparams),
                'link' => str_replace($baseurl, '', (string) $url)
            );

            if (substr($search, 0, 11) == '!collection') {
                $option_name = 'save_collection_to_dash';
                $extraparams["promoted_resource"] = "true";
                $extraparams["all_users"] = "1";
                $extraparams["link"] = $baseurl_short . "pages/search.php?search=!collection" . $collection;
                $data_attribute['url'] = generateURL($baseurl_short . "pages/dash_tile.php", $urlparams, $extraparams);
            }

            $options[$o]['value'] = $option_name;
            $options[$o]['label'] = $lang['savethissearchtodash'];
            $options[$o]['data_attr'] = $data_attribute;
            $options[$o]['category']  = ACTIONGROUP_SHARE;
            $options[$o]['order_by']  = 170;
            $o++;
        }

        // Save search as Smart Collections
        if ($top_actions && $allow_smart_collections && substr($search, 0, 11) != '!collection') {
            $extra_tag_attributes = sprintf(
                '
                    data-url="%spages/collections.php?addsmartcollection=%s&restypes=%s&archive=%s"
                ',
                $baseurl_short,
                urlencode((string) $search),
                urlencode((string) $restypes),
                urlencode((string) $archive)
            );

            $options[$o]['value'] = 'save_search_smart_collection';
            $options[$o]['label'] = $lang['savesearchassmartcollection'];
            $options[$o]['data_attr'] = array();
            $options[$o]['extra_tag_attributes'] = $extra_tag_attributes;
            $options[$o]['category']  = ACTIONGROUP_COLLECTION;
            $options[$o]['order_by']  = 170;
            $o++;
        }

        if ($resources_count != 0 && !$system_read_only) {
                $extra_tag_attributes = sprintf(
                    '
                        data-url="%spages/collections.php?addsearch=%s&restypes=%s&order_by=%s&sort=%s&archive=%s&mode=resources&daylimit=%s"
                    ',
                    $baseurl_short,
                    urlencode((string) $search),
                    urlencode((string) $restypes),
                    urlencode((string) $order_by),
                    urlencode((string) $sort),
                    urlencode((string) $archive),
                    urlencode((string) $daylimit)
                );

                $options[$o]['value'] = 'save_search_items_to_collection';
                $options[$o]['label'] = $lang['savesearchitemstocollection'];
                $options[$o]['data_attr'] = array();
                $options[$o]['extra_tag_attributes'] = $extra_tag_attributes;
                $options[$o]['category']  = ACTIONGROUP_COLLECTION;
                $options[$o]['order_by']  = 170;
                $o++;

            if (0 != $resources_count && $show_searchitemsdiskusage) {
                $extra_tag_attributes = sprintf(
                    '
                        data-url="%spages/search_disk_usage.php?search=%s&restypes=%s&offset=%s&order_by=%s&sort=%s&archive=%s&daylimit=%s&k=%s"
                    ',
                    $baseurl_short,
                    urlencode((string) $search),
                    urlencode((string) $restypes),
                    urlencode((string) $offset),
                    urlencode((string) $order_by),
                    urlencode((string) $sort),
                    urlencode((string) $archive),
                    urlencode((string) $daylimit),
                    urlencode((string) $k)
                );

                $options[$o]['value'] = 'search_items_disk_usage';
                $options[$o]['label'] = $lang['searchitemsdiskusage'];
                $options[$o]['data_attr'] = array();
                $options[$o]['extra_tag_attributes'] = $extra_tag_attributes;
                $options[$o]['category']  = ACTIONGROUP_ADVANCED;
                $options[$o]['order_by']  = 300;
                $o++;
            }
        }
    }

    // If all resources are editable, display an edit all link
    if ($top_actions && !$omit_edit_all) {
        $data_attribute['url'] = generateURL($baseurl_short . "pages/edit.php", $urlparams, array("editsearchresults" => "true", "search_access" => $search_access));
        $options[$o]['value'] = 'editsearchresults';
        $options[$o]['label'] = $lang['edit_all_resources'];
        $options[$o]['data_attr'] = $data_attribute;
        $options[$o]['category'] = ACTIONGROUP_EDIT;
        $options[$o]['order_by']  = 130;
        $o++;
    }

    if ($top_actions && ($k == '' || $internal_share_access)) {
        $options[$o]['value']            = 'csv_export_results_metadata';
        $options[$o]['label']            = $lang['csvExportResultsMetadata'];
        $options[$o]['data_attr']['url'] = sprintf(
            '%spages/csv_export_results_metadata.php?search=%s&restypes=%s&order_by=%s&archive=%s&sort=%s&access=%s',
            $baseurl_short,
            urlencode((string) $search),
            urlencode((string) $restypes),
            urlencode((string) $order_by),
            urlencode((string) $archive),
            urlencode((string) $sort),
            urlencode((string) $search_access)
        );
        $options[$o]['category'] = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 290;
        $o++;
    }

    // Run report on search results
    if ($top_actions && checkperm('t')) {
        $backurl_to_search = generateURL("{$baseurl_short}pages/search.php", get_search_params(), $urlparams);

        $options[$o]['value'] = 'run_report_on_search_results';
        $options[$o]['label'] = $lang['run_report_on_search_results'];
        $options[$o]['data_attr']['url'] = generateURL("{$baseurl_short}pages/team/team_report.php", ['backurl' => $backurl_to_search]);
        $options[$o]['category'] = ACTIONGROUP_ADVANCED;
        $options[$o]['order_by']  = 280;
        $o++;
    }

    // Add extra search actions or modify existing options through plugins
    $modified_options = hook('render_search_actions_add_option', '', array($options, $urlparams));
    if ($top_actions && !empty($modified_options)) {
        $options = $modified_options;
    }

    return $options;
}

/**
 * Constructs a SQL filter based on the provided search parameters.
 *
 * This function generates a prepared statement query that can be used to filter search results
 * based on various criteria, including archive status, resource types, user permissions, and more.
 * The function also takes into account user-specific access rights and other configurations
 * to ensure that the returned resources meet the necessary visibility and editability criteria.
 *
 * @param string $search The search query string.
 * @param mixed $archive Archive states to filter by (can be a comma-separated string).
 * @param string $restypes Resource types to include in the search.
 * @param int $recent_search_daylimit Limit for filtering recent searches by creation date.
 * @param mixed $access_override If set, overrides access restrictions.
 * @param bool $return_disk_usage Indicates whether to include disk usage information.
 * @param bool $editable_only If true, only returns resources that are editable by the user.
 * @param int|null $access The specific access level to filter by (if applicable).
 * @param bool $smartsearch If true, enables smart search features.
 * @return PreparedStatementQuery A prepared statement object containing the SQL query and parameters.
 */
function search_filter($search, $archive, $restypes, $recent_search_daylimit, $access_override, $return_disk_usage, $editable_only = false, $access = null, $smartsearch = false)
{
    debug_function_call("search_filter", func_get_args());

    global $userref,$userpermissions,$resource_created_by_filter,$uploader_view_override,$edit_access_for_contributor,$additional_archive_states,$heightmin,
    $search_all_workflow_states,$collections_omit_archived,$k,$collection_allow_not_approved_share,$archive_standard;

    if (hook("modifyuserpermissions")) {
        $userpermissions = hook("modifyuserpermissions");
    }
    $userpermissions = (isset($userpermissions)) ? $userpermissions : array();

    # Convert the provided search parameters into appropriate SQL, ready for inclusion in the do_search() search query.
    if (!is_array($archive)) {
        $archive = explode(",", $archive);
    }
    $archive = array_filter($archive, function ($state) {
        return (string)(int)$state == (string)$state;
    }); // remove non-numeric values

    $sql_filter = new PreparedStatementQuery();

    # Apply resource types
    if (($restypes != "") && (substr($restypes, 0, 6) != "Global") && substr($search, 0, 11) != '!collection') {
        if ($sql_filter->sql != "") {
            $sql_filter->sql .= " AND ";
        }
        $restypes_x = explode(",", $restypes);
        $sql_filter->sql .= "resource_type IN (" . ps_param_insert(count($restypes_x)) . ")";
        $sql_filter->parameters = array_merge($sql_filter->parameters, ps_param_fill($restypes_x, "i"));
    }

    # Apply day limit
    if ('' != $recent_search_daylimit && is_numeric($recent_search_daylimit)) {
        if ('' != $sql_filter->sql) {
            $sql_filter->sql .= ' AND ';
        }

        $sql_filter->sql .= "creation_date > (curdate() - interval ? DAY)";
        $sql_filter->parameters = array_merge($sql_filter->parameters, ["i",$recent_search_daylimit]);
    }

    # The ability to restrict access by the user that created the resource.
    if (isset($resource_created_by_filter) && count($resource_created_by_filter) > 0) {
        $created_filter = "";
        $created_filter_params = [];
        foreach ($resource_created_by_filter as $filter_user) {
            if ($filter_user == -1) {
                $filter_user = $userref;
            } # '-1' can be used as an alias to the current user. I.e. they can only see their own resources in search results.
            if ($created_filter != "") {
                $created_filter .= " OR ";
            }
            $created_filter .= "created_by = ?";
            $created_filter_params[] = "i";
            $created_filter_params[] = $filter_user;
        }
        if ($created_filter != "") {
            if ($sql_filter->sql != "") {
                $sql_filter->sql .= " AND ";
            }
            $sql_filter->sql .= "(" . $created_filter . ")";
            $sql_filter->parameters = array_merge($sql_filter->parameters, $created_filter_params);
        }
    }

    # append resource type restrictions based on 'T' permission
    # look for all 'T' permissions and append to the SQL filter.
    $rtfilter = array();

    for ($n = 0; $n < count($userpermissions); $n++) {
        if (substr($userpermissions[$n], 0, 1) == "T") {
            $rt = substr($userpermissions[$n], 1);
            if (is_numeric($rt) && !$access_override) {
                $rtfilter[] = $rt;
            }
        }
    }
    if (count($rtfilter) > 0) {
        if ($sql_filter->sql != "") {
            $sql_filter->sql .= " AND ";
        }
        $sql_filter->sql .= "resource_type NOT IN (" . ps_param_insert(count($rtfilter)) . ")";
        $sql_filter->parameters = array_merge($sql_filter->parameters, ps_param_fill($rtfilter, "i"));
    }

    # append "use" access rights, do not show confidential resources unless admin
    if (!checkperm("v") && !$access_override) {
        if ($sql_filter->sql != "") {
            $sql_filter->sql .= " AND ";
        }
        # Check both the resource access, but if confidential is returned, also look at the joined user-specific or group-specific custom access for rows.
        $sql_filter->sql .= "(r.access<>'2' OR (r.access=2 AND ((rca.access IS NOT null AND rca.access<>2) OR (rca2.access IS NOT null AND rca2.access<>2))))";
    }

    # append standard archive searching criteria. Updated Jan 2016 to apply to collections as resources in a pending state that are in a shared collection could bypass approval process
    if (!$access_override) {
        if (substr($search, 0, 11) == "!collection" || substr($search, 0, 5) == "!list" || substr($search, 0, 15) == "!archivepending" || substr($search, 0, 12) == "!userpending") {
            # Resources in a collection or list may be in any archive state
            # Other special searches define the archive state in search_special()
            if (substr($search, 0, 11) == "!collection" && $collections_omit_archived && !checkperm("e2")) {
                $sql_filter->sql .= (($sql_filter->sql != "") ? " AND " : "") . "archive<>2";
            }
        } elseif ($search_all_workflow_states || substr($search, 0, 8) == "!related" || substr($search, 0, 8) == "!hasdata" || strpos($search, "integrityfail") !== false) {
            hook("search_all_workflow_states_filter", "", [$sql_filter]);
        } elseif (count($archive) == 0 || $archive_standard && !$smartsearch) {
            # If no archive specified add in default archive states (set by config options or as set in rse_workflow plugin)
            # Defaults are not used if searching smartsearch collection, actual values will be used instead
            if ($sql_filter->sql != "") {
                $sql_filter->sql .= " AND ";
            }
            $defaultsearchstates = get_default_search_states();
            if (count($defaultsearchstates) == 0) {
                // Make sure we have at least one state - system has been misconfigured
                $defaultsearchstates[] = 0;
            }
            $sql_filter->sql .= "archive IN (" . ps_param_insert(count($defaultsearchstates)) . ")";
            $sql_filter->parameters = array_merge($sql_filter->parameters, ps_param_fill($defaultsearchstates, "i"));
        } else {
            # Append normal filtering - extended as advanced search now allows searching by archive state
            if ($sql_filter->sql != "") {
                $sql_filter->sql .= " AND ";
            }
            $sql_filter->sql .= "archive IN (" . ps_param_insert(count($archive)) . ")";
            $sql_filter->parameters = array_merge($sql_filter->parameters, ps_param_fill($archive, "i"));
        }
        if (!checkperm("v") && !(substr($search, 0, 11) == "!collection" && $k != '' && $collection_allow_not_approved_share)) {
            // Append standard filtering to hide resources in a pending state, whatever the search
            // except when the resource is of a type that the user has ert permission for
            $rtexclusions = "";
            $rtexclusions_params = [];
            for ($n = 0; $n < count($userpermissions); $n++) {
                if (substr($userpermissions[$n], 0, 3) == "ert") {
                    $rt = substr($userpermissions[$n], 3);
                    if (is_int_loose($rt)) {
                        $rtexclusions .= " OR (resource_type = ?)";
                        array_push($rtexclusions_params, "i", $rt);
                    }
                }
            }
            $sql_filter->sql .= " AND (((r.archive<>-2 OR r.created_by = ?) AND (r.archive<>-1 OR r.created_by = ?)) " . $rtexclusions . ")";
            $sql_filter->parameters = array_merge($sql_filter->parameters, ["i",$userref,"i",$userref], $rtexclusions_params);
            unset($rtexclusions);
        }
    }
    # Add code to filter out resoures in archive states that the user does not have access to due to a 'z' permission
    $filterblockstates          = [];
    for ($n = -2; $n <= 3; $n++) {
        if (checkperm("z" . $n) && !$access_override) {
            $filterblockstates[] = $n;
        }
    }

    foreach ($additional_archive_states as $additional_archive_state) {
        if (checkperm("z" . $additional_archive_state)) {
            $filterblockstates[] = $additional_archive_state;
        }
    }
    if (count($filterblockstates) > 0 && !$access_override) {
        if ($uploader_view_override) {
            if ($sql_filter->sql != "") {
                $sql_filter->sql .= " AND ";
            }
            $sql_filter->sql .= "(archive NOT IN (" . ps_param_insert(count($filterblockstates)) . ") OR created_by = ?)";
            $sql_filter->parameters = array_merge($sql_filter->parameters, ps_param_fill($filterblockstates, "i"));
            $sql_filter->parameters[] = "i";
            $sql_filter->parameters[] = $userref;
        } else {
            if ($sql_filter->sql != "") {
                $sql_filter->sql .= " AND ";
            }
            $sql_filter->sql .= "archive NOT IN (" . ps_param_insert(count($filterblockstates)) . ")";
            $sql_filter->parameters = array_merge($sql_filter->parameters, ps_param_fill($filterblockstates, "i"));
        }
    }

    # Append media restrictions
    if ($heightmin != '') {
        if ($sql_filter->sql != "") {
            $sql_filter->sql .= " AND ";
        }
        $sql_filter->sql .= "dim.height>= ? ";
        $sql_filter->parameters[] = "i";
        $sql_filter->parameters[] = $heightmin;
    }

    # append ref filter - never return the batch upload template (negative refs)
    if ($sql_filter->sql != "") {
        $sql_filter->sql .= " AND ";
    }
    $sql_filter->sql .= "r.ref>0";

    // Only users with v perm can search for resources with a specific access
    if (checkperm("v") && !is_null($access) && is_numeric($access)) {
        $sql_filter->sql .= (trim($sql_filter->sql) != "" ? " AND " : "");
        $sql_filter->sql .= "r.access = ?";
        $sql_filter->parameters[] = "i";
        $sql_filter->parameters[] = $access;
    }
    // Append filter if only searching for editable resources
    if ($editable_only) {
        $editable_filter = new PreparedStatementQuery();
        if (!checkperm("v") && !$access_override) {
            // following condition added 2020-03-02 so that resources without an entry in the resource_custom_access table are included in the search results - "OR (rca.access IS NULL AND rca2.access IS NULL)"
            $editable_filter->sql .= "(r.access <> 1 OR (r.access = 1 AND ((rca.access IS NOT null AND rca.access <> 1) OR (rca2.access IS NOT null AND rca2.access <> 1) OR (rca.access IS NULL AND rca2.access IS NULL)))) ";
        }

        # Construct resource type exclusion based on 'ert' permission
        # look for all 'ert' permissions and append to the exclusion array.
        $rtexclusions = array();
        for ($n = 0; $n < count($userpermissions); $n++) {
            if (substr($userpermissions[$n], 0, 3) == "ert") {
                $rt = substr($userpermissions[$n], 3);
                if (is_numeric($rt)) {
                    $rtexclusions[] = $rt;
                }
            }
        }

        $blockeditstates = array();
        for ($n = -2; $n <= 3; $n++) {
            if (!checkperm("e" . $n)) {
                $blockeditstates[] = $n;
            }
        }
        foreach ($additional_archive_states as $additional_archive_state) {
            if (!checkperm("e" . $n)) {
                $blockeditstates[] = $n;
            }
        }
        // Add code to hide resources in archive<0 unless has 't' permission, resource has been contributed by user or has ert permission
        if (!checkperm("t")) {
            if ($editable_filter->sql != "") {
                $editable_filter->sql .= " AND ";
            }
            $editable_filter->sql .= "(archive NOT IN (-2,-1) OR (created_by = ?";
            $editable_filter->parameters = ["i",$userref];
            if (count($rtexclusions) > 0) {
                $editable_filter->sql  .= " OR resource_type IN (" . ps_param_insert(count($rtexclusions)) . ")";
                $editable_filter->parameters = array_merge($editable_filter->parameters, ps_param_fill($rtexclusions, "i"));
            }
            $editable_filter->sql .= "))";
        }

        if (count($blockeditstates) > 0) {
            $blockeditoverride          = "";
            $blockeditoverride_params   = [];
            global $userref;
            if ($edit_access_for_contributor) {
                $blockeditoverride .= " created_by = ?";
                $blockeditoverride_params[] = "i";
                $blockeditoverride_params[] = $userref;
            }
            if (count($rtexclusions) > 0) {
                if ($blockeditoverride != "") {
                    $blockeditoverride .= " AND ";
                }
                $blockeditoverride .= "resource_type IN (" . ps_param_insert(count($rtexclusions)) . ")";
                $blockeditoverride_params = array_merge($blockeditoverride_params, ps_param_fill($rtexclusions, "i"));
            }
            if ($editable_filter->sql != "") {
                $editable_filter->sql .= " AND ";
            }

            $editable_filter->sql .= "(archive NOT IN (" . ps_param_insert(count($blockeditstates)) . ")";
            $editable_filter->parameters = array_merge($editable_filter->parameters, ps_param_fill($blockeditstates, "i"));
            if ($blockeditoverride != "") {
                $editable_filter->sql .= " OR " . $blockeditoverride;
                $editable_filter->parameters = array_merge($editable_filter->parameters, $blockeditoverride_params);
            }
            $editable_filter->sql .= ")";
        }

        // Check for blocked/allowed resource types
        $allrestypes = get_resource_types("", false, false, true);
        $blockedrestypes = array();
        foreach ($allrestypes as $restype) {
            if (checkperm("XE" . $restype["ref"])) {
                $blockedrestypes[] = $restype["ref"];
            }
        }
        if (checkperm("XE")) {
            $okrestypes = array();
            $okrestypesor = "";
            $okrestypesorparams = [];
            foreach ($allrestypes as $restype) {
                if (checkperm("XE-" . $restype["ref"])) {
                    $okrestypes[] = $restype["ref"];
                }
            }
            if (count($okrestypes) > 0) {
                if ($editable_filter->sql != "") {
                    $editable_filter->sql .= " AND ";
                }
                if ($edit_access_for_contributor) {
                    $okrestypesor .= " created_by = ?";
                    $okrestypesorparams = ["i",$userref];
                }
                $editable_filter->sql .= "(resource_type IN (" . ps_param_insert(count($okrestypes)) . ")" . (($okrestypesor != "") ? " OR " . $okrestypesor : "") . ")";
                $editable_filter->parameters = array_merge($editable_filter->parameters, ps_param_fill($okrestypes, "i"), $okrestypesorparams);
            } else {
                if ($editable_filter->sql != "") {
                    $editable_filter->sql .= " AND ";
                }
                $editable_filter->sql .= " 0=1";
            }
        }

        if (count($blockedrestypes) > 0) {
            $blockrestypesor = "";
            $blockrestypesorparams = [];
            if ($edit_access_for_contributor) {
                $blockrestypesor .= " created_by = ?";
                $blockrestypesorparams = ["i",$userref];
            }
            if ($editable_filter->sql != "") {
                $editable_filter->sql .= " AND ";
            }
            $editable_filter->sql .= "(resource_type NOT IN (" . ps_param_insert(count($blockedrestypes)) . ")" . (($blockrestypesor != "") ? " OR " . $blockrestypesor : "") . ")";
            $editable_filter->parameters = array_merge($editable_filter->parameters, ps_param_fill($blockedrestypes, "i"), $blockrestypesorparams);
        }

        $updated_editable_filter = hook("modifysearcheditable", "", array($editable_filter,$userref));
        if ($updated_editable_filter !== false) {
            $editable_filter = $updated_editable_filter;
        }

        if ($editable_filter->sql != "") {
            if ($sql_filter->sql != "") {
                $sql_filter->sql .= " AND ";
            }
            $sql_filter->sql .= $editable_filter->sql;
            $sql_filter->parameters = array_merge($sql_filter->parameters, $editable_filter->parameters);
        }
    }

    return $sql_filter;
}

/**
 * Processes special searches and constructs a corresponding SQL query.
 *
 * This function handles various special search commands (like viewing the last resources,
 * resources with no downloads, duplicates, collections, etc.) and creates a prepared statement
 * for the query that retrieves the desired resources based on the search parameters.
 * It also incorporates user permissions and other configurations into the search logic.
 *
 * @param string $search The search string indicating the type of special search.
 * @param PreparedStatementQuery $sql_join The SQL JOIN query to be applied.
 * @param int $fetchrows The number of rows to fetch.
 * @param string $sql_prefix The prefix for the SQL query.
 * @param string $sql_suffix The suffix for the SQL query.
 * @param string $order_by The order by clause for sorting the results.
 * @param string $orig_order The original order specified by the user.
 * @param PreparedStatementQuery $select The fields to select in the query.
 * @param PreparedStatementQuery $sql_filter The SQL WHERE filter to apply.
 * @param mixed $archive Archive states to filter by.
 * @param bool $return_disk_usage Indicates whether to return disk usage information.
 * @param bool $return_refs_only If true, returns only resource references.
 * @param bool $returnsql If true, returns the constructed SQL query instead of executing it.
 * @return mixed The results of the special search or false if no special search was matched.
 */
function search_special($search, $sql_join, $fetchrows, $sql_prefix, $sql_suffix, $order_by, $orig_order, $select, $sql_filter, $archive, $return_disk_usage, $return_refs_only = false, $returnsql = false)
{
    # Process special searches. These return early with results.
    global $FIXED_LIST_FIELD_TYPES, $lang, $k, $USER_SELECTION_COLLECTION, $date_field;
    global $allow_smart_collections, $smart_collections_async;
    global $config_search_for_number,$userref;

    setup_search_chunks($fetchrows, $chunk_offset, $search_chunk_size);

    // Don't cache special searches by default as often used for special purposes
    // e.g. collection count to determine edit accesss
    $b_cache_count = false;

    if (!is_a($sql_join, "PreparedStatementQuery") && trim($sql_join == "")) {
        $sql_join = new PreparedStatementQuery();
    }
    if (!is_a($sql_filter, "PreparedStatementQuery") && trim($sql_filter == "")) {
        $sql_filter = new PreparedStatementQuery();
    }
    $sql = new PreparedStatementQuery();
    # View Last
    if (substr($search, 0, 5) == "!last") {
        # Replace r2.ref with r.ref for the alternative query used here.

        $order_by = str_replace("r.ref", "r2.ref", $order_by);
        if ($orig_order == "relevance") {
            # Special case for ordering by relevance for this query.
            $direction = ((strpos($order_by, "DESC") === false) ? "ASC" : "DESC");
            $order_by = "r2.ref " . $direction;
        }

        # add date field, if access allowed, for use in $order_by
        if (metadata_field_view_access($date_field) && strpos($select->sql, "field" . $date_field) === false) {
            $select->sql .= ", field{$date_field} ";
        }

        # Extract the number of records to produce
        $last = explode(",", $search);
        $last = str_replace("!last", "", $last[0]);

        # !Last must be followed by an integer. SQL injection filter.
        if (is_int_loose($last)) {
            $last = (int)$last;
        } else {
            $last = 1000;
            $search = "!last1000";
        }

        # Fix the ORDER BY for this query (special case due to inner query)
        $order_by = str_replace("r.rating", "rating", $order_by);

        $sql->sql = $sql_prefix . "SELECT DISTINCT *,r2.total_hit_count score FROM (SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE " . $sql_filter->sql . " ORDER BY ref DESC LIMIT $last ) r2 [ORDER_BY_SQL] " . $sql_suffix;
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 12) == "!nodownloads") {
        // View Resources With No Downloads
        if ($orig_order == "relevance") {
            $order_by = "ref DESC";
        }
        $select->sql = "r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . "  WHERE " . $sql_filter->sql . " AND r.ref NOT IN (SELECT DISTINCT object_ref FROM daily_stat WHERE activity_type='Resource download') [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 11) == "!duplicates") {
        // Duplicate Resources (based on file_checksum)
        // Extract the resource ID
        $ref = explode(" ", $search);
        $ref = str_replace("!duplicates", "", $ref[0]);
        $ref = explode(",", $ref); // just get the number
        $ref = $ref[0];
        if ($ref != "") {
            # Find duplicates of a given resource
            if (is_int_loose($ref)) {
                $sql->sql = sprintf(
                    "SELECT [SELECT_SQL]
                        FROM resource r %s
                        WHERE %s
                            AND file_checksum <> ''
                            AND file_checksum IS NOT NULL
                            AND file_checksum = (
                                SELECT file_checksum
                                    FROM resource
                                    WHERE ref= ?
                                        AND (file_checksum <> '' AND file_checksum IS NOT NULL)
                                )
                        [GROUP_BY_SQL]
                        [ORDER_BY_SQL]",
                    $sql_join->sql,
                    $sql_filter->sql
                );
                $order_by = "file_checksum, ref";
                $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters, ["i",$ref]);
            } else {
                // Given resource is not a valid identifier
                return [];
            }
        } else {
            // Find all duplicate resources
            $order_by = "file_checksum, ref";
            $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE " . $sql_filter->sql . " AND file_checksum IN (SELECT file_checksum FROM (SELECT file_checksum FROM resource WHERE file_checksum <> '' AND file_checksum IS NOT null GROUP BY file_checksum having count(file_checksum)>1)r2) [ORDER_BY_SQL] {$sql_suffix}";
            $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
        }
        $select->sql = "r.hit_count score, {$select->sql}";
    } elseif (substr($search, 0, 11) == '!collection') {
        # View Collection
        global $userref,$ignore_collection_access;

        $colcustperm = $sql_join;

        # Extract the collection number
        $collection = explode(' ', $search);
        $collection = str_replace('!collection', '', $collection[0]);
        $collection = explode(',', $collection); // just get the number
        $collection = (int)$collection[0];

        if (!checkperm('a')) {
            # Check access
            $validcollections = [];
            if (upload_share_active() !== false) {
                $validcollections = get_session_collections(get_rs_session_id(), $userref);
            } else {
                $user_collections = array_column(get_user_collections($userref, "", "name", "ASC", -1, false), "ref");
                $public_collections = array_column(search_public_collections('', 'name', 'ASC', true, false, false), 'ref');
                # include collections of requested resources
                $request_collections = array();
                if (checkperm("R")) {
                    include_once 'request_functions.php';
                    $request_collections = array_column(get_requests(), 'collection');
                    $externally_requested_collections = array_column(ps_query('SELECT ref FROM collection WHERE user = -2'), 'ref');
                    $request_collections = array_merge($request_collections, $externally_requested_collections);
                }
                # include collections of research resources
                $research_collections = array();
                if (checkperm("r")) {
                    include_once 'research_functions.php';
                    $research_collections = array_column(get_research_requests(), 'collection');
                }
                $validcollections = array_unique(array_merge($user_collections, array($USER_SELECTION_COLLECTION), $public_collections, $request_collections, $research_collections));
            }

            // Attach the negated user reference special collection
            $validcollections[] = (0 - $userref);

            if (in_array($collection, $validcollections) || (in_array($collection, array_column(get_all_featured_collections(), 'ref')) && featured_collection_check_access_control($collection)) || $ignore_collection_access) {
                if (!collection_readable($collection)) {
                    return array();
                }
            } elseif ($k == "" || upload_share_active() !== false) {
                return [];
            }
        }

        if ($allow_smart_collections) {
            global $smartsearch_ref_cache;
            if (isset($smartsearch_ref_cache[$collection])) {
                $smartsearch_ref = $smartsearch_ref_cache[$collection]; // this value is pretty much constant
            } else {
                $smartsearch_ref = ps_value('SELECT savedsearch value FROM collection WHERE ref = ?', ['i',$collection], '');
                $smartsearch_ref_cache[$collection] = $smartsearch_ref;
            }

            global $php_path;
            if ($smartsearch_ref != '' && !$return_disk_usage) {
                if ($smart_collections_async && isset($php_path) && file_exists($php_path . '/php')) {
                    exec($php_path . '/php ' . dirname(__FILE__) . '/../pages/ajax/update_smart_collection.php ' . escapeshellarg($smartsearch_ref) . ' ' . '> /dev/null 2>&1 &');
                } else {
                    update_smart_collection($smartsearch_ref);
                }
            }
        }

        $select->sql = "DISTINCT c.date_added,c.comment,r.hit_count score,length(c.comment) commentset, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r JOIN collection_resource c ON r.ref=c.resource " .
        $colcustperm->sql . " WHERE c.collection = ? AND (" . $sql_filter->sql . ") [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";

        $sql->parameters = array_merge($select->parameters, $colcustperm->parameters, ["i",$collection], $sql_filter->parameters);

        $collectionsearchsql = hook('modifycollectionsearchsql', '', array($sql));

        if ($collectionsearchsql) {
            $sql = $collectionsearchsql;
        }
    } elseif (substr($search, 0, 14) == "!relatedpushed") {
        # View Related - Pushed Metadata (for the view page)
        # Extract the resource number
        $resource = explode(" ", $search);
        $resource = str_replace("!relatedpushed", "", $resource[0]);

        if (isset($GLOBALS["related_pushed_order_by"])) {
            if (is_int_loose($GLOBALS["related_pushed_order_by"])) {
                if (metadata_field_view_access($GLOBALS["related_pushed_order_by"])) {
                    $order_by = set_search_order_by($search, "field" . $GLOBALS["related_pushed_order_by"], "ASC");
                }
            } else {
                $order_by = set_search_order_by($search, $GLOBALS["related_pushed_order_by"], "ASC");
            }
        }

        $order_by = str_replace("r.", "", $order_by); # UNION below doesn't like table aliases in the ORDER BY.
        $select->sql = "DISTINCT r.hit_count score, rt.name resource_type_name, {$select->sql}";
        $relatedselect = $sql_prefix . "
                    SELECT [SELECT_SQL]
                      FROM resource r
                      JOIN resource_type rt ON r.resource_type=rt.ref AND rt.push_metadata=1
                      JOIN resource_related t ON (%s) "
                         . $sql_join->sql
                 . " WHERE 1=1 AND " . $sql_filter->sql
              . " [GROUP_BY_SQL]";

        $sql->sql = sprintf($relatedselect, "t.related=r.ref AND t.resource = ?")
                . " UNION "
                . sprintf($relatedselect, "t.resource=r.ref AND t.related= ?")
                . " [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge(
            $select->parameters,
            ["i",$resource],
            $sql_join->parameters,
            $sql_filter->parameters,
            $select->parameters,
            ["i",$resource],
            $sql_join->parameters,
            $sql_filter->parameters
        );
    } elseif (substr($search, 0, 8) == "!related") {
        # View Related
        # Extract the resource number
        $resource = explode(" ", $search);
        $resource = str_replace("!related", "", $resource[0]);
        $order_by = str_replace("r.", "", $order_by); # UNION below doesn't like table aliases in the ORDER BY.

        global $pagename, $related_search_show_self;
        $sql_self = new PreparedStatementQuery();
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        if ($related_search_show_self && ($pagename == 'search' || $pagename == 'collections')) {
            $sql_self->sql = " SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE r.ref = ? AND " . $sql_filter->sql . " GROUP BY r.ref UNION ";
            $sql_self->parameters = array_merge($select->parameters, $sql_join->parameters, ["i",$resource], $sql_filter->parameters);
        }

        $sql->sql = $sql_prefix . $sql_self->sql . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " JOIN resource_related t ON (t.related = r.ref AND t.resource = ?)  WHERE " . $sql_filter->sql . " [GROUP_BY_SQL]
        UNION
        SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql  . " JOIN resource_related t ON (t.resource = r.ref AND t.related = ?) WHERE " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($sql_self->parameters, $select->parameters, $sql_join->parameters, ["i", $resource], $sql_filter->parameters, $select->parameters, $sql_join->parameters, ["i", $resource], $sql_filter->parameters);
    } elseif (substr($search, 0, 4) == "!geo") {
        # Geographic search
        $geo = explode("t", str_replace(array("m","p"), array("-","."), substr($search, 4))); # Specially encoded string to avoid keyword splitting
        if (!isset($geo[0]) || empty($geo[0]) || !isset($geo[1]) || empty($geo[1])) {
            exit($lang["geographicsearchmissing"]);
        }
        $bl = explode("b", $geo[0]);
        $tr = explode("b", $geo[1]);
        $select->sql = "r.hit_count score, {$select->sql}";
        $sql->sql = "SELECT [SELECT_SQL]
                       FROM resource r " . $sql_join->sql .
                     "WHERE geo_lat > ? AND geo_lat < ? " .
                       "AND geo_long > ? AND geo_long < ?
                        AND " . $sql_filter->sql .
                        " [GROUP_BY_SQL] [ORDER_BY_SQL]";

        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, ["d",$bl[0],"d",$tr[0],"d",$bl[1],"d",$tr[1]], $sql_filter->parameters);
        $sql->sql = $sql_prefix . $sql->sql . $sql_suffix;
    } elseif (substr($search, 0, 10) == "!colourkey") {
        # Similar to a colour by key
        # Extract the colour key
        $colourkey = explode(" ", $search);
        $colourkey = str_replace("!colourkey", "", $colourkey[0]);
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql = new PreparedStatementQuery();
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE has_image > 0 AND LEFT(colour_key,4) = ? AND " . $sql_filter->sql . " [GROUP_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, ["s",$colourkey], $sql_filter->parameters);
    } elseif (substr($search, 0, 7) == "!colour") {
        # Colour search
        $colour = explode(" ", $search);
        $colour = str_replace("!colour", "", $colour[0]);
        $select->sql = "r.hit_count score, {$select->sql}";
        $sql = new PreparedStatementQuery();
        $sql->sql = "SELECT [SELECT_SQL]
                       FROM resource r " . $sql_join->sql .
                    " WHERE colour_key LIKE ? " .
                        "OR  colour_key LIKE ? " .
                       "AND " . $sql_filter->sql .
                       " [GROUP_BY_SQL] [ORDER_BY_SQL]";

        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, ["s",$colour . "%","s","_" . $colour . "%"], $sql_filter->parameters);
        $searchsql = $sql_prefix . $sql->sql . $sql_suffix;
        $sql->sql  = $searchsql;
    } elseif (substr($search, 0, 4) == "!rgb") {
        // Similar to a colour
        $rgb = explode(":", $search);
        $rgb = explode(",", $rgb[1]);
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $searchsql = new PreparedStatementQuery();
        $searchsql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE has_image > 0 AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL]" . $sql_suffix;
        $order_by = "(abs(image_red - ?)+abs(image_green - ?)+abs(image_blue - ?)) ASC";
        $order_by_params =  ["i",$rgb[0],"i",$rgb[1],"i",$rgb[2]];
        $hardlimit = 500;
        $searchsql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
        $sql = $searchsql;
    } elseif (substr($search, 0, 10) == "!nopreview") {
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql = new PreparedStatementQuery();
        $sql->sql = $sql_prefix .
            "SELECT [SELECT_SQL]
                FROM resource r
                $sql_join->sql
                WHERE has_image=0
                  AND {$sql_filter->sql}
                [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (($config_search_for_number && is_numeric($search)) || substr($search, 0, 9) == "!resource") {
        $searchref = preg_replace("/[^0-9]/", "", $search);
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE r.ref = ? AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL]" . $sql_suffix;
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, ["i",$searchref], $sql_filter->parameters);
    } elseif (substr($search, 0, 15) == "!archivepending") {
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE r.archive=1 AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 12) == "!userpending") {
        if ($orig_order == "rating") {
            $order_by = "request_count DESC," . $order_by;
        }
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE r.archive=-1
        AND {$sql_filter->sql} [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 14) == "!contributions") {
        global $userref;

        # Extract the user ref
        $cuser = explode(" ", $search);
        $cuser = str_replace("!contributions", "", $cuser[0]);

        // Don't filter if user is searching for their own resources and $open_access_for_contributor=true;
        global $open_access_for_contributor;
        if ($open_access_for_contributor && $userref == $cuser) {
            $sql_filter->sql = "archive IN (" . ps_param_insert(count($archive)) . ")";
            $sql_filter->parameters = ps_param_fill($archive, "i");
            $sql_join->sql = " JOIN resource_type AS rty ON r.resource_type = rty.ref ";
            $sql_join->parameters = array();
            // Remove reference to custom access
            $select->sql = str_replace(["rca.access", "rca2.access"], "0", $select->sql);
        }

        $select->sql = "DISTINCT r.hit_count score, " . str_replace(",rca.access group_access,rca2.access user_access ", ",null group_access, null user_access ", $select->sql);
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE created_by = ? AND r.ref > 0 AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, ["i", $cuser], $sql_filter->parameters);
    } elseif ($search == "!images") {
        // Search for resources with images
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE has_image>0 AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 7) == "!unused") {
        // Search for resources not used in any collections
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix;
        $sql->sql .= sprintf(
            "SELECT [SELECT_SQL]
                FROM resource r %s
                WHERE r.ref>0
                    AND r.ref NOT IN (SELECT c.resource FROM collection_resource c)
                    AND %s
                [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}",
            $sql_join->sql,
            $sql_filter->sql
        );
        $sql->sql .= $sql_suffix;
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 5) == "!list") {
        // Search for a list of resources
        // !listall = archive state is not applied as a filter to the list of resources.
        $resources = explode(" ", $search);
        if (substr($search, 0, 8) == "!listall") {
            $resources = str_replace("!listall", "", $resources[0]);
        } else {
            $resources = str_replace("!list", "", $resources[0]);
        }
        $resources = explode(",", $resources); // Separate out any additional keywords
        $resources = array_filter(explode(":", $resources[0]), "is_int_loose");
        $listsql = new PreparedStatementQuery();
        if (count($resources) == 0) {
            $listsql->sql = " WHERE r.ref IS NULL";
            $listsql->parameters = [];
        } else {
            $listsql->sql = " WHERE r.ref IN (" . ps_param_insert(count($resources)) . ")";
            $listsql->parameters = ps_param_fill($resources, "i");
        }

        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix;
        $sql->sql .= sprintf(
            "SELECT [SELECT_SQL]
                FROM resource r %s%s
                    AND %s
                [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}",
            $sql_join->sql,
            $listsql->sql,
            $sql_filter->sql,
        );
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $listsql->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 8) == "!hasdata") {
        // View resources that have data in the specified field reference - useful if deleting unused fields
        $fieldref = intval(trim(substr($search, 8)));
        $sql_join->sql .= " RIGHT JOIN resource_node rn ON r.ref=rn.resource JOIN node n ON n.ref=rn.node WHERE n.resource_type_field = ?";
        array_push($sql_join->parameters, "i", $fieldref);

        // Cache this as it is a very slow query
        $b_cache_count = true;

        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (substr($search, 0, 11) == "!properties") {
        // Search for resource properties
        // Note: in order to combine special searches with normal searches, these are separated by space (" ")
        $searches_array = explode(' ', $search);
        $properties     = explode(';', substr($searches_array[0], 11));

        // Use a new variable to ensure nothing changes $sql_filter unless this is a valid property search
        $propertiessql = new PreparedStatementQuery();
        foreach ($properties as $property) {
            $propertycheck = explode(":", $property);
            if (count($propertycheck) == 2) {
                $propertyname   = $propertycheck[0];
                $propertyval    = $propertycheck[1];

                $sql_filter_properties_and = $propertiessql->sql != "" ? " AND "  : "";
                switch ($propertyname) {
                    case "hmin":
                        $propertiessql->sql .= $sql_filter_properties_and . " rdim.height >= ?";
                        array_push($propertiessql->parameters, "i", $propertyval);
                        break;
                    case "hmax":
                        $propertiessql->sql .= $sql_filter_properties_and . " rdim.height <= ?";
                        array_push($propertiessql->parameters, "i", $propertyval);
                        break;
                    case "wmin":
                        $propertiessql->sql .= $sql_filter_properties_and . " rdim.width >= ?";
                        array_push($propertiessql->parameters, "i", $propertyval);
                        break;
                    case "wmax":
                        $propertiessql->sql .= $sql_filter_properties_and . " rdim.width <= ?";
                        array_push($propertiessql->parameters, "i", $propertyval);
                        break;
                    case "fmin":
                        // Need to convert MB value to bytes
                        $propertiessql->sql .= $sql_filter_properties_and . " r.file_size >= ?";
                        array_push($propertiessql->parameters, "i", floatval($propertyval) * 1024 * 1024);
                        break;
                    case "fmax":
                        // Need to convert MB value to bytes
                        $propertiessql->sql .= $sql_filter_properties_and . " COALESCE(r.file_size, 0) <= ?";
                        array_push($propertiessql->parameters, "i", floatval($propertyval) * 1024 * 1024);
                        break;
                    case "fext":
                        $propertyval = str_replace("*", "%", $propertyval);
                        $propertiessql->sql .= $sql_filter_properties_and . " r.file_extension ";
                        if (substr($propertyval, 0, 1) == "-") {
                            $propertyval = substr($propertyval, 1);
                            $propertiessql->sql .= " NOT ";
                        }
                        if (substr($propertyval, 0, 1) == ".") {
                            $propertyval = substr($propertyval, 1);
                        }
                        $propertiessql->sql .= " LIKE ?";
                        array_push($propertiessql->parameters, "s", $propertyval);
                        break;
                    case "pi":
                        $propertiessql->sql .= $sql_filter_properties_and . " r.has_image = ?";
                        array_push($propertiessql->parameters, "i", $propertyval);
                        break;
                    case "cu":
                        $propertiessql->sql .= $sql_filter_properties_and . " r.created_by = ?";
                        array_push($propertiessql->parameters, "i", $propertyval);
                        break;

                    case "orientation":
                        $orientation_filters = array(
                            "portrait"  => "COALESCE(rdim.height, 0) > COALESCE(rdim.width, 0)",
                            "landscape" => "COALESCE(rdim.height, 0) < COALESCE(rdim.width, 0)",
                            "square"    => "COALESCE(rdim.height, 0) = COALESCE(rdim.width, 0)",
                        );

                        if (!in_array($propertyval, array_keys($orientation_filters))) {
                            break;
                        }
                        $propertiessql->sql .= $sql_filter_properties_and .  $orientation_filters[$propertyval];
                        break;
                }
            }
        }
        if ($propertiessql->sql != "") {
            if (strpos($sql_join->sql, "LEFT JOIN resource_dimensions rdim on r.ref=rdim.resource") === false) {
                $sql_join->sql .= " LEFT JOIN resource_dimensions rdim on r.ref=rdim.resource";
            }
            if ($sql_filter->sql == "") {
                $sql_filter->sql .= " WHERE " . $propertiessql->sql;
            } else {
                $sql_filter-> sql .= " AND " . $propertiessql->sql;
            }
            $sql_filter->parameters = array_merge($sql_filter->parameters, $propertiessql->parameters);
            $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
        }

        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE r.ref > 0 AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif ($search == "!integrityfail") {
        // Search for resources where the file integrity has been marked as problematic or the file is missing
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE integrity_fail=1 AND no_file=0 AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif ($search == "!locked") {
        // Search for locked resources
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE lock_user<>0 AND " . $sql_filter->sql . " [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    } elseif (checkperm('a') && $search == "!noningested") {
        // System admins only - search for resources that have not been ingested - unfiltered
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource r " . $sql_join->sql . " WHERE file_path IS NOT NULL AND file_path <> '' [GROUP_BY_SQL] [ORDER_BY_SQL] {$sql_suffix}";
        $sql->parameters = array_merge($select->parameters, $sql_join->parameters);
    } elseif (preg_match('/^!report(\d+)(p[-1\d]+)?(d\d+)?(fy\d{4})?(fm\d{2})?(fd\d{2})?(ty\d{4})?(tm\d{2})?(td\d{2})?/i', $search, $report_search_data)) {
        /*
        View report as search results.

        Special search "!report" can contain extra info for the reports' query period.

        Syntax: !reportID[p?][d??][fy????][fm??][fd??][ty????][tm??][td??]
        Where:
        - ID  is the actual report ref (mandatory)
        - p   is the selected period (see $reporting_periods_default config option)
        - d   is the period in specific number of days (p=0 in this case)
        - fy,fm,fd (and their counter parts: ty,tm,td) represent a full date range (p=-1 in this case)

        Examples for viewing as search results report #18:
         - Last 7 days: !report18p7
         - Last 23 days: !report18p0d23
         - Between 2000-01-06 & 2023-03-16: !report18p-1fy2000fm01fd06ty2023tm03td16
        */
        debug('[search_special] Running a "!report" search...');
        $select->sql = "DISTINCT r.hit_count score, {$select->sql}";
        $no_results_sql = new PreparedStatementQuery(
            $sql_prefix . "SELECT [SELECT_SQL] FROM resource AS r "
            . $sql_join->sql
            . ' WHERE 1 = 2 AND ' . $sql_filter->sql
            . ' [GROUP_BY_SQL] [ORDER_BY_SQL] ' . $sql_suffix,
            array_merge($sql_join->parameters, $sql_filter->parameters)
        );

        // Users with no access control to reports get no results back (ie []).
        if (!checkperm('t')) {
            debug(sprintf('[WARNING][search_special][access control] User #%s attempted to run "%s" search without the right permissions', (int) $userref, $search));
            $sql = $no_results_sql;
        } else {
            include_once 'reporting_functions.php';
            $report_id = $report_search_data[1];
            $all_reports = get_reports();
            $reports_w_thumbnail = array_filter(array_column($all_reports, 'query', 'ref'), 'report_has_thumbnail');
            $reports_w_support_non_correlated_sql = array_filter(array_column($all_reports, 'support_non_correlated_sql', 'ref'));
            $reports = array_diff_key($reports_w_thumbnail, $reports_w_support_non_correlated_sql);
            if (isset($reports[$report_id])) {
                $report = $reports[$report_id];

                $report_period = [];
                $report_period_info_idxs = range(2, 9);
                $report_period_info_names = array_combine($report_period_info_idxs, ['period', 'period_days', 'from-y', 'from-m', 'from-d', 'to-y', 'to-m', 'to-d']);
                $report_period_info_lookups = array_combine($report_period_info_idxs, ['p', 'd', 'fy', 'fm', 'fd', 'ty', 'tm', 'td']);
                foreach ($report_period_info_names as $idx => $info_name) {
                    if (!isset($report_search_data[$idx])) {
                        continue;
                    }

                    $report_period[$info_name] = str_replace($report_period_info_lookups[$idx], '', $report_search_data[$idx]);
                }

                $period = report_process_period($report_period);

                $report_sql = report_process_query_placeholders($report, [
                    '[from-y]' => $period['from_year'],
                    '[from-m]' => $period['from_month'],
                    '[from-d]' => $period['from_day'],
                    '[to-y]' => $period['to_year'],
                    '[to-m]' => $period['to_month'],
                    '[to-d]' => $period['to_day'],
                ]);
                $report_sql = preg_replace('/;\s?/m', '', $report_sql, 1);

                $sql->sql = $sql_prefix . "SELECT [SELECT_SQL] FROM resource AS r"
                    . " INNER JOIN ($report_sql) AS rsr ON rsr.thumbnail = r.ref "
                    . $sql_join->sql
                    . ' WHERE ' . $sql_filter->sql . ' [GROUP_BY_SQL] [ORDER_BY_SQL] ' . $sql_suffix;
                $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
                debug("[search_special] SQL = " . json_encode($sql));
            } else {
                debug("[search_special] Report #{$report_id} not found");
                $sql = $no_results_sql;
            }
        }
    }

    # Within this hook implementation, set the value of the global $sql variable:
    # Since there will only be one special search executed at a time, only one of the
    # hook implementations will set the value. So, you know that the value set
    # will always be the correct one (unless two plugins use the same !<type> value).
    $hooksql = hook("addspecialsearch", "", array($search, $select, $sql_join , $sql_filter, $sql));
    if (is_a($hooksql, 'PreparedStatementQuery')) {
        debug("Addspecialsearch hook returned useful results.");
        $hooksql->sql = $sql_prefix . $hooksql->sql . $sql_suffix;
        $sql = $hooksql;
    }

    if ($sql->sql != "") {
        // Construct a reduced query for refs only searches, disk usage or count only searches
        $removecolumns = ["c.date_added",
            "c.comment",
            "length\\(c\\.comment\\) commentset",
            "r.has_image",
            "r.is_transcoding",
            "r.creation_date",
            "r.user_rating_count",
            "r.user_rating_total",
            "r.user_rating",
            "r.rating",
            "r.file_extension",
            "r.preview_extension",
            "r.image_red",
            "r.image_green",
            "r.image_blue",
            "r.thumb_width",
            "r.thumb_height",
            "r.colour_key",
            "r.created_by",
            "r.file_modified",
            "r.file_checksum",
            "r.request_count",
            "r.new_hit_count",
            "r.expiry_notification_sent",
            "r.preview_tweaks",
            "r.file_path",
            "r.modified",
            "r.file_size",
            "rty.order_by",
            "c.date_added",
            "c.comment",
        ];

        $reducedselect = $select->sql;

        // Only reduce columns if an sql prefix is not set, this is to ensure compatibility with any encapsulating query that might be present. I.e. disk usage
        if (trim((string) $sql_prefix) == "") {
            foreach ($removecolumns as $removecolumn) {
                $reducedselect = preg_replace("/(,\s?" . $removecolumn . ")/", "", $reducedselect);
            }
            $reducedselect = preg_replace("/(,\s?r\\.field\\d+)/", "", $reducedselect); // remove any fieldXX columns from select
        }

        $reduced_sql = clone $sql;
        $reduced_sql->sql = str_replace(
            ["[SELECT_SQL]", "[GROUP_BY_SQL]", "[ORDER_BY_SQL]"],
            [$reducedselect, "GROUP BY r.ref", ""],
            $reduced_sql->sql
        );
        if (isset($hardlimit)) {
            $reduced_sql->sql .= " LIMIT $hardlimit";
        }

        if ($return_refs_only) {
            $sql = $reduced_sql;
        } else {
            $sql->sql = str_replace(
                ["[SELECT_SQL]", "[GROUP_BY_SQL]", "[ORDER_BY_SQL]"],
                [$select->sql, "GROUP BY r.ref", "ORDER BY {$order_by}"],
                $sql->sql
            );
            if (isset($order_by_params)) {
                // Only used by $rgb search
                $sql->parameters = array_merge($sql->parameters, $order_by_params);
                if (isset($hardlimit) && $chunk_offset > $hardlimit - $search_chunk_size) {
                    $chunk_offset = $hardlimit - $search_chunk_size;
                }
            }
        }

        if ($returnsql) {
            return $sql;
        } else {
            $result = sql_limit_with_total_count($sql, $search_chunk_size, $chunk_offset, $b_cache_count, $reduced_sql);
            if (is_array($fetchrows)) {
                return $result;
            }

            $resultcount = $result["total"]  ?? 0;
            if ($resultcount > 0 && count($result["data"]) > 0) {
                $return = $result['data'];
                $resultcount -= count($return);
                while ($resultcount > 0) {
                    $return = array_merge($return, array_pad([], ($resultcount > 1000000 ? 1000000 : $resultcount), 0));
                    $resultcount -= 1000000;
                }
            } else {
                $return = [];
            }
        }
        hook('beforereturnresults', '', array($result, $archive));
        return $return;
    }

     # Arrived here? There were no special searches. Return false.
     return false;
}

/**
* Function used to create a list of nodes found in a search string
*
* IMPORTANT: use resolve_given_nodes() if you need to detect nodes based on
* search string format (ie. @@253@@255 and/ or @@!260)
*
* @param string $string
*
* @return array
*/
function resolve_nodes_from_string($string)
{
    if (!is_string($string)) {
        return array();
    }

    $node_bucket     = array();
    $node_bucket_not = array();
    $return          = array();

    resolve_given_nodes($string, $node_bucket, $node_bucket_not);

    foreach ($node_bucket as $nodes) {
        foreach ($nodes as $node) {
            $return[] = $node;
        }
    }

    foreach ($node_bucket_not as $node_not) {
        $return[] = "-" . $node_not;
    }

    return $return;
}

/**
* Utility function which helps rebuilding a specific field search string
* from a node element
*
* @param array $node A node element as returned by get_node() or get_nodes()
*
* @return string
*/
function rebuild_specific_field_search_from_node(array $node)
{
    if (0 == count($node)) {
        return '';
    }

    $field_shortname = ps_value("SELECT name AS `value` FROM resource_type_field WHERE ref = ?", array("i",$node['resource_type_field']), "field{$node['resource_type_field']}", "schema");

    // Note: at the moment there is no need to return a specific field search by multiple options
    // Example: country:keyword1;keyword2
    return (strpos($node['name'], " ") === false) ? $field_shortname . ":" . i18n_get_translated($node['name']) : "\"" . $field_shortname . ":" . i18n_get_translated($node['name']) . "\"";
}

function search_get_previews($search, $restypes = "", $order_by = "relevance", $archive = 0, $fetchrows = -1, $sort = "DESC", $access_override = false, $ignore_filters = false, $return_disk_usage = false, $recent_search_daylimit = "", $go = false, $stats_logging = true, $return_refs_only = false, $editable_only = false, $returnsql = false, $getsizes = array(), $previewextension = "jpg")
{
    global $access;

    $structured = false;
    if (is_array($fetchrows)) {
        $structured = true;
    } elseif (!is_array($fetchrows) && strpos((string)$fetchrows, ",") !== false) {
        $fetchrows = explode(",", $fetchrows);
        if (count($fetchrows) == 2) {
            $structured = true;
        } else {
            $fetchrows = 0;
        }
    }

    if ($structured) {
        array_map(function ($val) {
            return $val > 0 ? $val : 0;
        }, $fetchrows);
    }

    # Note the subset of the available parameters. We definitely don't want to allow override of permissions or filters.
    $results = do_search($search, $restypes, $order_by, $archive, $fetchrows, $sort, $access_override, DEPRECATED_STARSEARCH, $ignore_filters, $return_disk_usage, $recent_search_daylimit, $go, $stats_logging, $return_refs_only, $editable_only, $returnsql);
    if (is_string($getsizes)) {
        $getsizes = explode(",", $getsizes);
    }
    $getsizes = array_map('trim', $getsizes);

    if (!is_array($results)) {
        return $results;
    }

    $total = $results["total"] ?? count($results);
    $resultset = $results["data"] ?? $results;
    $use_watermark = check_use_watermark();

    if (is_array($resultset) && is_array($getsizes) && count($getsizes) > 0) {
        $available = get_all_image_sizes(true, ($access == 1));
        for ($n = 0; $n < $total; $n++) {
            // if using fetchrows some results may just be == 0 - remove from results array
            if (!isset($resultset[$n]) || $resultset[$n] == 0) {
                continue;
            }

            $access = $resultset[$n]["resultant_access"] ?? get_resource_access($resultset[$n]);

            if ($access == 2) {
                // No images for confidential resources
                continue;
            }
            foreach ($getsizes as $getsize) {
                if (!(in_array($getsize, array_column($available, "id")))) {
                    continue;
                }
                if (
                    !resource_has_access_denied_by_RT_size($resultset[$n]['resource_type'], $getsize)
                    && file_exists(get_resource_path($resultset[$n]["ref"], true, $getsize, false, $previewextension, -1, 1, $use_watermark))
                ) {
                    $resultset[$n]["url_" . $getsize] = get_resource_path($resultset[$n]["ref"], false, $getsize, false, $previewextension, -1, 1, $use_watermark);
                }
            }
        }
    }
    return $structured ? ["total" => $total, "data" => $resultset] : $resultset;
}

function get_upload_here_selected_nodes($search, array $nodes)
{
    $upload_here_nodes = resolve_nodes_from_string($search);
    if (empty($upload_here_nodes)) {
        return $nodes;
    }

    return array_merge($nodes, $upload_here_nodes);
}

/**
* get the default archive states to search
*
* @return array
*/
function get_default_search_states()
{
    global $searchstates;

    $defaultsearchstates = isset($searchstates) ? $searchstates : array(0); // May be set by rse_workflow plugin
    $modifiedstates = hook("modify_default_search_states", "", array($defaultsearchstates));
    if (is_array($modifiedstates)) {
        return $modifiedstates;
    }
    return $defaultsearchstates;
}

/**
* Get the required search filter sql for the given filter for use in do_search()
*
* @return PreparedStatementQuery
*/
function get_filter_sql($filterid)
{
    global $userref, $access_override, $custom_access_overrides_search_filter, $open_access_for_contributor;

    $filter         = get_filter($filterid);
    if (!$filter) {
        return false;
    }
    $filterrules    = get_filter_rules($filterid);

    $modfilterrules = hook("modifysearchfilterrules");
    if ($modfilterrules) {
        $filterrules = $modfilterrules;
    }

    $filtercondition = $filter["filter_condition"];
    $filters = array();
    $filter_ors = array(); // Allow filters to be overridden in certain cases
    $filter_ors_params = array();
    foreach ($filterrules as $filterrule) {
        $filtersql = new PreparedStatementQuery();
        if (count($filterrule["nodes_on"]) > 0) {
            $filtersql->sql .= "r.ref " . ($filtercondition == RS_FILTER_NONE ? " NOT " : "") . " IN (SELECT rn.resource FROM resource_node rn WHERE rn.node IN (" . ps_param_insert(count($filterrule["nodes_on"])) . ")) ";
            $filtersql->parameters = array_merge($filtersql->parameters, ps_param_fill($filterrule["nodes_on"], "i"));
        }

        if (count($filterrule["nodes_off"]) > 0) {
            if ($filtersql->sql != "") {
                $filtersql->sql .= " OR ";
            }
            $filtersql->sql .= "r.ref " . ($filtercondition == RS_FILTER_NONE ? "" : " NOT") . " IN (SELECT rn.resource FROM resource_node rn WHERE rn.node IN (" . ps_param_insert(count($filterrule["nodes_off"])) . ")) ";
            $filtersql->parameters = array_merge($filtersql->parameters, ps_param_fill($filterrule["nodes_off"], "i"));
        }
        $filters[] = $filtersql;
    }

    if (count($filters) > 0) {
        if ($filtercondition == RS_FILTER_ALL || $filtercondition == RS_FILTER_NONE) {
            $glue = " AND ";
        } else {
            // This is an OR filter
            $glue = " OR ";
        }

        $filter_add =  new PreparedStatementQuery();
        // Bracket the filters to ensure that there is no hanging OR to create an unintentional disjunct
        $filter_add->sql = "(" . implode($glue, array_column($filters, "sql")) . ")";
        foreach ($filters as $filter) {
            $filter_add->parameters = array_merge($filter_add->parameters, $filter->parameters);
        }

        # If custom access has been granted for the user or group, nullify the search filter, effectively selecting "true".
        if (!$access_override && $custom_access_overrides_search_filter) {
            $filter_ors[] = "(rca.access IS NOT null AND rca.access<>2) OR (rca2.access IS NOT null AND rca2.access<>2)";
        }

        if ($open_access_for_contributor) {
            $filter_ors[] = "(r.created_by = ?)";
            array_push($filter_ors_params, "i", $userref);
        }

        if (count($filter_ors) > 0) {
            $filter_add->sql = "((" . $filter_add->sql . ") OR (" . implode(") OR (", $filter_ors) . "))";
            $filter_add->parameters = array_merge($filter_add->parameters, $filter_ors_params);
        }

        return $filter_add;
    }
    return false;
}

function split_keywords($search, $index = false, $partial_index = false, $is_date = false, $is_html = false, $keepquotes = false, bool $preserve_separators = false)
{
    # Takes $search and returns an array of individual keywords.
    global $permitted_html_tags, $permitted_html_attributes;

    if ($index && $is_date) {
        # Date handling... index a little differently to support various levels of date matching (Year, Year+Month, Year+Month+Day).
        $s = explode("-", $search);
        if (count($s) >= 3) {
            return array($s[0],$s[0] . "-" . $s[1],$search);
        } elseif (is_array($search)) {
            return $search;
        } else {
            return array($search);
        }
    }

    # Remove any real / unescaped lf/cr
    $search = str_replace("\r", " ", $search);
    $search = str_replace("\n", " ", $search);
    $search = str_replace("\\r", " ", $search);
    $search = str_replace("\\n", " ", $search);

    if ($is_html || (substr($search, 0, 1) == "<" && substr($search, -1, 1) == ">")) {
        // String can't be in encoded format at this point or string won't be indexed correctly.
        $search = html_entity_decode($search);
        if ($index) {
            // Clean up html for indexing
            // Allow indexing of anchor text
            $allowed_tags = array_merge(array("a"), $permitted_html_tags);
            $allowed_attributes = array_merge(array("href"), $permitted_html_attributes);
            $search = strip_tags_and_attributes($search, $allowed_tags, $allowed_attributes);

            // Get rid of the actual html tags and attribute ids to prevent indexing these
            foreach ($allowed_tags as $allowed_tag) {
                $search = str_replace(array("<" . $allowed_tag . ">","<" . $allowed_tag,"</" . $allowed_tag), " ", $search);
            }
            foreach ($allowed_attributes as $allowed_attribute) {
                $search = str_replace($allowed_attribute . "=", " ", $search);
            }
            // Remove any left over tag parts
            $search = str_replace(array(">", "<","="), " ", $search);
        }
    }

    $ns = trim_spaces($search);

    if (!$index && strpos($ns, ":") !== false) { # special 'constructed' query type
        if ($keepquotes) {
            preg_match_all('/("|-")(?:\\\\.|[^\\\\"])*"|\S+/', $ns, $matches);
            $return = trim_array($matches[0], ",");
        } elseif (strpos($ns, "startdate") !== false || strpos($ns, "enddate") !== false) {
            $return = explode(",", $ns);
        } else {
            $ns = cleanse_string($ns, $preserve_separators, !$index);
            $return = explode(" ", $ns);
        }
        // If we are not breaking quotes we may end up a with commas in the array of keywords which need to be removed
        if ($keepquotes) {
            $return = trim_array($return, ",");
        }
        return $return;
    } else {
        # split using spaces and similar chars (according to configured whitespace characters)
        if (!$index && $keepquotes && strpos($ns, "\"") !== false) {
            preg_match_all('/("|-")(?:\\\\.|[^\\\\"])*"|\S+/', $ns, $matches);

            $splits = $matches[0];
            $ns = array();
            foreach ($splits as $split) {
                if (!(substr($split, 0, 1) == "\"" && substr($split, -1, 1) == "\"") && strpos($split, ",") !== false) {
                    $split = explode(",", $split);
                    $ns = array_merge($ns, $split);
                } else {
                    $ns[] = $split;
                }
            }
        } else {
            # split using spaces and similar chars (according to configured whitespace characters)
            $ns = explode(" ", cleanse_string($ns, false, !$index));
        }

        if ($keepquotes) {
            $ns = trim_array($ns, ",");
        }

        if ($index && $partial_index) {
            return add_partial_index($ns);
        }
        return $ns;
    }
}

/**
 * Removes characters from a string, for example full stops, prior to keyword splitting - {@see split_keywords()}.
 * Also makes the string lower case ready for indexing.
 *
 * @param string $string Text value which needs to be pre-processed
 * @param bool $preserve_separators Set to false to separate keywords when specifying multiple field/keyword pairs
 * (comma and colon)
 * @param bool $for_search Set to true if you need certain characters to be preserved during a search (e.g. the wildcard
 * or minus for NOT searches).
 */
function cleanse_string($string, $preserve_separators, $for_search = false): string
{
    global $config_separators;

    // Replace some HTML entities with empty space
    // Most of them should already be in $config_separators
    // but others, like &shy; don't have an actual character that we can copy and paste
    // to $config_separators
    $string = htmlentities($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $string = str_replace('&nbsp;', ' ', $string);
    $string = str_replace('&shy;', ' ', $string);
    $string = str_replace('&lsquo;', ' ', $string);
    $string = str_replace('&rsquo;', ' ', $string);
    $string = str_replace('&ldquo;', ' ', $string);
    $string = str_replace('&rdquo;', ' ', $string);
    $string = str_replace('&ndash;', ' ', $string);

    // Revert the htmlentities as otherwise we lose ability to identify certain text e.g. diacritics
    $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

    $unicode_allowlist = [];

    if ($for_search) {
        $unicode_allowlist[] = '*'; # wildcard

        // Preserve hyphen so we know which keywords to omit from the search (for a NOT search)
        if ((substr($string, 0, 1) == "-" || strpos($string, " -") !== false) && strpos($string, " - ") === false) {
            $unicode_allowlist[] = '-';
        }

        // Preserve the exclamation mark when doing a special search
        if (substr($string, 0, 1) == "!" && strpos(substr($string, 1), "!") === false) {
            $unicode_allowlist[] = '!';
        }
    }

    $separators = array_diff($config_separators, $unicode_allowlist);

    if (!$preserve_separators) {
        # Also strip out the separators used when specifying multiple field/keyword pairs (comma and colon)
        $separators[] = ",";
        $separators[] = ":";
    }

    return mb_strtolower(
        allow_unicode_characters(str_replace($separators, ' ', $string), $unicode_allowlist),
        'UTF-8'
    );
}

/**
 * Resolve keyword
 *
 * @param string $keyword   The keyword to resolve
 * @param bool   $create    If keyword not found, should we create it instead?
 * @param bool   $normalize Should we normalize the keyword before resolving?
 * @param bool   $stem      Should we use the keywords' stem when resolving?
 *
 * @return int|bool Returns the keyword reference for $keyword, or false if no such keyword exists.
 */
function resolve_keyword($keyword, $create = false, $normalize = true, $stem = true)
{
    debug_function_call("resolve_keyword", func_get_args());

    // Create a cache to ensure we find new nodes subsequently if in a transaction
    global $resolve_keyword_cache;
    global $quoted_string, $stemming;
    $kwhash = md5($keyword) . md5("!" . $keyword . ($normalize ? "NORM" : "") . ($stem ? "STEM" : ""));
    if (isset($resolve_keyword_cache[$kwhash])) {
        return $resolve_keyword_cache[$kwhash];
    }
    $keyword = mb_strcut($keyword, 0, 100); # Trim keywords to 100 chars for indexing, as this is the length of the keywords column.

    if (!$quoted_string && $normalize) {
        $keyword = normalize_keyword($keyword);
        debug("resolving normalized keyword " . $keyword  . ".");
    }

    # Stemming support. If enabled and a stemmer is available for the current language, index the stem of the keyword not the keyword itself.
    # This means plural/singular (and other) forms of a word are treated as equivalents.

    if ($stem && $stemming && function_exists("GetStem")) {
        $keyword = GetStem($keyword);
    }

    $return = ps_value("SELECT ref value FROM keyword WHERE keyword = ?", array("s",trim($keyword)), 0, "keyword");
    if ($return === 0) {
        if ($create) {
            # Create a new keyword.
            debug("resolve_keyword: Creating new keyword for " . $keyword);
            ps_query("insert into keyword (keyword,soundex,hit_count) values (?,left(?,10),0)", array("s",$keyword,"s",soundex($keyword)));
            $return = sql_insert_id();
            clear_query_cache("keyword");
        } else {
            return false;
        }
    }

    $resolve_keyword_cache[$kwhash] = $return;
    return $return;
}

/**
 * Generates a list of keywords for indexing, including all possible infixes
 * for each keyword in the provided list.
 *
 * This function processes each keyword and, for those without spaces,
 * adds all possible infixes of a specified minimum length to the return array.
 * The resulting array is suitable for indexing in fields that have partial indexing enabled.
 *
 * @param array $keywords An array of keywords to process for partial indexing.
 * @return array An array of keywords, each with its associated position in the original list.
 */
function add_partial_index($keywords)
{
    $return = array();
    $position = 0;
    $x = 0;
    for ($n = 0; $n < count($keywords); $n++) {
        $keyword = trim($keywords[$n]);
        $return[$x]['keyword'] = $keyword;
        $return[$x]['position'] = $position;
        $x++;
        if (strpos($keyword, " ") === false) { # Do not do this for keywords containing spaces as these have already been broken to individual words using the code above.
            global $partial_index_min_word_length;
            # For each appropriate infix length
            for ($m = $partial_index_min_word_length; $m < strlen($keyword); $m++) {
                # For each position an infix of this length can exist in the string
                for ($o = 0; $o <= strlen($keyword) - $m; $o++) {
                    $infix = mb_substr($keyword, $o, $m);
                    $return[$x]['keyword'] = $infix;
                    $return[$x]['position'] = $position; // infix has same position as root
                    $x++;
                }
            }
        } # End of no-spaces condition
        $position++; // end of root keyword
    } # End of partial indexing keywords loop
    return $return;
}


/**
 * Suggests complete existing keywords based on a partial search term.
 *
 * This function fetches keywords that match the given partial word, returning
 * suggestions from the keyword database. It also considers user permissions by
 * excluding indexed fields that are hidden from the user. Additionally, it can
 * restrict results to a specific resource type field.
 *
 * @param string $search The partial keyword to search for.
 * @param string $ref (optional) The resource type field to restrict suggestions to.
 * @return array An array of suggested keywords matching the search criteria.
 */
function get_suggested_keywords($search, $ref = "")
{
    global $autocomplete_search_items,$autocomplete_search_min_hitcount;

    # Fetch a list of fields that are not available to the user - these must be omitted from the search.
    $hidden_indexed_fields = get_hidden_indexed_fields();

    $restriction_clause_node = "";
    $params = array("s",$search . "%");

    if (count($hidden_indexed_fields) > 0) {
        $restriction_clause_node .= " AND n.resource_type_field NOT IN (" . ps_param_insert(count($hidden_indexed_fields)) . ")";
        $params = array_merge($params, ps_param_fill($hidden_indexed_fields, "i"));
    }

    if ((string)(int)$ref == $ref) {
        $restriction_clause_node .= " AND n.resource_type_field = ?";
        $params[] = "i";
        $params[] = $ref;
    }

    $params[] = "i";
    $params[] = $autocomplete_search_items;

    return ps_array("SELECT ak.keyword value
        FROM
            (
            SELECT k.keyword, k.hit_count
            FROM keyword k
            JOIN node_keyword nk ON nk.keyword=k.ref
            JOIN node n ON n.ref=nk.node
            WHERE k.keyword LIKE ? " . $restriction_clause_node . "
            ) ak
        GROUP BY ak.keyword, ak.hit_count
        ORDER BY ak.hit_count DESC LIMIT ?", $params);
}

/**
 * Retrieves keywords related to a given keyword reference.
 *
 * This function checks a cache for related keywords associated with the provided
 * keyword reference. If not found in the cache, it queries the database for related
 * keywords. The relationship can be one-way or bidirectional based on the
 * configuration. It returns an array of related keyword references.
 *
 * @param int $keyref The reference ID of the keyword for which to find related keywords.
 * @return array An array of related keyword references.
 */
function get_related_keywords($keyref)
{
    debug_function_call("get_related_keywords", func_get_args());

    # For a given keyword reference returns the related keywords
    # Also reverses the process, returning keywords for matching related words
    # and for matching related words, also returns other words related to the same keyword.
    global $keyword_relationships_one_way, $related_keywords_cache;

    if (isset($related_keywords_cache[$keyref])) {
        return $related_keywords_cache[$keyref];
    } else {
        if ($keyword_relationships_one_way) {
            $related_keywords_cache[$keyref] = ps_array("SELECT related value FROM keyword_related WHERE keyword = ?", array("i", $keyref), "keywords_related");
            return $related_keywords_cache[$keyref];
        } else {
            $related_keywords_cache[$keyref] = ps_array("SELECT keyword value FROM keyword_related WHERE related = ? UNION SELECT related value FROM keyword_related WHERE (keyword = ? OR keyword IN (SELECT keyword value FROM keyword_related WHERE related = ?)) AND related <> ?", array("i", $keyref, "i", $keyref, "i", $keyref, "i", $keyref), "keywords_related");
            return $related_keywords_cache[$keyref];
        }
    }
}

/**
 * Retrieves keywords and their related keywords, optionally filtered by specific keywords.
 *
 * This function returns a list of keywords along with their related keywords grouped
 * together. It can filter the results based on the provided keyword or specific keyword
 * string. The related keywords are returned as a comma-separated string.
 *
 * @param string $find An optional keyword to find related keywords for. If specified,
 *                     it filters the results to include only the related keywords for
 *                     this keyword.
 * @param string $specific An optional specific keyword to find. If specified, it filters
 *                         the results to include only the related keywords for this specific
 *                         keyword.
 * @return array An array of keywords and their related keywords grouped together.
 */
function get_grouped_related_keywords($find = "", $specific = "")
{
    debug_function_call("get_grouped_related_keywords", func_get_args());
    $sql = "";
    $params = array();

    if ($find != "") {
        $sql = "where k1.keyword=? or k2.keyword=?";
        $params[] = "s";
        $params[] = $find;
        $params[] = "s";
        $params[] = $find;
    }
    if ($specific != "") {
        $sql = "where k1.keyword=?";
        $params[] = "s";
        $params[] = $specific;
    }

    return ps_query("
        select k1.keyword,group_concat(k2.keyword order by k2.keyword separator ', ') related from keyword_related kr
            join keyword k1 on kr.keyword=k1.ref
            join keyword k2 on kr.related=k2.ref
        $sql
        group by k1.keyword order by k1.keyword
        ", $params, "keywords_related");
}

/**
 * Saves the related keywords for a specified keyword.
 *
 * This function first resolves the keyword reference for the provided keyword. It then deletes
 * any existing relationships for that keyword and inserts the new related keywords into the
 * database.
 *
 * @param string $keyword The keyword for which related keywords are being saved.
 * @param string $related A comma-separated string of related keywords to associate with the
 *                        specified keyword.
 * @return bool Returns true on success, or false on failure.
 */
function save_related_keywords($keyword, $related)
{
    debug_function_call("save_related_keywords", func_get_args());

    $keyref = resolve_keyword($keyword, true, false, false);
    $s = trim_array(explode(",", $related));

    ps_query("DELETE FROM keyword_related WHERE keyword = ?", array("i",$keyref));
    if (trim($related) != "") {
        for ($n = 0; $n < count($s); $n++) {
            ps_query("insert into keyword_related (keyword,related) values (?,?)", array("i",$keyref,"i",resolve_keyword($s[$n], true, false, false)));
        }
    }
    clear_query_cache("keywords_related");
    return true;
}

/**
 * Retrieves a list of fields suitable for the simple search box.
 *
 * This function gathers all resource type fields that are marked for simple search usage.
 * It includes standard fields and custom fields that have their titles translated. It ensures
 * that only fields with appropriate permissions and those that are either indexed or of a
 * fixed list type are included in the returned array.
 *
 * @return array An array of fields suitable for simple search, including their titles and other
 *               properties, filtered by permissions and search capabilities.
 */
function get_simple_search_fields()
{
    global $FIXED_LIST_FIELD_TYPES, $country_search;

    # First get all the fields
    $allfields = get_resource_type_fields("", "order_by");

    # Applies field permissions and translates field titles in the newly created array.
    $return = array();
    for ($n = 0; $n < count($allfields); $n++) {
        if (
            # Check if for simple_search
            # Also include the country field even if not selected
            # This is to provide compatibility for older systems on which the simple search box was not configurable
            # and had a simpler 'country search' option.
            ($allfields[$n]["simple_search"] == 1 || (isset($country_search) && $country_search && $allfields[$n]["ref"] == 3))
            &&
            # Must be either indexed or a fixed list type
            ($allfields[$n]["keywords_index"] == 1 || in_array($allfields[$n]["type"], $FIXED_LIST_FIELD_TYPES))
            &&
            metadata_field_view_access($allfields[$n]["ref"])
        ) {
            $allfields[$n]["title"] = lang_or_i18n_get_translated($allfields[$n]["title"], "fieldtitle-");
            $return[] = $allfields[$n];
        }
    }
    return $return;
}

/**
 * Retrieves a list of fields/properties suitable for search display based on the provided field references.
 *
 * @param array $field_refs An array of field references to filter the search display fields.
 * @return array An array of fields with their properties, including translated titles, that are
 *               visible to the user based on permission checks.
 * @throws Exception if the input parameter is not an array.
 */
function get_fields_for_search_display($field_refs)
{
    if (!is_array($field_refs)) {
        exit(" passed to getfields() is not an array. ");
    }

    # Executes query.
    $fields = ps_query("select " . columns_in("resource_type_field") . " from resource_type_field where ref in (" . ps_param_insert(count($field_refs)) . ")", ps_param_fill($field_refs, "i"), "schema");

    # Applies field permissions and translates field titles in the newly created array.
    $return = array();
    for ($n = 0; $n < count($fields); $n++) {
        if (metadata_field_view_access($fields[$n]["ref"])) {
            $fields[$n]["title"] = lang_or_i18n_get_translated($fields[$n]["title"], "fieldtitle-");
            $return[] = $fields[$n];
        }
    }
    return $return;
}

/**
* Get all defined filters (currently only used for search)
*
* @param string $order  column to order by
* @param string $sort   sort order ("ASC" or "DESC")
* @param string $find   text to search for in filter
*
* @return array
*/
function get_filters($order = "ref", $sort = "ASC", $find = "")
{
    $validorder = array("ref","name");
    if (!in_array($order, $validorder)) {
        $order = "ref";
    }

    if ($sort != "ASC") {
        $sort = "DESC";
    }

    $condition = "";
    $join = "";
    $params = array();

    if (trim($find) != "") {
        $join = " LEFT JOIN filter_rule_node fn ON fn.filter=f.ref LEFT JOIN node n ON n.ref = fn.node LEFT JOIN resource_type_field rtf ON rtf.ref=n.resource_type_field";
        $condition = " WHERE f.name LIKE ? OR n.name LIKE ? OR rtf.name LIKE ? OR rtf.title LIKE ?";

        $params[] = "s";
        $params[] = "%" . $find . "%";
        $params[] = "s";
        $params[] = "%" . $find . "%";
        $params[] = "s";
        $params[] = "" . $find . "";
        $params[] = "s";
        $params[] = "" . $find . "";
    }

    $sql = "SELECT f.ref, f.name FROM filter f {$join}{$condition} GROUP BY f.ref ORDER BY f.{$order} {$sort}"; // $order and $sort are already confirmed to be valid.

    return ps_query($sql, $params);
}

/**
* Get filter summary details
*
* @param int $filterid  ID of filter (from usergroup search_filter_id or user search_filter_oid)
*
* @return array
*/
function get_filter($filterid)
{
    // Codes for filter 'condition' column
    // 1 = ALL must apply
    // 2 = NONE must apply
    // 3 = ANY can apply

    if (!is_numeric($filterid) || $filterid < 1) {
            return false;
    }

    $filter  = ps_query("SELECT ref, name, filter_condition FROM filter f WHERE ref=?", array("i",$filterid));

    if (count($filter) > 0) {
        return $filter[0];
    }

    return false;
}

/**
* Get filter rules for use in search
*
* @param int $filterid  ID of filter (from usergroup search_filter_id or user search_filter_oid)
*
* @return array
*/
function get_filter_rules($filterid)
{
    $filter_rule_nodes  = ps_query("SELECT fr.ref as rule, frn.node_condition, frn.node FROM filter_rule fr LEFT JOIN filter_rule_node frn ON frn.filter_rule=fr.ref WHERE fr.filter=?", array("i",$filterid));

    // Convert results into useful array
    $rules = array();
    foreach ($filter_rule_nodes as $filter_rule_node) {
        $rule = $filter_rule_node["rule"];
        if (!isset($rules[$filter_rule_node["rule"]])) {
            $rules[$rule] = array();
            $rules[$rule]["nodes_on"] = array();
            $rules[$rule]["nodes_off"] = array();
        }
        if ($filter_rule_node["node_condition"] == 1) {
            $rules[$rule]["nodes_on"][] = $filter_rule_node["node"];
        } else {
            $rules[$rule]["nodes_off"][] = $filter_rule_node["node"];
        }
    }

    return $rules;
}

/**
* Get filter rule
*
* @param int $ruleid  - ID of filter rule
*
* @return array
*/
function get_filter_rule($ruleid)
{
    $rule_data = ps_query("SELECT fr.ref, frn.node_condition, group_concat(frn.node) AS nodes, n.resource_type_field FROM filter_rule fr JOIN filter_rule_node frn ON frn.filter_rule=fr.ref join node n on frn.node=n.ref WHERE fr.ref=? GROUP BY n.resource_type_field,frn.node_condition", array("i",$ruleid));
    if (count($rule_data) > 0) {
        return $rule_data;
    }
    return false;
}

/**
* Save filter, will return existing filter ID if text matches already migrated
*
* @param int $filter            - ID of filter. Set to 0 for new filter
* @param int $filter_name       - Name of filter
* @param int $filter_condition  - One of RS_FILTER_ALL,RS_FILTER_NONE,RS_FILTER_ANY
*
* @return boolean | integer     - false, or ID of filter
*/
function save_filter(int $filter, string $filter_name, string $filter_condition)
{
    if (!in_array($filter_condition, array(RS_FILTER_ALL,RS_FILTER_NONE,RS_FILTER_ANY))) {
        return false;
    }

    if ($filter != 0) {
        if (!is_int_loose($filter)) {
            return false;
        }
        ps_query("UPDATE filter SET name=?, filter_condition=? WHERE ref = ?", array("s",$filter_name,"s",$filter_condition,"i",$filter));
    } else {
        ps_query("INSERT INTO filter (name, filter_condition) VALUES (?,?)", array("s",$filter_name,"s",$filter_condition));
        return sql_insert_id();
    }

    return $filter;
}

/**
* Save filter rule, will return existing rule ID if text matches already migrated
*
* @param int $filter_rule       - ID of filter_rule
* @param int $filterid          - ID of associated filter
* @param array|string $ruledata   - Details of associated rule nodes  (as JSON if submitted from rule edit page)
*
* @return boolean | integer     - false, or ID of filter_rule
*/
function save_filter_rule($filter_rule, $filterid, $rule_data)
{
    if (!is_array($rule_data)) {
        $rule_data = json_decode($rule_data);
    }

    if ($filter_rule != "new" && is_int_loose($filter_rule) && $filter_rule > 0) {
        ps_query("DELETE FROM filter_rule_node WHERE filter_rule = ?", array("i",$filter_rule));
    } else {
        ps_query("INSERT INTO filter_rule (filter) VALUES (?)", array("i",$filterid));
        $filter_rule = sql_insert_id();
    }

    if (count($rule_data) > 0) {
        $nodeinsert = array();
        $params = array();
        for ($n = 0; $n < count($rule_data); $n++) {
            $condition = $rule_data[$n][0];
            for ($rd = 0; $rd < count($rule_data[$n][1]); $rd++) {
                $nodeid = $rule_data[$n][1][$rd];
                $nodeinsert[] = "(?,?,?)";
                $params[] = "i";
                $params[] = $filter_rule;
                $params[] = "i";
                $params[] = $nodeid;
                $params[] = "i";
                $params[] = $condition;
            }
        }
        $sql = "INSERT INTO filter_rule_node (filter_rule,node,node_condition) VALUES " . implode(',', $nodeinsert);
        ps_query($sql, $params);
    }
    return $filter_rule;
}

/**
* Delete specified filter
*
* @param int $filter       - ID of filter
*
* @return boolean | array of users/groups using filter
*/
function delete_filter($filter)
{
    if (!is_numeric($filter)) {
            return false;
    }

    // Check for existing use of filter
    $checkgroups = ps_array("SELECT ref value FROM usergroup WHERE search_filter_id=? OR edit_filter_id=? OR derestrict_filter_id=?", ['i', $filter,'i', $filter,'i', $filter], "");
    $checkusers  = ps_array("SELECT ref value FROM user WHERE search_filter_o_id=? ", array("i",$filter), "");

    if (count($checkgroups) > 0 || count($checkusers) > 0) {
        return array("groups" => $checkgroups, "users" => $checkusers);
    }

    // Delete and cleanup any unused
    ps_query("DELETE FROM filter WHERE ref=?", array("i",$filter));
    ps_query("DELETE FROM filter_rule WHERE filter NOT IN (SELECT ref FROM filter)");
    ps_query("DELETE FROM filter_rule_node WHERE filter_rule NOT IN (SELECT ref FROM filter_rule)");
    ps_query("DELETE FROM filter_rule WHERE ref NOT IN (SELECT DISTINCT filter_rule FROM filter_rule_node)");

    return true;
}

/**
* Delete specified filter_rule
*
* @param int $filter       - ID of filter_rule
*
* @return boolean | integer     - false, or ID of filter_rule
*/
function delete_filter_rule($filter_rule)
{
    if (!is_numeric($filter_rule)) {
            return false;
    }

    // Delete and cleanup any unused nodes
    ps_query("DELETE FROM filter_rule WHERE ref=?", array("i",$filter_rule));
    ps_query("DELETE FROM filter_rule_node WHERE filter_rule NOT IN (SELECT ref FROM filter_rule)");
    ps_query("DELETE FROM filter_rule WHERE ref NOT IN (SELECT DISTINCT filter_rule FROM filter_rule_node)");

    return true;
}

/**
* Copy specified filter_rule
*
* @param int $filter            - ID of filter_rule to copy
*
* @return boolean | integer     - false, or ID of new filter
*/
function copy_filter($filter)
{
    if (!is_numeric($filter)) {
            return false;
    }

    ps_query("INSERT INTO filter (name, filter_condition) SELECT name, filter_condition FROM filter WHERE ref=?", array("i",$filter));
    $newfilter = sql_insert_id();
    $rules = ps_array("SELECT ref value from filter_rule  WHERE filter=?", array("i",$filter));
    foreach ($rules as $rule) {
        ps_query("INSERT INTO filter_rule (filter) VALUES (?)", array("i",$newfilter));
        $newrule = sql_insert_id();
        ps_query("INSERT INTO filter_rule_node (filter_rule, node_condition, node) SELECT ? , node_condition, node FROM filter_rule_node WHERE filter_rule=?", array("i",$newrule,"i",$rule));
    }

    return $newfilter;
}

/**
* Add POST/GET parameters into search string. Moved from pages/search.php
*
* @param string $search        Existing search string without params added
*
* @return string               Updated string with params added
*/
function update_search_from_request($search)
{
    global $config_separators,$resource_field_verbatim_keyword_regex;
    reset($_POST);
    reset($_GET);

    foreach (array_merge($_GET, $_POST) as $key => $value) {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value != "") {
            if (substr($key, 0, 6) == "field_") {
                if ((string_ends_with($key, "-y") !== false) || (string_ends_with($key, "-m") !== false) || (string_ends_with($key, "-d") !== false)) {
                    # Date field

                    # Construct the date from the supplied dropdown values
                    $key_part = substr($key, 0, strrpos($key, "-"));
                    $field = substr($key_part, 6);
                    $value = "";
                    if (strpos($search, $field . ":") === false) {
                        $key_year = $key_part . "-y";
                        $value_year = getval($key_year, "");

                        if ($value_year != "") {
                            $value = $value_year;
                        } else {
                            $value = "nnnn";
                        }

                        $key_month = $key_part . "-m";
                        $value_month = getval($key_month, "");

                        if ($value_month == "") {
                            $value_month .= "nn";
                        }

                        $key_day = $key_part . "-d";
                        $value_day = getval($key_day, "");

                        if ($value_day != "") {
                            $value .= "|" . $value_month . "|" . $value_day;
                        } elseif ($value_month != "nn") {
                            $value .= "|" . $value_month;
                        }

                        $search = (($search == "") ? "" : join(", ", split_keywords($search)) . ", ") . $field . ":" . $value;
                    }
                } elseif (strpos($key, "_drop_") !== false) {
                    # Dropdown field
                    # Add keyword exactly as it is as the full value is indexed as a single keyword for dropdown boxes.
                    $search = (($search == "") ? "" : join(", ", split_keywords($search, false, false, false, false, true)) . ", ") . substr($key, 11) . ":" . $value;
                } elseif (strpos($key, "_cat_") !== false) {
                    # Category tree field
                    # Add keyword exactly as it is as the full value is indexed as a single keyword for dropdown boxes.
                    $value = str_replace(",", ";", $value);
                    if (substr($value, 0, 1) == ";") {
                        $value = substr($value, 1);
                    }

                    $search = (($search == "") ? "" : join(", ", split_keywords($search, false, false, false, false, true)) . ", ") . substr($key, 10) . ":" . $value;
                } else {
                    # Standard field
                    if (
                        isset($resource_field_verbatim_keyword_regex[substr($key, 6)])
                        && preg_match($resource_field_verbatim_keyword_regex[substr($key, 6)], str_replace('*', '', $value))
                    ) {
                        $values =  explode(' ', mb_strtolower(trim_spaces(str_replace($config_separators, ' ', $value)), 'UTF-8'));
                    } else {
                        $values = [$value];
                    }

                    foreach ($values as $value) {
                        # Standard field
                        $search = (($search == "") ? "" : join(", ", split_keywords($search, false, false, false, false, true)) . ", ") . substr($key, 6) . ":" . $value;
                    }
                }
            } elseif ('' != $value && is_iterable($value) && substr($key, 0, 14) == 'nodes_searched') {
                // Nodes can be searched directly when displayed on simple search bar
                // Note: intially they come grouped by field as we need to know whether if
                // there is a OR case involved (ie. @@101@@102)
                $node_ref = '';

                foreach ($value as $searched_field_nodes) {
                    // Fields that are displayed as a dropdown will only pass one node ID
                    if (!is_array($searched_field_nodes) && '' == $searched_field_nodes) {
                        continue;
                    } elseif (!is_array($searched_field_nodes)) {
                        $node_ref .= ', ' . NODE_TOKEN_PREFIX . $searched_field_nodes;

                        continue;
                    }

                    // For fields that can pass multiple node IDs at a time
                    $node_ref .= ', ';

                    foreach ($searched_field_nodes as $searched_node_ref) {
                        $node_ref .= NODE_TOKEN_PREFIX . $searched_node_ref;
                    }
                }
                if ($node_ref !== '') {
                    $search .= ", " . $node_ref;
                }
            }
        }
    }

    $year = getval("basicyear", "");
    if ($year != "") {
        $search = (($search == "") ? "" : join(", ", split_keywords($search, false, false, false, false, true)) . ", ") . "basicyear:" . $year;
    }
    $month = getval("basicmonth", "");
    if ($month != "") {
        $search = (($search == "") ? "" : join(", ", split_keywords($search, false, false, false, false, true)) . ", ") . "basicmonth:" . $month;
    }
    $day = getval("basicday", "");
    if ($day != "") {
        $search = (($search == "") ? "" : join(", ", split_keywords($search, false, false, false, false, true)) . ", ") . "basicday:" . $day;
    }

    return $search;
}

/**
 * Retrieves the default resource types for search functionality.
 *
 * This function determines which resource types to include in the search based on the global
 * settings for resource and theme inclusion. If resources are to be included, it checks the
 * default resource types and returns them as an array. If no specific default resource types
 * are defined, it defaults to including "Global." If resources are not to be included, it
 * defaults to "Collections," and if themes are included, "FeaturedCollections" is also added.
 *
 * @return array An array of default resource types to be used in the search.
 */
function get_search_default_restypes()
{
    global $search_includes_resources, $search_includes_themes,$default_res_types;
    $defaultrestypes = array();

    if ($search_includes_resources) {
        if ($default_res_types == "") {
            $defaultrestypes[] = "Global";
        } else {
            $defaultrestypes = (is_array($default_res_types) ? $default_res_types : explode(",", (string) $default_res_types));
        }
    } else {
        $defaultrestypes[] = "Collections";
        if ($search_includes_themes) {
            $defaultrestypes[] = "FeaturedCollections";
        }
    }
    return $defaultrestypes;
}

/**
 * Retrieves the selected resource types for the search functionality.
 *
 *
 * @return array An array of selected resource types for the search.
 */
function get_selectedtypes()
{
    global $search_includes_resources, $default_advanced_search_mode;
    $restypes = getval("restypes", "");
    $advanced_search_section = getval("advanced_search_section", "");

    # If advanced_search_section is absent then load it from restypes
    if (
        getval("submitted", "") == ""
        && !isset($advanced_search_section)
    ) {
            $advanced_search_section = $restypes;
    }

    # If clearbutton pressed then the selected types are reset based on configuration settings
    if (getval('resetform', '') != '') {
        if (isset($default_advanced_search_mode)) {
            $selectedtypes = explode(',', trim($default_advanced_search_mode, ' ,'));
        } else {
            if ($search_includes_resources) {
                $selectedtypes = array('Global', 'Media');
            } else {
                $selectedtypes = array('Collections');
            }
        }
    } else # Not clearing, so get the currently selected types
        {
        $selectedtypes = explode(',', $advanced_search_section);
    }

    return $selectedtypes;
}

/**
 * Renders the buttons for the advanced search form.
 *
 * This function generates HTML for two buttons:
 * one to reset the search form and clear the submitted search criteria,
 * and another to execute the search.
 *
 * @return void This function outputs HTML directly and does not return a value.
 */

function render_advanced_search_buttons()
{
    global $lang, $baseurl_short;
    ?>

    <div class="QuestionSubmit QuestionSticky">
        <input name="resetform" class="resetform" type="submit" onClick="unsetCookie('search_form_submit','<?php echo $baseurl_short; ?>')" value="<?php echo escape($lang["clearbutton"]); ?>" />
        &nbsp;
        <input name="dosearch" class="dosearch" type="submit" value="<?php echo escape($lang["action-viewmatchingresults"]); ?>" />
    </div>

    <?php
}

/**
* If a "fieldX" order_by is used, check it's a valid value.
* Where the value is invalid, the default 'resourceid' will be used instead.
*
* @param   string   Order by string e.g. field8
*
* @return  string   Order by string if valid else default 'resourceid'
*/
function check_order_by_in_table_joins($order_by): string
{
    if (substr($order_by, 0, 5) == "field" && !in_array(substr($order_by, 5), get_resource_table_joins())) {
        global $userref;
        debug("Userref $userref requested invalid order_by $order_by - using default: resourceid - check config and user preferences.");
        return 'resourceid';
    }

    return $order_by;
}

/**
* Get collection total resource count for a list of collections
*
* @param array $refs List of collection IDs
*
* @return array Returns table of collections and their total resource count (taking into account access controls). Please
*               note that the returned array might NOT contain keys for all the input IDs (e.g validation failed).
*/
function get_collections_resource_count(array $refs)
{
    $return = [];

    foreach ($refs as $ref) {
        if (!(is_int_loose($ref) && $ref > 0)) {
            continue;
        }

        $colresults = do_search("!collection{$ref}", '', 'relevance', '0', [0,0]);
        if (!isset($colresults["total"])) {
            continue;
        }
        $return[$ref] = $colresults["total"];
    }

    return $return;
}

/**
 * Get all search request parameters. Note that this does not escape the
 * parameters which must be sanitised using e.g. htmlspecialchars() or urlencode() before rendering on page
 *
 * @return array()
 */
function get_search_params()
{
    $searchparams = array(
        "search"        => "",
        "restypes"      => "",
        "archive"       => "",
        "order_by"      => "",
        "sort"          => "",
        "offset"        => "",
        "k"             => "",
        "access"        => "",
        "foredit"       => "",
        "recentdaylimit" => "",
        "go"            => "",
        );
    $requestparams = array();
    foreach ($searchparams as $searchparam => $default) {
        $requestparams[$searchparam] = getval($searchparam, $default);
    }
    return $requestparams;
}

/**
* Helper function to check a string is not just the asterisk.
*
* @param string $str The string to be checked.
*
* @return boolean
*/
function is_not_wildcard_only(string $str)
{
    return trim($str) !== '*';
}

/**
 * Convert node searches into a friendly syntax. Used by search_title_processing.php
 *
 * @param  string $string   Search string
 * @return string
 */
function search_title_node_processing($string)
{
    if (substr(ltrim($string), 0, 2) == NODE_TOKEN_PREFIX) {
        # convert to shortname:value
        $node_id = substr(ltrim($string), 2);
        $node_data = array();
        get_node($node_id, $node_data);
        $field_title = ps_value("select name value from resource_type_field where ref=?", array("i",$node_data['resource_type_field']), '', 'schema');
        return $field_title . ":" . $node_data['name'];
    }
    return $string;
}

/**
 * Allow $fetchrows as supplied to do_search() to support an integer or array. If integer then search will recieve the number of rows with no offset.
 * If array then search will receive the number of rows to return and an offset allowing for chunking of results.
 * $chunk_offset[0] is the offset of the first row to return. $chunk_offset[1] is the number of rows to return in the batch. $chunk_offset[0] will normally be 0 in the first search,
 * increasing by $chunk_offset[1] for each search, generated by an external looping structure. This allows for batches of $chunk_offset[1] search results up to the total size of the search.
 * For an example {@see pages/csv_export_results_metadata.php}. This approach can be used to avoid particularly large searches exceeding the PHP memory_limit when processing the data in ps_query().
 *
 * @param  int|array   $fetchrows           $fetchrows value passed from do_search() / search_special(). See details above.
 * @param  int         $chunk_offset        Starting position for offset. Default is 0 if none supplied i.e. $fetchrows is int.
 * @param  int         $search_chunk_size   Number of rows to return.
 *
 * @return void
 */
function setup_search_chunks($fetchrows, ?int &$chunk_offset, ?int &$search_chunk_size): void
{
    if (is_array($fetchrows) && isset($fetchrows[0]) && isset($fetchrows[1])) {
        $chunk_offset = max((int)$fetchrows[0], 0);
        $search_chunk_size = (int)$fetchrows[1];
    } else {
        $chunk_offset = 0;
        $search_chunk_size = (int)$fetchrows;
    }
}

/**
 * Log which keywords are used in a search
 *
 * @param array $keywords           refs of keywords used in a search
 * @param array $search_results     result of the search
 */
function log_keyword_usage($keywords, $search_result)
{
    if (is_array($keywords) && count($keywords) > 0) {
        if (array_key_exists('total', $search_result)) {
            $count = $search_result['total'];
        } elseif (is_array($search_result)) {
            $count = count($search_result);
        }

        $log_code = (!isset($count) || $count > 0 ? 'Keyword usage' : 'Keyword usage - no results found');
        foreach ($keywords as $keyword) {
            daily_stat($log_code, $keyword);
        }
    }
}

/**
 * Validate and set the order_by for the current search from the requested values passed to do_search()
 *
 * @param string $search
 * @param string $order_by
 * @param string $sort
 *
 * @return string
 *
 */
function set_search_order_by(string $search, string $order_by, string $sort): string
{
    global $lang;

    if (!validate_sort_value($sort)) {
        $sort = 'asc';
    }
    $order_by_date_sql_comma = ",";
    $order_by_date = "r.ref $sort";
    if (metadata_field_view_access($GLOBALS["date_field"])) {
        $order_by_date_sql = "field" . (int) $GLOBALS["date_field"] . " " . $sort;
        $order_by_date_sql_comma = ", {$order_by_date_sql}, ";
        $order_by_date = "{$order_by_date_sql}, r.ref {$sort}";
    }

    # Check if order_by is empty string as this avoids 'relevance' default
    if ($order_by === "") {
        $order_by = "relevance";
    }

    $order = [
        "relevance"       => "score $sort, user_rating $sort, total_hit_count $sort {$order_by_date_sql_comma} r.ref $sort",
        "popularity"      => "user_rating $sort, total_hit_count $sort {$order_by_date_sql_comma} r.ref $sort",
        "rating"          => "r.rating $sort, user_rating $sort, score $sort, r.ref $sort",
        "date"            => "$order_by_date, r.ref $sort",
        "colour"          => "has_image $sort, image_blue $sort, image_green $sort, image_red $sort {$order_by_date_sql_comma} r.ref $sort",
        "title"           => "field" . (int) $GLOBALS["view_title_field"] . " " . $sort . ", r.ref $sort",
        "file_path"       => "file_path $sort, r.ref $sort",
        "resourceid"      => "r.ref $sort",
        "resourcetype"    => "order_by $sort, resource_type $sort, r.ref $sort",
        "extension"       => "file_extension $sort, r.ref $sort",
        "status"          => "archive $sort, r.ref $sort",
        "modified"        => "modified $sort, r.ref $sort"
    ];

    // Used for collection sort order as sortorder is ASC, date is DESC
    $revsort = (strtoupper($sort) == 'DESC') ? "ASC" : " DESC";

    // These options are only supported if the default field 3 is still present
    if (in_array(3, get_resource_table_joins())) {
        $order["country"] = "field3 $sort, r.ref $sort";
        $order["titleandcountry"] = "field" . $GLOBALS["view_title_field"] . " $sort, field3 $sort, r.ref $sort";
    }

    // Add collection sort option only if searching a collection
    if (substr($search, 0, 11) == '!collection') {
        $order["collection"] = "c.sortorder $sort,c.date_added $revsort,r.ref $sort";
    }

    # Check if date_field is being used as this will be needed in the inner select to be used in ordering
    $GLOBALS["include_fieldx"] = false;
    if (isset($order_by_date_sql) && array_key_exists($order_by, $order) && strpos($order[$order_by], $order_by_date_sql) !== false) {
        $GLOBALS["include_fieldx"] = true;
    }

    # Append order by field to the above array if absent and if named "fieldn" (where n is one or more digits)
    if (!in_array($order_by, $order) && (substr($order_by, 0, 5) == "field")) {
        $order_by = check_order_by_in_table_joins($order_by);
        # If fieldx is being used this will be needed in the inner select to be used in ordering
        $GLOBALS["include_fieldx"] = true;
        # Check for field type
        $field_order_check = ps_query(
            "SELECT field_constraint, sort_method
            FROM resource_type_field
            WHERE ref = ?
            LIMIT 1",
            ["i",str_replace("field", "", $order_by)],
            "",
            "schema"
        )[0];
        # Establish sort order (numeric or otherwise)
        # Attach ref as a final key to foster stable result sets which should eliminate resequencing when moving <- and -> through resources (in view.php)
        if ($field_order_check["sort_method"] == FIELD_SORT_METHODS['dot-notation']) {
            $order[$order_by] =
                "CASE WHEN TRIM($order_by) = '' THEN 0 ELSE 1 END $sort,
                ISNULL(REGEXP_SUBSTR(SUBSTRING_INDEX($order_by, '.', 1), '[^0-9]+')) $sort,
                IFNULL(REGEXP_SUBSTR(SUBSTRING_INDEX($order_by, '.', 1), '[^0-9]+'), '') $sort,
                CAST(REGEXP_SUBSTR(SUBSTRING_INDEX($order_by, '.', 1), '[0-9]+') AS UNSIGNED) $sort,";
            for ($n = 2; $n <= 10; $n++) {
                $order[$order_by] .= "\nCAST(SUBSTRING_INDEX(SUBSTRING_INDEX($order_by, '.', $n), '.', -1) AS UNSIGNED) $sort,";
            }
            $order[$order_by] .= "\nref $sort";
        } elseif ($field_order_check["field_constraint"] == 1) {
            $order[$order_by] = "$order_by +0 $sort,r.ref $sort";
        } else {
            $order[$order_by] = "$order_by $sort,r.ref $sort";
        }
    }
    hook("modifyorderarray");
    $order_by = (isset($order[$order_by]) ? $order[$order_by] : (substr($search, 0, 11) == '!collection' ? $order['collection'] : $order['relevance']));       // fail safe by falling back to default if not found

    return $order_by;
}

/**
 * Escape and process a string from ResourceSpace search syntax to regex syntax
 * Wildcards are replaced with '.*?' - any character 0 or more times
 * Multiple keyword matches are split into an OR group
 * Word boundaries are added to avoid unintended wildcards.
 *
 * @param  string $keyword      Keyword string without any field names or special search strings
 * @return string               Processed string ready for RLIKE searches
 */
function prepare_regex_search_string(string $keyword): string
{
    $keyword = preg_quote($keyword);
    $keyword = str_replace('\*', '.*?', $keyword);
    $keywords = explode(";", $keyword);
    if (count($keywords) > 1) {
        $keyword = '(' . implode("|", $keywords) . ')';
    }
    if (preg_match('/\w/', $keyword) === 0) {
        // Use lookaheads/lookbehinds for boundary-like behavior when the keyword consists of non-word characters (e.g. ,, &, -)
        // because \b only works for transitions between word characters (letters, digits, _) and non-word characters.
        $keyword = "(?<!\w)" . $keyword . "(?!\w)";
    } else {
        $keyword = "\\b" . $keyword . "\\b";
    }
    return $keyword;
}