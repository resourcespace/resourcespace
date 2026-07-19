<?php

/**
 * Check whether the current ResourceSpace search can be handled by Typesense.
 *
 * @param string $search The original search string after ResourceSpace preprocessing.
 * @param array $keywords Parsed search keywords.
 * @param array $node_bucket Included node search buckets.
 * @param array $node_bucket_not Excluded node search buckets.
 * @param bool $return_disk_usage Whether disk usage totals are requested.
 * @param bool $editable_only Whether only editable resources should be returned.
 * @param bool $returnsql Whether SQL should be returned instead of results.
 * @param bool $smartsearch Whether smart search mode is active.
 *
 * @return bool True if Typesense can handle the search, otherwise false.
 */
function typesense_search_supported(
    string $search,
    array $keywords,
    array $node_bucket,
    array $node_bucket_not,
    bool $return_disk_usage,
    bool $editable_only,
    bool $returnsql,
    bool $smartsearch
): bool {
    if (substr($search, 0, 1) === "!") {
        return false;
    }

    if (strpos($search, ":") !== false) {
        return false;
    }

    if (count($node_bucket) > 0 || count($node_bucket_not) > 0) {
        return false;
    }

    if ($returnsql || $return_disk_usage || $editable_only || $smartsearch) {
        return false;
    }

    if (count($keywords) === 0) {
        return false;
    }

    return true;
}


/**
 * Run a ResourceSpace search using Typesense and return ResourceSpace-compatible results.
 *
 * @param string $search Search string.
 * @param mixed $restypes Resource type filter.
 * @param array $archive Archive state filter.
 * @param mixed $fetchrows Result limit or chunk details.
 * @param bool $return_refs_only Whether only resource refs should be returned.
 * @param PreparedStatementQuery $select Existing ResourceSpace SELECT fields.
 * @param string $order_by The order by SQL from the standard ResourceSpace search construction.
 *
 * @return array|false ResourceSpace-compatible results, or false to fall back to core search.
 */
function typesense_search_do_search(
    string $search,
    $restypes,
    array $archive,
    $fetchrows,
    bool $return_refs_only,
    PreparedStatementQuery $select,
    string $order_by,
    string $sort
) {
    $result = typesense_search_get_refs($search, $restypes, $archive, $fetchrows, $order_by, $sort);

    if ($result === false) {
        return false;
    }

    return typesense_search_hydrate_refs(
        $result['refs'],
        $result['total'],
        $fetchrows,
        $return_refs_only,
        $select,
        $order_by
    );
}


/**
 * Query Typesense and return matching resource refs in relevance order.
 *
 * @param string $search Search string.
 * @param mixed $restypes Resource type filter.
 * @param array $archive Archive state filter.
 * @param mixed $fetchrows Result limit or chunk details.
 *
 * @return array|false Ordered resource refs, or false if Typesense should be bypassed.
 */
function typesense_search_get_refs(
    string $search,
    $restypes,
    array $archive,
    $fetchrows,
    $order_by,
    $sort
) {
    global $typesense_search_collection, $typesense_search_global_filter, $date_field;

    setup_search_chunks($fetchrows, $chunk_offset, $search_chunk_size);

    $per_page = $search_chunk_size === -1 ? 250 : $search_chunk_size;
    $page = $search_chunk_size > 0
        ? (int)floor($chunk_offset / $search_chunk_size) + 1
        : 1;

    $filter_by = typesense_search_filter_by($restypes, $archive) . $typesense_search_global_filter;

    $prefix = "false,false";
    // Turn on prefix matching for wildcards.
    if (substr($search, -1) === '*') {
        $prefix = "true,true";
        $search = substr($search, 0, -1);
    }

    // Order by - convert the ResourceSpace SQL order to a Typesense compatible order
    $sort_by="_text_match";
    if (substr($order_by,0,5)=="field" . $date_field) {$sort_by="date";}
    if (substr($order_by,0,5)=="r.ref") {$sort_by="resource";}
    if (substr($order_by,0,8)=="modified") {$sort_by="modified";}
    $sort_by.=":" . strtolower($sort);

    $params = array(
        'q' => $search,
        'query_by' => 'title,text',
        'query_by_weights' => '10,2',
        'num_typos' => 0,
        'prefix' => $prefix,
        'page' => $page,
        'per_page' => $per_page,
        'sort_by' => $sort_by
    );

    if ($filter_by !== '') {
        $params['filter_by'] = $filter_by;
    }

    $endpoint =
        '/collections/'
        . rawurlencode($typesense_search_collection)
        . '/documents/search?'
        . http_build_query($params);

    $result = typesense_search_request('GET', $endpoint);

    if ($result === false || !isset($result['hits']) || !is_array($result['hits'])) {
        return false;
    }

    debug('typesense_search_get_refs(): found=' . ($result['found'] ?? 'unknown'));
    debug('typesense_search_get_refs(): hits=' . count($result['hits']));

    $refs = array();

    foreach ($result['hits'] as $hit) {
        if (isset($hit['document']['resource'])) {
            $refs[] = (int)$hit['document']['resource'];
        }
    }

    return array(
        'refs' => $refs,
        'total' => (int)($result['found'] ?? count($refs)),
    );
}


/**
 * Build a Typesense filter expression for simple ResourceSpace filters.
 *
 * @param mixed $restypes Resource type filter.
 * @param array $archive Archive state filter.
 *
 * @return string Typesense filter expression.
 */
function typesense_search_filter_by($restypes, array $archive): string
{
    $filters = array();

    if (is_string($restypes) && trim($restypes) !== '') {
        $resource_types = array_filter(array_map('intval', explode(',', $restypes)));

        if (count($resource_types) > 0) {
            $filters[] = 'resource_type:=[' . implode(',', $resource_types) . ']';
        }
    } elseif (is_array($restypes) && count($restypes) > 0) {
        $resource_types = array_filter(array_map('intval', $restypes));

        if (count($resource_types) > 0) {
            $filters[] = 'resource_type:=[' . implode(',', $resource_types) . ']';
        }
    }

    $archive_states = array_filter(
        array_map('intval', $archive),
        function ($archive_state) {
            return is_int($archive_state);
        }
    );

    if (count($archive_states) > 0) {
        $filters[] = 'archive:=[' . implode(',', $archive_states) . ']';
    }

    return implode(' && ', $filters);
}


/**
 * Hydrate Typesense resource refs into the standard ResourceSpace search result structure.
 *
 * @param array $refs Ordered resource refs from Typesense.
 * @param int $total Total number of matches reported by Typesense.
 * @param mixed $fetchrows Result limit or chunk details.
 * @param bool $return_refs_only Whether only resource refs should be returned.
 * @param PreparedStatementQuery $select Existing ResourceSpace SELECT fields.
 * @param string $order_by The order by SQL from the standard ResourceSpace search construction.
 *
 * @return array ResourceSpace-compatible search results.
 */
function typesense_search_hydrate_refs(
    array $refs,
    int $total,
    $fetchrows,
    bool $return_refs_only,
    PreparedStatementQuery $select,
    string $order_by
): array {
    if (count($refs) === 0) {
        return is_array($fetchrows)
            ? array('total' => 0, 'data' => array())
            : array();
    }

    setup_search_chunks($fetchrows, $chunk_offset, $search_chunk_size);

    $ref_placeholders = ps_param_insert(count($refs));
    $field_placeholders = ps_param_insert(count($refs));

    $ref_params = array();
    $field_params = array();

    foreach ($refs as $ref) {
        $ref_params[] = 'i';
        $ref_params[] = (int)$ref;

        $field_params[] = 'i';
        $field_params[] = (int)$ref;
    }

    $query = new PreparedStatementQuery();

    if ($return_refs_only) {
        $query->sql =
            'SELECT r.ref'
            . ' FROM resource r'
            . ' WHERE r.ref IN (' . $ref_placeholders . ')'
            . ' AND r.ref > 0';

        $query->parameters = $ref_params;
    } else {
        $query->sql =
            'SELECT r.hit_count score, ' . $select->sql
            . ' FROM resource r'
            . ' JOIN resource_type AS rty ON r.resource_type = rty.ref'
            . ' WHERE r.ref IN (' . $ref_placeholders . ')'
            . ' AND r.ref > 0';

        $query->parameters = array_merge(
            $select->parameters,
            $ref_params
        );
    }

    $query->sql .=
        ' GROUP BY r.ref'
        . ' ORDER BY FIELD(r.ref, ' . $field_placeholders . ')';

    $query->parameters = array_merge($query->parameters, $field_params);

    debug('typesense_search_hydrate_refs(): candidate refs=' . count($refs));
    debug('typesense_search_hydrate_refs(): sql=' . $query->sql);
    debug('typesense_search_hydrate_refs(): params=' . print_r($query->parameters, true));

    $rows = ps_query($query->sql, $query->parameters);
    $paged_rows = $rows;

    if ($return_refs_only) {
        $paged_rows = array_map(
            function ($row) {
                return array('ref' => (int)$row['ref']);
            },
            $paged_rows
        );
    }

    if (is_array($fetchrows)) {
        return array('total' => $total, 'data' => $paged_rows);
    }
    else  {
        return $paged_rows;
    }
}


/**
 * Send a request to the Typesense API.
 *
 * @param string $method HTTP method.
 * @param string $endpoint API endpoint beginning with a slash.
 * @param array|null $payload Optional request payload.
 *
 * @return array|false Decoded JSON response, or false on failure.
 */
function typesense_search_request(string $method, string $endpoint, ?array $payload = null)
{
    global $typesense_search_host, $typesense_search_port;
    global $typesense_search_protocol, $typesense_search_api_key;
    global $typesense_search_timeout;

    $url =
        $typesense_search_protocol
        . '://'
        . $typesense_search_host
        . ':'
        . $typesense_search_port
        . $endpoint;

    $curl = curl_init($url);

    if ($curl === false) {
        debug('typesense_search_request(): Failed to initialise cURL');
        return false;
    }

    $headers = array(
        'Content-Type: application/json',
        'X-TYPESENSE-API-KEY: ' . $typesense_search_api_key,
    );

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $typesense_search_timeout);
    curl_setopt($curl, CURLOPT_TIMEOUT, $typesense_search_timeout);

    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($curl);

    if ($response === false) {
        debug('typesense_search_request(): cURL error: ' . curl_error($curl));
        return false;
    }

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    debug(
        'typesense_search_request(): '
        . $method
        . ' '
        . $endpoint
        . ' returned HTTP '
        . $status
    );

    if ($status < 200 || $status >= 300) {
        debug('typesense_search_request(): Response body: ' . $response);
        echo $response;
        return false;
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        debug('typesense_search_request(): Failed to decode JSON response');
        return false;
    }

    return $decoded;
}


/**
 * Ensure that the Typesense resources collection exists.
 *
 * @return bool True if the collection exists or was created.
 */
function typesense_search_ensure_collection(): bool
{
    global $typesense_search_collection;

    $existing = typesense_search_request('GET', '/collections/' . rawurlencode($typesense_search_collection));

    if ($existing !== false) {
        return true;
    }

    $schema = array(
        'name' => $typesense_search_collection,
        'fields' => array(
            array('name' => 'resource', 'type' => 'int32', 'sort' => true),
            array('name' => 'title', 'type' => 'string', 'stem' => true),
            array('name' => 'text', 'type' => 'string', 'stem' => true),
            array('name' => 'resource_type', 'type' => 'int32', 'facet' => true, 'sort' => true),
            array('name' => 'archive', 'type' => 'int32', 'facet' => true),
            array('name' => 'date', 'type' => 'int64', 'sort' => true, 'optional' => true),
            array('name' => 'modified', 'type' => 'int64', 'sort' => true, 'optional' => true),
            ),
        'default_sorting_field' => 'resource',
    );

    $created = typesense_search_request('POST', '/collections', $schema);
    if (!$created) debug('typesense_search_ensure_collection(): collection creation FAILED');

    return $created !== false;
}


/**
 * Build a Typesense document for a ResourceSpace resource.
 *
 * @param int $resource Resource ID.
 *
 * @return array|false Typesense document data, or false if the resource cannot be indexed.
 */
function typesense_search_get_document_data(int $resource)
{
    global $date_field;

    $resource_data = get_resource_data($resource);

    if ($resource_data === false || !is_array($resource_data)) {
        return false;
    }

    $fields = get_resource_type_fields($resource_data['resource_type']);

    if (!is_array($fields)) {
        return false;
    }

    $indexed_values = array($resource); // Always index the resource ID itself.
    $title = '';

    foreach ($fields as $field) {
        if (($field['keywords_index'] ?? $field['index'] ?? 0) != 1) {
            continue;
        }

        $value = trim((string)get_data_by_field($resource, (int)$field['ref']));

        if ($value === '') {
            continue;
        }

        $indexed_values[] = $value;

        if ((int)$field['ref'] === (int)$GLOBALS['view_title_field']) {
            $title = $value;
        }
    }

    // Fetch CLIP vetor if we have one.
    /*
    $image_embedding=null;
    $image_embedding_blob=ps_value("SELECT vector_blob value FROM resource_clip_vector WHERE ref=? LIMIT 1",["i",$resource],"");
    if (strlen($image_embedding_blob)>0) {
        $image_embedding=unpack('g*', $image_embedding_blob);
    }
    */

    // Created date
    $date=null;
    $date_value=$resource_data['field' . $date_field];
    if (strlen($date_value)>0) {$date=strtotime($date_value);}

    // Modified date
    $modified=null;
    $modified_value=$resource_data['modified'];
    if (strlen($modified_value)>0) {$modified=strtotime($modified_value);}

    return array(
        'id' => (string)$resource,
        'resource' => (int)$resource,
        'title' => $title,
        'text' => implode(' ', $indexed_values),
        'resource_type' => (int)$resource_data['resource_type'],
        'archive' => (int)$resource_data['archive'],
        'date' => $date,
        'modified' => $modified
    );
}


/**
 * Index a single ResourceSpace resource in Typesense.
 *
 * @param int $resource Resource ID.
 *
 * @return bool True if the resource was indexed successfully.
 */
function typesense_search_index_resource(int $resource): bool
{
    global $typesense_search_collection;

    if (!typesense_search_ensure_collection()) {
        return false;
    }

    $document = typesense_search_get_document_data($resource);

    if ($document === false) {
        return false;
    }

    $endpoint =
        '/collections/'
        . rawurlencode($typesense_search_collection)
        . '/documents?action=upsert';

    return typesense_search_request('POST', $endpoint, $document) !== false;
}


/**
 * Delete a resource document from Typesense.
 *
 * @param int $resource Resource ID.
 *
 * @return bool True if the delete request succeeded.
 */
function typesense_search_delete_resource(int $resource): bool
{
    global $typesense_search_collection;

    $endpoint =
        '/collections/'
        . rawurlencode($typesense_search_collection)
        . '/documents/'
        . rawurlencode((string)$resource);

    return typesense_search_request('DELETE', $endpoint) !== false;
}


/**
 * Reindex all resources that are currently linked to a node.
 *
 * @param int $node Node ID.
 *
 * @return int Number of resources successfully reindexed.
 */
function typesense_search_reindex_node_resources(int $node): int
{
    $resources = ps_array(
        'SELECT DISTINCT resource value FROM resource_node WHERE node = ?',
        array('i', $node)
    );

    $indexed = 0;

    foreach ($resources as $resource) {
        if (typesense_search_index_resource((int)$resource)) {
            $indexed++;
        }
    }

    return $indexed;
}


/**
 * Reindex resources in batches.
 *
 * @param int $limit Maximum number of resources to index in this batch.
 * @param int $after Only index resources with refs greater than this value.
 *
 * @return array Batch indexing summary.
 */
function typesense_search_reindex_all(int $limit = 100, int $after = 0): array
{
    $resources = ps_array(
        'SELECT ref value FROM resource WHERE ref > ? ORDER BY ref ASC LIMIT ?',
        array('i', $after, 'i', $limit)
    );

    $indexed = 0;
    $failed = 0;
    $last = $after;
    $content_length = 0;

    foreach ($resources as $resource) {
        $last = (int)$resource;

        $document = typesense_search_get_document_data($last);

        if ($document === false) {
            $failed++;
            continue;
        }

        $content_length += strlen($document['title'] ?? '');
        $content_length += strlen($document['text'] ?? '');

        if (typesense_search_index_document($document)) {
            $indexed++;
        } else {
            $failed++;
        }
    }

    return array(
        'indexed' => $indexed,
        'failed' => $failed,
        'last' => $last,
        'content_length' => $content_length,
        'complete' => count($resources) < $limit,
    );
}


/**
 * Index a Typesense document.
 *
 * @param array $document Typesense document data.
 *
 * @return bool True if the document was indexed successfully.
 */
function typesense_search_index_document(array $document): bool
{
    global $typesense_search_collection;

    $endpoint =
        '/collections/'
        . rawurlencode($typesense_search_collection)
        . '/documents?action=upsert';

    return typesense_search_request('POST', $endpoint, $document) !== false;
}


/**
 * Synchronise ResourceSpace related keywords to Typesense synonyms.
 *
 * Creates or updates synonym groups in the configured Typesense collection
 * based on ResourceSpace related keyword relationships so that searches
 * automatically match related terms using OR-style expansion.
 *
 * @return bool True if the sync completed successfully.
 */
function typesense_search_sync_related_keywords(): bool
{
    global $typesense_search_collection;

    $synonyms_endpoint =
        '/collections/'
        . rawurlencode($typesense_search_collection)
        . '/synonyms';

    $existing = typesense_search_request('GET', $synonyms_endpoint);

    if ($existing !== false && isset($existing['synonyms']) && is_array($existing['synonyms'])) {
        foreach ($existing['synonyms'] as $synonym) {
            $id = $synonym['id'] ?? '';

            if (strpos($id, 'rs_related_') !== 0) {
                continue;
            }

            typesense_search_request(
                'DELETE',
                $synonyms_endpoint . '/' . rawurlencode($id)
            );
        }
    }

    $groups = get_grouped_related_keywords('');

    foreach ($groups as $group) {
        $keywords = array();

        $keywords[] = trim((string)$group['keyword']);

        foreach (explode(',', (string)$group['related']) as $related) {
            $related = trim($related);

            if ($related !== '') {
                $keywords[] = $related;
            }
        }

        $keywords = array_values(array_unique(array_filter($keywords)));

        if (count($keywords) < 2) {
            continue;
        }

        sort($keywords);

        $payload = array(
            'synonyms' => $keywords,
        );

        $endpoint =
            $synonyms_endpoint
            . '/'
            . rawurlencode('rs_related_' . md5(implode('|', $keywords)));

        typesense_search_request('PUT', $endpoint, $payload);
    }

    return true;
}
