<?php

/**
* Takes a search string $search, as provided by the user, and returns a results set of matching resources. If there are
* no matches, instead returns an array of suggested searches
*
* @uses debug()
* @uses hook()
* @uses ps_value()
* @uses ps_query()
* @uses split_keywords()
* @uses add_verbatim_keywords()
* @uses search_filter()
* @uses search_special()
*
* @param string      $search                  Search string
* @param string      $restypes                Optionally used to specify which resource types to search for
* @param string      $order_by
* @param string      $archive                 Allows searching in more than one archive state
* @param int|array   $fetchrows               - If passing an integer, retrieve the specified number of rows (a limit with no offset).
*                                             The returned array of resources will be padded with '0' elements up to the total without the limit.
*                                             - If passing an array, the first element must be the offset (int) and the second the limit (int)
*                                             (the number of rows to return). See setup_search_chunks() for more detail.
*                                             IMPORTANT: When passing an array, the returned array will be in structured form - as returned by
*                                             sql_limit_with_total_count() i.e. the array will have 'total' (the count) and 'data' (the resources)
*                                             named array elements, and the data will not be padded.
* @param string      $sort
* @param boolean     $access_override         Used by smart collections, so that all all applicable resources can be judged
*                                             regardless of the final access-based results
* @param integer     $starsearch              DEPRECATED_STARSEARCH passed in for backwards compatibility
* @param boolean     $ignore_filters
* @param boolean     $return_disk_usage
* @param string      $recent_search_daylimit
* @param string|bool $go                      Paging direction (prev|next)
* @param boolean     $stats_logging           Log keyword usage
* @param boolean     $return_refs_only
* @param boolean     $editable_only
* @param boolean     $returnsql               Returns the query as a PreparedStatementQuery instance
* @param integer     $access                  Search for resources with this access
*
* @return null|string|array|PreparedStatementQuery
*/
function do_search(
    $search,
    $restypes = '',
    $order_by = 'relevance',
    $archive = '0',
    $fetchrows = -1,
    $sort = 'desc',
    $access_override = false,
    $starsearch = DEPRECATED_STARSEARCH,     # Parameter retained for backwards compatibility
    $ignore_filters = false,
    $return_disk_usage = false,
    $recent_search_daylimit = '',
    $go = false,
    $stats_logging = true,
    $return_refs_only = false,
    $editable_only = false,
    $returnsql = false,
    $access = null,
    $smartsearch = false
) {
    debug_function_call("do_search", func_get_args());

    global $sql, $order, $select, $sql_join, $sql_filter, $orig_order, $usergroup,
        $userref,$k, $DATE_FIELD_TYPES,$stemming, $usersearchfilter, $userpermissions, $usereditfilter, $userdata,
        $lang, $baseurl, $internal_share_access, $config_separators, $date_field, $noadd, $wildcard_always_applied,
        $index_contributed_by, $max_results, $config_search_for_number,
        $category_tree_search_use_and_logic, $date_field, $FIXED_LIST_FIELD_TYPES, $userderestrictfilter;

    if (!is_a($select, "PreparedStatementQuery")) {
            $select = new PreparedStatementQuery($select ?? '', array());
        }

    if ($editable_only && !$returnsql && trim((string) $k) != "" && !$internal_share_access) {
        return array();
    }

    $alternativeresults = hook("alternativeresults", "", array($go));
    if ($alternativeresults) {
        return $alternativeresults;
    }

    if (!is_not_wildcard_only($search)) {
        $search = '';
        debug('do_search(): User searched only for "*". Converting this into an empty search instead.');
    }

    $modifyfetchrows = hook("modifyfetchrows", "", array($fetchrows));
    if ($modifyfetchrows) {
        $fetchrows = $modifyfetchrows;
    }

    if (!validate_sort_value($sort)) {
        $sort = 'asc';
    }

   // Check the requested order_by is valid for this search. Function also allows plugin hooks to change this
    $orig_order = $order_by;
    $order_by = set_search_order_by($search, $order_by, $sort);

    $archive = explode(",", $archive); // Allows for searching in more than one archive state

    // IMPORTANT!
    // add to this array in the format [AND group]=array(<list of nodes> to OR)
    $node_bucket = array();
    // add to this normal array to exclude nodes from entire search
    $node_bucket_not = array();
    // Take the current search URL and extract any nodes (putting into buckets) removing terms from $search
    resolve_given_nodes($search, $node_bucket, $node_bucket_not);

    $searchidmatch = false;
    // Used to check if ok to skip keyword due to a match with resource type/resource ID
    if (is_int_loose($search)) {
        // Resource ID is no longer indexed, if search is just for a single integer then include this
        $searchidmatch = ps_value("SELECT COUNT(*) AS value FROM resource WHERE ref = ?", ["i",$search], 0) != 0;
    }

    // Resource type is no longer indexed
    $restypenames = get_resource_types();

    # Extract search parameters and split to keywords.
    $search_params = $search;
    if (substr($search, 0, 1) == "!" && substr($search, 0, 6) != "!empty") {
        # Special search, discard the special search identifier when splitting keywords and extract the search parameters
        $s = strpos($search, " ");
        if ($s === false) {
            $search_params = ""; # No search specified
        } else {
            $search_params = substr($search, $s + 1); # Extract search params
        }
    }

    if ($search_params != "") {
        if (preg_match('/^[^\\s]+$/',$search)) {
            $keywords = [$search_params];
        } else {
            $keywords = split_keywords($search_params, false, false, false, false, true);
        }
    } else {
        $keywords = array();
    }

    $search = trim($search);
    $keywords = array_values(array_filter(array_unique($keywords), 'is_not_wildcard_only'));

    $modified_keywords = hook('dosearchmodifykeywords', '', array($keywords, $search));
    if ($modified_keywords) {
        $keywords = $modified_keywords;
    }

    # -- Build up filter SQL that will be used for all queries
    $sql_filter = new PreparedStatementQuery();
    $sql_filter = search_filter($search, $archive, $restypes, $recent_search_daylimit, $access_override, $return_disk_usage, $editable_only, $access, $smartsearch);
    debug("do_search(): \$sql_filter = '" . $sql_filter->sql . "', parameters = ['" . implode("','", $sql_filter->parameters) . "']");

    # Initialise variables.
    $sql = "";
    $sql_keyword_union              = array(); // An array of all the unions - at least one for each keyword
    //$sql_keyword_union_params       = array();
    $sql_keyword_union_aggregation  = array(); // This is added to the SELECT statement. Normally 'BIT_OR(`keyword_[union_index]_found`) AS `keyword_[union_index]_found`', where '[union_index]' will be replaced
    $sql_keyword_union_criteria     = array(); // Criteria for the union to be true - normally '`h`.`keyword_[union_index]_found`', where '[union_index]' will be replaced

    // For each union sql_keyword_union_or must be set
    // This will normally will be false to ensure that all keywords are found
    // Needs to be set to false when keywords are expanded and an extra $sql_keyword_union element is added (e.g. for wildcards) so that a match on any is ok
    $sql_keyword_union_or           = array();

    $sql_join = new PreparedStatementQuery();
    $sql_join->sql                  =   "";
    $sql_join->parameters           = [];

    # If returning disk used by the resources in the search results ($return_disk_usage=true) then wrap the returned SQL in an outer query that sums disk usage.
    $sql_prefix = "";
    $sql_suffix = "";
    if ($return_disk_usage) {
        $sql_prefix = "SELECT sum(disk_usage) total_disk_usage, count(*) total_resources, resourcelist.ref, resourcelist.score, resourcelist.user_rating, resourcelist.total_hit_count FROM (";
        $sql_suffix = ") resourcelist";
    }

    # ------ Advanced 'custom' permissions, need to join to access table.
    if ((!checkperm("v")) && !$access_override) {
        # one extra join (rca2) is required for user specific permissions (enabling more intelligent watermarks in search view)
        # the original join is used to gather group access into the search query as well.
        $sql_join->sql   = " LEFT OUTER JOIN resource_custom_access rca2 ON r.ref=rca2.resource AND rca2.user = ? AND (rca2.user_expires IS null or rca2.user_expires>now()) AND rca2.access<>2  ";
        array_push($sql_join->parameters, "i", $userref);
        $sql_join->sql  .= " LEFT OUTER JOIN resource_custom_access rca ON r.ref=rca.resource AND rca.usergroup = ? AND rca.access<>2 ";
        array_push($sql_join->parameters, "i", $usergroup);

        if ($sql_filter->sql != "") {
            $sql_filter->sql .= " AND ";
        }
        # If rca.resource is null, then no matching custom access record was found
        # If r.access is also 3 (custom) then the user is not allowed access to this resource.
        # Note that it's normal for null to be returned if this is a resource with non custom permissions (r.access<>3).
        $sql_filter->sql .= " NOT (rca.resource IS null AND r.access=3)";
    }

    # Join thumbs_display_fields to resource table
    $select->sql = "r.ref, r.resource_type, r.has_image, r.is_transcoding, r.creation_date, r.rating, r.user_rating, r.user_rating_count, r.user_rating_total, r.file_extension, r.preview_extension, r.image_red, r.image_green, r.image_blue, r.thumb_width, r.thumb_height, r.archive, r.access, r.colour_key, r.created_by, r.file_modified, r.file_checksum, r.request_count, r.new_hit_count, r.expiry_notification_sent, r.preview_tweaks, r.file_path, r.modified, r.file_size ";
    $select->parameters = array();
    $sql_hitcount_select = "r.hit_count";

    $modified_select = hook('modifyselect');
    $select->sql .= $modified_select ? $modified_select : '';      // modify select hook 1

    $modified_select2 = hook('modifyselect2');
    $select->sql .= $modified_select2 ? $modified_select2 : '';    // modify select hook 2

    $select->sql .= $return_disk_usage ? ',r.disk_usage' : '';      // disk usage

    // Get custom group and user access rights if available to generate resultant_access,
    // otherwise select null values so columns can still be used regardless
    // This can then be passed through  to access checking functions in order to eliminate many single queries.
    if (!checkperm("v") && !$access_override) {
        // Get custom access
        $select->sql .= ",rca.access group_access,rca2.access user_access ";
        if (!checkperm("g") && !$internal_share_access) {
            // Restrict all resources by default
            if (is_int_loose($userderestrictfilter) && $userderestrictfilter > 0) {
                // Add exemption for custom access
                $derestrict_filter_sql = get_filter_sql($userderestrictfilter);
                if (is_a($derestrict_filter_sql, "PreparedStatementQuery")) {
                    $access_sql = "CASE WHEN " . $derestrict_filter_sql->sql . " THEN 0 ELSE 1 END";
                    $select->sql .= ", LEAST(IFNULL(rca.access, $access_sql), IFNULL(rca2.access, $access_sql)) resultant_access ";
                    // Add node parameters to the start
                    $select->parameters = array_merge(
                        $select->parameters,
                        $derestrict_filter_sql->parameters,
                        $derestrict_filter_sql->parameters,
                    );
                }
            } else {
                // Set access to restricted unless custom access is set
                $select->sql .= ", LEAST(IFNULL(rca.access, 1), IFNULL(rca2.access, 1)) resultant_access ";
            }
        } else {
            // Consider X permission else apply standard resultant_access for this user based on resource access and custom access
            $sql_restricted_types = array();
            foreach ($restypenames as $res_check_type) {
                if (checkperm('X' . $res_check_type['ref'])) {
                    $sql_restricted_types[] = $res_check_type['ref'];
                }
            }
            // Custom access can override X permission. Note: rows not returned for custom access "Confidential" so X can't increase access here.
            if (count($sql_restricted_types) > 0) {
                $select->sql .= ", CASE ";
                foreach ($sql_restricted_types as $sql_restricted_type) {
                    $select->sql .= "WHEN resource_type = ? THEN LEAST(IFNULL(rca.access, 1), IFNULL(rca2.access, 1)) ";
                    $select->parameters[] = 'i';
                    $select->parameters[] = $sql_restricted_type;
                }
                $select->sql .= "ELSE LEAST(IFNULL(rca.access, r.access), IFNULL(rca2.access, r.access)) END resultant_access ";
            } else {
            $select->sql .= ", LEAST(IFNULL(rca.access, r.access), IFNULL(rca2.access, r.access)) resultant_access ";
            }
        }
    } else {
        $select->sql .= ", null group_access, null user_access ";
        $select->sql .= ", r.access resultant_access ";
    }

    # add 'joins' to select (only add fields if not returning the refs only)
    $joins = $return_refs_only === false || $GLOBALS["include_fieldx"] === true ? get_resource_table_joins() : array();
    foreach ($joins as $datajoin) {
        if (metadata_field_view_access($datajoin) || $datajoin == $GLOBALS["view_title_field"]) {
            $select->sql .= ", r.field{$datajoin} ";
        }
    }

    # Prepare SQL to add join table for all provided keywords

    $suggested = $keywords; # a suggested search
    $fullmatch = true;
    $c = 0;
    $t = new PreparedStatementQuery();
    $t2 = new PreparedStatementQuery();
    $score = "";

    # Do not process if a numeric search is provided (resource ID)
    global $keysearch;
    $keysearch = !($config_search_for_number && is_numeric($search));

    # Fetch a list of fields that are not available to the user - these must be omitted from the search.
    $hidden_indexed_fields = get_hidden_indexed_fields();

    // *******************************************************************************
    //                                                      order by RESOURCE TYPE
    // *******************************************************************************
    $sql_join->sql .= " JOIN resource_type AS rty ON r.resource_type = rty.ref ";
    $select->sql .= ", rty.order_by ";

    hook("search_pipeline_setup", "", array($search, $select, $sql_join , $sql_filter, $sql));

    // Search pipeline - each step handles an aspect of search and adds to the assembled SQL.
    $return = include "do_search_keywords.php";
    if ($return !== 1) {
        return $return;
    } // Forward any return from this include.
    $return = include "do_search_nodes.php";
    if ($return !== 1) {
        return $return;
    } // Handle returns from this include.
    $return = include "do_search_suggest.php";
    if ($return !== 1) {
        return $return;
    } // Handle returns from this include.
    $return = include "do_search_filtering.php";
    if ($return !== 1) {
        return $return;
    } // Handle returns from this include.
    $return = include "do_search_union_assembly.php";
    if ($return !== 1) {
        return $return;
    } // Handle returns from this include.

    # Handle numeric searches when $config_search_for_number=false, i.e. perform a normal search but include matches for resource ID first
    global $config_search_for_number;
    if (!$config_search_for_number && is_int_loose($search)) {
        # Always show exact resource matches first.
        $order_by = "(r.ref='" . $search . "') desc," . $order_by;
    }

    # Can only search for resources that belong to featured collections. Doesn't apply to user's special upload collection to allow for upload then edit mode.
    $upload_collection = '!collection' . (0 - $userref);
    if (checkperm("J") && $search != $upload_collection) {
        $collection_join = " JOIN collection_resource AS jcr ON jcr.resource = r.ref JOIN collection AS jc ON jcr.collection = jc.ref";
        $collection_join .= featured_collections_permissions_filter_sql("AND", "jc.ref", true);

        $sql_join->sql = $collection_join . $sql_join->sql;
    }

    # --------------------------------------------------------------------------------
    # Special Searches (start with an exclamation mark)
    # --------------------------------------------------------------------------------
    $sql_select = clone $select;
    $special_results = search_special($search, $sql_join, $fetchrows, $sql_prefix, $sql_suffix, $order_by, $orig_order, $sql_select, $sql_filter, $archive, $return_disk_usage, $return_refs_only, $returnsql);
    if ($special_results !== false) {
        log_keyword_usage($keywords_used, $special_results);
        return $special_results;
    }

    # -------------------------------------------------------------------------------------
    # Standard Searches
    # -------------------------------------------------------------------------------------

    # We've reached this far without returning.
    # This must be a standard (non-special) search.

    # Construct and perform the standard search query.
    $sql = new PreparedStatementQuery();
    if ($sql_filter->sql != "") {
        if ($sql->sql != "") {
            $sql->sql .= " AND ";
        }
        $sql->sql .= $sql_filter->sql;
        $sql->parameters = array_merge($sql->parameters, $sql_filter->parameters);
    }

    # Append custom permissions
    $t->sql .= $sql_join->sql;
    $t->parameters = array_merge($t->parameters, $sql_join->parameters);

    if ($score == "") {
        $score = $sql_hitcount_select;
    } # In case score hasn't been set (i.e. empty search)

    if (($t2->sql != "") && ($sql->sql != "")) {
        $sql->sql = " AND " . $sql->sql;
    }

    # Compile final SQL
    $results_sql = new PreparedStatementQuery();
    $results_sql->sql = $sql_prefix . "SELECT DISTINCT $score score, $select->sql FROM resource r" . $t->sql . " WHERE " . $t2->sql . $sql->sql . " GROUP BY r.ref, user_access, group_access ORDER BY " . $order_by . $sql_suffix;
    $results_sql->parameters = array_merge($select->parameters, $t->parameters, $t2->parameters, $sql->parameters);

    # Debug
    debug('$results_sql=' . $results_sql->sql . ", parameters: " . implode(",", $results_sql->parameters));

    setup_search_chunks($fetchrows, $chunk_offset, $search_chunk_size);

    if ($return_refs_only) {
        # Execute query but only ask for ref columns back from ps_query();
        # We force verbatim query mode on (and restore it afterwards) as there is no point trying to strip slashes etc. just for a ref column
        if ($returnsql) {
            return $results_sql;
        }
        $count_sql = clone $results_sql;
        $count_sql->sql = str_replace("ORDER BY " . $order_by, "", $count_sql->sql);
        $result = sql_limit_with_total_count($results_sql, $search_chunk_size, $chunk_offset, true, $count_sql);

        if (is_array($fetchrows)) {
            // Return without converting into the legacy padded array
            log_keyword_usage($keywords_used, $result);
            return $result;
        }

        $resultcount = $result["total"]  ?? 0;
        if ($resultcount > 0 && count($result["data"]) > 0) {
            $result = array_map(function ($val) {
                return ["ref" => $val["ref"]];
            }, $result["data"]);
        } elseif (!is_array($fetchrows)) {
            $result = [];
        }
        log_keyword_usage($keywords_used, $result);
        return $result;
    } else {
        # Execute query as normal
        if ($returnsql) {
            return $results_sql;
        }
        $count_sql = clone $results_sql;
        $count_sql->sql = str_replace("ORDER BY " . $order_by, "", $count_sql->sql);
        $result = sql_limit_with_total_count($results_sql, $search_chunk_size, $chunk_offset, true, $count_sql);
    }

    if (is_array($fetchrows)) {
        // Return without converting into the legacy padded array
        log_keyword_usage($keywords_used, $result);
        return $result;
    }
    $resultcount = $result["total"]  ?? 0;
    if ($resultcount > 0 & count($result["data"]) > 0) {
        $result = $result['data'];
        if ($search_chunk_size !== -1) {
            // Only perform legacy padding of results if not all rows have been requested or total may be incorrect
            $diff = $resultcount - count($result);
            while ($diff > 0) {
                $result = array_merge($result, array_fill(0, ($diff < 1000000 ? $diff : 1000000), 0));
                $diff -= 1000000;
            }
        }
        hook("beforereturnresults", "", array($result, $archive));
        log_keyword_usage($keywords_used, $result);
        return $result;
    } else {
        $result = [];
    }

    hook('zero_search_results');

    // No suggestions for field-specific searching or if just one keyword
    if (strpos($search, ":") !== false || count($keywords) === 1) {
        return "";
    }

    # All keywords resolved OK, but there were no matches
    # Remove keywords, least used first, until we get results.
    $lsql = new PreparedStatementQuery();
    $omitmatch = false;

    for ($n = 0; $n < count($keywords); $n++) {
        if (substr($keywords[$n], 0, 1) == "-") {
            $omitmatch = true;
            $omit = $keywords[$n];
        }
        if ($lsql->sql != "") {
            $lsql->sql .= " OR ";
        }
        $lsql->sql .= "keyword = ?";
        $lsql->parameters = array_merge($lsql->parameters, ["i", $keywords[$n]]);
    }

    if ($omitmatch) {
        return trim_spaces(str_replace(" " . $omit . " ", " ", " " . join(" ", $keywords) . " "));
    }
    if ($lsql->sql != "") {
        $least = ps_value("SELECT keyword value FROM keyword WHERE " . $lsql->sql . " ORDER BY hit_count ASC LIMIT 1", $lsql->parameters, "");
        return trim_spaces(str_replace(" " . $least . " ", " ", " " . join(" ", $keywords) . " "));
    } else {
        return array();
    }
}

// Take the current search URL and extract any nodes (putting into buckets) removing terms from $search
//
// Currently supports:
// @@!<node id> (NOT)
// @@<node id>@@<node id> (OR)
function resolve_given_nodes(&$search, &$node_bucket, &$node_bucket_not)
{

    // extract all of the words, a word being a bunch of tokens with optional NOTs
    if (preg_match_all('/(' . NODE_TOKEN_PREFIX . NODE_TOKEN_NOT . '*\d+)+/', $search, $words) === false || count($words[0]) == 0) {
        return;
    }

    // spin through each of the words and process tokens
    foreach ($words[0] as $word) {
        $search = preg_replace('/(, )?' . preg_quote($word, '/') . '\b/', '', $search); // remove the entire word and comma separator if found from the search string
        $search = trim(trim($search), ',');

        preg_match_all('/' . NODE_TOKEN_PREFIX . '(' . NODE_TOKEN_NOT . '*)(\d+)/', $word, $tokens);

        if (count($tokens[1]) == 1 && $tokens[1][0] == NODE_TOKEN_NOT) {      // you are currently only allowed NOT condition for a single token within a single word
            $node_bucket_not[] = $tokens[2][0];       // add the node number to the node_bucket_not
            continue;
        }

        $node_bucket[] = $tokens[2];
    }
}
