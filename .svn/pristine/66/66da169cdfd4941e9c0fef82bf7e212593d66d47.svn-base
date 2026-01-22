<?php
if (!function_exists('track_field_history_get_field_log')) {
    
    function track_field_history_get_field_log($resource_id, $field_id) {
    
        $query = "SELECT resource_log.date AS date,
                         IFNULL(user.fullname, user.username) AS user,
                         resource_log.diff
                    FROM resource_log
               LEFT JOIN user ON user.ref = resource_log.user
                   WHERE (type = 'e' OR type = 'm')
                     AND resource = ?
                     AND resource_type_field = ?
                ORDER BY resource_log.date DESC;";

        return ps_query($query, array("i", $resource_id, "i", $field_id));

    }
}
