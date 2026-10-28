<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/request_functions.php';
    $get = $_GET;
    $post = $_POST;
    $saved_permissions = $GLOBALS['userpermissions'];
    $user = new_user('requester_' . test_generate_random_ID(6));
    $request = 0;
    try {
        $resource = create_resource(1, 0);
        $collection = create_collection($user, 'Request fixture');
        add_resource_to_collection($resource, $collection, true);
        ps_query('INSERT INTO request (user,collection,created,status,request_mode) VALUES (?,?,NOW(),0,1)', ['i', $user, 'i', $collection]);
        $request = sql_insert_id();
        $check(get_request($request)['status'] == 0, 'Pending request fixture missing');
        $_GET = [];
        $_POST = ['status' => '1', 'expires' => '2099-12-31', 'assigned_to' => '0', 'reasonapproved' => 'Approved for testing'];
        $GLOBALS['userpermissions'] = array_unique(array_merge($saved_permissions, ['R', 'Ra']));
        $check(save_request($request), 'Request approval failed');
        $row = get_request($request);
        $check($row['status'] == 1 && $row['reasonapproved'] === 'Approved for testing', 'Approval status/reason not persisted');
        $access = ps_query('SELECT * FROM resource_custom_access WHERE resource = ? AND user = ?', ['i', $resource, 'i', $user]);
        $check(count($access) === 1 && $access[0]['access'] == 0, 'Approval did not grant access');
        save_request($request);
        $check(ps_value('SELECT COUNT(*) value FROM resource_custom_access WHERE resource = ? AND user = ?', ['i', $resource, 'i', $user], 0) == 1, 'Repeated approval duplicated grant');
        $_POST = ['status' => '2', 'expires' => '', 'assigned_to' => '0', 'reason' => 'Declined for testing'];
        $check(save_request($request), 'Request rejection failed');
        $check(get_request($request)['status'] == 2 && get_request($request)['reason'] === 'Declined for testing', 'Rejection not persisted');
        $check(ps_value('SELECT COUNT(*) value FROM resource_custom_access WHERE resource = ? AND user = ?', ['i', $resource, 'i', $user], -1) == 0, 'Rejection retained access grant');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $_GET = $get;
        $_POST = $post;
        $GLOBALS['userpermissions'] = $saved_permissions;
        ps_query('DELETE FROM request WHERE ref = ?', ['i', $request]);
        ps_query('DELETE FROM resource_custom_access WHERE user = ?', ['i', $user]);
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
    }
})();
