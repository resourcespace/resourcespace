<?php

#
# Script to update the resource table with values from resource_data when data joins are added or removed.
# Use "add=" to update a specific field or list of fields
# Use "remove=" to drop the column of a specific field or list of fields
# If neither are set the script will use $data_joins to add AND remove fields
#
include "../../include/boot.php";
if ('cli' != PHP_SAPI) {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
    $add = getval("add", "");
    $remove = getval("remove", "");
} else {
    $shortopts = "ha:r:";
    $longopts = array("help","add:","remove:");
    $clargs = getopt($shortopts, $longopts);

    if (isset($clargs["help"]) || isset($clargs["h"])) {
        echo "Usage php update_data_joins.php [OPTIONS]" . PHP_EOL . PHP_EOL;
        echo "Optional arguments:-" . PHP_EOL;
        echo "-a, --add     A list of fields to add i.e. --add <comma separated list of numbers>" . PHP_EOL;
        echo "-r, --remove  A list of fields to remove i.e. --remove <comma separated list of numbers>" . PHP_EOL;
        echo "Examples:-" . PHP_EOL;
        echo "   php update_data_joins.php --add 18" . PHP_EOL;
        echo "   - This will add field18 to the resource table and update the data join for" . PHP_EOL;
        echo "     field 18 (usually description/caption) for all resources." . PHP_EOL;
        echo "Examples:-" . PHP_EOL;
        echo "   php update_data_joins.php --remove 51" . PHP_EOL;
        echo "   - This will remove field51 from the resource table" . PHP_EOL;
        echo "   php update_data_joins.php" . PHP_EOL;
        echo "   - This will add all fields in \$data_joins to the resource table and remove fields not present" . PHP_EOL;
        exit();
    }
    $add = $clargs["add"] ?? $clargs["a"] ?? "";
    $remove = $clargs["remove"] ?? $clargs["r"] ?? "";
}

$all = (($add == '' && $remove == '') ? true : false);

$fields = ps_array(
    "SELECT `COLUMN_NAME` value
    FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA`='{$mysql_db}'
        AND `TABLE_NAME`='resource'
        AND `COLUMN_NAME` REGEXP 'field\\\\d+' = 1",
    []
);
echo "fields: " . json_encode($fields) . "<br/>";

$resource_table_joins = get_resource_table_joins();
echo "resource_table_joins: " . json_encode($resource_table_joins) . "<br/>";
if ($remove !== '' || $all) {
    // drop tables first
    if ($all) {
        // we need to create a list of fields that aren't in $resource_table_joins
        $remove = array();
        foreach ($fields as $field) {
            $field_ref = substr($field, (strlen("field")));
            if (!in_array($field_ref, $resource_table_joins)) {
                $remove[] = (int)$field_ref;
            }
        }
    } else {
        $remove = explode(",", $remove);
        $r_count = count($remove);
        for ($r = 0; $r < $r_count; $r++) {
            if (!in_array("field" . $remove[$r], $fields)) {
                unset($remove[$r]);
            }
        }
        $remove = array_filter(array_values($remove), "is_int_loose");
    }
    if (count($remove) > 0) {
        foreach ($remove as $column) {
            echo "Dropping column $column...";
            $wait = ps_query("ALTER TABLE resource DROP COLUMN field" . (int)$column);
            echo "done!<br/>";
            flush();
        }
        echo "Done dropping columns<br/><br/>";
    } else {
        echo "No columns to drop.<br/><br/>";
    }
} else {
    echo "No columns to drop.<br/><br/>";
}
flush();
if ($add !== '' || $all) {
    if ($all) {
        $add = $resource_table_joins;
    } else {
        $add = explode(",", $add);
        $a_count = count($add);
        for ($a = 0; $a < $a_count; $a++) {
            if (!in_array($add[$a], $resource_table_joins)) {
                echo "Field " . escape($add[$a]) . " is not part of \$resource_table_joins...removing from list<br/>";
                unset($add[$a]);
            }
        }
        $add = array_values($add);
    }
    if (count($add) > 0) {
        foreach ($add as $column) {
            echo "Updating column field$column...";
            update_fieldx($column);
            echo "done!<br/>";
            flush();
        }
        echo "Done updating columns<br/><br/>";
    } else {
        echo "No columns to update.<br/><br/>";
    }
} else {
    echo "No columns to update.<br/><br/>";
}

echo "Done with updating and removing data_join_columns from the resource table.<br/>";
