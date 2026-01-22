<?php
include_once dirname(__FILE__, 2) . '/include/clip_functions.php';

function HookClipAllAddspecialsearch($search, $select, $sql_join, $sql_filter)
{
    global $clip_search_cutoff, $clip_similar_cutoff, $clip_duplicate_cutoff, $clip_results_limit_search, $clip_results_limit_similar, $clip_service_url, $clip_query_time, $clip_enable_full_duplicate_search;
    if (substr($search, 0, 11) == '!clipsearch') {
        $function = "search";
        $search = substr($search, 12);
        $search = "A photo of a " . $search;
        $min_score = $clip_search_cutoff;
    } elseif (substr($search, 0, 12) == '!clipsimilar') {
        $function = "search";
        $resource = substr($search, 12);
        if (!is_numeric($resource)) {
            return false;
        }
        $min_score = $clip_similar_cutoff;
    } elseif (substr($search, 0, 13) == '!clipspecific') {
        $function = "search";
        $ref = substr($search, 13);
        if (!is_numeric($ref)) {
            return false;
        }
        $min_score = $clip_similar_cutoff;
    } elseif (substr($search, 0, 15) == '!clipduplicates' && $clip_enable_full_duplicate_search) {
        $function = "duplicates";
        $min_score = $clip_duplicate_cutoff;
    } elseif (substr($search, 0, 14) == '!clipduplicate') {
        $function = "search";
        $resource = substr($search, 14);
        if (!is_numeric($resource)) {
            return false;
        }
        $min_score = $clip_duplicate_cutoff;

        if (checkperm("a")) {
            // Admin only - show resources in all states
            $sql_filter->parameters = [];
            $sql_filter->sql = "true";
        }
    } else {
        return null;
    }

    $clip_service_call = $clip_service_url . "/" . $function;
    global $mysql_db;

    // Send search to Python service
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $clip_service_call);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Connection: keep-alive',
        'Expect:' // Prevents "100-continue" delay
    ]);


    if ($function == "search" && !isset($resource) && !isset($ref)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'db' => $mysql_db,
            'text' => $search,
            'top_k' => $clip_results_limit_search
        ]);
    }
    if ($function == "search" && isset($resource)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'db' => $mysql_db,
            'resource' => $resource,
            'top_k' => $clip_results_limit_similar
        ]);
    }
    if ($function == "search" && isset($ref)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'db' => $mysql_db,
            'ref' => $ref,
            'top_k' => $clip_results_limit_similar
        ]);
    }
    if ($function == "duplicates") {
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'db' => $mysql_db,
            'threshold' => $clip_duplicate_cutoff
        ]);
    }

    $start_time = microtime(true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $end_time = microtime(true);
    $clip_query_time = round(($end_time - $start_time) * 1000);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        echo "Error from CLIP service (HTTP $http_code)\n";
        exit(1);
    }

    $results = json_decode($response, true);
    if (!is_array($results)) {
        echo "Invalid response from CLIP service.\n";
        exit(1);
    }

    debug('clipsearch: ' . count($results) . ' results found for search phrase: "' . $search . '"');

    // Filter out results with score below the threshold
    $results = array_filter($results, static function ($result) use ($min_score) {
        return isset($result['score']) && $result['score'] >= $min_score;
    });

    debug('clipsearch: ' . count($results) . ' results found for search phrase: "' . $search . '" after filtering by $min_score: ' . $min_score);

    // Fetch titles from the resource table
    $ids = array_column($results, 'resource');

    debug('clipsearch: Resources found: ' . implode(', ', $ids));

    // No results - we must still run a query but one that returns no results.
    if (count($ids) == 0) {
        $ids = [-1];
    }

    $in_sql = ps_param_insert(count($ids));
    $params = ps_param_fill($ids, "i");
    $clipsql = new PreparedStatementQuery();
    $clipsql->sql = "SELECT DISTINCT r.hit_count score, $select->sql FROM resource r " . $sql_join->sql . " WHERE r.ref > 0 AND r.ref in ($in_sql) AND " . $sql_filter->sql . " ORDER BY FIELD(r.ref, $in_sql)";
    $clipsql->parameters = array_merge($select->parameters, $sql_join->parameters, $params, $sql_filter->parameters, $params);
    return $clipsql;
}

function HookClipAllSearch_pipeline_setup($search, $select, $sql_join, $sql_filter)
{
    if (substr($search, 0, 11) != '!clipsearch' && (substr($search, 0, 12) != '!clipsimilar')) {
        return false;
    }
    global $keysearch;
    $keysearch = false; // Disable keyword searching if using natural language search
}


function HookClipAllSearchbarafterbuttons()
{
    global $lang,$baseurl;
    if (checkperm("clip-sb")) {
        return false;
    }
    ?>
    <p><i aria-hidden="true" class="icon-brain"></i>&nbsp;<a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl ?>/plugins/clip/pages/search.php"><?php echo escape($lang["clip-ai-smart-search"]) ?></a></p>
    <?php
}

/**
 * Runs vector generation automatically after preview creation, if configured.
 *
 * This function is triggered after a resource's preview image has been generated.
 * If configured, it will perform CLIP generation on the main resource file
 * (ignoring alternative files). Progress messages are displayed during processing.
 *
 * @param int $resource    The resource reference ID that has just had previews created.
 * @param int $alternative The alternative file ID, or -1 if processing the main resource.
 * @param bool $generate_all Flag to indicate if hook has been triggered during full preview creation process
 *
 * @return void
 */
function HookClipAllAfterpreviewcreation(int $resource, int $alternative, bool $generate_all = false): void
{
    global $clip_vector_on_upload,$lang,$clip_resource_types;

    $resource_type = get_resource_data($resource)["resource_type"];

    if ($alternative === -1 && $clip_vector_on_upload && in_array($resource_type, $clip_resource_types)) {
        // Nothing to do for alternatives; face processing is for the main file only.
        // Detect images on upload if configured
        set_processing_message($lang["clip-generating"] . " " . $resource);
        $success = clip_generate_vector($resource);
        if ($success) {
            set_processing_message($lang["clip-tagging"] . " " . $resource);
            clip_tag($resource);
        }
    }
}

function HookClipAllCron()
{
    global $clip_cron_generate_batch;
    clip_generate_missing_vectors($clip_cron_generate_batch);
}

function HookClipAllBeforedeleteresourcefromdb($ref) 
{
    ps_query('DELETE FROM resource_clip_vector WHERE resource = ?', ['i', $ref]);
}