<?php
/**
 * Valencia DAM – User messages API for ResourceSpace
 *
 * INSTALLATION:
 *   plugins/valencia_messages/valencia_messages.yaml
 *   plugins/valencia_messages/api/api_bindings.php
 * Enable under Admin → Plugins.
 *
 * Remote API only exposes get_user_message(ref); inbox listing needs message_get().
 */

/**
 * List messages for the current user.
 *
 * @param int $include_seen 1 = include already-read messages
 * @param int $limit        Max rows (0 = default 100)
 * @return array
 */
function api_valencia_list_user_messages($include_seen = 0, $limit = 100)
{
    global $userref;
    if (!function_exists('message_get') || empty($userref)) {
        return [];
    }

    $include_seen = (int) $include_seen === 1;
    $limit = (int) $limit;
    if ($limit <= 0) {
        $limit = 100;
    }

    $messages = [];
    // message_get(&$messages, $user, $expired, $seen, $sort, $order_by, $limit)
    message_get($messages, (int) $userref, false, $include_seen, 'DESC', 'created', $limit);

    $out = [];
    foreach ($messages as $row) {
        $out[] = [
            'ref' => (int) ($row['ref'] ?? 0), // user_message.ref (for message_seen)
            'message_id' => (int) ($row['message_id'] ?? ($row['ref'] ?? 0)),
            'message' => (string) ($row['message'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'owner' => (string) ($row['owner'] ?? ''),
            'ownerid' => isset($row['ownerid']) ? (int) $row['ownerid'] : null,
            'created' => $row['created'] ?? null,
            'expires' => $row['expires'] ?? null,
            'seen' => (int) ($row['seen'] ?? 0),
        ];
    }
    return $out;
}

/**
 * Fetch one message (permissions honoured by get_user_message).
 *
 * @param int $ref Message ID (message.ref) — also accept user_message.ref via list payload
 * @return array|false
 */
function api_valencia_get_user_message($ref)
{
    $ref = (int) $ref;
    if ($ref <= 0) {
        return false;
    }
    if (function_exists('get_user_message')) {
        $msg = get_user_message($ref);
        if (is_array($msg)) {
            return [
                'ok' => true,
                'message' => (string) ($msg['message'] ?? ''),
                'url' => (string) ($msg['url'] ?? ''),
                'owner' => $msg['owner'] ?? null,
            ];
        }
    }
    return ['ok' => false, 'error' => 'not_found'];
}

/**
 * Mark a user_message as seen.
 *
 * @param int $ref user_message.ref
 * @return array
 */
function api_valencia_mark_message_seen($ref)
{
    $ref = (int) $ref;
    if ($ref <= 0 || !function_exists('message_seen')) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    message_seen($ref);
    return ['ok' => true, 'ref' => $ref];
}
