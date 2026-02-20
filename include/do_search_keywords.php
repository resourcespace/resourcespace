<?php

// *******************************************************************************
//
//  Included within do_search() for keywords processing and assembly into SQL
//
// *******************************************************************************

// Candidate for replacement function including the variables needed.
// function search_process_keyword($keyword, &$n, &$c, &$keywords, &$hidden_indexed_fields, &$search, &$searchidmatch, &$restypenames, &$ignore_filters, &$node_bucket, &$stats_logging, &$go, &$sql_keyword_union, &$sql_keyword_union_aggregation, &$sql_keyword_union_criteria, &$sql_keyword_union_or, &$omit, &$fullmatch, &$suggested, &$sql_join, &$sql_filter)

$keywords_used = [];

if ($keysearch) {
    $date_parts = [];
    for ($n = 0; $n < count($keywords); $n++) {
        $canskip = false;
        $search_field_restrict = "";
        $keyword = $keywords[$n];
        debug("do_search(): \$keyword = {$keyword}");
        $quoted_string = (substr($keyword, 0, 1) == "\""  || substr($keyword, 0, 2) == "-\"" ) && substr($keyword, -1, 1) == "\"";

        $quoted_field_match = false;
        $field_short_name_specified = false;

        // Extra sql to search non-field data that used to be stored in resource_keyword e.g. resource type/resource contributor
        $non_field_keyword_sql = new PreparedStatementQuery();

        if ($quoted_string && substr($keyword, 1, strlen(FULLTEXT_SEARCH_PREFIX)) == FULLTEXT_SEARCH_PREFIX) {
            // Full text search
            $fulltext_string = str_replace(FULLTEXT_SEARCH_QUOTES_PLACEHOLDER, "\"", substr($keyword, strlen(FULLTEXT_SEARCH_PREFIX) + 2, -1));
            if (strpos($fulltext_string, "@") !== false) {
                // There's an @ character in the fulltext search which InnoDB does not permit, so quote-wrap the search string
                $fulltext_string = "'\"" . $fulltext_string . "\"'";
            }

            $freetextunion = new PreparedStatementQuery();
            $freetextunion->sql = " SELECT resource, [bit_or_condition] 1 AS score FROM resource_node rn LEFT JOIN node n ON n.ref=rn.node WHERE MATCH(name) AGAINST (? IN BOOLEAN MODE)";
            $freetextunion->parameters = ["s",$fulltext_string];
            if (count($hidden_indexed_fields) > 0) {
                $freetextunion->sql .= " AND n.resource_type_field NOT IN (" .  ps_param_insert(count($hidden_indexed_fields)) . ")";
                $freetextunion->parameters = array_merge($freetextunion->parameters, ps_param_fill($hidden_indexed_fields, "i"));
            }

            $sql_keyword_union[] = $freetextunion;
            $sql_keyword_union_aggregation[] = "BIT_OR(`keyword_[union_index]_found`) AS `keyword_[union_index]_found`";
            $sql_keyword_union_or[] = false;
            $sql_keyword_union_criteria[] = "`h`.`keyword_[union_index]_found`";
            continue;
        }

        if ($keyword == $search && is_int_loose($keyword) && $searchidmatch) {
            // Resource ID is no longer indexed, if search is just for a single integer then include this
            $non_field_keyword_sql->sql .= " UNION (SELECT " . (int)$keyword . " AS resource, [bit_or_condition] 1 AS score)";
            $canskip = true;
        } elseif (in_array(mb_strtolower($keyword), array_map("mb_strtolower", array_column($restypenames, "name")))) {
            // Resource type is no longer actually indexed but this will still honour the config by including in search
            $non_field_keyword_sql->sql .= " UNION (SELECT r.ref AS resource, [bit_or_condition] 1 AS score FROM resource r LEFT JOIN resource_type rt ON r.resource_type=rt.ref WHERE r.ref > 0 AND rt.name LIKE ?)";
            array_push($non_field_keyword_sql->parameters, "s", $keyword);
            $canskip = true;
        }
        if ($index_contributed_by) {
            // Resource type is no longer actually indexed but this will still honour the config by including in search
            $matchusers = get_users(0, $keyword, "u.username", true);
            if (count($matchusers) > 0) {
                $user_sql = get_users(0, $keyword, "u.username", true, -1, "", true);
                $non_field_keyword_sql->sql .= " UNION (
                    SELECT r.ref AS resource, [bit_or_condition] 1 AS score
                    FROM resource r
                    WHERE
                        r.created_by IN (SELECT u.ref FROM (" . $user_sql->sql . " ) as u)
                        AND r.ref >0
                    )";
                $non_field_keyword_sql->parameters = array_merge(
                    $non_field_keyword_sql->parameters,
                    $user_sql->parameters
                );
                $canskip = true;
            }
        }

        if (
            (
                !$quoted_string
                || ($quoted_string // If quoted string with a field specified we first need to try and resolve it to a node instead of working out keyword positions etc.
                && strpos($keyword, ":") !== false)
            )
            && (substr($keyword, 0, 1) != "!"
                || substr($keyword, 0, 6) == "!empty")
        ) {
            $keywordprocessed = false;

            if (strpos($keyword, ":") !== false) {
                $field_short_name_specified = true;
                $kw = explode(":", ($quoted_string ? substr($keyword, 1, -1) : $keyword), 2);
                # Fetch field info
                global $fieldinfo_cache;
                $fieldname = $kw[0];
                $keystring = $kw[1];
                debug("do_search(): \$fieldname = {$fieldname}");
                debug("do_search(): \$keystring = {$keystring}");
                if (isset($fieldinfo_cache[$fieldname])) {
                    $fieldinfo = $fieldinfo_cache[$fieldname];
                } else {
                    $fieldinfo = ps_query("SELECT ref, `type` FROM resource_type_field WHERE name = ?", ["s",$fieldname], "schema");

                    // Checking for date from Simple Search will result with a fieldname like 'year' which obviously does not exist
                    if (0 === count($fieldinfo) && ('basicyear' == $kw[0] || 'basicmonth' == $kw[0] || 'basicday' == $kw[0])) {
                        $fieldinfo = ps_query("SELECT ref, `type` FROM resource_type_field WHERE ref = ?", ["i",$date_field], "schema");
                    }
                    if (0 === count($fieldinfo)) {
                        // Search may just happen to include a colon - treat the colon as a space and add to $keywords array to process separately
                        $addedkeywords = explode(":", $keyword);
                        $keywords = array_merge($keywords, $addedkeywords);
                        continue;
                    } else {
                        $fieldinfo = $fieldinfo[0];
                        $fieldinfo_cache[$fieldname] = $fieldinfo;
                    }
                }
                if (!metadata_field_view_access($fieldinfo['ref'])) {
                    // User can't search against a metadata field they don't have access to
                    return false;
                }
            }

            //First try and process special keyword types
            if ($field_short_name_specified && !$quoted_string && !$ignore_filters && isset($fieldinfo['type']) && in_array($fieldinfo['type'], $DATE_FIELD_TYPES)) {
                // ********************************************************************************
                // Date field keyword
                // ********************************************************************************

                global $datefieldinfo_cache;
                if (isset($datefieldinfo_cache[$fieldname])) {
                    $datefieldinfo = $datefieldinfo_cache[$fieldname];
                } else {
                    $datefieldinfo = ps_query("SELECT ref FROM resource_type_field WHERE name = ? AND type IN (" . FIELD_TYPE_DATE_AND_OPTIONAL_TIME . "," . FIELD_TYPE_EXPIRY_DATE . "," . FIELD_TYPE_DATE . "," . FIELD_TYPE_DATE_RANGE . ")", ["s",$fieldname], "schema");
                    $datefieldinfo_cache[$fieldname] = $datefieldinfo;
                }

                if (count($datefieldinfo) && substr($keystring, 0, 5) != "range") {
                    $c++;
                    $datefieldinfo = $datefieldinfo[0];
                    $datefield = $datefieldinfo["ref"];

                    $val = str_replace("n", "_", $keystring);
                    $val = str_replace("|", "-", $val);

                    if ($fieldinfo['type'] == FIELD_TYPE_DATE_RANGE) {
                        // Find where the searched value is between the range values
                        $sql_join->sql .= " JOIN resource_node drrn" . $c . "s ON drrn" . $c . "s.resource=r.ref JOIN node drn" . $c . "s ON drn" . $c . "s.ref=drrn" . $c . "s.node AND drn" . $c . "s.resource_type_field = ? AND drn" . $c . "s.name >= ? JOIN resource_node drrn" . $c . "e ON drrn" . $c . "e.resource=r.ref JOIN node drn" . $c . "e ON drn" . $c . "e.ref=drrn" . $c . "e.node AND drn" . $c . "e.resource_type_field = ? AND DATE(drn" . $c . "e.name) <= ?";
                        array_push($sql_join->parameters, "i", $datefield, "i", $datefield, "s", $val, "s", $val);
                    } else {
                        $sql_join->sql .= " JOIN resource_node rnd" . $c . " ON rnd" . $c . ".resource=r.ref JOIN node dn" . $c . " ON dn" . $c . ".ref=rnd" . $c . ".node AND dn" . $c . ".resource_type_field = ?";
                        array_push($sql_join->parameters, "i", $datefield);

                        $sql_filter->sql .= ($sql_filter->sql != "" ? " AND " : "") . "dn" . $c . ".name like ?";
                        array_push($sql_filter->parameters, "s", $val . "%");
                    }


                    // Find where the searched value is LIKE the range values
                } elseif (in_array($kw[0], array("basicday","basicmonth","basicyear"))) {
                    $c++;
                    if (!isset($datefieldjoin)) {
                        // We only want to join once to the date_field
                        $sql_join->sql .= " JOIN resource_node rdnf" . $c . " ON rdnf" . $c . ".resource=r.ref JOIN node rdn" . $c . " ON rdnf" . $c  . ".node=rdn" . $c . ".ref AND rdn" . $c . ".resource_type_field = ?";
                        $datefieldjoin = $c;
                        array_push($sql_join->parameters, "i", $date_field);
                    }

                    if ('basicday' == $kw[0]) {
                        $date_parts['day'] = $keystring;
                    } elseif ('basicmonth' == $kw[0]) {
                        $date_parts['month'] = $keystring;
                    } elseif ('basicyear' == $kw[0]) {
                        $date_parts['year'] = $keystring;
                    }
                } elseif (count($datefieldinfo) && substr($keystring, 0, 5) == "range") {
                    # Additional date range filtering
                    $c++;
                    $rangestring = substr($keystring, 5);
                    if (strpos($rangestring, "start") !== false) {
                        $rangestartpos = strpos($rangestring, "start") + 5;
                        $rangestart = str_replace(" ", "-", substr($rangestring, $rangestartpos, strpos($rangestring, "end") ? strpos($rangestring, "end") - $rangestartpos : 10));
                    }
                    if (strpos($keystring, "end") !== false) {
                        $rangeend = str_replace(" ", "-", $rangestring);
                        $rangeend = substr($rangeend, strpos($rangeend, "end") + 3, 10) . " 23:59:59";
                    }

                    // Find where the start value or the end value  is between the range values
                    if (isset($rangestart)) {
                        // Need to check for a date greater than the start date
                        $sql_join->sql .= " JOIN resource_node drrn" . $c . "s ON drrn" . $c . "s.resource=r.ref JOIN node drn" . $c . "s ON drn" . $c . "s.ref=drrn" . $c . "s.node AND drn" . $c . "s.resource_type_field = ? AND drn" . $c . "s.name>= ? ";
                        array_push($sql_join->parameters, "i", $fieldinfo['ref'], "s", $rangestart);
                    }
                    if (isset($rangeend)) {
                        // Need to check for a date earlier than the end date
                        $sql_join->sql .= " JOIN resource_node drrn" . $c . "e ON drrn" . $c . "e.resource=r.ref JOIN node drn" . $c . "e ON drn" . $c . "e.ref=drrn" . $c . "e.node AND drn" . $c . "e.resource_type_field = ? AND drn" . $c . "e.name <= ? ";
                        array_push($sql_join->parameters, "i", $fieldinfo['ref'], "s", $rangeend);
                    }
                }
                $keywordprocessed = true;
            } elseif ($field_short_name_specified && substr($keystring, 0, 8) == "numrange" && !$quoted_string && !$ignore_filters && isset($fieldinfo['type']) && $fieldinfo['type'] == 0) {
                // Text field numrange search ie mynumberfield:numrange1|1234 indicates that mynumberfield needs a numrange search for 1 to 1234.
                $c++;
                $rangefieldinfo = ps_query("SELECT ref FROM resource_type_field WHERE name = ? AND type IN (0)", ["s",$fieldname], "schema");
                $rangefieldinfo = $rangefieldinfo[0];
                $rangefield = $rangefieldinfo["ref"];
                $rangestring = substr($keystring, 8);
                $minmax = explode("|", $rangestring);
                $min = str_replace("neg", "-", $minmax[0]);
                if (isset($minmax[1])) {
                    $max = str_replace("neg", "-", $minmax[1]);
                } else {
                    $max = '';
                }
                if ($max != '' || $min != '') {
                    // At least the min or max should be set
                    if ($max == '' || $min == '') {
                        // if only one number is entered, do a direct search
                        if ($sql_filter->sql != "") {
                            $sql_filter->sql .= " AND ";
                        }
                        $sql_filter->sql .= "rnn" . $c . ".name = ? ";
                        array_push($sql_filter->parameters, "d", max($min, $max));
                    } else {
                        // else use min and max values as a range search
                        if ($sql_filter->sql != "") {
                            $sql_filter->sql .= " AND ";
                        }
                        $sql_filter->sql .= "rnn" . $c . ".name >= ? ";
                        array_push($sql_filter->parameters, "d", $min);
                        if ($sql_filter->sql != "") {
                            $sql_filter->sql .= " AND ";
                        }
                        $sql_filter->sql .= "rnn" . $c . ".name <= ? ";
                        array_push($sql_filter->parameters, "d", $max);
                    }
                }

                $sql_join->sql .= " JOIN resource_node rrn" . $c . " ON rrn" . $c . ".resource=r.ref LEFT JOIN node rnn" . $c . " ON rnn" . $c . ".ref=rrn" . $c . ".node AND rnn" . $c . ".resource_type_field = ?";

                array_push($sql_join->parameters, "i", $rangefield);
                $keywordprocessed = true;
            } elseif (
                $field_short_name_specified
                && !$ignore_filters
                && isset($fieldinfo['type'])
                && in_array($fieldinfo['type'], $FIXED_LIST_FIELD_TYPES)
            ) {
                // Convert legacy fixed list field search to new format for nodes (@@NodeID)
                // We've searched using a legacy format (ie. fieldShortName:keyword), try and convert it to @@NodeID
                $field_nodes      = get_nodes($fieldinfo['ref'], null, false, true);

                // Check if multiple nodes have been specified for an OR search
                $keywords_expanded = explode(';', $keystring);
                $nodeorcount = count($node_bucket);
                foreach ($keywords_expanded as $keyword_expanded) {
                    debug("keyword_expanded:  " . $keyword_expanded);
                    $field_node_index = array_search(mb_strtolower(i18n_get_translated($keyword_expanded)), array_map('i18n_get_translated', array_map('mb_strtolower', array_column($field_nodes, 'name'))));
                    // Take the ref of the node and add it to the node_bucket as an OR
                    if (false !== $field_node_index) {
                        $node_bucket[$nodeorcount][] = $field_nodes[$field_node_index]['ref'];
                        $quoted_field_match = true; // String has been resolved to a node so even if it is quoted we don't need to process it as a quoted string now
                        $keywordprocessed = true;
                    }
                }
            } elseif (
                !$quoted_string
                && preg_match(
                    '/^                   # Start of the string
                    [^-]                  # Not a negative keyword search
                    (?!.*[\*;])           # Negative lookahead: Ensure no asterisk (*) or semi colon exists in the string
                    (?=                   # Positive lookahead: Ensure at least one special character exists
                        [^ \t\n]*         # Any number of characters that are not space, tab, or newline
                        [!@#$%^&()_+\-=\[\]{}\'"\\,.<>\/?] # One special character from this set
                    )
                    [^ \t\n]*             # Match any number of characters that are not space, tab, or newline
                    $                     # End of the string
                    /x',
                    $keyword
                ) === 1
            ) {
                // Quote field specific keywords that contain special characters and no spaces unless a wildcard is present
                $keyword = "\"$keyword\"";
                $quoted_string = true;
            }

            if ($field_short_name_specified) { // Need this also for string matching in a named text field
                $keyword = $keystring;
                $search_field_restrict = $fieldinfo['ref'];
            }

            if (!$quoted_string && !$keywordprocessed && !($field_short_name_specified && hook('customsearchkeywordfilter', null, array($kw)))) { // Need this also for string matching in a named text field
                // Normal keyword
                //
                // Searches all fields that the user has access to
                // If ignoring field specifications then remove them.
                $keywords_expanded = explode(';', $keyword);
                $keywords_expanded_or = count($keywords_expanded) > 1;
                # Omit resources containing this keyword?
                $omit = false;
                if (substr($keyword, 0, 1) == "-") {
                    $omit = true;
                    $keyword = substr($keyword, 1);
                }

                # Search for resources with an empty field, ex: !empty18  or  !emptycaption
                $empty = false;
                if (substr($keyword, 0, 6) == "!empty") {
                    $nodatafield = str_replace("!empty", "", $keyword);

                    if (!is_numeric($nodatafield)) {
                        $nodatafield = ps_value("SELECT ref value FROM resource_type_field WHERE name = ?", ["i",$nodatafield], "", "schema");
                    }

                    if ($nodatafield == "" || !is_numeric($nodatafield)) {
                        exit('invalid !empty search');
                    }
                    $empty = true;
                }

                if (in_array($keyword, $noadd)) { # skip common words that are excluded from indexing
                    debug("do_search(): skipped common word: {$keyword}");
                } else {
                    // ********************************************************************************
                    //                                                                 Handle wildcards
                    // ********************************************************************************
                    $wildcards = false;
                    if (strpos($keyword, "*") !== false || $wildcard_always_applied) {
                        if ($wildcard_always_applied && strpos($keyword, "*") === false) {
                            # Suffix asterisk if none supplied and using $wildcard_always_applied mode.
                            $keyword = $keyword . "*";
                        }
                        $wildcards = true;
                    }

                    $keyref = resolve_keyword(str_replace('*', '', $keyword), false, true, !$quoted_string); # Resolve keyword. Ignore any wildcards when resolving. We need wildcards to be present later but not here.
                    if ($keyref === false) {
                        if ($stemming) {
                            // Attempt to find match for original keyword
                            $keyref = resolve_keyword(str_replace('*', '', $keyword), false, true, false);
                        }

                        if ($keyref === false) {
                            if ($keywords_expanded_or) {
                                $alternative_keywords = array();
                                foreach ($keywords_expanded as $keyword_expanded) {
                                    $alternative_keyword_keyref = resolve_keyword($keyword_expanded, false, true, true);
                                    if ($alternative_keyword_keyref === false) {
                                        continue;
                                    }

                                    $alternative_keywords[] = $alternative_keyword_keyref;
                                }

                                if (count($alternative_keywords) > 0) {
                                    // Multiple alternative keywords
                                    $alternative_keywords_sql = new PreparedStatementQuery();
                                    $alternative_keywords_sql->sql = " OR nk[union_index].keyword IN (" . ps_param_insert(count($alternative_keywords)) . ")";
                                    $alternative_keywords_sql->parameters = ps_param_fill($alternative_keywords, "i");
                                    debug("do_search(): \$alternative_keywords_sql = {$alternative_keywords_sql->sql}, parameters = " . implode(",", $alternative_keywords_sql->parameters));
                                }
                            } elseif (strpos($keyword, "*") === false && preg_match('/\\s/', $search) !== 1) {
                                // Check keyword for defined separators and if found each part of the value is added as a keyword for checking. Not for wildcards with no spaces
                                $contains_separators = false;
                                foreach ($config_separators as $separator) {
                                    if (strpos($keyword, $separator) !== false) {
                                        $contains_separators = true;
                                    }
                                }
                                if ($contains_separators === true) {
                                    $keyword_split = split_keywords($keyword);

                                    if ($field_short_name_specified) {
                                        $keyword_split = array_map(prefix_value($fieldname . ":"), $keyword_split);
                                    }
                                    $keywords = array_merge($keywords, $keyword_split);
                                    continue;
                                }
                            }
                        }
                    }

                    if ($keyref === false && !$omit && !$empty && !$wildcards && !$field_short_name_specified && !$canskip) {
                        // ********************************************************************************
                        //                                                                     No wildcards
                        // ********************************************************************************

                        $fullmatch = false;
                        $soundex = resolve_soundex($keyword);
                        if ($soundex === false) {
                            # No keyword match, and no keywords sound like this word. Suggest dropping this word.
                            $suggested[$n] = "";
                        } else {
                            # No keyword match, but there's a word that sounds like this word. Suggest this word instead.
                            $suggested[$n] = "<i>" . $soundex . "</i>";
                        }
                    } else {
                        // ********************************************************************************
                        //                                                                  Found wildcards
                        // ********************************************************************************

                        // Multiple alternative keywords
                        $alternative_keywords_sql = new PreparedStatementQuery();
                        $alternative_keywords = array();
                        if ($keywords_expanded_or) {
                            foreach ($keywords_expanded as $keyword_expanded) {
                                $alternative_keyword_keyref = resolve_keyword($keyword_expanded, false, true, true);

                                if ($alternative_keyword_keyref === false) {
                                    continue;
                                }

                                $alternative_keywords[] = $alternative_keyword_keyref;
                            }

                            if (count($alternative_keywords) > 0) {
                                $alternative_keywords_sql->sql = " OR nk[union_index].keyword IN (" . ps_param_insert(count($alternative_keywords)) . ")";
                                $alternative_keywords_sql->parameters = ps_param_fill($alternative_keywords, "i");
                                debug("do_search(): \$alternative_keywords_sql = {$alternative_keywords_sql->sql}, parameters = " . implode(",", $alternative_keywords_sql->parameters));
                            }
                        }

                        if ($keyref === false) {
                            # make a new keyword
                            $keyref = resolve_keyword(str_replace('*', '', $keyword), true, true, false);
                        }
                        # Key match, add to query.
                        $c++;

                        $relatedsql = new PreparedStatementQuery();

                        # Add related keywords
                        $related = get_related_keywords($keyref);
                        if ($stemming) {
                            # Need to ensure we include related keywords for original string
                            $original_keyref = resolve_keyword(str_replace('*', '', $keyword), false, true, false);
                            if ($original_keyref && $original_keyref !== $keyref) {
                                $original_related = get_related_keywords($original_keyref);
                                if (count($original_related) > 0) {
                                    $original_related_kws = ps_array("SELECT keyword AS `value` FROM keyword WHERE ref IN (" . ps_param_insert(count($original_related)) . ")", ps_param_fill($original_related, "i"));

                                    $extra_related = array();
                                    foreach ($original_related_kws as $orig_related_kw) {
                                        $extrakeyword = GetStem(trim($orig_related_kw));
                                        $extra_related[] = resolve_keyword($extrakeyword, true, false, false);
                                    }
                                    $related = array_merge($related, $extra_related);
                                }
                            }
                        }
                        if (count($related) > 0) {
                            $relatedsql->sql .= " OR (nk[union_index].keyword IN (" . ps_param_insert(count($related)) . ")";
                            $relatedsql->parameters = array_merge($relatedsql->parameters, ps_param_fill($related, "i"));

                            if ($field_short_name_specified && isset($fieldinfo['ref'])) {
                                $relatedsql->sql .= " AND nk[union_index].node IN (SELECT ref FROM node WHERE resource_type_field = ? )";
                                $relatedsql->parameters[] = "i";
                                $relatedsql->parameters[] = $fieldinfo['ref'];
                            }
                            $relatedsql->sql .= ")";
                        }

                        # Form join
                        $sql_exclude_fields = hook("excludefieldsfromkeywordsearch");

                        if ($omit) {
                            # Exclude matching resources from query (omit feature)
                            if ($sql_filter->sql != "") {
                                $sql_filter->sql .= " AND ";
                            }

                            // ----- check that keyword does not exist via resource_node->node_keyword relationship -----

                            $sql_filter->sql .= "`r`.`ref` NOT IN (SELECT `resource` FROM `resource_node` JOIN `node_keyword` ON `resource_node`.`node`=`node_keyword`.`node`" .
                                " WHERE `resource_node`.`resource`=`r`.`ref` AND `node_keyword`.`keyword` = ?)";
                            array_push($sql_filter->parameters, "i", $keyref);
                        } else # Include in query
                            {
                            // --------------------------------------------------------------------------------
                            // Start of normal union for resource keywords
                            // --------------------------------------------------------------------------------

                            // // these restrictions apply to both !empty searches as well as normal keyword searches (i.e. both branches of next if statement)
                            $union_restriction_clause = new PreparedStatementQuery();
                            $skipfields = array();

                            if (!empty($sql_exclude_fields)) {
                                if ($wildcards) {
                                    $union_restriction_clause->sql = " AND resource_type_field NOT IN (" . ps_param_insert(count($sql_exclude_fields)) .  ")";
                                } else {
                                    $union_restriction_clause->sql .= " AND nk[union_index].node NOT IN (SELECT ref FROM node WHERE resource_type_field IN (" . ps_param_insert(count($sql_exclude_fields)) .  "))";
                                }
                                $union_restriction_clause->parameters = array_merge($union_restriction_clause->parameters, ps_param_fill($sql_exclude_fields, "i"));

                                $skipfields = explode(",", str_replace(array("'","\""), "", $sql_exclude_fields));
                            }

                            if (count($hidden_indexed_fields) > 0) {
                                if ($wildcards) {
                                    $union_restriction_clause->sql = " AND resource_type_field NOT IN (" . ps_param_insert(count($hidden_indexed_fields)) .  ")";
                                } else {
                                    $union_restriction_clause->sql .= " AND nk[union_index].node NOT IN (SELECT ref FROM node WHERE node.resource_type_field IN (" .  ps_param_insert(count($hidden_indexed_fields)) . "))";
                                }
                                $union_restriction_clause->parameters = array_merge($union_restriction_clause->parameters, ps_param_fill($hidden_indexed_fields, "i"));
                                $skipfields = array_merge($skipfields, $hidden_indexed_fields);
                            }
                            if (isset($search_field_restrict) && $search_field_restrict != "") {
                                // Search is looking for a keyword in a specified field
                                if ($wildcards) {
                                    $union_restriction_clause->sql .= " AND resource_type_field = ?";
                                } else {
                                    $union_restriction_clause->sql .= " AND nk[union_index].node IN (SELECT ref FROM node WHERE node.resource_type_field = ?)";
                                }
                                $union_restriction_clause->parameters = array_merge($union_restriction_clause->parameters, ["i",$search_field_restrict]);
                            }
                            if ($empty) {  // we are dealing with a special search checking if a field is empty
                                // First check user can see this field
                                if (in_array($nodatafield, $skipfields)) {
                                    // Not permitted to check this field, return false
                                    return false;
                                }

                                $selectedrestypes = [];
                                $restypes = trim((string)($restypes));
                                if ($restypes != "") {
                                    $selectedrestypes = explode(",", $restypes);
                                }

                                $restypesql = new PreparedStatementQuery();

                                $nodatafieldinfo = get_resource_type_field($nodatafield);
                                if (!$nodatafieldinfo) {   
                                    if (isset($_GET['search'])) {
                                        error_alert($lang['error_fieldidnotfound'],true);
                                        exit;
                                    }
                                    error_alert($lang['error_fieldidnotfound'],false);
                                    exit;
                                }
                                $nodatarestypes = trim((string)$nodatafieldinfo["resource_types"]);
                                if ($nodatarestypes != "") {
                                    $nodatarestypes = explode(",", $nodatarestypes);
                                } else {
                                    $nodatarestypes = [];
                                }

                                if ($nodatafieldinfo["global"] === 1) {
                                    // Global field empty search
                                    // Candidate resources are those which exist in selected resource types
                                    if (count($selectedrestypes) > 0) {
                                        $restypesql->sql = " AND r[union_index].resource_type IN (" . ps_param_insert(count($selectedrestypes)) . ") ";
                                        $restypesql->parameters = ps_param_fill($selectedrestypes, "i");
                                    }
                                } else {
                                    // Non-global field empty search
                                    // Candidate resources are those whose resource type is linked to the field and which exists in selected resource types
                                    if (count($selectedrestypes) > 0) {
                                        $candidaterestypes = array_intersect($nodatarestypes, $selectedrestypes);
                                    } else {
                                        $candidaterestypes = $nodatarestypes;
                                    }
                                    $restypesql->sql = " AND r[union_index].resource_type IN (" . ps_param_insert(count($candidaterestypes)) . ") ";
                                    $restypesql->parameters = ps_param_fill($candidaterestypes, "i");
                                }

                                // Check that nodes are empty
                                $union = new PreparedStatementQuery();
                                $union->sql = "SELECT ref AS resource, [bit_or_condition] 1 AS score FROM resource r[union_index] WHERE r[union_index].ref NOT IN
                                    (
                                    SELECT rn.resource FROM
                                    node n
                                    RIGHT JOIN resource_node rn ON rn.node=n.ref
                                    WHERE  n.resource_type_field = ? $restypesql->sql
                                    GROUP BY rn.resource
                                    )";
                                $union->parameters = array_merge(["i",$nodatafield], $restypesql->parameters);
                                $sql_keyword_union[] = $union;
                                $sql_keyword_union_criteria[] = "`h`.`keyword_[union_index]_found`";
                                $sql_keyword_union_aggregation[] = "BIT_OR(`keyword_[union_index]_found`) AS `keyword_[union_index]_found`";
                                $sql_keyword_union_or[] = false;
                            } elseif ($wildcards) {
                                $union = new PreparedStatementQuery();
                                preg_match_all('/
                                (?=             # Positive lookahead
                                    [a-zA-Z0-9] # Match a single character that is a letter or digit
                                )
                                [a-zA-Z0-9*]{3,}   # Match a group of 3 or more characters that are letters, digits, or asterisks
                                /x', $keyword, $matches);
                                if (count($matches) > 0) {
                                    $stop_words_query = new PreparedStatementQuery();
                                    foreach ($matches[0] as $match) {
                                        if ($stop_words_query->sql == "") {
                                            $stop_words_query->sql =
                                            "SELECT value
                                            FROM INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD
                                            WHERE value LIKE ?";
                                        } else {
                                            $stop_words_query->sql .= " OR value LIKE ?";
                                        }
                                        $stop_words_query->parameters = array_merge(
                                            $stop_words_query->parameters,
                                            ["s", str_replace("*","%" , $match)]
                                        );
                                    }
                                    $stop_words = ps_array($stop_words_query->sql, $stop_words_query->parameters);
                                }
                                if (
                                    substr($keyword, 0, 1) != "*"
                                    && strlen(trim($keyword, '*')) >= 3
                                    && preg_match('/[-+@<>().;]/', $keyword) !== 1
                                    && empty($stop_words)
                                ) {
                                    // Use fulltext search as a preference, but not if
                                    // there is a leading wildcard
                                    // or the search is too short
                                    // or the search contains any of +,-,@,<,>,(,),.;
                                    // or contains any words InnoDB considers a stop word
                                    $union->sql = "
                                            SELECT resource, [bit_or_condition] hit_count AS score
                                                FROM resource_node rn[union_index]
                                                WHERE rn[union_index].node IN
                                                    (SELECT ref FROM `node` WHERE MATCH(name) AGAINST (? IN BOOLEAN MODE) "
                                                . $union_restriction_clause->sql . ")
                                            GROUP BY resource " .
                                        ($non_field_keyword_sql->sql != "" ? $non_field_keyword_sql->sql : "");
                                    $keyword = "+" . $keyword;
                                } elseif (
                                    preg_match('/\W/', str_replace("*", "", $keyword)) !== 1
                                ) {
                                    // Use a LIKE search, unless there is any whitespace
                                    $keyword = str_replace("*", "%", $keyword);
                                    $union->sql = "
                                            SELECT resource, [bit_or_condition] hit_count AS score
                                                FROM resource_node rn[union_index]
                                                WHERE rn[union_index].node IN
                                                    (SELECT n.ref
                                                        FROM keyword k
                                                        JOIN node_keyword nk ON nk.keyword=k.ref
                                                        JOIN `node` n ON n.ref=nk.node
                                                        WHERE k.keyword LIKE ? "
                                                . $union_restriction_clause->sql . ")
                                            GROUP BY resource ";
                                } else {
                                    // Use RLIKE to search between word boundaries in the node names
                                    $union->sql = 
                                            "SELECT resource, [bit_or_condition] hit_count AS score
                                                FROM resource_node rn[union_index]
                                                WHERE rn[union_index].node IN
                                                    (SELECT ref 
                                                        FROM `node` 
                                                        WHERE ";

                                    if (preg_match('/(a-zA-Z0-9]{3,})/', $keyword, $matches)) {
                                        foreach($matches as $match) {
                                            if (!in_array($match, $stop_words)) {
                                                // Full text matching is used to reduce the rows checked by regex where possible
                                                $union->parameters = array_merge(['s',$match], $union->parameters);
                                                $union->sql .= "MATCH(name) AGAINST (? IN BOOLEAN MODE) AND ";
                                            }
                                        }
                                    }

                                    $union->sql .= "name RLIKE ? "
                                                . $union_restriction_clause->sql . ")
                                                GROUP BY resource " .
                                                ($non_field_keyword_sql->sql != "" ? $non_field_keyword_sql->sql : "");

                                    $keyword = prepare_regex_search_string($keyword);
                                }
                                
                                $union->parameters = array_merge(
                                    $union->parameters,
                                    ["s",$keyword],
                                    $union_restriction_clause->parameters
                                );

                                if ($non_field_keyword_sql->sql != "") {
                                    $union->parameters = array_merge($union->parameters, $non_field_keyword_sql->parameters);
                                }
                                $sql_keyword_union[] = $union;
                                $sql_keyword_union_criteria[] = "`h`.`keyword_[union_index]_found`";
                                $sql_keyword_union_or[] = "";
                                $sql_keyword_union_aggregation[] = "BIT_OR(`keyword_[union_index]_found`) AS `keyword_[union_index]_found`";
                            } else // we are dealing with a standard keyword match
                                {
                                // ----- resource_node -> node_keyword sub query -----
                                $union = new PreparedStatementQuery();

                                $union->sql = " SELECT resource, [bit_or_condition] hit_count AS score
                                                        FROM resource_node rn[union_index]
                                                        WHERE rn[union_index].node IN
                                                            (SELECT node
                                                                FROM `node_keyword` nk[union_index]
                                                                WHERE ((nk[union_index].keyword = ? " . $relatedsql->sql . ") " .  $union_restriction_clause->sql . ")" .
                                                            ($alternative_keywords_sql->sql != "" ? ($alternative_keywords_sql->sql . $union_restriction_clause->sql) : "" ) .
                                            ") GROUP BY resource " .
                                                ($non_field_keyword_sql->sql != "" ? $non_field_keyword_sql->sql : "") ;

                                $union->parameters = array_merge(["i",$keyref], $relatedsql->parameters, $union_restriction_clause->parameters);
                                if ($alternative_keywords_sql->sql != "") {
                                    $union->parameters = array_merge($union->parameters, $alternative_keywords_sql->parameters, $union_restriction_clause->parameters);
                                }
                                if ($non_field_keyword_sql->sql != "") {
                                    $union->parameters = array_merge($union->parameters, $non_field_keyword_sql->parameters);
                                }

                                $sql_keyword_union[] = $union;

                                // ---- end of resource_node -> node_keyword sub query -----
                                $sql_keyword_union_criteria[] = "`h`.`keyword_[union_index]_found`";
                                $sql_keyword_union_aggregation[] = "BIT_OR(`keyword_[union_index]_found`) AS `keyword_[union_index]_found`";

                                $sql_keyword_union_or[] = $keywords_expanded_or;

                                // Log this
                                if ($stats_logging && !$go) {
                                    $keywords_used[] = $keyref;
                                }
                            } // End of standard keyword match
                        } // end if not omit
                    } // end found wildcards
                } // end handle wildcards
            } // end normal keyword
        } // End of if not quoted string and end of check if special search
        if ($quoted_string && !$quoted_field_match) {
            $quotedfieldid = "";
            // This keyword is a quoted string, split into keywords but don't preserve quotes this time
            if ($field_short_name_specified && isset($fieldinfo['ref'])) {
                  // We have already parsed the keyword when looking for a node, get string and then filter on this field
                  $quotedkeywords = split_keywords($keystring);
                  $quotedfieldid = $fieldinfo['ref'];
            } else {
                $quotedkeywords = split_keywords(substr($keyword, 1, -1));
            }
                $omit = false;
            if (substr($keyword, 0, 1) == "-") {
                $omit = true;
                $keyword = substr($keyword, 1);
            }

                $qk = 1; // Set the counter to the first keyword
                $last_key_offset = 1;
                $fixedunion = new PreparedStatementQuery();
                $fixedunioncondition = new PreparedStatementQuery();
            foreach ($quotedkeywords as $quotedkeyword) {
                global $noadd, $wildcard_always_applied;
                if (in_array($quotedkeyword, $noadd)) { # skip common words that are excluded from indexing
                    # Support skipped keywords - if the last keyword was skipped (listed in $noadd), increase the allowed position from the previous keyword. Useful for quoted searches that contain $noadd words, e.g. "black and white" where "and" is a skipped keyword.
                    ++$last_key_offset;
                } else {
                    $keyref = resolve_keyword($quotedkeyword, false, true, false); # Resolve keyword.
                    if ($keyref === false) {
                        # make a new keyword
                        $keyref = resolve_keyword($quotedkeyword, true, true, false);
                    }

                    $union_restriction_clause = new PreparedStatementQuery();

                    if (!empty($sql_exclude_fields)) {
                        $union_restriction_clause->sql .= " AND nk_[union_index]_" . $qk . ".node NOT IN (SELECT ref FROM node WHERE resource_type_field IN (" . ps_param_insert(count($sql_exclude_fields)) . "))";
                        $union_restriction_clause->parameters = array_merge($union_restriction_clause->parameters, ps_param_fill($sql_exclude_fields, "i"));
                    }

                    if (count($hidden_indexed_fields) > 0) {
                        $union_restriction_clause->sql .= " AND nk_[union_index]_" . $qk . ".node NOT IN (SELECT ref FROM node WHERE resource_type_field IN (" .  ps_param_insert(count($hidden_indexed_fields)) . "))";
                        $union_restriction_clause->parameters = array_merge($union_restriction_clause->parameters, ps_param_fill($hidden_indexed_fields, "i"));
                    }

                    if ($quotedfieldid != "") {
                        $union_restriction_clause->sql .= " AND nk_[union_index]_" . $qk . ".node IN (SELECT ref FROM node WHERE resource_type_field = ? )";
                        $union_restriction_clause->parameters = array_merge($union_restriction_clause->parameters, ["i",$quotedfieldid]);
                    }

                    if ($qk == 1) {
                        // Add code to find matching nodes in resource_node
                        $fixedunion->sql = " SELECT rn_[union_index]_" . $qk . ".resource, [bit_or_condition] rn_[union_index]_" . $qk . ".hit_count AS score FROM resource_node rn_[union_index]_" . $qk .
                            " LEFT OUTER JOIN `node_keyword` nk_[union_index]_" . $qk . " ON rn_[union_index]_" . $qk . ".node=nk_[union_index]_" . $qk . ".node AND (nk_[union_index]_" . $qk . ".keyword = ? " .  ")";
                        $fixedunion->parameters = array_merge($fixedunion->parameters, ["i",$keyref]);

                        $fixedunioncondition->sql = "nk_[union_index]_" . $qk . ".keyword = ? " . $union_restriction_clause->sql;
                        $fixedunioncondition->parameters = array_merge(["i",$keyref], $union_restriction_clause->parameters);
                    } else {
                        # For keywords other than the first one, check the position is next to the previous keyword.
                        # Also check these occurances are within the same field.
                            $fixedunion->sql .= " JOIN `node_keyword` nk_[union_index]_" . $qk . " ON nk_[union_index]_" . $qk . ".node = nk_[union_index]_" . ($qk - 1) . ".node AND nk_[union_index]_" . $qk . ".keyword = ? AND  nk_[union_index]_" . $qk . ".position=nk_[union_index]_" . ($qk - 1) . ".position+" . $last_key_offset ;
                            array_push($fixedunion->parameters, "i", $keyref);
                    }

                        $last_key_offset = 1;
                        $qk++;
                } // End of if keyword not excluded (not in $noadd array)
            } // End of each keyword in quoted string

            if (trim($fixedunioncondition->sql) != "") {
                if ($omit) {# Exclude matching resources from query (omit feature)
                    if ($sql_filter->sql != "") {
                        $sql_filter->sql .= " AND ";
                    }

                    $fixedunion->sql = str_replace("[bit_or_condition]", "", " r.ref NOT IN (SELECT resource FROM (" . $fixedunion->sql . " WHERE " . $fixedunioncondition->sql . ") qfilter[union_index]) "); # Instead of adding to the union, filter out resources that do contain the quoted string.
                    $sql_filter->sql .= str_replace("[union_index]", $n+1, $fixedunion->sql);

                    $sql_filter->parameters = array_merge($sql_filter->parameters, $fixedunion->parameters, $fixedunioncondition->parameters);
                } elseif (is_a($fixedunion, "PreparedStatementQuery")) {
                    $addunion = new PreparedStatementQuery();
                    $addunion->sql = $fixedunion->sql . " WHERE " . $fixedunioncondition->sql . " GROUP BY resource ";
                    $addunion->parameters = array_merge($fixedunion->parameters, $fixedunioncondition->parameters);
                    $sql_keyword_union[] = $addunion;
                    $sql_keyword_union_aggregation[] = "BIT_OR(`keyword_[union_index]_found`) AS `keyword_[union_index]_found` ";
                    $sql_keyword_union_or[] = false;
                    $sql_keyword_union_criteria[] = "`h`.`keyword_[union_index]_found`";
                }
            }
                $c++;
        }
    } // end keywords expanded loop
    if (isset($datefieldjoin)) {
        $date_string = sprintf(
            "%s-%s-%s",
            $date_parts['year']  ?? '____',
            $date_parts['month'] ?? '__',
            $date_parts['day']   ?? '__'
        ) . '%';
        $sql_filter->sql .= ($sql_filter->sql != "" ? " AND " : "") . "rdn" . $datefieldjoin . ".name like ? ";
        array_push($sql_filter->parameters, "s", $date_string);
    }
} // end keysearch if
