<?php

include_once __DIR__ . "/../../include/boot.php";
if ($sysvars["upgrade_system_level"] < 2) {
    // After migrating to nodes it is preferable to delete resource_keyword rows for fixed list data
    // as this is now stored in normalised form in resource_node and node_keyword

    // Updated to only delete data if the resource_keyword table still exists - this table will be unused from v10 and likley to be deleted later
    $alltables = ps_query("SHOW TABLES");
    $resource_keyword_table = in_array("resource_keyword", array_column($alltables, "Tables_in_" . $mysql_db));
    if ($resource_keyword_table) {
        $fixed_fields = ps_array("SELECT ref value FROM resource_type_field WHERE type IN (" . ps_param_insert(count($FIXED_LIST_FIELD_TYPES)) . ")", ps_param_fill($FIXED_LIST_FIELD_TYPES, "i"));

        foreach ($fixed_fields as $fixed_field) {
            echo " - Deleting resource_keyword data for field ref#" . $fixed_field . $LINE_END;
            $count_query = "SELECT COUNT(*) value FROM resource_keyword WHERE resource_type_field = ?";
            $c = ps_value($count_query, array("i", $fixed_field), 0);
            while ($c > 0) {
                ps_query("DELETE FROM resource_keyword WHERE resource_type_field = ? LIMIT 1000", array("i", $fixed_field));
                $c = $c - 1000;
                echo " -- " . $c . " rows remaining to delete" . $LINE_END;
            }
        }
    }
} else {
    echo " - Skipping flush_unused_keywords - already complete" . $LINE_END;
}
