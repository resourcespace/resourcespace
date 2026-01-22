<?php
include "../../include/boot.php";

include "../../include/authenticate.php";
if (!checkperm("a")) {
    exit("Permission denied");
}
# Specify whether you want to have table_* and index_* files created
$createTableStructure = true;
$createIndices = true;
$createData = false;

# Use the below to set which tables we will extract data for - empty array means all tables.
#$dataFor=array("usergroup","resource_type_field","site_text","user","collection","user_collection","report","preview_size","resource_type");
$dataFor = array();
$tableFor = array();
$indicesFor = array();

if (getval("execute", "") != "" && enforcePostRequest(false)) {
    # Fetch all tables
    $tables = ps_query("show tables");
    for ($n = 0; $n < count($tables); $n++) {
        $table = $tables[$n]["Tables_in_" . $mysql_db];

        # Table structure
        if ($createTableStructure && (in_array($table, $tableFor) || count($tableFor) === 0)) {
            $f = fopen("../../dbstruct/table_" . $table . ".txt", "w");
            $describe = ps_query("describe `$table`");
            for ($m = 0; $m < count($describe); $m++) {
                fputcsv($f, $describe[$m]);
            }
            fclose($f);
        }

        # Indices
        if ($createIndices && (in_array($table, $indicesFor) || count($indicesFor) === 0)) {
            $f = fopen("../../dbstruct/index_" . $table . ".txt", "w");
            $index = ps_query("show index from `$table`");
            for ($m = 0; $m < count($index); $m++) {
                fputcsv($f, $index[$m]);
            }
            fclose($f);
        }

        # Data
        if ($createData && (in_array($table, $dataFor) || count($dataFor) === 0)) {
            $f = fopen("../../dbstruct/data_" . $table . ".txt", "w");
            $index = ps_query("select * from `$table`"); // Select * is fine here as no parameters
            for ($m = 0; $m < count($index); $m++) {
                fputcsv($f, $index[$m]);
            }
            fclose($f);
        }
    }
    printArray('Created tables', $createTableStructure, $tableFor);
    printArray('Created indices for tables', $createIndices, $indicesFor);
    printArray('Created data for tables', $createData, $dataFor);
} else {
    if (!$createTableStructure && !$createIndices && !$createData) {
        die('Current configuration does nothing. Please alter dbstruct_create.php to your needs.');
    }
    ?>
    <p>This tool is for ResourceSpace developers only. It (re)creates the database structures defined in the 'dbstruct' folder using the current database as a master. Do not run this unless you are sure what it does. Do not commit the changed dbstruct files back to Subversion unless you intend to alter the database structure for all installations.</p>
    <?php
    printArray('Creates tables', $createTableStructure, $tableFor);
    printArray('Creates indices for tables', $createIndices, $indicesFor);
    printArray('Creates data for tables', $createData, $dataFor);
    ?>
    <form method="post">
        <?php generateFormToken("dbstruct_create"); ?>
    <input type="submit" name="execute" value="Execute">
    </form>
    <?php
}

function printArray($label, $show, $array)
{
    if (!$show) {
        return;
    }

    echo '<p><b>' . $label . ':</b> ';

    if (count($array) == 0) {
        echo 'for all tables</p>';
        return;
    }

    $first = true;
    foreach ($array as $item) {
        if ($first) {
            $first = false;
        } else {
            echo ', ';
        }
        echo $item;
    }

    echo '</p>';
}
?>
