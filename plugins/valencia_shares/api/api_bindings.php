<?php
/**
 * Valencia DAM – External collection share API for ResourceSpace
 *
 * INSTALLATION auf dem DAM-Server (dam.valencia.ch):
 *   1. Ordner plugins/valencia_shares/ anlegen
 *   2. Diese Datei nach plugins/valencia_shares/api/api_bindings.php kopieren
 *   3. plugins/valencia_shares/valencia_shares.yaml kopieren
 *   4. Plugin im Admin unter «Plugins» aktivieren
 *   5. PHP-OPcache leeren / Apache neu laden
 *
 * ResourceSpace hat generate_collection_access_key() / get_collection_external_access()
 * intern, aber die Remote-API listet unter Share nur delete_access_keys (und das oft
 * nur mit native-Auth). Ohne diese Bindings liefert /api/ leere Antworten.
 */

/**
 * Build a public external share URL for a collection + access key.
 *
 * @param int    $collection
 * @param string $access_key
 * @return string
 */
function valencia_shares_build_url($collection, $access_key)
{
    global $baseurl;
    $base = rtrim((string) $baseurl, '/');
    return $base . '/?c=' . (int) $collection . '&k=' . rawurlencode((string) $access_key);
}

/**
 * Create an external access key for a collection and return key + URL.
 *
 * @param int    $collection Collection ref
 * @param int    $access     0 = open (download), 1 = restricted
 * @param string $expires    Optional expiry date (Y-m-d or empty)
 * @param string $sharepwd   Optional share password
 * @return array
 */
function api_valencia_create_collection_share($collection, $access = 0, $expires = '', $sharepwd = '')
{
    $collection = (int) $collection;
    if ($collection <= 0) {
        return ['ok' => false, 'error' => 'invalid_collection'];
    }

    $col = get_collection($collection);
    if ($col === false || !is_array($col)) {
        return ['ok' => false, 'error' => 'collection_not_found'];
    }

    if (function_exists('allow_collection_share') && !allow_collection_share($col)) {
        return ['ok' => false, 'error' => 'share_not_allowed'];
    }

    $access = (int) $access;
    if ($access < 0) {
        $access = 0;
    }

    $expires = trim((string) $expires);
    $sharepwd = (string) $sharepwd;

    if (!function_exists('generate_collection_access_key')) {
        return ['ok' => false, 'error' => 'function_unavailable'];
    }

    $key = generate_collection_access_key(
        $collection,
        0,
        '',
        $access,
        $expires,
        '',
        $sharepwd
    );

    if ($key === false || $key === null || $key === '') {
        return ['ok' => false, 'error' => 'key_generation_failed'];
    }

    $key = (string) $key;
    return [
        'ok' => true,
        'access_key' => $key,
        'collection' => $collection,
        'access' => $access,
        'expires' => $expires,
        'url' => valencia_shares_build_url($collection, $key),
    ];
}

/**
 * List external shares for a collection (enriched with URLs).
 *
 * @param int $collection
 * @return array
 */
function api_valencia_list_collection_shares($collection)
{
    $collection = (int) $collection;
    if ($collection <= 0 || !function_exists('get_collection_external_access')) {
        return [];
    }

    $rows = get_collection_external_access($collection);
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $key = (string) ($row['access_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $out[] = [
            'access_key' => $key,
            'collection' => $collection,
            'access' => isset($row['access']) ? (int) $row['access'] : null,
            'expires' => $row['expires'] ?? null,
            'date' => $row['maxdate'] ?? ($row['date'] ?? null),
            'lastused' => $row['lastused'] ?? null,
            'emails' => $row['emails'] ?? '',
            'usergroup' => isset($row['usergroup']) ? (int) $row['usergroup'] : null,
            'upload' => isset($row['upload']) ? (int) $row['upload'] : 0,
            'url' => valencia_shares_build_url($collection, $key),
        ];
    }
    return $out;
}

/**
 * Revoke one collection external share key.
 *
 * @param int    $collection
 * @param string $access_key
 * @return array
 */
function api_valencia_delete_collection_share($collection, $access_key)
{
    $collection = (int) $collection;
    $access_key = trim((string) $access_key);
    if ($collection <= 0 || $access_key === '') {
        return ['ok' => false, 'error' => 'invalid_params'];
    }

    if (!function_exists('delete_collection_access_key')) {
        return ['ok' => false, 'error' => 'function_unavailable'];
    }

    delete_collection_access_key($collection, $access_key);
    return ['ok' => true, 'collection' => $collection, 'access_key' => $access_key];
}

/**
 * List usernames attached to a collection (user_collection), without requiring admin.
 *
 * @param int $collection
 * @return array list of {ref, username, fullname}
 */
function api_valencia_get_collection_users($collection)
{
    $collection = (int) $collection;
    if ($collection <= 0) {
        return [];
    }

    $rows = ps_query(
        'SELECT u.ref, u.username, u.fullname
           FROM user_collection AS uc
     INNER JOIN user AS u ON u.ref = uc.user
          WHERE uc.collection = ?
       ORDER BY u.username ASC',
        ['i', $collection]
    );

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'ref' => (int) ($row['ref'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'fullname' => (string) ($row['fullname'] ?? ''),
        ];
    }
    return $out;
}
