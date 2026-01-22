<?php

/**
 * Access control check if user is allowed to manage system tabs.
 *
 * @return bool
 * */
function acl_can_manage_tabs()
{
    return checkperm('a');
}

/**
 * Get entire tab records for a list of IDs
 *
 * @param array $refs List of tab refs
 *
 * @return array
 */
function get_tabs_by_refs(array $refs)
{
    $refs = array_filter($refs, 'is_int_loose');
    $refs_count = count($refs);
    if ($refs_count > 0) {
        return ps_query(
            'SELECT ref, `name`, order_by FROM tab WHERE ref IN (' . ps_param_insert($refs_count) . ') ORDER BY order_by',
            ps_param_fill($refs, 'i')
        );
    }

    return [];
}

/**
 * Get tabs (paged) based on some criteria (currently only order by and limit).
 *
 * @param array $criteria Array holding criteria information (order_by and limit).
 *
 * @return array {@see sql_limit_with_total_count()}
 * */
function get_tabs_with_usage_count(array $criteria)
{
    $order_by = isset($criteria['order_by'][0]) && in_array($criteria['order_by'][0], ['order_by', 'ref'])
                ? $criteria['order_by'][0]
                : 'order_by';
    $sort = isset($criteria['order_by'][1]) && validate_sort_value($criteria['order_by'][1])
            ? $criteria['order_by'][1]
            : 'ASC';

    $per_page = $criteria['limit']['per_page'] ?? null;
    $offset = $criteria['limit']['offset'] ?? null;

    $query = new PreparedStatementQuery(
        "SELECT t.ref,
                t.`name`,
                t.order_by,
                (SELECT count(ref) FROM resource_type_field WHERE tab = t.ref) AS usage_rtf,
                (SELECT count(ref) FROM resource_type WHERE tab = t.ref) AS usage_rt
           FROM tab AS t
           ORDER BY {$order_by} {$sort}"
    );

    return sql_limit_with_total_count($query, $per_page, $offset);
}

/**
 * Get all tab records, sorted by the order_by column
 * @return array
 * */
function get_all_tabs()
{
    return ps_query('SELECT ref, `name`, order_by FROM tab ORDER BY order_by');
}

/**
 * Get list of all tabs sorted based on current configuration. This always adds a fake record (ref #0) to indicate no assignment.
 *
 * @return array Key is the tabs' ID and value its translated name.
 */
function get_tab_name_options()
{
    // The no selection option is always first
    $tabs = array_map('i18n_get_translated', [0 => ''] + array_column(get_all_tabs(), 'name', 'ref'));
    return sort_tabs_as_configured($tabs);
}

/**
 * Sort list of tab names (preserving their key ID)
 *
 * @param array $tabs List of tab ID and tab translated name pairs
 *
 * @return array
 * */
function sort_tabs_as_configured(array $tabs)
{
    if ($GLOBALS['sort_tabs'] ?? false) {
        asort($tabs);
    }

    return $tabs;
}

/**
 * Create a new system tab record
 * NOTE: order_by should only be set when re-ordering the set by the user. {@see sql_reorder_records('tab', $refs)}
 *
 * @return bool|int Return new tab record ID or FALSE otherwise
 */
function create_tab(array $tab)
{
    $name = trim($tab['name'] ?? '');
    if ($name !== '' && acl_can_manage_tabs()) {
        ps_query(
            'INSERT INTO tab (`name`, order_by)
                     VALUES (
                        ?,
                        (
                            SELECT * FROM (
                                (SELECT ifnull(tab.order_by, 0) + 10 FROM tab ORDER BY ref DESC LIMIT 1)
                                UNION (SELECT 10)
                            ) AS nob
                            LIMIT 1
                        ))',
            ['s', $name]
        );
        $ref = sql_insert_id();
        log_activity(null, LOG_CODE_CREATED, $name, 'tab', 'name', $ref);

        return $ref;
    }

    return false;
}

/**
 * Delete system tabs.
 *
 * IMPORTANT: never allow the "Default" tab (ref #1) to be deleted because this is the fallback location for information
 * that has no association with other tabs.
 *
 * @param array $refs List of tab IDs
 *
 * @return bool Returns TRUE if it executed the query, FALSE otherwise
 */
function delete_tabs(array $refs)
{
    if (!acl_can_manage_tabs()) {
        return false;
    }

    $batch_activity_logger = function ($ref) {
        log_activity(null, LOG_CODE_DELETED, null, 'tab', 'name', $ref);
    };
    $refs_chunked = array_chunk(
        // Sanitise list: only numbers and never allow the "Default" tab (ref #1) to be deleted
        array_diff(array_filter($refs, 'is_int_loose'), [1]),
        SYSTEM_DATABASE_IDS_CHUNK_SIZE
    );
    foreach ($refs_chunked as $refs_list) {
        $return = ps_query(
            'DELETE FROM tab WHERE ref IN (' . ps_param_insert(count($refs_list)) . ')',
            ps_param_fill($refs_list, 'i')
        );

        array_walk($refs_list, $batch_activity_logger);
    }

    return isset($return);
}

/**
 * Update an existing tab.
 * NOTE: order_by should only be set when re-ordering the set by the user. {@see sql_reorder_records('tab', $refs)}
 *
 * @param array $tab A tab record (type)
 *
 * @return bool Returns TRUE if it executed the query, FALSE otherwise
 */
function save_tab(array $tab)
{
    $ref = (int) $tab['ref'];
    $name = trim($tab['name']);

    if ($ref > 0 && $name !== '' && acl_can_manage_tabs()) {
        log_activity(null, LOG_CODE_EDITED, $name, 'tab', 'name', $ref);
        ps_query('UPDATE tab SET `name` = ? WHERE ref = ?', ['s', $name, 'i', $ref]);
        return true;
    }

    return false;
}
