<?php

# ---------------------------------------------------------------
    # Keyword union assembly.
    # Use UNIONs for keyword matching instead of the older JOIN technique - much faster
    # Assemble the new join from the stored unions
    # ---------------------------------------------------------------
if (count($sql_keyword_union) > 0) {
    $union_sql_arr = [];
    $union_sql_params = [];

    for ($i = 1; $i <= count($sql_keyword_union); $i++) {
        $bit_or_condition = "";
        for ($y = 1; $y <= count($sql_keyword_union); $y++) {
            if ($i == $y) {
                $bit_or_condition .= " TRUE AS `keyword_{$y}_found`, ";
            } else {
                $bit_or_condition .= " FALSE AS `keyword_{$y}_found`,";
            }
        }
        $sql_keyword_union[($i - 1)]->sql = str_replace('[bit_or_condition]', $bit_or_condition, $sql_keyword_union[($i - 1)]->sql);
        $sql_keyword_union[($i - 1)]->sql = str_replace('[union_index]', $i, $sql_keyword_union[($i - 1)]->sql);
        $sql_keyword_union[($i - 1)]->sql = str_replace('[union_index_minus_one]', ($i - 1), $sql_keyword_union[($i - 1)]->sql);

        $union_sql_arr[] = $sql_keyword_union[$i - 1]->sql;
        $union_sql_params = array_merge($union_sql_params, $sql_keyword_union[$i - 1]->parameters);
    }

    for ($i = 1; $i <= count($sql_keyword_union_criteria); $i++) {
        $sql_keyword_union_criteria[($i - 1)] = str_replace('[union_index]', $i, $sql_keyword_union_criteria[($i - 1)]);
        $sql_keyword_union_criteria[($i - 1)] = str_replace('[union_index_minus_one]', ($i - 1), $sql_keyword_union_criteria[($i - 1)]);
    }

    for ($i = 1; $i <= count($sql_keyword_union_aggregation); $i++) {
        $sql_keyword_union_aggregation[($i - 1)] = str_replace('[union_index]', $i, $sql_keyword_union_aggregation[($i - 1)]);
    }

    $sql_join->sql .= " JOIN (
            SELECT resource,sum(score) AS score,
            " . join(", ", $sql_keyword_union_aggregation) . " from
            (" . join(" union ", $union_sql_arr) . ") AS hits GROUP BY resource) AS h ON h.resource=r.ref ";

    $sql_join->parameters = array_merge($sql_join->parameters, $union_sql_params);
    if ($sql_filter->sql != "") {
        $sql_filter->sql .= " AND ";
    }

    if (count($sql_keyword_union_or) != count($sql_keyword_union_criteria)) {
        debug("Search error - union criteria mismatch");
        return "ERROR";
    }

    $sql_filter->sql .= "(";

    for ($i = 0; $i < count($sql_keyword_union_or); $i++) {
        // Builds up the string of conditions that will be added when joining to the $sql_keyword_union_criteria
        if ($i == 0) {
            $sql_filter->sql .= $sql_keyword_union_criteria[$i];
            continue;
        }

        if ($sql_keyword_union_or[$i] != $sql_keyword_union_or[$i - 1]) {
            $sql_filter->sql .= ') AND (' . $sql_keyword_union_criteria[$i];
            continue;
        }

        if ($sql_keyword_union_or[$i]) {
            $sql_filter->sql .= ' OR ';
        } else {
            $sql_filter->sql .= ' AND ';
        }

        $sql_filter->sql .= $sql_keyword_union_criteria[$i];
    }

    $sql_filter->sql .= ")";

    # Use amalgamated resource_keyword hitcounts for scoring (relevance matching based on previous user activity)
    $score = "h.score";
}
