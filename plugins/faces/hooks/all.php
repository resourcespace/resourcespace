<?php

include_once dirname(__FILE__, 2) . '/include/faces_functions.php';

/**
 * Hook that enables special search syntax to find resources with visually similar faces to a given resource.
 *
 * This hook listens for the special search syntax `!face<ID>`, where `<ID>` is the `ref` of a face
 * in the `resource_face` table. It queries an external Python service via HTTP to retrieve a list
 * of resource references with matching faces above a configurable similarity threshold. The results
 * are then integrated into ResourceSpace’s standard search mechanism.
 *
 * @param string $search        The search string input, expected to be in the format '!face<ID>' if this is a search we're interested in.
 * @param object $select        An object containing the SQL SELECT clause and its parameters.
 * @param object $sql_join      An object containing any necessary SQL JOIN clauses and parameters.
 * @param object $sql_filter    An object containing additional SQL filter conditions and parameters.
 *
 * @return PreparedStatementQuery|false  Returns a prepared SQL query to fetch matching resources if
 *                                       the search matches the `!face` pattern; otherwise, returns false.
 *
 * @global string $faces_service_endpoint  Endpoint URL of the Python face recognition service.
 * @global float  $faces_match_threshold   Similarity threshold for face matches (default 0.3).
 * @global string $mysql_db                Name of the current MySQL database (for namespacing service queries).
 */
function HookFacesAllAddspecialsearch($search, $select, $sql_join, $sql_filter)
{
    global $faces_service_endpoint, $faces_match_threshold;

    if (substr($search, 0, 5) == '!face') {
        $function = "find_similar_faces";
        $face = substr($search, 5);
    } else {
        return null;
    }

    $faces_service_call = $faces_service_endpoint . "/" . $function;
    global $mysql_db;

    // Send search to Python service
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $faces_service_call);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Connection: keep-alive',
        'Expect:' // Prevents "100-continue" delay
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'db' => $mysql_db,
        'ref' => (int)$face,
        'threshold' => $faces_match_threshold,
        'k' => 200
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Connection: keep-alive',
        'Expect:'
    ]);

    $start_time = microtime(true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $end_time = microtime(true);
    $query_time = round(($end_time - $start_time) * 1000);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        echo "Error from faces_service (HTTP $http_code)\n";
        exit(1);
    }

    $results = json_decode($response, true);
    if (!is_array($results)) {
        echo "Invalid response from faces_service.\n";
        exit(1);
    }

    $ids = array_column($results, 'resource');

    // No results - we must still run a query but one that returns no results.
    if (count($ids) == 0) {
        $ids = [-1];
    }

    $in_sql = ps_param_insert(count($ids));
    $params = ps_param_fill($ids, "i");
    $sql = new PreparedStatementQuery();
    $sql->sql = "SELECT DISTINCT r.hit_count score, $select->sql FROM resource r " . $sql_join->sql . " WHERE r.ref > 0 AND r.ref in ($in_sql) AND " . $sql_filter->sql . " ORDER BY FIELD(r.ref, $in_sql)";
    $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $params, $sql_filter->parameters, $params);
    return $sql;
}


/**
 * Runs face detection and tagging automatically after preview creation, if configured.
 *
 * This function is triggered after a resource's preview image has been generated.
 * If configured, it will perform face detection and/or tagging on the main resource file
 * (ignoring alternative files). Progress messages are displayed during processing.
 *
 * @param int  $resource    The resource reference ID that has just had previews created.
 * @param int  $alternative The alternative file ID, or -1 if processing the main resource.
 * @param bool $generate_all Flag to indicate if hook has been triggered during full preview creation process
 *
 * @return void
 */
function HookFacesAllAfterpreviewcreation(int $resource, int $alternative, bool $generate_all = false): void
{
    global $faces_detect_on_upload, $faces_tag_on_upload, $lang;

    if ($alternative === -1 && $faces_detect_on_upload && $generate_all) {
        // Nothing to do for alternatives; face processing is for the main file only.
        // Detect images on upload if configured
        set_processing_message($lang["faces-detecting"] . " " . $resource);
        faces_detect($resource, true);
    }
    
    if ($alternative === -1 && $faces_tag_on_upload) {
        // Nothing to do for alternatives; face processing is for the main file only.
        // Tag images on upload if configured
        set_processing_message($lang["faces-tagging"] . " " . $resource);
        faces_tag($resource);
    }
}

/**
 * Add face detection to the cron.
 *
 * @return void
 */
function HookFacesAllCron()
{
    faces_detect_missing();
}
