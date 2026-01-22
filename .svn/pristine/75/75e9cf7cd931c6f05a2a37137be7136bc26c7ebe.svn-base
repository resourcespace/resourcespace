<?php

/**
 * Sends an image file or text input to the CLIP vector service and returns the resulting vector.
 *
 * @param bool   $is_text   Whether the input is text (true) or an image file (false).
 * @param string $input     Either the text string or the path to the image file.
 * @param int    $ref       The resource ID (used for logging/debugging purposes only).
 *
 * @return array|false      Returns a 512-float array representing the CLIP vector,
 *                          or false if the service failed or returned invalid data.
 */
function get_vector(bool $is_text, string $input, int $ref): array|false
{
    global $clip_service_url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $clip_service_url . "/vector");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (!$is_text) {
        $post_fields = [
            'image' => new CURLFile($input)
        ];
    } else {
        $post_fields = [
            'text' => $input
        ];
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        logScript("Resource $ref: error from CLIP service (HTTP $http_code)");
        return false;
    }

    $vector = json_decode($response, true);
    if (!is_array($vector) || count($vector) !== 512) {
        logScript("Resource $ref: invalid CLIP vector returned");
        return false;
    }

    return $vector;
}


/**
 * Generates a simple visual representation of a CLIP vector as a 64-character string. This helps
 * visualise differences between vectors in a human-readable form.
 *
 * @param array $vector  A 512-element array of float values (normalised CLIP vector).
 *
 * @return string        A 64-character string "fingerprint" visualising the vector content.
 */
function vector_visualise(array $vector): string
{
    $chars = str_split(" .:;i1tfLCG08@");
    $out = "";
    $chunks = array_chunk($vector, 8);

    foreach ($chunks as $chunk) {
        $mean = array_sum($chunk) / count($chunk);
        $sum_squares = 0;
        foreach ($chunk as $v) {
            $sum_squares += pow($v - $mean, 2);
        }
        $std_dev = sqrt($sum_squares / count($chunk));

        // Clamp & scale std dev for visualisation (tune this if needed)
        $scaled = min(1.0, $std_dev * 5); // exaggerate a bit
        $index = (int)round($scaled * (count($chars) - 1));
        $out .= $chars[$index];
    }

    return $out;
}

/**
 * Generates and stores a CLIP vector for a given resource.
 *
 * This function:
 * - Loads the specified resource data.
 * - Attempts to locate a resized (pre) version of the resource image.
 * - Sends the image to the CLIP service to obtain a 512-float vector.
 * - Stores the resulting vector as a binary blob in the database.
 *
 * @param int $ref The resource ID for which to generate the vector.
 *
 * @return bool        Returns true on success, or false on failure (e.g., file missing or vector generation error).
 */
function clip_generate_vector($ref) : bool
{
    $resource = get_resource_data($ref);
    $ext = "jpg";
    $size = "pre";
    $checksum = $resource['file_checksum'];
    $return = false;

    // Remove existing vectors
    ps_query("DELETE FROM resource_clip_vector WHERE resource = ?", ['i', $ref]);

    // Store image vector
    $image_path = get_resource_path($ref, true, $size, false, $ext);
    if (!file_exists($image_path)) {
        logScript("⚠ Resource $ref: file not found at $image_path");
        return false;
    }

    $vector = get_vector(false, $image_path, $ref);
    if ($vector === false) {
        // Stop processing if issue with FastAPI server
        return false;
    } 

    // Store vector in DB
    $vector = array_map('floatval', $vector); // ensure float values
    $blob = pack('f*', ...$vector);

    ps_query(
        "INSERT INTO resource_clip_vector (resource, vector_blob, checksum, is_text) VALUES (?, ?, ?, false)",
        ['i', $ref, 's', $blob, 's', $checksum]
    ); // Note the blob must be inserted as 's' type as ps_query() does not correctly handle 'b' yet (send_long_data() is needed)
    $return = sql_insert_id();

    logScript("✓ Image vector stored for resource $ref [" . vector_visualise($vector) . "] length: " . count($vector) . ", blob size: " . strlen($blob));

    // Store a vector for video snapshots also
    $snapshots = get_video_snapshots($ref, true, false);
    $frame_number = 0;
    foreach ($snapshots as $snapshot) {
        $frame_number++;

        $vector = get_vector(false, $snapshot, $ref);
        if ($vector === false) {
            // Stop processing if issue with FastAPI server
            return false;
        }

        // Store vector in DB
        $vector = array_map('floatval', $vector); // ensure float values
        $blob = pack('f*', ...$vector);

        ps_query(
            "INSERT INTO resource_clip_vector (resource, vector_blob, frame_number, checksum, is_text) VALUES (?, ?, ?, ?, false)",
            ['i', $ref, 's', $blob, 'i', $frame_number, 's', $checksum]
        ); // Note the blob must be inserted as 's' type as ps_query() does not correctly handle 'b' yet (send_long_data() is needed)

        logScript("✓ Video snapshot vector stored for resource $ref frame $frame_number [" . vector_visualise($vector) . "] length: " . count($vector) . ", blob size: " . strlen($blob));
    }
    return true; // Vector processing complete.
}


/**
 * Auto-tags and titles a resource using CLIP vector search.
 *
 * This function sends the resource ID to the external CLIP-based Python service
 * to retrieve suggested keywords and a title based on the resource's content.
 *
 * It populates:
 * - A keyword metadata field (multiple nodes created)
 * - A title metadata field (single text string)
 *
 * Global configuration variables (all available on the plugin's setup page)
 * - $clip_service_url: Base URL for the CLIP service
 * - $mysql_db: Current MySQL database name
 * - $clip_keyword_field: Field ID for keywords (node-based)
 * - $clip_keyword_url: URL of tag database for keywords
 * - $clip_keyword_count: Number of keyword suggestions to fetch
 * - $clip_title_field: Field ID for title (text field)
 * - $clip_title_url: URL of tag database for titles
 *
 * @param int $resource Resource ID to tag and title.
 * @return bool Always returns true after processing.
 */
function clip_tag(int $resource)
{
    global $clip_service_url, $mysql_db, $clip_keyword_field, $clip_keyword_url, $clip_keyword_count, $clip_title_field, $clip_title_url;
    $clip_service_call = $clip_service_url . "/tag";

    logScript("Calling CLIP service at $clip_service_call to tag resource $resource");

    if (is_numeric($clip_keyword_field) && $clip_keyword_field > 0) {
        // Keywords

        // Send search to Python service
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $clip_service_call);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Connection: keep-alive',
            'Expect:' // Prevents "100-continue" delay
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'db' => $mysql_db,
                'resource' => $resource,
                'url' => $clip_keyword_url,
                'top_k' => $clip_keyword_count,
        ]);
        $response = curl_exec($ch);
        $response_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logScript ("CLIP service response: " . $response);
        curl_close($ch);

        if (strlen($response) == 0 || $response_status !== 200) { 
            // CLIP server unresponsive
            return false; 
        }

        foreach (json_decode($response) as $result) {
            # Create new or fetch existing node
            $nodes[] = set_node(null, $clip_keyword_field, ucfirst($result->tag), null, 9999);
        }
        add_resource_nodes($resource, $nodes);
        logScript("CLIP suggested keywords resolved to nodes: " . join(", ", $nodes));
    }

    if (is_numeric($clip_title_field) && $clip_title_field > 0) {
        $ch = curl_init(); // new handle for the title request
        curl_setopt($ch, CURLOPT_URL, $clip_service_call);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Connection: keep-alive',
            'Expect:'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'db' => $mysql_db,
            'resource' => $resource,
            'url' => $clip_title_url,
            'top_k' => 1,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $title = urldecode(json_decode($response)[0]->tag);
        update_field($resource, $clip_title_field, $title);
        logScript("CLIP suggested title: " . $title);
    }

    return true;
}

function clip_generate_missing_vectors($limit)
{
    // Get resources needing vector generation or update - look at the modified date vs. the creation date on the text vector, and also the image checksum on the vector vs the one on the resource record. This catches both metadata and image updates.
    global $clip_resource_types;

    // Ensure only one instance of this.
    if (is_process_lock(__FUNCTION__) || count($clip_resource_types) === 0) {
        return false;
    }
    set_process_lock(__FUNCTION__);

    $sql = "
        SELECT r.ref value
        FROM resource r
        LEFT JOIN resource_clip_vector v_image ON v_image.is_text=0 and r.ref = v_image.resource

        WHERE r.has_image = 1
        AND r.resource_type in (" . ps_param_insert(count($clip_resource_types)) . ")
        AND r.file_checksum IS NOT NULL
        AND 
            (v_image.checksum IS NULL OR v_image.checksum != r.file_checksum)
        ORDER BY r.ref ASC
        LIMIT ?";

    $resources = ps_array($sql, array_merge(ps_param_fill($clip_resource_types, "i"), array('i', (int) $limit)));

    foreach ($resources as $resource) {
        clip_generate_vector($resource);
    }

    clear_process_lock(__FUNCTION__);
    return count($resources);
}


/**
 * Returns a count of vectors in the system
 *
 * @return int The total
 */
function clip_count_vectors()
{
    return ps_value("SELECT count(*) value from resource_clip_vector ", [], 0);
}


/**
 * Returns a count of vectors missing
 *
 * @return int The total
 */
function clip_missing_vectors()
{
    global $clip_resource_types;

    if (count($clip_resource_types) === 0) {
        return 0;
    }

    $sql = "
    SELECT count(*) value
    FROM resource r
    LEFT JOIN resource_clip_vector v_image ON v_image.is_text=0 and r.ref = v_image.resource

    WHERE r.has_image = 1
    AND r.resource_type in (" . ps_param_insert(count($clip_resource_types)) . ")
    AND r.file_checksum IS NOT NULL
    AND 
        (v_image.checksum IS NULL OR v_image.checksum != r.file_checksum)";

    return ps_value($sql, ps_param_fill($clip_resource_types, "i"), 0);
}


/**
 * Removes orphaned vectors - those that do not have a valid resource specified either because the resource has been removed or because
 * the list of resource types for which vectors are created has been changed.
 *
 * @return void
 */
function clip_vector_cleanup()
{
    global $clip_resource_types;
    $sql = "DELETE FROM resource_clip_vector";
    if (count($clip_resource_types) > 0) {
        $sql .= " WHERE resource not in (select ref from resource where resource_type in (" . ps_param_insert(count($clip_resource_types)) . "))";
    }
    ps_query($sql, ps_param_fill($clip_resource_types, "i"));
}
