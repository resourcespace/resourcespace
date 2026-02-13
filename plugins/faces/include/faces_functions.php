<?php

/**
 * Detects faces in the image associated with a given resource.
 *
 * This function attempts to locate a suitable JPEG file for the specified resource.
 * It then sends the file to the Python FastAPI service. For each detected
 * face, it records the bounding box, detection score, and face vector into the
 * `resource_face` database table. The resource is then marked as processed.
 *
 * @param  int   $ref     The resource reference ID to process.
 * @param  bool  $force   Force the processing of this resource, for example when replacing
 *                        the file or where it was previously not possible to find the file.
 *
 * @return bool Returns true if face detection and storage were successful,
 *              or false if the file was missing, the service failed, or invalid data was returned.
 */
function faces_detect(int $ref, bool $force = false): bool
{
    global $faces_service_endpoint, $faces_confidence_threshold;

    if ($force) {
        # Setting faces_processed = 0 restores default, allowing the script to pickup the resource if
        # this attempt fails e.g. if extract_faces service is unavailable.
        ps_query("UPDATE resource SET faces_processed = 0 WHERE ref = ?", ["i", $ref]);
        # Clear existing detected faces before processing the file.
        ps_query("DELETE FROM resource_face WHERE resource = ?", ["i", $ref]);
        $faces_processed = 0;
    } else {
        $faces_processed = (int) ps_value("SELECT faces_processed value FROM resource WHERE ref = ?", ["i", $ref], 1);
    }

    $file_path = get_resource_path($ref, true, 'scr', false, "jpg");

    if ('cli' == PHP_SAPI) {
        if (ob_get_level() == 0) {
            ob_start();
            $ob_started = true;
        }
        flush();
        ob_flush();
    }

    if ($faces_processed === 1) {
        logScript("Faces already processed for resource $ref");
        return false;
    }

    if (!file_exists($file_path)) {
        $resource_data = get_resource_data($ref);
        if (is_jpeg_extension((string) $resource_data['file_extension'])) {
            // Try full size JPEG as a fallback (for small images only where SCR wasn't generated)
            $file_path = get_resource_path($ref, true, '', false, $resource_data['file_extension']);
        } else {
            // Last try to find a file. Use the pre size. This should be present for files too small to have an scr size.
            // For example, if the derived jpg from an mp4 used to create previews is smaller than the scr size.
            $file_path = get_resource_path($ref, true, 'pre', false, "jpg");
        }
    }

    if (!file_exists($file_path)) {
        # Set faces_processed = 2 to prevent future retrying with each run of the script.
        # At this point there is no file to process so no need to keep trying.
        ps_query("UPDATE resource SET faces_processed = 2 WHERE ref = ?", ["i", $ref]);
        logScript("File not found for resource $ref ($file_path)");
        return false;
    }

    // Prepare file for POST
    $curl = curl_init();
    $cfile = new CURLFile($file_path);
    $postfields = ['file' => $cfile];

    curl_setopt_array($curl, [
        CURLOPT_URL => $faces_service_endpoint . "/extract_faces",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postfields,
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    unset($curl);

    if ($http_code !== 200) {
        logScript("Face service returned HTTP $http_code for resource $ref");
        return false;
    }

    $faces = json_decode($response, true);
    if (!is_array($faces)) {
        logScript("Invalid JSON from face service for resource $ref");
        return false;
    }

    foreach ($faces as $face) {
        $bbox = isset($face['bbox']) ? json_encode($face['bbox']) : null; // Bounding box data
        $score = isset($face['det_score']) ? (float)$face['det_score'] : null; // Confidence score
        $vector = isset($face['embedding']) ? pack("f*", ...$face['embedding']) : null;

        if ($vector === null) {
            continue;
        }

        if ($score < $faces_confidence_threshold) {
            continue;
        }

        if (ps_value("SELECT count(*) value FROM resource_face WHERE resource=? AND bbox=?", ['i', $ref, 's', $bbox], 0) > 0) {
            continue;
        }

        ps_query(
            "INSERT INTO resource_face (resource, bbox, det_score, vector_blob, created) VALUES (?, ?, ?, ?, NOW())",
            ['i', $ref, 's', $bbox, 'd', $score, 's', $vector]
        );
    }

    // Mark resource as processed, faces_processed = 1 indicates success.
    ps_query("UPDATE resource SET faces_processed = 1 WHERE ref = ?", ["i", $ref]);
    logScript("Processed resource $ref and found " . count($faces) . " faces.");
    if ($ob_started ?? false) {
        ob_get_clean();
    }
    return true;
}


/**
 * Tags detected faces in a given resource based on similarity to existing tagged faces.
 *
 * This function finds all untagged faces for the specified resource, sends each face to
 * the Python FastAPI service to find similar known faces, and tags the face with
 * the most frequently matched metadata node. It also updates the `resource_face` table
 * to associate the face with the chosen metadata node.
 *
 * @param int $resource The resource reference ID to process and tag faces for.
 *
 * @return bool Returns true on successful processing, or false if any service errors
 *              or invalid responses are encountered.
 */
function faces_tag(int $resource): bool
{
    global $faces_service_endpoint, $mysql_db, $faces_tag_threshold;

    if ('cli' == PHP_SAPI) {
        if (ob_get_level() == 0) {
            ob_start();
            $ob_started = true;
        }
        flush();
        ob_flush();
    }

    // Find untagged faces for this resource
    $faces = ps_array("SELECT ref value FROM resource_face WHERE resource=? and (node is null or node=0) ORDER BY ref desc", ["i", $resource]);
    foreach ($faces as $face) {
        logScript("Processing face " . $face . " in resource " . $resource);

        $function = "find_similar_faces";
        $faces_service_call = $faces_service_endpoint . "/" . $function;

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
            'threshold' => $faces_tag_threshold,
            'tagged_only' => true,
            'k' => 200
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Connection: keep-alive',
            'Expect:'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($http_code !== 200 || empty($response)) {
            echo "Error from faces_service (HTTP $http_code)\n";
            return false;
        }

        $results = json_decode($response, true);
        if (!is_array($results)) {
            logScript("Invalid response from faces_service.");
            return false;
        }

        if (count($results) == 0) {
            logScript("No matching faces found.");
            continue;
        }

        // Find all nodes set for matching faces.
        $nodes = array_column($results, 'node');

        // Filter out non-numeric or null values
        $filtered_nodes = array_filter($nodes, static function ($value) {
            return is_numeric($value) && $value > 0;
        });

        // Check if the filtered list is empty
        if (empty($filtered_nodes)) {
            logScript("No valid node values in the matching faces.");
            continue;
        }

        // Count frequency of each node
        $counts = array_count_values($filtered_nodes);

        // Find the node with the highest frequency
        arsort($counts);
        $most_common_node = array_key_first($counts);
        $count = reset($counts);

        logScript("Most common node: $most_common_node (occurs $count times)");

        // Tag this face with the node.
        add_resource_nodes($resource, [$most_common_node]); // Add to the resource metadata
        ps_query("update resource_face set node=? where ref=?", ["i",$most_common_node,"i",$face]); // Attach the node to this face

        logScript("Tagged with node: " . $most_common_node);
    }

    if ($ob_started ?? false) {
        ob_get_clean();
    }
    return true;
}

/**
 * API function to update the named person tag for a specific face using the provided node value.
 *
 * Typically triggered when selecting a name from a dropdown, this function assigns a metadata node
 * (e.g. representing a person) to a face record in the `resource_face` table by updating the `node` field.
 *
 * @param int $face  The unique reference ID of the face to update (from `resource_face.ref`).
 * @param int $node  The node ID to assign to the face (typically corresponds to a controlled vocabulary entry).
 *
 * @return bool  Returns true on successful update.
 *
 * @uses ps_query()
 * @uses debug()
 */
function api_faces_set_node(int $resource, int $face, int $node): bool
{
    debug("API: faces_tag(" . $face . ", " . $node);

    // Permission check
    $edit_access = get_edit_access($resource);
    if (!$edit_access) {
        return false;
    }

    // Set metadata
    add_resource_nodes($resource, array($node));

    // Assign to face
    ps_query("update resource_face set node=? where ref=?", ["i",$node,"i",$face]);
    return true;
}

/**
 * Detect faces in resources that have not yet been processed
 *
 * @return int       A count of the resources processed.
 *
 * @uses ps_query()
 * @uses debug()
 */
function faces_detect_missing()
{
    // Get all resources that haven't had faces processed yet

    // Ensure only one instance of this.
    if (is_process_lock(__FUNCTION__)) {
        return false;
    }
    set_process_lock(__FUNCTION__);

    $resources = ps_array("SELECT ref value FROM resource WHERE has_image=1 and (faces_processed is null or faces_processed=0) ORDER BY ref desc");

    foreach ($resources as $resource) {
        faces_detect($resource);
    }

    clear_process_lock(__FUNCTION__);
    return count($resources);
}

/**
 * Return count of images to process
 *
 * @return int       The count
 */
function faces_count_missing()
{
    return ps_value("SELECT count(*) value FROM resource WHERE has_image=1 and (faces_processed is null or faces_processed=0)", [], 0);
}

/**
 * Return a count of the detected faces
 *
 * @return int       The count
 */
function faces_count_faces()
{
    return ps_value("SELECT count(*) value FROM resource_face", [], 0);
}
