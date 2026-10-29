<?php

command_line_only();

return (static function () {
    global $userref, $userpermissions;

    $saved_permissions = $userpermissions;
    try {
        // Rejected requests must not leave partial resource records behind.
        $count = ps_value('SELECT COUNT(*) AS value FROM resource', [], 0);
        $invalid_type = ps_value('SELECT MAX(ref) + 1 AS value FROM resource_type', [], 1);
        foreach ([[$invalid_type, 0], [1, 'invalid-state']] as $input) {
            if (create_resource(...$input) !== false
                || ps_value('SELECT COUNT(*) AS value FROM resource', [], 0) != $count) {
                echo 'Invalid creation request inserted a resource - ';
                return false;
            }
        }

        // The default workflow state follows the first editable state.
        foreach ([-2, -1, 0] as $state) {
            $userpermissions = ['e' . $state];
            $resource = create_resource(1, 999, -1, '', 'txt', false);
            $data = get_resource_data($resource, false);
            if ($data === false || (int) $data['archive'] !== $state
                || (int) $data['created_by'] !== (int) $userref
                || (int) $data['resource_type'] !== 1
                || $data['file_extension'] !== 'txt' || (int) $data['no_file'] !== 1) {
                echo "Incorrect initial resource properties for state {$state} - ";
                return false;
            }
        }

        return true;
    } finally {
        $userpermissions = $saved_permissions;
    }
})();
